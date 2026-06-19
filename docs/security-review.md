# Security review process & pre-launch checklist

AI Boost for Joomla is a commercial extension that emits markup into every
front-end page, exposes admin AJAX endpoints, validates licences against a
third-party API, and reads/writes the filesystem and database. This document is
the **repeatable, codebase-specific** security process: the standing invariants
the code must uphold, the checklist to run before every public release, and the
audit cadence. It is owned by the maintainer; update it whenever a new endpoint,
front-end emitter, file operation, or external call is added.

## Cadence

- **Before every public release (minor or larger):** run the *Pre-release
  checklist* below. Patch releases that touch any item in the *Trigger surfaces*
  list run it too.
- **Twice a year (semi-annual):** run a full re-review — re-read every controller
  and every front-end emitter end to end, re-verify the invariants, and re-check
  the licence pins against the live Lemon Squeezy dashboard.
- **Whenever a Trigger surface changes:** the relevant checklist section is
  mandatory in that change's review.

## Trigger surfaces (changing any of these re-arms the checklist)

1. A new or changed **controller task** under
   `component/com_aiboost/admin/src/Administrator/Controller/`.
2. A new path that **writes the settings blob** (`#__aiboost_settings`) or any
   table.
3. A new **front-end emitter** (anything that builds head/body markup or JSON-LD
   via `HeadBlockBuilder`/`BodyBlockBuilder` or a plugin Service).
4. A new **outbound HTTP fetch** (cURL / `file_get_contents` / HTTP client).
5. A new **file operation** (upload, list, read, delete) or media type.
6. A change to **licence validation, pinning, or the Pro gate**.

## Standing invariants (must always hold)

These are the load-bearing security properties of the codebase. A change that
breaks any of them is a regression.

1. **Token + ACL on every state-changing endpoint.** Every controller task that
   writes to the DB or filesystem calls `Session::checkToken()` **first**, then
   `getIdentity()->authorise('core.manage', 'com_aiboost')` (or `core.admin`).
   Read-only GET endpoints may skip the token but must still be ACL-gated and must
   not return data an attacker page could read cross-origin (no CORS headers are
   set, so the same-origin policy is the backstop — keep it that way).
2. **Parameterised SQL only.** All values go through `$db->quote()`, all
   identifiers through `$db->quoteName()`, all ids through `(int)`. `LIKE` terms
   use `$db->quote('%' . $db->escape($term, true) . '%', false)`. No request input
   is ever concatenated into a query string. `SHOW COLUMNS`/`information_schema`
   queries interpolate only `$db->getPrefix()` / hard-coded literals.
