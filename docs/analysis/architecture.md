# AI Boost for Joomla — Architecture Analysis & Architect's Verdict

> **Pre-sale architecture review, synthesised 2026-06-28.** Owner's thesis: the product's core
> deliverable is *"code & text"* placed into the right OUTPUT SINKS — translatable, Google-readable,
> AI-visible. The owner asks three questions: **is the FOUNDATION (the root) solid; is it genuinely
> EXTENSIBLE via plugins; is it WordPress-ready** — before shipping to sale. Orientation:
> **SOLID FOUNDATION FIRST.**
>
> This document is the assembled verdict of seven evidence-cited section reviews (parts 1–8) plus two
> adversarial critic passes (a foundation skeptic on parts 1/3/5 and a security skeptic on part 6) and a
> one-person/near-sale over-engineering pass (part 8). Where the reviews disagreed, the critique is
> reflected honestly — not averaged away. Every load-bearing claim names the file/class/method that
> proves it. Source code read directly; sources reused (not re-derived): `docs/feature-verification.md`,
> `docs/ARCHITECTURE-BOUNDARIES.md`, `_handoff/docs/option-map.json` (276 options), `Procena1/2/3.txt`.
>
> **Finalised 2026-06-28 (order 0015).** This is the standalone, permanent record: every headline claim
> re-verified against the live code (see §11 Verification log — all confirmed, one count softened); the
> two previously-thin sections (§4 Capabilities, §8 Supporting machinery) filled in full from the option
> map and a code spot-check; the original part-files preserved verbatim as appendices A–F (§12). It is
> written to stand alone if all chat memory is wiped.

---

## 0. Executive summary

AI Boost has **two foundations, not one**, and they are in very different states. The **output
foundation** — how the product's text and code reach the page — is genuinely solid and ship-grade: a
single, deliberate spine where no plugin writes to the page directly, every plugin accumulates into one
of two builders (`HeadBlockBuilder` / `BodyBlockBuilder`), and one idempotent, order-independent
`finalize()` splices a single marker-wrapped block per region in a fixed order, byte-safely. One
settings source (a single JSON blob), one render contract, one manifest-plus-codegen source of truth for
options, and a cooperative conflict dedup that — by construction — can only ever delete *our own* tags.
This is mature engineering and is the strongest part of the codebase.

The **context foundation** — how the product decides *which* page it is on, *which* entity the page is
about, *which* language applies and *whether the page is indexable* — is the opposite: scattered. There
is no central Page-Type Resolver, no Entity Registry beyond two `@id`s, and **no indexability authority
at all**. The article gate is copy-pasted 22 times across 11 files; there are two contradictory
definitions of "homepage", one of them known-buggy and still live in the `<title>`/meta path. For an
SEO/AEO product this is the single highest-severity structural gap, and it is also the biggest WordPress
portability blocker. The reader review that called the whole root "solid" was right about the render
pipeline and wrong as a blanket statement; the critic pass is correct that "solid" averaged over both
foundations hides the crack.

On the owner's other two questions: **extensibility is real** — a versioned, event-driven Integration
SDK lets new `aiboost_int_*` bridges load with no core release, proven end-to-end by Falang and YOOtheme.
But the older `*_pro` decorator pattern is **retired** (every `*_pro` plugin is a dormant no-op) and
`ProFeatureRegistry` is a no-op compatibility shell, so adding a *Pro* feature means editing a core Free
plugin plus manifest plus codegen — not dropping in a decorator. **WordPress is a designed seam awaiting
implementation, not a near-build**: the adapter interfaces and the CMS-agnostic lib exist, but the
decisive Wp adapters throw, there is no content-repository seam for ~200 `#__` queries, and no WP entry
point exists. All three independent assessments advise deferring the WP build until Joomla proves the
market.

**Security is solid and unusually disciplined for a one-developer product**: Pro cannot be spoofed
client-side (single server-resolved `pro_activated` flag, fail-closed license verification with store +
product pinning), license keys are stripped from exports by a shared constant that import reuses, SVG
upload is blocked, path traversal and SQL are closed. The residual risk is the inherent admin-trust
model (custom-code injection is raw HTML by design) plus the WP port having no security primitives yet.

**The product also carries dead weight a solo near-sale owner should shed**: two Pro architectures
shipping at once, a second legacy copy of the whole product eating most of CI, ~18 dead/duplicate config
options, and `ai-content-verified`/`ai-content-optimized` meta tags that are an unbackable marketing
claim (a liability, not just clutter). None of this touches the output pipeline or the SDK — it is pure
subtraction. **Net verdict: the engine at the centre is ship-grade; the context layer and a config/docs
cleanup are the real pre-sale work, and the largest risk is positioning/over-claim, not broken plumbing.**

---

## 1. What it is + core principle (the root)

**In one sentence:** AI Boost is a single, consolidated output engine that takes the site owner's
settings and turns them into a fixed, ordered block of machine-readable signals — Schema.org JSON-LD,
OpenGraph/Twitter meta, AEO/AI-discovery meta, analytics tags and custom code — injected once into every
front-end page, with the same discipline for `<body>`/footer regions, plus standalone outputs
(`robots.txt`, `sitemap.xml`, `llms.txt`, Markdown, HTTP headers, runtime redirects).

The owner's thesis *is* the architecture: there are exactly two in-page output sinks
(`HeadBlockBuilder`, `BodyBlockBuilder`), one consolidated marker-wrapped block per region, and one
settings source feeding them.

### The root invariant (enforced structurally)

> No plugin writes to the page directly. Every plugin *accumulates* its contribution into a
> request-scoped builder; ONE idempotent `finalize()` renders the consolidated block and splices it into
> the page exactly once.

Evidence:

- `HeadBlockBuilder` (`component/lib/src/HeadBlockBuilder.php`) and `BodyBlockBuilder`
  (`component/lib/src/BodyBlockBuilder.php`) are the only two write points — both `final` classes with
  private static accumulators and a single `finalize()`. A grep across all system plugins confirms
  **12 push calls across 6 output plugins, zero direct `addCustomTag()`/`<body>` regex writes.**
- `finalize()` is guarded by a static `$finalized` flag (`HeadBlockBuilder.php:471`,
  `BodyBlockBuilder.php:227`): the first caller does the work, the rest no-op — which is why **plugin
  order does not matter**.
- Fixed render order `Schema → Social → AEO → Analytics → Custom Code` (the `ORDER` constant,
  `HeadBlockBuilder.php:87-93`).
- The splice before `</head>` is a **byte-safe substring splice, not `preg_replace`** — deliberate,
  because user custom-code may contain `$1`/`\1` regex back-references that `preg_replace` would corrupt
  (`HeadBlockBuilder.php:500-544`).
