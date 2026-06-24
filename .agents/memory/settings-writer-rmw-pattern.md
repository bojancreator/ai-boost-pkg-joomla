---
name: every settings-blob writer must be read-modify-write or full snapshot
description: The #__aiboost_settings 'main' row is one JSON blob; a writer that replaces it with a subset wipes sibling keys. Enforced by SettingsWriterRmwContractTest.
---

# Settings-blob writer rule (read-modify-write or full snapshot)

`#__aiboost_settings` key `main` is a SINGLE JSON blob. Any code that writes it replaces the
whole row, so a writer that builds a fresh array of only its own key(s) and writes that
**silently wipes every other setting**.

**Rule — every writer of the `main` blob MUST be one of:**
- **read-modify-write**: load the whole `settings_json`, change only your key(s), write the
  whole blob back (the `last_backup_at` pattern); OR
- **full snapshot**: rebuild the whole blob from a payload that already carries every key —
  ONLY `SettingsController::save()`, safe only because the Vue SPA always posts the complete
  snapshot (see [[settings-save-full-replace]]).

Never write a SUBSET. `SettingsPersistenceService::saveSettings()` is exactly that anti-pattern
and survives only as a "what not to do" marker — it has **no production caller**.

**How to apply when adding a settings writer** (new endpoint, install step, background job):
copy the read-modify-write shape. `IntegrationsController::saveToggle()`/`saveOptions()` carry
the canonical inline warning ("deliberate read-modify-write … NOT a settings.save … would drop
everything the page does not post"), and `pkg_script.php` guards every write with
`encodeSettingsBlobSafe()` — an empty/`false` encode once silently wiped all user data. All live
writers already comply (audited 2026-06-24: 15 writers, every one RMW or full-snapshot).

**Enforced** by `component/tests/Lib/SettingsWriterRmwContractTest.php`: a discovery guard fails
when a new settings-blob writer FILE appears unclassified, and a load-before-write check fails
when an `rmw` writer writes `settings_json` without loading it first. Add any new writer to its
`WRITERS` map. BACKLOG (post-1.0): delete the dead `saveSettings()` + make `save()`
merge-on-existing so even a partial save can never wipe siblings.
