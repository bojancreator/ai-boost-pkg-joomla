# JoomlaBoost — AI Coding Agent Instructions

## Project Context

**JoomlaBoost** is a universal Joomla 4.0+ system plugin for SEO optimization and performance enhancement. It's domain-agnostic and environment-aware, automatically adapting to production, staging, or development environments.

**Core Purpose**: Generate dynamic robots.txt, XML sitemaps, Schema.org/OpenGraph metadata, and analytics integration without hardcoded domain dependencies.

## Communication Guidelines

**Language**: Respond in **Serbian** (srpski).
**Tone**: Professional but conversational with light humor.
**Format**: Use numbered lists (1. 2. 3.) when proposing options or steps.
**Explanations**: When user writes "objasni", explain as if to a beginner.

## Architecture Overview

### Plugin Structure (Service-Oriented Design)

```
plugin/
├── joomlaboost.php              # Main entry point (PlgSystemJoomlaboost)
├── joomlaboost.xml              # Manifest with version/config
└── src/Services/                # All business logic
    ├── ServiceContainer.php     # DI container with lazy loading
    ├── ServiceInterface.php     # Base interface for all services
    ├── AbstractService.php      # Shared service implementation
    ├── DomainDetectionService.php  # Auto-detect domain/environment
    ├── RobotService.php         # Generate robots.txt
    ├── SitemapService.php       # Generate XML sitemaps
    ├── SchemaService.php        # JSON-LD structured data
    ├── OpenGraphService.php     # OG/Twitter Card meta tags
    ├── AnalyticsService.php     # GA4/GTM/Meta Pixel
    └── PerformanceService.php   # Caching & batch processing
```

### Key Joomla Hooks (Event Lifecycle)

1. **`onAfterInitialise`** - Early interceptor for robots/sitemaps/diagnostics (before routing)
2. **`onAfterRoute`** - Fallback interceptor if early hook missed
3. **`onBeforeCompileHead`** - Inject meta tags, OpenGraph, Schema.org, analytics
4. **`onAfterRender`** - Post-process HTML (staging badge, cleanup)
5. **`onBeforeRespond`** - Add HTTP headers (X-Robots-Tag for staging)

### Service Pattern

All services implement `ServiceInterface` with:
- `__construct(CMSApplication $app, Registry $params)` - Dependency injection
- `isEnabled(): bool` - Check plugin config
- `getCurrentDomain(): string` - Auto-detected or manual domain
- `getBaseUrl(): string` - Full URL with protocol

Services are lazy-loaded via `ServiceContainer` and cached per request.

## Critical Endpoints (Test These!)

### robots.txt
- **URL**: `/robots.txt` (or `/index.php?jb_robots=1`)
- **Content-Type**: `text/plain`
- **Caching**: ETag + 304 support
- **Must include**: `Sitemap: https://domain.com/sitemap_index.xml`

### Sitemaps
- **Index**: `/sitemap_index.xml` → `<sitemapindex>` listing pages/articles
- **Pages**: `/sitemap-pages.xml` → Menu items
- **Articles**: `/sitemap-articles.xml` → Published articles
- **Fallback**: `/index.php?jb_sitemap=index|pages|articles`
- **Caching**: ETag + Last-Modified + 304

### Diagnostics
- **URL**: `/index.php?jb_diag=1`
- **Response**: JSON with plugin status, domain config, services state
- **No auth required** (intentional for debugging)

## Development Workflows

### Build & Package

```powershell
# Development build (keeps debug code)
.\tools\build-optimizer.ps1 -Debug -Version "0.1.22"

# Production build (strips comments/debug)
.\tools\build-optimizer.ps1 -Production -Version "0.1.22"

# Universal builder (reads version from XML)
.\tools\build_joomlaboost_smart.ps1
```

**Output**: `tools/__build/joomlaboost-<version>.zip` ready for Joomla installation.

### Testing (Post-Install)

```powershell
# Automated test suite (checks robots/sitemap/meta)
.\tools\quick-test-joomlaboost.ps1

# Manual testing on staging
# https://staging.offroadserbia.com/robots.txt
# https://staging.offroadserbia.com/sitemap_index.xml
# https://staging.offroadserbia.com/index.php?jb_diag=1
```

### Code Quality (Before Commit)

