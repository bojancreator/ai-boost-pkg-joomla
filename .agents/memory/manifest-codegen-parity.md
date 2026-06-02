---
name: Manifest-derived Vue partials and parity tests
description: How codegen-from-manifest.py output interacts with the Vue↔ProFeatureRegistry parity test, and where the real gate-key check belongs.
---

`scripts/codegen-from-manifest.py` writes Vue form-field partials under
`component/com_aiboost/vue-admin/src/tabs/generated/{tab}/{key}.vue`. These
files are *pure derivations* of the manifest and are overwritten on every
codegen run. They don't necessarily emit a `<ProGate gate-key="{exact_key}">`
that matches a row in `ProFeatureRegistry::all()` — gating is often done at
section level via `sectionFields()`.

**Rule:** any scanner that walks the Vue tree to assert ProFeatureRegistry
coverage (e.g. `ProFeatureRegistryParityTest::collectVueFiles()`) must skip
paths containing `/tabs/generated/`. Otherwise it produces false-positive
"orphan" reports the moment new manifest fields are codegen-ed.

**Why:** Assertions on generated artifacts duplicate work the manifest tests
already do, and they flip red on every manifest addition until the registry
catches up — which makes the test untrustworthy and people start ignoring it.

**How to apply:** the *correct* place to assert manifest↔registry parity is
`ManifestProRegistryParityTest` (walks manifest tier=pro keys against the
registry key set + section-fields union). If you ever make generated partials
runtime-critical (i.e. they start gating real save-side logic), add a *separate*
test that maps generated partial → registry entry, do not delete the skip.
