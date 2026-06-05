# AI Boost for Joomla — Backlog

The **only** forward plan: real remaining work, in plain language, organised by
**type of work** — not just new features. When you pick up an item, follow the
**Task completion procedure (Definition of Done)** in `OPERATING.md`; when it's
shipped and verified on staging, delete its line from this file. That deletion
*is* marking it done — no parallel task panel.

Product strategy and release sequence for v0.5: `docs/v0.5-product-direction.md`.

---

## New options / features

- **Autopilot MVP** — guided one-time setup flow: Site Identity → Schema.org core →
  Sitemap → Social Meta, with a visual completion state showing which key areas are
  configured. New Vue route, first item under `SETUP`. No AI inference in this slice —
  Autopilot is a guided checklist. Architecture Gate 0 required before implementation.
  See `docs/v0.5-product-direction.md §3`.

- **Alias Assistant** — suggest and fix article aliases, with automatic 301
  redirects when an alias changes. *(post-v0.5)*
- **YOOtheme Pro integration** — bridge AI Boost into YOOtheme-built sites so they
  also get the schema / OG / AEO output. *(post-v0.5)*
- **Warn the admin when custom code is unusually large** — flag injected code that
  could slow the site down. *(post-v0.5)*
- **Preview injected custom code before saving it** — let admins see what will be
  output before it goes live. *(post-v0.5)*

## Admin UX / navigation

(admin menu structure, settings information architecture and option placement)

- **Rework the admin sidebar into clearer product areas** — replace the current
  `OVERVIEW / SEO FEATURES / TOOLS / SETTINGS` grouping with this v0.5 target order:

  ```text
  OVERVIEW
  - Dashboard
  - Health          (absorbs Errors — one status surface, not two)

  SETUP
  - Autopilot       (new — guided one-time setup, see Autopilot item above)
  - Site Identity
  - License & Updates
  - Integrations

  SEO
  - Technical SEO   (canonical URL + 404 monitoring, moved out of Sitemap)
  - Schema.org
  - Sitemap         (sitemap-only after Technical SEO moves out)
  - Social Meta
  - Analytics & Tracking

  AI VISIBILITY
  - AEO
  - GEO             (reserved placeholder — not implemented in v0.5)
  - Crawlers & Robots

  TOOLS
  - Redirects
  - Analyzers
  - URL Checker

  ADVANCED
  - Custom Code
  - Debug
  - Import
  - Help
  ```

  Implement in four slices as defined in `docs/architecture-refactor-plan.md`
  (Admin IA Gate 1). Keep `Dashboard` and `Health` at the top because they are
  status and diagnostics entrypoints. `General` settings (domain, conflict
  resolution) are accessible from within relevant pages but no longer a primary
  SETUP destination. Update route labels, sidebar active-state logic and
  docs/screenshots together per slice.

- **Rename admin menu items to match user mental models** — use these final names:
  `Organization` -> `Site Identity`; `Licenses` -> `License & Updates`;
  `Social & Meta` -> `Social Meta`; `Analytics` -> `Analytics & Tracking`;
  `AEO` -> `AEO` (short form, section header renamed to `AI VISIBILITY`);
  `Custom Code` stays `Custom Code` but moves to `ADVANCED`; `Debug` stays
  `Debug` and moves to `ADVANCED`. The goal is that a normal Joomla admin can
  guess where to go without knowing internal feature names. Architecture gate: not
  required. XHigh: recommended for final wording review before implementation.

- **Promote Organization settings into Site Identity** — move the current
  `Organization` tab out of the low-priority Settings area and make it the second
  item in `SETUP`, directly after `General`. Keep its internal order as:
  `Identity`, `Contact Information`, `Social Media Links`, `Address`,
  `Geographic Coordinates`, `Guest / Customer Rating`. This data feeds
  Schema.org, social metadata and AI search output, so it should feel like an
  onboarding foundation rather than an advanced setting.

