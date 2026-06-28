# AI Boost for Joomla — Backlog (foundation-first master list)

The **single permanent forward list** of remaining work. It is written to stand alone: assume chat
memory is wiped — everything needed to know *what is left and why* is here, with a pointer to the
analysis doc that holds the full detail. When you pick up an item, follow the **Definition of Done** in
`OPERATING.md`; when it is shipped and verified on a Free **and** a Pro test site, delete its line (that
deletion *is* marking it done).

**Where we are now** (version, branch, deployed): `STATUS.md`.
**Why / full evidence** (every claim has `file:line`):
`docs/analysis/architecture.md` (the ROOT→TRUNK→BRANCHES→TWIGS plan = §10),
`docs/analysis/licensing-and-pro-gating.md` (gating, findings P1–P8),
`docs/analysis/custom-fields.md` (fields, decisions D1–D6),
`_handoff/docs/option-map.json` (all 276 options with status).

**Legend (every item is tagged):**
✅ **DO** — on the pre-sale path · ❌ **REMOVE** — delete dead weight · ❓ **OPEN** — needs a product
decision · ⏸ **POST-LAUNCH** — deliberately deferred. Items confirmed by Bojan on 2026-06-28 are marked
**(Bojan)**. Pointers: `→ arch §X` / `→ licensing PX` / `→ fields DX` / `→ option-map` / `→ old-backlog`.

> **The shape of the work (plain framing):** the output engine — how your text and code reach the page —
> is ship-grade. The one real foundation job left is teaching the product to decide, in ONE place, *which
> page it is on and whether it should be indexed* (that fix also makes WordPress possible later). After
> that it is cleanup: finish the multilingual translation, delete the old leftover machinery, correct a
> few marketing claims the product can't back, and add tests so the health check can't lie. No leftover
> machinery and no WordPress work is needed to ship Joomla to sale. *(→ arch §0, §9)*

---

## ROOT — confirm/harden what the whole product stands on (mostly done; small fixes)

- ✅ **DO — Document/guard the static `finalize()` per-request dependency.** All builder state is
  `private static` and `reset()` is never called front-end, so order-independence relies on per-request
  PHP process death (fine on PHP-FPM/mod_php, breaks on Swoole/RoadRunner/FrankenPHP). Document it (or add
  a front-end `reset()` guard). Doc-only, low urgency — only matters before a persistent-runtime future.
  *(→ arch §10 R3, §9)*

---

## TRUNK — the structural fixes the product most needs before sale

