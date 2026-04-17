# Changelog

All notable changes to JoomlaBoost will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.21.0] - 2026-04-17

### Added
- **LlmsTxt custom pages multilingual**: `llmstxt_custom_pages` now supports per-language values — AI crawlers get localized page titles and descriptions
- **Version.php**: Single source of truth for plugin version constant, auto-updated by build script
- **Build script archiving**: Old builds automatically moved to `tools/__build/archive/` — only latest ZIP stays in build root

### Changed
- Build script (`_build_zip.ps1`) now auto-syncs `Version.php` with XML version on every build
- Removed static `llmstxt_custom_pages` field from XML (now dynamically injected per language)

---

## [0.20.0] - 2026-04-15

### Added
- **Central multilingual resolver**: `AbstractService::getLocalizedParam()` — unified 4-step resolution chain for all services: `{field}_{currentLang}` → `{field}_{defaultLang}` → `{field}` → DB translations
- **`getCurrentLangCode()`** and **`getDefaultLangCode()`** helper methods in AbstractService
- **OpenGraph per-language fields**: `og_site_name` and `og_image` now support per-language values (e.g., different hero banner or site name per language)
- **Schema Events multilingual**: `schema_events` JSON field now supports per-language event names and descriptions
- **Dynamic `availableLanguage`**: Schema.org ContactPoint now lists all active site languages instead of hardcoded values
- **Falang custom field translation**: `custom_og_title`, `custom_og_description`, `custom_og_image` now check Falang's `#__falang_content` table for translations

### Changed
- **Default language fallback**: All services now use Joomla's configured default language instead of hardcoded `en` — sites with non-English defaults work correctly
- `SchemaService`: removed 50-line private `getLocalizedParam()` — now inherits from `AbstractService`
- `QAManagementService`: replaced 30-line manual resolution with single `getLocalizedParam()` call
- `LlmsTxtService`: `org_name`, `org_description`, `manual_faqs` now use `getLocalizedParam()`
- `TranslationService`: DB fallback uses `getDefaultLangCode()` instead of hardcoded `'en'`
- Removed all real-world data from form hints and defaults (Vivid Blue, Budva, Rezevici, OffRoad Serbia, RS country code, Serbian phone numbers)
- Removed duplicate static `og_site_name`/`og_image` fields from XML (replaced by dynamic per-language injection)
- All geo/phone/address hints changed to generic fictional values

### Fixed
- Schema.org `addressCountry` and `addressLocality` no longer have hardcoded defaults — empty if not configured

---

## [0.8.6] - 2026-03-30

### Fixed
- **Hreflang tags missing on Falang non-default language pages (root cause fix)**: `LanguageService::getFalangLanguages()` used `INNER JOIN #__languages` — on sites with a single Joomla language + Falang overlay, the Montenegrin (ME) language has no matching row in `#__languages`, so the INNER JOIN silently dropped it and the plugin detected only 1 language, triggering the `isMultilingual() = false` early return. Changed to LEFT JOIN and reconstruct the language object from Falang's own `lang_code` field.
- **SEF prefix resolution for Falang languages**: added three-stage fallback: (1) `#__falang_url_configuration.sef` if available, (2) first 2 chars of Falang `lang_code` (e.g. `"me"` from `"me-ME"`), (3) Joomla `#__languages.sef` via LEFT JOIN.

---

## [0.8.5] - 2026-03-30