- **Simplify General so it contains only true global behaviour** — keep
  `Domain & Environment` and `Conflict Resolution Mode` in `General`. Move the
  current `robots.txt` enable/auto-sync/live-preview card out of `General` into
  `Crawlers & Robots`. This prevents global settings from becoming a mixed
  technical SEO/crawler page.

- **Create a dedicated Technical SEO page** — move technical site-wide SEO
  controls that are currently hidden in `Sitemap` into a new `Technical SEO`
  item under `SEO`. The first implementation slice should include:
  `Canonical URL` and `404 Monitoring`. `Canonical URL` should contain
  `Enable canonical URL management` and `Canonical URL Map`. `404 Monitoring`
  should contain `Log 404 Errors` and a clear link/path to `Redirects -> Recent
  404 errors`. Update all deep links and health targets that currently point to
  `tab=sitemap&field=enable_canonical` so they point to the new Technical SEO
  location.

- **Refocus Sitemap on sitemap work only** — remove `Canonical URL` and
  `404 Monitoring` from `Sitemap`. Reorder the page to: `XML Sitemap` core
  enable/settings first, `Live sitemap.xml preview` second, `Content to Include`,
  `Exclusions`, `Advanced` sitemap options, `Google News Sitemap`, `Submitting
  to Google / Bing guidance`, then `Search Engine Ping` as a collapsed or clearly
  demoted legacy section. Keep the 2026 Google/Bing guidance because it is
  useful, but place it after the actual sitemap configuration and preview so it
  does not interrupt setup.

- **Create Crawlers & Robots as the single crawler-policy page** — move all
  crawler and robots controls into the new `Crawlers & Robots` item under
  `AI VISIBILITY`. This page should contain, in order: `robots.txt Management`
  (enable, auto-sync, live preview), `SEO scraper blocks` (Ahrefs, Semrush,
  Majestic, Moz, Screaming Frog, Sitebulb, etc.), `AI Crawler Rules` with the
  default allow/block policy, per-bot rules for AI crawlers, and `Custom
  robots.txt Rules`. Keep the current saved setting keys unless a manifest
  migration is deliberately planned; this is primarily an information
  architecture move.

- **Refocus AI Search / AEO on AI-visible content endpoints and signals** — keep
  the renamed `AEO` page (section `AI VISIBILITY`) for: `llms.txt - AI Site Index`,
  `llms-full.txt - Full Site Index`, `Markdown Pages - AI Agent Endpoint`,
  `AI Signals`, and `IndexNow - Instant Search Indexing`. Move non-AI crawler
  controls out to `Crawlers & Robots`. The page name `AEO` is sufficient now that
  the section header is `AI VISIBILITY`.

- **Move Meta Pixel into Analytics & Tracking** — move `Meta Pixel`, `Meta Pixel
  Standard Events` and `Meta Pixel Custom Events` out of `Social Meta` and into
  `Analytics & Tracking`, after `Google Analytics 4` and `Google Tag Manager`.
  Rename `Site Verification` to `Search Console & Site Verification` and keep it
  at the top of `Analytics & Tracking`. The final order should be: `Search
  Console & Site Verification`, `Google Analytics 4`, `Google Tag Manager`,
  `Meta Pixel`, `Meta Pixel Standard Events`, `Meta Pixel Custom Events`.

- **Keep Social Meta focused on share-preview metadata** — after moving Meta
  Pixel out, keep `Social Meta` focused on `OpenGraph`, `Twitter / X Cards`,
  `Locale & Facebook` and any per-article/social preview override guidance.
  Final order: `OpenGraph`, `Twitter / X Cards`, `Locale & Facebook`. This makes
  the tab about how pages look when shared, not tracking or ads.

- **Polish Schema.org ordering without changing its scope** — keep Schema.org as
  a top SEO item, but reorder the cards to move foundational schema before
  optional rich-result types: `Schema.org Core`, `Business / Organization Type`,
  conditional business details (`Hotel`, `Restaurant`, service-specific,
  `Real Estate`, etc.), `Opening Hours`, `WebSite Schema`, `Article Schema`,
  `FAQ / QAPage Schema`, `Author Entity`, `HowTo Schema`, `Event Schema`.
  This keeps the current feature set but makes the page read from basic identity
  to optional advanced schema.

