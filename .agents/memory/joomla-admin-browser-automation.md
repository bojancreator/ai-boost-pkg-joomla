---
name: Joomla 6 admin SPA browser automation (com_aiboost)
description: Pitfalls when Playwright-walking the AI Boost admin SPA on Joomla 6.1 staging/free sites
---

Driving the AI Boost admin Vue SPA with headless Chromium on Joomla 6.1 has two
non-obvious failure modes that cost real time:

1. **Guided-tour "What's New" modal hard-redirects the SPA.**
   On a fresh login Joomla 6.1 shows a guided-tour / "What's New" modal that, ~2s
   after `com_aiboost` mounts, redirects the page to the cpanel and kills the SPA.
   **How to apply:** right after login click `button:has-text('Hide Forever')`,
   THEN load `?option=com_aiboost` exactly once.

2. **Hash routing must be driven via JS, not the goto URL.**
   Appending the route hash to the goto URL (`...option=com_aiboost#/route`) makes
   Chromium treat it as a same-document hash change on the cpanel page, so the SPA
   route never loads. **How to apply:** load `?option=com_aiboost` with no hash,
   then set `window.location.hash = '#/route'` via `page.evaluate`. SPA root id is
   `#ab-app`.

3. **`networkidle` never settles on some Settings tabs** (e.g. settings-social) —
   the walk hangs forever. **How to apply:** do NOT wait for `networkidle`; wait on
   the route's root element / a short timeout instead.

**Why:** discovered while doing the live FREE visual-audit walk; without these the
walk either captures cpanel screenshots or hangs. Note that direct hash routing
also **bypasses Sidebar nav hiding** — force-loading `#/import` renders the route
even though the `proHidden` Import nav item is hidden on Free; don't infer nav
visibility from a force-loaded screenshot.