- 🔄 **IN PROGRESS — Build ONE CMS-neutral Page-Type / Entity / Indexability / Canonical resolver (T1)**
  *(biggest single task; architecture's #1 pre-sale recommendation).* It (a) kills the ~20× duplicated
  article gate; (b) retires the known-buggy `detectPageType()` path/featured homepage definition and
  unifies on the menu-`home` flag; (c) gives the product the **indexability authority** it lacks today, so
  canonical/sitemap/schema/llms/Markdown stop disagreeing (and fixes the Markdown/llms duplicate-content
  hazard); (d) is the prerequisite that makes the WordPress seam runnable. Design approved (order 0018,
  `docs/analysis/T1-resolver-design.md`); shipping slice-by-slice, each behaviour-preserving unless marked:
  - **S0 ✅ DONE (order 0019, v0.87.62):** `lib/src/Page/*` (PageType, PageContext, PageResolver,
    IndexabilityPolicy) + `AdapterRegistry::pageResolver()` + bootstrap — wired but consumed by nobody;
    front-end byte-identical on Pro+Free; 19 unit tests, red-green proven.
  - **S1 ✅ DONE (order 0021, test-only — no version bump):** 33 characterization tests covering the FULL
    migration map (H1–H2, P1–P12, I1–I6, C1, L1–L3): resolver-equivalence for the article/homepage/
    category/canonical gates, behavioural SchemaProBuilder gate exclusion, and source-contract pins for the
    Factory/SQL-coupled sites. Suite 494 green, red-green proven. **Finding for S2 (ODLUKA):** the inline
    article gate is homepage-agnostic but the resolver classifies homepage-first → they diverge on a
    **single-article homepage** (today Article schema emits there; `PageContext::isArticle()` is false) —
    S2 must preserve today's behaviour or take an explicit decision.
  - **S2 ✅ DONE (order 0022, v0.87.63):** Schema layer reads `AdapterRegistry::pageResolver()` `PageContext`
    — `SchemaProBuilder` article gates (P3–P8) from RAW `option/view/rawId`, Free `SchemaBuilder::buildWebSite`
    (P10) from injected `isHomepage`. Used raw primitives (NOT homepage-first `isArticle()`) so the
    single-article-home case is preserved byte-for-byte (the S1 divergence finding → that semantics change is
    S7). Golden diff byte-identical: Pro staging before↔after (clean v0.87.62 baseline) + Free same-version
    isolation diff. Suite 494 green (single-article-home test still green); installed Pro+Free; Health 94/100.
  - **S3 ✅ DONE (order 0023, v0.87.64):** Social/OG layer reads `AdapterRegistry::pageResolver()` `PageContext`
    — `OgTagBuilder` (P11) seeds the props `context` block from RAW `option/view/rawId`; `OgTagProDecorator`
    (P12, `:123`) article gate reads the same raw primitives (falls back to `props['context']`). Raw primitives
    (NOT homepage-first `isArticle()`) → single-article-home `og:type=article` preserved byte-for-byte (S7 owns
    that semantics change). Golden diff OG/Twitter byte-identical: Pro staging before↔after (clean v0.87.63
    baseline) + Free same-version isolation (0.87.64 with vs without). Suite 494 + 3/3 green; installed Pro+Free;
    Health 94/100.
  - **S4** — bulk `IndexabilityPolicy` for the 4 enumerators (sitemap/news/llms/llms-full), SQL-parity-diff-guarded. **(NEXT)**
  - **S5** — canonical onto the resolver.
  - **S6** — expose language facts (active + site-default) via the resolver.
  - **S7** ⚠ — THE behaviour change: unify homepage detection on the menu `home=1` flag (own sign-off).
  - **S8** — per-page noindex emitter + Markdown/llms noindex authority (opt-in, default-OFF).
  - **S9** — cleanup + a contract test forbidding new inline `com_content`/`article` gates outside `lib/src/Page/`.
  *(→ arch §10 T1, §3, §9)*
- ✅ **DO (Bojan) — Finish the multilingual moat (`falang_schema_translate`).** Translate HowTo step
  names + FAQ items per language (currently English fallback). This sits on the product's only
  live-measured competitive edge (vs 4SEO, order 0009) — finish it before claiming multilingual depth.
  *(→ arch §10 T2; option-map `falang_schema_translate`)*
- ✅ **DO (Bojan) — Finish `manual_faq_scope`.** Port the scope filter into `SchemaProBuilder::decorateAll()`
  (using the `ctx->isHomepage()` it already holds) so manual FAQ on non-article pages actually respects
  the setting (today the option saves but no consumer reads it). Lower-impact companion: add a
  `q→question` / `a→answer` compatibility shim so legacy/imported `{q,a}` manual-FAQ data isn't silently
  skipped. *(→ old-backlog; option-map `manual_faq_scope`, `enable_manual_faqs`; arch §10 T3)*
- ✅ **DO (Bojan) — Config-surface cleanup (mechanical, codegen-guarded).** Delete the dead options,
  collapse the 4 parallel hreflang keys to one, align the 4 tier-mismatches, and resolve the phantom
  keys (wire a UI or remove). Exact targets in the **Config-cleanup appendix** at the bottom. Each
  deleted dead option is one fewer thing to test, document, and explain to a customer.
  *(→ arch §10 T3; licensing P6; fields D6; option-map)*
- ✅ **DO (Bojan) — Pro-installed-but-no-key UX.** Add a lightweight, visible **banner/notice** (NOT a
  full wizard) that, when `isProInstall && !isProActive`, routes the admin to the Licenses tab with a
  clear "enter your key to switch on Pro" message. Today a paying customer who never enters a key sees a
  Free-looking product — the single most likely "I paid and it doesn't work" support risk. *(→ licensing
  P5/ODLUKA 1)*
- ✅ **DO (Bojan) — Custom-fields screen + single source-of-truth catalogue.** Build ONE PHP catalogue
  class that every creator/reader/Health check/screen consumes, then: a read-only screen listing ALL AI
  Boost custom fields (article + user) with status (exists / populated) and **warnings when a gating
  toggle is on but its fields are missing**; and an **auto-create button for the 3 event fields** (and
  `aiboost_schema_type`), extending the existing OG/author auto-create. Field **names stay fixed** and
  **one-author-per-article stays** — for launch. Also closes the duplicated OG field definitions.
  *(→ fields D1+D2+D3 (+G9); arch §10 T3)*

---

## MAIN BRANCHES — competitive readiness and honest scope

- ✅ **DO (Bojan) — Correct the marketing claims (the dominant risk) + multilingual positioning.**
  Positioning = **HYBRID**: broad headline "better for multilingual sites" with Falang + per-language
  translation as the proof. Also: drop "36 schema types" → ~12 correct graphs; drop FAQ/HowTo
  rich-result promises (Google killed HowTo rich results 2023, restricted FAQ); de-emphasise SearchAction
  (sitelinks search box retired Nov 2024); reposition llms.txt/Markdown/AI-Visibility as
  **AI-agent/documentation accessibility, not AI ranking**; move custom-code out of the headline.
  *(→ arch §10 B1, §5; memory 4seo-competitor-multilingual)*
- ❓ **OPEN — Google Search Console integration.** The #1 competitive gap vs 4SEO (without measurement the
  tool is "set and hope"). Recommended; decide when reached. Pair with making Markdown/llms **noindex +
  out-of-sitemap** (part of the TRUNK indexability authority). *(→ arch §10 B2, §5)*
