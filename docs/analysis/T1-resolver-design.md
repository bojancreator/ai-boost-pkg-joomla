# T1 — Page-Type / Entity / Indexability / Canonical Resolver — Design (for approval)

> **Status:** DESIGN ONLY — no code written. This document proposes the single CMS-neutral resolver that
> §10 "T1" of `docs/analysis/architecture.md` calls the product's #1 pre-sale foundation fix. It defines
> *where the resolver lives, its interface, how every current consumer migrates onto it, and a safe
> slice-by-slice migration plan*. **Nothing is implemented until Bojan approves this design.**
> Code-verified against `refactor/structural`, order 0018, 2026-06-28.

---

## Plain-Serbian summary (for Bojan)

Naš dodatak trenutno **nema jedno mesto koje zna „na kojoj sam stranici"** — da li je to početna strana,
članak, kategorija; koji je glavni „entitet" na njoj; na kom je jeziku; koja je zvanična (canonical)
adresa; i — najvažnije — **da li ta stranica sme da se indeksira u Google-u**. Umesto toga, svaki deo
(schema, društvene mreže, sitemap, llms.txt, Markdown) sam, za sebe, donosi tu odluku — i te odluke se
**ponavljaju na ~20 mesta** i ponekad **protivreče** jedna drugoj. Najgore: postoje **dve različite
definicije početne strane**, od kojih je jedna poznato pogrešna, i **četiri različita shvatanja šta je
„stranica koja se indeksira"**. To je „pukotina u temelju" — radi danas, ali je krhko i pravi rizik od
duplog sadržaja, i jedina je prava prepreka da jednog dana pređemo na WordPress.

Ovaj dokument predlaže da napravimo **jedan „rešavač" (resolver)** — jedno mesto koje za tekuću stranicu
da jasne odgovore: tip stranice, glavni entitet, jezik, canonical adresu i „sme li da se indeksira".
Onda **sve ostalo pita njega**, umesto da svako računa za sebe. Tako nestaje ponavljanje, nestaju
protivrečnosti, i kasniji prelazak na WordPress postaje moguć.