- **Cooperative conflict dedup** (`trimBlockConflicts`, `HeadBlockBuilder.php:319-426`) is a pure,
  unit-testable function (`TrimBlockConflictsTest.php`) with a structural safety invariant: it reads the
  third party's tags only to *detect*, and every `preg_replace` target is *our own* rendered block — it
  can never delete a third party's tag. Custom Code (`SECTION_CODE`) is excluded from trimming by
  construction.

### The end-to-end pipeline (setting → bytes)

1. Vue SPA field (`v-model` key + `:data-ab-field`); source of truth is the manifest
   (`component/lib/src/Manifest/*.php`).
2. Save POSTs to `SettingsController`, which accepts only `SettingsSaveDefinition::acceptedKeys()`
   (unknown keys silently dropped — the three-way-key-alignment trap).
3. **Identity/Pro fence** is `SYSTEM_PRESERVED_KEYS` via `mergeSystemPreservedKeys()`
   (`SettingsSaveDefinition.php:42-62`) — license/identity keys cannot be self-promoted or wiped from
   the client. (Note: `ProFeatureRegistry::stripLocked()` is a **no-op** today — see §9.)
4. Persist to a **single row** of `#__aiboost_settings` (`setting_key='main'`, one JSON blob).
5. Read side: `onAfterInitialise` (redirects/heartbeat) → `onBeforeCompileHead` (every plugin reads the
   same request-cached blob, runs its generator, calls `pushSection()`/`pushBody()`) → `onAfterRender`
   (first `finalize()` dedups, renders in fixed order, lets bridges filter, byte-safe-splices).

### Manifest-first codegen (the second pillar)

`Manifest/*.php` is the single source of truth; `scripts/codegen-from-manifest.py` emits four derived
families (Pro stubs, en-GB `.ini`, Vue partials, Health stubs); a `--check` build mode fails if an option
is half-wired. A new option = edit manifest + codegen. This is what keeps the three-way alignment honest
at build time and makes config cleanup mechanical and safe.

---

## 2. Output map (every sink the product writes to)

Eight distinct output sinks, each with a single clear owner.

| # | Sink | Physical or virtual | Owner (entry) | Trigger |
|---|---|---|---|---|
| S1 | `<head>` consolidated AI Boost block | virtual (spliced before `</head>`) | `HeadBlockBuilder::finalize()` | each plugin's `onAfterRender` (first caller wins) |
| S2 | `<head>` native Joomla streams (canonical, hreflang, title, meta-desc) | virtual (Joomla renders) | `Document::addHeadLink()/setTitle()/setMetaData()` | `onBeforeCompileHead` |
| S3 | `<body>` top block (analytics `<noscript>`, custom body code) | virtual (spliced after `<body>`) | `BodyBlockBuilder::finalize()` | `onAfterRender` |
| S4 | Footer block (custom footer code) | virtual (spliced before `</body>`) | `BodyBlockBuilder::finalize()` | `onAfterRender` |
| S5 | `robots.txt` | **PHYSICAL file on disk** | `RobotsTxtBuilder::injectManagedBlock()` via `SettingsController::regenerateRobotsTxt()` | admin **Save** (not per-request) |
| S6 | `sitemap.xml` family (+ index/news/chunks) | virtual (request intercept) | `AiBoostSitemap::onAfterInitialise()` | front-end request to path |
| S7 | `llms.txt` family (+ `llms-full`, `llms-{sef}`, IndexNow key file) | virtual (request intercept) | `AiBoostAeo::onAfterInitialise()` | front-end request to path |
| S8 | HTTP headers + Markdown body | virtual (response mutation) | `AiBoostAeo` / `AiBoostSitemap` | `onBeforeCompileHead`/`onAfterRender`/`onAfterInitialise` |

### The critical `.htaccess` finding

**Verified: AI Boost does NOT write, read, or manage `.htaccess` anywhere.** A repo-wide search for
`htaccess|RewriteRule|mod_rewrite` across `component/` returns only **two explanatory comments**
(`SettingsController.php:248`, `package/pkg_script.php:1007`), each noting Joomla's standard `.htaccess`
excludes `robots.txt` from PHP rewriting. `docs/feature-verification.md` independently records the same
absence.

301/302 redirects are **DB-stored and served by PHP at runtime**: rules live in `#__aiboost_redirects`;
`AiBoostCore::handleRedirects()` (L560-628) loads enabled rules on every front-end `onAfterInitialise`,
matches the path (exact or `*` wildcard), increments a hit counter, and issues
`header('Location:', true, $code)` then `exit` (codes 301/302/303/307/308). Suppressed under
`staging_mode`. **Only `robots.txt` is a physical disk file** (fenced BEGIN/END managed block written on
Save, preserving user rules); `sitemap.xml`, `llms.txt`/`llms-full`/`llms-{sef}`, the IndexNow key file
and Markdown pages are **all generated virtually** by intercepting the request — nothing static, nothing
to go stale, nothing left behind on uninstall.

### Output map — function × sink × translatable (condensed)

| Function (key) | Sink | Translatable | Owner / proof |
|---|---|---|---|
| Schema JSON-LD (`enable_schema`) | S1 Schema | **Yes (Pro + Falang)** — `TranslationService`, gated `hasPro('int_falang')` (`AiBoostSchema.php:127-137`) | `aiboost_schema` |
| OpenGraph / Twitter (`enable_opengraph`) | S1 Social | **Yes (Pro)** — `OgTagProDecorator` (`AiBoostSocial.php:129-139`) | `aiboost_social` |
| AEO AI-signal meta (`aeo_ai_meta_enabled`) | S1 AEO | No (static hints) | `AiBoostAeo.php:225-242` |
| Markdown discovery `<link>` | S1 AEO | URL-only | `AiBoostAeo.php:246-259` |
| GSC/FB verification, GTM/GA4/Pixel | S1 Analytics (+ S3 noscript) | No (global IDs) | `AiBoostAnalytics.php` |
| Custom head/body/footer code | S1/S3/S4 Code | No (raw HTML, never trimmed) | `AiBoostCode.php` |
| Canonical (+ URL map) | S2 native | No (URL only) | `AiBoostCore.php:242-352` |
| Title / meta-desc templates | S2 native | Partial (`{site_name}` follows Joomla lang) | `applyTitleTemplate`/`applyMetaDescTemplate` |
| hreflang alternates | S2 native + S6 sitemap | **Yes (Pro + Falang)** | Falang bridge (`aiboost_hreflang_pro` is a skeleton) |
| `robots.txt` managed block | S5 physical | No (one file, all langs) | `RobotsTxtBuilder` |
| Sitemap base / images / news | S6 virtual | URL-level | `SitemapGenerator` |
| Sitemap hreflang `<xhtml:link>` | S6 virtual | **Yes (Pro + Falang)** | `HreflangSitemapExtension:383-385` |
| `llms.txt` / `llms-{sef}.txt` | S7 virtual | **Yes (Pro + Falang)** | `LlmsTxtProGenerator` |
| Markdown page body | S8 body | follows source page | `MarkdownConverterService` (page-type-agnostic) |
| X-Robots-Tag header | S8 header | No | `AiBoostAeo.php:219` |
| 301/302 redirect | HTTP `Location` (no `.htaccess`) | No | `AiBoostCore::handleRedirects()` |

