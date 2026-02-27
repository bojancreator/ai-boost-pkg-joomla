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

use JoomlaBoost\Plugin\System\JoomlaBoost\Services\{
    ServiceContainer,
    PerformanceService,
    OpenGraphService,
    MetaPixelService,
    SchemaService,
    AnalyticsService,
    SettingsPersistenceService
};

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

    /** @var AnalyticsService|null */
    private ?AnalyticsService $analyticsService = null;

    /** @var SettingsPersistenceService|null */
    private ?SettingsPersistenceService $settingsPersistenceService = null;

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
                // Admin area ready - no verbose messaging needed
            }
            return;
        }

        // Auto-sync robots.txt file (daily check)
        $this->autoSyncRobotsFile();

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
            // IMPORTANT: Add Google verification tags FIRST so they appear at top of <head>
            $this->addGoogleVerificationTags($document);

            // Add domain-specific meta tags (robots noindex for staging, etc.)
            $this->addDomainMetaTags($document);

            // Then add other meta tags
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

    /**
     * onAfterRender: HTML buffer post-processing
     *
     * 1. FAQ auto-detect: scan rendered HTML for <dl>/<dt>/<dd> patterns and
     *    inject FAQPage JSON-LD into <head> — works for YooTheme + all Falang languages.
     * 2. Staging badge: inject version badge + noindex meta (staging only).
     */
    public function onAfterRender(): void
    {
        $app = $this->getApp();
        if (!$app || !$app->isClient('site')) {
            return;
        }

        $body    = $app->getBody();
        $changed = false;

        // ── 1. FAQ auto-detect from rendered HTML buffer ─────────────────────
        // Runs on every page, for all languages (Falang translates before this hook).
        // SchemaService internally guards: enabled, allowSearchEngines, no duplicate FAQPage.
        try {
            $schemaService = new SchemaService($app, $this->params);
            $newBody = $schemaService->injectFAQFromHtmlBuffer($body);
            if ($newBody !== $body) {
                $body    = $newBody;
                $changed = true;
            }
        } catch (\Throwable $e) {
            $this->logDebug('FAQ buffer injection failed: ' . $e->getMessage());
        }

        // ── 2. Staging badge (only for staging environments) ──────────────────
        if ((bool) $this->params->get('show_staging_badge', 0)) {
            $domain = $this->getCurrentDomain();
            if ($this->isStaging($domain)) {
                try {
                    // Only inject if </body> tag exists
                    if (stripos($body, '</body>') !== false) {
                        // Get plugin version and current domain
                        $pluginVersion = $this->getPluginVersion();
                        $currentTime   = date('H:i:s');

                        // Staging badge HTML with inline styles and more info
                        $badge = <<<HTML
<!-- JoomlaBoost Staging Badge -->
<div style="position: fixed; bottom: 20px; right: 20px; background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); color: white; padding: 15px 20px; border-radius: 10px; font-family: 'Segoe UI', Arial, sans-serif; font-size: 13px; font-weight: bold; box-shadow: 0 6px 20px rgba(0,0,0,0.3); z-index: 999999; cursor: pointer; border: 2px solid rgba(255,255,255,0.3);" onclick="this.style.display='none';" title="Klikni da sakriješ">
<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
    🚧 <span style="text-transform: uppercase; letter-spacing: 0.5px;">Staging Environment</span>
</div>
<div style="font-size: 11px; font-weight: normal; opacity: 0.95; line-height: 1.6; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 8px;">
    <div><strong>Plugin:</strong> JoomlaBoost v{$pluginVersion}</div>
    <div><strong>Domen:</strong> {$domain}</div>
    <div><strong>Generisano:</strong> {$currentTime}</div>
</div>
</div>
<!-- /JoomlaBoost Staging Badge -->

HTML;

                        // ⚠️ STAGING: Add robots noindex meta tag
                        // We do this here (onAfterRender) to append to existing tags instead of overwriting them
                        if (stripos($body, '<meta name="robots"') !== false) {
                            // Robots tag exists - append noindex,nofollow if not present
                            $body = preg_replace_callback('/(<meta\s+name=["\']robots["\']\s+content=["\'])([^"\']*?)(["\']\s*\/?>)/i', function ($matches) {
                                $content = $matches[2];
                                if (stripos($content, 'noindex') === false) {
                                    $content .= ', noindex';
                                }
                                if (stripos($content, 'nofollow') === false) {
                                    $content .= ', nofollow';
                                }
                                return $matches[1] . $content . $matches[3];
                            }, $body);
                        } else {
                            // Robots tag missing - insert before closing </head>
                            $metaHtml = '<meta name="robots" content="noindex,nofollow">' . "\n";
                            if (stripos($body, '</head>') !== false) {
                                $body = str_ireplace('</head>', $metaHtml . '</head>', $body);
                            }
                        }

                        // Inject badge before </body>
                        $body    = str_ireplace('</body>', $badge . '</body>', $body);
                        $changed = true;
                    }
                } catch (\Throwable $e) {
                    $this->logDebug('Staging injection failed: ' . $e->getMessage());
                }
            }
        }

        // Only call setBody() if we actually changed something (performance)
        if ($changed) {
            $app->setBody($body);
        }
    }


    /**
     * onContentPrepareForm event
     * 1. Register custom form field types for plugin config
     * 2. Fix NULL custom field values for articles (PHP 8.1+ compatibility)
     */
    public function onContentPrepareForm($form, $data): void
    {
        if (!($form instanceof \Joomla\CMS\Form\Form)) {
            return;
        }

        $formName = $form->getName();

        // Register custom fields for plugin configuration
        if ($formName === 'com_plugins.plugin') {
            \Joomla\CMS\Form\FormHelper::addFieldPrefix('JoomlaBoost\\Plugin\\System\\JoomlaBoost\\Field');
            \Joomla\CMS\Form\FormHelper::addFieldPath(__DIR__ . '/src/Field');

            // Load JavaScript for multi-language selector
            try {
                $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
                $wa->registerAndUseScript(
                    'plg_system_joomlaboost.multilang-selector',
                    'plg_system_joomlaboost/multilang-selector.js',
                    [],
                    ['defer' => true],
                    []
                );
                $wa->registerAndUseStyle(
                    'plg_system_joomlaboost.multilang-selector-css',
                    'plg_system_joomlaboost/multilang-selector.css'
                );
            } catch (\Exception $e) {
                // Silent failure - assets are optional enhancement
            }

            return;
        }

        // Fix NULL custom field values for articles
        if ($formName !== 'com_content.article') {
            return;
        }

        // Get article ID from data
        $articleId = null;
        if (is_array($data) && isset($data['id'])) {
            $articleId = (int) $data['id'];
        } elseif (is_object($data) && isset($data->id)) {
            $articleId = (int) $data->id;
        }

        if (!$articleId) {
            return; // New article, no fields to fix yet
        }

        $this->fixArticleFieldValues($articleId);
    }

    /**
     * Fix NULL custom field values after article save (PHP 8.1+ compatibility)
     * Ensures media fields always have valid JSON, preventing json_decode(null) deprecation
     */
    public function onContentAfterSave($context, $article, $isNew): void
    {
        // Only process com_content.article context
        if ($context !== 'com_content.article') {
            return;
        }

        // Skip if article doesn't have an ID
        if (empty($article->id)) {
            return;
        }

        $this->fixArticleFieldValues($article->id);
    }

    /**
     * Fix NULL/empty values for ALL custom OG fields for a specific article
     * Prevents DOMCdataSection(null) and json_decode(null) deprecation errors
     *
     * @param int $articleId Article ID
     * @return void
     */
    private function fixArticleFieldValues(int $articleId): void
    {
        try {
            $db = Factory::getDbo();

            // Define all custom OG fields with their default values
            $fieldsToFix = [
                'custom_og_image' => '{"imagefile":""}',  // JSON for media field
                'custom_og_title' => '',                   // Empty string for text
                'custom_og_description' => ''              // Empty string for textarea
            ];

            foreach ($fieldsToFix as $fieldName => $defaultValue) {
                // Get field ID
                $query = $db->getQuery(true)
                    ->select('id')
                    ->from($db->quoteName('#__fields'))
                    ->where($db->quoteName('name') . ' = ' . $db->quote($fieldName));

                $db->setQuery($query);
                $fieldId = $db->loadResult();

                if (!$fieldId) {
                    continue; // Field doesn't exist, skip to next
                }

                // Check if value exists for this article
                $query = $db->getQuery(true)
                    ->select('value')
                    ->from($db->quoteName('#__fields_values'))
                    ->where($db->quoteName('field_id') . ' = ' . (int) $fieldId)
                    ->where($db->quoteName('item_id') . ' = ' . (int) $articleId);

                $db->setQuery($query);
                $currentValue = $db->loadResult();

                if ($currentValue === false) {
                    // Record doesn't exist - INSERT
                    $query = $db->getQuery(true)
                        ->insert($db->quoteName('#__fields_values'))
                        ->columns($db->quoteName(['field_id', 'item_id', 'value']))
                        ->values((int) $fieldId . ',' . (int) $articleId . ',' . $db->quote($defaultValue));

                    $db->setQuery($query);
                    $db->execute();
                } elseif ($currentValue === null || $currentValue === '') {
                    // Record exists but value is NULL/empty - UPDATE
                    $query = $db->getQuery(true)
                        ->update($db->quoteName('#__fields_values'))
                        ->set($db->quoteName('value') . ' = ' . $db->quote($defaultValue))
                        ->where($db->quoteName('field_id') . ' = ' . (int) $fieldId)
                        ->where($db->quoteName('item_id') . ' = ' . (int) $articleId);

                    $db->setQuery($query);
                    $db->execute();
                }
            }
        } catch (\Throwable $e) {
            // Silently fail - don't break article save or form load
            $this->logDebug('Custom field NULL fix failed: ' . $e->getMessage());
        }
    }

    /**
     * Intercept custom fields BEFORE type-specific plugins (Media, Text, etc.)
     *
     * This event fires during field preparation and allows us to sanitize NULL values
     * BEFORE plg_fields_media attempts json_decode() or BEFORE FieldsPlugin creates CDATA.
     *
     * Prevents TWO deprecation errors:
     * 1. json_decode(): Passing null to parameter #1 (Media plugin line 104)
     * 2. DOMCdataSection::__construct(): Passing null to parameter #1 (FieldsPlugin line 277)
     *
     * @param   string  $context  The context of the content being passed to the fields.
     * @param   object  $item     The item object containing the fields.
     * @param   object  $field    The field object being prepared (passed by reference).
     *
     * @return  void
     * @since   0.1.105
     */
    public function onCustomFieldsPrepareField($context, $item, $field): void
    {
        // 1. Target only Article Custom Fields
        if ($context !== 'com_content.article') {
            return;
        }

        // 2. Target only our specific OG fields
        $targetFields = [
            'custom_og_image',
            'custom_og_title',
            'custom_og_description',
        ];

        if (!in_array($field->name, $targetFields, true)) {
            return;
        }

        // 3. Determine default value based on field type
        $defaultValue = '';
        if ($field->type === 'media') {
            $defaultValue = '{"imagefile":""}'; // Valid JSON for Media field json_decode()
        } else {
            $defaultValue = ''; // Empty string for text/textarea CDATA sections
        }

        // 4. THE FIX: Check for NULL or empty string and inject defaults
        // This prevents BOTH json_decode(null) AND DOMCdataSection(null) errors
        if (($field->value ?? null) === null || $field->value === '') {
            $field->value = $defaultValue;
        }

        // Also ensure rawvalue is sanitized (used in backend form population)
        if (property_exists($field, 'rawvalue')) {
            if (($field->rawvalue ?? null) === null || $field->rawvalue === '') {
                $field->rawvalue = $defaultValue;
            }
        }
    }

    /**
     * Get plugin version from XML manifest
     */
    private function getPluginVersion(): string
    {
        static $version = null;

        if ($version === null) {
            $xmlPath = __DIR__ . '/joomlaboost.xml';
            if (file_exists($xmlPath)) {
                $xmlContent = file_get_contents($xmlPath);
                if (preg_match('/<version>([^<]+)<\/version>/', $xmlContent, $matches)) {
                    $version = $matches[1];
                } else {
                    $version = 'unknown';
                }
            } else {
                $version = 'unknown';
            }
        }

        return $version;
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
                'version' => $this->getPluginVersion(), // Dynamic from XML
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
        // Use SitemapService for both staging and production
        // This allows full testing of AI features (lastmod, images) on staging
        try {
            $container = $this->getServiceContainer();
            $sitemap = $container->sitemap();
            return $sitemap->generateSitemap();
        } catch (\Exception $e) {
            // Fallback to basic sitemap if service fails
            return $this->getBasicSitemap();
        }
    }

    /**
     * Basic fallback sitemap if service fails
     */
    private function getBasicSitemap(): string
    {
        $domain  = $this->getCurrentDomain();
        $lastmod = date('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
            . "  <!-- JoomlaBoost Basic Sitemap -->\n"
            . " <url>\n"
            . ' <loc>' . htmlspecialchars($domain) . "</loc>\n"
            . ' <lastmod>' . $lastmod . "</lastmod>\n"
            . " <changefreq>daily</changefreq>\n"
            . " <priority>1.0</priority>\n"
            . " </url>\n"
            . "</urlset>";
    }

    // Legacy method - kept for compatibility
    private function getStagingSitemap(): string
    {
        return $this->getBasicSitemap();
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

    /**
     * Add domain-specific meta tags (robots noindex for staging, etc.)
     * @deprecated Moved logic to onAfterRender for better compatibility
     */
    private function addDomainMetaTags(HtmlDocument $document): void
    {
        // Logic moved to onAfterRender to ensure we append to valid Joomla core tags
        // instead of overwriting them if they haven't been set yet.
        $domain = $this->getCurrentDomain();
        if ($this->isStaging($domain)) {
            $document->addCustomTag('<!-- Environment: STAGING (protected) -->');
        } else {
            $document->addCustomTag('<!-- Environment: PRODUCTION -->');
        }
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
            . "Disallow: /bin/\n"
            . "Disallow: /cache/\n"
            . "Disallow: /cli/\n"
            . "Disallow: /components/\n"
            . "Disallow: /includes/\n"
            . "Disallow: /installation/\n"
            . "Disallow: /language/\n"
            . "Disallow: /layouts/\n"
            . "Disallow: /libraries/\n"
            . "Disallow: /logs/\n"
            . "Disallow: /modules/\n"
            . "Disallow: /plugins/\n"
            . "Disallow: /tmp/\n\n"
            . "# Block all other crawlers\n"
            . "User-agent: *\n"
            . "Disallow: /\n\n"
            . "# This is a staging environment - not for public indexing\n\n"
            . "# Generated by JoomlaBoost Plugin\n"
            . "# Environment: Staging\n"
            . "# Generated: " . date('Y-m-d H:i:s T') . "\n";
    }

    /**
     * Auto-sync robots.txt file with daily check cache
     * Prevents conflicts with Joomla updates and Admin Tools
     */
    private function autoSyncRobotsFile(): void
    {
        // Check if auto-sync is enabled
        if (!$this->params->get('enable_robots', 1)) {
            return;
        }

        if (!$this->params->get('robots_auto_sync', 1)) {
            return;
        }

        try {
            // Cache key for last sync check
            $cacheKey = 'joomlaboost_robots_last_sync';
            $cache = Factory::getCache('plg_system_joomlaboost', '');
            $lastSync = $cache->get($cacheKey);

            // Only check once per day to avoid performance issues
            $today = date('Y-m-d');
            if ($lastSync === $today) {
                return; // Already synced today
            }

            $robotsPath = JPATH_ROOT . '/robots.txt';
            $currentContent = $this->generateRobotsContent();

            // Only update if file doesn't exist or content is different
            $needsUpdate = !file_exists($robotsPath) ||
                file_get_contents($robotsPath) !== $currentContent;

            if ($needsUpdate) {
                // Write new content directly (no backup - backups pollute the server)
                file_put_contents($robotsPath, $currentContent);
            }

            // Update cache
            $cache->store($today, $cacheKey);
        } catch (\Throwable $e) {
            // Fail silently - don't break site or show messages
        }
    }

    private function getProductionRobots(): string
    {
        $baseUrl = $this->getCurrentDomain();

        return "# JoomlaBoost Robots.txt - PRODUCTION ENVIRONMENT\n\n"
            . "User-agent: *\n"
            . "Allow: /\n\n"
            . "# Disallow admin and system folders\n"
            . "Disallow: /administrator/\n"
            . "Disallow: /api/\n"
            . "Disallow: /bin/\n"
            . "Disallow: /cache/\n"
            . "Disallow: /cli/\n"
            . "Disallow: /components/\n"
            . "Disallow: /includes/\n"
            . "Disallow: /installation/\n"
            . "Disallow: /language/\n"
            . "Disallow: /layouts/\n"
            . "Disallow: /libraries/\n"
            . "Disallow: /logs/\n"
            . "Disallow: /modules/\n"
            . "Disallow: /plugins/\n"
            . "Disallow: /tmp/\n\n"
            . "# Allow public assets\n"
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
            // Support multiple verification codes (comma or newline separated)
            $codes = preg_split('/[,\n\r]+/', $gscMeta, -1, PREG_SPLIT_NO_EMPTY);

            $count = 0;
            foreach ($codes as $code) {
                $code = trim($code);
                if (!empty($code)) {
                    // Use setMetaData with unique name to ensure tags appear early in <head>
                    // Joomla renders these BEFORE custom tags and right after charset/viewport
                    $document->setMetaData('google-site-verification-' . $count, $code);
                    $count++;
                }
            }

            if ($count > 0) {
                $this->logDebug('Added ' . $count . ' Google Search Console verification meta tag(s) at top of head');
            }
        }

        $additionalHtml = $this->params->get('gsc_additional_html', '');
        if (!empty($additionalHtml)) {
            $document->addCustomTag($additionalHtml);
            $this->logDebug('Added additional Google verification HTML');
        }

        // Facebook Domain Verification
        $fbVerification = trim((string) $this->params->get('fb_domain_verification', ''));
        if (!empty($fbVerification)) {
            $document->setMetaData('facebook-domain-verification', $fbVerification);
            $this->logDebug('Added Facebook domain verification meta tag');
        }

        // Analytics Service (GA4, GTM)
        if ($this->analyticsService === null) {
            $this->analyticsService = new AnalyticsService($this->getApp(), $this->params);
        }
        $this->analyticsService->injectAnalytics($document);
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

    /**
     * Event handler: Auto-save settings when plugin configuration is saved
     *
     * @param string $context The extension context
     * @param object $table Table object
     * @param bool $isNew Whether this is a new extension
     * @return void
     */
    public function onExtensionAfterSave($context, $table, $isNew): void
    {
        // Only save for this plugin
        if ($context !== 'com_plugins.plugin' || empty($table->element) || $table->element !== 'joomlaboost') {
            return;
        }

        try {
            // Initialize service if needed
            if ($this->settingsPersistenceService === null) {
                $this->settingsPersistenceService = new SettingsPersistenceService($this->getApp(), $this->params);
            }

            // Get current plugin parameters
            $params = json_decode($table->params, true);
            if (!is_array($params)) {
                return;
            }

            // Save to database
            $this->settingsPersistenceService->saveSettings($params);

            $this->logDebug('JoomlaBoost settings auto-saved to persistence storage');
        } catch (\Exception $e) {
            // Silent fail - don't break plugin save
            $this->logDebug('Failed to auto-save settings: ' . $e->getMessage());
        }
    }
}
