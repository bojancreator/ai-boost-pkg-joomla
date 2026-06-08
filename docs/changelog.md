# AI Boost for Joomla — Changelog

All notable changes to **AI Boost for Joomla** are documented here.
Latest version is always at the top. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

**Download:** [aiboostnow.com](https://aiboostnow.com)
**Requires:** Joomla 5.0–6.x · PHP 8.1+

---

## [Unreleased]

> Changes staged for the next release will appear here.

---

## v0.73.15 — 2026-06-05

### Changed
- **Project baseline reconciled for release readiness** — ROADMAP/next-steps tracking now treats `0.73.14` as the current truth, records completed Help, Schema Type, and Admin IA work, and points the next active slice at release-readiness / PHP compatibility.
- **Admin IA duplicate cleanup** — robots.txt, SEO scraper, and AI crawler controls now live only under Crawlers & Robots; canonical URL and 404 monitoring controls now live only under Technical SEO; Sitemap and AEO no longer duplicate those settings.
- **Manifest ownership aligned with the v0.5 IA** — crawler/robots settings are manifest-owned by `crawlers`, canonical/404 settings are manifest-owned by `technical`, and derived Vue partials were regenerated in the matching generated folders.
- **Documentation/product wording cleanup** — priority public docs now use AI Boost for Joomla, one-product license/support wording, `0.73.15`, current admin areas, and the current ownership split for AI Visibility, Crawlers & Robots, and Technical SEO.

### Verified
- Codacy CLI analysis passed for modified Vue/PHP/Python files; only pre-existing size/complexity advisory warnings remain in large utility/test files.
- `python scripts/codegen-from-manifest.py --check` passed with existing advisory Health gaps only.
- `php vendor/bin/phpunit --configuration phpunit.xml --filter SettingsSaveDefinitionTest --testdox` passed (`18 tests`, `349 assertions`).
- `pnpm run build` passed in `component/com_aiboost/vue-admin` and rebuilt `admin-vue.js`.
- Targeted docs grep passed for stale product/tier/tab wording in the priority docs; remaining keyword hits are contextual Facebook Debugger and generic agency wording, not Free/Pro or legacy product copy.

---

## v0.73.14 — 2026-06-05

### Changed
- **Schema Type options now feel meaningfully different** — shared fields were narrowed so Price Range, Service Area, Payment Accepted, Amenities, and hours labels only appear where they fit the selected schema type.
- **Type-specific labels for common business fields** — operations and opening-hours sections now use context-aware labels such as Dining Hours, Clinic Hours, Workshop Hours, Guest Payment & Amenities, and Visitor Access & Amenities.

### Verified
- Vue admin build passed after Schema Type UI differentiation.

---

## v0.73.13 — 2026-06-05

### Added
- **Richer Schema Type conditional options** — selecting business, service, Person, or News/Media schema types now reveals more relevant fields instead of only generic hints.
- **Additional Schema.org outputs** — the schema builder can now emit lodging cuisine and pets policy, payment methods, amenity features, expanded service/area coverage, Person job title/affiliation/expertise, and NewsMediaOrganization masthead, ethics policy, and founding date.

### Verified
- Focused PHPUnit passed for schema builder and settings-save coverage (`32 tests`). Vue admin build passed and manifest codegen guard confirmed all generated artifacts are present.

---

## v0.73.12 — 2026-06-05

### Changed
- **Help page rebuilt as a troubleshooting hub** — Help now guides admins by situation, common problem, launch validation area, and support request preparation instead of presenting another feature list.
- **Help links aligned with the v0.5 admin IA** — internal actions now point to the current SPA routes for Health, Analyzer, Autopilot, Import, Schema.org, Sitemap, Crawlers & Robots, Social Meta, Analytics & Tracking, AI Visibility, and URL Checker.

### Added
- **Copyable support request template** — admins can copy a structured support checklist with site URL, tested frontend URL, version details, Health result, Analyzer result, active cache/SEO/page-builder plugins, and recent changes.

### Verified
- Built `pkg_aiboost-0.73.12.zip`, installed it on Joomla 5 Pro staging, passed smoke matrix `20/20`, settings save, and Playwright Help page QA with no console issues.

---

## v0.73.11 — 2026-06-05

### Added
- **One-product admin experience for v0.5** — the admin UI now presents AI Boost for Joomla as a single commercial package instead of exposing visible Free/Pro locks, badges, or per-feature upgrade prompts.
- **Autopilot setup checklist** — new guided setup route for Site Identity, Schema.org core, Sitemap, and Social Meta readiness.
- **Expanded Schema.org business types** — Schema Type now supports Restaurant, AutomotiveBusiness, Store, TouristAttraction, ProfessionalService, Person, NewsMediaOrganization, and legacy aliases consistently across UI and backend JSON-LD output.

### Changed
- **Admin navigation reorganized for v0.5** — settings are grouped around Setup, SEO, AI Visibility, Tools, and Advanced areas, with backward-compatible route aliases for older links.
- **Schema.org output normalized** — the shipped schema plugin now emits selected business/person Schema.org types directly from the core builder and includes relevant business details such as cuisine, availableService, areaServed, priceRange, rating, and opening hours.
- **Integrations page simplified** — first-party integration cards now focus on supported bridge targets; unsupported legacy integration buttons were removed from the active admin flow.
- **Legacy split-package wording cleaned up** — schema code comments now describe the current one-product model while preserving compatibility shims for historical installs.

### Fixed
- **Installer cleanup of old split-package rows** — upgrades now remove stale Joomla Extension Manager entries from the previous split Free/Pro package layout, including legacy `*_PRO` plugins, `pkg_aiboost_pro`, `aiboost_int_falang`, and `AI Boost Health` rows.
- **Schema Type backend mismatch** — backend schema generation now accepts both Schema.org values saved by the current UI and older lowercase legacy keys.
- **Health/module leftovers after upgrade** — installer cleanup verifies whether Joomla uninstall actually removed old rows and deletes orphan extension/schema records when needed.

### Verified
- Focused Joomla 5 Pro QA passed: package install, smoke matrix `20/20`, settings save, Schema tab Playwright QA, JSON-LD output for Restaurant / AutomotiveBusiness / Person, stale extension row check, and clean uninstall / upgrade preservation verifier.
- Build pipeline passed: manifest codegen guard, Vue admin bundle build, package assembly, strict Pro-leakage verifier, PHP syntax checks, and focused PHPUnit schema tests (`44 tests`, `348 assertions`).

---

## v0.27.1 — 2026-05-12

### Fixed
- **Tab labels in other plugins no longer corrupted** — `onContentPrepareForm` now guards against running multilang field injection, field prefix registration, and JS/CSS asset loading for any plugin edit form that is *not* AI Boost for Joomla. Previously, opening the edit form of any other system plugin (e.g. System – Cache, System – Debug, System – SEF) caused their tab labels to display as raw language constants (e.g. `PLG_SYSTEM_CACHE_TAB_CACHING`) instead of translated text. The fix checks `$data->element` (object, Joomla 4/5 standard), `$data['element']` (array, legacy), and falls back to the URL `element` parameter — covering all Joomla 4–6 variants. All other plugin forms now exit immediately; only the `joomlaboost` element proceeds to injection.

---

## v0.27.0 — 2026-05-11

### Added
- **Pro feature gating for 8 specialized Site Types** — MedicalClinic, LegalService, EducationalOrganization, HealthClub, Dentist, RealEstateAgent, Person, and NewsMediaOrganization are now restricted to Developer and Agency license tiers.
- **Pro feature gating for Advanced Business Hours** — Full per-day schedule customization is now a Developer/Agency feature.
- **Pro upgrade notices** — Free-tier users see a clear in-admin message explaining which plan unlocks each Pro feature, with a direct link to the pricing page.
- **Developer bypass toggle** — A Debug tab option lets developers preview all Pro features without a license key (for local development and testing only).

### Changed
- Free tier retains the 5 general-purpose Site Types: LocalBusiness, Restaurant, Hotel, ECommerce Store, and Generic (fallback).
- Feature gating silently skips Pro output rather than showing errors — no impact on site visitors.

---

## v0.26.0 — 2026-05-07

### Added
- **Compact Business Hours widget** — New 7-row table UI replaces the previous freeform hours input. Each day has its own open/close time fields.
- **"All same hours" toggle** — A single switch lets users apply one schedule to all 7 days at once, or switch to individual per-day configuration.
- Mobile-optimized layout for the Business Hours table in the Joomla admin panel.

### Changed
- Business hours display in Schema.org output improved for clarity and spacing.

---

## v0.25.1 — 2026-05-07

### Added
- Custom field class support added for improved layout control in the admin panel.

### Fixed
- Confirmed compatibility with Joomla 6.1 staging environment — all 13 Site Type presets produce valid Schema.org output (13/13 ✅).
- Fixed an error that occurred when displaying a weekly schedule via custom field rendering.

---

## v0.25.0 — 2026-05-07

### Added
- **License activation panel** — The Plugin tab now shows active license details (tier, domain count, expiry) once a key has been verified.
- Build tooling: automated ZIP generation script and version bump script for consistent releases.

### Changed
- Branding updated throughout admin UI: all references changed to **AI Boost for Joomla**.

---

## v0.24.0 — 2026-05-04

### Added
- **8 new specialized Site Type presets:**
  - MedicalClinic — structured data for healthcare providers
  - LegalService (Lawyer/Law Firm) — Schema.org LegalService type
  - EducationalOrganization — for schools, academies, and online courses
  - HealthClub (Gym/Fitness) — with amenity and membership fields
  - Dentist — dental practice schema with specialty fields
  - RealEstateAgent — agency and listing schema
  - Person (Portfolio) — for personal brands, freelancers, and creatives
  - NewsMediaOrganization — for news sites and digital publications
- **Advanced Business Hours system** — Full `OpeningHoursSpecification` support: per-department schedules, holiday overrides, seasonal hours.
- **Complete 11-language internationalization** — 319 string constants extracted to `JOOMLABOOST_*` keys across 22 `.ini` files. Supported languages: English (primary), Serbian, German, Spanish, French, Italian, Russian, Portuguese (BR), Chinese, Arabic, Japanese.
- **License key field** — New custom field in the Plugin tab for entering and activating Gumroad license keys. All 11 language files include key labels and status messages.

### Changed
- Admin UI strings are now fully translatable — no hardcoded English in the interface.
- Site Type selector reorganized: 5 general-purpose types remain free; specialized types are clearly labeled.

---

## v0.23.0 — 2026-04-29

### Added
- LiteSpeed server bypass for dynamically generated files (`robots.txt`, `llms.txt`, XML sitemap) to prevent static caching of these routes.
- IndexNow ping reliability improvements — key file now served correctly through LiteSpeed environments.

### Fixed
- XML manifest parsing errors on certain Joomla installations.
- Plugin installation failure caused by manifest file path mismatch.
- Duplicate phone field in Organization tab.
- Dynamic language detection now correctly falls back to `en-GB`.

### Changed
- Admin panel descriptions shortened for better readability across all 7 tabs.
- Multilingual fields moved under "Advanced Options" collapsible section to reduce visual clutter.
- Plugin interface reorganized to group related fields more clearly.

---

## v0.22.0 — 2026-04-29

### Added
- **llms.txt generation** — Automatically serves `/llms.txt` describing the site's content for AI crawlers (ChatGPT, Perplexity, Claude, Gemini).
- **Hreflang support** — Multi-language XML sitemap with `<xhtml:link>` alternates for all active Joomla languages.
- **Schema.org Wizard** — Guided setup for selecting the right schema type for any site.
- **FAQ auto-detect** — Automatically detects FAQ-style content in Joomla articles and generates `FAQPage` structured data.
- **Performance module** — DNS prefetch, preconnect, resource hints, and optional lazy-load image injection.
- **Manual FAQ builder** — Add custom Q&A pairs directly in the plugin admin (Developer/Agency tier).
- **Events schema** — Schema.org `Event` structured data with date, location, organizer, and offers fields (Developer/Agency tier).
- **IndexNow integration** — Instant URL submission to Bing and Yandex on article publish/update (Developer/Agency tier).
- **Google Analytics 4 + GTM** — Head injection for GA4 measurement ID and Google Tag Manager container.
- **Meta Pixel** — Facebook/Instagram pixel injection with standard event support.
- **Google Search Console verification** — Meta tag injection for GSC property verification.

### Changed
- Plugin restructured into 7 focused admin tabs: Plugin, Organization, Schema.org, Sitemap, Social & Meta, Analytics, Debug.
- Feature gating introduced: advanced features (Manual FAQ, Events, IndexNow, llms.txt) restricted to Developer and Agency tiers.

---

## v0.21.0 and earlier

Early development versions (April 2026). Core plugin scaffold including basic Schema.org LocalBusiness output, XML sitemap generation, OpenGraph/Twitter Card meta tags, and robots.txt management. Not publicly released.

---

## System Requirements

| Requirement | Minimum |
|-------------|---------|
| Joomla | 5.0 |
| PHP | 8.1 |
| MySQL | 5.7 / MariaDB 10.4 |
| Joomla Maximum | 6.x (tested on 6.1) |

---

## Licensing

AI Boost for Joomla is now one commercial package. Visible per-feature Free/Pro
locking is retired from the admin experience; historical license and Pro markers
may remain as compatibility shims during the transition.

| Product | Entitlement |
|---------|-------------|
| AI Boost for Joomla | One commercial license unlocks the Joomla package experience |
| Integration bridges | Sold separately when they depend on paid third-party extensions |

Purchase at [aiboostnow.com](https://aiboostnow.com).
