---
name: Settings UI/backend default mismatch
description: Why a toggle can read OFF in the Vue admin while the PHP side still runs the feature
---

# Toggle reads OFF but feature still runs

A boolean setting whose key is **absent** from the saved `#__aiboost_settings` JSON
produces opposite defaults on the two sides:

- **Backend PHP** reads `(int)($settings['key'] ?? 1)` → absent = **enabled**.
- **Vue admin** binds `<input type=checkbox v-model="s.key" true-value="1" false-value="0">`
  → when `s.key` is `undefined`, vModelCheckbox compares against `"1"`, fails, and renders
  the switch **OFF**. (A sibling computed using `?? '1'` will still say ON, so the card and
  the knob disagree.)

Result: the admin sees a red OFF switch, flips/saves nothing meaningful (value stays
`undefined` → not persisted), and the backend keeps emitting output. Classic
"master switch OFF but component still works" report.

**Why:** `App.vue`'s `DEFAULTS` map (merged via `Object.assign({}, DEFAULTS, window.aiBoostSettings)`)
only lists keys someone remembered to add. A toggle missing from `DEFAULTS` has no UI default,
so its rendered state is driven by `undefined`, not by the backend's `?? 1` assumption.

**How to apply:** for any default-ON boolean, either add it to `DEFAULTS` with `'1'` so the
UI and the `?? 1` backend agree, or change the backend to default OFF. Never let the two sides
disagree on the absent-key default.

The original global `master_enabled` master switch was removed entirely (v0.70.0) at the
owner's request precisely because this mismatch made it untrustworthy — output is now gated
only by per-feature toggles (enable_sitemap, enable_robots, redirect_enabled, llmstxt_enabled,
the AEO/Pro feature flags, etc.). Do not reintroduce a global kill-switch.
