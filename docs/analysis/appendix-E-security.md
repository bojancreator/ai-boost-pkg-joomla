# Part 6 — Security / Breach-Resistance (Owner Q7)

**Scope:** Custom-code injection sinks, media picker (SVG/XSS), settings-save whitelist + `stripLocked()` + export/import denylist, secrets handling, SQL safety, and the licensing/Pro gate ("can Pro be spoofed client-side?"). Assessment is evidence-based: every claim names the file/class/method that proves it. Frame: is the **root** secure enough to ship to sale?

**Verdict in one line:** The security foundation is solid and unusually disciplined for a one-developer product — the dangerous edges (license forgery, export-leak of license keys, SVG stored-XSS, path traversal, SQL) are each closed at the server with a named, fail-closed mechanism. The residual risk is **inherent admin-trust** (custom-code injection is raw HTML by design, available to anyone with `core.manage`) plus a few smaller hardening gaps, none of which are launch-blockers but several of which deserve a fix before a wide public launch.

---

## 1. Custom-code injection path (`aiboost_code` + `aiboost_code_pro`)

**Files:** `component/plugins/system/aiboost_code/src/Extension/AiBoostCode.php`, `aiboost_code_pro/src/Extension/AiBoostCodePro.php`, `component/lib/src/HeadBlockBuilder.php`, `component/lib/src/BodyBlockBuilder.php`.

**How it works.** Three settings keys — `custom_code_head`, `custom_code_body`, `custom_code_footer` — are read from the single `#__aiboost_settings` JSON blob (`AiBoostCode::getSettings()`), trimmed, and pushed **verbatim, unescaped** into the page:
- head → `HeadBlockBuilder::pushSection(SECTION_CODE, $headCode)` (`AiBoostCode.php:192`)
- body → `BodyBlockBuilder::pushBody('Custom Body Code', $bodyCode)` (`:203`)
- footer → `BodyBlockBuilder::pushFooter('Custom Footer Code', $footerCode)` (`:214`)

This is **intentional and correct**: the entire point of a custom-code feature is to inject raw `<script>`/HTML the admin chooses. The builders are explicitly designed to leave this content untouched — `HeadBlockBuilder::trimOwnSections()` runs the cooperative dedup over Schema/Social/AEO/Analytics but **never over `SECTION_CODE`** (`HeadBlockBuilder.php:291-301`, comment at `:283-289`), so a user's pasted `gtag()`/`og:` tag is carried byte-for-byte. The splice in `finalize()` deliberately uses a substring splice (not `preg_replace`) precisely because the raw code may contain `$1`/`\1` regex back-reference syntax that `preg_replace` would corrupt (`HeadBlockBuilder.php:500-509`).

**Who can set it.** Only the settings-save endpoint, which is gated by `Session::checkToken()` + `authorise('core.manage', 'com_aiboost')` (`SettingsController::save()` lines 24-32). So this is a **stored-XSS-by-design surface restricted to trusted back-end admins** — the standard Joomla trust model (any user who can edit `custom HTML` modules or templates already has this power). It is NOT a public/front-end injection point: `onBeforeCompileHead()` early-returns unless `$app->isClient('site')` and the document is HTML (`AiBoostCode.php:160-167`), and emission is further gated by the master toggle `enable_custom_code` and a `staging_mode` suppression (`isEnabled()`, `:83-95`).

**Runtime Pro gate is server-side.** The whole "Custom Code" surface is registered Pro (`ProFeatureRegistry::all()` → `section:code`, `ProFeatureRegistry.php:97`) and the analogous decorators gate on `PluginRegistry::isProActive()` — see §6.

**Residual risk:** none beyond the inherent admin-trust model. The one thing to note for completeness: `custom_code_*_menu_ids` is `json_decode`d and each ID is `intval`-cast before an `in_array` menu match (`AiBoostCode.php:128-138`) — no injection there.

---

## 2. Media picker — SVG / stored-XSS block, path traversal, upload validation

**File:** `component/com_aiboost/admin/src/Administrator/Controller/MediaController.php` (plus a second, simpler lister `SettingsController::mediaImages()`).