### Fixed
- **Hreflang incomplete on non-default language pages**: `HreflangService::buildHref()` was losing the trailing slash when rebuilding URLs (`implode('/', $segments)` doesn't preserve it), causing inconsistent URL generation. Fixed by explicitly checking and restoring trailing slash.
- **Hreflang `x-default` missing on ME version**: `generateTags()` now resolves `x-default` in a separate pass through all languages, decoupled from the per-language loop. This ensures `x-default` is always emitted even when the default language entry comes after non-default languages.
- **Language prefix detection**: replaced call to non-existent `isLanguagePrefix()` method with inline `in_array()` check against a pre-built list of known SEF codes (e.g. `['en', 'me']`).

---

## [0.8.3] - 2026-03-13

### Fixed
- **Hotel schema not generated**: `generateOrganizationSchema()` had no `hotel` branch — when `schema_type = 'hotel'` was selected, the code fell through to the generic `Organization` schema. Now generates proper `LodgingBusiness` schema with `starRating`, `checkInTime`, `checkOutTime`, `petsAllowed`, `geo`, `address`, and social `sameAs` links.

---

## [0.8.2] - 2026-03-09

### Fixed
- **Sitemap duplicate root URL**: homepage (`/`) was added twice — once explicitly and again as a menu item. Added URL deduplication in `SitemapService::generateSitemap()` using a `$seenUrls` hash map (normalized without trailing slash). Google now sees each URL exactly once.

---

## [0.8.1] - 2026-03-09

### Fixed
- **Meta Pixel YooTheme consent**: changed category name from `meta_pixel` (underscore) to `meta-pixel` (hyphen) to match YooTheme's default consent category naming — without this fix, Meta Pixel script was not released when user accepted Marketing consent.

### Removed
- Dead code: `addSchemaMarkup()` method removed from `joomlaboost.php` — this method was never called (replaced by `addOptimizedSchemaMarkup()`).

### Documentation
- `ga4_consent_mode` field description updated: clearly explains that YooTheme consent mode requires a one-time setup in YooTheme Customizer → Scripts → Google Analytics (container can stay empty, just needs to be enabled).
- `pixel_consent_mode` field description updated: clearly explains the same one-time YooTheme Customizer setup for Meta Pixel container.
- `gtm_container_id` field description updated: added warning about potential GA4 duplicate tracking if YooTheme also has GA4 configured in its own Customizer integrations.

---

## [0.8.0] - 2026-03-02

### Added
- **FAQ detection via `jb--faq` CSS class**: mark any block element with `class="jb--faq"` and the plugin will extract FAQ Q&A only from that container. Fallback to global FAQ if no container found.
- **YooTheme UIkit Pattern 3 parser**: `el-item > el-title + el-content` — supports Accordion (`a.el-title`), Grid (`h3.el-title`) and List (`div.el-title`) elements inside `jb--faq`.

### Changed
- `LanguageService::getActiveLanguages()` now reads from `#__falang_languages` as primary source on Falang sites (fixes multilingual detection where `#__languages` has only 1 entry).
- `generateWebsiteSchema()` description now uses `$document->getMetaData('description')` (language-aware, Falang-ready) instead of global Joomla config `MetaDesc`.
- `generateBreadcrumbSchema()` now converts internal `index.php?option=...` URLs to SEF URLs via `Route::_()`.

### Fixed
- `HreflangService` now correctly detects Falang languages and injects hreflang tags on multilingual Falang sites.
- `HreflangService` guard now checks both `$headData['links']` (Language Filter) and `$headData['custom']`, skipping only when a full tag set exists.
- `extractFAQFromContent()` XPath context fallback changed from `$dom->documentElement` to `$dom` — fixes el-item detection when processing HTML fragments (extracted `jb--faq` container content).

---














## [0.2.30] - 2025-12-09

### Added
- 

### Changed
- 

### Fixed
- 

---## [0.2.29] - 2025-12-09

### Added
- 

### Changed
- 

### Fixed
- 

---## [0.2.28] - 2025-12-08

### Added
- 

### Changed
- 

### Fixed
- 

---## [0.2.27] - 2025-12-08

### Added
- 

### Changed
- 

### Fixed
- 

---## [0.2.26] - 2025-12-08

### Added
- 

### Changed
- 

### Fixed
- 

---## [0.2.25] - 2025-12-08

### Added
- 

### Changed
- 

### Fixed
- 

---## [0.2.24] - 2025-12-08

### Added
- 

### Changed
- 

### Fixed
- 

---## [0.2.23] - 2025-12-08

### Added
- 

### Changed
- 

### Fixed
- 

---## [0.2.22] - 2025-12-08

### Added
- 

### Changed
- 

### Fixed
- 

---## [0.2.21] - 2025-12-08

### Added
- 

### Changed
- 

### Fixed
- 

---## [0.2.20] - 2025-12-08

### Added
- 

### Changed
- 

### Fixed
- 

---## [0.2.19] - 2025-12-08

### Added
- 

### Changed
- 

### Fixed
- 

---## [0.2.18] - 2025-12-08

### Added
- 

### Changed
- 

### Fixed
- 

---## [0.2.17] - 2025-12-08

### Added
- 

### Changed
- 

### Fixed
- 

---## [0.2.16] - 2025-12-04

### Added
- 

### Changed
- 

### Fixed
- 

---## [0.2.15] - 2025-12-04

### Added
- 

### Changed
- 

### Fixed
- 

---## [0.2.14] - 2025-12-04

### Added
- Build script optimization with automatic backup file exclusion patterns
- Request-level caching for Schema.org generation (reduces DB queries)
- Request-level caching for OpenGraph meta tag generation
- FAQ schema auto-detection from YooTheme Accordion, Bootstrap Collapse, and HTML patterns
- Custom fields support for per-article OpenGraph overrides (`custom_og_image`, `custom_og_title`, `custom_og_description`)
- Batch processing for meta tags (single DOM operation instead of multiple)
- Lazy loading for heavy DB operations (only when needed)

### Changed
- **Build size reduced from 76KB to 54KB (-29%)**
- **Package file count reduced from 31 to 26 files**
- Service files reduced from 19 to 17 (removed legacy backups)
- Improved OpenGraph image extraction with priority: Custom Fields → Featured Image → Content extraction

### Fixed
- Build script now excludes backup files (`*.backup`, `*_OLD.php`, `*.v0.*`)
- Removed duplicate meta tag generation
- Fixed image URL normalization for social media validators

### Removed
- `AllServices.php` - empty placeholder file
- `CustomFieldsService_OLD.php` - old backup version
- All `.backup` files from production builds

---

## [0.2.13] - 2025-12-01

### Added
- Dynamic versioning in `script.php`
- Automatic build timestamp injection
- `getPluginBuildDate()` method with proper path resolution

### Fixed
- Build date display in plugin information
- `JPATH_PLUGINS` path resolution

---

## [0.2.12] - 2025-11-30

### Added
- Schema.org LocalBusiness markup support
- Hotel schema with check-in/check-out times
- Geographic coordinates for LocalBusiness

---

## [0.2.0 - 0.2.11] - Initial Development

### Added
- Core plugin architecture with service-oriented design
- Schema.org support (Organization, Article, WebSite, Blog, Breadcrumbs)
- OpenGraph meta tags generation
- Google Analytics 4 (GA4) integration
- Google Tag Manager (GTM) integration
- Meta Pixel (Facebook Pixel) integration
- Performance service with request-level caching
- Robots.txt generation
- XML sitemap generation
- Hreflang support for multilingual sites
- Environment detection (production, staging, development)
- Domain auto-detection
- Service autoloader with PSR-4 compliance
- Debug mode with HTML comment markers

### Infrastructure
- PHPStan level 6 code quality
- PHPCS PSR-12 compliance
- GitHub Actions CI/CD pipeline
- Automated testing scripts
- Build system with validation

---

## Project Links

- **Repository**: https://github.com/bojancreator/JoomlaBoost
- **Issues**: https://github.com/bojancreator/JoomlaBoost/issues
- **Documentation**: [docs/](docs/)
