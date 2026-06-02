<?php
/**
 * AI Boost — YooTheme Pro Bridge Plugin
 *
 * Detects YooTheme Pro and enhances AI Boost SEO/AEO output:
 *  - FAQPage JSON-LD from YooTheme Accordion elements
 *  - ImageGallery JSON-LD from YooTheme Gallery elements
 *  - Dynamic Schema.org Organization field mapping from YooTheme menu item params
 *    (telephone, address, priceRange, latitude/longitude, openingHours)
 *  - Page title / description override via document title/description
 *    → fed into aiboost_social pipeline (og:title, og:description, twitter:*)
 *  - Sitemap exclusion: registers builder-only menu IDs with BridgeDetector
 *    → consumed by aiboost_sitemap via onAiBoostBeforeSitemapBuild (once per request)
 *  - onAiBoostGetSettingsTabs: actionable "YooTheme Pro" tab in AI Boost Settings
 *    with editable toggles + accordion selector; persisted via SettingsController
 *
 * Graceful degradation: absent YooTheme Pro or Free license = silent boot.
 *
 * Requires: AI Boost for Joomla (Basic or Professional license).
 *
 * @package     AiBoost\Plugin\System\AiBoostYootheme
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostYootheme\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;

class AiBoostYootheme extends CMSPlugin
{
    protected $autoloadLanguage = true;

    private bool  $detected       = false;
    private bool  $licensed       = false;
    private array $abSettings     = [];
    private bool  $settingsLoaded = false;

    // ── Bootstrap ───────────────────────────────────────────────────────────

    public function onAfterInitialise(): void
    {
        $this->bootLib();
        $this->detected = $this->detectYooTheme();
        if (!$this->detected) {
            return;
        }
        $this->licensed = $this->isLicensed();
        // NOTE: sitemap exclusions are registered ONLY in onAiBoostBeforeSitemapBuild
        // (triggered once by aiboost_sitemap) — never here, to avoid double-registration.
    }

    private function bootLib(): void
    {
        static $booted = false;
        if ($booted) {
            return;
        }
        $boot = JPATH_ADMINISTRATOR . '/components/com_aiboost/lib/autoload.php';
        if (file_exists($boot)) {
            require_once $boot;
        }
        $booted = true;
    }

    // ── Detection ───────────────────────────────────────────────────────────

    private function detectYooTheme(): bool
    {
        if (!class_exists('AiBoost\\Lib\\BridgeDetector')) {
            return false;
        }

        return \AiBoost\Lib\BridgeDetector::classExists('YOOtheme\\Application')
            || \AiBoost\Lib\BridgeDetector::isExtensionEnabled('yootheme', 'template', '')
            || \AiBoost\Lib\BridgeDetector::fileExists('templates/yootheme/config/config.json')
            || \AiBoost\Lib\BridgeDetector::isInstalled('yootheme');
    }

    // ── License gate ────────────────────────────────────────────────────────

    private function isLicensed(): bool
    {
        $s = $this->loadAiBoostSettings();

        if (!empty($s['dev_license_preview'])) {
            return true;
        }
        if (!empty($s['dev_force_free_tier'])) {
            return false;
        }

        $key  = trim((string) ($s['license_key']  ?? ''));
        $tier = strtolower(trim((string) ($s['license_tier'] ?? '')));

        if (!$key || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $key)) {
            return false;
        }

        return in_array($tier, ['basic', 'professional', 'starter', 'developer', 'agency'], true);
    }

    private function loadAiBoostSettings(): array
    {
        if ($this->settingsLoaded) {
            return $this->abSettings;
        }
        $this->settingsLoaded = true;
        if (!class_exists('AiBoost\\Lib\\PluginSettings', false)) {
            return $this->abSettings = [];
        }
        return $this->abSettings = \AiBoost\Lib\PluginSettings::all();
    }

    // ── onAiBoostBeforeSitemapBuild ─────────────────────────────────────────

    /**
     * Called ONCE by aiboost_sitemap via Joomla event dispatcher before building
     * the sitemap URL list. Registers builder-only menu IDs for exclusion.
     *
     * This is the ONLY place exclusions are registered — NOT in onAfterInitialise.
     */
    public function onAiBoostBeforeSitemapBuild(): void
    {
        if (!$this->detected || !$this->licensed) {
            return;
        }
        if ((int) $this->params->get('yootheme_sitemap_exclude_builder', 1)) {
            $this->registerSitemapExclusions();
        }
    }

    /**
     * Register sitemap exclusions for menu items that do not represent indexable pages.
     *
     * Two categories are excluded:
     *
     * 1. Non-navigable generic types (separator, heading, url, alias):
     *    These do not represent real Joomla pages regardless of template.
     *
     * 2. YooTheme builder-only component pages (type='component', link contains
     *    'option=com_yootheme'):
     *    These are menu items that directly serve YooTheme builder output without
     *    standalone content — e.g. builder landing pages, theme sections. They are
     *    typically excluded from sitemap because their content is builder-managed,
     *    not article/category content that search engines index separately.
     *    Note: regular YooTheme-styled articles/categories use Joomla core component
     *    links (option=com_content) and are NOT excluded.
     *
     * This exclusion only applies to the aiboost_sitemap menu loop — articles and
     * categories are never affected.
     */
    private function registerSitemapExclusions(): void
    {
        if (!class_exists('AiBoost\\Lib\\BridgeDetector')) {
            return;
        }

        try {
            $db = Factory::getDbo();

            // 1. Non-navigable generic types
            $q1 = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__menu'))
                ->where($db->quoteName('published') . ' = 1')
                ->where($db->quoteName('client_id') . ' = 0')
                ->where($db->quoteName('type') . ' IN (' . implode(',', [
                    $db->quote('separator'),
                    $db->quote('heading'),
                    $db->quote('url'),
                    $db->quote('alias'),
                ]) . ')');
            $db->setQuery($q1);
            $genericIds = $db->loadColumn() ?: [];

            // 2. YooTheme builder component pages (link contains option=com_yootheme)
            $q2 = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__menu'))
                ->where($db->quoteName('published') . ' = 1')
                ->where($db->quoteName('client_id') . ' = 0')
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where($db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_yootheme%'));
            $db->setQuery($q2);
            $builderIds = $db->loadColumn() ?: [];

            $allIds = array_unique(array_merge(
                array_map('intval', $genericIds),
                array_map('intval', $builderIds)
            ));

            if (!empty($allIds)) {
                \AiBoost\Lib\BridgeDetector::excludeMenuIds($allIds);
            }
        } catch (\Throwable $e) {
            error_log('[AiBoost] YooTheme sitemap exclusion failed: ' . $e->getMessage());
        }
    }

    // ── onAiBoostGetSettingsTabs ────────────────────────────────────────────

    /**
     * Inject an actionable "YooTheme Pro" tab into AI Boost Settings.
     *
     * Contains editable toggles + accordion selector with [data-addon-plugin] /
     * [data-addon-param] attributes. settings.js collects these values and sends
     * them as addon_params JSON; SettingsController::saveAddonPluginParams()
     * persists them into #__extensions.params for the aiboost_yootheme plugin.
     *
     * Returns null when YooTheme is not detected (tab is not shown at all).
     */
    public function onAiBoostGetSettingsTabs(): ?array
    {
        if (!$this->detected) {
            return null;
        }

        $licOk    = $this->licensed;
        $licBadge = $licOk
            ? '<span class="badge bg-success">Licensed</span>'
            : '<span class="badge bg-warning text-dark">Free — upgrade to Basic or Professional</span>';

        // Current param values for pre-populating form fields
        $faqOn       = (int) $this->params->get('yootheme_faq_enabled', 1);
        $galleryOn   = (int) $this->params->get('yootheme_gallery_enabled', 1);
        $metaOn      = (int) $this->params->get('yootheme_meta_override', 1);
        $schemaOn    = (int) $this->params->get('yootheme_schema_mapping', 1);
        $excludeOn   = (int) $this->params->get('yootheme_sitemap_exclude_builder', 1);
        $accordion   = htmlspecialchars(
            (string) $this->params->get('yootheme_accordion_selector', '.uk-accordion'),
            ENT_QUOTES,
            'UTF-8'
        );

        $pluginMgrUrl = 'index.php?option=com_plugins&filter[folder]=system&filter[search]=aiboost_yootheme';

        // Helper: toggle row with radio Yes/No inputs (data-addon-param for JS pickup)
        $toggle = function (
            string $label,
            string $paramName,
            int    $value,
            bool   $enabled = true
        ) use ($licOk): string {
            $disabled = (!$licOk || !$enabled) ? ' disabled' : '';
            $yesChk   = $value ? ' checked' : '';
            $noChk    = $value ? '' : ' checked';
            $dimCls   = (!$licOk || !$enabled) ? ' text-muted' : '';
            return '<tr class="' . $dimCls . '">'
                . '<td class="pe-3 align-middle">' . $label . '</td>'
                . '<td class="align-middle">'
                . '<div class="btn-group btn-group-sm" role="group">'
                . '<input type="radio" class="btn-check" id="' . $paramName . '_yes" '
                . 'data-addon-param="' . $paramName . '" value="1"' . $yesChk . $disabled . '>'
                . '<label class="btn btn-outline-success" for="' . $paramName . '_yes">Yes</label>'
                . '<input type="radio" class="btn-check" id="' . $paramName . '_no" '
                . 'data-addon-param="' . $paramName . '" value="0"' . $noChk . $disabled . '>'
                . '<label class="btn btn-outline-secondary" for="' . $paramName . '_no">No</label>'
                . '</div></td></tr>';
        };

        $html = '<div data-addon-plugin="aiboost_yootheme">'
            . '<div class="ab-section-title">YooTheme Pro — Settings</div>'
            . '<p><strong>YooTheme Pro detected:</strong> <span class="badge bg-success">Yes</span>'
            . ' &nbsp; <strong>License:</strong> ' . $licBadge . '</p>'
            . (!$licOk
                ? '<div class="alert alert-warning py-2 mb-3">Upgrade to Basic or Professional to activate these features. '
                  . '<a href="https://aiboostnow.com/pricing" target="_blank" rel="noopener" class="alert-link">View plans</a></div>'
                : '')
            . '<table class="table table-sm table-bordered mb-3" style="max-width:540px;">'
            . '<thead><tr><th>Feature</th><th style="width:160px;">Setting</th></tr></thead>'
            . '<tbody>'
            . $toggle('FAQPage schema from Accordion elements', 'yootheme_faq_enabled', $faqOn)
            . $toggle('ImageGallery schema from Gallery elements', 'yootheme_gallery_enabled', $galleryOn)
            . $toggle('Dynamic Schema.org field mapping from menu params', 'yootheme_schema_mapping', $schemaOn)
            . $toggle('Page meta override → og:title / og:description', 'yootheme_meta_override', $metaOn)
            . $toggle('Exclude builder-only pages from sitemap', 'yootheme_sitemap_exclude_builder', $excludeOn)
            . '</tbody></table>'
            . '<div class="mb-3" style="max-width:420px;">'
            . '<label class="form-label fw-semibold" for="yootheme_accordion_selector">Accordion CSS selector</label>'
            . '<input type="text" class="form-control form-control-sm" '
            . 'id="yootheme_accordion_selector" '
            . 'data-addon-param="yootheme_accordion_selector" '
            . 'value="' . $accordion . '"'
            . ($licOk ? '' : ' disabled')
            . '>'
            . '<div class="form-text text-muted">CSS selector used to detect YooTheme Accordion elements for FAQPage schema. Default: <code>.uk-accordion</code></div>'
            . '</div>'
            . '<p class="text-muted small mb-0">These settings are saved when you click <strong>Save Settings</strong> above. '
            . 'Advanced options: <a href="' . $pluginMgrUrl . '" class="alert-link" target="_blank">AI Boost for YooTheme Pro plugin</a>.</p>'
            . '</div>';

        return [
            'id'    => 'tab-yootheme',
            'label' => 'YooTheme Pro',
            'svg'   => '<svg class="ab-ti" width="13" height="13" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8.186 1.113a.5.5 0 0 0-.372 0L1.846 3.5l2.404.961L10.404 2l-2.218-.887zm3.564 1.426L5.596 5 8 5.961 14.154 3.5l-2.404-.961zm3.25 1.7-6.5 2.6v7.922l6.5-2.6V4.24zM7.5 14.762V6.838L1 4.239v7.923l6.5 2.6z"/></svg>',
            'html'  => $html,
        ];
    }

    // ── onAfterRoute — page meta override ───────────────────────────────────

    /**
     * Read page title/description from YooTheme menu item params and set them
     * on the document. aiboost_social reads document title/description in
     * onBeforeCompileHead and uses them for og:title, og:description, twitter:*.
     *
     * Called in onAfterRoute (before onBeforeCompileHead) so the values are
     * available for the aiboost_social pipeline.
     */
    public function onAfterRoute(): void
    {
        if (!$this->detected || !$this->licensed) {
            return;
        }

        $app = Factory::getApplication();
        if (!$app->isClient('site')) {
            return;
        }

        $meta = (int) $this->params->get('yootheme_meta_override', 1);
        $schema = (int) $this->params->get('yootheme_schema_mapping', 1);

        if (!$meta && !$schema) {
            return;
        }

        try {
            $menu = $app->getMenu()->getActive();
            if (!$menu) {
                return;
            }

            $params = $menu->getParams();
            $doc    = $app->getDocument();
            if (!$doc) {
                return;
            }

            // ── Meta override (feeds aiboost_social OG pipeline) ────────────
            if ($meta) {
                $metaTitle = trim((string) (
                    $params->get('page_title', '')
                    ?: $params->get('pageTitle', '')
                    ?: $params->get('yoo_title', '')
                ));
                $metaDesc  = trim((string) (
                    $params->get('menu-meta_description', '')
                    ?: $params->get('metaDescription', '')
                    ?: $params->get('yoo_description', '')
                ));
                if ($metaTitle) {
                    $doc->setTitle($metaTitle);
                }
                if ($metaDesc) {
                    $doc->setDescription($metaDesc);
                }
            }

            // ── Dynamic Schema.org field mapping ────────────────────────────
            if ($schema) {
                $this->injectMappedSchema($doc, $params);
            }
        } catch (\Throwable $e) {
            error_log('[AiBoost] YooTheme schema/meta failed: ' . $e->getMessage());
        }
    }

    /**
     * Map YooTheme menu item params to a typed Schema.org entity and inject as JSON-LD.
     * Type is detected from menu params: event→Event, product→Product, default→Organization.
     */
    private function injectMappedSchema($document, \Joomla\Registry\Registry $params): void
    {
        // Detect schema type hint from menu params
        $typeHint = strtolower($this->firstParam($params, [
            'yoo_schema_type', 'schemaType', 'schema_type',
            'yoo_content_type', 'view', 'layout',
        ]));

        if (str_contains($typeHint, 'event')) {
            $schema = $this->buildEventSchema($params);
        } elseif (str_contains($typeHint, 'product')) {
            $schema = $this->buildProductSchema($params);
        } else {
            $schema = $this->buildOrganizationSupplementSchema($params);
        }

        if (empty($schema)) {
            return;
        }

        $document->addCustomTag(
            '<script type="application/ld+json">' . "\n"
            . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            . "\n" . '</script>'
        );
    }

    /**
     * Build Schema.org Event from menu params. Returns [] when name+startDate both absent.
     * Reads best-effort param keys (yoo_event_name, startDate, yoo_location, etc.).
     */
    private function buildEventSchema(\Joomla\Registry\Registry $params): array
    {
        $name      = $this->firstParam($params, ['yoo_event_name', 'event_name', 'eventName', 'page_title', 'pageTitle']);
        $startDate = $this->firstParam($params, ['yoo_start_date', 'startDate', 'event_start', 'date_start', 'yoo_date']);
        $endDate   = $this->firstParam($params, ['yoo_end_date', 'endDate', 'event_end', 'date_end']);
        $locName   = $this->firstParam($params, ['yoo_location', 'location', 'venue', 'event_location', 'event_venue']);
        $locAddr   = $this->firstParam($params, ['yoo_location_address', 'locationAddress', 'event_address', 'venue_address']);
        $organizer = $this->firstParam($params, ['yoo_organizer', 'organizer', 'event_organizer']);
        $image     = $this->firstParam($params, ['yoo_image', 'image', 'event_image', 'yoo_thumbnail']);
        $url       = $this->firstParam($params, ['yoo_event_url', 'eventUrl', 'event_url']);
        $status    = $this->firstParam($params, ['yoo_event_status', 'eventStatus', 'event_status']);
        $mode      = $this->firstParam($params, ['yoo_event_mode', 'eventAttendanceMode', 'event_mode']);

        if (!$name && !$startDate) {
            return [];
        }

        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Event',
            'eventStatus' => $status ?: 'EventScheduled',
        ];
        if ($name)  { $schema['name']      = $name; }
        if ($url)   { $schema['url']       = $url; }
        if ($image) { $schema['image']     = $image; }

        if ($startDate) {
            $schema['startDate'] = $startDate;
        }
        if ($endDate) {
            $schema['endDate'] = $endDate;
        }

        // EventAttendanceMode
        $modeMap = [
            'online'  => 'OnlineEventAttendanceMode',
            'offline' => 'OfflineEventAttendanceMode',
            'mixed'   => 'MixedEventAttendanceMode',
        ];
        foreach ($modeMap as $key => $val) {
            if (str_contains(strtolower($mode), $key)) {
                $schema['eventAttendanceMode'] = 'https://schema.org/' . $val;
                break;
            }
        }

        // Location
        if ($locName || $locAddr) {
            $loc = ['@type' => 'Place'];
            if ($locName) { $loc['name']    = $locName; }
            if ($locAddr) { $loc['address'] = $locAddr; }
            $schema['location'] = $loc;
        }

        if ($organizer) {
            $schema['organizer'] = ['@type' => 'Organization', 'name' => $organizer];
        }

        return $schema;
    }

    /**
     * Build Schema.org Product from menu params. Returns [] when name+sku+price all absent.
     * Reads best-effort param keys (yoo_product_name, sku, yoo_price, yoo_brand, etc.).
     */
    private function buildProductSchema(\Joomla\Registry\Registry $params): array
    {
        $name         = $this->firstParam($params, ['yoo_product_name', 'product_name', 'productName', 'page_title', 'pageTitle']);
        $sku          = $this->firstParam($params, ['yoo_sku', 'sku', 'product_sku']);
        $brand        = $this->firstParam($params, ['yoo_brand', 'brand', 'product_brand']);
        $price        = $this->firstParam($params, ['yoo_price', 'price', 'product_price']);
        $currency     = $this->firstParam($params, ['yoo_currency', 'currency', 'priceCurrency']) ?: 'EUR';
        $availability = $this->firstParam($params, ['yoo_availability', 'availability', 'product_availability']);
        $image        = $this->firstParam($params, ['yoo_image', 'image', 'product_image', 'yoo_thumbnail']);
        $description  = $this->firstParam($params, ['yoo_description', 'description', 'product_description']);

        if (!$name && !$sku && !$price) {
            return [];
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'Product',
        ];
        if ($name)        { $schema['name']        = $name; }
        if ($sku)         { $schema['sku']          = $sku; }
        if ($description) { $schema['description']  = $description; }
        if ($image)       { $schema['image']        = $image; }
        if ($brand)       { $schema['brand']        = ['@type' => 'Brand', 'name' => $brand]; }

        if ($price) {
            $availMap = [
                'outofstock'  => 'OutOfStock',
                'discontinued' => 'Discontinued',
                'preorder'    => 'PreOrder',
                'soldout'     => 'SoldOut',
            ];
            $availKey = strtolower(str_replace(' ', '', $availability));
            $avail = $availMap[$availKey] ?? 'InStock';
            $schema['offers'] = [
                '@type'         => 'Offer',
                'price'         => $price,
                'priceCurrency' => $currency,
                'availability'  => 'https://schema.org/' . $avail,
            ];
        }

        return $schema;
    }

    /**
     * Build supplemental Schema.org Organization contact/location fields from menu params.
     * Used when no Event or Product type hint is present. Returns [] when no fields found.
     */
    private function buildOrganizationSupplementSchema(\Joomla\Registry\Registry $params): array
    {
        $telephone  = $this->firstParam($params, ['telephone', 'phone', 'yoo_phone']);
        $street     = $this->firstParam($params, ['address', 'street', 'yoo_address', 'streetAddress']);
        $city       = $this->firstParam($params, ['city', 'locality', 'addressLocality']);
        $zip        = $this->firstParam($params, ['zip', 'postal_code', 'postalCode']);
        $country    = $this->firstParam($params, ['country', 'addressCountry']);
        $priceRange = $this->firstParam($params, ['price_range', 'priceRange', 'yoo_pricerange']);
        $lat        = $this->firstParam($params, ['latitude', 'lat', 'yoo_lat']);
        $lng        = $this->firstParam($params, ['longitude', 'lng', 'yoo_lng']);

        $hasData = ($telephone || $street || $city || $zip || $country || $priceRange || ($lat && $lng));
        if (!$hasData) {
            return [];
        }

        $schema = ['@context' => 'https://schema.org', '@type' => 'Organization'];

        if ($telephone) { $schema['telephone'] = $telephone; }
        if ($priceRange) { $schema['priceRange'] = $priceRange; }

        if ($street || $city || $zip || $country) {
            $address = ['@type' => 'PostalAddress'];
            if ($street)  { $address['streetAddress']  = $street; }
            if ($city)    { $address['addressLocality'] = $city; }
            if ($zip)     { $address['postalCode']      = $zip; }
            if ($country) { $address['addressCountry']  = $country; }
            $schema['address'] = $address;
        }

        if ($lat && $lng) {
            $schema['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $lat, 'longitude' => $lng];
        }

        return $schema;
    }

    /**
     * Return the first non-empty string value from a list of param key candidates.
     */
    private function firstParam(\Joomla\Registry\Registry $params, array $keys): string
    {
        foreach ($keys as $key) {
            $val = trim((string) $params->get($key, ''));
            if ($val !== '') {
                return $val;
            }
        }
        return '';
    }

    // ── onAfterRender — Accordion FAQ + Gallery Schema ───────────────────────

    public function onAfterRender(): void
    {
        if (!$this->detected || !$this->licensed) {
            return;
        }

        $app = Factory::getApplication();
        if (!$app->isClient('site')) {
            return;
        }

        $body = $app->getBody();
        if (!$body) {
            return;
        }

        $injections = [];

        if ((int) $this->params->get('yootheme_faq_enabled', 1)) {
            $selector = trim((string) $this->params->get('yootheme_accordion_selector', '.uk-accordion'));
            $schema   = $this->buildAccordionFaqSchema($body, $selector);
            if (!empty($schema)) {
                $injections[] = $this->encodeSchema($schema);
            }
        }

        if ((int) $this->params->get('yootheme_gallery_enabled', 1)) {
            $schema = $this->buildGallerySchema($body);
            if (!empty($schema)) {
                $injections[] = $this->encodeSchema($schema);
            }
        }

        if (empty($injections)) {
            return;
        }

        $scriptBlock = implode("\n", $injections);
        $body = str_replace('</head>', $scriptBlock . "\n</head>", $body, $count);
        if ($count) {
            $app->setBody($body);
        }
    }

    // ── Accordion → FAQPage Schema ──────────────────────────────────────────

    private function buildAccordionFaqSchema(string $html, string $containerClass): array
    {
        $cls = ltrim($containerClass, '.');
        if (!preg_match(
            '/<(?:ul|div)[^>]+class="[^"]*' . preg_quote($cls, '/') . '[^"]*"[^>]*>(.*?)<\/(?:ul|div)>/si',
            $html,
            $containerMatch
        )) {
            return [];
        }

        $faqs = [];
        preg_match_all(
            '/<(?:a|button|h\d)[^>]*class="[^"]*uk-accordion-title[^"]*"[^>]*>(.*?)<\/(?:a|button|h\d)>.*?'
            . '<div[^>]*class="[^"]*uk-accordion-content[^"]*"[^>]*>(.*?)<\/div>/si',
            $containerMatch[1],
            $matches
        );

        for ($i = 0, $n = count($matches[1]); $i < $n; $i++) {
            $q = trim(strip_tags($matches[1][$i]));
            $a = trim(strip_tags($matches[2][$i]));
            if ($q && $a) {
                $faqs[] = [
                    '@type'          => 'Question',
                    'name'           => $q,
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a],
                ];
            }
        }

        if (empty($faqs)) {
            return [];
        }

        return [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $faqs,
        ];
    }

    // ── Gallery → ImageGallery Schema ───────────────────────────────────────

    private function buildGallerySchema(string $html): array
    {
        $images  = [];
        $baseUrl = Uri::getInstance()->getScheme() . '://' . Uri::getInstance()->getHost();

        preg_match_all(
            '/<(?:a|figure)[^>]*data-caption="([^"]*)"[^>]*>.*?<img[^>]+src="([^"]+)"[^>]*>/si',
            $html,
            $matches
        );

        for ($i = 0, $n = count($matches[1]); $i < $n; $i++) {
            $caption = trim(strip_tags(html_entity_decode($matches[1][$i], ENT_QUOTES, 'UTF-8')));
            $src     = trim($matches[2][$i]);

            if (!$src || str_contains($src, 'data:') || str_contains($src, 'placeholder')) {
                continue;
            }

            if (!str_starts_with($src, 'http')) {
                $src = $baseUrl . '/' . ltrim($src, '/');
            }

            $img = ['@type' => 'ImageObject', 'url' => $src];
            if ($caption) {
                $img['caption'] = $caption;
            }
            $images[] = $img;
        }

        if (empty($images)) {
            return [];
        }

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'ImageGallery',
            'associatedMedia' => $images,
        ];
    }

    private function encodeSchema(array $schema): string
    {
        return '<script type="application/ld+json">' . "\n"
            . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            . "\n" . '</script>';
    }
}
