# JoomlaBoost - Universal SEO & Performance Plugin

## Version 0.1.17

Universal SEO and performance optimization plugin that automatically adapts to any Joomla site and environment.

## 🎯 Key Features

### 🌐 Domain-Agnostic Architecture

- **Auto Domain Detection**: Automatically detects current domain and environment
- **Multi-Environment Support**: Different configurations for production, staging, development
- **Universal Configuration**: Works on any domain without hardcoded settings

### 🔍 SEO Optimization

- **Dynamic Robots.txt**: Environment-aware robots.txt generation
- **XML Sitemaps**: Automatic sitemap generation and indexing
- **Schema Markup**: Structured data for rich snippets
- **OpenGraph Tags**: Social media optimization
- **Hreflang Tags**: Multilingual site support

### ⚡ Performance Features

- **Smart Caching**: Intelligent caching system
- **Optimized Output**: Minimal overhead and fast execution
- **Lazy Loading**: Services loaded only when needed

### 🛠️ Developer-Friendly

- **Service Architecture**: Clean, maintainable code structure
- **Debug Mode**: Comprehensive debugging and logging
- **Extensible Design**: Easy to extend with new features

## 📦 Installation

1. Download the plugin package
2. Install via Joomla Extensions Manager
3. Enable the plugin in System Plugins
4. Configure settings as needed

## ⚙️ Configuration

### Basic Settings

- **Auto Domain Detection**: Enable/disable automatic domain detection
- **Manual Domain**: Specify domain manually if auto-detection is disabled

### SEO Features

- **Robots.txt**: Enable dynamic robots.txt generation
- **XML Sitemap**: Enable sitemap generation
- **Schema Markup**: Enable structured data
- **OpenGraph**: Enable social media tags
- **Hreflang**: Enable multilingual support

### Performance

- **Caching**: Enable/disable caching
- **Cache TTL**: Set cache time-to-live

### Debug & Development

- **Debug Mode**: Enable detailed logging
- **Debug Markers**: Wrap injected content with HTML comments
- **Staging Badge**: Show environment indicator

## 🌍 Environment Detection

The plugin automatically detects the environment based on domain:

- **Production**: Standard domain (e.g., example.com)
- **Staging**: Domains containing "staging" or "stage"
- **Development**: Domains containing "dev" or "test"
- **Local**: localhost or 127.0.0.1

Different configurations apply based on environment:

### Production

- Full SEO features enabled
- Search engines allowed
- Optimized for performance

### Staging/Development

- SEO features limited
- Search engines blocked
- Debug features available
- Staging badge displayed

## 🔗 Endpoints

The plugin provides several endpoints:

- `/robots.txt` - Dynamic robots.txt
- `/sitemap.xml` - XML sitemap index
- `?jb_health=1` - Health check endpoint
- `?jb_diag=1` - Diagnostic information

## 🧪 Testing

Test endpoints on your site:

```bash
# Health check
curl https://yoursite.com/?jb_health=1

# Diagnostics
curl https://yoursite.com/?jb_diag=1

# Robots.txt
curl https://yoursite.com/robots.txt

# Sitemap
curl https://yoursite.com/sitemap.xml
```

## 📝 Changelog

### Version 0.1.17 (current)

- Initial release of universal plugin
- Complete rewrite from legacy plugin
- Domain-agnostic architecture
- Service-oriented design
- Multi-environment support
- Universal configuration system

## 🔄 Migration from legacy plugin

If migrating from the old legacy plugin:

1. Export your current configuration
2. Disable/uninstall the old plugin
3. Install JoomlaBoost
4. Reconfigure settings (settings are not automatically migrated)
5. Test all functionality

## 🛠️ Development

### Service Architecture

```php
JoomlaBoost\Plugin\System\JoomlaBoost\Services\
├── ServiceInterface.php          # Base interface
├── AbstractService.php           # Base service with domain detection
├── ServiceManager.php            # Service container
├── DomainDetectionService.php    # Domain detection logic
├── RobotService.php             # Robots.txt generation
├── SitemapService.php           # XML sitemap generation
└── ... (other services)
```

### Adding New Services

1. Create service class extending `AbstractService`
2. Implement `getServiceKey()` method
3. Add service to `ServiceManager`
4. Add configuration options to XML manifest

## 📞 Support

- Documentation: See README in this repo and docs/\*
- Issues: <https://github.com/bojancreator/JoomlaBoost/issues>
- Discussions: <https://github.com/bojancreator/JoomlaBoost/discussions>

## 📄 License

GNU General Public License version 2 or later

## 👥 Credits

- **Development Team**: JoomlaBoost Team
- **Original legacy plugin**: OffroadSerbia Team
- **Joomla CMS**: Joomla Community

---

**Version**: 0.1.17  
**Release Date**: September 2025  
**Joomla Compatibility**: 4.0+  
**PHP Compatibility**: 8.0+