- ✅ **DO — Test floor under the flagship surfaces.** Unit tests for `HealthCheckService`, the three
  analyzers, `DuplicateTagScanner`, and especially the URL Checker SSRF allow-list — so a verifier can
  never silently become an always-pass — plus the central output-escaping test (security #5). *(→ arch
  §10 B3, §8)*
- ✅ **DO (Bojan) — CI/scope hygiene.** Take the legacy top-level `plugins/` tree out of the active CI
  matrix and re-point `composer test` at the component, so "CI green" means "the live product is green."
  *(→ arch §10 B4, §9)*

---

## TWIGS — sequenced releases & pre-wide-launch hardening

- ✅ **DO (Bojan) — "Collapse" release: stop shipping the 5 dormant `*_pro` plugins + delete the orphaned
  packaging.** Remove the 5 `aiboost_*_pro` directories and the dead `build_pro_package_zip()` /
  `pkg_script_pro.php` / `pkg_aiboost_pro.xml`. **Careful:** install-lifecycle order with a clean-uninstall
  test on BOTH targets; **keep `sweepCollapsedProDecorators()`** (it cleans sites that still carry the old
  rows). *(→ licensing P4; arch §10 W1)*
- ✅ **DO (Bojan) — Dedup the two robots.txt code paths.** `RobotsTxtBuilder` ("# BEGIN AI Boost… managed
  block") vs the runtime `RobotsTxtManager` ("# [AI Boost AEO — managed section]") use different markers;
  converge onto the authoritative `RobotsTxtBuilder` (this is also where `robots_custom_rules` becomes
  live). *(→ arch §10 W2, §2 R2; option-map `robots_custom_rules`)*
- ⏸ **POST-LAUNCH — Before a WIDE public launch: harden custom-code ACL.** Gate the custom-code save path
  on `core.admin` / a dedicated `aiboost.editcode` ACL (today any `core.manage` on `com_aiboost` can
  inject raw HTML), and add a Health warning when `custom_code_*` is non-empty while >1 group holds
  `core.manage`. *(→ arch §10 W3, §7)*
- ⏸ **POST-LAUNCH — Fix the 2 broken YOOtheme parsers, then market YOOtheme.** FAQ accordion parser
  (`buildAccordionFaqSchema` non-greedy regex truncates on nested `<div>`; 0/6 pages) and gallery parser
  (`buildGallerySchema` expects old `data-caption`, incompatible with `uk-lightbox`; 0/51). Rewrite to
  read element semantics, not regex. The YOOtheme "moat" cannot be marketed until fixed. *(→ arch §10 W4,
  §5; option-map broken-4; old-backlog "YOOtheme menu-param mapping")*

---

## POST-LAUNCH / NICE-TO-HAVE (deferred)

**WordPress (architecture-gated):**
- ⏸ **POST-LAUNCH — FREEZE WordPress work.** Keep the cheap adapter *interfaces* (load-bearing for
  testability today); build **no** Wp impls / content-repository seam / entry point until Joomla revenue
  justifies entering the Yoast/Rank Math arena. When you do, the first task is the **content-repository
  seam** (~200 `#__` queries — the real cost), and a full nonce/capability/`prepare`/`kses` security
  layer is a hard prerequisite. Subsumes the old "WordPress vertical slice", "thin Joomla plugin classes
  into platform entrypoints", and the `ARCHITECTURE-BOUNDARIES.md` boundary work (route CMS calls through
  adapters; content-repository seam; WP data adapter; first standalone+integrative plugin). *(→ arch §10
  W5, §6; old-backlog "cross-platform boundary work")*

