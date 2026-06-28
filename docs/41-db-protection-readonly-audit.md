# #41 — Database-protection READ-ONLY audit

**Mode:** strictly read-only. Nothing was changed. This document maps where
update / install / uninstall **and** import/export can or cannot destroy a
customer's saved settings. Fixes are a separate, later step.

**Scope of "user data":** `#__aiboost_settings` (single JSON blob, key `main` —
the whole configuration) + `#__aiboost_translations`, `#__aiboost_redirects`,
`#__aiboost_url_scans`, `#__aiboost_error_log`, `#__aiboost_404_log`.

#41 is **two different problems** with **two different answers**:

| Problem | Trigger | Verdict |
|---|---|---|
| **1. Lifecycle** (install / update / uninstall) | a **customer** action | ✅ **SAFE today** — no live data-loss path |
| **2. Option versioning** (definitions never change, old stay forever) | a **developer** action (renaming/removing an option in a future version) | 🟠 **REAL GAP** — latent, not currently live |

---

## FAZA 0 — History: where the loss happened, and does the fix hold

The disaster Bojan remembers was real. The original bug wiped the whole `main`
settings row during the uninstall step of a free→pro cycle, in two ways:
a SQL `JSON_REMOVE()` that coerced the column to `NULL`, and a failed
`json_encode()` that returned `false` — either wrote an empty value that the
*next* install read as "no settings" and treated as a clean slate.

**It was fixed and the fix holds.** The git history is squashed at the
2026-06-02 "clean source snapshot", so the commit that originally *introduced*
the wipe is not individually recoverable; the surviving record is the in-code
HARD SAFETY comments (`pkg_script.php:2869-2875`, `:2824-2829`) and
`PkgScriptUninstallKeysTest`'s header. The decisive **hardening** is dated:

| Commit | Date | What it did |
|---|---|---|
| `3093aa3` | 2026-06-15 | **The major hardening.** Introduced the entire Phase 0 safety layer: postflight uninstall early-return guard, `backupMainSettings()` auto-backup, `encodeSettingsBlobSafe()` on every blob writer, the `!isProInstall()` veto on legacy-artifact removal. This is the single most important commit for Bojan's exact fear. |
| `cd257a2` | 2026-06-11 | "Bug #8" — `!isProInstall()` veto so a Free-base update never uninstalls a paying customer's Pro. |
| `84a52b6` | 2026-06-12 | `disableOrphanedProAddons()` runs first in `uninstall()` (admin-lockout incident). |
| `df07195` | 2026-06-19 | Locked the uninstall wipe-set to 3 dev keys only. |
| `9bcaf29` | 2026-06-24 | 22 default-ON toggles mirrored into Vue DEFAULTS — fixed a **setting-value** loss (a Save could persist OFF), not a table loss. |
| `0bf66b8` | 2026-06-24 | NBE-safe `ESCAPE` on the Pro-cleanup `LIKE` — fixed a *failed cleanup* (no Pro leak, no wipe). |

**Memory lessons that govern this area** (all still accurate):
`joomla-postflight-fires-on-uninstall.md`, `settings-save-full-replace.md`,
`settings-writer-rmw-pattern.md`, `settings-default-mismatch.md`.

**Verification:** working tree is clean and identical to HEAD for every
safety-relevant file. `vendor/bin/phpunit --filter "PkgScriptPhase0SafetyTest|
PkgScriptUninstallKeysTest|SettingsWriterRmwContractTest"` → green. No fix found
to have regressed.

---

## FAZA 1 — Lifecycle (pkg_script): **SAFE**

There are three installer scripts in the package (not two): the FREE/combined
`component/package/pkg_script.php`, the legacy Pro add-on
`component/package/pkg_script_pro.php`, and the component's own
`component/com_aiboost/admin/script.php`, plus two integration-plugin scripts.

### Every DB-touching operation, classified

