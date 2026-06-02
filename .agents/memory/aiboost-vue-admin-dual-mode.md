---
name: AI Boost Vue admin dual-mount + settings sub-tab nav
description: The Vue admin runs in TWO mount modes; the Settings component and any nav change must support both. How sub-tab deep-linking works.
---

# Vue admin runs in two mount modes

`main.js` mounts the admin two ways:
- **SPA mode** — when `#ab-app` exists (view=app), the whole UI is one Vue app with hash routing (`AppShell` + vue-router).
- **Legacy standalone mode** — when per-view mount points exist (`#ab-vue-settings`, `#ab-vue-dashboard`, …, view=settings/dashboard/…), each component is mounted on its own element with **no router**.

**Why it matters:** any component that relies on `this.$router` / `this.$route` must degrade gracefully when mounted standalone (router is undefined). The Settings component (`App.vue`) is mounted both ways.

**How to apply:** gate router-dependent UI behind `!this.$router`. After Plan B (vertical Sidebar), `App.vue` shows its own horizontal tab strip ONLY in legacy mode (`isStandalone = !this.$router`); in SPA mode the `Sidebar.vue` drives sub-tabs.

# Settings sub-tab navigation (Plan B sidebar, v0.65.0)

SEO feature tabs (schema/sitemap/social/analytics/aeo/code) and general/org/debug are NOT routes — they are sub-tabs inside the single `/settings` route in `App.vue` (`activeTab`, `tabs[]`).

- **SPA sidebar** links settings items to `{ path: '/settings', query: { tab: id } }`. `App.vue` reacts via a `watch` on `$route.query.tab` (+ a mount-time init from `this.$route.query.tab`). `selectTab()` mirrors the active tab back into the URL with `$router.replace` so the Sidebar highlight stays in sync.
- With hash routing the vue query lives **inside the hash** (`#/settings?tab=schema`), so `window.location.search` does NOT see it — read `this.$route.query`, not `location.search`, for SPA deep-links.

# Health "Fix It" deep-link flow

`HealthCheckService::settingsUrl($tab,$field)` builds a **legacy** URL `index.php?option=com_aiboost&view=settings&tab=<id>&field=<key>` (full page load). The legacy-mounted `App.vue` reads `window.location.search` on mount to select the tab and scroll/highlight `[data-ab-field]`. This path is separate from the SPA query path above — keep both working.

**Known pre-existing gap (not Plan B):** some `fix_actions` use `target_tab` (debug/licenses/integrations) with no `url`; `HealthApp.vue::fixActionHref()` only special-cases `target_tab==='errors'` and otherwise returns `action.url || '#'`, so those buttons resolve to `#`. Fixing requires generalizing `fixActionHref` to map any `target_tab` to a route — out of scope for nav reorg.
