# AI Boost for Joomla — Backlog

The **only** forward list of remaining work, in plain language, organised by **type of work**.
When you pick up an item, follow the **Definition of Done** in `OPERATING.md`; when it's shipped
and verified on a Free **and** a Pro test site, delete its line from this file. That deletion *is*
marking it done — no parallel task panel.

**Where we are now (version, branch, what's deployed, what's left to launch):** `STATUS.md`.
Product strategy and release sequence: `docs/v0.5-product-direction.md`.

> Pruned 2026-06-18: the entire Admin IA rework (sidebar grouping, Technical SEO page, Crawlers &
> Robots page, menu renames, Autopilot MVP, Meta Pixel move, AEO rename) shipped (phases 1–4), and
> the 4SEO/Sh404SEF/JoomSEF conflict-detector element-name bug is fixed — all removed from below.

---

## New options / features

- **[post-1.0] `manual_faq_scope` — decide: finish or remove** *(order 0006, Bojan undecided)*. The
  setting (manual FAQ "when to apply": fallback_all / always_all / fallback_home / always_home /
  disabled) is defined in the manifest + Vue UI + save whitelist but is read by NO consumer — the
  scope logic was never ported from the legacy standalone plugin into `SchemaProBuilder`, so manual
  FAQ ignores it. **Decision needed:** either (a) finish it — port the scope filter into
  `SchemaProBuilder::decorateAll()` using the `ctx->isHomepage()` it already holds (worth it only if
  customers want their own FAQ on non-article pages), or (b) remove it cleanly (manifest field + UI
  partial + save-whitelist entry). Do NOT delete until Bojan decides. (Separate, lower-impact: the
  manual `faq_items` builder reads `question`/`answer` keys; legacy/imported `{q,a}` data is silently
  skipped — add a q→question / a→answer compatibility shim if old backups must keep working.)

- **Integration improvements (from Plan 1 review, 2026-06-12 — deferred by Bojan):**
  - **Admin Tools: detect AI-bot blocking** *(high value, AEO-on-brand)* — Admin Tools' WAF /
    "Block user agents" can block GPTBot/ClaudeBot/PerplexityBot, killing AEO. Detect such rules and
    warn. Also: redirect-manager overlap, `.htaccess` overlap.
  - **YOOtheme: validate / rework menu-param schema mapping** — the Event/Product/Organization mapping
    reads ~30 *guessed* param keys (`yoo_event_name`…) that likely rarely match real builder params.
    Verify on a real YOOtheme site; if it never fires, parse the YOOtheme builder JSON or drop it
    (FAQ/gallery are body-based and reliable). Also: configurable gallery selector, DOMDocument instead
    of regex, HowTo/Breadcrumb schema.
  - **Falang new options** — `inLanguage` on Schema.org, `og:locale:alternate`, per-language meta
    title/description templates, per-language sitemap priority.
  - **Product decision** — should the YOOtheme bridge be Pro (current) or Free (Falang is Free)?
  - *Verify before reopening:* "render per-feature integration options in the SPA" may already have
    shipped under Phase 3 ("integration options UI", `ce895f0`) — confirm in the SPA before re-listing.
- **Alias Assistant** — suggest and fix article aliases, with automatic 301 redirects when an alias
  changes. *(post-v0.5)*
- **Warn the admin when custom code is unusually large** — flag injected code that could slow the site
  down. *(post-v0.5)*
- **Preview injected custom code before saving it** — let admins see the output before it goes live. *(post-v0.5)*
- **Extra social-profile fields (replacement for the removed Pinterest field)** — add 2–3 generic social
  fields (name + URL) so admins can cover networks that aren't on the fixed list (Mastodon, Bluesky,
  Threads). These feed Schema.org `sameAs`, with a CLEAR label that they must be the company's *official*
  profiles — not arbitrary links (an arbitrary URL in `sameAs` produces incorrect schema). Do it properly
  this time (Pinterest was half-done). *(post-1.0)*

## Admin UX / navigation

- **Polish Schema.org card ordering** — reorder cards so foundational schema comes before optional
  rich-result types (`Schema.org Core`, `Business / Organization Type`, conditional business details,
  `Opening Hours`, `WebSite`, `Article`, `FAQ/QAPage`, `Author Entity`, `HowTo`, `Event`). Feature set
  unchanged; this is ordering only. **Deferred post-launch** (high risk / low urgency on the most
  important page). *This is the only remaining item from the Admin IA rework — the rest shipped.*

## Refactors & technical work

- **Make settings save manifest-driven** — derive accepted keys/defaults/types/tier rules from the
  manifest registry instead of the `SettingsController.php` whitelist. Gate 2 and most of Gate 3 done
  (176/318 keys manifest-backed); remaining: Analytics and Sitemap SKU-ownership decisions. Architecture
  gate required (`docs/architecture-refactor-plan.md`); XHigh before the first slice.