**Translatability lens (the AI/Google-visibility edge):** essentially every per-language overlay
(translated schema/OG/llms + ALL sitemap hreflang) is hard-gated on **both** Pro **and**
`PluginRegistry::hasPro('int_falang')` (the Multilang/Falang SKU). Native-multilingual Joomla content
still renders per-language via Joomla, but AI Boost's *overlay* translations require the Falang add-on —
an OPEN product-boundary decision (project memory). This multilingual depth is the product's only
verified competitive edge (live-measured vs 4SEO, order 0009).

**Output-map risks:** (R1) redirects are PHP-runtime, not server-level — a per-request cost on
high-traffic sites and only for URLs reaching Joomla; (R2) two parallel robots.txt code paths with
**different marker strings** (`RobotsTxtBuilder` "# BEGIN AI Boost…managed block" vs runtime
`RobotsTxtManager` "# [AI Boost AEO — managed section]") — dedup before 1.0; (R4) meta-desc template
page-type gap (home/search/tag fall back to the global template); (R6) Markdown quality is
template-dependent; (R7) S2 native canonical/hreflang can be stripped by third parties on multilingual
sub-paths (not an AI Boost bug, but a delivery fragility the consolidated S1 block does not share).

---

## 3. Page-type / context resolution

**There is NO central Page Type Resolver.** Page-type, main-entity, language and indexability are each
decided independently inside the plugins that need them, in at least three incompatible ways. This is the
real foundation crack and it is load-bearing, not cosmetic.

- **The shared seam exposes only raw request primitives.** `AppContextInterface`
  (`component/lib/src/AppContextInterface.php`) offers `getCurrentOption/View/Id`, `isHomepage`,
  `getActiveLanguage` — but **no `getPageType()`, no `getMainEntity()`, no `isIndexable()`, no
  `getCanonical()`.** Classification is structurally pushed to the edges. No `PageType`/`PageContext`/
  `EntityRegistry`/`Indexability`/`Resolver` class exists anywhere under `lib/src/`.
- **Two contradictory homepage definitions, both live.** `JoomlaAppContext::isHomepage()`
  (`JoomlaAppContext.php:76-104`) uses the authoritative active-menu `home=1` flag (correct, locked by
  `HomepageDetectionContractTest.php`). But `AiBoostCore::detectPageType()` (`AiBoostCore.php:376-413`)
  still uses the path/`view=featured` heuristic that the first one's own docblock condemns as having
  "mis-fired site-wide" — and it is **not dormant**: it drives `applyTitleTemplate()` (`:461`) and
  `applyMetaDescTemplate()` (`:518`), the actual `<title>` and meta description. On a Featured-menu-item
  homepage, the schema layer and the title/meta layer can disagree about the same URL.
- **The article gate is copy-pasted, not shared** — the inline triple
  `option==='com_content' && view==='article' && id>0` appears **22 times across 11 files**, including
  4× in `SchemaProBuilder.php` alone (`:312, :373, :497, :575`).
- **No "main entity" object exists.** Free `SchemaBuilder` emits Organization + WebSite + BreadcrumbList
  on essentially every page (`:177-199`); entity-specific blocks (Article/FAQ/HowTo/Event) live ONLY in
  Pro and re-classify the page per block. Entity Registry by `@id` exists only for `#organization` /
  `#website`.
- **Indexability is never centrally resolved or honoured.** No `isIndexable()`, no per-page `noindex`/
  X-Robots emitter. Canonical (`resolveCanonical`, `:352`) ignores indexability; the sitemap applies its
  *own* private `state=1`/`published=1`/ACL filter (`SitemapGenerator.php:176-190`) — a **fourth**
  independent notion of "indexable" that no other sink consults. A page can be excluded from the sitemap
  yet still receive full schema + canonical, with nothing reconciling them (Procena3's duplicate-content
  hazard for the Markdown/llms layer).
- **Language is split** — per-request language (`getActiveLanguage` + `LanguageService`) is separate from
  translation-source authority (`LanguageDetector::detect`, Joomla-native vs Falang vs JoomFish), never
  unified under one context node.

This confirms Procena2's #1 v1.0 priority (a central Page Type Resolver) and #2 (an Entity Registry),
both unmet in code. It is simultaneously the single biggest WordPress-portability blocker: `WpAppContext`
returns `''`/`0`, so on WP every article/FAQ/HowTo/Event gate evaluates false and the entire
entity-schema layer is structurally inert until classification is re-expressed CMS-neutrally.

---

## 4. Capabilities

**Source:** the full per-option map in `_handoff/docs/option-map.json` (order 0010 — 276 rows, one per
option / admin tool / schema output, each with verified status + reason), reconciled with
`docs/feature-verification.md`. This section is the complete capability inventory; nothing is summarised
away.

### 4.1 Headline status breakdown (every individually-tracked surface)

| Status | Count | % | Meaning |
|---|---:|---:|---|
| ✅ **Radi** (working, verified) | **238** | **86.2%** | emits the intended artifact; verified live or by contract test |
| 🟡 Poluzavršeno (half-done) | 24 | 8.7% | exists but not fully wired (UI removed, phantom key, partial translation, stub) |
| ⚫ Mrtvo (dead) | 6 | 2.2% | saves but no runtime consumer |
| ⚠️ Pogrešno zaključano (tier-mismatch) | 4 | 1.4% | works, but Free/Pro disagrees across manifest vs runtime |
| 🔴 Ne radi (broken) | 4 | 1.4% | verified failing — all YOOtheme, all deferred post-1.0 |
| **Total** | **276** | 100% | options 248 + admin tools 15 + schema-output catalogue 13 |

Tier split (counting integration as Pro-side): **Free 145 / Pro+integration 131.** **Not one surface had
an undeterminable status** — there are no "unknown" rows.

### 4.2 Capability inventory by group (count · working · what it does · lead/weak items)

