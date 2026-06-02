---
name: Pro package installer must enable its own plugins
description: Why a paying Pro customer can get zero Pro output even with an active license — the Pro package installer never enabled its plugins.
---

# Pro package installer must enable its own plugins

The Free package script (`pkg_script.php`) has `enablePlugins()` that flips the
Free `aiboost_*` system plugins to `enabled=1`. The **Pro** package
(`pkg_aiboost_pro` / `pkg_script_pro.php`) is a *separate* install with its own
script and ships 5 closed-source plugins (`aiboost_schema_pro`, `aiboost_aeo_pro`,
`aiboost_social_pro`, `aiboost_hreflang_pro`, `aiboost_code_pro`).

**Rule:** the Pro installer's `postflight` must enable its own 5 plugins. If it
doesn't, Joomla installs them **disabled**, so a paying customer with a verified
license still sees **no Pro output** until they manually publish each plugin.

**Why:** this produced a real "Pro doesn't work" symptom. During the 3-state
audit, Schema/AEO Pro happened to be manually enabled on staging (so they
rendered), but `social_pro` (Pro OG) and `code_pro` (custom code) were left
disabled → those two silently produced nothing. The cause was *not* the swallowed
`catch (\Throwable)` in `OgTagProDecorator`; it was simply that the plugin was off.

**How to apply:** enabling Pro plugins unconditionally on install is safe because
each Pro plugin self-gates on a verified-active license (`PluginRegistry::hasPro`).
Plugin **enabled** = code path present; **license active** = behaviour unlocked.

**Related installer gap:** Joomla installs a *module extension* but never
auto-creates a module **instance**. `mod_aiboost_health` (admin cpanel widget)
therefore never appeared until `pkg_script.php` started inserting a published
instance (idempotent: only when none exists, into `#__modules` +
`#__modules_menu`, `client_id=1`, position `cpanel`).