This is the strongest-hardened controller in the codebase. Defence layers, all present:

- **SVG upload is blocked** while SVG *listing* is allowed. `UPLOAD_EXT = ['jpg','jpeg','png','gif','webp']` — raster only, **no svg** (`MediaController.php:63`); the rationale is documented at `:48-57`: an `image/svg+xml` file can carry `<script>` (stored XSS) that a finfo MIME sniff cannot neutralise.
- **MIME sniff after extension check.** Upload validates the real bytes with `new \finfo(FILEINFO_MIME_TYPE)` against an allow-list of four raster MIME types (`:228-234`) — so a renamed `.php`→`.jpg` is rejected ("File content does not match its extension").
- **`is_uploaded_file()` check** before move (`:214-217`) and `move_uploaded_file()` (`:250`), then `chmod 0644` (`:255`).
- **Filename sanitised** to `[a-zA-Z0-9._-]`, with collision-avoidance counter (`:237-248`).
- **Size cap** `MAX_UPLOAD_BYTES = 5 MB` (`:66`, `:210-213`).
- **Path-traversal containment** — `resolveFolder()`/`resolveItemPath()` reject `..` and NUL bytes, force the path under `JPATH_SITE/images`, and **canonicalise the deepest existing ancestor with `realpath()`** to close the "not-yet-created target under a planted symlink" hole (`:480-524`, comment `:500-504`).
- **Symlink-aware recursive delete** — `deleteDir()` treats symlinks as leaf nodes (unlink, never follow) and re-validates every child's real path stays inside `images/`, aborting on escape (`:424-474`).
- **CSRF + ACL** — every mutating task (`upload`/`mkdir`/`delete`) requires `Session::checkToken('request')` and `checkAdmin()` (`core.manage` or `core.admin`); the read-only `list` requires admin but no token (`:78-81`, `:567-574`).

**Residual risk:** low. `SettingsController::mediaImages()` (the older lister) is read-only, regex-sanitises the path to `[a-zA-Z0-9/_-]`, and is admin-gated (`SettingsController.php:475-542`) — but it does **not** do the realpath containment that `MediaController` does. Because it only `scandir`s and returns names/URLs (never writes or deletes), the blast radius is information-disclosure of directory listings within `/images`, which is already web-readable — acceptable, but worth unifying on `MediaController`'s resolver eventually.

---

## 3. Settings save — whitelist, `htmlspecialchars_decode`, `stripLocked()`, system-preserved keys

**Files:** `SettingsController::save()`, `component/lib/src/SettingsSaveDefinition.php`, `component/lib/src/ProFeatureRegistry.php`.

**Whitelist (positive allow-list).** Only keys in `SettingsSaveDefinition::acceptedKeys()` are read from input (`save()` lines 38-45). The list is `COMPATIBILITY_KEYS` (a ~250-entry explicit list) ∪ manifest-derived safe keys. Any field not on the list is **silently dropped** — a posted unknown key cannot reach the DB blob. This is the right shape (allow-list, not deny-list).

**`htmlspecialchars_decode` on every value (line 43).** Each accepted value is run through `htmlspecialchars_decode($value, ENT_QUOTES)` before storage. This is **not** a vulnerability in itself — it normalises browser-encoded form values back to raw, and each *consumer* is responsible for escaping on output (the analytics plugin escapes every ID with `htmlspecialchars` before emitting — `aiboost_analytics` `:108-365`; schema goes through `json_encode`). The custom-code fields are intentionally raw (§1). The thing to flag: this design means **output-escaping correctness is distributed across ~14 plugins**, so the invariant "every settings value is escaped at its sink" is only as strong as the least careful emitter. Spot-checks (analytics, schema, head/body builders) are clean, but this is the place a future regression could introduce stored XSS in a *non*-code field.