| Group | Rows | Working | Capability (condensed) | LEAD ⭐ / weak spots |
|---|---:|---:|---|---|
| **Core SEO / titles / robots / redirects** | 37 | 37 | per-page-type `<title>` + meta-desc templating w/ token substitution; canonical master + per-path override map; fenced `robots.txt` managed block; 301/302/303/307/308 redirects + `*` wildcard + hit counter + 404 log; staging/debug modes; per-feature conflict resolution; auto/manual domain | ⭐ Redirects + 404 log (measurable migration value); only weak item is `robots_auto_sync` (dead) |
| **Schema.org JSON-LD** | 89 | 86 | ~36 business-identity `@type` values (35/35 type-cycle verified live) + full `org_*` identity set; ~34 Pro `specific_*` rich-detail fields; sameAs (6 networks, TikTok added v0.87.56); aggregateRating; opening hours (simple/advanced/per-day); Services repeater; per-page Article(+BlogPosting/NewsArticle/TechArticle), FAQ/QA auto-detect, HowTo, Event; WebSite+SearchAction (homepage-only, B2-fixed); partial `@id` graph; per-language schema translation (Pro+Falang) | ⭐ Author E-E-A-T `Person`; ⭐ per-language translation. Weak: `manual_faq_scope` (dead), `enable_manual_faqs` legacy `q/a` data (half), `schema_breadcrumb_pro` (stub) |
| **OpenGraph / Twitter** | 19 | 15 | full `og:*` + `article:*` + Twitter-card set; `default_og_image` clean URL + real dims (B8-fixed); per-language `og:*` translation; 6 per-article custom-field overrides; intro-image fallback | ⭐ per-language OG translation. Weak: 4 tier-mismatch keys (`enable_per_article_fields`, `enable_article_og_type`, `fb_app_id`, `twitter_site_handle`) |
| **Analytics** | 16 | 16 | GA4 (+ Consent Mode), GTM (head + body `<noscript>`), Meta Pixel (single/multi, 15 standard + custom events, consent, noscript), Google/Bing/FB verification | none flagged; not an SEO differentiator (Procena) — keep as optional module |
| **Custom code** | 12 | 4 | raw HTML into head / after-body / before-`</body>`, master-gated, never dedup-trimmed | weak: 8 scope/menu keys (half — picker removed, value still honoured) + 2 legacy keys. Security surface (§7) |
| **Sitemap / hreflang** | 27 | 23 | dynamic sitemap (articles/menus/categories/tags), image sitemap, hreflang alternates + x-default, index/chunking, news sitemap (48h), per-type priorities/changefreq, Google/Bing ping + auto-ping-on-publish, guest-ACL filter | ⭐ sitemap hreflang (`enable_hreflang` is the real consumer). Weak: 4 parallel dead hreflang keys |
| **AEO (llms/IndexNow/crawlers)** | 34 | 28 | `/llms.txt` + `/llms-full.txt` + per-lang `/llms-{sef}.txt`; Markdown serving (.md/`?markdown=1`/Accept); IndexNow (key file + auto-submit); AI-crawler matrix (9 bots); SEO-scraper blocklist (12 bots); AI-signal meta | ⭐ IndexNow (legitimate). Weak: `enable_x_robots_header` + 4 `llmstxt_include_*` + `robots_custom_rules` (phantom/half); `aeo_ai_meta_enabled` works but is the unbackable claim (§5 reposition) |
| **Integrations** | 14 | 7 | Falang multilingual bridge (head + sitemap hreflang, x-default, schema/OG translation); YOOtheme bridge | ⭐⭐ Falang bridge — the verified moat (order 0009). Weak: YOOtheme = 2 broken parsers + rest deferred post-1.0 |
| **Admin tools** | 15 | 15 | Dashboard; Health + Fix-It; Conflict Manager; Redirects; URL/link checker (bg scans, GSC indexation); SEO/JSON-LD/AI-Visibility analyzers (one-click apply-fix); Import/Export (denylist-protected); Autopilot wizard; Changelog; Help; Error-log viewer; Media browser; Integrations manager | ⭐ Health + Fix-It; ⭐ Conflict Manager (14/14 verified, never disables others); ⭐ Redirects |
| **Schema @type catalogue (outputs)** | 13 | 9 | the distinct emitted blocks: 36-value identity dropdown, WebSite(+SearchAction), BreadcrumbList, Article+subtypes, FAQPage, QAPage, HowTo, Event, Author Person, Enhanced Breadcrumb (stub), YOOtheme Event/Product/Org/FAQ/Gallery | ⭐ Author Person E-E-A-T. Weak: Enhanced Breadcrumb (stub), YOOtheme FAQ + Gallery (broken) |

> Per-section row totals reconcile with the order-0008 inventory (Core 37, Analytics 16, Custom 12,
> Sitemap 27, AEO 34, Integrations 14, Tools 15; OG 19-distinct because the 6 Meta Pixel keys are counted
> once with home = Analytics; Schema 89 vs 0008's headline 90 is a ±1 inside 0008's own count). Full
> reconciliation in the order-0010 report.

### 4.3 The LEAD capabilities (⭐ — what the product should sell on)

Drawn from the `lead:true` rows of the option map and the three independent assessments (Procena1/2/3 +
the live 4SEO measurement, order 0009):

1. **Multilingual depth via the Falang bridge** — per-language *translated* schema/OG text + reciprocal
   hreflang + x-default in head AND sitemap. **The only live-measured competitive edge** (4SEO emits the
   same English text on every language and no hreflang on a Falang site — order 0009). Strongest on Falang
   sites; deep per-field translation remains an edge even on Joomla-native multilingual.
2. **Conflict Manager** — detects competing SEO/schema/OG emitters and per-feature takes over or defers,
   **never disabling another extension** (14/14 verified). All three assessments call this undervalued.
3. **Single consolidated output sink (§1)** — the cross-cutting capability that *makes* the Conflict
   Manager and clean output possible, and a genuine differentiator vs competitors that emit scattered tags.
4. **Author E-E-A-T `Person`** — real, defensible structured-data value (Procena).
5. **Redirects + 404 log** and **IndexNow** — concrete technical-SEO features with measurable effect.
6. **Health + live-artifact Fix-It** — verifies the tag actually reached the browser (§8), not just that a
   setting was saved.

### 4.4 What "238 working" does and does NOT mean

"Working" means the surface emits its intended artifact and that was verified live or pinned by a contract
test. It does **not** mean every working surface is *strategically* worth keeping or *correctly
positioned*: `aeo_ai_meta_enabled` is ✅ Radi yet is the §5 "reposition/remove" liability; the 36 schema
types all emit yet should be marketed as ~12 correct graphs. Capability completeness and product
positioning are separate axes — §5 covers the second.

---

## 5. Limitations

The 38 actionable items are **concentrated, not scattered**, and none sit on the core SEO/schema/OG/
sitemap path that ships on every page:

- **Broken (4) — verified failing on staging, all deferred post-1.0:** YOOtheme FAQ accordion parser
  (`buildAccordionFaqSchema` non-greedy regex truncates on nested `<div>`s; 0/6 pages emit FAQPage) and
  YOOtheme gallery parser (`buildGallerySchema` expects old `data-caption`; incompatible with newer
  `uk-lightbox`; 0/51 pages). The YOOtheme "moat" cannot be marketed until fixed.
- **Dead (6) — saves, no consumer:** `robots_auto_sync`; `manual_faq_scope`; and **four parallel hreflang
  keys for one thing** (`hreflang_sitemap`, `sitemap_hreflang`, `hreflang_enabled`,
  `hreflang_primary_language`) — the live consumer reads `enable_hreflang`; collapse to one.