| file:line | Operation | What it touches | Scope |
|---|---|---|---|
| `pkg_script.php:147-149` | postflight **returns immediately** unless install/update/discover_install | — | the central guard; nothing mutating runs on uninstall |
| `pkg_script.php:154` | `backupMainSettings()` before any migration | writes `main_backup_<ver>` sibling row, keeps newest 3 | **never touches `main`** |
| `install.sql` / `ensureNewTables()` | `CREATE TABLE IF NOT EXISTS` only | tables | existing rows **always preserved** |
| `uninstall.sql` (component + lib) | intentionally **EMPTY** | — | **no table is ever dropped** |
| `pkg_script.php:2652` `dropOldTable()` | `DROP TABLE IF EXISTS` | **legacy `#__joomlaboost_*` only**, after a verified row copy | never a `#__aiboost_*` table |
| `pkg_script.php:2846-2885` uninstall | `unset()` + `UPDATE` | removes **only** the 3 dev keys (`dev_license_preview`, `dev_force_free_tier`, `license_simulation`) from `main` | minimal; license/activation keys deliberately survive |
| `pkg_script.php:2719-2743` | `UPDATE #__extensions SET enabled=0` | disables orphaned `*_pro` plugins | **registration flag only** — deletes nothing |
| `pkg_script_pro.php` modules | `DELETE FROM #__modules` | Health-module **placement** rows | registration only |

There is **no** `DROP TABLE`, `TRUNCATE`, or unconditional `DELETE` against any
of the six user tables anywhere in any lifecycle script. There is **no**
`<update><schemas>` element in any manifest, so Joomla never auto-runs a
versioned schema-diff SQL (no hidden drop path).

### The dangerous sequence, traced

In the current architecture, "Pro" is the **combined package**; installing Pro
over Free is a normal package **upgrade** of the same component + plugins.

1. **Free → Pro:** backup written first → `CREATE TABLE IF NOT EXISTS` keeps
   rows → migrations skip-if-exists → edition flag added. **`main` preserved + backed up.**
2. **Pro → Free:** edition flag flipped to `0`; the runtime gate is
   `pro_activated`, not this flag; legacy-artifact removal runs only
   `if (!isProInstall())`. **`main` preserved.**
3. **Uninstall:** `uninstall.sql` empty → no table dropped; only the 3 dev keys
   stripped from `main`, and even that aborts rather than write an empty value.
   **All six tables + `main` preserved.**
4. **Reinstall:** `CREATE TABLE IF NOT EXISTS` finds the rows still there →
   **everything returns intact**, licence survives, the site comes back Pro.

### Barriers — all HOLD

- Postflight uninstall guard — `pkg_script.php:147`, `pkg_script_pro.php:98`.
- No table ever dropped — `uninstall.sql` empty; `dropOldTable` legacy-only.
- HARD SAFETY: never write empty/`''`/`[]`/`false` to `settings_json` —
  `pkg_script.php:2869-2885`; generalised as `encodeSettingsBlobSafe()` `:1328`.
- No-SQL-`JSON_REMOVE` rule (edit the blob in PHP, not SQL) — `:2824-2829`.
- `loadSettings()` returns `null`, never wipes, on empty/corrupt blob —
  `SettingsPersistenceService.php:93-98`.
- Migrations never overwrite existing keys (skip-if-exists) — `:2515-2518`.
- Backup-before-migrate — `:154` → `:1342-1382`.

### Uninstall verdict

Uninstall correctly distinguishes "remove the extension" (Joomla removes files +
`#__extensions` rows) from "delete user data" (it does **not**). It purges no
`#__aiboost_*` table, keeps the licence, and removes from `main` only the 3
QA/dev keys — and if the blob doesn't decode it is left entirely untouched.

### Two non-urgent observations (neither is a data-loss path)

- The two integration plugins write `main` with plain `json_encode` instead of
  the hardened encoder (`aiboost_int_falang/script.php:96`,
  `aiboost_int_yootheme/script.php:113,187`). They only ever **add** a key inside
  an `if ($row)` guard and run only in postflight, never uninstall — so they
  cannot empty the row, but they bypass the project-wide encoder discipline.
- Stale comment: `com_aiboost/admin/script.php:20-23` claims uninstall.sql drops
  tables. The file is intentionally empty; the runtime is safe, the comment is
  wrong.

### 🔴 LIVE RISK (lifecycle): **NONE.**

The free→pro→uninstall→reinstall disaster is safe end-to-end with layered,
test-backed defences. This is the good news that should retire the fear.

---

