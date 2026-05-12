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
use Joomla\CMS\Uri\Uri;

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
    HreflangService,
    RobotService,
    SettingsPersistenceService,
    IndexNowService,
    LlmsTxtService,
    VerticalPresetService
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

        // Remove static llms.txt and IndexNow key files — we now serve them
        // dynamically through PHP so LiteSpeed/AdminTools cannot block them.
        $this->removeStaticDynamicFiles();

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

        // llms.txt / llms-full.txt handling (AI search engines) — Developer/Agency only
        if ($this->isLlmsTxtRequest()) {
            if ($this->isProLicense()) {
                $this->handleLlmsTxtRequest($app);
            }
            return;
        }

        // IndexNow key file handling ({apiKey}.txt) — Developer/Agency only
        if ($this->isIndexNowKeyRequest()) {
            if ($this->isProLicense()) {
                $this->handleIndexNowKeyRequest($app);
            }
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

            // Add canonical link tag (current URL without query params)
            $this->addCanonicalTag($document);

            // Hreflang is handled in onAfterRender (buffer phase) to run AFTER
            // Language Filter / Falang which would otherwise overwrite our tags.

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

        // ── 1b. OG Image SVG Fix ───────────────────────────────────────────────
        // YooTheme Pro injects og:image AFTER onBeforeCompileHead, overriding
        // JoomlaBoost. This step runs post-render and replaces any SVG og:image
        // (invalid for Facebook/Twitter) with the plugin-configured fallback.
        try {
            $ogService = new OpenGraphService($app, $this->params);
            $newBody   = $ogService->fixSvgOgImageInBuffer($body);
            if ($newBody !== $body) {
                $body    = $newBody;
                $changed = true;
            }
        } catch (\Throwable $e) {
            $this->logDebug('OG SVG fix failed: ' . $e->getMessage());
        }

        // ── 1c. Deduplicate og: meta tags ─────────────────────────────────────
        // Both JoomlaBoost and some templates (e.g. YooTheme, emarket1ng) inject
        // og:type, og:title etc. independently. Keep only the FIRST occurrence of
        // each og: property tag; remove any subsequent duplicates.
        try {
            $seen    = [];
            $newBody = (string) preg_replace_callback(
                '/<meta\s+[^>]*property=["\']og:([^"\']+)["\'][^>]*>/i',
                static function (array $m) use (&$seen): string {
                    $prop = strtolower($m[1]);
                    if (isset($seen[$prop])) {
                        return ''; // Remove duplicate
                    }
                    $seen[$prop] = true;
                    return $m[0]; // Keep first occurrence
                },
                $body
            );
            if ($newBody !== $body) {
                $body    = $newBody;
                $changed = true;
                $this->logDebug('Deduplicated duplicate og: meta tags');
            }
        } catch (\Throwable $e) {
            $this->logDebug('OG dedup failed: ' . $e->getMessage());
        }

        // ── 1d. Fix HTML entities in twitter: meta content ────────────────────
        // twitter:description sometimes contains double-encoded entities (&#039;, &amp;#039;)
        // because the description goes through htmlspecialchars() twice. Twitter Card
        // parsers don't decode HTML entities from meta content attributes reliably.
        try {
            $newBody = (string) preg_replace_callback(
                '/(<meta\s+[^>]*name=["\']twitter:[^"\']+["\'][^>]*content=["\'])([^"\']+)(["\'][^>]*>)/i',
                static function (array $m): string {
                    $decoded = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    return $m[1] . htmlspecialchars($decoded, ENT_QUOTES, 'UTF-8') . $m[3];
                },
                $body
            );
            if ($newBody !== $body) {
                $body    = $newBody;
                $changed = true;
            }
        } catch (\Throwable $e) {
            $this->logDebug('Twitter entity fix failed: ' . $e->getMessage());
        }

        // ── 1e. NewsArticle schema — inject on article pages if not already present ─
        // Detection: find og:type in HTML then check if "article" is its content value
        // within a 200-char window. This is more reliable than regex and avoids false
        // positives from article:published_time (which templates add on ALL pages).
        try {
            // Find where og:type appears, then check its value in a small window
            $ogTypeOffset = stripos($body, 'og:type');
            $isArticlePage = false;

            if ($ogTypeOffset !== false) {
                $ogTypeWindow = substr($body, $ogTypeOffset, 200);
                $isArticlePage = stripos($ogTypeWindow, '"article"') !== false
                    || stripos($ogTypeWindow, "'article'") !== false;
            }

            $hasNewsArticle = stripos($body, '"NewsArticle"') !== false;
            $schemaEnabled  = (bool) $this->params->get('enable_schema', 1);

            if ($isArticlePage && !$hasNewsArticle && $schemaEnabled && stripos($body, '</head>') !== false) {
                // Headline from <title> tag (always reliable)
                $headline = '';
                if (preg_match('|<title>(.+?)</title>|is', $body, $tm)) {
                    $headline = html_entity_decode(trim($tm[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }

                // Current URL directly from Joomla (no HTML parsing)
                $articleUrl = Uri::getInstance()->toString();

                // Dates via simple string extraction (use \x27 for single-quote in pattern)
                $datePublished = '';
                if (preg_match('/article:published_time[^>]+content=[\x22\x27]([^\x22\x27]+)/i', $body, $dm)) {
                    $datePublished = $dm[1];
                } elseif (preg_match('/content=[\x22\x27]([^\x22\x27]+)[\x22\x27][^>]+article:published_time/i', $body, $dm)) {
                    $datePublished = $dm[1];
                }

                $dateModified = $datePublished;
                if (preg_match('/article:modified_time[^>]+content=[\x22\x27]([^\x22\x27]+)/i', $body, $dm)) {
                    $dateModified = $dm[1];
                } elseif (preg_match('/content=[\x22\x27]([^\x22\x27]+)[\x22\x27][^>]+article:modified_time/i', $body, $dm)) {
                    $dateModified = $dm[1];
                }

                // Image via og:image
                $imageUrl = '';
                if (preg_match('/og:image[^>]+content=[\x22\x27]([^\x22\x27]+)/i', $body, $im)) {
                    $imageUrl = $im[1];
                } elseif (preg_match('/content=[\x22\x27]([^\x22\x27]+)[\x22\x27][^>]+og:image/i', $body, $im)) {
                    $imageUrl = $im[1];
                }

                // Site name via og:site_name
                $siteName = '';
                if (preg_match('/og:site_name[^>]+content=[\x22\x27]([^\x22\x27]+)/i', $body, $sn)) {
                    $siteName = html_entity_decode($sn[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                } elseif (preg_match('/content=[\x22\x27]([^\x22\x27]+)[\x22\x27][^>]+og:site_name/i', $body, $sn)) {
                    $siteName = html_entity_decode($sn[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }

                if ($headline && $articleUrl) {
                    $newsArticle = [
                        '@context' => 'https://schema.org',
                        '@type'    => 'NewsArticle',
                        'headline' => $headline,
                        'url'      => $articleUrl,
                    ];

                    if ($imageUrl) {
                        $newsArticle['image'] = $imageUrl;
                    }

                    if ($datePublished) {
                        $newsArticle['datePublished'] = $datePublished;
                    }

                    if ($dateModified) {
                        $newsArticle['dateModified'] = $dateModified;
                    }

                    if ($siteName) {
                        $newsArticle['publisher'] = ['@type' => 'Organization', 'name' => $siteName];
                        $newsArticle['author']    = ['@type' => 'Organization', 'name' => $siteName];
                    }

                    $jsonBlock = '<script type="application/ld+json">' . "\n"
                        . json_encode($newsArticle, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT)
                        . "\n</script>";

                    $body    = str_ireplace('</head>', $jsonBlock . "\n</head>", $body);
                    $changed = true;
                    $this->logDebug('NewsArticle schema injected');
                }
            }
        } catch (\Throwable $e) {
            $this->logDebug('NewsArticle fallback failed: ' . $e->getMessage());
        }
        // ── 2. Hreflang injection (HTML buffer) ───────────────────────────────
        // Runs LAST — after Language Filter / Falang — to guarantee clean output.
        try {
            if ((bool) $this->params->get('enable_hreflang', 1)) {
                $langService = new \JoomlaBoost\Plugin\System\JoomlaBoost\Services\LanguageService($app, $this->params);
                if ($langService->isMultilingual()) {
                    $hreflangService = new HreflangService($app, $this->params);
                    $newBody = $hreflangService->injectIntoBuffer($body, $langService);
                    if ($newBody !== $body) {
                        $body    = $newBody;
                        $changed = true;
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->logDebug('Hreflang buffer injection failed: ' . $e->getMessage());
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

                        // Staging badge labels — hardcoded EN (developer-only widget, no i18n needed)
                        $stagingClickHide = 'Click to hide';
                        $stagingDomain    = 'Domain';
                        $stagingGenerated = 'Generated';

                        $badge = <<<HTML
<!-- AI Boost Staging Badge -->
<div style="position: fixed; bottom: 20px; right: 20px; background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); color: white; padding: 15px 20px; border-radius: 10px; font-family: 'Segoe UI', Arial, sans-serif; font-size: 13px; font-weight: bold; box-shadow: 0 6px 20px rgba(0,0,0,0.3); z-index: 999999; cursor: pointer; border: 2px solid rgba(255,255,255,0.3);" onclick="this.style.display='none';" title="{$stagingClickHide}">
<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
    🚧 <span style="text-transform: uppercase; letter-spacing: 0.5px;">Staging Environment</span>
</div>
<div style="font-size: 11px; font-weight: normal; opacity: 0.95; line-height: 1.6; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 8px;">
    <div><strong>Plugin:</strong> AI Boost for Joomla v{$pluginVersion}</div>
    <div><strong>{$stagingDomain}:</strong> {$domain}</div>
    <div><strong>{$stagingGenerated}:</strong> {$currentTime}</div>
</div>
</div>
<!-- /AI Boost Staging Badge -->

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
            // Only act on our own plugin edit form — not on every other plugin's form.
            // $data may be an object (stdClass) or an associative array depending on Joomla version.
            $element = '';
            if (is_object($data) && isset($data->element)) {
                $element = (string) $data->element;
            } elseif (is_array($data) && isset($data['element'])) {
                $element = (string) $data['element'];
            }
            // Fallback: read from the request (covers edge cases where $data is empty on first load)
            if ($element === '') {
                $element = (string) Factory::getApplication()->input->get('element', '', 'cmd');
            }
            if ($element !== 'joomlaboost') {
                return;
            }

            \Joomla\CMS\Form\FormHelper::addFieldPrefix('JoomlaBoost\\Plugin\\System\\JoomlaBoost\\Field');
            \Joomla\CMS\Form\FormHelper::addFieldPath(__DIR__ . '/src/Field');

            // ── Dynamic multilingual fields ───────────────────────────────────
            // Inject one standard text/textarea field per installed language for
            // each multilingual setting (org_name, org_description, etc.).
            // This matches v0.10.1 behaviour (_en/_sr suffix pattern) but works
            // for ANY number of installed languages automatically.
            try {
                $this->injectMultiLangParamFields($form);
            } catch (\Throwable $e) {
                // Never break the plugin edit form
            }

            // ── License tier banner ───────────────────────────────────────────
            // Inject a visible notice in the admin panel depending on license tier.
            try {
                $this->injectLicenseBanner();
            } catch (\Throwable $e) {
                // Never break the plugin edit form
            }

            // Load JavaScript for admin enhancements
            try {
                $wa = Factory::getApplication()->getDocument()->getWebAssetManager();

                // Inline admin styles
                Factory::getApplication()->getDocument()->addStyleDeclaration(
                    '#style-form .controls input[type="text"],' .
                    '#style-form .controls input[type="url"],' .
                    '#style-form .controls input[type="email"],' .
                    '#style-form .controls input[type="number"],' .
                    '#style-form .controls textarea {' .
                    'max-width:800px;width:100%;box-sizing:border-box;}'
                );

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
                $wa->registerAndUseScript(
                    'plg_system_joomlaboost.indexnow-generator',
                    'plg_system_joomlaboost/indexnow-generator.js',
                    [],
                    ['defer' => true],
                    []
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

        // Fix Joomla core dark-mode bug: Joomla sets
        // [data-bs-theme=dark] #content label { color: #212529 }
        // which makes labels nearly invisible on dark backgrounds.
        // We override it with the correct light colour.
        try {
            Factory::getApplication()->getDocument()->addStyleDeclaration(
                '[data-bs-theme=dark] #adminForm label,' .
                '[data-bs-theme=dark] #content label {' .
                    'color: #dee2e6 !important;' .
                '}'
            );
        } catch (\Throwable $ignored) {
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

        // IndexNow — ping search engines for published articles (Developer/Agency only)
        try {
            $indexNow = new IndexNowService($this->getApp(), $this->params);
            if ($this->isProLicense() && $indexNow->isEnabled() && isset($article->state) && (int) $article->state === 1) {
                $url = $indexNow->buildArticleUrl($article->id);
                if (!empty($url)) {
                    $indexNow->pingUrl($url);
                }
            }
        } catch (\Throwable $e) {
            $this->logDebug('IndexNow ping failed: ' . $e->getMessage());
        }
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

        $robotService = new RobotService($this->getApp(), $this->params);
        echo $robotService->generateRobots();
        $app->close();
    }

    private function generateRobotsContent(): string
    {
        $robotService = new RobotService($this->getApp(), $this->params);
        return $robotService->generateRobots();
    }

    /**
     * Remove static llms.txt and IndexNow key files from the site root.
     * These are now served dynamically via PHP so static copies conflict
     * with LiteSpeed/AdminTools security rules (403 Forbidden).
     * Called once per request — uses a session flag to run only once per session.
     */
    private function removeStaticDynamicFiles(): void
    {
        static $cleaned = false;
        if ($cleaned) {
            return;
        }
        $cleaned = true;

        // Remove static llms.txt
        if ((bool) $this->params->get('llmstxt_enabled', 0)) {
            $llmsFile = JPATH_SITE . DIRECTORY_SEPARATOR . 'llms.txt';
            if (file_exists($llmsFile)) {
                @unlink($llmsFile);
                $this->logDebug('Removed static llms.txt — now served dynamically');
            }

            $llmsFullFile = JPATH_SITE . DIRECTORY_SEPARATOR . 'llms-full.txt';
            if (file_exists($llmsFullFile)) {
                @unlink($llmsFullFile);
            }
        }

        // Remove static IndexNow key file
        if ((bool) $this->params->get('indexnow_enabled', 0)) {
            $apiKey = trim((string) $this->params->get('indexnow_api_key', ''));
            if (!empty($apiKey)) {
                $keyFile = JPATH_SITE . DIRECTORY_SEPARATOR . $apiKey . '.txt';
                if (file_exists($keyFile)) {
                    @unlink($keyFile);
                    $this->logDebug('Removed static IndexNow key file — now served dynamically');
                }
            }
        }
    }

    private function isLlmsTxtRequest(): bool
    {
        if (!(bool) $this->params->get('llmstxt_enabled', 0)) {
            return false;
        }

        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $cleanUri   = strtok($requestUri, '?') ?: '';

        return preg_match('#/llms(-full)?\.txt$#i', $cleanUri) === 1;
    }

    private function handleLlmsTxtRequest(CMSApplication $app): void
    {
        $llmsTxt = new LlmsTxtService($app, $this->params);
        $content  = $llmsTxt->generate();

        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=3600');

        echo $content;
        $app->close();
    }

    private function isIndexNowKeyRequest(): bool
    {
        if (!(bool) $this->params->get('indexnow_enabled', 0)) {
            return false;
        }

        $apiKey = trim((string) $this->params->get('indexnow_api_key', ''));
        if (empty($apiKey)) {
            return false;
        }

        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $cleanUri   = strtok($requestUri, '?') ?: '';

        return preg_match('#/' . preg_quote($apiKey, '#') . '\.txt$#i', $cleanUri) === 1;
    }

    private function handleIndexNowKeyRequest(CMSApplication $app): void
    {
        $apiKey = trim((string) $this->params->get('indexnow_api_key', ''));

        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=86400');

        echo $apiKey;
        $app->close();
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

    /**
     * Inject hreflang <link> tags for multilingual pages.
     *
     * Delegates to HreflangService which:
     * - Uses Falang as primary URL source
     * - Falls back to native Joomla multilingual
     * - Skips if Language Filter plugin already injected hreflang tags
     */
    private function addHreflangTags(HtmlDocument $document): void
    {
        if (!$this->params->get('enable_hreflang', 1)) {
            return;
        }

        try {
            $hreflangService = new HreflangService($this->app, $this->params);
            $hreflangService->injectIntoDocument($document);
        } catch (\Throwable $e) {
            $this->logDebug('Hreflang injection failed: ' . $e->getMessage());
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
     * Auto-sync robots.txt file when content changes.
     *
     * Uses RobotService (which uses EnvironmentType::getRobotsRules()) as
     * the single source of truth for robots.txt content and AI crawler directives.
     *
     * Optimized hash-based approach:
     *   1. Generate desired content in memory (no I/O)
     *   2. Hash it (md5, fast string op)
     *   3. Read tiny .robots_hash sidecar file (~32 bytes, OS page-cached)
     *   4. Only write robots.txt when the hash differs
     */
    private function autoSyncRobotsFile(): void
    {
        if (!$this->params->get('enable_robots', 1)) {
            return;
        }

        if (!$this->params->get('robots_auto_sync', 1)) {
            return;
        }

        try {
            $robotService = new RobotService($this->getApp(), $this->params);
            $newContent   = $robotService->generateRobots();
            $newHash      = md5($newContent);

            $robotsPath = JPATH_ROOT . '/robots.txt';
            $hashPath   = JPATH_ROOT . '/.robots_hash';

            // Read stored hash (tiny file, ~32 bytes — very cheap)
            $storedHash = file_exists($hashPath) ? trim((string) file_get_contents($hashPath)) : '';

            // Nothing to do if hashes match
            if ($storedHash === $newHash) {
                return;
            }

            // Write updated robots.txt and persist the new hash
            file_put_contents($robotsPath, $newContent, LOCK_EX);
            file_put_contents($hashPath, $newHash, LOCK_EX);
        } catch (\Throwable $e) {
            // Fail silently — never break the site
        }
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
    /**
     * Add canonical <link rel="canonical"> tag.
     *
     * Canonical URL = current URL with scheme+host+path, query params stripped.
     * This prevents duplicate-content penalties when the same page is reachable
     * via multiple URLs (Joomla non-SEF params, www vs non-www, sessions, etc.).
     *
     * Only injected if no canonical already exists (e.g. from another plugin).
     */
    private function addCanonicalTag(HtmlDocument $document): void
    {
        try {
            // Check if a canonical link already exists — avoid duplicates
            $headData = $document->getHeadData();
            if (!empty($headData['links'])) {
                foreach ($headData['links'] as $attribs) {
                    if (
                        isset($attribs['rel']) &&
                        strtolower((string) $attribs['rel']) === 'canonical'
                    ) {
                        return; // Already set — nothing to do
                    }
                }
            }

            // Build canonical URL: scheme + host + path (no query, no fragment)
            $uri  = Uri::getInstance();
            $base = rtrim((string) Uri::base(), '/');

            $path = $uri->getPath();

            // Preserve trailing slash only for homepage
            $canonical = $base . $path;

            // Ensure we never produce a bare domain without trailing slash on homepage
            if ($canonical === $base || $canonical === $base . '/') {
                $canonical = $base . '/';
            }

            if (empty($canonical)) {
                return;
            }

            $document->addHeadLink(
                htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8'),
                'canonical',
                'rel'
            );

            $this->logDebug('Canonical tag added: ' . $canonical);
        } catch (\Throwable $e) {
            $this->logDebug('Canonical tag injection failed: ' . $e->getMessage());
        }
    }

    private function addGoogleVerificationTags(HtmlDocument $document): void
    {
        // Collect all GSC verification codes from primary + secondary fields
        $allCodes = [];

        foreach (['gsc_verification_meta', 'gsc_verification_meta_2'] as $field) {
            $raw = trim((string) $this->params->get($field, ''));
            if ($raw !== '') {
                // Split on comma or newline (legacy single-field support)
                $parts = preg_split('/[,\n\r]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($parts as $part) {
                    $part = trim($part);
                    if ($part !== '') {
                        $allCodes[] = $part;
                    }
                }
            }
        }

        $count = 0;
        foreach ($allCodes as $code) {
            $document->setMetaData('google-site-verification-' . $count, $code);
            $count++;
        }

        if ($count > 0) {
            $this->logDebug('Added ' . $count . ' Google Search Console verification meta tag(s) at top of head');
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

            // ─── Vertical Preset application ─────────────────────────────────
            // If user selected a preset and ticked "Apply preset on save",
            // merge optimal defaults for that vertical into params, then
            // persist updated params back to #__extensions and reload them.
            $presetId      = (string) ($params['vertical_preset'] ?? '');
            $presetApply   = (int) ($params['vertical_preset_apply'] ?? 0);
            if ($presetId !== '' && $presetApply === 1) {
                try {
                    $presetService = new VerticalPresetService($this->getApp(), $this->params);
                    $params        = $presetService->applyPreset($presetId, $params);

                    // Persist updated params back to the extension row so the next
                    // page render and onExtensionAfterSave see the merged config.
                    $extensionId = (int) ($table->extension_id ?? 0);
                    if ($extensionId > 0) {
                        $presetService->persistParams($params, $extensionId);
                        // Update the in-memory $table so subsequent code in this same
                        // hook (LLMs.txt, robots.txt) uses the new values too.
                        $table->params = json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        // Also refresh $this->params so RobotService etc. see new flags.
                        $this->params->loadArray($params);
                    }

                    // Surface a flash message to the admin user.
                    $presetLabels = VerticalPresetService::listPresets();
                    $label        = $presetLabels[$presetId] ?? $presetId;
                    $this->getApp()->enqueueMessage(
                        sprintf('JoomlaBoost: "%s" preset applied successfully.', $label),
                        'success'
                    );
                } catch (\Throwable $e) {
                    $this->logDebug('VerticalPreset application failed: ' . $e->getMessage());
                    $this->getApp()->enqueueMessage(
                        'JoomlaBoost: preset could not be applied — ' . $e->getMessage(),
                        'warning'
                    );
                }
            }

            // Save to database
            $this->settingsPersistenceService->saveSettings($params);

            // IndexNow — create key file when plugin settings are saved (Developer/Agency only)
            try {
                if ($this->isProLicense() && !empty($params['indexnow_api_key'])) {
                    $indexNow = new IndexNowService($this->getApp(), $this->params);
                    $indexNow->ensureKeyFile();
                }
            } catch (\Throwable $e) {
                $this->logDebug('IndexNow key file creation failed: ' . $e->getMessage());
            }

            // LLMs.txt — generate file when plugin settings are saved (Developer/Agency only)
            try {
                if ($this->isProLicense()) {
                    $llmsTxt = new LlmsTxtService($this->getApp(), $this->params);
                    if ($llmsTxt->isEnabled()) {
                        $llmsTxt->generateAndWrite();
                    }
                }
            } catch (\Throwable $e) {
                $this->logDebug('LlmsTxt generation failed: ' . $e->getMessage());
            }

            // robots.txt — write immediately on admin save (do not wait for frontend request)
            // autoSyncRobotsFile() is guarded by isClient('site') so it never runs in admin.
            // Writing directly here ensures robots.txt is up-to-date right after Save.
            try {
                if ($this->params->get('enable_robots', 1) && $this->params->get('robots_auto_sync', 1)) {
                    $robotsPath = JPATH_ROOT . '/robots.txt';
                    $hashPath   = JPATH_ROOT . '/.robots_hash';
                    // Use RobotService — same as autoSyncRobotsFile(), single source of truth.
                    // RobotService uses EnvironmentType::getRobotsRules() which has AI crawlers.
                    $robotService = new RobotService($this->getApp(), $this->params);
                    $content      = $robotService->generateRobots();
                    file_put_contents($robotsPath, $content, LOCK_EX);
                    file_put_contents($hashPath, md5($content), LOCK_EX);
                    $this->logDebug('robots.txt written from admin save hook via RobotService');
                }
            } catch (\Throwable $e) {
                $this->logDebug('robots.txt write on save failed: ' . $e->getMessage());
            }

            $this->logDebug('JoomlaBoost settings auto-saved to persistence storage');
        } catch (\Exception $e) {
            // Silent fail - don't break plugin save
            $this->logDebug('Failed to auto-save settings: ' . $e->getMessage());
        }
    }

    /**
     * Returns the current license tier from params.
     * Values: '' (free/unlicensed), 'starter', 'developer', 'agency'.
     */
    private function getLicenseTier(): string
    {
        return strtolower(trim((string) $this->params->get('license_tier', '')));
    }

    /**
     * Returns true when the current license is both format-valid AND a Pro (Developer or Agency) tier.
     * A tier value stored without a valid key (e.g. stale config) is treated as non-Pro.
     */
    private function isProLicense(): bool
    {
        $key = trim((string) $this->params->get('license_key', ''));
        if ($key === '') {
            return false;
        }

        $validFormat = (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $key
        );
        if (!$validFormat) {
            return false;
        }

        $tier = $this->getLicenseTier();
        return $tier === 'developer' || $tier === 'agency';
    }

    /**
     * Injects a license status / upgrade banner at the top of the plugin admin form
     * using Joomla's inline style declaration — no external assets required.
     *
     * - Free/unlicensed: red/orange danger banner with purchase CTA.
     * - Starter: info/blue banner with upgrade-to-Pro CTA.
     * - Developer / Agency: no banner (everything is unlocked).
     */
    private function injectLicenseBanner(): void
    {
        $licenseKey = trim((string) $this->params->get('license_key', ''));
        $tier       = $this->getLicenseTier();

        $hasValidKey = $licenseKey !== '' && (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $licenseKey
        );

        $isFree    = !$hasValidKey || $tier === '';
        $isStarter = $hasValidKey && $tier === 'starter';
        $isPro     = $hasValidKey && ($tier === 'developer' || $tier === 'agency');

        if ($isPro) {
            return;
        }

        try {
            $doc = Factory::getApplication()->getDocument();

            if ($isFree) {
                $title = \Joomla\CMS\Language\Text::_('PLG_SYSTEM_JOOMLABOOST_FREE_BANNER_TITLE');
                $desc  = \Joomla\CMS\Language\Text::_('PLG_SYSTEM_JOOMLABOOST_FREE_BANNER_DESC');
                $color = '#842029';
                $bg    = '#f8d7da';
                $border = '#f5c2c7';
            } else {
                $title = \Joomla\CMS\Language\Text::_('PLG_SYSTEM_JOOMLABOOST_STARTER_BANNER_TITLE');
                $desc  = \Joomla\CMS\Language\Text::_('PLG_SYSTEM_JOOMLABOOST_STARTER_BANNER_DESC');
                $color = '#084298';
                $bg    = '#cfe2ff';
                $border = '#b6d4fe';
            }

            $jsTitle  = json_encode('<strong style="display:block;margin-bottom:4px;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</strong>' . $desc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $jsBorder = json_encode($border);
            $jsBg     = json_encode($bg);
            $jsColor  = json_encode($color);

            $bannerJs = <<<JS
            <script>
            (function () {
                var inject = function () {
                    if (document.getElementById('jb-license-banner')) { return; }
                    var banner = document.createElement('div');
                    banner.id = 'jb-license-banner';
                    banner.style.cssText = 'margin:12px 0 4px;padding:12px 16px;border-radius:6px;border:1px solid '+{$jsBorder}+';background:'+{$jsBg}+';color:'+{$jsColor}+';font-size:0.95em;line-height:1.5;';
                    banner.innerHTML = {$jsTitle};
                    /* Insert after the license_key field row */
                    var anchor = document.getElementById('jform_params_license_key');
                    if (anchor) {
                        var row = anchor.closest('.control-group') || anchor.closest('tr') || anchor.parentNode;
                        row.parentNode.insertBefore(banner, row.nextSibling);
                    } else {
                        /* Fallback: prepend to first tab panel */
                        var panel = document.querySelector('.tab-pane') || document.querySelector('#adminForm');
                        if (panel) { panel.insertBefore(banner, panel.firstChild); }
                    }
                };
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', inject);
                } else {
                    inject();
                }
            })();
            </script>
            JS;

            $doc->addCustomTag($bannerJs);
        } catch (\Throwable $e) {
            // Silent fail — never break admin
        }
    }

    /**
     * Inject per-language text/textarea fields into the plugin params form.
     *
     * Queries installed languages (Falang + native Joomla, merged and deduplicated)
     * and adds one standard Joomla field per language for each multilingual setting.
     *
     * Field naming: {baseFieldName}_{2charLangCode}  e.g. org_name_en, org_name_sr
     * This matches getLocalizedParam() pattern used in SchemaService and QAManagementService.
     *
     * @param  \Joomla\CMS\Form\Form  $form
     * @return void
     */
    private function injectMultiLangParamFields(\Joomla\CMS\Form\Form $form): void
    {
        // ── 1. Get installed languages (Falang + native Joomla, merged) ────────
        $db     = \Joomla\CMS\Factory::getDbo();
        $seen   = [];
        $langs  = [];

        // Try Falang
        try {
            $db->setQuery(
                'SELECT fl.lang_code, COALESCE(l.title, fl.lang_code) AS name'
                . ' FROM ' . $db->quoteName('#__falang_languages') . ' AS fl'
                . ' LEFT JOIN ' . $db->quoteName('#__languages') . ' AS l'
                . '   ON l.lang_id = fl.joomla_lang_id'
                . ' ORDER BY fl.id ASC'
            );
            foreach ((array) $db->loadObjectList() as $row) {
                $code = strtolower(substr((string) $row->lang_code, 0, 2));
                if ($code !== '' && !isset($seen[$code])) {
                    $seen[$code] = true;
                    $langs[]     = ['code' => $code, 'name' => (string) $row->name];
                }
            }
        } catch (\Throwable $e) {
            // Falang not installed
        }

        // Try native Joomla (#__languages)
        try {
            $db->setQuery(
                'SELECT lang_code, title AS name'
                . ' FROM ' . $db->quoteName('#__languages')
                . ' WHERE published = 1'
                . ' ORDER BY ordering ASC'
            );
            foreach ((array) $db->loadObjectList() as $row) {
                $code = strtolower(substr((string) $row->lang_code, 0, 2));
                if ($code !== '' && !isset($seen[$code])) {
                    $seen[$code] = true;
                    $langs[]     = ['code' => $code, 'name' => (string) $row->name];
                }
            }
        } catch (\Throwable $e) {
            // fall through
        }

        // Fallback to English only
        if (empty($langs)) {
            $langs = [['code' => 'en', 'name' => 'English']];
        }

        // ── 1b. Sort: default language first, then alphabetically ────────────
        $defaultLangCode = 'en';
        try {
            $defaultTag = \Joomla\CMS\Component\ComponentHelper::getParams('com_languages')->get('site', 'en-GB');
            $defaultLangCode = strtolower(substr($defaultTag, 0, 2));
        } catch (\Throwable $e) {
            // keep 'en'
        }

        // Separate default from rest, sort rest by name
        $defaultLang = null;
        $otherLangs  = [];
        foreach ($langs as $lang) {
            if ($lang['code'] === $defaultLangCode) {
                $defaultLang = $lang;
            } else {
                $otherLangs[] = $lang;
            }
        }
        usort($otherLangs, fn($a, $b) => strcmp($a['name'], $b['name']));

        // If default wasn't found (shouldn't happen), use first
        if ($defaultLang === null) {
            $defaultLang = array_shift($otherLangs) ?: ['code' => 'en', 'name' => 'English'];
        }

        // Mark default and rebuild
        $defaultLang['is_default'] = true;
        foreach ($otherLangs as &$ol) {
            $ol['is_default'] = false;
        }
        unset($ol);
        $langs = array_merge([$defaultLang], $otherLangs);

        // ── 2. Define multilingual field groups ────────────────────────────────
        // Each entry: [fieldset, type, baseLabel, hint, showon, extra-attrs]
        $groups = [
            // ── OpenGraph fields ──────────────────────────────────────────────
            'og_site_name' => [
                'fieldset' => 'opengraph',
                'type'     => 'text',
                'label'    => 'OG Site Name',
                'hint'     => 'e.g., My Awesome Business',
                'showon'   => 'enable_opengraph:1',
                'description' => 'Organization/Site name shown on Facebook, Twitter, LinkedIn shares.',
            ],
            'og_image' => [
                'fieldset'  => 'opengraph',
                'type'      => 'media',
                'label'     => 'OG Default Image',
                'hint'      => '',
                'showon'    => 'enable_opengraph:1',
                'directory' => 'images',
                'description' => 'Default social sharing image. Can differ per language (e.g., hero banner with localized text overlay).',
            ],
            // ── Schema: Organization info ─────────────────────────────────────
            'org_name' => [
                'fieldset' => 'organization',
                'type'     => 'text',
                'label'    => 'Organization Name',
                'hint'     => 'e.g., My Company Name',
                'showon'   => '',
            ],
            'org_description' => [
                'fieldset' => 'organization',
                'type'     => 'textarea',
                'label'    => 'Organization Description',
                'hint'     => 'e.g., A short description of your business or organization.',
                'showon'   => '',
                'rows'     => '3',
            ],
            'org_logo' => [
                'fieldset'  => 'organization',
                'type'      => 'media',
                'label'     => 'Organization Logo',
                'hint'      => '',
                'showon'    => '',
                'directory'  => 'images',
                'description' => 'Logo for Schema.org and OpenGraph. Can differ per language (e.g., logo with localized text).',
            ],
            // ── Schema: Address ───────────────────────────────────────────────
            'schema_address_locality' => [
                'fieldset' => 'organization',
                'type'     => 'text',
                'label'    => 'City/Locality',
                'hint'     => 'e.g., New York',
                'showon'   => 'enable_schema:1[AND]schema_type:localbusiness,hotel',
            ],
            'schema_address_street' => [
                'fieldset' => 'organization',
                'type'     => 'text',
                'label'    => 'Street Address',
                'hint'     => 'e.g., 123 Main Street',
                'showon'   => 'enable_schema:1[AND]schema_type:localbusiness,hotel',
            ],
            // ── Schema: FAQ (Developer / Agency only) ────────────────────────
            'manual_faqs' => [
                'fieldset' => 'schema',
                'type'     => 'textarea',
                'label'    => 'Manual FAQ Items',
                'hint'     => '',
                'showon'   => 'enable_schema:1[AND]enable_manual_faqs:1[AND]license_tier:developer,agency',
                'rows'     => '6',
                'description' => 'FAQ in JSON format. Example: [{"question":"Q?","answer":"A."}]',
            ],
            // ── Schema: Events (Developer / Agency only) ──────────────────────
            'schema_events' => [
                'fieldset' => 'schema',
                'type'     => 'textarea',
                'label'    => 'Events (JSON)',
                'hint'     => '',
                'showon'   => 'enable_schema:1[AND]schema_events_enabled:1[AND]license_tier:developer,agency',
                'rows'     => '8',
                'description' => 'Events JSON per language. Format: [{"name":"Event","startDate":"2026-12-31T20:00:00+01:00"}]',
            ],
            // ── LlmsTxt: AI Search (Developer / Agency only) ─────────────────
            'llmstxt_custom_pages' => [
                'fieldset' => 'analytics',
                'type'     => 'textarea',
                'label'    => 'Custom Pages for LLMs.txt',
                'hint'     => '',
                'showon'   => 'llmstxt_enabled:1[AND]license_tier:developer,agency',
                'rows'     => '5',
                'description' => 'Extra pages as JSON per language. Format: [{"title":"Page","url":"/path","description":"Brief info"}]',
            ],
        ];

        // ── 3. Build XML fragment and load into form ───────────────────────────
        foreach ($groups as $baseField => $cfg) {
            $xmlParts = ['<form><fields name="params"><fieldset name="' . $cfg['fieldset'] . '">'];

            foreach ($langs as $lang) {
                $fieldName  = $baseField . '_' . $lang['code'];
                $isDefault  = !empty($lang['is_default']);
                $langSuffix = $isDefault
                    ? $lang['name'] . ' — ★ Default'
                    : $lang['name'];
                $fieldLabel = htmlspecialchars($cfg['label'] . ' (' . $langSuffix . ')', ENT_QUOTES, 'UTF-8');
                $fieldHint  = htmlspecialchars($cfg['hint'] ?? '', ENT_QUOTES, 'UTF-8');
                $fieldDesc  = $isDefault
                    ? htmlspecialchars(($cfg['description'] ?? '') . ' Other languages will use this value as fallback if left empty.', ENT_QUOTES, 'UTF-8')
                    : htmlspecialchars(($cfg['description'] ?? '') . ' Leave empty to use the Default language value.', ENT_QUOTES, 'UTF-8');

                // ── Non-default languages = ADVANCED + PRO only ────────────────
                // Default language stays visible at all times (basic UI).
                // Translations only show when "Show Advanced Options" is on.
                // OG multilingual fields (og_site_name, og_image) require Pro
                // license for non-default languages — multilanguage OG is a
                // Professional/Agency feature.
                $rawShowon = $cfg['showon'];
                $isOgMultilangField = in_array($baseField, ['og_site_name', 'og_image'], true);
                if (!$isDefault) {
                    $rawShowon = $rawShowon === ''
                        ? 'show_advanced_options:1'
                        : $rawShowon . '[AND]show_advanced_options:1';
                    if ($isOgMultilangField) {
                        $rawShowon .= '[AND]license_tier:developer,agency[OR]dev_license_preview:1';
                    }
                }
                $showOn     = htmlspecialchars($rawShowon, ENT_QUOTES, 'UTF-8');
                $rows       = isset($cfg['rows']) ? ' rows="' . $cfg['rows'] . '"' : '';
                $directory  = isset($cfg['directory']) ? ' directory="' . $cfg['directory'] . '"' : '';

                $xmlParts[] = '<field'
                    . ' name="' . $fieldName . '"'
                    . ' type="' . $cfg['type'] . '"'
                    . ' label="' . $fieldLabel . '"'
                    . ($fieldHint !== '' ? ' hint="' . $fieldHint . '"' : '')
                    . ($fieldDesc !== '' ? ' description="' . $fieldDesc . '"' : '')
                    . ' showon="' . $showOn . '"'
                    . $rows
                    . $directory
                    . ' />';
            }

            $xmlParts[] = '</fieldset></fields></form>';
            $form->load(implode('', $xmlParts), false);
        }
    }
}
