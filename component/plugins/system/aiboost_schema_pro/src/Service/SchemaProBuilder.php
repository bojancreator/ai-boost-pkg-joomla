<?php
/**
 * AI Boost — SchemaProBuilder (Pro)
 *
 * Decorates the Free baseline schema blocks and appends Pro-only blocks.
 * Invoked from AiBoostSchemaPro::onAiBoostFilterSchemaBlocks.
 *
 * Decorates Organization block:
 *   - Upgraded @type via SiteTypePresetService (13 Site Type presets)
 *   - openingHoursSpecification (LocalBusiness subtypes)
 *   - aggregateRating
 *   - Type-specific fields (priceRange, servesCuisine, starRating,
 *     checkinTime, checkoutTime, availableService, areaServed)
 *   - Per-language org_name / org_description / org_address_* / org_logo
 *     via TranslationService + FalangBridge fallback
 *
 * Appends Pro blocks (in order):
 *   - FAQPage  / QAPage (controlled by schema_faq_output_type)
 *   - Article / BlogPosting / NewsArticle / TechArticle
 *   - HowTo
 *   - Event
 *
 * @package     AiBoost\Plugin\System\AiBoostSchemaPro
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostSchemaPro\Service;

defined('_JEXEC') or die;

use AiBoost\Lib\AppContextInterface;
use AiBoost\Lib\TranslationService;
use Joomla\Database\DatabaseInterface;

class SchemaProBuilder
{
    /** @var array<string,mixed> */
    private array               $settings;
    private AppContextInterface $ctx;
    private DatabaseInterface   $db;
    private FalangBridge        $falang;
    private ?TranslationService $translations;
    private string $option;
    private string $view;
    private int    $id;

    /**
     * @param array<string,mixed> $settings
     */
    public function __construct(
        array $settings,
        AppContextInterface $ctx,
        DatabaseInterface $db,
        ?TranslationService $translations = null
    ) {
        $this->settings     = $settings;
        $this->ctx          = $ctx;
        $this->db           = $db;
        $this->translations = $translations;
        $this->option       = $ctx->getCurrentOption();
        $this->view         = $ctx->getCurrentView();
        $this->id           = $ctx->getCurrentId();
        $this->falang       = new FalangBridge($db, $ctx->getActiveLanguage());
    }

    /**
     * Decorate Free blocks + append Pro blocks.
     *
     * @param  array<int, array<string,mixed>> $freeBlocks
     * @return array<int, array<string,mixed>>
     */
    public function decorateAll(array $freeBlocks): array
    {
        $out = [];
        foreach ($freeBlocks as $block) {
            $type = (string) ($block['@type'] ?? '');
            if ($type === 'Organization' || $type === 'LocalBusiness') {
                $out[] = $this->decorateOrganization($block);
            } else {
                $out[] = $block;
            }
        }

        // FAQ output type: 'faqpage' (default) | 'qapage' | 'both'
        $faqOutputType = (string) ($this->settings['schema_faq_output_type'] ?? 'faqpage');

        if ($faqOutputType === 'faqpage' || $faqOutputType === 'both') {
            $faq = $this->buildFaq();
            if ($faq) {
                $out[] = $faq;
            }
        }

        if ($faqOutputType === 'qapage' || $faqOutputType === 'both') {
            $qa = $this->buildQaPage();
            if ($qa) {
                $out[] = $qa;
            }
        }

        if ((int) ($this->settings['article_schema_enabled'] ?? 1)) {
            $article = $this->buildArticle();
            if ($article) {
                $out[] = $article;
            }
        }

        $howTo = $this->buildHowTo();
        if ($howTo) {
            $out[] = $howTo;
        }

        $event = $this->buildEvent();
        if ($event) {
            $out[] = $event;
        }

        return $out;
    }

    /**
     * Decorate the Free Organization block: upgraded @type, translations,
     * openingHours, aggregateRating, type-specific properties.
     *
     * @param  array<string,mixed> $block
     * @return array<string,mixed>
     */
    private function decorateOrganization(array $block): array
    {
        $typeKey = (string) ($this->settings['schema_type'] ?? 'organization');
        $type    = SiteTypePresetService::getSchemaType($typeKey, true);
        $isLocal = SiteTypePresetService::isLocalBusiness($typeKey, true);

        $block['@type'] = $type;

        // ── Multilingual: TranslationService primary, Falang fallback ─────────
        $orgNameRaw    = trim((string) ($this->settings['org_name']            ?? ''));
        $orgDescRaw    = trim((string) ($this->settings['org_description']     ?? ''));
        $addrStreetRaw = trim((string) ($this->settings['org_address_street']  ?? ''));
        $addrCityRaw   = trim((string) ($this->settings['org_address_city']    ?? ''));
        $logoRaw       = trim((string) ($this->settings['org_logo']            ?? ''));

        if ($this->translations !== null) {
            $lc = $this->ctx->getActiveLanguage();

            $orgName = $this->translations->get('org_name', $lc, '')
                    ?: $this->falang->translate('org_name', $orgNameRaw);
            if ($orgName !== '') {
                $block['name'] = $orgName;
            }

            $orgDesc = $this->translations->get('org_description', $lc, '')
                    ?: $this->falang->translate('org_description', $orgDescRaw);
            if ($orgDesc !== '') {
                $block['description'] = $orgDesc;
            }

            $logo = $this->translations->get('org_logo', $lc, $logoRaw);
            if ($logo !== '') {
                $block['logo'] = ['@type' => 'ImageObject', 'url' => $this->absoluteUrl($logo)];
            }

            // Patch translated address fields (preserves region/zip/country
            // from Free baseline if PostalAddress was emitted).
            $addrStreet = $this->translations->get('org_address_street', $lc, '')
                       ?: $this->falang->translate('org_address_street', $addrStreetRaw);
            $addrCity   = $this->translations->get('org_address_city', $lc, '')
                       ?: $this->falang->translate('org_address_city', $addrCityRaw);

            if (isset($block['address']) && is_array($block['address'])) {
                if ($addrStreet !== '') {
                    $block['address']['streetAddress']  = $addrStreet;
                }
                if ($addrCity !== '') {
                    $block['address']['addressLocality'] = $addrCity;
                }
            }
        }

        // ── AggregateRating ──────────────────────────────────────────────────
        $ratingValue = trim((string) ($this->settings['rating_value'] ?? ''));
        $ratingCount = trim((string) ($this->settings['rating_count'] ?? ''));
        if ($ratingValue !== '' && $ratingCount !== '' && (float) $ratingValue > 0 && (int) $ratingCount > 0) {
            $best  = trim((string) ($this->settings['rating_best']  ?? '5')) ?: '5';
            $worst = trim((string) ($this->settings['rating_worst'] ?? '1')) ?: '1';
            $block['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => $ratingValue,
                'reviewCount' => $ratingCount,
                'bestRating'  => $best,
                'worstRating' => $worst,
            ];
        }

        // ── LocalBusiness extras ─────────────────────────────────────────────
        if ($isLocal) {
            $hours = (new BusinessHoursBuilder())->build($this->settings);
            if ($hours) {
                $block['openingHoursSpecification'] = $hours;
            }

            $priceRange = trim((string) ($this->settings['specific_price_range'] ?? ''));
            if ($priceRange !== '') {
                $block['priceRange'] = $priceRange;
            }
        }

        // ── Type-specific required fields ────────────────────────────────────
        if (in_array($typeKey, ['restaurant', 'foodestablishment'], true)) {
            $cuisine = trim((string) ($this->settings['specific_serves_cuisine'] ?? ''));
            if ($cuisine !== '') {
                $block['servesCuisine'] = $cuisine;
            }
        }

        if ($typeKey === 'hotel') {
            $starRating = trim((string) ($this->settings['specific_star_rating'] ?? ''));
            if ($starRating !== '' && (int) $starRating > 0) {
                $block['starRating'] = ['@type' => 'Rating', 'ratingValue' => $starRating];
            }
            $checkin = trim((string) ($this->settings['specific_checkin_time'] ?? ''));
            if ($checkin !== '') {
                $block['checkinTime'] = $checkin;
            }
            $checkout = trim((string) ($this->settings['specific_checkout_time'] ?? ''));
            if ($checkout !== '') {
                $block['checkoutTime'] = $checkout;
            }
        }

        if (in_array($typeKey, ['medicalclinic', 'legalservice'], true)) {
            $service = trim((string) ($this->settings['specific_available_service'] ?? ''));
            if ($service !== '') {
                $block['availableService'] = $service;
            }
        }

        if (in_array($typeKey, ['realestateagent', 'automotivebusiness', 'professionalservice'], true)) {
            $areaServed = trim((string) ($this->settings['specific_area_served'] ?? ''));
            if ($areaServed !== '') {
                $block['areaServed'] = $areaServed;
            }
        }

        return $block;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Pro block builders
    // ══════════════════════════════════════════════════════════════════════════

    /** @return array<string,mixed>|null */
    private function buildFaq(): ?array
    {
        $items = $this->collectFaqItems();
        if (empty($items)) {
            return null;
        }

        $lc = $this->translations !== null ? $this->ctx->getActiveLanguage() : '';

        $mainEntity = [];
        foreach ($items as $idx => $item) {
            $q = trim((string) ($item['question'] ?? ''));
            $a = trim((string) ($item['answer']   ?? ''));
            if ($q === '' || $a === '') {
                continue;
            }
            if ($lc !== '' && $this->translations !== null) {
                $q = $this->translations->get('faq_' . $idx . '_q', $lc, $q);
                $a = $this->translations->get('faq_' . $idx . '_a', $lc, $a);
            }
            $mainEntity[] = [
                '@type'          => 'Question',
                'name'           => $q,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a],
            ];
        }

        if (empty($mainEntity)) {
            return null;
        }

        return [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $mainEntity,
        ];
    }

    /** @return array<string,mixed>|null */
    private function buildQaPage(): ?array
    {
        $items = $this->collectFaqItems();
        if (empty($items)) {
            return null;
        }

        $lc = $this->translations !== null ? $this->ctx->getActiveLanguage() : '';

        $mainEntity = [];
        foreach ($items as $idx => $item) {
            $q = trim((string) ($item['question'] ?? ''));
            $a = trim((string) ($item['answer']   ?? ''));
            if ($q === '' || $a === '') {
                continue;
            }
            if ($lc !== '' && $this->translations !== null) {
                $q = $this->translations->get('faq_' . $idx . '_q', $lc, $q);
                $a = $this->translations->get('faq_' . $idx . '_a', $lc, $a);
            }
            $mainEntity[] = [
                '@type'           => 'Question',
                'name'            => $q,
                'suggestedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $a,
                ],
            ];
        }

        if (empty($mainEntity)) {
            return null;
        }

        return [
            '@context'   => 'https://schema.org',
            '@type'      => 'QAPage',
            'mainEntity' => $mainEntity,
        ];
    }

    /**
     * Shared FAQ collection (manual JSON + optional auto-detect from
     * the current article).
     *
     * @return list<array<string,mixed>>
     */
    private function collectFaqItems(): array
    {
        $rawJson = trim((string) ($this->settings['faq_items'] ?? ''));
        $items   = [];
        if ($rawJson !== '' && $rawJson !== '[]' && $rawJson !== '{}') {
            try {
                $decoded = json_decode($rawJson, true);
                if (is_array($decoded)) {
                    $items = array_values($decoded);
                }
            } catch (\Throwable $e) {
                $items = [];
            }
        }

        if ((int) ($this->settings['faq_auto_detect'] ?? 0)
            && $this->option === 'com_content' && $this->view === 'article' && $this->id > 0
        ) {
            $detected = $this->detectFaqFromCurrentArticle();
            if (!empty($detected)) {
                $seen = [];
                foreach ($items as $it) {
                    $k = mb_strtolower(trim((string) ($it['question'] ?? '')));
                    if ($k !== '') {
                        $seen[$k] = true;
                    }
                }
                foreach ($detected as $d) {
                    $k = mb_strtolower(trim($d['question']));
                    if (!isset($seen[$k])) {
                        $items[] = $d;
                        $seen[$k] = true;
                    }
                }
            }
        }

        return $items;
    }

    /** @return list<array{question:string,answer:string}> */
    private function detectFaqFromCurrentArticle(): array
    {
        try {
            $db    = $this->db;
            $now   = gmdate('Y-m-d H:i:s');
            $query = $db->getQuery(true)
                ->select([$db->quoteName('introtext'), $db->quoteName('fulltext')])
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('id') . ' = ' . $this->id)
                ->where($db->quoteName('state') . ' = 1')
                ->where('(' . $db->quoteName('publish_up') . ' IS NULL OR '
                    . $db->quoteName('publish_up') . ' <= ' . $db->quote($now) . ')')
                ->where('(' . $db->quoteName('publish_down') . ' IS NULL OR '
                    . $db->quoteName('publish_down') . ' >= ' . $db->quote($now) . ')');
            $db->setQuery($query, 0, 1);
            $row = $db->loadObject();
        } catch (\Throwable $e) {
            return [];
        }

        if (!$row) {
            return [];
        }

        $html = trim((string) ($row->introtext ?? '')) . "\n" . trim((string) ($row->fulltext ?? ''));
        if (trim($html) === '') {
            return [];
        }

        return (new \AiBoost\Lib\FaqAutoDetectService())->parse($html, 20);
    }

    /** @return array<string,mixed>|null */
    private function buildArticle(): ?array
    {
        if ($this->option !== 'com_content' || $this->view !== 'article' || $this->id <= 0) {
            return null;
        }

        try {
            $db    = $this->db;
            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('id'),
                    $db->quoteName('title'),
                    $db->quoteName('metadesc'),
                    $db->quoteName('introtext'),
                    $db->quoteName('created'),
                    $db->quoteName('modified'),
                    $db->quoteName('created_by'),
                    $db->quoteName('images'),
                    $db->quoteName('catid'),
                ])
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('id') . ' = ' . $this->id)
                ->where($db->quoteName('state') . ' = 1');
            $db->setQuery($query, 0, 1);
            $row = $db->loadObject();
        } catch (\Throwable $e) {
            return null;
        }

        if (!$row) {
            return null;
        }

        $articleType = $this->resolveArticleType((int) $row->id);
        $articleUrl  = $this->ctx->getCurrentUrl();

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => $articleType,
            'headline' => (string) ($row->title ?? ''),
            'url'      => $articleUrl,
        ];

        $metadesc = trim((string) ($row->metadesc ?? ''));
        if ($metadesc !== '') {
            $schema['description'] = $metadesc;
        }

        $created  = trim((string) ($row->created  ?? ''));
        $modified = trim((string) ($row->modified ?? ''));

        $isZeroDate = static function (string $d): bool {
            return $d === '' || str_starts_with($d, '0000-00-00');
        };

        if (!$isZeroDate($created)) {
            try { $schema['datePublished'] = (new \DateTime($created))->format(\DateTime::ATOM); } catch (\Throwable $e) {}
        }
        $effectiveModified = !$isZeroDate($modified) ? $modified : $created;
        if (!$isZeroDate($effectiveModified)) {
            try { $schema['dateModified'] = (new \DateTime($effectiveModified))->format(\DateTime::ATOM); } catch (\Throwable $e) {}
        }

        if ((string) ($this->settings['schema_author_entity_enabled'] ?? '0') === '1') {
            $createdBy  = (int) ($row->created_by ?? 0);
            $authorName = $this->loadUserName($createdBy);

            if ($authorName !== '') {
                $author = ['@type' => 'Person', 'name' => $authorName];
                if ($createdBy > 0) {
                    $cf = $this->loadAuthorCustomFields($createdBy);
                    if ($cf['jobTitle'] !== '') {
                        $author['jobTitle'] = $cf['jobTitle'];
                    }
                    if ($cf['bio'] !== '') {
                        $author['description'] = $cf['bio'];
                    }
                    if ($cf['website'] !== '') {
                        $author['url'] = $cf['website'];
                    }
                    $sameAs = array_values(array_filter([$cf['linkedin'], $cf['wikipedia'], $cf['website']]));
                    if (!empty($sameAs)) {
                        $author['sameAs'] = $sameAs;
                    }
                }
                $schema['author'] = $author;
            }
        }

        $imageUrl = $this->extractIntroImageUrl((string) ($row->images ?? ''));
        if ($imageUrl !== '') {
            $schema['image'] = $imageUrl;
        }

        $orgName = trim((string) ($this->settings['org_name'] ?? ''));
        $orgUrl  = trim((string) ($this->settings['org_url']  ?? ''));
        $logo    = trim((string) ($this->settings['org_logo'] ?? ''));
        if ($this->translations !== null) {
            $lc2     = $this->ctx->getActiveLanguage();
            $orgName = $this->translations->get('org_name', $lc2, $orgName);
            $logo    = $this->translations->get('org_logo', $lc2, $logo);
        }
        if ($orgName !== '') {
            $publisher = ['@type' => 'Organization', 'name' => $orgName];
            if ($orgUrl !== '') $publisher['url']  = $orgUrl;
            if ($logo !== '')   $publisher['logo'] = ['@type' => 'ImageObject', 'url' => $this->absoluteUrl($logo)];
            $schema['publisher'] = $publisher;
        }

        return $schema;
    }

    /** @return array<string,mixed>|null */
    private function buildEvent(): ?array
    {
        if ((int) ($this->settings['events_enabled'] ?? 1) === 0) {
            return null;
        }
        $eventsCatId = (int) ($this->settings['events_category_id'] ?? 0);
        if ($eventsCatId <= 0) {
            return null;
        }
        if ($this->option !== 'com_content' || $this->view !== 'article' || $this->id <= 0) {
            return null;
        }

        try {
            $db    = $this->db;
            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('id'),
                    $db->quoteName('title'),
                    $db->quoteName('publish_up'),
                    $db->quoteName('publish_down'),
                ])
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('id') . ' = ' . $this->id)
                ->where($db->quoteName('state') . ' = 1')
                ->where($db->quoteName('catid') . ' = ' . $eventsCatId);
            $db->setQuery($query, 0, 1);
            $row = $db->loadObject();
        } catch (\Throwable $e) {
            return null;
        }

        if (!$row) {
            return null;
        }

        $customFields = $this->loadEventCustomFields((int) $row->id);

        $schema = [
            '@context'            => 'https://schema.org',
            '@type'               => 'Event',
            'name'                => (string) ($row->title ?? ''),
            'eventStatus'         => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        ];

        $startDate = $customFields['aiboost_event_start_date'] !== ''
            ? $customFields['aiboost_event_start_date']
            : trim((string) ($row->publish_up ?? ''));
        if ($startDate !== '' && $startDate !== '0000-00-00 00:00:00') {
            try { $schema['startDate'] = (new \DateTime($startDate))->format(\DateTime::ATOM); } catch (\Throwable $e) {}
        }

        $endDate = $customFields['aiboost_event_end_date'] !== ''
            ? $customFields['aiboost_event_end_date']
            : trim((string) ($row->publish_down ?? ''));
        if ($endDate !== '' && $endDate !== '0000-00-00 00:00:00') {
            try { $schema['endDate'] = (new \DateTime($endDate))->format(\DateTime::ATOM); } catch (\Throwable $e) {}
        }

        $location = $customFields['aiboost_event_location'];
        if ($location !== '') {
            $schema['location'] = ['@type' => 'Place', 'name' => $location];
        }

        $orgName = trim((string) ($this->settings['org_name'] ?? ''));
        if ($this->translations !== null) {
            $lc      = $this->ctx->getActiveLanguage();
            $orgName = $this->translations->get('org_name', $lc, $orgName)
                    ?: $this->falang->translate('org_name', $orgName);

            $eventIndex = $this->resolveEventIndex($this->id);
            $eventKey   = $eventIndex >= 0 ? 'event_' . $eventIndex . '_desc' : 'event_' . $this->id . '_desc';
            $eventDesc  = $this->translations->get($eventKey, $lc, '');
            if ($eventDesc !== '') {
                $schema['description'] = $eventDesc;
            }
        }
        if ($orgName !== '') {
            $schema['organizer'] = ['@type' => 'Organization', 'name' => $orgName];
        }

        return $schema;
    }

    /** @return array<string,mixed>|null */
    private function buildHowTo(): ?array
    {
        if ($this->option !== 'com_content' || $this->view !== 'article' || $this->id <= 0) {
            return null;
        }

        $rawJson = trim((string) ($this->settings['schema_howto'] ?? ''));
        if ($rawJson === '' || $rawJson === '{}' || $rawJson === '[]') {
            return null;
        }

        try {
            $data = json_decode($rawJson, true);
            if (!is_array($data)) {
                return null;
            }
        } catch (\Throwable $e) {
            return null;
        }

        if ((string) ($data['enabled'] ?? '0') !== '1') {
            return null;
        }

        $name  = trim((string) ($data['name'] ?? ''));
        $steps = $data['steps'] ?? [];
        if ($name === '' || !is_array($steps) || empty($steps)) {
            return null;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'HowTo',
            'name'     => $name,
        ];

        $desc = trim((string) ($data['description'] ?? ''));
        if ($desc !== '') {
            $schema['description'] = $desc;
        }

        $totalTime = trim((string) ($data['totalTime'] ?? ''));
        if ($totalTime !== '') {
            $schema['totalTime'] = $totalTime;
        }

        $stepList = [];
        foreach ($steps as $i => $step) {
            $stepName = trim((string) ($step['name'] ?? ''));
            $stepText = trim((string) ($step['text'] ?? ''));
            if ($stepName === '') {
                $stepName = 'Step ' . ($i + 1);
            }
            if ($stepText === '') {
                continue;
            }
            $stepList[] = [
                '@type' => 'HowToStep',
                'name'  => $stepName,
                'text'  => $stepText,
            ];
        }

        if (empty($stepList)) {
            return null;
        }

        $schema['step'] = $stepList;
        return $schema;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Private helpers
    // ══════════════════════════════════════════════════════════════════════════

    private function resolveArticleType(int $articleId): string
    {
        $override = $this->loadCustomField($articleId, 'aiboost_schema_type');
        $allowed  = ['Article', 'BlogPosting', 'NewsArticle', 'TechArticle'];
        if ($override !== '' && in_array($override, $allowed, true)) {
            return $override;
        }
        return 'Article';
    }

    private function loadCustomField(int $articleId, string $fieldName): string
    {
        if ($articleId <= 0) {
            return '';
        }
        try {
            $db    = $this->db;
            $query = $db->getQuery(true)
                ->select($db->quoteName('fv.value'))
                ->from($db->quoteName('#__fields', 'f'))
                ->join(
                    'INNER',
                    $db->quoteName('#__fields_values', 'fv')
                    . ' ON ' . $db->quoteName('fv.field_id') . ' = ' . $db->quoteName('f.id')
                    . ' AND ' . $db->quoteName('fv.item_id') . ' = ' . $db->quote((string) $articleId)
                )
                ->where($db->quoteName('f.name') . ' = ' . $db->quote($fieldName))
                ->where($db->quoteName('f.context') . ' = ' . $db->quote('com_content.article'))
                ->where($db->quoteName('f.state') . ' = 1');
            $db->setQuery($query, 0, 1);
            return trim((string) ($db->loadResult() ?? ''));
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** @return array<string,string> */
    private function loadEventCustomFields(int $articleId): array
    {
        $result = [
            'aiboost_event_start_date' => '',
            'aiboost_event_end_date'   => '',
            'aiboost_event_location'   => '',
        ];
        if ($articleId <= 0) {
            return $result;
        }
        try {
            $db    = $this->db;
            $names = array_keys($result);
            $query = $db->getQuery(true)
                ->select([$db->quoteName('f.name'), $db->quoteName('fv.value')])
                ->from($db->quoteName('#__fields', 'f'))
                ->join(
                    'INNER',
                    $db->quoteName('#__fields_values', 'fv')
                    . ' ON ' . $db->quoteName('fv.field_id') . ' = ' . $db->quoteName('f.id')
                    . ' AND ' . $db->quoteName('fv.item_id') . ' = ' . $db->quote((string) $articleId)
                )
                ->where($db->quoteName('f.name') . ' IN ('
                    . implode(',', array_map([$db, 'quote'], $names)) . ')')
                ->where($db->quoteName('f.context') . ' = ' . $db->quote('com_content.article'))
                ->where($db->quoteName('f.state') . ' = 1');
            $db->setQuery($query);
            foreach ($db->loadObjectList() ?: [] as $row) {
                $result[(string) $row->name] = trim((string) ($row->value ?? ''));
            }
        } catch (\Throwable $e) {
            // Silent fallback
        }
        return $result;
    }

    private function extractIntroImageUrl(string $imagesJson): string
    {
        if ($imagesJson === '' || $imagesJson === '{}') {
            return '';
        }
        try {
            $images = json_decode($imagesJson, true);
            $path   = trim((string) ($images['image_intro'] ?? ''));
            return $path !== '' ? $this->absoluteUrl($path) : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** @return array{jobTitle:string,bio:string,website:string,linkedin:string,wikipedia:string} */
    private function loadAuthorCustomFields(int $userId): array
    {
        $empty = [
            'jobTitle' => '', 'bio' => '', 'website' => '',
            'linkedin' => '', 'wikipedia' => '',
        ];

        if ($userId <= 0 || !class_exists('Joomla\\Component\\Fields\\Administrator\\Helper\\FieldsHelper')) {
            return $empty;
        }

        try {
            $user = \Joomla\CMS\Factory::getContainer()
                ->get(\Joomla\CMS\User\UserFactoryInterface::class)
                ->loadUserById($userId);
            if (!$user || (int) $user->id <= 0) {
                return $empty;
            }

            $fields = \Joomla\Component\Fields\Administrator\Helper\FieldsHelper::getFields(
                'com_users.user', $user, true
            );
            if (!is_array($fields) || empty($fields)) {
                return $empty;
            }

            $byName = [];
            foreach ($fields as $f) {
                if (!isset($f->name)) {
                    continue;
                }
                $val = isset($f->rawvalue) ? $f->rawvalue : ($f->value ?? '');
                if (is_array($val)) {
                    $val = implode(', ', array_filter(array_map('strval', $val)));
                }
                $byName[(string) $f->name] = trim((string) $val);
            }

            $lang = strtolower(substr($this->ctx->getActiveLanguage(), 0, 2));
            $pick = static function (string $base) use ($byName, $lang): string {
                foreach ([$base . '_' . $lang, $base . '_en', $base] as $key) {
                    if (isset($byName[$key]) && $byName[$key] !== '') {
                        return $byName[$key];
                    }
                }
                return '';
            };

            return [
                'jobTitle'  => $pick('aiboost_job_title'),
                'bio'       => $pick('aiboost_bio'),
                'website'   => $pick('aiboost_website'),
                'linkedin'  => $pick('aiboost_linkedin'),
                'wikipedia' => $pick('aiboost_wikipedia'),
            ];
        } catch (\Throwable $e) {
            return $empty;
        }
    }

    private function loadUserName(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }
        try {
            $db    = $this->db;
            $query = $db->getQuery(true)
                ->select($db->quoteName('name'))
                ->from($db->quoteName('#__users'))
                ->where($db->quoteName('id') . ' = ' . $userId);
            $db->setQuery($query, 0, 1);
            return trim((string) ($db->loadResult() ?? ''));
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Resolve the 0-based index of an event article within the admin-configured
     * schema_event_article_ids list. Returns -1 when the article is not listed.
     */
    private function resolveEventIndex(int $articleId): int
    {
        $raw = trim((string) ($this->settings['schema_event_article_ids'] ?? ''));
        if ($raw === '') {
            return -1;
        }
        $ids = array_values(array_filter(
            array_map('intval', explode(',', $raw)),
            static fn(int $v) => $v > 0
        ));
        $pos = array_search($articleId, $ids, true);
        return $pos !== false ? (int) $pos : -1;
    }

    private function absoluteUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return $this->ctx->getBaseUrl() . '/' . ltrim($path, '/');
    }
}
