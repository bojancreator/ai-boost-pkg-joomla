---
name: AI Boost staging frontend-audit gotchas
description: Non-obvious traps when auditing the AI Boost Pro frontend on the offroadserbia staging site (Falang, Pro-plugin default state).
---

# AI Boost staging frontend-audit gotchas

These bit the Pro-inactive live audit and will bite the Pro-active audit too.

## 1. Pro plugins install DISABLED by default
The 5 Pro plugins (`aiboost_{schema,social,aeo,hreflang,code}_pro`) ship with no
`enabled` attribute in their manifest and `pkg_script_pro` adds no enable step, so
Joomla defaults them to **disabled** on install. Confirm via Installer → Manage view.

**Why it matters:** any predicted "Pro plugin leaks artifact X without a license"
finding will NOT manifest on a default install — the plugin isn't even running. The
leak is *latent* (surfaces only if a user manually enables the plugin without a
license). Don't report "clean" as proof the gate works; characterise it as latent and
push the real `hasPro()` gate fix downstream. The canonical example is `social_pro`
OG enrichment (zero `hasPro('og')` guard).

## 2. Falang corrupts HTTP status codes — audit by BODY, not status
The staging site runs the third-party **Falang** multilingual plugin, whose PHP 8.5
`ReflectionProperty::setAccessible()` deprecation notice emits output before headers,
so `header()` fails ("Cannot modify header information"). Result: absent Pro endpoints
(`/sitemap-index.xml`, `/sitemap-news.xml`, `/llms-full.txt`) return **HTTP 200** with
a Falang error page / Joomla soft-404 body instead of a clean 404.

**Why it matters:** status-code-based absence checks are unreliable here. Always
inspect the response **body** — a real Pro sitemap has `<sitemapindex>`/`<urlset>`,
a real Pro `llms-full.txt` has AI Boost markdown. No such markers = absent.

## 3. Falang JS-redirects non-/sr/ URLs to the homepage
URLs without the `/sr/` language prefix (and `?option=com_content&view=category`
blog URLs) redirect to `/`, so they capture the homepage, not the intended page. Use
real published menu pages under `/sr/...` (e.g. `/sr/clanstvo`) for a non-home,
non-article "listing" capture.

## 4. settings.save strips Pro keys + never elevates the tier
Posting Pro-only fields (`custom_code_head`, `ga4_measurement_id`, per-language
`translations`) to the `settings.save` AJAX on a Pro-inactive install returns
`success:true` but silently drops them. Posting `license_tier:"pro"` does NOT set
`isPro` — `isPro` derives only from verified `license_state[*].status=active`. Good
for confirming server-side enforcement; also means the verifier's `license_tier:"pro"`
forcing is dead code.

## verify-clean-uninstall can brick the staging admin (teardown not crash-safe)
**Symptom:** every admin URL (incl. com_login) returns HTTP 500
`Attempted to load class "Logger" from namespace "AiBoost\Lib"`; frontend still 200.
**Cause:** `uninstall_all_aiboost` removes the package first (`'for joomla'` sort) and
relies on Joomla's cascade. NOTE the two "AI Boost for Joomla" rows are the **package
(pkg_aiboost) + the component (com_aiboost) sharing a display name** — that is NORMAL,
not a duplicate install (don't chase a phantom duplicate). The real defect: when one
row's uninstall 500s (partial teardown), the shared Core/`lib` is removed (so
`AiBoost\Lib\Logger` is gone from disk) while a leftover ENABLED aiboost system plugin
still autoloads it at boot → fatal before login renders → no HTTP recovery (login
itself 500s, so install/uninstall/disable are all unreachable).
**Do NOT re-run the destructive verifier on staging until the teardown is fixed** to
disable every `aiboost%` plugin BEFORE removing `lib`/Core (and/or guard the Logger
autoload with `class_exists(...,false)`). A re-run risks a second outage needing manual
DB recovery.
**Recovery (needs DB/FS, agent can't do over HTTP) — use the real prefix, NOT jos_:**
`UPDATE <prefix>_extensions SET enabled=0 WHERE element LIKE 'aiboost%' AND type='plugin';`
then reinstall pkg_aiboost over HTTP to restore lib + re-enable.
**Also:** `settings.save` is FULL-REPLACE (not merge) — a partial save wipes the whole
`settings_json` blob incl. `license_state`. Set toggles via `settings.save` FIRST, then
activate licenses via `settings.verifyLicense` (targeted merge) LAST.
