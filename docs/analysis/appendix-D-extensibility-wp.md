# Part 5 — Extensibility & WordPress Readiness

> Architect's read of the codebase, 2026-06-28. Evidence is cited by file + class/function.
> Snapshot counts (`#__`, `Factory::`) drift as code changes — treat as orders of magnitude.
> This part deliberately re-uses and re-checks `aiboost-joomla/docs/ARCHITECTURE-BOUNDARIES.md`
> rather than redoing it; where I disagree or refine, I say so.

---

## Verdict (short)

- **Extensibility: POSTAVLJENO (genuinely working today).** The component can receive new
  *integration* plugins with **no core release** — discovery, field contribution, output filtering
  and conflict-slot claiming are all event-driven and versioned. A third party (or a future AI Boost
  add-on) can ship a `plg_system_aiboost_int_<key>` ZIP and it lights up the Integrations dashboard,
  injects settings fields, and mutates head/sitemap/OG/schema output through a stable SDK.
- **WordPress-readiness: DELOM (scaffolded, not buildable).** The *shape* of WP support exists — a
  full `Cms/` adapter interface set with **both** Joomla (real) and Wp (placeholder) implementations,
  an `AppContextInterface` with a Joomla impl and a WP stub, and the whole integration SDK already
  guards on `ABSPATH`. But the two adapters that matter most (Database, Application body, Http) are
  `throw new \RuntimeException('… not implemented (v2.0 WordPress port)')`, there is **no content
  seam** abstracting the ~200 `#__` table queries, and **no WP entry point / build exists at all**.
  WordPress is a *designed seam awaiting implementation*, not a near-build.

---

## (a) Can the component receive NEW plugins / functions / extensions today? — YES, via the Integration SDK

The extension mechanism is **not** "edit core and add a plugin to the table." It is a mature,
versioned, event-driven SDK in `component/lib/src/Integration/`. A new extension participates through
four independent seams, none of which requires touching core:

### 1. Discovery — a new bridge appears with no core release
- `IntegrationRegistry::all()` (`component/lib/src/Integration/IntegrationRegistry.php`) fires the
  `onAiBoostRegisterIntegration` event (`Sdk::EVENT_REGISTER_INTEGRATION`) through
  `AdapterRegistry::events()->trigger(...)` and collects every `IntegrationDescriptor` returned.
- `PluginRegistry::integrations()` (lines 69-83) builds its element→key map **dynamically** from
  that registry, merging a 2-entry static fallback (`INTEGRATIONS_FALLBACK`) only so the planned
  Falang/YOOtheme tiles still render before their ZIPs ship. The class docblock states the intent
  explicitly: *"a new `plg_system_aiboost_int_<key>` bridge ZIP appears in the Integrations dashboard
  immediately — no core release needed."*
- `IntegrationDescriptor` (`IntegrationDescriptor.php`) is an immutable value object carrying label,
  vendor, category, host detection target, SDK version, conflict slots, etc. It has a forgiving
  `fromArray()` hydrator, so a bridge may return either a typed object or an array shorthand.

### 2. Versioned contract — forward/back compatibility is enforced
- `Sdk::SDK_VERSION = 1` / `MIN_SDK_VERSION = 1` (`Sdk.php`). `IntegrationRegistry::all()` calls
  `Sdk::isCompatible($desc->sdkVersion)` and **discards** mismatched bridges into
  `getSdkMismatches()` (surfaced as Health `warning_bridge_sdk_mismatch`). This is real, not
  cosmetic: a bridge built against a newer SDK than core is refused rather than half-wired.
- The doc contract (in `Sdk.php`) is disciplined: additive changes (new optional descriptor fields,
  new filter events) do **not** bump the version; only breaking removals/renames do.

### 3. Output extension — the FilterDispatcher seam
- Core plugins fire generic, named filter events right before emitting output via
  `FilterDispatcher::dispatch($event, $input)` (`FilterDispatcher.php`). Confirmed live wiring:
  `aiboost_schema` (`AiBoostSchema.php` line 107-109, `EVENT_FILTER_SCHEMA_BLOCKS`),
  plus `aiboost_social`, `aiboost_sitemap`, `aiboost_aeo`, and the YOOtheme bridge all reference
  `FilterDispatcher` / `onAiBoostFilter*` (grep: 8 files under `component/plugins/system`).
