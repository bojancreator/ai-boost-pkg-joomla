# Pre-sale roadmap (2026-06-16)

Curated, sequenced grouping of Bojan's 28-item pre-sale list into **discrete tasks**.
Each task gets its own detailed plan when we start it (kept shallow here on purpose, so
we don't get lost in depth). Phases = suggested order. "Merges into" = an existing plan
that already owns part of the work. ⬥ = needs a Bojan decision (recommendation given).
Cross-references `BACKLOG.md`, the website-refresh plan, and
`docs/lemon-squeezy-update-server.md`.

---

## Progress

- **2026-06-17 (v0.79.2, on staging):** T0 ✅ (import/export DoD rule) · T1 ✅ (redundant
  plugin On/Off removed from aeo/schema/sitemap/social) · item 11 ✅ (themed unsaved-changes
  dialog replaces browser `confirm()`) · item 12 ✅ (404 table header respects dark theme) ·
  item 13 ✅ (Health score is now weighted-proportional — staging shows 81, not 0; never 0
  unless every scoring check fails). All staging-verified. **Remaining in T2:** 12a (banners →
  footer — needs Bojan: slim global bar on every page vs move to bottom of Dashboard), 3 + 9
  (new Health checks — a proper task: backend check + frontend + fix-actions per the Health
  registry rule), 10 (crawler Allow-all/Block-all — automated QA inconclusive; the bulk buttons
  may not flag unsaved changes — needs a closer look), 17 (NEEDS Bojan's screenshot — his
  example is not multi-column). **12b moved to Phase 2** (relocates content into the Settings IA).

- **2026-06-17 (v0.79.3):** item 12a — Dashboard notifications reworked: the critical
  "no backup yet" notice stays always-visible at the top; the non-critical notifications
  (Settings active, Multilingual, stale-backup nag) now live in a **collapsible panel** that
  shrinks to a single "Open to see notifications ▾" bar (state remembered in localStorage).
  Staging-verified. **Still TODO for 12a:** show critical notifications on EVERY page (a global
  bar in AppShell fed by the bootstrap) — currently the critical notice is Dashboard-only.

- **2026-06-17 (v0.79.4):** items 3, 9, 10 done. **3** ✅ new Health check `warning_global_noindex`
  — warns when Joomla Global Config Robots = noindex/nofollow (suppressed on staging); **9** ✅ new
  Health check `warning_robots_not_writable` — warns when robots.txt/site root isn't writable
  (sitemap.xml & llms.txt are served dynamically, no disk write to fail); both unit-tested + present
  & executing on staging. **10** ✅ no bug — the AI Crawler Allow-all/Block-all `setAllBots()`
  correctly marks the form dirty (the earlier "dirty=false" was the QA clicking the wrong "Block all").
  **Phase 1 T2 remaining:** item 12a "step 2" (critical notifications on every page) + item 17
  (needs Bojan's screenshot). Everything else in Phase 1 is done.

- **2026-06-17 (v0.79.5):** item 17 ✅ — explanatory text no longer renders in columns. Root
  cause: `.ab-alert` was `display:flex`, so inline `<strong>/<code>/<em>` became separate columns;
  and a `.ab-help` description inside a flex `.ab-check/.ab-toggle` row was squeezed into a side
  column. Fix (ab-components.css, both admin/ + media/ copies): `.ab-alert` → `display:block` +
  `font-size: sm` (slightly smaller); field-description `.ab-help` wraps full-width below the
  toggle+label. Staging-verified on the "Submitting to search engines" callout + Article Schema
  description. **Phase 1 now done except item 12a "step 2"** (critical notifications on every page).

- **2026-06-17 (v0.79.6) — PHASE 1 COMPLETE:** item 12a "step 2" ✅ — a global `CriticalBar`
  (AppShell) now shows critical notifications on EVERY SPA page (suppressed on the Dashboard,
  which renders its own). First wired critical = "no settings backup yet" (gated on a new
  bootstrap `hasSettings` flag + localStorage); extensible for future critical events. Staging-
  verified (shows on Health, hidden on Dashboard). **All of Phase 1 (T0, T1, T2 items 1–20 in
  scope) is done and staging-verified.** Next: Phase 2 (admin navigation IA rework).

- **2026-06-17 (v0.80.0) — PHASE 2 COMPLETE (admin IA slice):** the remaining IA work shipped
  (most groups/aliases were already in place from earlier work). Done + staging-verified on
  offroadserbia (J6/PHP8.5): **item 15** ✅ real bug — Sidebar `isItemActive` fell back to `'general'`
  but the default settings tab is `'technical'`, so Technical SEO never lit up; fixed fallback →
  `'technical'`. **item 14** ✅ "Autopilot" → "Quick Setup" (visible labels only; route/id/`/setup`
  alias kept). **item 20** ✅ AEO page/menu label "AI Visibility" → "AEO" (section header "AI
  VISIBILITY" kept; Analyzer "AI Visibility" kept). **items 4/16** ✅ Page Title + Meta Description
  templates moved out of Technical SEO into a new **"Titles & Meta"** SEO page (`TitlesMetaTab.vue`);
  Technical SEO now reads cleanly (Domain · Conflict · Canonical · 404). **item 5** ✅ canonical
  stays in Technical SEO (already there). **item 12b** ✅ Danger-Zone uninstall explanation moved to
  the Import/Export page; dashboard keeps a calm "Settings Backup" card + short pointer. **items 1,
  18, 19** ✅ verified already satisfied (Schema sub-nav basic→advanced; settings already split;
  Custom Code already in ADVANCED). Build clean (codegen guard + leak check), 3/3 standalone tests,
  import/export round-trip PASS ("Titles / Meta" = 14 keys, no key dropped). **Next: Phase 3 (own conversation).**

- **2026-06-17 (v0.81.0) — PHASE 3 Slice A (item 8a) DONE:** Schema tab's single
  "❓ FAQ / Rich" section split into three separate sub-tabs — **❓ FAQ · 🔧 HowTo · 🎟 Events**
  (`SchemaTab.vue`: `visibleSchemaSections` + three `<template v-if="schemaSection===…">` blocks;
  pure front-end, no setting keys / emission / manifest touched). Staging-verified on offroadserbia
  (J6/PHP8.5) via Playwright: nav bar = `Core · Business · Hours · FAQ · HowTo · Events`, each sub-tab
  clicks + renders correct card (FAQ→FAQ/QAPage card, Events→Event Schema card), no AI-Boost console
  errors, Pro unlocked. composer test 3/3, PHPUnit 415/415, import/export round-trip PASS (site
  byte-identical). NOTE (Windows): run PHPUnit as `php vendor/bin/phpunit` (no `.bat` shim → bare
  `composer phpunit` fails). **Next: Slice B (item 2 — Author Entity one-click + Health).**

- **2026-06-17 (v0.82.0) — PHASE 3 Slice B (item 2) DONE:** one-click "Create author custom fields"
  button + Health check. New `SettingsController::createAuthorFields()` (AJAX, idempotent) creates the
  5 `aiboost_*` user custom fields (job_title/bio/website/linkedin/wikipedia, context `com_users.user`,
  group "AI Boost — Author") that `SchemaProBuilder::loadAuthorCustomFields()` reads; button lives in
  Schema → Core → Author Entity card (`SchemaTab.vue`, mirrors SocialTab's OG-repair pattern). New
  Health check `checkSchemaAuthorFieldsExist()` (`warning_schema_author_fields_missing`, category
  Schema) flags "X of 5 author custom fields exist" with a fix-action to the Author Entity tab.
  **Bug fixed along the way:** `#__fields`/`#__fields_groups` audit columns (created/created_by/
  modified/modified_by + created_time/created_user_id) are NOT NULL without a default on strict MySQL,
  so the field-group INSERT threw "Field 'created' doesn't have a default value" — now filled when the
  columns exist. Same latent bug existed in the OG `ensureOgFieldGroupInline` (masked because that
  group pre-existed on staging); refactored OG to delegate to the new generic `ensureFieldGroupInline`,
  fixing both. Staging-verified (Playwright): Health "0 of 5"→warning, button → "5 created", re-click →
  "0 created, 5 already existed" (idempotent), health.rerun → "All 5 exist" pass. composer test 3/3,
  PHPUnit 415/415, import/export round-trip PASS. **Next: Slice C (item 25 — integration options UI).**

- **2026-06-17 (v0.83.0) — PHASE 3 Slice C / T5 (item 25) DONE:** Falang (6) + YOOtheme (6) integration
  option fields now render + save on the Integrations page (were registered + saveable but had no UI).
  Each falang/yootheme card gets a collapsible **Options** `<details>`; Free fields plain, Pro fields
  wrapped in `<ProGate mode="card">`. New `components/IntegrationOptionField.vue` (toggle/select/text,
  v-model, `:data-ab-field`) renders each field. IntegrationsPage fetches the settings blob once on
  mount (`settings.getSettings`, defaults fill unset keys). **Save uses a new read-modify-write endpoint
  `IntegrationsController::saveOptions()`** (mirrors `saveToggle`; whitelisted `INTEGRATION_OPTION_KEYS`
  only) — NOT settings.save, which rebuilds the whole blob and would drop unposted keys. Staging-verified
  (Playwright): both cards render, falang_hreflang_head flip 1→0 persists, **sentinel schema_type
  unchanged + 239/239 keys before==after (no settings wiped)**, value restored. composer test 3/3,
  PHPUnit 415/415, import/export round-trip PASS. **Next: Slice D (item 8b — visual article picker).**

- **2026-06-17 (v0.84.0) — PHASE 3 Slice D (item 8b) DONE:** the Event Schema "article IDs" text box is
  now a visual picker with search. New `components/ArticlePicker.vue` (v-model on the same
  `schema_event_article_ids` comma-id string — no manifest/three-way change): selected articles as chips
  (index + title + id), debounced title-search dropdown, click to add / ✕ to remove. Backed by a new
  read-only endpoint `SettingsController::searchArticles()` (admin-gated, no token; `ids=` resolves
  IDs→titles, `q=` searches `#__content` by title, 30 cap, joins `#__categories`). Wired into the Events
  sub-tab in `SchemaTab.vue`; existing `event_{index}_desc` translation expanders still track picker order.
  Staging-verified (Playwright): endpoint returns 30 articles, typing filters, click adds a chip with the
  real title ("test proba" #220), expander "Event #0 (article #220)" appears. composer test 3/3, PHPUnit
  415/415, import/export round-trip PASS. **Next: Slice E (item 6 — holiday / special opening hours).**

- **2026-06-17 (v0.85.0) — PHASE 3 Slice E (item 6) DONE → PHASE 3 COMPLETE:** holiday / special opening
  hours. New manifest key `schema_special_hours` (type `json`, section `hours`, tier `free` — consistent
  with the free weekly hours; supersedes the dead schema_holiday_closed removed v0.73.42, this time WITH a
  consumer). Codegen run; the json field is hand-rendered so a `data-ab-field="schema_special_hours"`
  control in `SchemaTab.vue` satisfies the STRICT complex-coverage guard. UI: new "🎄 Holiday / Special
  Hours" repeater card in the Schema → Hours sub-tab (rows = label · from · to · Closed · opens/closes;
  parse/serialize like the howto repeater). **Emission (real consumer):** new
  `SchemaBuilder::buildSpecialOpeningHours()` emits `specialOpeningHoursSpecification` next to
  `openingHoursSpecification` in `decorateBusinessDetails()` (closed ⇒ 00:00/00:00; missing `to` ⇒ single
  day; invalid dates/times skipped). The orphaned `BusinessHoursBuilder` class was left as-is (emission
  stays inline in SchemaBuilder, same as the weekly hours). 2 new PHPUnit tests. Staging-verified
  (Playwright): repeater renders, row saves, **FRONT-END homepage JSON-LD emits
  specialOpeningHoursSpecification incl. the 2026-12-25 date** (AI Boost is the live emitter), row removed
  + restored. composer test 3/3, PHPUnit 417/417, import/export round-trip PASS.

  **★ PHASE 3 COMPLETE (v0.85.0).** All 5 slices done + staging-verified (offroadserbia J6/PHP8.5),
  **NOT yet committed**. T4 (items 2/6/8) + T5 (item 25) all shipped. Next per roadmap: Phase 4 (T6
  update-server, T7 perf/memory, T8 security) in its own conversation.

- **2026-06-17 (v0.85.1) — Slice D ArticlePicker UI fix (Bojan-reported):** the Events article-search
  dropdown (1) was clipped because `body[class*="com_aiboost"] .ab-card` has `overflow:hidden` and the
  list was `position:absolute`, and (2) showed a white background in dark mode because it used
  non-existent `--ab-surface`/`--ab-surface-2` tokens (fell back to white). Fix: render the results list
  **in normal flow** (card grows, never clips, any theme) + real theme-aware tokens
  (`--ab-bg-elev`/`--ab-bg-muted`/`--ab-text`/`--ab-border`). Also defined the previously-missing
  `ab-author-ok`/`ab-author-err` message colors (Slice B). **Verified in BOTH light AND dark themes via
  Playwright** (computed style: dark list bg = #23272f, text #e5e7eb, position static; visual screenshots
  of picker-open + Hours repeater + Author card + Integrations options in both themes; no console errors).
  composer test 3/3, import/export PASS. **Lesson:** admin CSS must use the real `--ab-*` tokens from
  `ab-tokens.css` (NOT invented names) and account for `.ab-card { overflow:hidden }`; always click-test
  dropdowns/overlays in BOTH themes, not just functional flow.

## Phase 1 — Quick wins (no IA changes, low risk, ship fast)

- **T0 · Process rule: import/export round-trip gate** (item 0) — S. Add to OPERATING.md
  Definition of Done: any settings/option change MUST pass the existing
  `verify-import-export.py` round-trip. Tooling already exists. → OPERATING.md DoD.
- **T1 · Remove redundant plugin On/Off toggle** (item 24) — S. Drop the duplicate JSTATUS
  `enabled` radio from `<config>` of aeo/schema/sitemap/social; for int_falang/int_yootheme
  keep the block but remove only the redundant radio. Verify nothing reads its own `enabled`
  param instead of Joomla's publish state.
- **T2 · Health + Dashboard UX polish** (items 3, 9, 10, 11, 12, 12a, 12b, 13, 17) — M. A
  batch of small fixes: NoIndex/NoFollow Health check (⬥ scope), robots/sitemap/llms
  write-failure Health notice, QA the AI-crawler Allow-all/Block-all + per-bot toggles,
  replace browser `confirm()` with a nice toast on unsaved-leave, dark-theme 404 table
  header, move header banners to footer, collapse Danger-Zone wording, stacked+smaller
  callout text (the `display:flex` ones), and a less brutal Health score (no 0 when some
  things pass).

## Phase 2 — Admin navigation IA rework (ONE slice)

- **T3 · Admin IA rework** (items 1, 4, 5, 14, 15, 16, 18, 19, 20) — L. → **BACKLOG admin-IA**.
  Do as one slice so deep-links/aliases/Health targets/tests update together. Contains:
  real bug fix 15 (Technical SEO has no active state — Sidebar default-tab mismatch),
  renames (⬥ 14 Autopilot, ⬥ 16 Technical SEO, ⬥ 20 AI Visibility), moves (⬥ 4 Page Title
  Templates, ⬥ 5 Canonical placement), verify-already-done (18 Settings split, 19 Custom
  Code in Advanced), and 1 (Schema sub-tab bar already exists → just reorder per BACKLOG).

## Phase 3 — Feature enrichment (after IA is stable)

- **T4 · Schema.org enrichment** (items 2, 6, 8) — L. ⬥ 2 Author Entity fields (auto-create
  vs manual), ⬥ 6 Hours holidays (specialOpeningHoursSpecification — needs a real
  BusinessHoursBuilder consumer), 8 split FAQ/HowTo/Event into separate sub-tabs + a visual
  article picker for Event (instead of numeric Article IDs).
- **T5 · Render Falang/YOOtheme options in the SPA** (item 25) — M. → **BACKLOG integration**.
  Fields are registered + gated correctly but no Vue renders them (run on defaults today).
  ⬥ placement: collapsible per-integration cards on IntegrationsPage.vue (recommended).

## Phase 4 — Launch hardening (parallel; backend/docs)

- **T6 · Update-server hygiene** (item 23) — S. → **licensed-update-server doc**. Keep Pro/addon
  manifests WITHOUT update servers until the website backend (C2) is live. Concrete now:
  verify the Free feed `aiboostnow.com/updates/pkg_aiboost.xml` is actually deployed/valid
  (Bojan sees "Could not parse" → it isn't), and investigate the live "Could not open update
  site #317 … updates.aiboostnow.com/api/updates/pkg_aiboost_pro.xml?key=…" error (a stale
  update site registered on the test site by an OLD Pro build; remove/disable it).
- **T7 · Performance + memory baseline** (items 26, 27) — M. Instrument onBeforeCompileHead/
  onAfterRender behind JDEBUG (timing + `memory_get_peak_usage`), average a few staging
  runs, publish a documented minimum (recommend 128 MB PHP floor) for a system-requirements page.
- **T8 · Security audit process + pre-launch re-review** (item 28) — L. The code already
  passed a 50-agent Faza A/B audit (XSS via JSON-LD, settings-wipe, store-pinning fixed).
  Missing: a documented repeatable checklist + cadence. Run a focused pre-launch re-review,
  document the process, schedule semi-annual re-audits.

## Mapped to OTHER plans (not this plugin sprint)

- **Items 7, 21, 22** → website-refresh plan (aiboostnow.com Download/Docs/Help) +
  `docs/lemon-squeezy-update-server.md` (C2 backend). Per-product LS licensing (v0.79.0) done.
  7 (per-field help-icons deep-linking to docs) is blocked until the docs website exists.

---

## Decisions (CONFIRMED 2026-06-16)

1. **(item 2) Author Entity fields** — ✅ **one-click button** in Schema auto-creates the 5 Joomla
   user custom fields + a Health check that flags missing ones.
2. **(item 6) Hours holidays** — ✅ **date-range exception periods + individual holiday dates →
   `specialOpeningHoursSpecification`** (needs a real BusinessHoursBuilder consumer, not just UI).
3. **(item 14) Autopilot rename** — ✅ **"Quick Setup"**.
4. **(item 25) Integration options** — ✅ **collapsible per-integration cards on the Integrations
   page, IN v0.5** (now, not deferred).
5. **(item 16) Technical SEO rename** — **keep "Technical SEO"** (Bojan did not object; the real
   confusion is the leftover Title Templates card, which item 4 moves out).
6. **(item 20) AI Visibility collision** — **section stays "AI VISIBILITY", page/menu item → "AEO"**
   (matches BACKLOG; Bojan did not object).
7. **(item 3) NoIndex/NoFollow Health check** — **global config check now; defer per-article/menu
   scan** to post-launch (Bojan did not object).
8. **(items 4/5) Page Title Templates & Canonical** — **separate tab each** inside the SEO/Technical
   area (finalise exact placement during the IA slice).
9. (item 24) Remove redundant On/Off toggle — proceed.
10. (item 23) Update servers — keep Pro/addon out until C2; verify Free feed; remove stale update
    site #317 on the test site.
11. (items 26/27, 28) Perf/memory + security — proceed as launch hardening.

## Sequencing
Phase 1 → Phase 2 (IA slice, load-bearing) → Phase 3 (builds on new tabs) → Phase 4 (parallel).
The renames (14, 16, 20) and integration placement (25) gate Phases 2–3, so decide those first.
