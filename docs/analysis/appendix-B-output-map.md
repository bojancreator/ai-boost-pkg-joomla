# Part 2 — The Output Map ("every function its own place")

> **Dimension 2 of the pre-ship architecture review.** This is the centerpiece: an exhaustive
> enumeration of every OUTPUT SINK the product writes to, which plugin/service owns each sink,
> whether the content is per-language translatable, and a function × sink × page-type × translatable
> matrix. Every claim cites the file/class/method that proves it.
>
> Evidence base: the real code under `aiboost-joomla/component/`, read 2026-06-28. Corroborated
> against `docs/feature-verification.md` (which independently records the `.htaccess` absence) and
> `docs/ARCHITECTURE-BOUNDARIES.md`.

---

## 0. TL;DR for the owner (plain)

The product places its "code & text" into **eight distinct delivery channels**. Five live inside the
HTML page (head, body-top, footer), and three are standalone files/responses the site serves on
demand (`robots.txt`, `sitemap.xml`, `llms.txt` + relatives, plus Markdown and special HTTP headers).

Two architectural facts dominate this map:

1. **The product NEVER touches `.htaccess`.** 301/302 redirects are stored in a database table and
   served by PHP at runtime (`header('Location: …')`), not by Apache rewrite rules. This is portable
   and WordPress-friendly, but it means redirects only fire while Joomla boots (see Risk R1).
2. **One physical file, everything else virtual.** Only `robots.txt` is written to disk (on Save).
   `sitemap.xml`, `llms.txt`, `llms-full.txt`, per-language `llms-*.txt`, the IndexNow key file, and
   Markdown pages are all generated **on the fly** by intercepting the request — no static files,
   nothing to go stale, nothing left behind on uninstall.

Translatability is strong where it matters for AI/Google (Schema, OG, llms.txt, sitemap hreflang)
but is **Pro- and Falang-gated**, and several head sinks (canonical, title/meta templates, analytics,
custom code) are single-language by design.

---

## 1. The eight output sinks (enumerated)

| # | Sink | Physical or virtual | Written by (entry) | Trigger / event |
|---|---|---|---|---|
| S1 | HTML `<head>` — consolidated AI Boost block | virtual (spliced before `</head>`) | `HeadBlockBuilder::finalize()` | every front-end plugin's `onAfterRender` (first caller wins) |
| S2 | HTML `<head>` — native Joomla streams (canonical, hreflang, title, meta-desc) | virtual (Joomla renders) | `Document::addHeadLink()` / `setTitle()` / `setMetaData()` | `onBeforeCompileHead` |
| S3 | HTML `<body>` top — consolidated block (analytics `<noscript>`, custom body code) | virtual (spliced after `<body>`) | `BodyBlockBuilder::finalize()` (body region) | `onAfterRender` |
| S4 | HTML footer — consolidated block (custom footer code) | virtual (spliced before `</body>`) | `BodyBlockBuilder::finalize()` (footer region) | `onAfterRender` |
| S5 | `robots.txt` | **PHYSICAL file on disk** (`JPATH_ROOT/robots.txt`) | `RobotsTxtBuilder::injectManagedBlock()` via `SettingsController::regenerateRobotsTxt()` | admin **Save** (not per-request) |
| S6 | `sitemap.xml` (+ `-index`, `-news`, `-{n}` chunks) | virtual (request intercept) | `AiBoostSitemap::onAfterInitialise()` → `buildUrlset()` | front-end request to the path |
| S7 | `llms.txt` (+ `llms-full.txt`, `llms-{sef}.txt`, `{indexnow_key}.txt`) | virtual (request intercept) | `AiBoostAeo::onAfterInitialise()` → `LlmsTxt(Pro)Generator` | front-end request to the path |
| S8 | HTTP response headers + Markdown body | virtual (response mutation) | `AiBoostAeo` (X-Robots-Tag, Markdown), `AiBoostSitemap` (XML headers) | `onBeforeCompileHead` / `onAfterRender` / `onAfterInitialise` |

There is also one **outbound** sink (not part of the page the user sees) and several **DB** sinks
that are not page output but are listed for completeness in §6.

---

## 2. Sink-by-sink detail

### S1 — Consolidated HTML `<head>` block