- Seven named events exist (`Sdk.php`): head output, sitemap URL set, robots rules, OG tags, social
  props, schema blocks, llms.txt. A new plugin extends any of these by listening with
  `public function onAiBoostFilterXxx(array $input, FilterResult $r): void`.
- `FilterResult` (`FilterResult.php`) is a mutable carrier with a **mutation audit log**
  (`setOutput($out, $by, $reason)` records who changed what) surfaced in Debug/Health. Dispatch is
  **fail-safe**: a listener that throws is logged but never breaks core output (`FilterDispatcher`
  catch block preserves partial mutations).

### 4. Settings/UI extension — manifest field contribution
- A bridge contributes its own admin settings keys through `onAiBoostRegisterFields()` (live in
  `AiBoostIntFalang.php` lines 95-120; 17 files reference the event). Crucially, the save whitelist
  is built from a **live dispatch** of this method (`SettingsSaveDefinition.php`,
  `SettingsController.php`), so a new plugin's fields are persisted without editing the
  `$fields` whitelist — closing the usual "dead option" trap for *integration* fields.
- Field registration is deliberately kept **outside** the Pro-strip fence so a plain Settings save
  never drops a paying customer's keys even in the Free build.

### 5. Base class removes boilerplate
- `AbstractIntegrationPlugin` (`Integration/AbstractIntegrationPlugin.php`) gives a new bridge: lib
  autoloader boot, host detection via `BridgeDetector`, the discovery handler, master-toggle gating
  (`isAdminEnabled()` → `integration_<key>_enabled`), and per-request settings caching. The
  documented subclass contract is ~3 methods: `describe()`, `onAiBoostRegisterFields()`, and one or
  more `onAiBoostFilter*` handlers.

**Conclusion (a): the foundation is genuinely extensible for the integration use-case.** Two real
bridges prove it end-to-end: `aiboost_int_falang` (multilingual hreflang) and `aiboost_int_yootheme`.
Adding a third integration is a ZIP, not a core patch.

### Honest limits on extensibility
1. **Two extension *shapes* exist, and only one is open.**
   - *Integration bridges* (`aiboost_int_*`) — the open, supported, no-core-release path above.
   - *Pro feature plugins* (`aiboost_*_pro`) — **effectively retired as an extension mechanism.**
     Every `*_pro` decorator is now a **dormant no-op**: e.g. `AiBoostSchemaPro.php` (lines 34-43)
     `onAfterInitialise()` does nothing — its docblock says the Pro logic was *relocated INTO the
     free `aiboost_schema` plugin and gated on `PluginRegistry::isProActive()`*. So "add a new
     capability" today means **editing a core Free plugin + the manifest + codegen**, not dropping in
     a decorator. The decorator pattern the prompt asks about is historical, not the live path.
2. **`ProFeatureRegistry` is a legacy compatibility shell, not the gate.** `ProFeatureRegistry.php`
   is explicitly downgraded: `stripLocked()` / `stripProOptions()` are **no-ops** that return the
   payload unchanged (lines 149-152, 309-312); `proOptions()` and `lockedSettingsKeys()` return `[]`.
   The CLAUDE.md "register every Pro surface in ProFeatureRegistry" rule is **stale** versus this
   file — the live gate is the single `pro_activated` flag via `PluginRegistry::isProActive()`
   (`PluginRegistry.php` lines 375-378). This is a documentation/architecture drift worth flagging,
   not a functional bug.
3. **Bridges hard-depend on AI Boost being installed.** `AbstractIntegrationPlugin::bootIntegration()`
   (lines 156-171) `require_once`s `com_aiboost/lib/autoload.php`; with no lib it bails. So the
   current bridge sub-pattern cannot ship as a *standalone+integrative* plugin (one that runs on its
   own and merely enriches AI Boost when present). The boundaries doc already names this as GAP #3
   and proposes a different sub-pattern post-1.0.