**Custom fields (deferred):**
- ⏸ **POST-LAUNCH (Bojan) — Configurable custom-field names.** Let admins map AI Boost outputs onto their
  own existing fields. Only if customers ask — most prefer the one-click create buttons. *(→ fields D4)*
- ⏸ **POST-LAUNCH (Bojan) — Per-article author override / co-authors.** Today the author is always the
  article's `created_by`; no override, no multiple authors. Larger product change. *(→ fields D5/G5)*

**Schema / output polish:**
- ⏸ **POST-LAUNCH — Finish or delete `schema_breadcrumb_pro` / "Enhanced BreadcrumbList".** It is a codegen
  stub with no output; the basic breadcrumb already works Free. *(→ option-map; arch §5)*
- ⏸ **POST-LAUNCH — Custom-Code per-menu scope (8 keys) decision.** The picker was removed from the UI but
  the plugin still honours stored values. Either restore a per-page/per-menu scope picker or remove the
  keys cleanly (`custom_code_{head,body,footer}_{scope,menu_ids}` + delete the 2 legacy `custom_code_scope`
  / `custom_code_menu_ids`). *(→ option-map; arch §5)*
- ⏸ **POST-LAUNCH — Polish Schema.org card ordering.** Reorder cards so foundational schema precedes
  optional rich-result types. Feature set unchanged; high-risk/low-urgency on the most important page —
  the only remaining Admin-IA-rework item. *(→ old-backlog)*
- ⏸ **POST-LAUNCH — Extra social-profile fields** (replacement for the removed Pinterest field): 2–3
  generic name+URL fields feeding Schema.org `sameAs`, clearly labelled "official profiles only"
  (Mastodon/Bluesky/Threads). Do it properly this time. *(→ old-backlog)*

**Admin UX / integrations:**
- ⏸ **POST-LAUNCH — Integration master toggles shown locked (upsell).** Render
  `integration_falang_enabled` / `integration_yootheme_enabled` as a ProGate-locked control with an
  "available as a paid add-on" message when the SKU is inactive. Billing is already protected — this is a
  sales change, not a security one. *(→ old-backlog; option-map `integration_yootheme_enabled`)*