**Owner:** `AiBoost\Lib\HeadBlockBuilder` (`component/lib/src/HeadBlockBuilder.php`).

Yoast/GTM-style single block wrapped by `<!-- AI Boost for Joomla - Start/End -->`, spliced once
immediately before `</head>` via a **byte-safe substring splice** (not `preg_replace`, because
user-supplied head HTML may contain `$1`/`\1` regex tokens — see `finalize()` L500-509). Every
front-end plugin calls `finalize()` in its `onAfterRender`; a static `$finalized` flag makes the
first call do the work, so **plugin order does not matter**.

Fixed render order (`HeadBlockBuilder::ORDER`, L87-93): **Schema → Social → AEO → Analytics → Custom
Code**. Each sub-section is fed by a `pushSection()` call:

| Sub-section | Pushing plugin/service | What it emits |
|---|---|---|
| Schema | `aiboost_schema` → `SchemaBuilder::buildAll()` (`AiBoostSchema.php` L164) | `<script type="application/ld+json">` blocks. Pro overlay via `SchemaProBuilder`; YOOtheme bridge also pushes here (`AiBoostIntYootheme.php` L261) |
| Social | `aiboost_social` → `OgTagBuilder::renderProps()` (`AiBoostSocial.php` L162) | `<meta property="og:*">` + `<meta name="twitter:*">` |
| AEO | `aiboost_aeo` (`AiBoostAeo.php` L235, L252) | `<meta name="ai-content-verified/optimized">`, `<meta name="llms-txt">`, Markdown discovery `<link rel="alternate" type="text/markdown">` |
| Analytics | `aiboost_analytics` (`AiBoostAnalytics.php` L365) | GSC verification `<meta>`, FB domain verification `<meta>`, GTM/GA4/Meta-Pixel `<script>` |
| Custom Code | `aiboost_code` (`AiBoostCode.php` L192) | raw user `custom_code_head` HTML — **always last** so it can override AI Boost output; **never** conflict-trimmed |

