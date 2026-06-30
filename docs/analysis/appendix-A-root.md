# Part 1 — THE ROOT (core principle + output pipeline)

> Architect review, dimension 1 of N. Answers: *what does this product actually do, what is its
> core principle, and is the foundation coherent and solid or scattered?* Every claim below cites
> the file/class/method that proves it. Read directly from source on 2026-06-28.

---

## 1. What the product IS, in one sentence

AI Boost is a **single, consolidated output engine** that takes the site owner's settings and turns
them into a fixed, ordered block of machine-readable signals — Schema.org JSON-LD, OpenGraph/Twitter
meta, AEO/AI-discovery meta, analytics tags and custom code — injected once into every front-end
page, plus the same discipline for `<body>`/footer regions. The owner's thesis ("code & text placed
into the right OUTPUT SINKS, translatable + Google-readable + AI-visible") is **literally the
architecture**: there are exactly two output sinks (`HeadBlockBuilder`, `BodyBlockBuilder`), one
consolidated marker-wrapped block per region, and one settings source feeding them.

## 2. The core principle (the "root invariant")

The whole foundation rests on **one rule, enforced structurally**:

> No plugin writes to the page directly. Every plugin *accumulates* its contribution into a
> request-scoped builder; ONE idempotent `finalize()` renders the consolidated block and splices it
> into the page body exactly once.

Evidence:

- `HeadBlockBuilder` (`component/lib/src/HeadBlockBuilder.php`) and `BodyBlockBuilder`
  (`component/lib/src/BodyBlockBuilder.php`) are the only two write points. Both are `final` classes
  with private static accumulators (`$sections`, `$body`, `$footer`) and a single `finalize()`.
- The accumulation API is `HeadBlockBuilder::pushSection($section, $body)`,
  `BodyBlockBuilder::pushBody($label, $body)` / `pushFooter($label, $body)`. A grep across all system
  plugins shows **every** output plugin (schema, social, aeo, analytics, code, int_yootheme) funnels
  through these and **none** emit HTML themselves — 12 push calls across 6 plugins, zero direct
  `addCustomTag()`/`<body>` regex writes.
- `finalize()` is guarded by a static `$finalized` flag (`HeadBlockBuilder.php:471-474`,
  `BodyBlockBuilder.php:227-230`): the **first** caller does the work, the rest no-op. This is why
  **plugin order does not matter** — every plugin calls `finalize()` in its own `onAfterRender`, and
  whoever runs first wins. This is the keystone that makes the engine robust against Joomla plugin
  ordering.

This is a coherent, deliberate design — Yoast/GTM-style consolidation — not an accident. The class
docblocks state the intent explicitly and the section order is a named constant.

## 3. The end-to-end pipeline: from a setting to bytes on the page

Here is the complete path a single option travels, with the proving code at each hop. This is the
"one coherent model" the owner asked for.

### Write side (admin → storage)

1. **Vue SPA edits a field.** The field's `v-model` key (e.g. `enable_schema`) plus a
   `:data-ab-field` for Health Fix-It. Source of truth for every field is the manifest
   (`component/lib/src/Manifest/*.php`).
2. **Save POSTs to `SettingsController`** (`component/com_aiboost/admin/src/Administrator/Controller/SettingsController.php`).
   The controller accepts ONLY whitelisted keys via
   `SettingsSaveDefinition::acceptedKeys()` (`SettingsController.php:38`,
   `component/lib/src/SettingsSaveDefinition.php`). Keys not on the list are **silently dropped** —
   this is the "three-way key alignment" invariant: Vue v-model ↔ accepted-keys whitelist ↔ consuming
   plugin must all agree or the option is dead.
3. **Pro/identity guard.** `ProFeatureRegistry::stripLocked()` removes any Pro-gated key a Free
   install tried to post (`SettingsController.php:142`), and `SYSTEM_PRESERVED_KEYS`
   (`SettingsSaveDefinition.php:42-55`) fences off licence/identity keys so a client can neither
   self-promote to Pro nor wipe a paying customer's activation.
4. **Persist.** Everything lands in a **single row** of `#__aiboost_settings` —
   `setting_key = 'main'`, `settings_json` = one JSON blob (`SettingsController.php:216-228`). The
   entire product configuration is one JSON document, not a column-per-setting table.

### Read side (front-end request → page bytes)