- **Build a small WordPress vertical slice** — Organization/WebSite schema end-to-end on WordPress first,
  to expose missing CMS abstractions before the next wave of options. *(post-v0.5 — hold until Joomla
  v0.5 ships and shared service boundaries are stable.)* Architecture gate + XHigh required.
- **Thin Joomla plugin classes into platform entrypoints** — keep plugin classes as event/bootstrap
  layers; move business logic into shared services, starting with `AiBoostCore.php`. Architecture gate +
  XHigh required. (Biggest long-term risk is future Joomla/WordPress duplication, not the current product.)
- **Cross-platform boundary work** (from `docs/ARCHITECTURE-BOUNDARIES.md`, 2026-06-24 snapshot) —
  groundwork so a WordPress build + standalone+integrative plugins don't duplicate the core:
  - *Route leaking CMS calls through the adapters* — replace the direct `Route::_` (sitemap/hreflang
    generators), `JPATH_ROOT` (`OgTagBuilder`/`OgTagProDecorator`/`RobotsTxtManager`), `Factory::getContainer`
    (`SchemaProBuilder`) and `Joomla\CMS\Log\Log` (`IndexNowService`) with `Cms\AdapterRegistry`.
  - *Add a content-repository seam* — abstract the inline Joomla `#__` data fetch (~40 queries across 8
    generators; SchemaPro 7, Hreflang 12, Sitemap 6, llms_pro 6, …) behind one interface, so the data layer
    is the single thing reimplemented per CMS.
  - *WordPress data adapter (~35% of generation = data fetch)* — `wp_posts`/`wp_terms`/`wp_postmeta` source
    + finish `Cms/Wp/*` wiring + a WP entry/event layer; the ~65% shape logic transfers as-is. (Sharpens the
    older "WordPress vertical slice" item above.)
  - *First standalone+integrative plugin* — a NEW sub-pattern (NOT an `AbstractIntegrationPlugin` copy, which
    hard-depends on `com_aiboost`): runs on its own, integrates with AI Boost via the SDK `onAiBoost*` events
    behind `class_exists()`.
  *(all post-1.0, architecture-gated)*