---

## (b) How close to a WordPress build? — Scaffolded seam, not buildable (DELOM)

### What IS abstracted (the good news)

1. **A complete CMS adapter interface layer** in `component/lib/src/Cms/`: `DatabaseAdapter`,
   `HttpAdapter`, `FilesystemAdapter`, `ApplicationAdapter`, `ClockAdapter`,
   `EventDispatcherAdapter`, `DocumentAdapter`, `RouterAdapter`. Each has a **real Joomla impl**
   (`Cms/Joomla/Joomla*Adapter.php`) and a **WordPress impl** (`Cms/Wp/Wp*Adapter.php`).
2. **A single static touchpoint.** `AdapterRegistry` (`Cms/AdapterRegistry.php`) is "the ONLY static
   touchpoint that hard-codes `Joomla\Cms\Joomla` class names" (its own docblock). It lazily defaults
   to Joomla but exposes `set*Adapter()` so a WP loader can inject Wp adapters. `AdapterBootstrap`
   does the Joomla-side wiring and its docblock anticipates *"On WordPress the v2.0 loader will
   provide its own bootstrap that registers WpXxxAdapter instances instead."*
3. **The lib core is genuinely CMS-agnostic at the import level.** `grep "use Joomla\CMS"` across all
   **81** files in `component/lib/src` returns **0** — the shared lib programs against framework
   interfaces (`Joomla\Database\DatabaseInterface`, `Joomla\Http\Http`) and its own adapters, not the
   Joomla CMS application classes.
4. **The integration SDK is already WP-aware.** Every SDK + lib file guards with
   `defined('_JEXEC') or defined('ABSPATH') or die` (37 files in lib/src carry the `ABSPATH` guard),
   and `WpEventDispatcherAdapter::trigger()` already routes through `apply_filters()` — so the entire
   FilterDispatcher / IntegrationRegistry machinery would work under WP the moment the data adapters
   exist.
5. **An `AppContextInterface`** (`component/lib/src/AppContextInterface.php`) abstracts page context
   (current URL, language, homepage, title, pathway, timezone) with a real `JoomlaAppContext` and a
   `WpStub/WpAppContext`.
6. **Two WP adapters are already functional stubs** (not throwers): `WpRouterAdapter`
   (home_url/REQUEST_URI), `WpFilesystemAdapter` (ABSPATH/WP_PLUGIN_DIR), `WpClockAdapter`, and the
   `WpEventDispatcherAdapter` above.

### What still hard-codes Joomla / blocks a WP build (the gaps)

1. **The decisive adapters THROW.** A WP build cannot run because:
   - `WpDatabaseAdapter::getConnection()` → `throw RuntimeException('not implemented (v2.0)')`.
   - `WpHttpAdapter::getClient()` → throws.
   - `WpApplicationAdapter::getBody()/setBody()` → throw (and `isSite()` returns `false`, so
     head/body builders skip output by design). `WpDocumentAdapter` mutators are no-ops with `TODO`s.
   - `WpStub/WpAppContext` returns empty defaults for **every** method (URL, title, language, …).
   So the front-end generation pipeline would produce nothing under WP today.
2. **No content seam — the real rewrite surface.** There is **no `ContentRepository` / data-source
   interface** (confirmed: no `*Repository`/`*DataSource` file in `lib/src`). The Joomla data model
   is queried inline. `grep "#__"` counts: **~104 occurrences across ~22 lib files** + **~96 across
   the plugin services** (~200 total). The heavy concentrations (per ARCHITECTURE-BOUNDARIES.md, which
   I re-confirmed by file list): `HreflangSitemapExtension`, `SchemaProBuilder`, `SitemapGenerator`,
   `LlmsTxtProGenerator`, `CustomFieldReader`. These hard-bind to `#__content`, `#__categories`,
   `#__menu`, `#__languages`, `#__falang_content`, `#__extensions`, `#__aiboost_settings`. Porting to
   WP means re-sourcing all of them from `wp_posts`/`wp_terms`/`wp_postmeta` — and with no seam, that
   is a per-call rewrite, not a single adapter swap. **This is the dominant cost of a WP build.**