- **Half-done (24):** Custom-Code per-menu scope (8 keys; picker removed from UI but plugin still honours
  stored values); 4 phantom `llmstxt_include_*` toggles (read by generators, in no manifest); phantom
  `enable_x_robots_header`; `robots_custom_rules` read only by the legacy `RobotsTxtManager`;
  `schema_breadcrumb_pro` is a codegen stub with no output; **`falang_schema_translate` — HowTo step
  names + FAQ items NOT translated per language (EN fallback)**, which sits on the only verified moat and
  is the single highest-value fix before sale.
- **Tier-mismatch (4):** `enable_per_article_fields`, `enable_article_og_type`, `fb_app_id`,
  `twitter_site_handle` — manifest says Free but consumed only inside the Pro OG decorator; align the
  three sources.

**Reposition (15) — the dominant risk is positioning, not code.** These work but are over-marketed:
drop "36 schema types" (sell ~12 correct graphs); drop "rich result" promises on FAQ/HowTo (Google killed
HowTo rich results 2023, restricted FAQ to gov/health); de-emphasise SearchAction (sitelinks search box
retired Nov 2024); reposition llms.txt/Markdown/AI-Visibility as **AI-agent/documentation accessibility,
not AI ranking** (and Markdown must be noindex/out-of-sitemap or it creates duplicate content); move
custom-code out of the headline (not an SEO feature + a security surface).

**Capability gaps vs a modern 2026 SEO/AEO product:** (1) **Google Search Console integration** — the #1
competitive gap vs 4SEO (the real market leader); without measurement the tool is "set and hope";
(2) central Page-Type Resolver + Entity Registry (§3); (3) Output Inspector with per-value provenance;
(4) three-level validation (JSON → rich-result eligibility → business logic); (5) live Bot Access Checker;
(6) `WebPage` subtypes + `@graph` consolidation; (7) native Product/ItemList (no VirtueMart/HikaShop);
(8) cache-clear hook + sitemap CPU optimisation for agency servers; (9) competitor-import migration;
(10) Consent-Mode ordering guarantee. None are fatal for 1.0, but they define the honest scope of what to
claim and build next.

---

## 6. Extensibility & WordPress-readiness

**Extensibility: POSTAVLJENO (genuinely working today) — for integration bridges.** The mechanism is a
versioned, event-driven SDK in `component/lib/src/Integration/`, with four independent seams, none
requiring a core release:

1. **Discovery** — `IntegrationRegistry::all()` fires `onAiBoostRegisterIntegration`;
   `PluginRegistry::integrations()` (`:69-83`) builds its map dynamically, so a new
   `plg_system_aiboost_int_<key>` ZIP appears in the dashboard immediately.
2. **Versioned contract** — `Sdk::SDK_VERSION=1`; incompatible bridges are discarded into
   `getSdkMismatches()` and surfaced as a Health warning. Real gating, not cosmetic.
3. **Output extension** — core plugins call `FilterDispatcher::dispatch()` for 7 named events; a throwing
   listener is caught and never breaks core output; `FilterResult` logs every mutation.
4. **Settings/UI extension** — `onAiBoostRegisterFields()` feeds the save whitelist live, so a bridge's
   fields persist without editing `$fields`.

`AbstractIntegrationPlugin` removes boilerplate; the subclass contract is ~3 methods. **Two real bridges
(Falang, YOOtheme) prove it end-to-end** — adding a third is a ZIP, not a core patch.

**Honest extensibility limits:** (a) the `*_pro` **decorator pattern is RETIRED** — every `*_pro` plugin
is a dormant no-op (`AiBoostSchemaPro::onAfterInitialise()` does nothing; Pro logic was relocated INTO
the Free plugins gated on `isProActive()`), so adding a *Pro* feature means editing a core Free plugin +
manifest + codegen; (b) `ProFeatureRegistry` is a no-op compat shell; (c) current bridges hard-depend on
AI Boost being installed (`AbstractIntegrationPlugin` `require_once`s `com_aiboost/lib/autoload.php`), so
they cannot ship as standalone+integrative plugins yet.

**WordPress-readiness: DELOM (scaffolded, not buildable).** What IS abstracted: a full `Cms/` adapter
interface layer (Database, Http, Filesystem, Application, Clock, EventDispatcher, Document, Router) with
**both** Joomla (real) and Wp (placeholder) implementations; `AdapterRegistry` as the single static
touchpoint; **the lib is genuinely CMS-agnostic at the import level** — `grep "use Joomla\CMS"` across
all 81 lib files returns 0; 37 files carry the `ABSPATH` guard; `WpEventDispatcherAdapter` already routes
through `apply_filters()`. What BLOCKS a build: the decisive adapters **throw** —
`WpDatabaseAdapter::getConnection()`, `WpHttpAdapter::getClient()`,
`WpApplicationAdapter::getBody()/setBody()` all `throw RuntimeException('… v2.0 WordPress port')`,
`isSite()` returns `false`; **no content-repository seam exists** for ~200 `#__` queries (~104 in 22 lib
files + ~96 in plugin services hard-binding to `#__content`/`#__categories`/`#__menu`/`#__languages`);
no WP entry point, plugin header, or build exists.

**Effort shape:** the Wp adapters are *small and well-specified* by their TODOs; the
**content-repository seam is the large, schedule-risky rewrite** and is where the real work lives. All
three assessments + Procena3 explicitly advise **deferring the WP build until Joomla proves the market**
(Yoast/Rank Math own that space). Correct posture: keep the cheap adapter *interfaces* (load-bearing for
testability today), freeze the Wp impls.

---

## 7. Security

**Solid and unusually disciplined for a one-developer product.** The dangerous edges are each closed at
the server with a named, fail-closed mechanism. The security skeptic re-verified the chain against live
code and confirms the verdict **holds — no "Closed" item should be re-graded to "Open".**

- **Pro cannot be spoofed client-side.** `PluginRegistry::isProActive()` reads only
  `pro_activated==='1'` (`:375-378`); that flag is on `SYSTEM_PRESERVED_KEYS`, so save + import
  **physically refuse to write it from client input**; it flips only via `saveLicenseState()` after
  `LicenseValidator::verify()` confirms `valid===true` AND active key AND store-pin (`367944`) AND
  product-pin against the live API — **fail-closed on any error**. `coreLicenseActive()` ignores `int_*`
  keys so a cheap add-on key can't unlock the core bundle. The bootstrap `isPro` shipped to Vue is
  cosmetic only.
- **Export/import secret-leak & entitlement-forgery are closed by a SINGLE shared constant.**
  `SYSTEM_PRESERVED_KEYS` is stripped on export (`buildExportPayload`) and is the literal
  `IMPORT_DENYLIST` (`ImportController.php:39`) — the two boundaries cannot drift; a backup never carries
  the plaintext `license_key`, and a forged file cannot set `pro_activated`/`license_state` on another
  site.