- **Pro gate drift in admin/health DISPLAY (#2 follow-up)** — three live places derive isPro from the
  raw `license_tier` instead of `PluginRegistry::isProActive()`: `mod_aiboost_health.php:78`,
  `HealthCheckService.php:2690`, `Dashboard/HtmlView.php:269` (`checkIsProEnabled`). Plus two dead
  helpers: `ProGate` trait `isProEnabled():46`, `AbstractService::isProTier():56`. Effect: a
  perpetual-Pro customer reads "Free" in the admin/health PANEL after the licence expires — display
  only, NOT visitor-facing emission, so no Pro leak at the customer. Fix: switch the three live ones to
  `isProActive()`; delete the two dead helpers. Low priority — cosmetic admin bug. *(post-1.0)*
- **Converge LIKE prefix scans onto the sql_mode-independent form (#8 follow-up)** — three sites match
  `aiboost_*_pro` via escaped-underscore `LIKE … ESCAPE '\'`: `PluginRegistry.php:415`,
  `mod_aiboost_health.php:47`, and `pkg_script.php` (the last was a live NBE bug, fixed in this commit by
  adding the ESCAPE clause). The explicit ESCAPE clause is correct under all sql_modes, but the lesson's
  canonical, fully sql_mode-independent form is a coarse escape-free WHERE (`type='plugin' AND
  folder='system'`) + `str_starts_with($element,'aiboost_') && str_ends_with($element,'_pro')` in PHP.
  Converge all three onto that. Also fold in the user-search LIKE in `ErrorsController.php:98` (manual
  `\_`/`\%` escaping with no ESCAPE clause — NBE-fragile too; low impact, admin error-log search). Gated
  change with a real install-path test (`pkg_script` is install lifecycle). *(post-1.0)*
- **Harden settings save to merge-on-existing (#16)** — `SettingsController::save()` rebuilds the
  `#__aiboost_settings` blob from the posted form, so it is safe ONLY because the Vue SPA posts the full
  snapshot. Make it merge the posted keys onto the loaded existing blob so even a partial save can never
  wipe siblings, then delete the dead `SettingsPersistenceService::saveSettings()` (a subset-replace
  writer with no production caller — the only physical instance of the anti-pattern) so the dormant mine
  disappears. Gated refactor with full licence/Pro save tests (it touches the code that guards billing).
  Behaviour is locked-as-is by `SettingsWriterRmwContractTest`. *(post-1.0 — not before launch.)*
- **UI colour tokens — extract the genuine colour bypasses** so a status-colour change is one place.
  Spots: `App.vue` staging/upgrade banner (~25 amber hex), `HealthApp` pass/fail (`#198754` / `#dc3545`
  + dark variants), per-tab accent palette (`App.vue`/`DashboardApp`). Add `--ab-warning` / `--ab-success`
  / `--ab-danger` (+ accent) and point those spots at them. Small, CSS-only, visually verifiable.
  *(post-1.0 — not before launch.)*
- **Cards: consolidate the two overlapping families** (`.ab-card` vs `.ab-section`) into one card family.
  Do this around the WordPress port, where shared components get unified anyway. *(post-1.0)*
- **AbButton + AbCard wrapper components** to remove repeated markup; sweep the ~180 one-off inline layout
  styles into utility/scoped rules. Nice-to-have only — these are mostly one-off LAYOUT, not shared theme,
  so they do NOT block the "one CSS fix = all pages" goal. *(optional, low value — only if there's appetite
  after 1.0.)*
- **Integration master toggles shown locked (upsell)** — render `integration_falang_enabled` /
  `integration_yootheme_enabled` as a ProGate-locked control with an "available as a paid add-on" message
  whenever `hasPro('int_falang')` / `hasPro('int_yootheme')` is inactive — the same mechanism as the
  existing Pro Options fields. Why: upsell. Today a Free user sees the switch ON and assumes the integration
  works (or is broken), instead of seeing what they get by paying. Billing is already protected (two walls:
  `@pro:start` build-stripping + the `hasPro` licence gate), so this is NOT a security fix — it is a sales
  one. *(post-1.0 — not before launch.)*
- **Safer option versioning (#41 DB audit follow-up)** — latent gap: if a future version RENAMES or
  REMOVES a setting key without also adding it to the manual compatibility list (`COMPATIBILITY_KEYS`),
  the stored value is silently dropped on the next Save. It doesn't touch current customers; it's a trap
  for the future. Add automatic/safer option versioning so a value can't be lost when a key is renamed.
  *(post-1.0)*
- **Automate the export secret-protection denylist (#42 export/import audit follow-up)** — the protection
  that keeps licence keys/secrets out of a backup is currently manual (the `SYSTEM_PRESERVED_KEYS`
  denylist). Automate it so a new sensitive field doesn't have to be remembered into the denylist by hand
  (risk: forget one and a secret leaks into an export). *(post-1.0)*

## Bugs & fixes

- **301 redirects are missing from export/backup (#42 export/import audit follow-up)** — export/backup
  currently does NOT include 301 redirects, so when settings are moved to another site the redirects are
  lost. Add redirects to export/import. *(post-1.0)*

## Testing & infrastructure

- **Targeted screenshot: `--only`+`--theme` on `scripts/ui-audit-screenshots.js`** so one screen can be shot in both themes without the full 46-shot set. Until then the subagent fallback runs the full set and judges only the two relevant PNGs. **Raise priority if the fallback fires often in practice** (decide from real measurement, not assumption).
- **Markdown feature (`markdown_pages_enabled`) — completeness check** — not exercised in the verification
  campaign (it was off on staging). Verify alongside the Tassos comparison (see *Competitor analysis*
  below): does the dedicated `.md` URL work, the `Accept: text/markdown` header, the discovery `<link>` in
  head, and noindex + canonical on the `.md` version — and complete whatever is missing. *(post-1.0)*
- **Live BreadcrumbList check on a non-YOOtheme template** — the BreadcrumbList code is confirmed correct
  but was never verified live (the YOOtheme template doesn't populate the Joomla pathway). Confirm live on
  a Cassiopeia template (`joomla6-free.testmyweb.info` is already on Cassiopeia). NOTE: this is separate
  from the YOOtheme Breadcrumb investigation (order 0007) — here we confirm the code works on a standard
  template; there we investigate why it doesn't fire on YOOtheme. *(post-1.0)*

## Health scan polish

(none open)

## Research & strategy

- **Competitor analysis of the Joomla SEO/schema/AEO market** — produce two concrete lists, NOT an
  encyclopedia of competitors: (A) where a feature has become a market standard and we lack it (backlog
  candidates), and (B) where we are stronger (marketing ammunition). Competitors: Tassos "Google Structured
  Data", 4SEO, sh404SEF, and other active Joomla schema/SEO/AEO extensions. For each, from PUBLIC sources
  only (site, docs, demo, reviews): which features they have that we don't; which we have that they don't;
  how honestly they position AI/AEO features (genuine vs inflated marketing). **Legal boundary:** learn the
  PRINCIPLE from public docs/behaviour — yes; read or copy their CODE — no (commercial + copyright; a GPL
  copy would force us GPL); reimplement the idea in our OWN code — yes. First concrete inputs already
  spotted: (a) Tassos HTML→Markdown does a dedicated `.md` URL, `Accept: text/markdown` handling, a
  `<link rel="alternate" type="text/markdown">` discovery tag in head, and X-Robots noindex + canonical on
  the `.md` version — compare against our `markdown_pages_enabled` and list the gaps (ties to the Markdown
  completeness item under *Testing & infrastructure*); (b) a 10-year competitor openly states that llms.txt
  as "you'll be visible to the AI" does NOT work — confirms our AEO marketing should keep an honest tone
  (cleaner text for bots when they arrive, not a false ranking promise). *(post-1.0)*

## Documentation / skill

(none open)

---

## Not in this backlog (on purpose)