```bash
composer lint        # PHPCS PSR-12 (exclude Joomla namespace warnings)
composer lint-fix    # Auto-fix style issues
composer stan        # PHPStan level 6 static analysis
```

**Config**: `config/phpcs.xml`, `config/phpstan.neon`

### Git Workflow

```powershell
# Quick commit & push (uses VS Code task)
.\tools\git-quick-push.ps1 -Message "feat: add hreflang support"

# CI/CD triggers:
# - Push to main → PHPCS + PHPStan (no auto-deploy)
# - Manual: Actions → "Deploy to Staging" (currently disabled)
```

## Critical Configuration Fields

**Plugin Settings** (Extensions → Plugins → System - JoomlaBoost):

- **`active_domain`** - Domain where plugin is active (supports subdomains)
- **`enable_robots`** - Toggle robots.txt generation
- **`enable_sitemap`** - Toggle sitemap generation
- **`sitemap_use_index`** - Use sitemap_index.xml (true) or single sitemap.xml (false)
- **`ga4_measurement_id`** / **`meta_pixel_id`** - Analytics integration

## Common Issues & Fixes

### Problem: robots.txt returns HTML/404
1. Check `.htaccess` has rewrite rules for `robots.txt`
2. Remove physical `robots.txt` from webroot if it exists
3. Test fallback: `/index.php?jb_robots=1`
4. Verify `active_domain` matches current hostname
5. Check CDN/WAF isn't blocking requests

### Problem: Sitemap returns HTML instead of XML
1. Same `.htaccess` rules for `sitemap*.xml`
2. Test non-SEF fallback: `/index.php?jb_sitemap=index`
3. Check diagnostics: `/index.php?jb_diag=1` → `active_match` should be `1`

### Problem: Service not loading
1. Check `ServiceContainer` has service in `$serviceMap`
2. Verify `ServiceAutoloader` registers class paths
3. Ensure service implements `ServiceInterface`
4. Check plugin config enables the service feature

## File Editing Rules

### When modifying services:
1. **Always** maintain strict typing (`declare(strict_types=1)`)
2. **Always** implement `ServiceInterface` for new services
3. **Always** register in `ServiceContainer::$serviceMap`
4. **Run** `composer stan` before committing

### When modifying plugin entry point:
1. **Never** remove `\defined('_JEXEC') or die;` security check
2. **Always** maintain hook method signatures (Joomla contract)
3. **Profile** performance impact (services are lazy-loaded for a reason)

### When updating XML manifest:
1. **Increment** version number (semantic versioning)
2. **Update** `<creationDate>` if releasing
3. **Add** new config fields under `<config><fields>`

## Testing Multi-Domain Setup

Plugin is **universal** - no hardcoded domains. To test:

1. **Production**: Configure `active_domain: example.com` → robots allow crawling
2. **Staging**: Configure `active_domain: staging.example.com` → robots disallow + noindex headers
3. **Local Dev**: Plugin auto-detects `localhost` → development mode features

**Key files**: `DomainDetectionService.php` handles environment logic.

## Documentation

- **Architecture**: `docs/architecture/AI-OVERVIEW.md` (quick reference)
- **Endpoints**: `docs/ENDPOINTS.md` (API specs with examples)
- **Build Process**: `docs/BUILD-SYSTEM-INSTRUCTIONS.md`
- **Troubleshooting**: `docs/TROUBLESHOOTING.md` (404/HTML issues)
- **Changelog**: `docs/RELEASE-NOTES.md`

## Performance Notes

- **Service loading**: 5-8 services loaded on average (vs. 15 always-on before optimization)
- **Build size**: 90.75 KB production ZIP (42% smaller than v0.1.17)
- **Caching**: Request-level service cache prevents duplicate initialization
- **Batch processing**: `PerformanceService::processBatchedMeta()` reduces DOM operations

## Quick Start for New Contributors

1. **Clone repo** → `git clone <repo-url>`
2. **Install deps** → `composer install`
3. **Build plugin** → `.\tools\build_joomlaboost_smart.ps1`
4. **Test locally** → Install ZIP in Joomla 4.x test site
5. **Verify endpoints** → Check `/robots.txt`, `/sitemap_index.xml`, `/index.php?jb_diag=1`
6. **Run QA** → `composer lint && composer stan`

Staging test site: **https://staging.offroadserbia.com**
