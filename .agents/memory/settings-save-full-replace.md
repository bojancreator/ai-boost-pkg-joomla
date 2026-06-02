---
name: settings.save is a full-replace endpoint
description: Why partial programmatic settings.save calls silently wipe sibling keys, and how it broke the uninstall verifier
---

# settings.save full-replace gotcha

`SettingsController::save()` is a **full-replace** store, not a merge. It writes only
the posted `$settings` plus a small carry-forward whitelist (dismissed_checks,
license_tier, dev flags, locked Pro keys, change_counter). Any key NOT in the POST and
NOT in that whitelist is **dropped** from the `main` row of `#__aiboost_settings`.

**Why this matters:** the Vue SPA always posts the entire form, so in normal use nothing
is lost. But any *automation* (the uninstall verifier, scripts, future programmatic
callers) that does a partial `settings.save{one_key=...}` will silently erase every other
setting in the row.

**How it bit us:** the clean-uninstall verifier seeded a QA marker via
`save_marker(org_name)` then called `ensure_robots_marker()` which does
`settings.save{enable_robots=1}` — that second partial save clobbered org_name *before*
uninstall ran, making the "user data preserved" assertion fail even though uninstall was
correct. Fix was ordering: do robots normalization FIRST, make the marker the LAST
settings write before uninstall.

**How to apply:** never assume settings.save merges. If you must set one key
programmatically, GET the full settings first, mutate, and POST the whole object — or
sequence partial saves so the value you care about is written last. If a future task
wants true partial saves to be safe, make save() merge over the existing row instead of
replacing.