- ⏸ **POST-LAUNCH — Admin Tools: detect AI-bot blocking.** Admin Tools' WAF can block
  GPTBot/ClaudeBot/PerplexityBot, killing AEO — detect such rules and warn. Plus redirect-manager /
  `.htaccess` overlap awareness. *(→ old-backlog)*
- ⏸ **POST-LAUNCH — Falang new options:** `inLanguage` on Schema.org, `og:locale:alternate`, per-language
  meta title/description templates, per-language sitemap priority. *(→ old-backlog)*
- ⏸ **POST-LAUNCH — Verify before reopening:** "render per-feature integration options in the SPA" may
  already have shipped under Phase 3 (`ce895f0`) — confirm in the SPA before re-listing as work. *(→ old-backlog)*
- ⏸ **POST-LAUNCH — Alias Assistant** — suggest/fix article aliases with automatic 301s when an alias
  changes. *(→ old-backlog)*
- ⏸ **POST-LAUNCH — Custom-code safety UX:** warn when injected custom code is unusually large; preview
  injected code before saving. *(→ old-backlog)*

**Refactors / technical debt (all post-1.0, architecture-gated where noted):**
- ⏸ **POST-LAUNCH — Make settings save manifest-driven.** Derive accepted keys/defaults/types/tier from
  the manifest registry instead of the `SettingsController` whitelist. Gate 2 + most of Gate 3 done
  (176/318 keys); remaining: Analytics + Sitemap SKU-ownership. Architecture gate + XHigh. *(→ old-backlog)*