## FAZA 2 — Option versioning (Bojan's rule): **PARTIAL — real gap**

Bojan's rule: *every option's definition is unique and never changes; you may
add new ones but old ones stay forever for compatibility; a changed option uses
a NEW definition and the old one remains; import restores everything, ignores
what no longer exists, and sets even what changed but means the same.*

| # | Sub-rule | Verdict | Why |
|---|---|---|---|
| 1 | Definitions are unique | ✅ **COMPLIES** | Each manifest field has an explicit, label-independent string `key`; codegen uses `field["key"]` verbatim. Renaming a *label* changes nothing in the DB. |
| 2 | Old definitions never removed; stay for back-compat | 🟠 **VIOLATES** | No mechanism keeps an old key alive after a manifest rename/removal. Save is **full-replace gated by a whitelist** (`SettingsController.php:38-45` → `acceptedKeys()`); any key not in the whitelist is dropped on the next Save. The only keep-alive is the **hand-maintained** `COMPATIBILITY_KEYS` list (`SettingsSaveDefinition.php:74-215`) — a convention, not an enforced guarantee. |
| 3 | Changed option uses a NEW definition, old stays | 🟠 **ABSENT** | No "supersede with a new key, retain the old" facility. The only key-remap is the frozen legacy `$keyMap` for pre-`pkg_aiboost` imports (`ImportController.php:296-343`). Live data has no versioned-definition path. |
| 4 | Import: restore all, ignore removed, set changed-but-same | 🟡 **MOSTLY** | Import is additive `array_merge($existing, file)` (`ImportController.php:144`), **bypasses the save whitelist**, so it restores and gracefully keeps unknown/removed keys without crashing. "Changed-but-same" only via the frozen legacy map. The denylist strips **only** licence/identity keys — no legitimate user setting (`:39,108-114`). |

**No general migration / option-versioning layer exists.** There is no
`schema_version` stamp on the blob, no per-version upgrade hook, no alias table.
The only settings migrations are two one-off, install-time routines (legacy
`#__joomlaboost_*` table copy; a single `license_tier → pro_skus` transform).

### Concrete ways the rule breaks today

1. **Key rename = guaranteed loss on first Save.** Rename manifest key `X→Y` in
   a future version: every customer who saved `X` loses it on their next Save
   (`X` not in the whitelist → dropped; `Y` reads its default). The value lingers
   orphaned until that Save, which masks the bug in QA.
2. **Removing a manifest field silently deletes its value on next Save** unless a
   developer also pins it in `COMPATIBILITY_KEYS`. Nothing enforces "once
   shipped, never removed."
3. **Type / allowed-value change has no coercion or definition-versioning** — the
   old raw value is written back unchanged and the consuming service must
   tolerate it.
4. **Save/import asymmetry:** importing an old backup *recovers* removed keys
   (merge, no whitelist), but the very next in-app Save *strips them again*.
5. **No version stamp** means a future migration couldn't even tell which
   definition-version a stored value belongs to.

### 🟠 GAP (versioning): real but **latent**

This hole does not lose data on its own. It fires only when **we** rename or
remove an option's internal key in a future release without pinning it — which
is exactly the "the next version changes an option" scenario Bojan worries
about. It is the right thing to close next; it is not actively losing data today.

---

## FAZA 3 — Bottom line (already-safe vs real-hole)

**Already safe (do not touch):**
- The entire install / update / uninstall / reinstall lifecycle. No customer
  action loses data. The historical disaster is fixed and the fix holds, with
  layered defences and passing regression tests.

**Real hole (close next, separate task):**
- Option versioning. If a future version renames or removes an option's internal
  key, the customer's saved value for it is dropped on their next Save, because
  the save whitelist is the only gatekeeper and the only keep-alive is a
  hand-maintained list. There is no migration layer, no version stamp, and no
  enforced "old definitions stay forever" guarantee. Import is forgiving;
  in-app Save is not.

**Fix sketch for the later step (NOT done here):** enforce rules 2 & 3 with a
test that pins every shipped key as permanently-accepted (so removing one from
the manifest fails CI unless it is moved to a frozen keep-list), an optional
old→new alias map for renames, and a `schema_version` stamp on the blob so a
real migration layer becomes possible. None of this is implemented in this pass.