3. **JSON-LD cannot break out of its `<script>`.** Every
   `<script type="application/ld+json">` body is encoded with
   `JSON_HEX_TAG | JSON_HEX_AMP` (plus the unescaped-unicode/slashes flags) so a
   value containing `</script>` or `<!--` cannot terminate the element. Every
   `<meta>`/`<link>` attribute value is `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
4. **Raw-HTML surfaces are admin-only and registered.** Fields that emit
   unescaped HTML (`custom_code_*`, `gsc_additional_html`) are writable only via
   the token+ACL-gated settings save and are registered Pro sections in
   `ProFeatureRegistry`. Do not add a new raw-HTML field without both.
5. **System/licence keys never come from the client.** `SettingsSaveDefinition::
   SYSTEM_PRESERVED_KEYS` (licence key/state/tier, `pro_activated*`, `install_id`,
   `dev_*`) are carried forward from the existing row on save
   (`mergeSystemPreservedKeys`) and stripped on import
   (`ImportController::IMPORT_DENYLIST` **is** that same constant) and on export
   (`buildExportPayload`). A client/import file can never self-promote to Pro or
   forge licence state. The two lists must stay derived from the one constant.
6. **Licence validation is fail-closed and pinned.** `LicenseValidator::verify()`
   rejects any response whose `meta.store_id` ≠ `EXPECTED_STORE_ID`, or whose
   `meta.product_id` is not in the allowed set, or that proves no store/product at
   all; it makes no API call while a pin is unconfigured. The transport posts only
   to the hard-coded `api.lemonsqueezy.com` HTTPS endpoints with TLS verification
   on. There is no mock/`AB-*`/simulator bypass — every key goes through the real
   fail-closed `LicenseValidator::verify()` (the QA simulator was removed in v0.86.5).
7. **The Pro runtime gate is one signal.** Every server/runtime/UI Pro gate routes
   through `PluginRegistry::isProActive()` / `hasPro($sku)`, which reads only the
   perpetual `pro_activated` flag (never `license_tier`, never a live
   `license_state` walk). Each `_pro` plugin listener gates itself on `hasPro()`
   before emitting Pro output.
8. **Outbound fetches block internal targets (SSRF).** Any code that fetches an
   admin-influenceable URL validates the target first: http/https only, the site's
   own host allowed, otherwise every resolved IP must be public (loopback,
   RFC1918, link-local `169.254.0.0/16`, unique-local and reserved ranges
   rejected), **re-checked on every redirect hop**. See
   `UrlcheckerController::isFetchTargetAllowed()` /
   `UrlCheckerService::isFetchTargetAllowed()`. `AnalyzerController` and
   `HealthController` use the own-host / `isUnderSiteRoot` equivalents.
9. **File operations are contained.** Uploads: extension whitelist (raster only,
   **no SVG**), `finfo` MIME sniff, `is_uploaded_file`, size cap, sanitised
   filename. List/delete: reject `..`/NUL and use `realpath()` containment under
   the images root; treat symlinks as leaf nodes. No new file type without the
   same pattern.
10. **No dangerous sinks.** No `eval`/`exec`/`system`/`shell_exec`/`passthru`/
    `popen`/`proc_open`/`unserialize`/`extract`/`create_function`. Input is read
    via Joomla `getInput()` filters, never `$_GET`/`$_POST`/`$_REQUEST`.

## Pre-release checklist

Run before tagging a public build. Each item maps to an invariant above.

- [ ] **Endpoints:** every new/changed controller task has `Session::checkToken()`
      before any write, and `authorise('core.manage'…)` after. No token-less
      state change. (Grep the controller for `checkToken`; confirm one per write
      task.)
- [ ] **SQL:** every new query uses `quote()`/`quoteName()`/`(int)`; no
      string-concatenated input. `composer phpstan` clean.
- [ ] **Output:** every new front-end value is HTML/JSON-LD escaped per invariant
      3; new JSON-LD uses `JSON_HEX_TAG | JSON_HEX_AMP`. No new `v-html` bound to
      settings/DB text (only manifest-static constants).
- [ ] **Pro/licence:** new `tier=pro` field gated at runtime via
      `isProActive()/hasPro()`; new SETTINGS-blob writer carries forward / strips
      `SYSTEM_PRESERVED_KEYS`; new integration SKU added to `EXPECTED_PRODUCT_IDS`
      (never null).
- [ ] **SSRF:** any new outbound fetch of an admin-influenceable URL goes through
      a host/IP guard and re-checks redirect hops.
- [ ] **Files:** any new upload/list/delete uses the whitelist + MIME + realpath
      containment pattern; SVG upload stays blocked.
- [ ] **Secrets:** export/debug output strips `SYSTEM_PRESERVED_KEYS`; no licence
      key/token written to logs; no debug-only diagnostic endpoint shipped without
      a `JDEBUG` gate.
- [ ] **Tests:** `composer test` (standalone) + `php vendor/bin/phpunit` green,
      including `SettingsSaveDefinitionTest`, `LicenseValidatorVerifyTest`, the
      manifest/Pro parity tests, and import/export round-trip
      (`verify-import-export.py`).
- [ ] **Pins:** `EXPECTED_STORE_ID` + `EXPECTED_CORE_PRODUCT_IDS` /
      `EXPECTED_PRODUCT_IDS` still match the live Lemon Squeezy dashboard.
- [ ] **Build hygiene:** `verify-no-pro-leakage` STRICT passes; no `@pro:` markers
      leak into the Free ZIP; no debug endpoints reachable in production.

## Accepted risks / design decisions

- **Admin-set raw HTML** (`custom_code_*`, `gsc_additional_html`) is an
  intentional escape hatch for verification snippets. It is `core.manage`-only and
  equivalent to capabilities the admin already has. Not escaped by design.
- **Re-displaying the licence key** in the Licenses page is intentional UX. The
  key is the customer's own, returned over a token+ACL-gated same-origin request.
  Masking it (return only a suffix) is a future defence-in-depth nicety, not a
  blocker.
- **Analytics IDs in inline JS** rely on `htmlspecialchars` (HTML escaper) in a JS
  context. Safe on the supported PHP 8.1+ target because the default `ENT_QUOTES`
  closes both string-literal and `</script>` breakout, and the inputs are
  admin-only. Optional hardening: `json_encode` the values / validate ID formats.

## Audit history

- **2026 (Faza A/B), ~50-agent audit:** fixed XSS via JSON-LD breakout, the
  settings-save full-replace wipe, and Lemon Squeezy store/product pinning.
- **v0.85.2 pre-launch re-review (this document's origin):** full-file re-read of
  all 14 controllers + front-end emitters + licence/secret surfaces, each
  candidate finding adversarially verified. Confirmed the earlier import-bypass
  and `aiboost_social_pro` leak suspicions are **fixed**. Fixed: URL-Checker SSRF
  (own-host/public-IP guard + per-redirect-hop re-check), removed two orphaned
  debug endpoints (`debugsettings`, `enableplugins`), token-gated the `export`
  `last_backup_at` write, restored TLS verification on the robots/sitemap
  previews. No SQL injection, no dangerous sinks, no unauthenticated exposure
  found.