**`stripLocked()` / `stripProOptions()` are now NO-OPS.** In the one-product model both methods `return $payload` unchanged and `lockedSettingsKeys()` returns `[]` (`ProFeatureRegistry.php:309-312`, `:281-284`, `:149-152`). **Consequence:** a Free admin CAN save Pro feature values into the blob — they are not stripped on save. This is **safe** because the runtime emitters gate on `isProActive()` server-side (§6), so a saved-but-locked value simply does nothing. The defence has moved from "strip on save" to "ignore on emit". Worth documenting as a deliberate architecture choice, not a hole — but it does mean the save endpoint no longer enforces tier on *feature* fields (only on *license/identity* fields, below).

**System-preserved keys (the real save-time security boundary).** `SettingsSaveDefinition::mergeSystemPreservedKeys()` (`:240-250`) **fail-closes in both directions** for `SYSTEM_PRESERVED_KEYS` (`:42-62`): license key, `license_tier`, `license_state`, `pro_activated`, `pro_installed`, `install_id`, dev overrides, etc. A value present in the existing row always overwrites whatever the client posted (so posting `pro_activated=1` cannot self-promote Free → Pro), and a key absent from the existing row is removed entirely (so it can never be *introduced* via the form). This is the single most important save-time guard and it is correct. Note the removed `dev_license_preview`/`dev_force_free_tier` overrides still sit on this denylist as an anti-self-promotion guard (matches CLAUDE.md / memory `license-sim-removed`).

---

## 4. Export / Import denylist (cross-install license-key leakage)

**Files:** `SettingsController::export()` / `buildExportPayload()`, `ImportController::upload()`.

- **Export strips license/identity keys.** `buildExportPayload()` (`SettingsController.php:391-411`) `unset()`s every `SYSTEM_PRESERVED_KEYS` entry from `params` before writing the downloadable JSON — so a backup file **never carries the customer's plaintext license key**, activation flags, or `install_id`. The function is `static` + side-effect-free specifically so the redaction contract is unit-testable (comment `:376-384`).
- **Import refuses the same keys.** `ImportController::IMPORT_DENYLIST = SettingsSaveDefinition::SYSTEM_PRESERVED_KEYS` (`ImportController.php:39`) — **built directly on the shared constant so the two boundaries cannot drift.** On upload each denied key is `unset()` (`:108-114`); the comment at `:22-38` spells out the threat it closes: a hand-crafted export could otherwise set `license_state[*].status=active` to forge entitlement, set `pro_activated` to unlock Pro on another site, or clobber the unique `install_id`.
- **Import hardening:** CSRF + `core.manage` (`:43-51`), `is_uploaded_file` + 5 MB cap (`:57-65`), JSON-validated (`:73-77`), and **merge-not-overwrite** so destination-only keys survive (`:144`).

This is a textbook implementation — the single-source-of-truth shared constant between save / export / import is exactly right and prevents the classic "we patched save but forgot import" drift.

---

## 5. SQL safety

Across `SettingsController`, `ImportController`, `MediaController`, `PluginRegistry`, every query uses the Joomla query builder with `$db->quote()` / `$db->quoteName()` and `(int)` casts — no string-concatenated user input into SQL was found.

- **LIKE search** (the classic injection spot) is correctly escaped: `searchArticles()` does `$db->quote('%' . $db->escape($q, true) . '%', false)` — `escape(..., true)` escapes LIKE wildcards, `quote(..., false)` then quotes without re-escaping (`SettingsController.php:1053`). The `fillUpdateDownloadKey()` LIKE on `%updates.aiboostnow.com%` is a constant, not user input (`:1931`).
- **ID lists** are `array_map('intval', …)` before `IN (...)` (`searchArticles` `:1041-1049`).
- `SHOW COLUMNS FROM` uses `$db->getPrefix()` (trusted) not user input (`:669`, `:784`).
- The only raw superglobal in the whole front-end path is `aiboost_aeo` reading `$_GET['markdown'] === '1'` as a boolean compare (`AiBoostAeo.php:393`) — no sink, no injection.

**Verdict:** SQL surface is clean.

---

## 6. Licensing / Pro gate — **can Pro be spoofed client-side? No.**

**Files:** `component/lib/src/PluginRegistry.php`, `LicenseValidator.php`, `ProGate.php`, `SettingsController::verifyLicense()`, `View/App/HtmlView.php`.