- ⏸ **POST-LAUNCH — Harden settings save to merge-on-existing (#16)** and delete the dead
  `SettingsPersistenceService::saveSettings()` (the only physical subset-replace writer). Behaviour is
  locked by `SettingsWriterRmwContractTest`. Touches billing-guard code — full Pro save tests. *(→ old-backlog)*
- ⏸ **POST-LAUNCH — Converge LIKE prefix scans onto the sql_mode-independent form (#8).** Replace escaped
  `LIKE 'aiboost\_%_pro'` at `PluginRegistry.php:415`, `mod_aiboost_health.php:47`, `pkg_script.php`, and
  `ErrorsController.php:98` with a coarse escape-free WHERE + `str_starts_with`/`str_ends_with` in PHP.
  Install-path test required. *(→ old-backlog; memory sql-like-prefix-scan)*
- ⏸ **POST-LAUNCH — Safer option versioning (#41).** Add automatic option versioning so a renamed/removed
  key can't silently drop a stored value on the next Save (today guarded only by the manual
  `COMPATIBILITY_KEYS` list). *(→ old-backlog)*
- ⏸ **POST-LAUNCH — Automate the export secret-protection denylist (#42).** Make `SYSTEM_PRESERVED_KEYS`
  membership automatic so a new sensitive field can't be forgotten out of the export denylist. *(→ old-backlog)*
- ⏸ **POST-LAUNCH — 301 redirects missing from export/backup (#42).** Add redirects to export/import so
  they survive a site move. *(→ old-backlog)*
- ⏸ **POST-LAUNCH — UI colour tokens.** Extract the genuine colour bypasses (App.vue banner amber,
  HealthApp pass/fail, per-tab accents) into `--ab-warning`/`--ab-success`/`--ab-danger` (+ accent).
  Small, CSS-only, visually verifiable. *(→ old-backlog)*
- ⏸ **POST-LAUNCH — Cards: consolidate `.ab-card` vs `.ab-section`** into one family (do it around the
  WordPress port). And the optional, low-value `AbButton`/`AbCard` wrapper sweep (one-off layout, doesn't
  block the "one CSS fix = all pages" goal). *(→ old-backlog)*

**Testing & infrastructure:**
- ⏸ **POST-LAUNCH — Targeted screenshot `--only` + `--theme` on `scripts/ui-audit-screenshots.js`** so one
  screen can be shot in both themes without the full 46-shot set. Raise priority if the subagent fallback
  fires often in practice (decide from measurement). *(→ old-backlog)*
- ⏸ **POST-LAUNCH — Markdown feature (`markdown_pages_enabled`) completeness check.** Not exercised in the
  verification campaign. Verify the dedicated `.md` URL, `Accept: text/markdown`, the discovery `<link>`,
  and noindex + canonical on the `.md` version; complete whatever is missing (ties to the GSC/indexability
  work and the competitor analysis). *(→ old-backlog; arch §5)*
- ⏸ **POST-LAUNCH — Live BreadcrumbList check on a non-YOOtheme template** (Cassiopeia,
  `joomla6-free.testmyweb.info`). The code is confirmed correct but never verified live. Separate from the
  YOOtheme Breadcrumb investigation (order 0007). *(→ old-backlog)*

**Research & strategy:**
- ⏸ **POST-LAUNCH — Competitor analysis of the Joomla SEO/schema/AEO market.** Two concrete lists, NOT an
  encyclopedia: (A) market-standard features we lack; (B) where we are stronger (marketing ammunition).
  Competitors: Tassos "Google Structured Data", 4SEO, sh404SEF. Public sources only; learn the principle,
  reimplement in our own code (never copy GPL code). Feeds the honest-positioning work (B1) and the
  Markdown completeness check. *(→ old-backlog; arch §5; memory 4seo-competitor-multilingual)*

---

## OPEN DECISIONS (flagged, not decided)

- ❓ **OPEN — YOOtheme bridge: paid add-on (current) or Free?** Falang is Free; should YOOtheme be too?
  *(→ old-backlog; licensing E)*
- ❓ **OPEN — Integration licences relock on expiry while core Pro is perpetual — intended?** Core Pro
  (`pro_activated`) never relocks; integration Pro (`hasPro('int_*')`) reads the live status and expires.
  Both are reasonable; the asymmetry is undocumented. Confirm the intended behaviour and document it
  (OPERATING.md + website pricing copy). *(→ licensing P7/ODLUKA 7)*
- ❓ **OPEN — Google Search Console integration** — recommended (the #1 gap vs 4SEO); decide when reached.
  *(listed under MAIN BRANCHES; restated here as an open product call. → arch §10 B2)*

---

## Config-cleanup appendix — exact targets for the TRUNK config-surface item

Precise keys so nothing is lost (status from `_handoff/docs/option-map.json`, order 0010):

**⚫ Dead (6) — saves but no consumer:**
- `robots_auto_sync` → **REMOVE** (does nothing).
- `manual_faq_scope` → **FINISH** (Bojan-decided; see TRUNK) — not just delete.
- `hreflang_sitemap`, `sitemap_hreflang`, `hreflang_enabled`, `hreflang_primary_language` →
  **collapse to one** (the live consumer is `enable_hreflang`; head hreflang goes through the Falang bridge).

**🟡 Half-done — wire a UI or remove (decide each):**
- `enable_x_robots_header` (phantom — no UI), `robots_custom_rules` (only the legacy `RobotsTxtManager`
  reads it → fold into the robots.txt dedup), `llmstxt_include_menu` / `_about` / `_socials` / `_faq`
  (read by generators, in no manifest — add UI or document as always-on), `enable_manual_faqs` legacy
  `q/a` data (add the compat shim — see TRUNK `manual_faq_scope`).
- Custom-Code scope keys (8 + 2 legacy) and `schema_breadcrumb_pro` / "Enhanced BreadcrumbList" → see
  POST-LAUNCH (decision deferred).

**🔴 Broken (4) — all YOOtheme, all POST-LAUNCH:** `yootheme_faq_enabled`, `yootheme_gallery_enabled`
(+ the emitted "YOOtheme FAQ" / "YOOtheme ImageGallery" blocks) → fix the parsers before marketing
YOOtheme (see TWIGS W4). The remaining YOOtheme half-done keys (`yootheme_meta_override`,
`yootheme_schema_mapping`, `yootheme_accordion_selector`, `yootheme_sitemap_exclude_builder`,
`integration_yootheme_enabled`) are deferred with the same work.