3. **Direct CMS calls still leak past the adapters** in a few generators (boundaries doc GAP #1,
   re-confirmed): `Route::_()` (17 occurrences in lib+plugins) in `SitemapGenerator`,
   `NewsSitemapGenerator`, `HreflangSitemapExtension`, and the Falang bridge; `JPATH_ROOT` (10
   occurrences) in `OgTagBuilder`, `RobotsTxtManager`; one `Factory::getContainer()` user lookup in
   `SchemaProBuilder`. `Factory::` totals ~117 (lib+plugins) — but the lib-side 40 are heavily
   concentrated in the adapter *implementations themselves* (where Factory is correct) plus a handful
   of services (`LicenseHeartbeat`, `LanguageService`, `TranslationService`, `DomainDetectionService`).
   The plugin-side 77 are the data-acquisition services that the content seam would absorb.
4. **No WordPress entry point or build exists.** `find` for `wp-*.php` / `*wordpress*` returns
   nothing outside vendor/node_modules. There is no WP plugin header, no `register_activation_hook`,
   no `pnpm`/`python` build target emitting a WP ZIP. WP support is **design intent in code comments
   + interface stubs**, with zero shippable artifact.

### Effort shape (architect's estimate, for the owner's decision)

Re-using the boundaries doc's "~65% portable shape logic / ~35% Joomla data" framing — which my
re-measure supports — a WP build is roughly:
- **Small:** implement the 6 throwing/no-op Wp adapters (DB shim on `$wpdb`, Http on
  `wp_remote_request`, Application body via `ob_start`+`wp_head`/`wp_footer`, Document via
  `add_action('wp_head')`, AppContext via `get_bloginfo`/`$wp`). Bounded, well-specified by the TODO
  comments.
- **Large:** introduce the missing **content-repository seam** and route ~200 `#__` queries through
  it, then implement the WP data source. This is the genuine rewrite and the schedule risk.
- **Plus:** a WP entry/loader, settings storage mapping (`#__aiboost_settings` JSON blob →
  `wp_options`), the admin SPA host, and a WP build/package pipeline — all greenfield.

Independent reviewers concur on sequencing: `_handoff/docs/Procena3.txt` explicitly warns against
spending resources on the WordPress adapter layer *"until the Joomla version proves the market"*
(Yoast/Rank Math own that space). The architecture is correctly *prepared* for WP without *committing*
to it — a good posture for "solid foundation first."

---

## setupStatus

| Dimension | Status | One-line reason |
|---|---|---|
| **Extensibility** | **postavljeno** | Versioned event-driven Integration SDK; new `aiboost_int_*` bridges load with no core release (Falang + YOOtheme prove it). Caveat: the `*_pro` decorator path is retired and `ProFeatureRegistry` is a no-op shell. |
| **WordPress-readiness** | **delom** | Full adapter + AppContext interface layer with WP impls exists and the SDK guards on `ABSPATH`, but DB/Http/Application-body adapters throw, there is no content seam for ~200 `#__` queries, and no WP entry point/build exists. |

## Recommendations (for the owner, plain framing in chat)

1. **Treat the integration SDK as a real product surface** — it is the cleanest part of the
   foundation. Document it (a public "build an AI Boost integration" guide) so partners/add-ons can
   extend without you shipping a core release each time.
2. **Reconcile the gating docs with reality** — CLAUDE.md still mandates `ProFeatureRegistry`
   registration, but that class no longer enforces anything (live gate = `pro_activated`). Update the
   instructions so future work does not chase a dead registry.
3. **Defer the WordPress build, keep the seam** — the scaffolding is cheap to keep and correct. Do
   not implement the Wp adapters / content seam until Joomla revenue justifies entering the
   Yoast/Rank Math arena. When you do, the first task is the **content-repository seam**, not the
   adapters — that is where the real work and risk live.
