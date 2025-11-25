# JoomlaBoost – Release Notes

## v0.2.0 (2025-11-25) 🎯 CRITICAL FIX - PHP 8.1+ Custom Fields

### 🔥 Major Fix - NULL Deprecation Errors Resolved

**Problem:** PHP 8.1+ deprecation warnings when opening articles without custom field values:
- `json_decode(): Passing null to parameter #1` (Media fields)
- `DOMCdataSection::__construct(): Passing null to parameter #1` (All field types)
- Errors appeared in admin panel with Error Reporting = Maximum

**Root Cause:** Custom fields created WITHOUT `default_value` in `#__fields` table.

**Solution:** Use Joomla's native `default_value` column mechanism - the proper Joomla way!

### ✅ What Changed

**script.php - createField():**
```php
$columns = [
    // ... existing columns ...
    'default_value',  // ✅ ADDED - was missing!
    // ...
];

$defaultValue = ($type === 'media') 
    ? '{"imagefile":""}' 
    : '';

$values = [
    // ...
    $db->quote($defaultValue),  // 🎯 Sets default in Joomla
    // ...
];
```

**script.php - updateFieldsDisplayParam():**
```php
// Only update if currently empty - preserves user customization!
if (empty($field->default_value)) {
    $updateQuery->set('default_value = ' . $db->quote($defaultValue));
}
```

### 🎯 Why This is THE Solution

✅ **Joomla Native** - Uses built-in field defaults (no hacks!)  
✅ **Zero Runtime Cost** - No database triggers, no event hooks  
✅ **Admin Visible** - Editable in Content → Fields → Edit  
✅ **Automatic** - New articles inherit defaults  
✅ **Safe Upgrades** - Preserves manual customizations  
✅ **Simple** - 3 lines of code vs 100+ lines of triggers  

### 📦 Installation & Upgrade

**From ANY v0.1.x:**
1. Upload `joomlaboost-0.2.0.zip`
2. Extensions → Manage → Install
3. Auto-upgrades existing plugin
4. No configuration needed!

**Verification:**
```sql
SELECT name, type, default_value 
FROM #__fields 
WHERE name LIKE 'custom_og_%';
```

Expected:
- `custom_og_image` → `{"imagefile":""}`
- `custom_og_title` → `` (empty string)
- `custom_og_description` → `` (empty string)

**Test:**
1. Content → New Article
2. Leave custom fields empty
3. Save & Close → Reopen
4. Expected: NO errors! ✅

### 🧠 Lessons Learned (17 Iterations!)

- v0.1.91-97: Tried `fieldparams` workarounds ❌
- v0.1.98: Access Level = 3 (frontend only) ⚠️
- v0.1.100-103: SQL normalization + Fields plugin ⚠️
- v0.1.104-107: Database triggers (overkill!) ❌
- **v0.2.0: Joomla `default_value` column** ✅

**The Problem:** We were looking at `fieldparams.imagefile` but ignoring the obvious `default_value` field in admin UI! 🤦

### 📖 Full Documentation

See `docs/CUSTOM-FIELDS-PHP81-ISSUE.md` for:
- Complete debugging timeline
- All 17 attempts documented
- Root cause analysis
- Implementation details

### 🏗️ Architecture (Final Stack)

**Layer 1:** Joomla Default Value (v0.2.0) ⚡ **PRIMARY**  
**Layer 2:** Access Level = 3 (v0.1.98) - Guest protection  
**Layer 3:** SQL Normalization (v0.1.100) - Historical cleanup  
**Layer 4:** Fields Plugin (v0.1.103) - Optional fallback  

---

## v0.1.56 — 2025-12-XX 🤖 AI Crawler Optimization

### ✨ Major Features

#### **Canonical URL Support**

- Added automatic canonical URL generation for all pages
- Removes tracking parameters (utm\_\*, fbclid, gclid, \_ga) from canonical URLs
- Handles article URLs with proper `?id=X` parameter inclusion
- Prevents duplicate content indexing by AI crawlers and search engines
- New methods: `addCanonicalUrl()`, `cleanUrl()` in main plugin file

#### **Enhanced Article Schema for AI**

