# Architecture boundaries — logic vs CMS, and the integration SDK

> **Snapshot as of 2026-06-24.** This is a point-in-time read of the codebase to inform repo
> structure ahead of a WordPress build. The specific numbers (coupling counts, `#__` query counts,
> percentages) WILL drift as code changes — treat them as orders of magnitude, not live truth.
> Re-measure before acting. Operative rule: joomla-development skill → "Cross-platform & integration
> boundary". Tasks: `BACKLOG.md` (post-1.0).

## TL;DR

- **~65% of the generation logic is portable** output-shaping (Schema JSON-LD shape, OG tags,
  llms.txt format, sitemap XML structure). ~35% is Joomla-specific **data acquisition** (`#__` table
  queries) plus a few direct `Route::_` / `JPATH_ROOT` calls.
- **A `Cms/` adapter layer already exists**, with abstract adapters + **both Joomla and WordPress**
  implementations + `AdapterRegistry` + `AppContextInterface` + `WpStub`.
- **The integration layer is a mature, versioned SDK** and is **already WordPress-aware** (it guards
  on `defined('_JEXEC') or defined('ABSPATH')`).
- So WordPress is **not a rewrite of the logic** — it is mainly a new **data layer** + wiring the few
  leaking calls through the existing adapters + a WP entry/event layer.

## The three layers

| Layer | Where | State |
|---|---|---|
| **Logic / generators** | `plugins/system/<plugin>/src/Service/*` (SchemaBuilder, SitemapGenerator, LlmsTxtGenerator, OgTagBuilder, …) + `lib/src/RobotsTxtBuilder`, `MarkdownConverterService` | Thin, data injected; low CMS-class coupling |
| **CMS adapter** | `lib/src/Cms/` — abstract adapters + `Cms/Joomla/*` + `Cms/Wp/*` + `AdapterRegistry`, `AppContextInterface`, `WpStub/WpAppContext` | Built for Database, Application, Router, Document, Filesystem, Http, Clock, EventDispatcher (Joomla + Wp) |
| **Integration SDK** | `lib/src/Integration/` — `Sdk`, `AbstractIntegrationPlugin`, `IntegrationDescriptor`, `IntegrationRegistry`, `FilterDispatcher`, `FilterResult`, + `lib/src/BridgeDetector` | Versioned events, descriptor discovery, `class_exists`-guarded, WP-aware |

## Boundaries that HOLD (clean separation)

- **Generators take data injected**, not by reaching for the CMS. Docblocks state it explicitly,
  e.g. SitemapGenerator: *"DatabaseInterface is injected; this service makes no Factory:: or Uri::
  calls."* Most generators import only `Joomla\Database\DatabaseInterface` (a framework interface
  with a `WpDatabaseAdapter`). Rough class-coupling: ~7 generators essentially clean (0 CMS-class
  calls), ~5 with a single CMS spot, ~3 using `Route::_`. None reach for `Factory::getDbo()` /
  `getApplication()` broadly.
- **Integration is a clean core + thin bridge.** Core plugins fire generic `onAiBoostFilter*` events
  via `FilterDispatcher::dispatch()` and read bridge-registered data via `BridgeDetector::get*()`;
  they never name Falang/YOOtheme. Bridges are SEPARATE plugins that implement `onAiBoostFilter*` /
  register data. Detection = `IntegrationDescriptor` host + `BridgeDetector::isExtensionEnabled()`
  (an `#__extensions` install check) + `class_exists()` guards → graceful when host or core absent.
- **Infrastructure is adapter-abstracted** (DB / app / clock / events / http go through
  `AdapterRegistry`), and the SDK + `BridgeDetector` already load under WordPress (`ABSPATH` guard).

## GAPS (where the boundary leaks)

1. **Direct CMS calls still inside a few generators** (should route through the existing adapters):
   - `Route::_()` for SEF URLs — `SitemapGenerator`, `NewsSitemapGenerator`, `HreflangSitemapExtension`.
   - `JPATH_ROOT` for filesystem paths — `OgTagBuilder`, `OgTagProDecorator`, `RobotsTxtManager`.
   - one `Factory::getContainer()` (user lookup) — `SchemaProBuilder`; one `Joomla\CMS\Log\Log` —
     `IndexNowService`.
2. **The CMS data model is NOT abstracted** — generators query Joomla tables inline; there is no
   "content repository" seam. This is the real WP rewrite surface. Approx `#__`-table queries:
   `HreflangSitemapExtension` 12, `SchemaProBuilder` 7, `SitemapGenerator` 6, `LlmsTxtProGenerator` 6,
   `CustomFieldReader` 4, `NewsSitemapGenerator`/`LlmsTxtGenerator`/`OgTagProDecorator` ~2 each
   (~40 total across 8 services). Note `SchemaBuilder` (base JSON-LD) has **0** — the most portable.
3. **Existing bridges hard-depend on AI Boost.** `AbstractIntegrationPlugin` boots the shared lib
   from `com_aiboost`, so the current Falang/YOOtheme bridges are NOT standalone — they assume AI
   Boost is installed. A standalone+integrative plugin needs a different sub-pattern (own core logic,
   AI Boost as an optional `class_exists`-guarded layer).

## Plan (post-1.0, gated — see BACKLOG)

1. **Route the leaking calls through the adapters** — replace direct `Route::_` / `JPATH_ROOT` /
   `Factory` / `Log` in generators with `AdapterRegistry` (`RouterAdapter`, `FilesystemAdapter`, …).
2. **Add a content-repository seam** — abstract the `#__` data fetch behind an interface so the
   ~40 inline Joomla queries become a single adapter to reimplement per CMS.
3. **WordPress adapter** — provide the WP data-source (`wp_posts` / `wp_terms` / `wp_postmeta`),
   finish the `Cms/Wp/*` wiring, and a WP entry/event layer. The ~65% shape logic transfers as-is.
4. **First standalone+integrative plugin** — a NEW sub-pattern (not an `AbstractIntegrationPlugin`
   copy): runs on its own, integrates with AI Boost via the SDK events behind `class_exists`.