5. **`onAfterInitialise`** (`aiboost_core` → `AiBoostCore.php:59`) runs first: wires the CMS adapter
   layer (`AdapterBootstrap::registerJoomla`), runs admin-only heartbeat/reconcile, and on the site
   handles early 301/302 redirects *before* Joomla routes (`handleRedirects`, `AiBoostCore.php:560`).
6. **`onBeforeCompileHead`** — every output plugin reads the SAME blob with an identical
   request-cached loader (`getAiBoostSettings()` — present verbatim in `AiBoostCore.php:35-54` and
   `AiBoostSchema.php:216-235`), checks its master switch (`enable_schema`, etc.), runs its generator
   (`SchemaBuilder`, `OgTagBuilder`, …), and calls `pushSection()/pushBody()`. Example:
   `AiBoostSchema::onBeforeCompileHead` builds JSON-LD, optionally Pro-decorates it, JSON-encodes with
   `JSON_HEX_TAG|JSON_HEX_AMP` (XSS-safe), then `HeadBlockBuilder::pushSection(SECTION_SCHEMA, …)`
   (`AiBoostSchema.php:163-168`).
7. **`aiboost_core` seeds the request-scoped policy** in its own `onBeforeCompileHead`
   (`AiBoostCore.php:194-270`): the hide-comments flag and the **per-feature conflict mode** for each
   section (`HeadBlockBuilder::setSectionMode(...)` driven by `ConflictPolicy::legacyModeFor()`),
   plus canonical URL (`addHeadLink` + `noteNative('canonical')`) and title/meta-description
   templates (rewritten in place, `applyTitleTemplate`/`applyMetaDescTemplate`).
8. **`onAfterRender`** — every plugin calls `HeadBlockBuilder::finalize($app, Version::VERSION)` and
   `BodyBlockBuilder::finalize($app)`. The first call:
   - bails unless `isSite()` and body is non-empty;
   - runs the **cooperative conflict dedup** on OUR sections only (`trimOwnSections` →
     `trimBlockConflicts`, `HeadBlockBuilder.php:291-426`) — a pure, unit-testable function that can
     only ever delete *our* tags, never a third party's (the safety invariant by construction);
   - renders the block in the **fixed order** `Schema → Social → AEO → Analytics → Custom Code`
     (the `ORDER` constant, `HeadBlockBuilder.php:87-93`);
   - lets registered integration bridges filter the final HTML (`FilterDispatcher::dispatch(EVENT_FILTER_HEAD_OUTPUT)`);
   - **byte-safe splices** the block immediately before `</head>` using a substring splice, NOT
     `preg_replace` — deliberately, because user custom-code may contain `$1`/`\1` regex
     back-reference syntax that `preg_replace` would corrupt (`HeadBlockBuilder.php:500-544`).
   `BodyBlockBuilder::finalize` mirrors this for after-`<body>` and before-`</body>`.

So the model is: **one JSON blob → N plugins accumulate into 2 builders → 1 idempotent finalize →
1 consolidated block per region, in fixed order, spliced byte-safely.** That is the entire root.

## 4. Manifest-first codegen (the second pillar of the root)

The settings surface is not hand-maintained — it is **generated from one source of truth**.

- `component/lib/src/Manifest/*.php` (`core.php`, `schema.php`, `og.php`, `code.php`, `hreflang.php`,
  `aeo.php`) declare every field as a data array (key, tab, type, tier, sku, default, health, i18n…).
  `Manifest/Registry.php` merges the static manifests with runtime plugin contributions
  (`onAiBoostRegisterFields`) and annotates each field's lock state from `PluginRegistry::capabilities()`.
- `scripts/codegen-from-manifest.py` reads those manifests and emits four derived families: Pro
  feature stubs, en-GB `.ini` keys, Vue form partials (`vue-admin/src/tabs/generated/`), and Health
  stubs. Generated partials are overwritten every run; stubs are idempotent (never clobber hand logic).
- A `--check` mode fails the build if a complex field lacks both a generated partial and a
  hand-written `data-ab-field`. This is what keeps the three-way key alignment honest at build time.

Verdict on codegen: **coherent and enforced** — a new option means *edit manifest → codegen*, and the
build refuses to ship a half-wired option. This is the discipline that makes "is the foundation
extensible" answerable with "yes, by design."

## 5. Cross-CMS adapter layer (WordPress-readiness at the root)

