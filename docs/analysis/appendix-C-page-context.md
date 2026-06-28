# Part 3 — Page-Type & Context Resolution

**Dimension:** How does the app know which page / entity / language it is on, and is that
classification centralised or scattered?

**Verdict in one line:** There is **NO central Page Type Resolver**. Page-type, main-entity,
language and indexability are each decided independently — in at least three incompatible ways —
inside the individual plugins that need them. This confirms the gap flagged in `Procena2.txt`
(section "4. Dodati → A. Page Type Resolver", priority #1 for v1.0) and `Procena3.txt`.

All file paths below are relative to the workspace root
(`c:/Users/User/Desktop/ai-bost-joomla/`).

---

## 1. What the context layer actually exposes

The only shared "where am I" seam is **`AppContextInterface`**
(`aiboost-joomla/component/lib/src/AppContextInterface.php`), implemented by
**`JoomlaAppContext`** (`…/lib/src/JoomlaAppContext.php`) and the stub
**`WpAppContext`** (`…/lib/src/WpStub/WpAppContext.php`).

The interface exposes **low-level request primitives only** — it deliberately does NOT classify
the page:

| Method | Returns | What it is |
|---|---|---|
| `isHomepage(): bool` | bool | the *only* page-type judgement in the shared layer |
| `getCurrentOption(): string` | `com_content`, … | raw Joomla request input |
| `getCurrentView(): string` | `article`, `category`, `featured`, … | raw Joomla request input |
| `getCurrentId(): int` | id | raw Joomla request input |
| `getActiveLanguage(): string` | e.g. `de-DE` | active request language |
| `getDefaultLanguage(): string` | e.g. `en-GB` | site default language |
| `getPathway(): array` | breadcrumb steps | for BreadcrumbList |
| `getPageTitle()/getPageDescription()` | document strings | base title/desc |

**There is no `getPageType()`, no `getMainEntity()`, no `isIndexable()` / `isNoindex()`, no
`getCanonical()` on the interface.** Each consumer is handed the raw `option/view/id` triple and
must classify the page itself. So the classification logic is **structurally pushed out to the
edges** by design of the interface, not centralised.

There is also no `PageType`, `PageContext`, `EntityRegistry`, `Indexability` or `Resolver` class
anywhere under `aiboost-joomla/component/lib/src/` (verified by directory listing — the lib holds
`HeadBlockBuilder`, `BodyBlockBuilder`, builders, `LanguageService`, `LanguageDetector`,
`ConflictManager`, etc., but nothing that classifies a page).

---

## 2. Homepage detection — TWO different definitions of "home"

### 2a. `JoomlaAppContext::isHomepage()` — the authoritative one
(`…/lib/src/JoomlaAppContext.php`, lines 76–104)

Resolves home from the **active menu item's `home` flag**
(`$app->getMenu()->getActive()->home === 1`), with a bare-root path check (`'' || 'index.php'`)
kept only as a fallback when there is no active menu item. Its own docblock explains this fixed a
bug where the old path/`view=featured` heuristic "mis-fired site-wide … emitting homepage-only
schema (WebSite/SearchAction) on every page". This is the correct Joomla way and is the one
consumed by `SchemaBuilder::buildWebSite()`.

A **source-level contract test** locks this in:
`…/component/tests/Lib/HomepageDetectionContractTest.php` asserts `isHomepage()` must call
`getActive(`, must read `->home`, and must **NOT** contain `'featured'`.

### 2b. `AiBoostCore::detectPageType()` — a SECOND, contradicting definition
(`…/component/plugins/system/aiboost_core/src/Extension/AiBoostCore.php`, lines 376–413)

The core plugin has its **own private** page classifier used for title/meta-description template
selection (called at lines 461 and 518). It returns
`'home' | 'article' | 'category' | 'search' | 'tag' | 'default'` and decides "home" via:

```php
$path = ltrim(Uri::getInstance()->getPath(), '/');
if ($path === '' || $path === 'index.php') { return 'home'; }
if ($option === 'com_content' && $view === 'featured') { return 'home'; }
```

This is **exactly the heuristic that `isHomepage()`'s docblock and the contract test condemn as
having mis-fired site-wide** — yet it is still live here, classifying "home" for title templates.
So on a site whose homepage is a Featured menu item, `isHomepage()` (menu flag) and
`detectPageType()` (path/featured) can disagree about whether the same URL is the homepage. Two
sources of truth, one of them known-buggy, still coexist.

`detectPageType()` is `private` and used nowhere else — it is **not** exposed to the schema, OG,
sitemap, or AEO plugins, which each re-derive page type yet again (next section).

---

## 3. Per-page entity / page-type decisions are scattered and duplicated

Every plugin that needs "is this an article?" re-implements the same inline triple
`option === 'com_content' && view === 'article' && id > 0` against the raw request primitives —
there is no shared helper. Grep for the article/page-type pattern across
`component/plugins/system/` returns **22 occurrences in 11 files**:

| File | Occurrences | What it decides locally |
|---|---|---|
| `aiboost_core/.../AiBoostCore.php` | 5 | `detectPageType()` home/article/category/search/tag + category token |
| `aiboost_schema/.../SchemaProBuilder.php` | 5 | article / FAQ-autodetect / HowTo / Event gates |
| `aiboost_social/.../OgTagBuilder.php` + `OgTagProDecorator.php` | 2 | `og:type` + context block |
| `aiboost_sitemap/.../SitemapGenerator.php` + `NewsSitemapGenerator.php` + `HreflangSitemapExtension.php` | 4 | which URLs to enumerate |
| `aiboost_aeo/.../LlmsTxtGenerator.php` + `LlmsTxtProGenerator.php` + `AiBoostAeo.php` | 5 | which content to list |
| `aiboost_schema/.../AiBoostSchema.php` | 1 | dispatch gating |

### Concrete proof — the article gate is copy-pasted, not shared

In **`SchemaProBuilder.php`** the identical guard appears **four separate times**, once per block
builder:

- `buildArticle()` (line 373): `if ($this->option !== 'com_content' || $this->view !== 'article' || $this->id <= 0) return null;`
- `buildEvent()` (line 497): same triple
- `buildHowTo()` (line 575): same triple
- `collectFaqItems()` (line 312): same triple (for FAQ auto-detect)

Each builder independently re-reads `getCurrentOption/View/Id` (captured in the constructor, lines
60–62) and re-decides the page type. There is no single "this request is an Article entity with id
N" fact that all four share.

### The "main entity" concept does not exist as a resolved object

There is no notion of *the page's primary entity*. What happens instead:

- **`SchemaBuilder` (Free)** — `buildAll()` (lines 177–199) emits **Organization + WebSite +
  BreadcrumbList on essentially every page**, gated only by `isHomepage()` (for WebSite) and the
  presence of a pathway (for Breadcrumb). The base Free schema does **not** vary by article /
  category / contact — the same Organization block ships site-wide. It carries a stable
  `@id` (`…/#organization`, `…/#website`) — a partial "entity registry by convention" — but only
  for those two nodes; there is no registry for people/authors/locations/products
  (the `Procena2` "B. Entity Registry" recommendation is unmet).
- **`SchemaProBuilder` (Pro)** — the *only* layer that adds entity-specific blocks (Article,
  BlogPosting/NewsArticle/TechArticle via `resolveArticleType()` line 668, FAQ, HowTo, Event), and
  it does so by re-classifying the page itself per block. So "what is the main entity of this page"
  is answered **only in Pro, only for `com_content` articles, and re-derived four times**.

### Language resolution is split across two unrelated mechanisms

1. **Per-request active language** — `getActiveLanguage()` (falls back to `getDefaultLanguage()`,
   not hardcoded English) and `getDefaultLanguage()` (reads global `language` config). Thin wrapper
   `LanguageService::getCurrentTag()` (`…/lib/src/LanguageService.php`). This is what
   `SchemaProBuilder`/`OgTagProDecorator` pass to `TranslationService::get(key, lc, fallback)` to
   overlay per-language strings.
2. **Translation-source authority** — `LanguageDetector::detect()`
   (`…/lib/src/LanguageDetector.php`) is a *separate*, statically-cached resolver that decides
   whether Joomla-native / Falang / JoomFish translations are authoritative
   (`preferred_source`). It answers "which translation store wins", NOT "what language is this
   request" — the two are not unified under one page-context object.

So even **language** — the dimension the product's whole multilingual edge rests on — is resolved
through two independent code paths with no shared context node.

---

## 4. Indexability is never centrally resolved (and never honoured cross-sink)

This is the most consequential gap for SEO correctness. There is **no `isIndexable()` anywhere**,
and no plugin computes or emits a per-page robots/noindex decision that the other output sinks
respect:

- **Canonical** — `AiBoostCore::resolveCanonical()` (lines 352–369) builds canonical from a URL map
  or the bare current URL. It does **not** consult any indexability state — it will emit a canonical
  on a page another layer might consider non-indexable.
- **No per-page `noindex` emitter** — grep for `noindex` / `X-Robots-Tag` /
  `setMetaData(...robots...)` across the core plugin returns nothing. The product never sets
  `<meta name="robots" content="noindex">` per page based on page type; it only manages **site-wide
  `/robots.txt`** bot rules (`aiboost_aeo/.../RobotsTxtManager.php`, `RobotsBotRules.php`).
- **Sitemap has its OWN private indexability rule** —
  `aiboost_sitemap/.../SitemapGenerator.php` filters by `a.state = 1`, `c.published = 1`, and guest
  ACL `access IN (...)` (lines 176–190, 237–365). That is a *fourth* independent definition of
  "what counts as a real, indexable page", not shared with schema / canonical / OG. A page can be
  excluded from the sitemap yet still receive full schema + canonical, or vice-versa, with nothing
  reconciling them.
- **`Procena3.txt` risk** (lines 40, 128–129): the Markdown/`llms.txt` AI-discovery layer can create
  indexable duplicate content unless served `noindex` / kept out of the sitemap — precisely the kind
  of cross-sink indexability decision that only a central resolver could enforce. Today nothing owns
  it.

---

## 5. WordPress-readiness consequence (owner's "WP-ready" question)

The context seam is **Joomla-shaped at the interface level**, which is a real portability problem:
`AppContextInterface` exposes `getCurrentOption()` / `getCurrentView()` / `getCurrentId()` —
concepts that only exist in Joomla. Every page-type and entity decision in the codebase is written
as `option === 'com_content' && view === 'article'` against those primitives.

`WpAppContext` (`…/lib/src/WpStub/WpAppContext.php`) is a stub whose `getCurrentOption/View` return
`''` and `getCurrentId()` returns `0`. **As written, on WordPress every article/FAQ/HowTo/Event
gate evaluates false** — so the entire entity-schema layer is structurally inert under WP until the
classification is re-expressed in CMS-neutral terms. A central, CMS-neutral Page Type Resolver
(returning `{type, entityId, entityKind, language, indexable, canonical}`) is exactly the seam that
would let the ~65% portable generation logic (`docs/ARCHITECTURE-BOUNDARIES.md`) actually run on a
second CMS. Its absence is therefore both a *correctness* gap on Joomla and the single biggest
*portability* blocker.

---

## 6. Summary table — where each context fact is decided

| Context fact | Centralised? | Where it lives | Problem |
|---|---|---|---|
| Is homepage? | Partly | `JoomlaAppContext::isHomepage()` (menu flag) **vs** `AiBoostCore::detectPageType()` (path/featured) | Two definitions, one known-buggy still live |
| Page type (article/category/…) | **No** | re-derived inline in 11 files (22×) | Copy-pasted `option/view/id` triple; no shared classifier |
| Main entity of the page | **No** | only `SchemaProBuilder`, Pro-only, com_content-only, 4× re-derived | No entity object; no Entity Registry beyond `#organization`/`#website` @ids |
| Active language | Partly | `getActiveLanguage()` + `LanguageService` | OK, but separate from translation-source authority |
| Translation source authority | Yes (own silo) | `LanguageDetector::detect()` | Not unified with language resolution |
| Indexability / noindex | **No** | implicit; sitemap has its own SQL rule; no per-page robots emitter | No `isIndexable()`; sinks can disagree |
| Canonical | Local | `AiBoostCore::resolveCanonical()` | Ignores indexability |

**Bottom line:** the foundation answers "where am I?" with a scatter of per-plugin heuristics over
raw Joomla request input, including two contradictory homepage definitions and four independent
notions of "indexable". `Procena2`/`Procena3` flagged a missing central Page Type Resolver +
Entity Registry — the code confirms it. Building that single resolver (page type + main entity +
language + indexability + canonical, in CMS-neutral terms) is the highest-leverage foundation fix:
it removes the duplication, reconciles the homepage/indexability disagreements, and is the
prerequisite that makes the entity-schema layer portable to WordPress.