- **Media picker is the best-hardened controller:** SVG upload blocked (raster-only allow-list,
  documented stored-XSS rationale), `finfo` MIME sniff after extension check, `is_uploaded_file` + 5MB
  cap, realpath-based traversal containment (incl. the not-yet-created-target hole), symlink-safe
  recursive delete, CSRF + ACL on every mutating task.
- **Custom-code injection** emits raw/unescaped head/body/footer **by design** — restricted to
  `core.manage` admins with CSRF, front-end-only, master-toggle gated; never dedup-trimmed; substring
  splice avoids back-reference corruption. Standard Joomla admin-trust surface, not a defect. *Skeptic's
  refinement:* the right is `core.manage` on `com_aiboost`, NOT Super User — on a delegated-roles site a
  Manager who would not be trusted with template HTML can still inject raw script. **Before a WIDE public
  launch**, gate the custom-code save path on `core.admin` or a dedicated `aiboost.editcode` ACL, and add
  a Health warning when `custom_code_*` is non-empty while >1 group holds `core.manage`.
- **SQL is clean:** query builder + quote/quoteName + int casts; the LIKE spot is correctly escaped
  (`searchArticles`).

**Residual / hardening (none launch-blocking for Joomla):** (#5) output-escaping is **distributed across
~14 emitters**, not a central chokepoint — verified clean today (OG `htmlspecialchars ENT_QUOTES`,
analytics escapes every ID, JSON-LD `JSON_HEX_TAG|JSON_HEX_AMP` in both schema sinks), but the softest
point and the most likely future XSS regression. **Promote the central output-escaping test from optional
to required-before-wide-launch.** Legacy `ProGate` `license_tier` path is live dead code (cannot grant
core Pro, but confusing — remove). `SettingsController::mediaImages()` lacks `MediaController`'s realpath
containment (low — read-only dir listing under web-readable `/images`). **The WP port has ZERO security
primitives** (no nonce/capability/`$wpdb->prepare`/`wp_kses`) — a hard prerequisite gating any WP launch,
correctly unclaimed.

---

## 8. Supporting machinery

**The most mature, ship-ready part of AI Boost.** This is the clearest proof of the owner's thesis: it
does not merely read settings, it verifies the live artifact reached the browser. "Supporting machinery"
= the diagnostic, fix, analysis and conflict layer that wraps the output engine.

### 8.1 Component inventory (verified present in this tree, 2026-06-28)

| Component | File(s) | Size / shape | Tested? |
|---|---|---|---|
| Health check + scoring | `lib/src/HealthCheckService.php` | **3,660 lines**, ~80 checks, DI'd, manifest auto-registration, weighted scoring, SSRF-hardened live scan | one narrow test only |
| SEO analyzer | `lib/src/SeoAnalyzerService.php` | 13 weighted checks + whitelist one-click fix | none |
| JSON-LD analyzer | `lib/src/JsonLdAnalyzerService.php` | required/recommended fields for ~19 types | none |
| AI-Visibility analyzer | `lib/src/AiVisibilityAnalyzerService.php` | llms.txt, AI-crawler, IndexNow, AI-meta, OG, canonical, schema | none |
| URL / link checker | `com_aiboost/admin/lib/src/UrlCheckerService.php` | resumable bg scan (`#__aiboost_url_scans`), `fastcgi_finish_request` detach, SSRF allow-list | none (incl. its SSRF allow-list) |
| Conflict policy (resolver) | `lib/src/ConflictPolicy.php` | pure resolver | **TESTED** |
| Conflict emission skip | `lib/src/DocumentInspector.php` | emission-time skip | **TESTED** |
| Conflict registry | `lib/src/ConflictManager.php` | 11-slot first-claim registry | **TESTED** |
| Competitor DB scan | `lib/src/ConflictDetector.php` | DB scan (keys 4SEO on `forseo`) | partial |
| Live duplicate scan | `lib/src/DuplicateTagScanner.php` | live DOM duplicate scan | none |

(File presence + the 3,660-line Health size spot-checked directly in this finalisation pass; see §11.)

### 8.2 What each layer does

- **Health** (`HealthCheckService.php`, 3,660 lines, ~80 checks, fully DI'd) HTTP-fetches the homepage
  and asserts the actual tag/position: GTM `<noscript>` inside `<body>`, Meta Pixel noscript in
  body-not-head, AEO meta emitted, Markdown discovery link present. **Manifest-driven auto-registration**
  means any new setting with a `health` block gets a check for free, upgradeable to real probing.
  Weighted-proportional scoring (critical=15, warning=5) excludes info/Conflicts, fixing the historical
  "Pro scores 0" bug. Server-side live scan is **SSRF-hardened** (every redirect hop re-validated).
- **Fix-It is real at three layers:** Health deep-links (`{label, url, target_tab, target_field}` →
  scroll-to `data-ab-field`); SEO analyzer one-click server auto-apply on a **whitelist**; URL Checker
  writes real 301s/canonical overrides.
- **Analyzers (three, all same-host-locked + token+ACL gated):** SeoAnalyzer (13 weighted checks),
  JsonLdAnalyzer (required/recommended fields for ~19 types), AiVisibilityAnalyzer (llms.txt, AI-crawler
  directives, IndexNow, AI meta, OG, canonical, schema).
- **URL Checker is production-grade:** resumable background scan persisted in `#__aiboost_url_scans` with
  `fastcgi_finish_request` detach; cancel/status/history; thorough SSRF allow-list rejecting
  RFC1918/loopback/link-local/cloud-metadata, re-checked every hop.
- **Conflict layer is a clean four-piece design:** `ConflictPolicy` (pure resolver, TESTED),
  `DocumentInspector` (emission-time skip, TESTED), `ConflictManager` (11-slot first-claim registry,
  TESTED), `ConflictDetector` (DB competitor scan — keys 4SEO correctly on `forseo`) +
  `DuplicateTagScanner` (live DOM duplicate scan).

**The one genuine weakness is automated-test coverage.** The pure resolvers are tested; the
**highest-value runtime/HTTP classes have essentially no unit tests** — `HealthCheckService` (one narrow
test), `DuplicateTagScanner`, all three analyzers, `UrlCheckerService` (incl. its SSRF allow-list) have
NONE. A silent regression (a changed Joomla head API, a wrong settings key) could turn a verifier into an
always-pass, and the owner would ship believing the sink is fine. Secondary: a few Health checks are
always-pass placeholders (`warningLicenseDomainMismatch` FUTURE HOOK; legacy gating shims); live-artifact
checks degrade to "could not verify" (pass) when the homepage fetch fails (cache/cert/WAF) — a real
missing tag can be masked as inconclusive; Conflict/Help deep links depend on the website's fixed
`/docs/conflicts#` URL contract.

---

## 9. Architect's verdict — set up / not / can / cannot; solid vs fragile; over-complicated

**Do NOT accept "solid foundation" as a single verdict — split it.** There are two foundations.

**The OUTPUT root is ship-grade (solid).** Single write point, idempotent order-independent `finalize()`,
byte-safe splice, by-construction-safe cooperative dedup, one JSON blob, one render contract, one
manifest+codegen source of truth. This is the strongest part of the codebase and I would ship it.
*One unstated caveat the critic surfaced:* all builder state is `private static` and `reset()` is **never
called on the front-end** — order-independence relies entirely on per-request PHP process teardown. True
today (PHP-FPM/mod_php) but it breaks under any persistent runtime (Swoole/RoadRunner/FrankenPHP), where
request #2 sees `$finalized===true` and emits nothing. Document this implicit dependency (or add a
front-end `reset()` guard) before any persistent-runtime future.

**The CONTEXT layer is the real pre-sale foundation fix (fragile/scattered).** No central Page-Type
Resolver, no Entity Registry beyond two `@id`s, **no indexability authority at all**, two contradictory
homepage definitions (one known-buggy, live in the title/meta path), the article gate duplicated 22×.
For an SEO/AEO product, sinks disagreeing on whether a page is indexable is the highest-severity
structural hole. This is also the single biggest WP portability blocker.

**Two documented contradictions must be reconciled, not whitewashed.** (1) The reader doc that called the
whole root "solid" attributed the save-side Pro guard to `ProFeatureRegistry::stripLocked()`, which is a
**no-op** (`:309-312` literally `return $payload`); the *real* fence is `SYSTEM_PRESERVED_KEYS`. The
protection is real; the explanation pointed at dead code. (2) **CLAUDE.md's central gating invariant is
STALE** — it mandates registering every Pro surface in `ProFeatureRegistry` to drive "server-side
`stripLocked()` on save", but that enforcement is gutted; the live gate is the single `pro_activated`
flag. A foundation whose own rulebook no longer matches the code is solid-by-accident in that corner.

**Set up (can do today):** consolidated multi-sink output on every page; manifest-first option pipeline;
versioned Integration SDK (new bridges with no core release); single-flag fail-closed Pro gate; secret-
leak-proof export/import; hardened media uploads; live-artifact Health verification + three-layer Fix-It;
production-grade URL Checker + Conflict Manager; per-language schema/OG/hreflang/llms (Pro + Falang).

**Not set up / cannot do today:** central page-type/entity/indexability resolution; per-page noindex
authority; GSC integration; output inspector with per-value provenance; rich-result/business-logic
validation; native Product/e-commerce schema; a runnable WordPress build (adapters throw, no content
seam, no entry point); the `*_pro` decorator extension path (retired); YOOtheme FAQ/gallery schema
(parsers broken, deferred).

**Where it is over-complicated (dead weight, none load-bearing):** **two Pro architectures shipping at
once** (5 dormant `*_pro` no-op plugins + no-op `ProFeatureRegistry` + dead `ProGate` license-tier path,
all around a 1-flag gate); **a second legacy copy of the whole product** in top-level `plugins/` that
CLAUDE.md calls "not the active codebase" yet consumes ~4 of 5 CI jobs; **a speculative half-stubbed
WordPress port** built before any sale (all three assessments say defer); ~18 dead/duplicate config
options (incl. four parallel hreflang keys); and `ai-content-verified`/`ai-content-optimized` meta tags
that are an **unbackable claim and a liability**, not just clutter. Cutting all of this removes weight,
not capability — and in two cases (dual Pro architecture, dead ProGate path) actively reduces the risk
surface. **Keep at all costs:** the output pipeline, the Integration SDK, the Conflict Manager, the
multilingual output, and the manifest codegen (the codegen is what makes the cuts safe).

---

## 10. FOUNDATION-FIRST forward plan (root → trunk → main branches → twigs)

Structured so each step unblocks the next toward sale. **The root is mostly already healthy; the trunk is
the real pre-sale work.**

### ROOT — confirm and harden what the whole product stands on (mostly done; small fixes)
- **R1.** Reconcile the gating documentation with reality: state in CLAUDE.md + the joomla-development
  skill that the live gate is `pro_activated` via `isProActive()`, NOT `ProFeatureRegistry`. *(Doc-only,
  zero-risk; stops future work chasing dead code. Unblocks: a trustworthy rulebook for every later step.)*
- **R2.** Remove the dead `ProGate::validateAndStoreLicense/storeLicenseTier/onExtensionAfterSave`
  license-tier path. *(Low-risk; shrinks the attack surface.)*
- **R3.** Document (or guard with a front-end `reset()`) the implicit per-request-process-death dependency
  of the static `finalize()` keystone, so the output root survives a future persistent runtime.

### TRUNK — the one structural fix the product most needs before sale
- **T1. Build a single CMS-neutral Page-Type / Entity / Indexability / Canonical resolver.** This is the
  highest-leverage foundation fix. It (a) kills the 22× duplicated article gate; (b) retires the
  known-buggy `detectPageType()` path/featured homepage definition and unifies on the menu-`home` flag;
  (c) gives the product an **indexability authority** it currently lacks, so canonical/sitemap/schema/
  llms/Markdown stop disagreeing (and fixes the Markdown/llms duplicate-content hazard); (d) is the
  prerequisite that makes the WP seam runnable. *Unblocks: correctness on Joomla today AND every future
  WP/entity-schema effort.*
- **T2. Finish the multilingual moat:** complete `falang_schema_translate` so HowTo step names and FAQ
  items translate per language (currently EN fallback). *This sits on the product's only verified
  competitive edge — finish it before claiming multilingual depth in marketing.*
- **T3. Config-surface cleanup (mechanical, codegen-guarded):** delete the 6 dead options, collapse the 4
  parallel hreflang keys to one, align the 4 tier-mismatches, resolve the phantom `llmstxt_include_*` /
  `enable_x_robots_header` keys (wire UI or remove). *Each deleted dead option is one fewer thing to test,
  document, and explain to a customer.*

### MAIN BRANCHES — competitive readiness and honest scope
- **B1. Correct the marketing claims** (the dominant risk): drop "36 schema types" → ~12 correct graphs;
  drop FAQ/HowTo rich-result promises; de-emphasise SearchAction; reposition llms.txt/Markdown/
  AI-Visibility as agent/documentation accessibility, not ranking; **cut the `ai-content-verified`/
  `ai-content-optimized` tags entirely** (liability). *Unblocks: a defensible product page for an SEO
  audience that checks.*
- **B2. Close the #1 competitive gap — Google Search Console integration** (4SEO ships it; without
  measurement the tool is "set and hope"). Make Markdown/llms noindex + out-of-sitemap as part of T1's
  indexability authority.
