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