**Cooperative dedup** runs at finalize time (`trimBlockConflicts()`, L319) — if a third party already
emits OG / `ai-content-verified` / single-instance Organization JSON-LD / GA4-GTM-Pixel in *their*
`<head>`, AI Boost removes **only its own** matching tag (the safety invariant: it never reads or
writes the third party's tags). Custom Code is exempt by construction (`trimOwnSections()`, L291-301).

A registered integration bridge can mutate the whole rendered block via the
`EVENT_FILTER_HEAD_OUTPUT` SDK event just before splice (L529-537) — falls back to the original on any
malformed return so a buggy bridge can never blank the head.

### S2 — Native Joomla head streams (canonical, hreflang, title, meta-description)

**Owner:** `aiboost_core` (`AiBoostCore::onBeforeCompileHead()`).

These deliberately do **not** go through S1 — they use Joomla's dedicated streams so templates and
other extensions can dedup/override them:

| Output | Method | Source |
|---|---|---|
| `<link rel="canonical">` | `$document->addHeadLink(…, 'canonical')` (L246) | `resolveCanonical()` — `canonical_url_map` JSON path-prefix match, else `scheme://host/path` |
| `<title>` rewrite | `$document->setTitle()` (L500) | per-page-type title templates (`applyTitleTemplate()`) |
| `<meta name="description">` rewrite | `$document->setMetaData('description', …)` (L550) | per-page-type meta-desc templates (`applyMetaDescTemplate()`) |
| `<link rel="alternate" hreflang>` | `addHeadLink()` (Pro hreflang / `aiboost_int_falang`) | Pro decorator; the `aiboost_hreflang_pro` plugin today is a gating skeleton (`AiBoostHreflangPro.php` L5-13) |

Each is registered with `HeadBlockBuilder::noteNative()` (L247, L262, L267) so the consolidated block
header lists them under "Also emitted via Joomla head". All gated by `ConflictPolicy::shouldApply
Exclusive()` (defer/takeover per feature) and globally suppressed when `staging_mode` is on (L230).

### S3 / S4 — Consolidated `<body>` top and footer blocks

**Owner:** `AiBoost\Lib\BodyBlockBuilder` (`component/lib/src/BodyBlockBuilder.php`).

Two wrappers: one spliced immediately after the opening `<body…>` tag (S3), one before `</body>`
(S4). Fed by `pushBody()` / `pushFooter()`:

| Region | Content | Pusher |
|---|---|---|
| body-top (S3) | GTM `<noscript><iframe>` | `AiBoostAnalytics.php` L172 |
| body-top (S3) | Meta Pixel `<noscript><img>` | `AiBoostAnalytics.php` L359 |
| body-top (S3) | user `custom_code_body` | `AiBoostCode.php` L203 |
| footer (S4) | user `custom_code_footer` | `AiBoostCode.php` L214 |

Empty regions produce no wrapper at all. Cooperative body-noscript dedup via `trimBodyConflicts()`
(L144) keys on body-only signals (`googletagmanager.com/ns.html`, `facebook.com/tr?`) so head GTM/Pixel
can't false-trigger it.

### S5 — `robots.txt` (the ONLY physical file)

**Two parallel code paths — a known design wart (see Risk R2):**

1. **Disk write on Save (authoritative).** `SettingsController::regenerateRobotsTxt()`
   (`SettingsController.php` L428-460) writes `JPATH_ROOT/robots.txt` using
   `RobotsTxtBuilder::injectManagedBlock()` (`component/lib/src/RobotsTxtBuilder.php`). AI Boost owns
   **only a fenced block** (`# BEGIN AI Boost for Joomla managed block … # END`, L38-39) — user rules
   outside the fence are preserved; turning management off strips just the block (deletes the file
   only if it was entirely ours). Web servers serve this file directly from disk; the in-code comment
   (L247-248) explains Joomla's standard `.htaccess` excludes `robots.txt` from PHP rewriting — which
   is *why* a physical file is needed.
   - Content: Joomla system-path `Disallow`s, public-asset `Allow`s, `Sitemap:` line (unless
     `enable_sitemap=0`), per-bot SEO scraper blocks (`scraper_*`), custom scraper textarea, the AI
     Crawler per-bot Allow/Block matrix (`crawler_bot_rules` JSON + `aeo_crawler_default_policy`,
     `ai_crawlers_enabled`), and a custom-crawler textarea (`crawler_rules`).

2. **Runtime read+append (`RobotsTxtManager`, different marker scheme).**
   `component/.../aiboost_aeo/src/Service/RobotsTxtManager.php` reads the on-disk file and appends a
   `# [AI Boost AEO — managed section]`. It adds **staging/dev/local host detection** that forces
   `Disallow: /` for all bots (L180-187) and fires `EVENT_FILTER_ROBOTS_RULES` so the Pro AEO plugin /
   integration bridges (JoomSEF) can decorate. **The two paths use different marker strings** — flagged
   in `feature-verification.md` for pre-1.0 dedup review.

### S6 — `sitemap.xml` family (virtual, request-intercepted)

**Owner:** `aiboost_sitemap` (`AiBoostSitemap::onAfterInitialise()`).

No static file. The plugin intercepts `sitemap.xml`, `sitemap-index.xml`, `sitemap-news.xml`, and
`sitemap-{n}.xml` chunks *before* Joomla routes (L104-158), builds the `<urlset>` from
`SitemapGenerator` + extensions, sets `Content-Type: application/xml`, `Cache-Control`, and
`X-Robots-Tag: noindex` (`sendXml()`, L534-542), then `Factory::getApplication()->close()`. A clean
output buffer is opened first (`beginCleanResponse()`) and any stray third-party notices are discarded
(`discardStrayOutput()`) so the XML never gets corrupted by Falang/PHP-8.5 deprecations.

Page-type coverage (from `makeGenerator()`, L453-477): articles (`include_articles`), menu items
(`include_menu_items`), categories (`include_categories`, default OFF), tags (`include_tags`, Pro).
Pro adds `<image:image>` (`ImageSitemapExtension`), hreflang `<xhtml:link>` (`HreflangSitemapExtension`
— gated on **both** Pro AND `int_falang` Multilang licence, L383-385), sitemap index + chunks, and
news sitemap. Bridges can rewrite the URL set via `EVENT_FILTER_SITEMAP_URL_SET` (L244-252).

### S7 — `llms.txt` family (virtual, request-intercepted)

**Owner:** `aiboost_aeo` (`AiBoostAeo::onAfterInitialise()`).

| Path | Tier | Generator | Translatable |
|---|---|---|---|
| `/llms.txt` | Free | `LlmsTxtGenerator::generate()` (L156-194) | base = default language only; Pro rebuild adds translations |
| `/llms.txt` (Pro rebuild) | Pro | `LlmsTxtProGenerator::generate()` (L164-171) | **Yes** — per-language via `TranslationService`, + "Full Index" reference |
| `/llms-full.txt` | Pro | `LlmsTxtProGenerator::generateFull()` (L118-127) | full article+category index |
| `/llms-{sef}.txt` | Pro | `LlmsTxtProGenerator` with `$langCode` (L95-116) | **Yes** — one file per published content language (SEF→`#__languages` lookup) |
| `/{indexnow_key}.txt` | Pro | inline echo of the key (L129-135) | n/a (verification token) |

Free `/llms.txt` content (`LlmsTxtGenerator`): top-level menu **Pages**, **Additional Pages**
(`llmstxt_custom_pages` JSON), **Recent Content** (articles), **About**, **Social Media**, **FAQ**.
All served with `Content-Type: text/plain; charset=utf-8` + `Cache-Control`. Third-party bridges can
decorate via `EVENT_FILTER_LLMS_TXT` (L173-187) — applied **after** the Pro pass so bridges get the
last word. A matched Pro path whose feature is OFF returns cleanly (404-style) rather than falling
through to the Markdown detector (L137-140).

### S8 — HTTP response headers + Markdown body

| Output | Owner | Detail |
|---|---|---|
| `X-Robots-Tag: index, follow` | `AiBoostAeo::onBeforeCompileHead()` L219-221 | Pro, gated `enable_x_robots_header` + `isProActive` |
| `Content-Type: text/markdown` + `Cache-Control` + `X-Content-Type-Options: nosniff` | `AiBoostAeo::onAfterRender()` L368-370 | when the request is a Markdown request |
| Markdown page **body** (replaces HTML) | `MarkdownConverterService::convert()` (`component/lib/src/MarkdownConverterService.php`) | Free; triggered by `.md` suffix / `?markdown=1` / `Accept: text/markdown` (L385-428); converts the main content region (`<main>`, `#sp-component`, `.item-page`, `.blog`, `<article>`, fallback `<body>`) to clean Markdown |
| Markdown discovery `<link rel="alternate" type="text/markdown">` | `AiBoostAeo::onBeforeCompileHead()` L246-259 | Free; into S1 AEO section |
| `Content-Type: application/xml` + `X-Robots-Tag: noindex` | `AiBoostSitemap::sendXml()` L537-539 | sitemap responses |
| `text/plain` + `Cache-Control` | `AiBoostAeo` llms.txt branches | llms responses |
| `X-AiBoost-Perf` | `AiBoostCore::onAfterRender()` L313 | debug-only perf probe |

**Markdown serving is page-type-agnostic** — it operates on *any* rendered HTML page by extracting the
main content region, so homepage / article / category / YOOtheme pages all convert (quality varies
with how well the template marks up its main region).

---

## 3. The CRITICAL `.htaccess` / redirect finding

**Verified: AI Boost does NOT write, read, or manage `.htaccess` anywhere.** A repo-wide search for
`htaccess|RewriteRule|mod_rewrite` across `component/` returns only **two explanatory comments**
(`SettingsController.php` L248 and `package/pkg_script.php` L1007) noting that Joomla's standard
`.htaccess` excludes `robots.txt` from PHP rewriting. There is no rewrite-rule generator, no
`.htaccess` editor, no setting key. `feature-verification.md` independently records the same explicit
absence.

**How 301/302 redirects actually work** (`AiBoostCore::handleRedirects()`, L560-628):

1. Rules are stored in the DB table **`#__aiboost_redirects`** (columns `from_url`, `to_url`,
   `redirect_type`, `enabled`, `hits`), managed by the admin Redirects page.
2. On every front-end request, `onAfterInitialise()` (which runs **after Joomla boots** but before
   routing) loads enabled rules, matches the current path/full-URL (exact or `*` wildcard, L599-604),
   increments the hit counter, and issues `header('Location: ' . $to, true, $code)` then `exit`
   (L619-620). Supported codes: 301/302/303/307/308 (default 301).
3. Suppressed entirely when `staging_mode` is on (L112-115).

**Implication (Risk R1):** because redirects run inside PHP after Joomla boots, they are heavier than
a server-level `.htaccess`/Nginx rule and only cover URLs that reach Joomla. A request blocked or
served by the web server before PHP (e.g. a real file on disk) never hits this handler. This is a
deliberate portability choice — it ports cleanly to WordPress (where `template_redirect` is the
equivalent hook) and needs no server config — but it is a runtime cost on every page load and is not
a substitute for server-level redirects on high-traffic sites.

---

## 4. THE MATRIX — function × sink × page-type × translatable

Page-type columns: **H**=homepage, **A**=article, **C**=category, **M**=other menu types (single
article/contact/custom), **Y**=YOOtheme builder page. Cell legend: ✓ emitted, –  not applicable/​absent,
`co` cooperative-skip possible. **Translatable** = per-language content varies (not just URL).

### 4.1 HTML `<head>` (S1 + S2)

| Function (key) | Sink | H | A | C | M | Y | Translatable | Owner / proof |
|---|---|---|---|---|---|---|---|---|
| Schema JSON-LD base (`enable_schema`) | S1 Schema | ✓ | ✓ | ✓ | ✓ | ✓ (Y bridge L261) | **Yes (Pro + Falang)** — `SchemaProBuilder` + `TranslationService`, gated `hasPro('int_falang')` (`AiBoostSchema.php` L127-137) | `aiboost_schema` |
| Schema Pro detail/types | S1 Schema | ✓ | ✓ | ✓ | ✓ | ✓ | Yes (Pro + Falang) | `SchemaProBuilder` |
| OpenGraph (`enable_opengraph`) | S1 Social | ✓ | ✓ | ✓ | ✓ | ✓ | **Yes (Pro)** — `OgTagProDecorator` translates site_name / og_description / image (`AiBoostSocial.php` L129-139); `co` (`SIG_OG_TITLE`) | `aiboost_social` |
| Twitter Cards (`enable_twitter_cards`) | S1 Social | ✓ | ✓ | ✓ | ✓ | ✓ | Yes (Pro) | `aiboost_social` |
| YOOtheme per-page OG override (Free) | S2 (doc meta) | – | – | – | ✓ (menu pages) | ✓ | follows source page lang | `AiBoostIntYootheme::onAfterRoute()` L187 |
| AEO AI-signals meta (`aeo_ai_meta_enabled`) | S1 AEO | ✓ | ✓ | ✓ | ✓ | ✓ | No (static hints) | `AiBoostAeo.php` L225-242 |
| Markdown discovery `<link>` (`markdown_pages_enabled`) | S1 AEO | ✓ | ✓ | ✓ | ✓ | ✓ | URL-only (per-page) | `AiBoostAeo.php` L246-259 |
| GSC verification (`enable_google_verification`) | S1 Analytics | ✓ | ✓ | ✓ | ✓ | ✓ | No | `AiBoostAnalytics.php` L98 |
| FB domain verification | S1 Analytics | ✓ | ✓ | ✓ | ✓ | ✓ | No | L130 |
| GTM (`enable_gtm`) | S1 Analytics + S3 noscript | ✓ | ✓ | ✓ | ✓ | ✓ | No; `co` (`SIG_GTM`) | L140 |
| GA4 (`enable_ga4`) | S1 Analytics | ✓ | ✓ | ✓ | ✓ | ✓ | No; `co` (`SIG_GA4`) | L177 |
| Meta Pixel (`enable_meta_pixel`) | S1 Analytics + S3 noscript | ✓ | ✓ | ✓ | ✓ | ✓ | No; `co` (`SIG_META_PIXEL`) | L273 |
| Custom head code (`custom_code_head`) | S1 Custom Code | ✓* | ✓* | ✓* | ✓* | ✓* | No (raw HTML, never trimmed) | `AiBoostCode.php` L192 (*scope: all or specific menu IDs) |
| Canonical (`enable_canonical`) | S2 native | ✓ | ✓ | ✓ | ✓ | ✓ | No (URL only) | `AiBoostCore.php` L242-249 |
| Canonical URL map (`canonical_url_map`) | S2 native | ✓ | ✓ | ✓ | ✓ | ✓ | No | `resolveCanonical()` L352 |
| Title template (per type) | S2 native | ✓ home | ✓ article | ✓ category | ✓ default | ✓ default | partially (`{site_name}` from Joomla lang) | `applyTitleTemplate()` L453; page type via `detectPageType()` L376 |
| Meta-desc template (per type) | S2 native | ✓* | ✓ | ✓ | ✓ default | ✓ default | partially | `applyMetaDescTemplate()` L515 (*GAP: home/search/tag fall back to global — see Risk R4) |
| hreflang alternates | S2 native | ✓ | ✓ | ✓ | ✓ | ✓ | **Yes (Pro + Falang)** | Pro hreflang / `aiboost_int_falang`; `aiboost_hreflang_pro` is a skeleton today |

### 4.2 HTML `<body>` / footer (S3 / S4)

| Function | Sink | H | A | C | M | Y | Translatable | Owner |
|---|---|---|---|---|---|---|---|---|
| GTM `<noscript>` | S3 | ✓ | ✓ | ✓ | ✓ | ✓ | No | `AiBoostAnalytics.php` L172 |
| Meta Pixel `<noscript>` | S3 | ✓ | ✓ | ✓ | ✓ | ✓ | No | L359 |
| Custom body code (`custom_code_body`) | S3 | ✓* | ✓* | ✓* | ✓* | ✓* | No | `AiBoostCode.php` L203 |
| Custom footer code (`custom_code_footer`) | S4 | ✓* | ✓* | ✓* | ✓* | ✓* | No | L214 |

### 4.3 Standalone files / responses (S5 / S6 / S7 / S8)

| Function | Sink | Page-type scope | Translatable | Owner |
|---|---|---|---|---|
| `robots.txt` managed block (`enable_robots`) | S5 physical | site-wide (one file) | No (one file, all langs) | `RobotsTxtBuilder` via `SettingsController` L428 |
| Sitemap base (articles/menus/cats) | S6 virtual | A + M + C + tags(Pro) | URL-level (multilingual via hreflang only) | `SitemapGenerator` |
| Sitemap hreflang `<xhtml:link>` | S6 virtual | A + M | **Yes (Pro + Falang)** | `HreflangSitemapExtension` L383-385 |
| Sitemap images `<image:image>` (Pro) | S6 virtual | entries with intro image | n/a | `ImageSitemapExtension` |
| News sitemap (Pro) | S6 virtual | news category articles | per-article | `NewsSitemapGenerator` |
| `llms.txt` (Free) | S7 virtual | menus + custom + recent articles + about/social/FAQ | default lang only | `LlmsTxtGenerator` |
| `llms.txt` (Pro) / `llms-{sef}.txt` | S7 virtual | same, per language | **Yes (Pro + Falang)** | `LlmsTxtProGenerator` |
| `llms-full.txt` (Pro) | S7 virtual | full article+category index | (default lang) | `LlmsTxtProGenerator::generateFull()` |
| IndexNow key file | S7 virtual | n/a | n/a | inline echo (`AiBoostAeo.php` L129) |
| Markdown page | S8 body | **any** page with a detectable main region | follows source page lang | `MarkdownConverterService` |
| X-Robots-Tag header (Pro) | S8 header | site-wide | No | `AiBoostAeo.php` L219 |
| 301/302 redirect | HTTP `Location` (no `.htaccess`) | any URL reaching Joomla | No | `AiBoostCore::handleRedirects()` L560 |

---

## 5. Translatability summary (the AI/Google-visibility lens)

| Translatable per-language | Single-language by design |
|---|---|
| Schema JSON-LD (Pro + Falang) | Canonical URL + URL map |
| OpenGraph / Twitter (Pro) | Title / meta-desc templates (mostly — `{site_name}` follows Joomla lang) |
| hreflang (head + sitemap; Pro + Falang) | Analytics (GSC/FB/GTM/GA4/Pixel) — IDs are global |
| `llms.txt` + `llms-{sef}.txt` (Pro + Falang) | Custom head/body/footer code (raw HTML) |
| Markdown pages (follow source page) | `robots.txt` (one file, all langs) |
| AEO AI-signal meta (static, lang-neutral) | X-Robots-Tag |

**Key gating fact:** essentially every per-language translation site is gated on **both** Pro
activation **and** `PluginRegistry::hasPro('int_falang')` (the Multilang/Falang SKU) — e.g.
`AiBoostSchema.php` L134, `AiBoostSocial.php` L132, sitemap hreflang L383-385. Native Joomla
multilingual content still flows through Joomla's own per-language rendering, but AI Boost's
*overlay* translations (translated schema/OG/llms) require the Falang add-on. This matches the
memory note that multilingual (esp. on Falang) is the product's verified competitive edge.

---

## 6. Non-page sinks (for completeness)

| Sink | What | Owner |
|---|---|---|
| Outbound HTTP POST to `api.indexnow.org` | URL submission on article publish (Pro) | `IndexNowService::submit()` (`aiboost_aeo/src/Service/IndexNowService.php` L56) |
| Outbound ping to Google/Bing | sitemap ping on publish/save (Pro) | `SearchEnginePingService` (`aiboost_sitemap`) |
| `#__aiboost_redirects` (DB) | redirect rules + hit counters | `AiBoostCore` |
| `#__aiboost_404_log` (DB) | 404 monitoring upsert | `AiBoostCore::log404Request()` L633 |
| `#__aiboost_error_log` (DB) | AI Boost warnings/errors | `Logger` (`error_log_enabled`) |
| Joomla log file `aiboost_aeo.php` | IndexNow audit trail | `IndexNowService::ensureLogger()` L119 |
| Admin-only injected CSS (article edit dark theme) | `$document->addStyleDeclaration()` | `AiBoostCore::onBeforeRender()` L188 |

---

## 7. Risks & gaps (output-map specific)

- **R1 — Redirects are PHP-runtime, not server-level.** Every front-end request pays the cost of
  loading + matching all enabled rules in `onAfterInitialise` before the page renders; the redirect
  only fires for URLs that reach Joomla. Fine for portability/WordPress, but not equivalent to an
  `.htaccess`/Nginx redirect for performance or for paths short-circuited by the web server. No
  bulk/regex-grouped indexing of rules is visible — large rule tables scan linearly (L589).
- **R2 — Two parallel robots.txt code paths with different markers.** The Save-time fenced disk write
  (`RobotsTxtBuilder`, `# BEGIN … managed block`) and the runtime `RobotsTxtManager`
  (`# [AI Boost AEO — managed section]`) coexist with different fences and partly overlapping content.
  `feature-verification.md` already flags this for pre-1.0 dedup. Risk: double-output or
  marker-mismatch leaving an unstripped block. The disk path is the authoritative one in practice.
- **R3 — Per-language overlay is hard-gated on Falang (`int_falang`).** Translated Schema/OG/llms.txt
  and ALL sitemap hreflang require the Multilang add-on even on native-multilingual Joomla sites
  (e.g. sitemap L383-385). A native-multilingual customer without Falang gets default-language
  schema/OG and no sitemap hreflang. Confirm this is the intended product boundary (this is an OPEN
  ODLUKA already noted in project memory).
- **R4 — Meta-description template page-type gap.** `applyMetaDescTemplate()` does a generic
  `meta_desc_template_{type}` lookup, but the manifest defines only `article`/`category`/`default`
  keys — `home`/`search`/`tag` page types silently fall back to the global template
  (`feature-verification.md` notes this on `meta_desc_template_default`). A dead-ish per-type path.
- **R5 — `robots_auto_sync` is an orphaned/dead option** (no runtime consumer; robots.txt regenerates
  unconditionally on save when `enable_robots=1`). Output-irrelevant but pollutes the sink's settings
  surface — remove or wire before 1.0.
- **R6 — Markdown conversion quality is template-dependent.** `MarkdownConverterService` finds the
  main region by a fixed selector list (`<main>`, `#sp-component`, `.item-page`, `.blog`, `<article>`,
  else `<body>`). Templates that don't use these (custom/builder layouts, some YOOtheme pages) fall
  back to the whole `<body>` and may include chrome the strip list doesn't catch. Worth a visual
  spot-check per target template before claiming "Markdown for every page".
- **R7 — Canonical/hreflang via native head stream can be stripped by third parties.**
  `feature-verification.md` records (order 0005) that on a Falang+YOOtheme multilingual site, canonical
  (an `addHeadLink`) is rewritten/stripped on language sub-paths by Falang/YOOtheme — not an AI Boost
  bug, but a real output-delivery fragility for S2 sinks that the consolidated S1 block does not have.