**The gate is a single server-resolved flag.** `PluginRegistry::isProActive($settings)` returns `pro_activated === '1'`, else false (`PluginRegistry.php:375-378`). Every Pro decision point delegates to it: the admin bootstrap (`View/App/HtmlView.php:204`), the settings-save tier check (`SettingsController::isProSetting()` → `isProActive`, `:1132-1139`), the runtime emitters (`aiboost_schema` `:127` gates `SchemaProBuilder` on `isProActive($settings)`; `aiboost_schema_pro` header comment confirms it is "gated on `PluginRegistry::isProActive()`"). UI, server enforcement and front-end rendering therefore **cannot drift apart** — they read the same DB flag.

**Why the client cannot forge it:**
1. `pro_activated` is on `SYSTEM_PRESERVED_KEYS`, so the settings-save and import endpoints **physically refuse to write it from client input** (§3, §4). The only writer is `PluginRegistry::markPerpetualActivation()` (`:599-609`), reached only from `saveLicenseState()`.
2. `saveLicenseState()` only flips `pro_activated` when `coreLicenseActive($states)` is true — and that function **ignores `int_*` integration keys** (anti-leak, `:583-594`), so a cheap add-on key cannot unlock the core bundle.
3. The `license_state` written by `saveLicenseState()` comes from `LicenseValidator::verify()`, which is **fail-closed and server-authoritative**: `status` is only `'active'` when the live API returns `valid===true` AND `license_key.status==='active'` AND `meta.store_id === EXPECTED_STORE_ID` (store pinning, `LicenseValidator.php:386-397`) AND, for pinned products, `meta.product_id` is in the allowed set (product pinning, `:399-416`). Store ID `367944` and the core 3/10/unlimited product IDs are pinned (`:60`, `:104`); an unconfigured pin fails closed with a loud error and **no API call** (`:331-335`).
4. The client's only inputs to `verifyLicense()` are `sku` (validated against a fixed `LICENSE_SKUS` allow-list, `:1809`/`:1828`) and the raw `license_key`. It **cannot** post a status — the status is whatever the authoritative API says. `verifyLicense()` is CSRF + `core.manage` gated via `guardLicense()` (`:2031-2045`).
5. The bootstrap `isPro` shipped to the Vue SPA is **cosmetic only** — `View/App/HtmlView.php:255` sets it from the server's `isProActive`, and even if a user edited the in-page JS to `isPro=true`, the save endpoint, import endpoint and every runtime emitter re-derive Pro from the DB flag independently. Flipping the client flag unlocks **nothing** server-side.

**The fail-closed posture is consistent throughout** `LicenseValidator` — every network error, missing HTTP transport, empty/malformed response, foreign store or wrong product resolves to a non-active status (`callApi` `:245-271` returns `'free'`; `verify` returns `status:'invalid'/'expired'`). Pro is never granted on ambiguity.

**One legacy wrinkle (not a hole):** `ProGate` trait still contains the *old* per-plugin `license_tier`-in-`#__extensions.params` path (`validateAndStoreLicense` / `storeLicenseTier` / `onExtensionAfterSave`, `ProGate.php:60-176`) pointing the deprecated `LicenseValidator::validate()` (LS-style). This is dead/legacy relative to the perpetual-activation model and is not the active gate, but it is live code that writes a `license_tier` param. It cannot grant core Pro (the gate is `pro_activated`, not `license_tier`), but it is confusing surface area that should be removed to prevent a future maintainer wiring a gate to it.

---

## 7. Secrets handling

