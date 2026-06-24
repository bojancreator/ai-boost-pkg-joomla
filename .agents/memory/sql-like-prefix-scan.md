---
name: SQL LIKE prefix scans are sql_mode-fragile
description: Why aiboost_ prefix scans over #__extensions filter in PHP instead of using SQL LIKE with a backslash escape
---

When scanning Joomla `#__extensions` (or any table) for the `aiboost_` element
prefix, do NOT use `LIKE 'aiboost\_%'`. The backslash escape of the literal `_`
is sql_mode-dependent: under `NO_BACKSLASH_ESCAPES` (and across some DB
drivers) the pattern fails to match normal rows, which would falsely classify
healthy plugins as `missing` and trip warning-level Health checks on clean
installs.

**Rule:** select with a coarse, escape-free WHERE (e.g. `type='plugin' AND
folder='system'`) and filter the exact prefix in PHP with
`str_starts_with($element, 'aiboost_')`.

**Why:** portability and correctness beat the tiny extra row count; admin Health
/ install-integrity scans are not a hot path.

**How to apply:** any new code that enumerates AI Boost extensions/plugins by
prefix (InstallIntegrity, conflict/duplicate scanners, registries).

## Live example fixed + structural guard (2026-06-24)

`pkg_script.php`'s Free-package postflight disabled the `aiboost_*_pro` decorators with
`LIKE 'aiboost\_%\_pro'` and **no ESCAPE clause** — a LIVE latent bug: under `NO_BACKSLASH_ESCAPES` a
bare LIKE has NO escape character at all (the default backslash escape is disabled), so the hard-coded
backslashes become literal characters to match and the query disabled NOTHING on such hosts. Verified
against dev.mysql.com + MySQL Bug #10214 (mysqli `real_escape_string` does NOT add backslashes under NBE,
so the backslashes are the developer's, not the driver's — the footgun is a red herring here).

Two forms are safe under ALL sql_modes:
1. **Canonical (preferred — the rule above):** coarse escape-free WHERE + `str_starts_with`/`str_ends_with`
   in PHP — no LIKE escaping at all, fully sql_mode-independent.
2. **Explicit `ESCAPE '\'` clause:** a non-empty one-character ESCAPE is honoured regardless of NBE (NBE
   only disables the implicit/empty default escape). `PluginRegistry::isProInstall` + `mod_aiboost_health`
   use this; `pkg_script` was fixed to match (the minimal pre-1.0 fix). Converging all three onto form 1
   is a post-1.0 BACKLOG item.

NEVER ship an escaped-underscore LIKE (`\_`) without an ESCAPE clause. Enforced by
`component/tests/Lib/SqlLikePrefixEscapeContractTest.php` — a SOURCE-level guard, because the standalone
test DB ignores WHERE/LIKE and CI has no NBE MySQL, so a result-level test would falsely pass for BOTH
the broken and fixed code. (Related, separate, low impact: the user-search LIKE in `ErrorsController.php`
manually escapes `\_`/`\%` without an ESCAPE clause and is NBE-fragile the same way.)