The builders already accept a CMS-neutral seam. `finalize()` signatures are
`CMSApplication|ApplicationAdapter` (`HeadBlockBuilder.php:469`, `BodyBlockBuilder.php:225`): a raw
Joomla app is wrapped in `JoomlaApplicationAdapter`, but an `ApplicationAdapter` (the WP port + unit
tests) is accepted directly. Every lib file guards with `defined('_JEXEC') or defined('ABSPATH') or
die` — the WordPress entry constant is already first-class (`AdapterRegistry.php:29`, every
`Cms/Wp/*` adapter). Both `Cms/Joomla/*` and `Cms/Wp/*` implementations exist for Database,
Application, Router, Document, Filesystem, Http, Clock, EventDispatcher. The pre-existing
`docs/ARCHITECTURE-BOUNDARIES.md` measures ~65% of generation logic as portable output-shaping; the
remaining ~35% is Joomla data acquisition (`#__` queries) with no content-repository seam yet.

So at the ROOT level the output pipeline is **already CMS-agnostic**; the WordPress gap is a data
layer, not a rewrite of the engine. (Full WP judgement belongs to the dedicated WP dimension; noted
here only because the seam lives in the root files.)

## 6. Resilience features baked into the root

- **Partial-install tripwire.** Every plugin probes `libReady()` (`class_exists` on two core lib
  classes, in a try/catch because JDEBUG's loader throws) and no-ops if the shared lib is half-removed
  (`AiBoostCore.php:693-705`, identical in `AiBoostSchema.php:197-209`). The plugin entry file also
  returns early if `lib/autoload.php` is absent (`aiboost_core.php:15-19`). A broken/partial deploy
  degrades to "does nothing," never a white screen.
- **Never crash the page.** Redirect handling, 404 logging, and Pro decoration are all wrapped in
  `try/catch` that swallow and (optionally) log (`handleRedirects`, `log404Request`,
  SchemaPro decorate at `AiBoostSchema.php:138-140`).
- **Idempotent, order-independent finalize** (the `$finalized` flag) — already covered above.
- **Staging mode** short-circuits canonical/templates/redirects with a clear `error_log` breadcrumb
  (`AiBoostCore.php:112-115`).

## 7. Is the root coherent and solid, or scattered? — Architect verdict

**Coherent and solid.** This is the strongest part of the codebase. The product has a genuine,
single, well-named architectural spine that all output flows through:

1. **One principle**, enforced structurally (no direct page writes; accumulate-then-finalize).
2. **One settings source** (single JSON blob) with a guarded write path and a request-cached read.
3. **One render contract** (fixed section order, single marker-wrapped block per region, byte-safe
   splice).
4. **One source of truth for options** (manifest + codegen + build-time `--check`).
5. **One CMS seam** (adapter layer) already threaded through the builders.

The cooperative-dedup design deserves special credit: it is a *pure* function with a structural safety
invariant (it can only delete our own tags), unit-testable away from Joomla. That is mature
engineering, not a quick hack.

The root is **not scattered**. Where complexity exists (the conflict/dedup regexes, the per-feature
conflict policy), it is concentrated, documented and centralized — exactly where complexity *should*
live. On the owner's question "is the foundation solid before shipping": **the output root is
ship-grade.** The known soft spots are one layer out (the un-abstracted `#__` data-acquisition for the
WP port, and the three-way-alignment discipline that depends on humans editing manifests correctly —
mitigated but not eliminated by the build check), not in the root pipeline itself.

---

### Appendix — key file map for this dimension

| Concern | File |
|---|---|
| Head output sink | `component/lib/src/HeadBlockBuilder.php` |
| Body/footer output sink | `component/lib/src/BodyBlockBuilder.php` |
| Core plugin (canonical, redirects, policy seed, finalize) | `component/plugins/system/aiboost_core/src/Extension/AiBoostCore.php` |
| Core plugin entry / partial-install guard | `component/plugins/system/aiboost_core/aiboost_core.php` |
| Representative output plugin | `component/plugins/system/aiboost_schema/src/Extension/AiBoostSchema.php` |
| Manifest sources of truth | `component/lib/src/Manifest/*.php` |
| Manifest merge + lock state | `component/lib/src/Manifest/Registry.php` |
| Codegen | `scripts/codegen-from-manifest.py` |
| Save whitelist + identity fence | `component/lib/src/SettingsSaveDefinition.php` |
| Save controller | `component/com_aiboost/admin/src/Administrator/Controller/SettingsController.php` |
| CMS adapter seam | `component/lib/src/Cms/AdapterRegistry.php`, `Cms/Joomla/*`, `Cms/Wp/*` |
| Prior boundary analysis (reused) | `docs/ARCHITECTURE-BOUNDARIES.md` |
