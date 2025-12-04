# Changelog

All notable changes to JoomlaBoost will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned
- Automatic version bumping in build process
- Build artifact management automation

---



## [0.2.16] - 2025-12-04

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