- **No secrets in the shipped plugin.** Store/product IDs are public pinning constants, not secrets (`LicenseValidator.php:60-104`). The license key itself lives only in the DB `license_state` and is **stripped from exports** (§4).
- **Backend** (`aiboostnow-backend/`) keeps secrets in gitignored `config/secrets.php` or env vars (`AIBOOST_DB_*`, `AIBOOST_ADMIN_TOKEN`) per its `src/Config.php` — out of scope for this plugin review but consistent with the no-secrets-in-repo rule.
- **`indexnow_api_key`** and similar third-party keys are stored in the settings blob and **are** carried in export/import (they are not on the denylist). That is acceptable (they are the customer's own keys for their own site) but means a leaked backup file exposes the IndexNow key — minor, worth a line in docs.

---

## 8. WordPress-readiness security note (Owner's "WP-ready?" angle)

The CMS-abstraction layer exists (`component/lib/src/Cms/`, `WpStub/`, `Cms/Wp/WpDocumentAdapter.php`) but the **WordPress security primitives are not yet implemented**: a grep for `wp_kses` / `esc_html` / `sanitize_*` / `current_user_can` / `wpdb->prepare` / nonce across `WpStub/` and `Cms/Wp/` returns **nothing** — `WpStub/` is a single `WpAppContext.php`. The Joomla side gets its safety from Joomla's `Session::checkToken`, `authorise()`, and the query builder; **none of those have WP equivalents wired yet.** So when the WP port is built, the entire CSRF (nonce), capability (`current_user_can`), SQL (`$wpdb->prepare`) and output-escaping (`wp_kses`/`esc_*`) layer must be re-implemented — the current adapter does not inherit it for free. This is the single biggest security item gating an actual WP launch, and it is correctly *not* claimed as done.

---

## 9. Realistic breach surfaces — ranked

| # | Surface | Exposure | Protection | Residual |
|---|---------|----------|-----------|----------|
| 1 | Custom code injection (`aiboost_code`) | Stored HTML/JS into every front-end page | Admin-only (`core.manage` + CSRF), front-end-only emit, master toggle | **By design** — inherent admin trust; not a defect |
| 2 | License/Pro forgery | Unlock paid features for free | `pro_activated` server-only + `verify()` store/product pinning, fail-closed; client flag cosmetic | **Closed** |
| 3 | Export/import license-key leak & entitlement forgery | Plaintext key in backup; activate Pro elsewhere | Shared `SYSTEM_PRESERVED_KEYS` strip on export + deny on import | **Closed** |
| 4 | Media upload (web-shell / SVG XSS / traversal) | RCE / stored XSS / file escape | No-SVG upload, finfo MIME sniff, realpath containment, symlink-safe delete, CSRF+ACL | **Closed** |
| 5 | Output-escaping regression in a non-code settings field | Stored XSS | Distributed `htmlspecialchars`/`json_encode` at each sink (verified clean today) | **Low, monitor** — invariant is per-emitter, not central |
| 6 | SQL injection | DB compromise | Query builder + quote/quoteName + LIKE escape + int casts everywhere | **Closed** |
| 7 | Legacy `ProGate` `license_tier` param path | Confusing dead gate | Not the active gate; cannot grant core Pro | **Low** — remove to prevent future misuse |
| 8 | `SettingsController::mediaImages()` weaker resolver | Dir-listing info disclosure under `/images` | Regex sanitised + admin-gated; read-only | **Low** — unify on `MediaController` resolver |
| 9 | WP port lacks nonce/cap/prepare/kses | Full breach class on WP | Not yet built (correctly unclaimed) | **Open for WP only** — Joomla unaffected |

---

## 10. Recommendations before public launch (priority order)

1. **(Hygiene)** Remove or quarantine the legacy `ProGate::validateAndStoreLicense` / `storeLicenseTier` / `onExtensionAfterSave` path (§6) so no future maintainer can wire a gate to the dead `license_tier` param.
2. **(Hardening)** Add a central output-escaping helper (or an automated test) asserting that every non-`custom_code_*` settings value reaching a head/body sink is escaped — to make surface #5 a *central* invariant rather than a per-emitter convention.
3. **(Hygiene)** Unify `SettingsController::mediaImages()` onto `MediaController::resolveFolder()` containment (#8).
4. **(Docs)** Note in customer docs that exports carry their own third-party keys (IndexNow etc.) so backups should be treated as sensitive (§7).
5. **(WP only)** Treat the WP security primitive layer (nonce / capability / prepare / kses) as a hard prerequisite for the WP port, not a follow-up (§8).

None of 1-4 are launch-blockers for the Joomla product; the core breach classes (license forgery, key leak, upload RCE/XSS, SQL, path traversal) are each closed today with a named, fail-closed mechanism.
