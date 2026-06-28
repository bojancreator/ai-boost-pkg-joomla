# Export / Import READ-ONLY audit (backup + internal test tool)

**Mode:** strictly read-only. Nothing was changed. This maps whether
export/import is **complete** and **safe** enough to rely on, both as a
customer's backup and as our internal test tool for multilingual/feature checks.
Companion to `41-db-protection-readonly-audit.md`. Every claim is cited file:line.

## The one architectural fact that drives everything

`SettingsController::export()`
([SettingsController.php:276](../component/com_aiboost/admin/src/Administrator/Controller/SettingsController.php#L276))
**dumps the entire `main` settings blob**, **adds all `#__aiboost_translations`
rows**, then `buildExportPayload()`
([:391](../component/com_aiboost/admin/src/Administrator/Controller/SettingsController.php#L391))
**removes every key in `SettingsSaveDefinition::SYSTEM_PRESERVED_KEYS`**
([:397-399](../component/com_aiboost/admin/src/Administrator/Controller/SettingsController.php#L397)).
It is a **denylist-based whole-blob dump, not an allowlist.** Import is the
mirror: whole-file `array_merge` over the existing blob, minus the same denylist.

Consequence:
- **Completeness is automatic** — anything in the blob is exported, including
  new options, with no per-option code.
- **Secret-safety rides entirely on one denylist** (`SYSTEM_PRESERVED_KEYS`).
  Any sensitive key written into the blob but missing from that list would leak.

---

## 1. Completeness — does it capture all user settings?

The five option-origin channels, tested by "does the value physically land in
the `main` blob?" (if it does, the whole-blob dump exports it):

| # | Channel | Captured? | Where stored / why |
|---|---|---|---|
| 1 | Static Manifest fields (`Manifest/*.php`) | ✅ | `main` blob via Save → `acceptedKeys()` → `safeManifestKeys()` (`SettingsSaveDefinition.php:317-353`) |
| 2 | Runtime `onAiBoostRegisterFields` (falang_*, yootheme_*) | ✅ *conditional* | `main` blob via `IntegrationsController::saveOptions()` (`IntegrationsController.php:200-239`) and the live event dispatch in `acceptedKeys()` (`SettingsSaveDefinition.php:356-374`) |
| 3 | `SettingsSaveDefinition` legacy/generated keys (COMPATIBILITY_KEYS + manifest) | ✅ | blob keys, written by Save (`SettingsController.php:211-232`) |
| 4 | `IntegrationsController::INTEGRATION_OPTION_KEYS` (falang_*/yootheme_* + `integration_*_enabled`) | ✅ | `main` blob via `saveOptions()`/`saveToggle()` (`IntegrationsController.php:87-124, 200-239`); **none are denylisted**, so export keeps them |
| 5 | `onAiBoostGetSettingsTabs` | N/A | UI-only tab data (`{id,label,html,svg}`, `View/Settings/HtmlView.php:130-154`); introduces no persisted value — inputs save through channels 1-4 |

**So yes — integration options (falang_/yootheme_) AND runtime fields ARE
captured**, because they all write into the `main` blob rather than into plugin
params. Translations are captured separately (`SettingsController.php:303-313`).

### Blind spots (real)

1. **`#__aiboost_redirects` is NOT exported.** Export reads only `settings_json`
   + `#__aiboost_translations`. User-authored 301 redirects (the 301 manager) are
   genuine configuration and are silently absent from any backup. **This is the
   one clear backup/restore data gap.**
2. `#__aiboost_url_scans`, `#__aiboost_404_log`, `#__aiboost_error_log` are also
   not exported — defensibly out of scope (derived/log data), but the omission is
   silent and untested.
3. **Save-after-import drop trap.** Import restores any key by blind merge
   (`ImportController.php:144`), but the next in-app **Save** rebuilds the row
   only from `acceptedKeys()` (`SettingsController.php:38-45`) and overwrites.
   Any imported key not in `acceptedKeys()` and not carried-forward is silently
   dropped on that save — notably a **disabled integration's** `falang_*`/
   `yootheme_*` option values (the bridge plugin must be *enabled* for its keys to
   enter `acceptedKeys()` via the live event dispatch). Import reports success;
   the next Save quietly prunes them.

---

## 2. Auto-update — does export adapt when an option is added/removed?

- **Export: AUTO.** Whole-blob `json_decode` + dump (`SettingsController.php:290-296`)
  — a newly added option appears in the file with no code change (provided its
  value reaches the blob, i.e. it is in `acceptedKeys()`; see the #41 audit).
- **Import: AUTO.** Whole-file `array_merge` (`ImportController.php:144`) — a new
  key imports with no code change.

So completeness auto-adapts. **But three hand-maintained lists still sit on the
path**, each with a silent failure mode:

| List | Location | Failure if a dev forgets it |
|---|---|---|
| `SYSTEM_PRESERVED_KEYS` | `SettingsSaveDefinition.php:42-62` | A new secret not added → **leaks into the backup** AND can be smuggled in via crafted import. (Drift is test-guarded; new omissions are not — see §3D.) |
| `COMPATIBILITY_KEYS` | `SettingsSaveDefinition.php:74-215` | A new non-manifest key absent here → imports but is **dropped on next Save** (the #41 fragility) |
| `INTEGRATION_OPTION_KEYS` | `IntegrationsController.php:36-53` | A new integration option not mirrored here → never reaches the blob → never exported; no parity test catches it |

This is the **same fragility as #41 versioning**: completeness auto-adapts, but
secret-protection and the save-whitelist are manual.

---

## 3. Secrets — CRITICAL (both directions)

### A. Explicit boundary exists

Yes: `SYSTEM_PRESERVED_KEYS` (`SettingsSaveDefinition.php:42-62`) is the single
source of truth. `IMPORT_DENYLIST = SYSTEM_PRESERVED_KEYS`
(`ImportController.php:39`) so export-strip and import-deny cannot drift. Three
tests pin the contract: `SettingsExportPayloadTest`,
`SettingsSaveSystemPreservedKeysTest`, `ImportDenylistParityTest`.

### B. 🟢 Leak check — NO active secret leak

Every secret/identity key written into the blob is denylisted and provably
stripped. The 15 covered keys: `license_key`, `license_tier`, `license_state`
(holds nested plaintext SKU keys — stripped with its parent), `license_heartbeat`,
`license_reconcile`, `license_simulation`, `pro_activated`, `pro_activated_at`,
`pro_activated_version`, `pro_skus`, `pro_installed`, `install_id`,
`last_backup_at`, `dev_license_preview`, `dev_force_free_tier`.
`SettingsExportPayloadTest` asserts the literal secret value appears nowhere in
the serialised file. **No `*_token`/`*_secret`/`*_password`/`webhook`/
`client_secret` field exists** anywhere in manifests or services (grep-confirmed;
`specific_credentials` is a Schema.org qualification field — "MD, PhD" — not a
secret). Heartbeat/reconcile write only denylisted keys; there is no `install_id`
alias outside the denylisted `license_heartbeat`.

**One item for explicit owner sign-off (NOT a security bug): `indexnow_api_key`
is exported.** It is written by the normal form save and is not denylisted, so it
appears in the file. But it is a **deliberately public verification token** — the
plugin serves it openly at `https://yoursite/{key}.txt`
([IndexNowService.php:64](../component/plugins/system/aiboost_aeo/src/Service/IndexNowService.php#L64),
[AiBoostAeo.php:84](../component/plugins/system/aiboost_aeo/src/Extension/AiBoostAeo.php#L84))
so Bing/Yandex can prove ownership; anyone can already read it on the live site.
It grants no Pro and no identity beyond the public domain. Exporting it in a
backup is reasonable (restore keeps IndexNow working). Only nuance: reusing one
export across domains would plant the same token on several sites — harmless.
**No action required for security; flagged so the decision is explicit.**

### C. Over-redaction check — nothing valuable wrongly stripped

No customer-meaningful configuration is wrongly removed. `license_tier` is a
derived signal (must be re-derived at destination); `last_backup_at` is
install-local — both correctly redacted. Minor opposite issue (over-EXPORT, not a
security problem): `conflict_setup_done`, `dismissed_checks`, `last_seen_version`
are install-local UI state that ARE exported and would transfer between sites
(e.g. a restored `dismissed_checks` hides Health warnings the new site never
reviewed). Cosmetic, low priority.

### D. The structural weakness — denylist completeness is unguarded

The denylist is hand-maintained, and the tests only catch **drift/regression of
the existing list**, not **future omissions**.
`SettingsSaveSystemPreservedKeysTest` asserts the constant equals exactly those
15 names — so the only thing that trips a test is editing the existing constant.
**If a future developer adds a new secret-bearing key to some write path and
forgets to denylist it, every test stays green and the secret exports silently.**
This is the highest-value hardening opportunity (out of scope here): a test that
fails unless every key matching `*_secret|*_token|*_password|*_key|webhook` (and
every new license/identity write) is either denylisted or explicitly marked
public.

---

## 4. Test-tool blind spots — what import alone cannot exercise

Because `pro_activated`, `pro_skus`, `license_state`, `license_key`, `install_id`
and the `dev_*` flags are denylisted on **both** export and import, **importing a
JSON can never make a site Pro.** `isProActive()` is exactly
`settingEnabled($settings,'pro_activated')` (`PluginRegistry.php:377`), and
`pro_activated` is unreachable via import (`ImportController.php:109-114`).

So these load their DATA on import but stay OFF until a real key is activated or
`pro_activated='1'` is DB-seeded:

| Feature | Gate (denylisted) | Data imports? | Works after import alone? |
|---|---|---|---|
| Core Pro decorators (Schema/Social/AEO/Code Pro) | `pro_activated` | toggles yes | **NO** |
| Full hreflang Pro | `pro_activated` | yes | **NO** |
| IndexNow auto-submit | `isProActive` | `indexnow_*` yes | **NO** |
| Translated **schema** output (multilingual) | `pro_activated` AND `hasPro('int_falang')` (both denylisted) | translations yes | **NO** |
| Falang Pro / YOOtheme Pro | `license_state['int_*']` (denylisted) | options yes | **NO** |

**Multilingual blind spot, precisely:** translation **data** DOES import
(`#__aiboost_translations` is a separate, non-denylisted table — upserted at
`ImportController.php:167-204`, exported at `SettingsController.php:303-313`). So
the test tool can load every per-language value. **But the translated-schema
OUTPUT requires `isProActive()` AND `hasPro('int_falang')`**
(`AiBoostSchema.php:127,134`) — neither can arrive via import. **An import-only
test can confirm translation rows load and toggles are set, but CANNOT verify
translated JSON-LD actually renders.** Relying on import alone for multilingual
verification produces false negatives (data present, output silent). To exercise
gated multilingual output you must separately activate a real key or DB-seed
`pro_activated='1'` (+ a Falang `license_state` row) — per OPERATING.md.

---

## Verdict — already good vs holes

**Already good (no action needed):**
- Whole-blob export/import is genuinely auto-updating; manifest, legacy/compat,
  integration options + master switches, and per-language translations are all
  captured.
- **No license key / identity / activation flag can reach the backup file**;
  verified and test-pinned in lock-step across save/export/import.
- All analytics IDs (GA4/GTM/Pixel/GSC/FB) are public client-side identifiers,
  correctly exportable. The IndexNow token export is by-design (public).

**Holes / next steps (not security-critical today, separate fix tasks):**
1. **Backup gap:** `#__aiboost_redirects` (user 301s) is not exported — a restore
   loses them.
2. **Denylist completeness is unguarded (§3D):** a future new secret could leak
   silently with all tests green — add a completeness test.
3. **Save-after-import drop trap (§1.3):** restored keys not in `acceptedKeys()`
   (esp. a disabled integration's options) vanish on the next Save.
4. **`INTEGRATION_OPTION_KEYS` has no parity test** against the bridges' field
   lists — a future integration option could quietly fall out of backups.
5. **Test-tool blind spot:** import can never turn a site Pro, so Pro-gated and
   translated-schema OUTPUT cannot be verified by import alone — needs a real key
   or DB-seed alongside.

**No 🔴 active secret leak found. No fixes applied (read-only).**
