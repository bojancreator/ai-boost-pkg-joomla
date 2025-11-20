<?php

/**
 * JoomlaBoost Plugin - Optimized Performance Edition
 * @version     1.0.0
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Application\CMSApplication;

// Register optimized autoloader
require_once __DIR__ . '/src/Services/ServiceAutoloader.php';
JoomlaBoost\Plugin\System\JoomlaBoost\Services\ServiceAutoloader::register(__DIR__ . '/src/Services/');

// Load core services immediately for better performance
JoomlaBoost\Plugin\System\JoomlaBoost\Services\ServiceAutoloader::loadCoreServices();

use JoomlaBoost\Plugin\System\JoomlaBoost\Services\ServiceContainer;
use JoomlaBoost\Plugin\System\JoomlaBoost\Services\PerformanceService;
use JoomlaBoost\Plugin\System\JoomlaBoost\Services\OpenGraphService;
use JoomlaBoost\Plugin\System\JoomlaBoost\Services\SchemaService;
use JoomlaBoost\Plugin\System\JoomlaBoost\Services\MetaPixelService;

/**
 * JoomlaBoost plugin - Performance Optimized Architecture
 */
class PlgSystemJoomlaboost extends CMSPlugin
{
    /** @var ServiceContainer|null */
    private ?ServiceContainer $serviceContainer = null;

    /** @var PerformanceService|null */
    private ?PerformanceService $performanceService = null;

    /** @var OpenGraphService|null */
    private ?OpenGraphService $openGraphService = null;

    /** @var SchemaService|null */
    private ?SchemaService $schemaService = null;

    /** @var MetaPixelService|null */
    private ?MetaPixelService $metaPixelService = null;

    /**
     * Constructor
     *
     * @param mixed $subject The object to observe
     * @param array<string, mixed> $config Configuration array
     */
    public function __construct($subject, array $config = [])
    {
        // Set autoload language before parent constructor
        if (!isset($config['autoloadLanguage'])) {
            $config['autoloadLanguage'] = true;
        }

        parent::__construct($subject, $config);
    }

    /**
     * Get service container with lazy initialization
     */
    private function getServiceContainer(): ServiceContainer
    {
        if ($this->serviceContainer === null) {
            $app = $this->getApp();
            if ($app) {
                $this->serviceContainer = new ServiceContainer($app, $this->params);
            }
        }

        return $this->serviceContainer;
    }

