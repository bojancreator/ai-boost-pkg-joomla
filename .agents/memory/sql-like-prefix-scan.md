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
