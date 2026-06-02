---
name: Joomla package postflight fires on uninstall
description: Joomla calls a package install script's postflight() during uninstall with $type='uninstall' — guard any (re)generation so it can't undo uninstall cleanup.
---

# Joomla package postflight runs on uninstall too

When a `pkg_*` package is uninstalled via com_installer, Joomla invokes the
package install script's `uninstall()` **and then also calls `postflight()`
with `$type === 'uninstall'`**. This is easy to miss because postflight reads
as an install/update hook.

**Why this bit us:** the pkg_script `postflight()` called a
`cleanupStaticFiles()` helper (which *injects* the robots.txt managed block)
**unconditionally**, outside the `in_array($type, ['install','update','discover_install'])`
guard. So on uninstall the sequence was: `uninstall()` strips the managed block
→ `postflight(type='uninstall')` re-injects it. Net result: the block survived
every uninstall and the clean-uninstall verifier's `/robots.txt clean` check
always failed, even though the strip logic itself was provably correct.

**How to apply:** any side effect in a package `postflight()` that *creates* or
*regenerates* artifacts (static files, DB rows, menu items) must be inside the
`install/update/discover_install` type guard — never run it for every
postflight call, or it will undo what `uninstall()` just did.

**Diagnostic that cracked it:** route a debug line into a file the server is
actually allowed to serve (robots.txt is whitelisted on LiteSpeed; arbitrary
new webroot files 403/404), recording `$type` in each lifecycle method. That
revealed the `type=uninstall` postflight firing *after* the strip. Also: a
cache-bust query param does NOT bust LiteSpeed static-file serving — isolate
"HTTP cache vs disk truth" by changing disk server-side (a settings save) and
re-curling immediately.

**Verifier gotcha (same task):** the clean-uninstall verifier filtered rows by
`'for joomla' in label`, which matches BOTH the package and the component
(both display "AI Boost for Joomla"), so it removed a package member directly
instead of "package row(s) only". The real fix was the postflight guard; the
verifier two-row removal was a red herring that only surfaced the latent bug.

**Recurred (admin Health module move base→Pro):** `pkg_script_pro.php::postflight()`
published the `mod_aiboost_health` admin module unconditionally, so Pro uninstall
(`uninstall()->removeHealthModule()`) was undone by `postflight('uninstall')`
re-publishing it. Same guard fixed it. Install-time runtime checks (module present,
count=1 in cpanel) do NOT exercise this — the bug is uninstall-only, caught by code
review.

**Related #__assets orphan risk:** the health-module helpers delete `#__modules`
rows directly (not via JTable/Installer), so instances carrying an `asset_id` can
leave orphan `#__assets` rows. The instance we insert uses `asset_id=0` (safe);
pre-existing instances from old base builds are the only risk. Low/cosmetic — fix
only if a clean-uninstall asset check is added.