- Added `wordCount` calculation to Article Schema (helps AI estimate reading time)
- Added `mainEntityOfPage` structure (explicit page identification)
- Enhanced `author` with real user names from #\_\_users table (via `created_by` lookup)
- Added `publisher` with organization logo as ImageObject
- New methods: `getAuthorSchema()`, `getPublisherSchema()` in SchemaService
- SQL updated for Joomla 4+ compatibility (uses `state` column, LEFT JOIN #\_\_users)

#### **Author Metadata in OpenGraph**

- Added `article:author` OpenGraph meta tag for proper attribution
- Queries #\_\_users table for real author names
- Priority: `created_by_alias` → real user name → 'Author' fallback
- Combined with timestamps in unified `addArticleMetadata()` method
- Improves content credibility for social sharing and AI indexing

### 🎯 AI Crawler Benefits

These changes ensure AI assistants (ChatGPT, Claude, Perplexity) can properly:

- Identify canonical URLs (no duplicate content confusion)
- Understand article structure (word count, main entity, author details)
- Attribute content correctly (author names, publisher info)
- Index content efficiently (structured data + OpenGraph metadata)

### 🔧 Technical Changes

- **Main Plugin** (joomlaboost.php):

  - Lines 624-670: New `addCanonicalUrl()` method
  - Lines 672-699: New `cleanUrl()` helper for tracking parameter removal
  - Line 156: Canonical URL added as first operation in `onBeforeCompileHead`

- **SchemaService.php**:

  - Lines 377-433: Enhanced `generateArticleSchema()` with AI-friendly fields
  - Lines 999-1027: New `getAuthorSchema()` method (queries #\_\_users)
  - Lines 1029-1062: New `getPublisherSchema()` method (adds logo)

- **OpenGraphService.php**:
  - Lines 288-320: Renamed `addArticleTimestamps()` → `addArticleMetadata()`
  - Lines 403-443: New `getArticleMetadata()` method (timestamps + author)
  - Line 254: Updated call to pass `$articleId` parameter

### 📦 Build Info

- Build size: 297.33 KB
- Services: 17
- Files: 27

---

## v0.1.24 — 2025-11-19 🎯 Complete Service Architecture

### ✨ Major Features

#### **Complete Service Architecture Implementation**

- All 17 services now included in build directory
- Service-oriented architecture with lazy loading and dependency injection
- Request-level service caching for optimal performance
- Fixed service dependency resolution and container management

#### **Core Services Added**

- `AnalyticsService` - GA4, GTM, Meta Pixel integration foundation
- `HealthService` - Plugin health monitoring
- `HreflangService` - Multi-language support
- `InjectionService` - Dynamic content injection
- `RobotService` - Enhanced robots.txt generation
- `ServiceManager` - Central service management
- `DomainDetectionService` - Universal domain detection
- `SitemapService` - XML sitemap generation with sitemap_index support

### 🔧 Code Quality Improvements

- **PSR-12 Compliance**: Fixed indentation in all service files (4-space standard)
- **Consistent Formatting**: Cleaned up whitespace in AbstractService, ServiceContainer, ServiceAutoloader
- **Type Safety**: Maintained strict typing throughout (`declare(strict_types=1)`)
- **PHPStan Level 6**: No new static analysis issues

### 📚 Documentation Added

- **AI-SEARCH-OPTIMIZATION-STRATEGY.md**: Comprehensive guide for AI search visibility (ChatGPT, Perplexity, Google AI)
- **FAQ-SCHEMA-GUIDE.md**: Complete FAQ Schema implementation guide with best practices
- **quick-faq-test.php**: Standalone testing tool for FAQ extraction patterns

### 🚀 Performance & Architecture

- **Build Size**: 35.8 KB (optimized, 42% smaller than v0.1.17)
- **Service Loading**: Lazy loading reduces memory footprint
- **Caching**: Request-level cache prevents duplicate service initialization
- **Memory Efficient**: Service container with smart dependency resolution

### 🐛 Bug Fixes

- Fixed service autoloader class map (all 17 services registered)
- Resolved DomainDetectionService class not found error
- Fixed indentation inconsistencies across service files
- Corrected service dependency injection chain

### 🎯 Technical Details

**Services Architecture**:

```
ServiceContainer (DI + Lazy Loading)
├── DomainDetectionService (domain detection)
├── PerformanceService (caching, batch processing)
├── RobotService (robots.txt)
├── SitemapService (XML sitemaps)
├── SchemaService (Schema.org JSON-LD)
├── OpenGraphService (OG + Twitter Cards)
├── AnalyticsService (GA4/GTM/Meta Pixel)
├── HreflangService (multi-language)
├── InjectionService (dynamic injection)
├── HealthService (monitoring)
└── AllServices (placeholder)
```

**Service Dependencies**:

- `schema` → requires `performance`, `domainDetection`
- `openGraph` → requires `performance`, `domainDetection`
- `sitemap` → requires `domainDetection`
- `analytics` → requires `domainDetection`
- `hreflang` → requires `domainDetection`

### 📦 Installation Notes

- **Compatibility**: Joomla 4.0+, PHP 8.1+
- **Upgrade Path**: Clean install recommended (uninstall previous version first)
- **Configuration**: All settings preserved, review plugin parameters after installation
- **Testing**: Verify endpoints after installation:
  - `/robots.txt` - Dynamic robots.txt
  - `/sitemap_index.xml` - Sitemap index
  - `/sitemap-pages.xml` - Pages sitemap
  - `/sitemap-articles.xml` - Articles sitemap
  - `/index.php?jb_diag=1` - Diagnostic endpoint

### 🔮 What's Next (v0.1.25+)

- OpenGraph enhancement (og:image, og:site_name configuration)
- Breadcrumb Schema implementation
- HowTo Schema for step-by-step content
- Review/Rating Schema
- Event Schema
- VideoObject Schema (YouTube integration)
- Enhanced Analytics integration testing
- Admin dashboard for SEO metrics

### 🙏 Contributors

- Bojan Živković (@bojancreator) - Lead Developer
- GitHub Copilot - AI-assisted development

---

## 1.8.5 — 2025-09-01

## 1.8.6 — Router fix for path endpoints

- Router now derives the requested path from REQUEST_URI (pre-rewrite) which fixes 404 responses for /robots.txt and /sitemap\*.xml on stacks that rewrite to /index.php.
- Bumped manifest and plugin version.

## 1.8.4 — 2025-09-01

- Diagnostics endpoint (`/jb-diag`) now responds regardless of `active_domain` to simplify staging/host debugging. It still reports `active_match` flag for visibility.
- Bumped internal version and manifest; rebuilt package.

## 1.8.3 — 2025-09-01

- Added path-based diagnostics endpoint handling in plugin: `GET /jb-diag` → `text/plain` with host, active domain match, and enable flags.
- Keeps early routing via Router + com_ajax; returns immediately in `onAfterInitialise`.
- Manifest/version bump and packaging.

## 1.8.2 — 2025-09-01

- Router refactor: early path mapping to com_ajax for `/robots.txt`, `/sitemap.xml`, `/sitemap_index.xml`, `/sitemap-pages.xml`, `/sitemap-articles.xml`.
- Fixed stray newline before XML preamble in sitemaps.
- Joomla 4/5 compatibility: PSR-4 namespaces included in manifest; Router typed to `CMSApplication`.
- Docs: AI-OVERVIEW, ENDPOINTS, TROUBLESHOOTING, NEXT-STEPS, updated README.

## 1.8.1 — 2025-09-01

- Lightweight diagnostics via query param `?jb_diag=1` for quick environment checks.

## 1.8.0 — 2025-09-01

- `active_domain` expanded to allow subdomains (wildcard-like match).
- Prepped for full automation of robots/sitemaps within the plugin.

## 1.7.7 — 2025-08-29

- Removed environment auto-detect and scope filters; added optional `active_domain` guard.
- Moved "Disable analytics" to Debug tab (`debug_disable_analytics`).
- Removed extra HTML attributes and "head-top" custom code fields and logic.
- Simplified noindex logic to manual only; kept robust X-Robots-Tag header assertion across phases.
- Bumped internal version and synced language strings (sr/en).

## 1.7.8 — 2025-08-29

- Removed global Debug master switch (no master ON/OFF)
- Removed "Disable analytics in Debug" option
- Removed UI mode (Simple/Advanced) and "Show inline help" (and all help notes)
- Cleaned all `showon` gates referencing removed fields; advanced options always visible
- Synced manifest and language files; plugin version bumped to 1.7.8
- Note: Production sitemap endpoints currently return 404; follow-up in NEXT-STEPS

## 1.7.6 — 2025-08-28

- Expanded sitemap endpoints (hyphen/underscore + query fallback) and caching headers.
- Stronger noindex parity (meta + X-Robots-Tag) with late assertion.
- Packaging and tooling updates.