Najvažnije: predlažem da se to uradi **postepeno, parče po parče**, gde **svako parče ne menja ponašanje**
(samo preusmeri kod da pita jedno mesto), pa ga proveravamo na test-sajtu. **Samo jedna stvar namerno
menja ponašanje** — ujednačavanje definicije početne strane na ispravnu (po Joomla „home" oznaci menija) —
i nju posebno proveravamo. Ništa se ne kvari pred prodaju jer se ide sitnim, proverljivim koracima.

**ODLUKA:** Da li odobravaš ovaj nacrt (mesto, oblik „rešavača", mapu prelaska i plan po parčićima) da
bismo počeli prvo parče? Ako da, prvo parče je da napravimo „rešavač" i pustimo ga da radi **paralelno,
bez ikakvog uticaja**, dok postojeći kod ostaje netaknut — pa tek onda jedan po jedan deo prebacujemo na
njega. (Detaljna pitanja/varijante su na dnu kao `ODLUKA D1–D5`.)

---

## 1. The problem this resolver solves (verified)

From `architecture.md` §3 + §10-T1 and `appendix-C-page-context.md`, re-verified against live code on
`refactor/structural`:

1. **No central classifier.** `AppContextInterface` exposes only raw request primitives
   (`getCurrentOption/View/Id`, `isHomepage`, `getActiveLanguage`) — **no `getPageType()`,
   `getMainEntity()`, `isIndexable()`, `getCanonical()`. No `PageType` / `PageContext` / `Resolver` /
   `Indexability` class exists under `lib/src/` (verified by listing — see §10 inventory).
2. **Two contradictory homepage definitions, both live:**
   - `JoomlaAppContext::isHomepage()` (`JoomlaAppContext.php:76-104`) — **correct**, reads the active
     menu item's `home=1` flag; locked by `tests/Lib/HomepageDetectionContractTest.php`.
   - `AiBoostCore::detectPageType()` (`AiBoostCore.php:376-413`) — **buggy** path/`view=featured`
     heuristic, still live: drives `applyTitleTemplate()` (`:461`) and `applyMetaDescTemplate()`
     (`:518`). On a Featured-menu homepage the title/meta layer and the schema layer can disagree.
3. **The article gate is copy-pasted ~20× across ~9–11 files** — the inline triple
   `option==='com_content' && view==='article' && id>0` (full call-site map in §4).
4. **No indexability authority.** No `isIndexable()`, no per-page `noindex`/X-Robots emitter keyed to
   page type. Canonical (`AiBoostCore::resolveCanonical()`, `:352-369`) ignores indexability. The
   sitemap, news-sitemap, llms.txt and llms-full each apply their **own** `state=1`/`published=1`/ACL
   SQL filter — **four independent notions of "indexable"** that never reconcile. The Markdown/llms
   discovery layer can expose indexable duplicate content with nothing to mark it `noindex`.
5. **Language split** — per-request language (`getActiveLanguage`) is separate from translation-source
   authority (`LanguageDetector::detect`), and the "site default content language" (the SEO-correct
   default, `ComponentHelper::getParams('com_languages')->get('site')`) is read ad-hoc in a couple of
   places, NOT exposed centrally — distinct from `getDefaultLanguage()` which reads the *global* config
   default (`JoomlaAppContext.php:58-65`; see memory `joomla-site-default-vs-global-language.md`).
6. **Biggest WP-portability blocker.** `WpAppContext` returns `''`/`0`, so every `option/view/id` gate
   evaluates false on WordPress — the whole entity-schema layer is inert there until classification is
   re-expressed CMS-neutrally.

---

## 2. The resolver — responsibilities & shape

### 2.1 What it answers (for the current request)

A single value object — **`PageContext`** — produced once per request by **`PageResolver`**:

| Field | Type | Meaning |
|---|---|---|
| `type` | `PageType` enum (string-backed) | `HOMEPAGE`, `ARTICLE`, `CATEGORY`, `FEATURED`, `CONTACT`, `TAG`, `SEARCH`, `MENU_OTHER`, `COMPONENT_OTHER`, `UNKNOWN` |
| `entityKind` | string | CMS-neutral entity kind: `article` \| `category` \| `contact` \| `tag` \| `site` \| `''` |
| `entityId` | int | the primary entity's id (0 when none) |
| `option` / `view` / `rawId` | string/int | raw primitives, preserved for edge cases + debugging |
| `isHomepage` | bool | the ONE homepage truth (menu `home=1`) |
| `language` | string | active request language tag (e.g. `de-DE`) |
| `siteDefaultLanguage` | string | **site default content** language (`com_languages` `site`), the SEO/x-default default |
| `globalDefaultLanguage` | string | global config default (kept for back-compat; rarely the right one) |
| `canonical` | string | resolved canonical URL for this page |
| `indexable` | bool | the SINGLE indexability authority every emitter consults |
| `noindexReason` | string | why not indexable (`''` when indexable) — for Health + debugging |

`PageType` is an enum so a `match` is exhaustive and adding a type forces every consumer to consider it
(compile-ish safety via tests). `entityKind` is the **CMS-neutral** projection — Joomla's
`com_content.article` and a future WP `post` both map to `entityKind='article'`, which is what kills the
WP blocker.

### 2.2 Interface sketch (proposed — subject to approval, not final code)

```php
namespace AiBoost\Lib\Page;            // new sub-namespace under lib/src/Page/

enum PageType: string {
    case HOMEPAGE = 'homepage';   case ARTICLE = 'article';   case CATEGORY = 'category';
    case FEATURED = 'featured';   case CONTACT = 'contact';   case TAG = 'tag';
    case SEARCH = 'search';       case MENU_OTHER = 'menu_other';
    case COMPONENT_OTHER = 'component_other'; case UNKNOWN = 'unknown';
}

final class PageContext {
    public function __construct(
        public readonly PageType $type,
        public readonly string $entityKind,
        public readonly int    $entityId,
        public readonly string $option,
        public readonly string $view,
        public readonly int    $rawId,
        public readonly bool   $isHomepage,
        public readonly string $language,
        public readonly string $siteDefaultLanguage,
        public readonly string $globalDefaultLanguage,
        public readonly string $canonical,
        public readonly bool   $indexable,
        public readonly string $noindexReason,
    ) {}

    public function isArticle(): bool  { return $this->type === PageType::ARTICLE && $this->entityId > 0; }
    public function isCategory(): bool { return $this->type === PageType::CATEGORY; }
    // …thin helpers replace the inline triples (see §4)
}

interface PageResolverInterface {
    public function resolve(): PageContext;     // memoised per request
}

final class PageResolver implements PageResolverInterface {
    public function __construct(
        private readonly AppContextInterface $ctx,
        private readonly IndexabilityPolicy  $indexability,
        // entity/menu lookups go through the Cms DatabaseAdapter, never direct Joomla calls
    ) {}
    public function resolve(): PageContext { /* builds + caches the PageContext */ }
}
```

### 2.3 Indexability authority — two faces, one policy

Indexability has two *uses* with different performance shapes; both must read **one** policy so they can
never drift:

- **Per-request** — `PageContext::indexable` for the page being rendered. Drives: (a) an optional
  per-page `<meta name="robots" content="noindex">` emitter (new, behind a setting); (b) whether the
  Markdown alternate is served `noindex` / the X-Robots header is correct; (c) whether canonical should
  self-reference.
- **Item-level (bulk)** — the sitemap / news-sitemap / llms / llms-full enumerate *thousands* of items
  and cannot call a per-item PHP predicate cheaply. So the policy also exposes a **single shared
  constraint contributor** the enumerators apply to their queries, instead of each hand-writing
  `state=1 / published=1 / access IN (...)`.

```php
final class IndexabilityPolicy {
    // Per-request decision from a resolved (or partly-resolved) context.
    public function isIndexable(PageContext|PartialContext $c): array; // [bool, reasonString]

    // ONE definition of "a publicly-indexable content item", applied by every bulk enumerator.
    // CMS-neutral intent; the Joomla impl contributes the WHERE fragment via the DatabaseAdapter.
    public function articleListConstraints(QueryShape $q): void;
    public function categoryListConstraints(QueryShape $q): void;
    public function menuListConstraints(QueryShape $q): void;
}
```

The **decision logic** (what "indexable" means: published + within publish window + guest-visible ACL +
not an excluded type + honour any future per-item noindex flag) lives once, CMS-neutrally. The **query
fragment** that expresses it for Joomla lives in the Joomla side (it already exists, copy-pasted, in
`SitemapGenerator`); we lift it to the policy so all four enumerators share it. On WP the same policy
intent is expressed against WP queries later — but the policy *decision* is written once now.

> This is what closes architecture.md **B2** ("make Markdown/llms noindex + out-of-sitemap as part of
> T1's indexability authority") and the Procena3 duplicate-content hazard.

### 2.4 Placement & wiring (CMS-neutral)

- **Lives in** `component/lib/src/Page/` — `PageType.php`, `PageContext.php`, `PageResolver.php`,
  `PageResolverInterface.php`, `IndexabilityPolicy.php`. Same `AiBoost\Lib` family, guarded with
  `class_exists`/`enum_exists` like every other lib class (bundled into each plugin ZIP by the build).
- **Reads context only through `AppContextInterface`** (+ the `Cms` `DatabaseAdapter` for entity/menu
  lookups) — **no direct `Factory`/`Uri`/`#__` calls in the resolver**, per the cross-platform boundary
  rule (`ARCHITECTURE-BOUNDARIES.md`). That is precisely what makes it WP-portable.
- **Obtained via the existing registry.** Today plugins do `new JoomlaAppContext()` (4 sites:
  `AiBoostAeo.php:107,118,152`, `AiBoostSchema.php:99`, `AiBoostSocial.php:100`) and
  `AdapterRegistry` already vends the other adapters. Add `AdapterRegistry::pageResolver()` +
  `setPageResolver()`, wired in `Cms/AdapterBootstrap.php` (next to the 8 existing `set*` calls). One
  resolver instance per request; `resolve()` memoises the `PageContext`. Plugins then call
  `AdapterRegistry::pageResolver()->resolve()` instead of re-deriving context.
- **Lifecycle:** the resolver is request-scoped and built lazily on first `resolve()`. It must reset on
  `AdapterRegistry::reset()` (already exists for tests / future persistent runtimes).

---

## 3. The ONE intended behaviour change (everything else is behaviour-preserving)

**Unify homepage detection on the menu `home=1` flag.** Today `applyTitleTemplate`/`applyMetaDescTemplate`
classify "home" via the buggy path/featured heuristic (`detectPageType()`); the resolver will make them
use `PageContext::isHomepage` (= `JoomlaAppContext::isHomepage()`, the contract-tested truth).

- **Effect:** on a site whose homepage is a **Featured** or **Single-Article** menu item, the title/meta
  templates will now treat that page as `home` correctly (and, conversely, will stop treating a random
  `view=featured` blog sub-page or an `index.php` non-SEF URL as "home"). This *aligns* title/meta with
  the schema layer, which already uses the menu flag.
- **Verification (must be shown on staging):** on a Featured-home site, confirm `title_template_home` /
  `meta_desc_template_home` apply on the homepage and NOT on inner featured/blog pages; confirm a
  non-home `view=featured` page now uses `*_default` not `*_home`. Capture before/after `<title>` +
  `<meta name="description">` on (a) the home menu item, (b) an inner blog page, (c) an article.
- This is the only slice that may change output; it gets its own order and its own sign-off.

---

## 4. Consumer migration MAP (every call site)

Goal: every place that today decides homepage / page-type / article-gate / indexability / canonical /
language adopts the resolver. Sites verified by grep on `refactor/structural`
(`com_content` = 44 across 16 files; literal `'article'` view-check = 20 across 9 files — matches the
architecture.md verification log "~20+ across ~9–11 files").

### 4.1 Homepage definitions (2)

| # | Site | File:line | Today | After |
|---|---|---|---|---|
| H1 | authoritative | `JoomlaAppContext::isHomepage()` `:76-104` | menu `home=1` (correct) | becomes the resolver's homepage source; method stays (resolver delegates to it) |
| H2 | **buggy, retire** | `AiBoostCore::detectPageType()` `:376-413` → used by `applyTitleTemplate` `:461` + `applyMetaDescTemplate` `:518` | path/`view=featured` heuristic | `detectPageType()` deleted; both template methods read `PageContext::type` / `isHomepage` (the §3 behaviour change) |

### 4.2 Page-type / entity / article gates

| # | Site | File:line | Today decides | After |
|---|---|---|---|---|
| P1 | `AiBoostCore::detectPageType()` | `AiBoostCore.php:376-413` | home/article/category/search/tag/default for templates | replaced by `PageContext::type` (`match`) |
| P2 | `AiBoostCore::resolveCategoryToken()` | `AiBoostCore.php:418-439` | article gate → category title token | `if ($pc->isArticle())`; category id from `PageContext` |
| P3 | `SchemaProBuilder` ctor | `SchemaProBuilder.php:60-62` | captures option/view/id | inject `PageContext` |
| P4 | `SchemaProBuilder::collectFaqItems()` | `:312-313` | article gate (FAQ auto-detect) | `if ($pc->isArticle())` |
| P5 | `SchemaProBuilder::buildArticle()` | `:373` | article gate | `if (!$pc->isArticle()) return null;` |
| P6 | `SchemaProBuilder::buildEvent()` | `:497` | article gate | same |
| P7 | `SchemaProBuilder::buildHowTo()` | `:575` | article gate | same |
| P8 | `SchemaProBuilder::resolveArticleType()` | `:668-676` | article @type (entity sub-type) | reads `PageContext` entity id; @type override stays |
| P9 | `AiBoostSchema` dispatch | `AiBoostSchema.php:99,113-115,127` | builds `new JoomlaAppContext()` + context array + Pro gate | build/pass `PageContext`; Pro gate unchanged |
| P10 | `SchemaBuilder` (Free) `buildAll/buildWebSite` | `SchemaBuilder.php:177-199, 767-812` | `buildWebSite()` gated by `isHomepage()` `:769`; Org/Breadcrumb site-wide | `buildWebSite()` reads `PageContext::isHomepage`; Org/Breadcrumb unchanged |
| P11 | `OgTagBuilder` (Free) | `OgTagBuilder.php:67-69, 100` | option/view/id → `og:type=website` | reads `PageContext`; Free stays website-only |
| P12 | `OgTagProDecorator` | `OgTagProDecorator.php:123` | article gate → per-article OG | `if ($pc->isArticle())`; rest unchanged |

### 4.3 Indexability (4 private notions today → one authority)

| # | Site | File:line | Today | After |
|---|---|---|---|---|
| I1 | Sitemap | `SitemapGenerator.php:176-190` (+237-365) | own SQL `state=1`/publish window/ACL | applies `IndexabilityPolicy::articleListConstraints()` etc. |
| I2 | News sitemap | `NewsSitemapGenerator.php:103` | own `a.state=1` filter | same policy |
| I3 | llms.txt | `LlmsTxtGenerator.php:333` | own `state=1` filter | same policy |
| I4 | llms-full / categories | `LlmsTxtProGenerator.php:517,559,579-581` | own `state=1`/`access=1`/`published=1` filters | same policy |
| I5 | **(missing today)** per-page noindex | — | no per-page `noindex`; X-Robots emits `index,follow` site-wide (`AiBoostAeo.php:219`) | new: per-page emitter driven by `PageContext::indexable`; Markdown alternate + X-Robots respect it |
| I6 | Markdown discovery/serve | `AiBoostAeo.php:148, 224-233` (detect/serve) | serves Markdown alternate, no noindex authority | consult `PageContext::indexable`; mark Markdown alternate noindex / out-of-sitemap (closes B2 hazard) |

### 4.4 Canonical (1)

| # | Site | File:line | Today | After |
|---|---|---|---|---|
| C1 | `AiBoostCore::resolveCanonical()` | `AiBoostCore.php:352-369` | URL map or bare current URL; ignores indexability | resolver owns canonical (`PageContext::canonical`); URL-map logic preserved; may consult indexability |

### 4.5 Language (3 paths → exposed via context)

| # | Site | File:line | Today | After |
|---|---|---|---|---|
| L1 | active language | `JoomlaAppContext::getActiveLanguage()` `:48-56` | per-request tag | surfaced as `PageContext::language` (unchanged value) |
| L2 | site default content lang | ad-hoc `ComponentHelper::getParams('com_languages')->get('site')` (`AiBoostIntFalang.php:283`; sitemap B7) | scattered | surfaced as `PageContext::siteDefaultLanguage` (one source) |
| L3 | translation-source authority | `LanguageDetector::detect()` | separate silo ("which store wins") | **stays separate** for now — different concern (store priority, not page language); the resolver only *exposes* language facts. Unifying L3 is explicitly out of T1 scope (note for a later step). |

---

## 5. Safe incremental migration plan (slice by slice)

Each slice is one future `change` order, **behaviour-preserving unless marked**, independently
verifiable on staging (Pro + Free), with the `finalize()` output proof and Health green. Slices are
ordered so nothing breaks near launch and each unblocks the next.

- **S0 — Build the resolver, wire it, DON'T consume it yet.** Add `lib/src/Page/*` + `IndexabilityPolicy`
  + `AdapterRegistry::pageResolver()` + bootstrap. No existing call site changes. Ship it dormant.
  *Proof:* unit tests for `PageResolver` (see §6); zero output diff on staging (nothing consumes it).
  *Risk:* ~nil. *Rollback:* remove the dormant classes.

- **S1 — Add characterization tests FIRST** (before any consumer moves). Lock current output of the 12
  P-sites + 4 I-sites on a fixture site (home, article, category, featured, tag, search, restricted
  article) so later slices prove "no behaviour change". *Proof:* tests pass on untouched code. *Risk:*
  nil. (This precedes every behaviour-preserving slice; do it once.)

- **S2 — Migrate the Schema layer's article gates (P3–P10).** Replace the 4 `SchemaProBuilder` triples +
  ctor + Free `buildWebSite` homepage gate with `PageContext`. Pure delegation; output identical.
  *Proof:* schema JSON-LD byte-identical on the fixture pages (Pro + Free). *Rollback:* revert the one
  plugin.

- **S3 — Migrate Social (P11–P12).** `OgTagBuilder` + `OgTagProDecorator` read `PageContext`. Output
  identical. *Proof:* OG/Twitter tags identical. *Rollback:* revert.

- **S4 — Introduce the IndexabilityPolicy for bulk enumerators (I1–I4).** Point sitemap / news / llms /
  llms-full at the shared constraint contributor. **Behaviour-preserving target:** same URL set as
  today (the policy is seeded with exactly today's union rule). *Proof:* diff the generated
  sitemap.xml / llms.txt URL lists before/after — must be identical. *Risk:* medium (SQL parity) — the
  characterization diff is the guard. *Rollback:* revert; the 4 private filters are untouched until the
  diff is clean.

- **S5 — Canonical onto the resolver (C1).** `resolveCanonical()` logic moves into the resolver;
  `AiBoostCore` reads `PageContext::canonical`. Behaviour-preserving (URL-map + bare-URL logic copied
  verbatim). *Proof:* canonical identical on fixture pages incl. URL-map hits. *Rollback:* revert.

- **S6 — Expose language facts (L1–L2).** Surface `language` + `siteDefaultLanguage` via `PageContext`;
  switch the ad-hoc readers to it. Behaviour-preserving (same values; the B7 site-default fix already
  landed — this just centralises it). *Proof:* head/sitemap hreflang + x-default unchanged. *Rollback:*
  revert.

- **S7 — ⚠ THE behaviour change: unify homepage detection (H2, §3).** Delete `detectPageType()`; title/
  meta templates use `PageContext`. *Proof:* the §3 before/after capture on a Featured-home site +
  inner pages + article. *Risk:* the only intended-change slice — gets explicit Bojan sign-off and its
  own staging review. *Rollback:* restore `detectPageType()` (kept in git until S7 is signed off).

- **S8 — Per-page indexability emitter (I5–I6) — NEW capability, opt-in.** Add the per-page `noindex`
  emitter + make Markdown alternate / X-Robots respect `PageContext::indexable`; close the
  duplicate-content hazard. Behind a setting, default = current behaviour, so shipping it is safe; the
  new noindex behaviour is opt-in until verified. *Proof:* with the setting on, a non-indexable page
  emits `noindex` and drops from sitemap; with it off, output unchanged. *Risk:* medium (SEO-visible) —
  default-off de-risks launch. *Rollback:* the setting.

- **S9 — Cleanup + lock-in.** Delete now-dead inline gates, add a contract test that **fails if a new
  inline `option==='com_content' && view==='article'` triple appears outside `lib/src/Page/`** (prevents
  regression to scatter). Update CLAUDE.md / joomla-development skill with the resolver as the one place
  to ask "where am I".

**Ordering rationale:** S0/S1 are zero-risk scaffolding; S2–S6 are pure delegations that each shrink the
duplication without changing bytes; S7 (the one real change) lands only after the delegations prove the
resolver is faithful; S8 adds the new indexability capability last, opt-in; S9 prevents backsliding.
Launch can happen after any slice — every slice leaves the product shippable.

---

## 6. Risks, test strategy & rollback

**How each slice is proven behaviour-preserving — characterization tests (S1) + output diffs:**
- **Unit:** `PageResolverTest` over a fake `AppContextInterface` — asserts `type/entityKind/entityId/
  isHomepage/indexable/canonical/language` for every `PageType` (home via menu flag, article, category,
  featured, tag, search, restricted/unpublished article → `indexable=false` with reason). Mirrors and
  reuses the spirit of the existing `HomepageDetectionContractTest`.
- **Golden-output diffs on staging:** for each migration slice, capture the relevant artefact BEFORE
  (current code) and AFTER (slice) on the fixture pages and assert byte-identical: schema JSON-LD (S2),
  OG/Twitter (S3), sitemap.xml + llms.txt URL sets (S4), canonical (S5), hreflang/x-default (S6). The
  diff being empty IS the proof of "no behaviour change". S7 is the deliberate exception — its diff is
  reviewed, not required empty.
- **Health:** every slice ends with Health green on Pro + Free staging (no new warnings).

**Top risks & mitigations:**
1. *SQL parity in S4* (the bulk indexability lift) — biggest correctness risk. Mitigation: seed the
   policy with exactly today's union filter; gate the slice on an empty sitemap/llms URL diff; keep the
   old private filters in git until the diff is clean.
2. *Hidden coupling* — a consumer relies on a quirk of the buggy `detectPageType()`. Mitigation: S1
   characterization tests capture the quirk; S7 surfaces it as a reviewed diff, not a surprise.
3. *Per-request cost* — resolver does a menu/entity lookup. Mitigation: memoise per request; reuse
   lookups the consumers already do; lookups go through the cached `DatabaseAdapter`.
4. *WP stub* — `WpAppContext` still returns `''`/`0`. The resolver is built CMS-neutral now but WP
   *impl* stays frozen per W5; on WP it simply yields `UNKNOWN`/`indexable=false` until the WP content
   seam exists. No Joomla risk.

**Rollback:** every slice is one-plugin/one-file scoped and revertible in isolation; the dormant S0
resolver makes "stop consuming it" a safe no-op. `detectPageType()` and the 4 private filters are kept
in git until their replacement slice is signed off.

---

## 7. What is explicitly OUT of T1 scope

- **Entity Registry** beyond the existing `#organization`/`#website` `@id`s (people/products/locations) —
  Procena2 "B", a separate later step; T1 gives it the seam (`entityKind`/`entityId`) but does not build
  the registry.
- **Translation-source authority** unification (`LanguageDetector`, L3) — different concern; T1 only
  exposes language facts.
- **WordPress `WpAppContext` implementation** — frozen per architecture.md W5; T1 only keeps the seam
  CMS-neutral.
- **Marketing/claims, GSC integration, the multilingual translation finish** — B-branch items, not T1.

---

## 8. Open decisions for Bojan (`ODLUKA`)

- **ODLUKA (approval):** Approve this design — placement (`lib/src/Page/`), the `PageContext` shape, the
  consumer migration map, and the slice plan — so S0 (build dormant resolver) can start? Nothing changes
  behaviour until S7, which gets its own sign-off.
- **ODLUKA D1 — homepage change timing:** land the one behaviour change (S7, unify homepage detection) as
  part of T1, or split it into its own clearly-flagged release after the silent delegations? *(Recommend:
  keep in T1 but as the last, separately-reviewed slice.)*
- **ODLUKA D2 — per-page noindex (S8):** ship the new per-page `noindex` + Markdown/llms noindex authority
  default-OFF (opt-in, safest) or default-ON for new installs? *(Recommend: default-OFF until verified on
  staging, then revisit.)*
- **ODLUKA D3 — scope of the indexability lift (S4):** lift all four bulk enumerators onto the shared
  policy in T1, or only sitemap now and llms later? *(Recommend: all four — that's the point of one
  authority; the diff guard makes it safe.)*
- **ODLUKA D4 — regression lock (S9):** add the contract test that forbids new inline article-gate triples
  outside `lib/src/Page/`? *(Recommend: yes — it's what stops the scatter from coming back.)*
- **ODLUKA D5 — naming:** `PageResolver` / `PageContext` / `PageType` under `AiBoost\Lib\Page` acceptable,
  or prefer `ContextResolver` / `RequestContext`? *(Cosmetic; recommend the `Page*` names.)*

---

*Design only — no code changed (order 0018, read-only). Sources: `docs/analysis/architecture.md` §3 +
§10-T1, `docs/analysis/appendix-C-page-context.md`, and the live code on `refactor/structural` cited
inline. Related boundary rules: `docs/ARCHITECTURE-BOUNDARIES.md`. Implementation begins only after Bojan
approves, one slice per `change` order.*
