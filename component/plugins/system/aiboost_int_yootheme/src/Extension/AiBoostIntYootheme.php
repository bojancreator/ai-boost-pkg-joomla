<?php
/**
 * AI Boost — YOOtheme Pro Integration Plugin
 *
 * SDK port of the legacy `aiboost_yootheme` bridge (which checked a dead
 * `license_tier` model and injected JSON-LD via addCustomTag() / regex
 * `</head>`). This version:
 *
 *   1. Extends AbstractIntegrationPlugin → inherits lib autoloader,
 *      BridgeDetector host check, discovery event, and the master on/off
 *      switch (isActive()).
 *   2. Gates runtime emission on an ACTIVE Pro licence via
 *      PluginRegistry::hasPro('int_yootheme') — never the old license_tier.
 *   3. Routes ALL JSON-LD through the consolidated AI Boost head block:
 *      - menu-param schema (Event/Product/Organization) via
 *        HeadBlockBuilder::pushSection('schema', …) in onBeforeCompileHead;
 *      - body-dependent FAQ/gallery schema via the SDK
 *        onAiBoostFilterHeadOutput filter, which runs INSIDE
 *        HeadBlockBuilder::finalize() once the body is rendered. NEVER a
 *        regex `</head>` splice.
 *   4. Contributes its settings via onAiBoostRegisterFields (integration
 *      tag 'yootheme') instead of a legacy onAiBoostGetSettingsTabs HTML tab.
 *
 * Coexistence rule: only ACTIVATES when YOOtheme Pro is present AND the
 * integration is switched on AND Pro is licensed. It never edits YOOtheme
 * data — it only reads page content/menu params to add schema and meta.
 *
 * @package     AiBoost\Plugin\System\AiBoostIntYootheme
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostIntYootheme\Extension;

defined('_JEXEC') or die;

use AiBoost\Lib\ConflictManager;
use AiBoost\Lib\HeadBlockBuilder;
use AiBoost\Lib\Integration\AbstractIntegrationPlugin;
use AiBoost\Lib\Integration\FilterResult;
use AiBoost\Lib\Integration\IntegrationDescriptor;
use AiBoost\Lib\Integration\Sdk;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

class AiBoostIntYootheme extends AbstractIntegrationPlugin
{
    /** JSON-LD encode flags. JSON_HEX_TAG/AMP close the stored-XSS hole a raw
     *  `</script>` inside page content would otherwise open. */
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP;

    /** Cached result of libReady() — null until first probed. */
    private ?bool $libReady = null;

    protected function describe(): IntegrationDescriptor
    {
        return new IntegrationDescriptor(
            key:            'yootheme',
            pluginElement:  'aiboost_int_yootheme',
            label:          'YOOtheme Pro',
            vendor:         'YOOtheme',
            category:       'Page Builder',
            description:    'Premium Joomla theme and page builder. AI Boost reads YOOtheme Pro page content for FAQ and gallery Schema.org and uses page title/description for per-page meta.',
            hostType:       'template',
            hostElement:    'yootheme',
            sdkVersion:     Sdk::SDK_VERSION,
            minCoreVersion: '0.58.0',
            version:        '0.74.0',
            learnUrl:       'https://yootheme.com/marketplace/yootheme-pro',
            addonUrl:       'https://aiboostnow.com/integrations/yootheme',
            icon:           'icon-puzzle',
            claimsSlots:    [
                ConflictManager::SLOT_SCHEMA_FAQ,
            ],
        );
    }

    // ── onAiBoostRegisterFields (manifest contributions) ───────────────────

    public function onAiBoostRegisterFields(): array
    {
        if (!$this->libReady()) {
            return [];
        }

        return [
            $this->manifestField('yootheme_faq_enabled', 'schema', 'yootheme', 'YOOtheme: FAQ schema from Accordion'),
            $this->manifestField('yootheme_gallery_enabled', 'schema', 'yootheme', 'YOOtheme: ImageGallery schema from Gallery'),
            $this->manifestField('yootheme_schema_mapping', 'schema', 'yootheme', 'YOOtheme: Schema.org mapping from menu params'),
            $this->manifestField('yootheme_accordion_selector', 'schema', 'yootheme', 'YOOtheme: Accordion CSS selector', 'text', '.uk-accordion', [
                'description' => 'CSS selector used to detect YOOtheme Accordion elements for FAQPage schema.',
            ]),
            $this->manifestField('yootheme_meta_override', 'social', 'yootheme', 'YOOtheme: override OpenGraph from page meta'),
            $this->manifestField('yootheme_sitemap_exclude_builder', 'sitemap', 'yootheme', 'YOOtheme: exclude builder-only pages from sitemap'),
        ];
    }

    /** @param array<string,mixed> $extra */
    private function manifestField(
        string $key,
        string $tab,
        string $section,
        string $label,
        string $type = 'toggle',
        string $default = '1',
        array $extra = []
    ): array {
        return array_merge([
            'key'         => $key,
            'tab'         => $tab,
            'section'     => $section,
            'label'       => $label,
            'type'        => $type,
            'default'     => $default,
            'tier'        => 'free',
            'sku'         => 'core',
            'integration' => 'yootheme',
        ], $extra);
    }

    // ── Runtime gate ───────────────────────────────────────────────────────

    /**
     * The bridge emits only when: the host (YOOtheme template) is present,
     * the admin left this integration switched on, AND an AI Boost Pro
     * licence is active. The YOOtheme enhancements are a Pro feature.
     */
    private function bridgeOn(): bool
    {
        if (!$this->libReady() || !$this->isActive()) {
            return false;
        }
        try {
            return \AiBoost\Lib\PluginRegistry::hasPro('int_yootheme');
        } catch (\Throwable) {
            return false;
        }
    }

    private function onSiteHtml(): bool
    {
        $app = Factory::getApplication();
        if (!$app->isClient('site')) {
            return false;
        }
        $doc = $app->getDocument();
        return $doc && $doc->getType() === 'html';
    }

    // ── onAfterRoute — page meta override ──────────────────────────────────

    /**
     * Copy the YOOtheme menu page title/description onto the document so the
     * aiboost_social pipeline (which reads them in onBeforeCompileHead) emits
     * matching og:title / og:description. Runs in onAfterRoute so the values
     * are set before any onBeforeCompileHead handler reads them.
     */
    public function onAfterRoute(): void
    {
        if (!$this->bridgeOn() || !$this->onSiteHtml()) {
            return;
        }
        if ((string) $this->readAiBoostSetting('yootheme_meta_override', '1') === '0') {
            return;
        }

        try {
            $menu = Factory::getApplication()->getMenu()->getActive();
            if (!$menu) {
                return;
            }
            $params = $menu->getParams();
            $doc    = Factory::getApplication()->getDocument();
            if (!$doc) {
                return;
            }

            $title = $this->firstParam($params, ['page_title', 'pageTitle', 'yoo_title']);
            $desc  = $this->firstParam($params, ['menu-meta_description', 'metaDescription', 'yoo_description']);
            if ($title !== '') {
                $doc->setTitle($title);
            }
            if ($desc !== '') {
                $doc->setDescription($desc);
            }
        } catch (\Throwable) {
            // graceful degradation
        }
    }

    // ── onBeforeCompileHead — menu-param Schema.org ────────────────────────

    /**
     * Build a typed Schema.org entity from YOOtheme menu params and push it
     * into the consolidated head block's Schema section. Replaces the legacy
     * addCustomTag() injection.
     */
    public function onBeforeCompileHead(): void
    {
        if (!$this->bridgeOn() || !$this->onSiteHtml()) {
            return;
        }
        if ((string) $this->readAiBoostSetting('yootheme_schema_mapping', '1') === '0') {
            return;
        }
        if (!class_exists(HeadBlockBuilder::class)) {
            return;
        }

        try {
            $menu = Factory::getApplication()->getMenu()->getActive();
            if (!$menu) {
                return;
            }
            $schema = $this->buildMappedSchema($menu->getParams());
            if ($schema === []) {
                return;
            }
            HeadBlockBuilder::pushSection(HeadBlockBuilder::SECTION_SCHEMA, $this->encodeSchema($schema));
        } catch (\Throwable) {
            // graceful degradation
        }
    }

    // ── onAiBoostFilterHeadOutput — body-dependent FAQ + gallery ───────────

    /**
     * SDK filter fired INSIDE HeadBlockBuilder::finalize(), after the page
     * body is rendered. This is where body-dependent schema belongs: we read
     * the YOOtheme Accordion / Gallery markup from the finished body and
     * append the JSON-LD to the consolidated head block — no `</head>` splice.
     *
     * Note: finalize() only fires (and so this filter only runs) when AI Boost
     * has head content to emit. On a configured YOOtheme site the Schema
     * plugin's identity JSON-LD guarantees that; a site with ALL AI Boost head
     * output disabled would also suppress this enhancement, which is the
     * intended behaviour.
     */
    public function onAiBoostFilterHeadOutput(array $input, FilterResult $result): void
    {
        if (!$this->bridgeOn() || !$this->onSiteHtml()) {
            return;
        }

        $body = (string) Factory::getApplication()->getBody();
        if ($body === '') {
            return;
        }

        $blocks = [];

        if ((string) $this->readAiBoostSetting('yootheme_faq_enabled', '1') !== '0') {
            $selector = trim((string) $this->readAiBoostSetting('yootheme_accordion_selector', '.uk-accordion'));
            $faq = $this->buildAccordionFaqSchema($body, $selector !== '' ? $selector : '.uk-accordion');
            if ($faq !== []) {
                $blocks[] = $this->encodeSchema($faq);
            }
        }

        if ((string) $this->readAiBoostSetting('yootheme_gallery_enabled', '1') !== '0') {
            $gallery = $this->buildGallerySchema($body);
            if ($gallery !== []) {
                $blocks[] = $this->encodeSchema($gallery);
            }
        }

        if ($blocks === []) {
            return;
        }

        $out  = $result->getOutput();
        $html = (string) ($out['html'] ?? '');
        $out['html'] = $html . "\n" . implode("\n", $blocks);
        $result->setOutput($out, 'aiboost_int_yootheme', 'YOOtheme FAQ/gallery schema from page body');
    }

    // ── onAiBoostBeforeSitemapBuild — exclude builder-only pages ───────────

    public function onAiBoostBeforeSitemapBuild(): void
    {
        if (!$this->bridgeOn()) {
            return;
        }
        if ((string) $this->readAiBoostSetting('yootheme_sitemap_exclude_builder', '1') === '0') {
            return;
        }
        $this->registerSitemapExclusions();
    }

    private function registerSitemapExclusions(): void
    {
        if (!class_exists('AiBoost\\Lib\\BridgeDetector')
            || !method_exists('AiBoost\\Lib\\BridgeDetector', 'excludeMenuIds')) {
            return;
        }

        try {
            $db = Factory::getDbo();

            // Non-navigable generic menu types.
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
            $genericIds = $db->setQuery($q1)->loadColumn() ?: [];

            // YOOtheme builder-only component pages.
            $q2 = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__menu'))
                ->where($db->quoteName('published') . ' = 1')
                ->where($db->quoteName('client_id') . ' = 0')
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where($db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_yootheme%'));
            $builderIds = $db->setQuery($q2)->loadColumn() ?: [];

            $allIds = array_values(array_unique(array_merge(
                array_map('intval', (array) $genericIds),
                array_map('intval', (array) $builderIds)
            )));

            if ($allIds !== []) {
                \AiBoost\Lib\BridgeDetector::excludeMenuIds($allIds);
            }
        } catch (\Throwable $e) {
            error_log('[AiBoost] YOOtheme sitemap exclusion failed: ' . $e->getMessage());
        }
    }

    // ── Schema builders (ported from the legacy bridge) ────────────────────

    /** @return array<string,mixed> */
    private function buildMappedSchema(Registry $params): array
    {
        $typeHint = strtolower($this->firstParam($params, [
            'yoo_schema_type', 'schemaType', 'schema_type',
            'yoo_content_type', 'view', 'layout',
        ]));

        if (str_contains($typeHint, 'event')) {
            return $this->buildEventSchema($params);
        }
        if (str_contains($typeHint, 'product')) {
            return $this->buildProductSchema($params);
        }
        return $this->buildOrganizationSupplementSchema($params);
    }

    /** @return array<string,mixed> */
    private function buildEventSchema(Registry $params): array
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

        if ($name === '' && $startDate === '') {
            return [];
        }

        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Event',
            'eventStatus' => $status !== '' ? $status : 'EventScheduled',
        ];
        if ($name !== '')      { $schema['name']      = $name; }
        if ($url !== '')       { $schema['url']       = $url; }
        if ($image !== '')     { $schema['image']     = $image; }
        if ($startDate !== '') { $schema['startDate'] = $startDate; }
        if ($endDate !== '')   { $schema['endDate']   = $endDate; }

        $modeMap = [
            'online'  => 'OnlineEventAttendanceMode',
            'offline' => 'OfflineEventAttendanceMode',
            'mixed'   => 'MixedEventAttendanceMode',
        ];
        foreach ($modeMap as $key => $val) {
            if ($mode !== '' && str_contains(strtolower($mode), $key)) {
                $schema['eventAttendanceMode'] = 'https://schema.org/' . $val;
                break;
            }
        }

        if ($locName !== '' || $locAddr !== '') {
            $loc = ['@type' => 'Place'];
            if ($locName !== '') { $loc['name']    = $locName; }
            if ($locAddr !== '') { $loc['address'] = $locAddr; }
            $schema['location'] = $loc;
        }
        if ($organizer !== '') {
            $schema['organizer'] = ['@type' => 'Organization', 'name' => $organizer];
        }

        return $schema;
    }

    /** @return array<string,mixed> */
    private function buildProductSchema(Registry $params): array
    {
        $name         = $this->firstParam($params, ['yoo_product_name', 'product_name', 'productName', 'page_title', 'pageTitle']);
        $sku          = $this->firstParam($params, ['yoo_sku', 'sku', 'product_sku']);
        $brand        = $this->firstParam($params, ['yoo_brand', 'brand', 'product_brand']);
        $price        = $this->firstParam($params, ['yoo_price', 'price', 'product_price']);
        $currency     = $this->firstParam($params, ['yoo_currency', 'currency', 'priceCurrency']);
        $availability = $this->firstParam($params, ['yoo_availability', 'availability', 'product_availability']);
        $image        = $this->firstParam($params, ['yoo_image', 'image', 'product_image', 'yoo_thumbnail']);
        $description  = $this->firstParam($params, ['yoo_description', 'description', 'product_description']);

        if ($name === '' && $sku === '' && $price === '') {
            return [];
        }

        $schema = ['@context' => 'https://schema.org', '@type' => 'Product'];
        if ($name !== '')        { $schema['name']        = $name; }
        if ($sku !== '')         { $schema['sku']         = $sku; }
        if ($description !== '') { $schema['description'] = $description; }
        if ($image !== '')       { $schema['image']       = $image; }
        if ($brand !== '')       { $schema['brand']       = ['@type' => 'Brand', 'name' => $brand]; }

        if ($price !== '') {
            $availMap = [
                'outofstock'   => 'OutOfStock',
                'discontinued' => 'Discontinued',
                'preorder'     => 'PreOrder',
                'soldout'      => 'SoldOut',
            ];
            $availKey = strtolower(str_replace(' ', '', $availability));
            $schema['offers'] = [
                '@type'         => 'Offer',
                'price'         => $price,
                'priceCurrency' => $currency !== '' ? $currency : 'EUR',
                'availability'  => 'https://schema.org/' . ($availMap[$availKey] ?? 'InStock'),
            ];
        }

        return $schema;
    }

    /** @return array<string,mixed> */
    private function buildOrganizationSupplementSchema(Registry $params): array
    {
        $telephone  = $this->firstParam($params, ['telephone', 'phone', 'yoo_phone']);
        $street     = $this->firstParam($params, ['address', 'street', 'yoo_address', 'streetAddress']);
        $city       = $this->firstParam($params, ['city', 'locality', 'addressLocality']);
        $zip        = $this->firstParam($params, ['zip', 'postal_code', 'postalCode']);
        $country    = $this->firstParam($params, ['country', 'addressCountry']);
        $priceRange = $this->firstParam($params, ['price_range', 'priceRange', 'yoo_pricerange']);
        $lat        = $this->firstParam($params, ['latitude', 'lat', 'yoo_lat']);
        $lng        = $this->firstParam($params, ['longitude', 'lng', 'yoo_lng']);

        $hasData = ($telephone !== '' || $street !== '' || $city !== '' || $zip !== ''
            || $country !== '' || $priceRange !== '' || ($lat !== '' && $lng !== ''));
        if (!$hasData) {
            return [];
        }

        $schema = ['@context' => 'https://schema.org', '@type' => 'Organization'];
        if ($telephone !== '')  { $schema['telephone']  = $telephone; }
        if ($priceRange !== '') { $schema['priceRange'] = $priceRange; }

        if ($street !== '' || $city !== '' || $zip !== '' || $country !== '') {
            $address = ['@type' => 'PostalAddress'];
            if ($street !== '')  { $address['streetAddress']   = $street; }
            if ($city !== '')    { $address['addressLocality'] = $city; }
            if ($zip !== '')     { $address['postalCode']      = $zip; }
            if ($country !== '') { $address['addressCountry']  = $country; }
            $schema['address'] = $address;
        }

        if ($lat !== '' && $lng !== '') {
            $schema['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $lat, 'longitude' => $lng];
        }

        return $schema;
    }

    /** @param array<int,string> $keys */
    private function firstParam(Registry $params, array $keys): string
    {
        foreach ($keys as $key) {
            $val = trim((string) $params->get($key, ''));
            if ($val !== '') {
                return $val;
            }
        }
        return '';
    }

    /** @return array<string,mixed> */
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
            if ($q !== '' && $a !== '') {
                $faqs[] = [
                    '@type'          => 'Question',
                    'name'           => $q,
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a],
                ];
            }
        }

        if ($faqs === []) {
            return [];
        }

        return [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $faqs,
        ];
    }

    /** @return array<string,mixed> */
    private function buildGallerySchema(string $html): array
    {
        $images  = [];
        $uri     = Uri::getInstance();
        $baseUrl = $uri->getScheme() . '://' . $uri->getHost();

        preg_match_all(
            '/<(?:a|figure)[^>]*data-caption="([^"]*)"[^>]*>.*?<img[^>]+src="([^"]+)"[^>]*>/si',
            $html,
            $matches
        );

        for ($i = 0, $n = count($matches[1]); $i < $n; $i++) {
            $caption = trim(strip_tags(html_entity_decode($matches[1][$i], ENT_QUOTES, 'UTF-8')));
            $src     = trim($matches[2][$i]);

            if ($src === '' || str_contains($src, 'data:') || str_contains($src, 'placeholder')) {
                continue;
            }
            if (!str_starts_with($src, 'http')) {
                $src = $baseUrl . '/' . ltrim($src, '/');
            }

            $img = ['@type' => 'ImageObject', 'url' => $src];
            if ($caption !== '') {
                $img['caption'] = $caption;
            }
            $images[] = $img;
        }

        if ($images === []) {
            return [];
        }

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'ImageGallery',
            'associatedMedia' => $images,
        ];
    }

    /** @param array<string,mixed> $schema */
    private function encodeSchema(array $schema): string
    {
        return '<script type="application/ld+json">'
            . json_encode($schema, self::JSON_FLAGS)
            . '</script>';
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Whether the shared AiBoost\Lib library is fully loadable — see the
     * identical guard on AiBoostIntFalang. Under JDEBUG Joomla's debug class
     * loader THROWS on a missing class file, so the try/catch matters.
     */
    private function libReady(): bool
    {
        if ($this->libReady !== null) {
            return $this->libReady;
        }
        try {
            $this->libReady = class_exists('AiBoost\\Lib\\PluginRegistry')
                && class_exists('AiBoost\\Lib\\Logger');
        } catch (\Throwable) {
            $this->libReady = false;
        }
        return $this->libReady;
    }
}
