# BMAD Method - JoomlaBoost Project

## Project Overview

Universal Joomla plugin za SEO i performance optimizaciju sa AI-driven development workflow.

## Agents & Roles

### 🔧 Joomla Developer (`/joomla-dev`)

**Fokus**: Plugin development, Joomla API, event handling

- PHP 8.1+ development
- Joomla 4/5/6 compatibility
- Plugin architecture design
- Database operations
- Configuration management

**Tipični zadaci**:

```
/joomla-dev Dodaj novi endpoint za sitemap generisanje
/joomla-dev Optimizuj performance meta tag generisanja
/joomla-dev Implementiraj caching za schema.org markup
```

### 🏗️ PHP Architect (`/php-architect`)

**Fokus**: Code structure, performance, best practices

- OOP design patterns
- Namespace organizacija
- Performance optimizacija
- Refactoring strategije

**Tipični zadaci**:

```
/php-architect Refaktorišu strukturu plugin-a za bolje performance
/php-architect Predloži design pattern za service management
/php-architect Optimizuj autoloading strategiju
```

### 🧪 QA Tester (`/qa-tester`)

**Fokus**: Code quality, testing, CI/CD

- PHPStan static analysis
- PHPCS style checking
- Testing strategije
- CI pipeline maintenance

**Tipični zadaci**:

```
/qa-tester Analiziraj kod sa PHPStan level 7
/qa-tester Postavi unit testove za novi service
/qa-tester Optimizuj CI workflow za brže buildove
```

### 🎯 SEO Specialist (`/seo-specialist`)

**Fokus**: SEO optimization, performance monitoring

- Schema.org implementation
- OpenGraph meta tags
- Core Web Vitals
- Crawling optimization

**Tipični zadaci**:

```
/seo-specialist Implementiraj Event schema za Joomla članke
/seo-specialist Optimizuj OpenGraph image handling
/seo-specialist Analiziraj Core Web Vitals impact
```

## Workflow Commands

### Development

```bash
# Analiza koda
/qa-tester Pokreni full code analysis (PHPStan + PHPCS)

# Build i pakovanje
/joomla-dev Izgradi plugin ZIP sa verzijskim označavanjem

# Testing
/qa-tester Testiraj plugin instalaciju na Joomla 5
```

### Quality Assurance

```bash
# Pre-commit checks
composer run lint && composer run stan

# Build test
bash tools/build.sh

# Manual install test
# Upload build/*.zip kroz Joomla admin
```

### Release

```bash
# Version bump
/php-architect Ažuriraj verziju u Version.php

# Release prep
/qa-tester Pripremi release notes i changelog

# Package creation
/joomla-dev Kreiraj production-ready ZIP pakete
```

## Project Structure

```
src/plugins/system/joomlaboost/     # Main plugin kod
├── src/                            # Namespace classes
├── joomlaboost.php                 # Plugin entry point
├── joomlaboost.xml                 # Manifest
└── language/                       # Translations

tools/                              # Build scripts
├── build.sh                       # Universal build (zip/tar/pwsh)
├── build_joomlaboost_*.ps1         # PowerShell variants
└── diagnostics/                    # Test utilities

.github/workflows/                  # CI/CD
├── ci.yml                          # QA checks
├── build-zip.yml                   # Manual build
└── release.yml                     # Tag-based release
```

## Standards

- **PHP**: >=8.1, PSR-12, PHPStan level 7
- **Joomla**: 4.0+ compatibility
- **Namespace**: JoomlaBoost\\Plugin\\System\\JoomlaBoost
- **Versioning**: Semantic (0.1.17 trenutno)

## Quick Start

1. `composer install` - Dependencies
2. `composer run lint` - Style check
3. `composer run stan` - Static analysis
4. `bash tools/build.sh` - Build plugin
5. Upload `build/*.zip` u Joomla admin

## AI Development Tips

- Koristi agent prefixe za jasne uloge
- Kombinu različite agente za complex tasks
- QA agent uvek pre commit-a
- SEO specialist za performance optimizacije
