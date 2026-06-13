<?php
/**
 * AI Boost — YOOtheme Integration Plugin (single plugin: free OG + Pro schema)
 *
 * ONE plugin element for the whole YOOtheme integration (Plan 2a, single-plugin
 * model). It contains BOTH tiers:
 *
 *   - FREE: per-page OpenGraph/meta override from the YOOtheme menu page title
 *     and description (gate isActive() only — OG for YOOtheme is free).
 *   - PRO (`int_yootheme` SKU): FAQ (Accordion) + ImageGallery + Event/Product/
 *     Organization Schema.org, and sitemap exclusion of builder-only pages
 *     (gate: integration active AND an active YOOtheme Pro licence).
 *
 * Anti-piracy: every Pro-only section is fenced with the build's Pro-strip
 * markers. The build STRIPS those blocks from the Free distribution ZIP (verified
 * by verify-no-pro-leakage STRICT), so the free plugin physically lacks the
 * schema code. The paid "AI Boost — YOOtheme Pro" Lemon Squeezy product ships the SAME
 * element FULL (unstripped); installing it UPGRADES this plugin in place
 * (same id / settings / enabled state) — one row in the Plugins manager, never a
 * second plugin. Pro options stay runtime-gated on the active YOOtheme Pro licence.
 *
 * Coexistence: only ACTIVATES when the YOOtheme template is present AND the
 * integration is switched on (Integrations master toggle). It never edits
 * YOOtheme data — it only reads menu params / page markup to add meta + schema.
 *
 * @package     AiBoost\Plugin\System\AiBoostIntYootheme
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostIntYootheme\Extension;

defined('_JEXEC') or die;

use AiBoost\Lib\ConflictManager;
use AiBoost\Lib\Integration\AbstractIntegrationPlugin;
use AiBoost\Lib\Integration\IntegrationDescriptor;
use AiBoost\Lib\Integration\Sdk;
use Joomla\CMS\Factory;
use Joomla\Registry\Registry;
// @pro:start
use AiBoost\Lib\HeadBlockBuilder;
use AiBoost\Lib\Integration\FilterResult;
use AiBoost\Lib\PluginRegistry;
use Joomla\CMS\Uri\Uri;
// @pro:end

class AiBoostIntYootheme extends AbstractIntegrationPlugin
{
    // @pro:start
    /** JSON-LD encode flags. JSON_HEX_TAG/AMP close the stored-XSS hole a raw
     *  `</script>` inside page content would otherwise open. */
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP;
    // @pro:end

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
            description:    'Premium Joomla theme and page builder. The free integration aligns per-page OpenGraph meta with your YOOtheme page title and description. YOOtheme Pro (licensed) adds FAQ, ImageGallery and Event/Product/Organization Schema.org plus sitemap clean-up.',
            hostType:       'template',
            hostElement:    'yootheme',
            sdkVersion:     Sdk::SDK_VERSION,
            minCoreVersion: '0.58.0',
            version:        '0.75.0',
            learnUrl:       'https://yootheme.com/marketplace/yootheme-pro',
            addonUrl:       'https://aiboostnow.com/integrations/yootheme',
            icon:           'icon-puzzle',
            claimsSlots:    [
                ConflictManager::SLOT_SCHEMA_FAQ,
            ],
        );
    }

    // ── onAiBoostRegisterFields (manifest contributions) ───────────────────

    /**
     * Field registration runs regardless of licence/host state so a plain
     * Settings save never drops these keys. The free OG override is always
     * contributed; the Pro schema fields (tier='pro', sku='int_yootheme') live in
     * the Pro-stripped section and so are absent from the Free build — and locked
     * in the UI until the YOOtheme Pro licence is active (Manifest\Registry).
     */
    public function onAiBoostRegisterFields(): array
    {
        if (!$this->libReady()) {
            return [];
        }

        $fields = [
            $this->manifestField('yootheme_meta_override', 'social', 'yootheme', 'YOOtheme: override OpenGraph from page meta'),
        ];

        // @pro:start
        $fields[] = $this->manifestField('yootheme_faq_enabled', 'schema', 'yootheme', 'YOOtheme: FAQ schema from Accordion', 'toggle', '1', [], 'pro', 'int_yootheme');
        $fields[] = $this->manifestField('yootheme_gallery_enabled', 'schema', 'yootheme', 'YOOtheme: ImageGallery schema from Gallery', 'toggle', '1', [], 'pro', 'int_yootheme');
        $fields[] = $this->manifestField('yootheme_schema_mapping', 'schema', 'yootheme', 'YOOtheme: Schema.org mapping from menu params', 'toggle', '1', [], 'pro', 'int_yootheme');
        $fields[] = $this->manifestField('yootheme_accordion_selector', 'schema', 'yootheme', 'YOOtheme: Accordion CSS selector', 'text', '.uk-accordion', [
            'description' => 'CSS selector used to detect YOOtheme Accordion elements for FAQPage schema.',
        ], 'pro', 'int_yootheme');
        $fields[] = $this->manifestField('yootheme_sitemap_exclude_builder', 'sitemap', 'yootheme', 'YOOtheme: exclude builder-only pages from sitemap', 'toggle', '1', [], 'pro', 'int_yootheme');
        // @pro:end

        return $fields;
    }

    /** @param array<string,mixed> $extra */
    private function manifestField(
        string $key,
        string $tab,
        string $section,
        string $label,
        string $type = 'toggle',
        string $default = '1',
        array $extra = [],
        string $tier = 'free',
        string $sku = 'core'
    ): array {
        return array_merge([
            'key'         => $key,
            'tab'         => $tab,
            'section'     => $section,
            'label'       => $label,
            'type'        => $type,
            'default'     => $default,
            'tier'        => $tier,
            'sku'         => $sku,
            'integration' => 'yootheme',
        ], $extra);
    }

    // ── Runtime gates ──────────────────────────────────────────────────────

    /**
     * FREE gate (OpenGraph): host (YOOtheme template) present AND the admin left
     * the integration switched on. No Pro check — OG for YOOtheme is free.
     */
    private function bridgeOn(): bool
    {
        return $this->libReady() && $this->isActive();
    }

    // @pro:start
    /**
     * PRO gate (Schema.org): the free gate PLUS an active YOOtheme Pro licence
     * (hasPro('int_yootheme')) — independent of the core bundle (per-integration
     * licensing). This whole method is stripped from the Free build.
     */
    private function proOn(): bool
    {
        if (!$this->bridgeOn()) {
            return false;
        }
        try {
            return PluginRegistry::hasPro('int_yootheme');
        } catch (\Throwable) {
            return false;
        }
    }
    // @pro:end

    private function onSiteHtml(): bool
    {
        $app = Factory::getApplication();
        if (!$app->isClient('site')) {
            return false;
        }
        $doc = $app->getDocument();
        return $doc && $doc->getType() === 'html';
    }

    // ── onAfterRoute — page meta override (FREE) ───────────────────────────

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

    // @pro:start
    // ── onBeforeCompileHead — menu-param Schema.org (PRO) ───────────────────

    /**
     * Build a typed Schema.org entity from YOOtheme menu params and push it
     * into the consolidated head block's Schema section. Replaces the legacy
     * addCustomTag() injection.
     */
    public function onBeforeCompileHead(): void
    {
        if (!$this->proOn() || !$this->onSiteHtml()) {
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

    // ── onAiBoostFilterHeadOutput — body-dependent FAQ + gallery (PRO) ──────

    /**
     * SDK filter fired INSIDE HeadBlockBuilder::finalize(), after the page
     * body is rendered. This is where body-dependent schema belongs: we read
     * the YOOtheme Accordion / Gallery markup from the finished body and
     * append the JSON-LD to the consolidated head block — no `</head>` splice.
     *
     * @param array<string,mixed> $input
     */
    public function onAiBoostFilterHeadOutput(array $input, FilterResult $result): void
    {
        if (!$this->proOn() || !$this->onSiteHtml()) {
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

    // ── onAiBoostBeforeSitemapBuild — exclude builder-only pages (PRO) ──────

    public function onAiBoostBeforeSitemapBuild(): void
    {
        if (!$this->proOn()) {
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
    // @pro:end

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
