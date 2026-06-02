---
name: Deployed ZIP can lag behind repo source
description: When a staging/CI test fails on behaviour that source code says is fixed, suspect a stale deployed build before deep-debugging the test or source.
---

# Deployed ZIP can lag behind repo source

A staging verifier check (e.g. `verify-clean-uninstall.py` robots.txt cleanup)
can fail even though the *current repo source* clearly contains the fix. The
cause: the **built/deployed ZIP in `deliverables/plugin/` was built from an
older source** and never rebuilt, so the live site runs old code while you read
new code.

**Tell-tale sign:** a live artifact (robots.txt header, marker string) that does
NOT match any string in current source. If the live string isn't grep-able in
`component/`, the deployed build is stale — do not keep debugging the strip/test
logic.

**Why:** `Version.php` can be bumped in source (e.g. 0.71→0.72.x) without anyone
running `build-package-zip.py` + `install-to-staging.py`. The newest ZIP in
`deliverables/plugin/` then trails source by a version.

**How to apply:** Before deep-debugging a staging test failure, (1) inspect the
newest deployed ZIP's actual code (use Python `zipfile` to recurse the nested
`packages/*.zip` — `unzip` is not installed in this env), and (2) compare its
markers against source. If they differ, rebuild + reinstall to *both* staging
(Pro) and free, then re-run. Confirm the deployed writer's output matches source
(do a settings-save and re-fetch the artifact) before trusting any pass/fail.
