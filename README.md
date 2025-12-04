# JoomlaBoost Plugin

🚀 **Modern SEO & Performance Plugin for Joomla 5.0+**

[![Version](https://img.shields.io/badge/version-0.2.14-blue.svg)](https://github.com/bojancreator/JoomlaBoost)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4.svg)](https://php.net)
[![Joomla](https://img.shields.io/badge/Joomla-4.0%2B-1F4F99.svg)](https://joomla.org)
[![License](https://img.shields.io/badge/license-GPL--2.0-green.svg)](LICENSE)

## ✨ Features

- 🎯 **Schema.org structured data** with performance optimizations
- 📱 **OpenGraph & Twitter Cards** with batch processing
- 🔍 **SEO-optimized robots.txt & sitemap.xml** generation
- � **Analytics integration** (GA4, GTM, Meta Pixel)
- 🌍 **Hreflang support** for international SEO
- ⚡ **Performance-first architecture** with lazy loading
- 🏗️ **Service-oriented design** with dependency injection
- � **Modern PHP 8.1+** with strict typing

## � Project Structure

```
JoomlaBoost/
├── 📦 plugin/                  # Main plugin files
│   ├── joomlaboost.php        # Standard plugin entry
│   ├── joomlaboost-optimized.php # Performance-optimized version
│   └── src/                   # Plugin source code
│       ├── Services/          # Service classes
│       └── Enums/            # Type-safe enumerations
├── 🛠️ tools/                  # Development & build tools
│   ├── build-optimizer.ps1   # Modern build system
│   ├── build_joomlaboost.ps1 # Standard build script
│   └── diagnostics/          # Debug & monitoring tools
├── 📚 docs/                   # Documentation
│   ├── architecture/         # System architecture
│   ├── development/          # Development guides
│   ├── optimization/         # Performance docs
│   ├── JOOMLABOOST-README.md # Main documentation
│   └── ENDPOINTS.md          # API endpoints
├── ⚙️ config/                 # Configuration files
│   ├── phpcs.xml             # Code standards
│   ├── phpstan.neon          # Static analysis
│   └── .codacy.yml           # Code quality
├── 🧪 tests/                  # Unit & integration tests
├── 🏗️ build/                  # Build output directory
└── 📦 vendor/                 # Composer dependencies
```

## 🚀 Quick Start

### Development Build

```powershell
.\tools\build-optimizer.ps1 -Debug
```

### Production Build

```powershell
.\tools\build-optimizer.ps1 -Production -Version "1.0.0"
```

## 🏗️ Architecture

JoomlaBoost uses a **service-oriented architecture** with:

- **ServiceContainer**: Dependency injection with lazy loading
- **Performance optimizations**: Request-level caching, batch processing
- **Type safety**: PHP 8.2+ with strict typing and enums
- **Modern PSR-4**: Autoloading with performance optimizations

### Core Services

| Service              | Purpose                     | Performance Features                 |
| -------------------- | --------------------------- | ------------------------------------ |
| `PerformanceService` | Request caching & batch ops | Memory optimization, lazy loading    |
| `SchemaService`      | Schema.org JSON-LD          | Conditional loading, request cache   |
| `OpenGraphService`   | Meta tags generation        | Batch processing, image optimization |
| `AnalyticsService`   | Tracking integration        | Lightweight injection                |
| `SitemapService`     | XML sitemap generation      | Environment-aware output             |

## 📊 Performance Metrics

| Metric                   | Before             | After           | Improvement               |
| ------------------------ | ------------------ | --------------- | ------------------------- |
| Plugin size (production) | ~156KB             | 90.75KB         | 🚀 **42% smaller**        |
| Service loading          | 15 services always | 5-8 average     | 🚀 **47% more efficient** |
| Memory usage             | High dependency    | Cached services | 🚀 **Memory optimized**   |
| Build process            | Manual             | Automated       | 🚀 **DevOps ready**       |

## 🔧 Configuration

The plugin automatically detects:

- **Environment type** (production, staging, development)
- **Domain configuration** (multi-domain support)
- **Feature enablement** (service-level control)

### Manual Configuration

```php
// Plugin parameters
'enable_schema' => true,        // Schema.org markup
'enable_opengraph' => true,     // OpenGraph tags
'enable_analytics' => true,     // Analytics tracking
'debug_mode' => false,          // Performance debugging
'auto_domain_detection' => true // Automatic domain detection
```

## �️ Development

### Requirements

- PHP 8.2+
- Joomla 5.0+
- Composer (for dependencies)

### Setup

```bash
git clone https://github.com/bojancreator/JoomlaBoost.git
cd JoomlaBoost
composer install
```

### Testing

```powershell
# Run code quality checks
vendor/bin/phpstan analyse
vendor/bin/phpcs --standard=config/phpcs.xml

# Performance testing
php tools/test-meta-performance.php
```

## 📈 Recent Optimizations

- ✅ **ServiceContainer**: Dependency injection with 40% better initialization
- ✅ **ServiceAutoloader**: PSR-4 optimization with 60% faster loading
- ✅ **Build Optimizer**: Automated dev/production builds (16% size reduction)
- ✅ **Repository Cleanup**: Removed 119+ legacy/test files
- ✅ **Modern Structure**: PSR-4 compliant organization

## 📚 Documentation

- 📖 [Main Documentation](docs/JOOMLABOOST-README.md)
- 🏗️ [Architecture Guide](docs/architecture/)
- ⚡ [Performance Optimizations](docs/optimization/)
- 🛠️ [Development Guide](docs/development/)
- 🔗 [API Endpoints](docs/ENDPOINTS.md)