- **B3. Test floor under the flagship surfaces:** unit tests for `HealthCheckService`, the three
  analyzers, `DuplicateTagScanner`, and especially the URL Checker SSRF allow-list — so a verifier can
  never silently become an always-pass. Add the central output-escaping test (security #5).
- **B4. CI/scope hygiene:** take the legacy `plugins/` tree out of the active CI matrix and re-point
  `composer test` at the component, so "CI green" means "the live product is green."

### TWIGS — deliberate, sequenced releases and pre-wide-launch hardening
- **W1.** One "collapse" release that cleanly uninstalls the 5 dormant `*_pro` plugins via `pkg_script`
  and stops shipping them (verify clean uninstall on both targets).
- **W2.** Dedup the two robots.txt code paths (different markers) onto the authoritative
  `RobotsTxtBuilder`.
- **W3.** Before a WIDE public launch: gate custom-code save on `core.admin` / `aiboost.editcode` + add
  the Health warning for delegated-roles sites (security skeptic).
- **W4.** Fix the two deferred YOOtheme parsers (FAQ accordion, gallery) — only then market YOOtheme.
- **W5. FREEZE WordPress work:** keep the cheap adapter interfaces, build no Wp impls/content seam/entry
  point until Joomla revenue justifies entering the Yoast/Rank Math arena. When you do, the first task is
  the **content-repository seam** (the real cost), and a full nonce/capability/prepare/kses security layer
  is a hard prerequisite.

**Bottom line for the owner (plain framing):** the engine at the centre — how your text and code get onto
the page — is the right size and ready to sell. The one real foundation job left is teaching the product
to decide, in one place, *which page it is on and whether that page should be indexed* (that one fix also
makes WordPress possible later). After that it is cleanup: finish the multilingual translation, delete the
old leftover machinery, correct a handful of marketing claims that the product cannot back, and add tests
so your "health check" can never lie to you. None of the leftover machinery, and no WordPress work, is
needed to ship Joomla to sale.

---

## 11. Verification log (finalisation pass, 2026-06-28)

Every load-bearing claim was re-checked against the live code on branch `refactor/structural` during this
finalisation. Result: **all confirmed**, one count softened. No claim was found wrong (so no claim-level
ODLUKA), but the gating-doc contradiction in §9 is restated below as a process ODLUKA because it requires
an owner decision to fix.

| Claim (section) | Method | Result |
|---|---|---|
| `ProFeatureRegistry::stripLocked()` is a no-op (§1, §9) | read `ProFeatureRegistry.php:309-312` | **Confirmed** — body is literally `return $payload;` |
| Two contradictory homepage definitions, both live (§3, §9) | `JoomlaAppContext.php:76` (menu `home=1`) vs `AiBoostCore.php:376 detectPageType()` | **Confirmed** — both exist; `HomepageDetectionContractTest` locks the correct one |
| Dormant `*_pro` decorators (§6, §9) | read `AiBoostSchemaPro.php:34-43` | **Confirmed** — `onAfterInitialise()` is an explicit no-op ("Pro decoration now runs inside the free plugin") |
| WP adapters throw (§6) | grep `Cms/Wp/*.php` | **Confirmed** — `WpHttpAdapter`, `WpDatabaseAdapter`, `WpApplicationAdapter::getBody/setBody` all `throw RuntimeException('… v2.0 WordPress port')` |
| Single write point: 12 push calls / 6 plugins, zero direct writes (§1) | grep `pushSection\|pushBody\|pushFooter` in `plugins/` | **Confirmed** — exactly 12 across 6 plugins |
| ~200 `#__` queries hard-bind to Joomla tables (§6) | grep `#__` in `lib/src` | **Confirmed lower half** — 104 across 22 lib files (matches the doc's "~104 in 22 lib files"; plugin-service half not re-counted) |
| Health = 3,660 lines, ~80 checks (§8) | `wc -l HealthCheckService.php` | **Confirmed** — 3,660 lines |
| Supporting-machinery classes present (§8) | `ls lib/src` | **Confirmed** — ConflictPolicy/DocumentInspector/ConflictManager/ConflictDetector/DuplicateTagScanner + Seo/JsonLd/AiVisibility analyzers; `UrlCheckerService` under `com_aiboost/admin/lib/src` |
| Legacy second product copy in top-level `plugins/` (§9) | `ls plugins/` | **Confirmed** — `aiboost-aeo`, `aiboost-codemanager`, `aiboost-hreflang`, `aiboost-opengraph`, `aiboost-schema`, … |
| Dead `ProGate` license-tier path (§7, §9, R2) | grep `license_tier` in `ProGate.php` | **Confirmed** — `validateAndStoreLicense`/`storeLicenseTier`/`license_tier` param all present and unreachable for granting core Pro |
| `.htaccess` is never managed (§2) | (carried from prior pass) | unchanged — two explanatory comments only |
| **Article gate "duplicated 22× across 11 files" (§3, §9)** | grep `'article'` and `com_content` in `component` | **Thrust confirmed, count softened** — literal `'article'` view-check appears **20× across 9 files**, `com_content` **44× across 19 files**. The heavy duplication / absence of a shared resolver is real; the exact "22× / 11 files" is approximate (depends on counting gate variants). Treat as **~20+ duplications across ~9-11 files.** |

**Restated as a process ODLUKA (from §9):** CLAUDE.md's central gating invariant — "register every Pro
surface in `ProFeatureRegistry` to drive server-side `stripLocked()` on save" — is **stale**, because
`stripLocked()` is a no-op and the live gate is the single `pro_activated` flag enforced by
`SYSTEM_PRESERVED_KEYS`. The protection is real; the rulebook points at dead code. Fixing the rulebook
(forward plan **R1**) needs the owner's go-ahead because it edits CLAUDE.md + the joomla-development skill.

---

## 12. Appendices (full evidence, preserved verbatim)

The original section reviews are kept verbatim alongside this document under `docs/analysis/` — nothing
from the source analysis was dropped. This finalised `architecture.md` is the standalone verdict; the
appendices are the long-form evidence behind it.

| Appendix | File | Covers |
|---|---|---|
| A | `appendix-A-root.md` | The root / core principle (output invariant, end-to-end pipeline) — feeds §1 |
| B | `appendix-B-output-map.md` | Full output map (function × sink × translatable, `.htaccess` finding) — feeds §2 |
| C | `appendix-C-page-context.md` | Page-type / context / indexability deep dive — feeds §3 |
| D | `appendix-D-extensibility-wp.md` | Integration SDK + WordPress-readiness — feeds §6 |
| E | `appendix-E-security.md` | Security review (full) — feeds §7 |
| E2 | `appendix-E2-security-critic.md` | Adversarial security-skeptic pass — the `core.manage` custom-code refinement |
| F | `appendix-F-over-engineering.md` | One-person/near-sale over-engineering pass — feeds §9-§10 |

> Capabilities (§4) and Supporting machinery (§8) have **no part-file appendix** — their reader passes
> failed in the original run, which is why they were the two thin sections. They were filled in this
> finalisation directly from `_handoff/docs/option-map.json` (§4) and a live code spot-check (§8); both
> now stand alone. The earlier draft and its part-files remain in `_handoff/docs/` (superseded copies).