- **Move Custom Code and Debug into Advanced** — remove `Custom Code` from the
  SEO feature group and place it under `ADVANCED` because it is developer/admin
  power functionality, not a normal SEO feature. Keep its internal order as
  `Head Code`, `Body Code`, `Footer Code`. Keep `Debug` next to it under
  `ADVANCED`, and keep `Help` as the final item in the sidebar.

- **Update navigation-dependent links and tests with the IA change** — when the
  menu move is implemented, update `Sidebar.vue`, the settings tabs list in
  `App.vue`, route labels/meta in `router.js`, `HealthTab` target mappings,
  `UrlCheckerTab`/`UrlCheckerPage` canonical fix links, docs pages, screenshots,
  and any tests that assert tab names, settings routes or Pro-gated menu items.
  This should be done as one focused UX/navigation slice so old deep links do not
  silently point users to the wrong page.

  Required target updates:
  `enable_canonical` -> `Technical SEO`; `redirect_404_log_enabled` ->
  `Technical SEO`; `enable_robots` and robots preview/fix actions ->
  `Crawlers & Robots`; `ga4_measurement_id`, GTM fields and Meta Pixel fields ->
  `Analytics & Tracking`; Organization/Site Identity configure links ->
  `Site Identity`.

  Backwards compatibility rule: old query tabs such as `tab=org`, `tab=aeo`,
  `tab=social`, `tab=analytics`, `tab=sitemap`, and `tab=code` may remain as
  internal aliases, but the visible sidebar and tab labels should use the new
  names. Old links should still land on the right new page or scroll to the
  moved field.

## Refactors & technical work

(structural / gating / cleanup work that isn't a user-facing feature)

- **Make settings save manifest-driven** — move the settings save whitelist out
  of `SettingsController.php` and derive accepted keys, defaults, types and tier
  rules from the manifest registry. This is the most important step for making
  options easy to add or remove safely. Architecture gate: required; start with
  `docs/architecture-refactor-plan.md`. XHigh: required before the first
  implementation slice. *(Gate 2 and most of Gate 3 are already complete — see
  architecture-refactor-plan.md for current status: 176/318 keys manifest-backed.
  Remaining: Analytics and Sitemap SKU ownership decisions.)*
- **Build a small WordPress vertical slice now** — implement only
  Organization/WebSite schema end-to-end on WordPress first. This will expose
  missing CMS abstractions before the project grows by another large wave of
  options. Architecture gate: required. XHigh: required before architecture and
  implementation. *(post-v0.5 — hold until Joomla v0.5 ships and shared service
  boundaries are stable.)*
- **Thin Joomla plugin classes into platform entrypoints** — keep Joomla plugin
  classes as event/bootstrap layers, but move business logic into shared
  services. Start with `AiBoostCore.php`, because it currently mixes platform
  handling with canonical, title, redirects, update and logging behaviour.
  Architecture gate: required. XHigh: required for service-boundary design and
  final compatibility review.

  Architecture note: the current foundation is serious and the Free/Pro package
  architecture is already strong. The biggest long-term risk is not the current
  Joomla product, but future duplication when maintaining Joomla and WordPress
  unless the platform boundary is tightened before the next wave of features.

## Bugs & fixes

(confirmed defects to fix)

## Testing & infrastructure

(test/CI infrastructure tasks)

## Health scan polish

(improvements to the Health registry / scanner)

## Documentation / skill

(docs and `joomla-development` skill lessons)

- **v0.72.x staging verification complete** — see `deliverables/docs/v0.72.x-staging-verification.md`.
  All 0.72.x changes (Task #567 LicenseReconcile, Task #566 robots.txt uninstall fix) verified live
  on staging. No regressions. 16 Health issues found are all pre-existing config gaps, not code
  defects. Known follow-up: build script doesn't inject package version into plugin XML manifests
  → persistent `warning_install_integrity` (tracked as task #572).

---

## Not in this backlog (on purpose)