    /**
     * Safe application access
     */
    private function getApp(): ?CMSApplication
    {
        try {
            return Factory::getApplication();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Early request hook (check robots/sitemap)
     */
    public function onAfterInitialise(): void
    {
        $app = $this->getApp();
        if (!$app) {
            return;
        }

        if (!$app->isClient('site')) {
            // Optional: debug in backend
            if ($app->isClient('administrator')) {
                $this->logDebug('JoomlaBoost initialised (admin)');
            }
            return;
        }

        // Diagnostic endpoint handling
        if ($this->isDiagnosticRequest()) {
            $this->handleDiagnosticRequest($app);
            return;
        }

        // robots.txt handling
        if ($this->isRobotsRequest()) {
            $this->handleRobotsRequest($app);
            return;
        }

        // sitemap.xml handling
        if ($this->isSitemapRequest()) {
            $this->handleSitemapRequest($app);
            return;
        }
    }

    /**
     * Modify head (schema, verification, analytics, OpenGraph) - OPTIMIZED VERSION
     */
    public function onBeforeCompileHead(): void
    {
        $app = $this->getApp();
        if (!$app || !$app->isClient('site')) {
            return;
        }

        $document = $app->getDocument();
        if (!$document instanceof HtmlDocument) {
            return;
        }

        // Initialize performance service for request-level optimizations
        if ($this->performanceService === null) {
            $this->performanceService = new PerformanceService($app, $this->params);
        }

        // Start performance measurement if debug mode is enabled
        $startTime = $this->params->get('debug_mode', 0) ? microtime(true) : 0;

        try {
            // Lightweight operations first (no DB queries)
            $this->addGoogleVerificationTags($document);
            $this->addMetaPixel($document);

            // Add OpenGraph tags with performance optimizations
            $this->addOptimizedOpenGraphTags($document);

            // Add Schema markup with performance optimizations
            $this->addOptimizedSchemaMarkup($document);

            // Process all batched meta tags in single DOM operation
            $processed = $this->performanceService->processBatchedMeta($document);

            // Log performance metrics if debug enabled
            if ($this->params->get('debug_mode', 0) && $startTime > 0) {
                $duration = round((microtime(true) - $startTime) * 1000, 2);
                $metrics = $this->performanceService->getPerformanceMetrics();
                $this->logDebug("Head compilation completed in {$duration}ms", [
                    'processed_meta_tags' => $processed,
                    'performance_metrics' => $metrics
                ]);
            }
        } catch (\Throwable $e) {
            $this->logDebug('Head compilation failed: ' . $e->getMessage());
        }
    }

    private function isSitemapRequest(): bool
    {
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $cleanUri   = strtok($requestUri, '?') ?: '';

        // Match various sitemap formats: sitemap.xml, sitemap_index.xml, sitemap-pages.xml, sitemap-articles.xml
        if (preg_match('#/sitemap([_-](index|pages|articles|categories))?\.xml$#i', $cleanUri)) {
            return true;
        }

        return isset($_GET['format']) && (string) $_GET['format'] === 'sitemap';
    }

    private function isDiagnosticRequest(): bool
    {
        // Check for ?jb_diag=1 query parameter
        return isset($_GET['jb_diag']) && $_GET['jb_diag'] === '1';
    }

    private function handleDiagnosticRequest(CMSApplication $app): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        echo json_encode($this->generateDiagnosticData(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $app->close();
    }

    private function generateDiagnosticData(): array
    {
        $domain = $this->getCurrentDomain();
        $isStaging = $this->isStaging($domain);

        return [
            'plugin' => [
                'name' => 'JoomlaBoost',
                'version' => '0.1.24',
                'status' => 'active'
            ],
            'environment' => [
                'type' => $isStaging ? 'staging' : 'production',
                'domain' => $domain,
                'host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
                'php_version' => PHP_VERSION,
                'joomla_version' => defined('JVERSION') ? JVERSION : 'unknown'
            ],
            'features' => [
                'robots_txt' => (bool) $this->params->get('enable_robots', 1),
                'sitemap' => (bool) $this->params->get('enable_sitemap', 1),
                'schema' => (bool) $this->params->get('enable_schema', 1),
                'opengraph' => (bool) $this->params->get('enable_opengraph', 1),
                'hreflang' => (bool) $this->params->get('enable_hreflang', 1),
                'faq_schema' => (bool) $this->params->get('faq_schema_enabled', 1),
                'ga4' => (bool) $this->params->get('enable_ga4', 0),
                'gtm' => (bool) $this->params->get('enable_gtm', 0),
                'meta_pixel' => (bool) $this->params->get('enable_meta_pixel', 0)
            ],
            'services' => [
                'available' => [
                    'DomainDetectionService',
                    'RobotService',
                    'SitemapService',
                    'SchemaService',
                    'OpenGraphService',
                    'AnalyticsService',
                    'MetaPixelService',
                    'HreflangService',
                    'PerformanceService',
                    'HealthService',
                    'InjectionService'
                ],
                'loaded' => class_exists('JoomlaBoost\\Plugin\\System\\JoomlaBoost\\Services\\ServiceContainer')
            ],
            'endpoints' => [
                'robots' => rtrim($domain, '/') . '/robots.txt',
                'sitemap' => rtrim($domain, '/') . '/sitemap.xml',
                'diagnostic' => rtrim($domain, '/') . '/index.php?jb_diag=1'
            ],
            'debug' => [
                'mode' => (bool) $this->params->get('debug_mode', 0),
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'timestamp' => date('Y-m-d H:i:s T')
            ]
        ];
    }

    private function handleSitemapRequest(CMSApplication $app): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');

        // Use SitemapService for sitemap generation
        $sitemapService = $this->getServiceContainer()->get('sitemap');

        if ($sitemapService && method_exists($sitemapService, 'generateSitemapIndex')) {
            echo $sitemapService->generateSitemapIndex();
        } else {
            // Fallback to basic sitemap if service unavailable
            echo $this->generateSitemapContent();
        }

        $app->close();
    }

    private function generateSitemapContent(): string
    {
        $domain    = $this->getCurrentDomain();
        $isStaging = $this->isStaging($domain);
        return $isStaging ? $this->getStagingSitemap() : $this->getProductionSitemap();
    }

    private function getStagingSitemap(): string
    {
        $domain  = $this->getCurrentDomain();
        $lastmod = date('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
            . "
  <!-- JoomlaBoost Sitemap - STAGING ENVIRONMENT -->\n"
            . "
  <!-- Limited sitemap for staging -->\n"
            . " <url>\n"
            . ' <loc>' . htmlspecialchars($domain) . "</loc>\n"
            . ' <lastmod>' . $lastmod . "</lastmod>\n"
            . " <changefreq>daily</changefreq>\n"
            . " <priority>1.0</priority>\n"
            . " </url>\n"
            . "
  <!-- Generated by JoomlaBoost Plugin -->\n"
            . "
  <!-- Environment: Staging -->\n"
            . "
  <!-- Generated: " . date('Y-m-d H:i:s T') . " -->\n"
            . "
</urlset>";
    }

    private function getProductionSitemap(): string
    {
        $domain = $this->getCurrentDomain();
        $lastmod = date('Y-m-d\TH:i:s\Z');

        return '
<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
            . "
  <!-- JoomlaBoost Sitemap - PRODUCTION ENVIRONMENT -->\n"
            . " <url>\n"
            . ' <loc>' . htmlspecialchars($domain) . "</loc>\n"
            . ' <lastmod>' . $lastmod . "</lastmod>\n"
            . " <changefreq>daily</changefreq>\n"
            . " <priority>1.0</priority>\n"
            . " </url>\n"
            . "
  <!-- TODO: Add menu items and articles dynamically -->\n"
            . "
  <!-- Generated by JoomlaBoost Plugin -->\n"
            . "
  <!-- Environment: Production -->\n"
            . "
  <!-- Generated: " . date('Y-m-d H:i:s T') . " -->\n"
            . "
</urlset>";
    }

    private function isRobotsRequest(): bool
    {
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $cleanUri = strtok($requestUri, '?') ?: '';

        if (preg_match('#/robots\.txt$#i', $cleanUri)) {
            return true;
        }

        return isset($_GET['format']) && (string) $_GET['format'] === 'robots';
    }

    private function handleRobotsRequest(CMSApplication $app): void
    {
        header('Content-Type: text/plain');
        header('Cache-Control: public, max-age=3600');

        echo $this->generateRobotsContent();
        $app->close();
    }

    private function generateRobotsContent(): string
    {
        $domain = $this->getCurrentDomain();
        $isStaging = $this->isStaging($domain);
        return $isStaging ? $this->getStagingRobots() : $this->getProductionRobots();
    }

    private function getCurrentDomain(): string
    {
        $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
        return $scheme . '://' . $host . '/';
    }

    private function isStaging(string $domain): bool
    {
        $stagingKeywords = ['staging', 'stage', 'dev', 'test', 'localhost'];
        foreach ($stagingKeywords as $keyword) {
            if (stripos($domain, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    private function getStagingRobots(): string
    {
        return "# JoomlaBoost Robots.txt - STAGING ENVIRONMENT\n"
            . "# This site is not indexed by search engines\n\n"
            . "# Allow Google Search Console and related tools for testing\n"
            . "User-agent: Googlebot\n"
            . "User-agent: Google-InspectionTool\n"
            . "User-agent: Google-Site-Verification\n"
            . "User-agent: GoogleOther\n"
            . "Allow: /\n"
            . "Disallow: /administrator/\n"
            . "Disallow: /api/\n"
            . "Disallow: /cache/\n"
            . "Disallow: /tmp/\n\n"
            . "# Block all other crawlers\n"
            . "User-agent: *\n"
            . "Disallow: /\n\n"
            . "# This is a staging environment - not for public indexing\n\n"
            . "# Generated by JoomlaBoost Plugin\n"
            . "# Environment: Staging\n"
            . "# Generated: " . date('Y-m-d H:i:s T') . "\n";
    }

    private function getProductionRobots(): string
    {
        $baseUrl = $this->getCurrentDomain();

        return "# JoomlaBoost Robots.txt - PRODUCTION ENVIRONMENT\n\n"
            . "User-agent: *\n"
            . "Allow: /\n\n"
            . "# Disallow admin areas\n"
            . "Disallow: /administrator/\n"
            . "Disallow: /cache/\n"
            . "Disallow: /includes/\n"
            . "Disallow: /language/\n"
            . "Disallow: /libraries/\n"
            . "Disallow: /logs/\n"
            . "Disallow: /tmp/\n\n"
            . "# Allow specific files\n"
            . "Allow: /templates/\n"
            . "Allow: /media/\n"
            . "Allow: /images/\n\n"
            . "# Sitemap\n"
            . "Sitemap: {$baseUrl}sitemap.xml\n\n"
            . "# Generated by JoomlaBoost Plugin\n"
            . "# Environment: Production\n"
            . "# Generated: " . date('Y-m-d H:i:s T') . "\n";
    }

    private function addOptimizedOpenGraphTags(HtmlDocument $document): void
    {
        if (!$this->params->get('enable_opengraph', 1)) {
            $this->logDebug('OpenGraph: Disabled in plugin settings');
            return;
        }

        try {
            if ($this->openGraphService === null) {
                $this->openGraphService = new OpenGraphService($this->getApp(), $this->params);
            }

            $this->openGraphService->generateOpenGraphTags();
            $this->logDebug('OpenGraph tags generated with performance optimizations');
        } catch (\Throwable $e) {
            $this->logDebug('OpenGraph generation failed: ' . $e->getMessage());
        }
    }

    private function addOptimizedSchemaMarkup(HtmlDocument $document): void
    {
        try {
            if ($this->schemaService === null) {
                $this->schemaService = new SchemaService($this->getApp(), $this->params);
            }

            if (!$this->params->get('enable_schema', 1)) {
                $this->logDebug('Schema: Disabled in plugin settings');
                return;
            }

            $schema = $this->schemaService->generateSchema();
            if (!empty($schema)) {
                $jsonLd = '<script type="application/ld+json">' . "\n";
                $jsonLd .= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                $jsonLd .= "\n" . '</script>';
                $document->addCustomTag($jsonLd);
                $this->logDebug('Schema.org JSON-LD generated with performance optimizations', [
                    'schemas_count' => count($schema)
                ]);
            } else {
                $this->logDebug('Schema: No schema data generated');
            }
        } catch (\Throwable $e) {
            $this->logDebug('Schema.org generation failed: ' . $e->getMessage());
        }
    }

    private function logDebug(string $message, array $context = []): void
    {
        try {
            if ($this->params && $this->params->get('debug_mode', 0)) {
                $logMessage = '[JoomlaBoost] ' . $message;
                if (!empty($context)) {
                    $logMessage .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                Factory::getApplication()->enqueueMessage($logMessage, 'info');
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function addSchemaMarkup(HtmlDocument $document): void
    {
        try {
            if ($this->schemaService === null) {
                $this->schemaService = new SchemaService($this->getApp(), $this->params);
            }

            if (!$this->params->get('enable_schema', 1)) {
                $this->logDebug('Schema: Disabled in plugin settings');
                return;
            }

            $schema = $this->schemaService->generateSchema();
            if (!empty($schema)) {
                $jsonLd = '<script type="application/ld+json">
' . "\n";
                $jsonLd .= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                $jsonLd .= "\n
</script>";
                $document->addCustomTag($jsonLd);
                if ($this->params->get('debug_mode', 0)) {
                    $this->logDebug('Schema.org JSON-LD generated: ' . count($schema) . ' schema(s)');
                }
            } else {
                $this->logDebug('Schema: No schema data generated');
            }
        } catch (\Throwable $e) {
            if ($this->params->get('debug_mode', 0)) {
                $this->logDebug('Schema.org generation failed: ' . $e->getMessage());
            }
        }
    }

    private function addGoogleVerificationTags(HtmlDocument $document): void
    {
        $gscMeta = $this->params->get('gsc_verification_meta', '');
        if (!empty($gscMeta)) {
            $document->setMetaData('google-site-verification', $gscMeta);
            $this->logDebug('Added Google Search Console verification meta tag');
        }

        $additionalHtml = $this->params->get('gsc_additional_html', '');
        if (!empty($additionalHtml)) {
            $document->addCustomTag($additionalHtml);
            $this->logDebug('Added additional Google verification HTML');
        }

        if ($this->params->get('enable_ga4', 0)) {
            $this->addGA4Tracking($document);
        }

        if ($this->params->get('enable_gtm', 0)) {
            $this->addGTMTracking($document);
        }
    }

    private function addGA4Tracking(HtmlDocument $document): void
    {
        $measurementId = $this->params->get('ga4_measurement_id', '');
        if (empty($measurementId)) {
            $this->logDebug('GA4: No measurement ID provided');
            return;
        }

        $ga4Script = "
<!-- Google Analytics 4 -->
<script async src=\"https://www.googletagmanager.com/gtag/js?id={$measurementId}\"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag() {
  dataLayer.push(arguments);
}
gtag('js', new Date());
gtag('config', '{$measurementId}');
</script>";
        $document->addCustomTag($ga4Script);
        $this->logDebug('Added Google Analytics 4 tracking for: ' . $measurementId);
    }

    private function addGTMTracking(HtmlDocument $document): void
    {
        $containerId = $this->params->get('gtm_container_id', '');
        if (empty($containerId)) {
            $this->logDebug('GTM: No container ID provided');
            return;
        }

        $gtmHead = "
<!-- Google Tag Manager -->
<script>
(function(w, d, s, l, i) {
  w[l] = w[l] || [];
  w[l].push({
    'gtm.start': new Date().getTime(),
    event: 'gtm.js'
  });
  var f = d.getElementsByTagName(s)[0],
    j = d.createElement(s),
    dl = l != 'dataLayer' ? '&l=' + l : '';
  j.async = true;
  j.src = 'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
  f.parentNode.insertBefore(j, f);
})(window, document, 'script', 'dataLayer', '{$containerId}');
</script>
<!-- End Google Tag Manager -->";
        $document->addCustomTag($gtmHead);
        $this->logDebug('Added Google Tag Manager tracking for: ' . $containerId);
    }

    private function addMetaPixel(HtmlDocument $document): void
    {
        if (!$this->params->get('enable_meta_pixel', false)) {
            return;
        }

        if ($this->metaPixelService === null) {
            $this->metaPixelService = new MetaPixelService($this->params);
        }

        $this->metaPixelService->injectPixelCode($document);
        $this->metaPixelService->injectCustomEvents($document);
        $this->logDebug('Added Meta Pixel tracking');
    }
}
