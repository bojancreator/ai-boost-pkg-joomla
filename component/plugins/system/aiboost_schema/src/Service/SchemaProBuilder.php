<?php
/**
 * AI Boost — SchemaProBuilder (Pro)
 *
 * Decorates the core schema blocks and appends extended schema blocks.
 * Invoked from AiBoostSchema::onAiBoostFilterSchemaBlocks.
 *
 * Overlays the identity block (any business type recognised by
 * SiteTypePresetService, not only Organization / LocalBusiness):
 *   - Per-language org_name / org_description / org_address_* / org_logo
 *     via TranslationService (falls back to the base value when no row exists)
 *
 * The free SchemaBuilder owns the upgraded @type, @id and all type-specific
 * properties (hours, ratings, cuisine, services, amenities, payments); the Pro
 * layer no longer re-emits them, so the two builders cannot diverge.
 *
 * Appends extended blocks (in order):
 *   - FAQPage  / QAPage (controlled by schema_faq_output_type)
 *   - Article / BlogPosting / NewsArticle / TechArticle
 *   - HowTo
 *   - Event
 *
 * @package     AiBoost\Plugin\System\AiBoostSchema
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostSchema\Service;

defined('_JEXEC') or die;

use AiBoost\Lib\AppContextInterface;
use AiBoost\Lib\Page\PageContext;
use AiBoost\Lib\TranslationService;
use Joomla\Database\DatabaseInterface;

class SchemaProBuilder
{
    /** @var array<string,mixed> */
    private array               $settings;
    private AppContextInterface $ctx;
    private DatabaseInterface   $db;
    private ?TranslationService $translations;
    private string $option;
    private string $view;
    private int    $id;
    /** T1·S7: the one homepage truth (menu home=1) — closes the article gates on the home. */
    private bool $isHomepage;
    /** T1·S6: resolved per-request language (PageContext::language) when available. */
    private string $lang;

    /**
     * @param array<string,mixed> $settings
     * @param ?PageContext        $pageContext  T1·S2: the resolved per-request page
     *        context. When provided (production, via AdapterRegistry::pageResolver()),
     *        the page-type primitives are read from it; when null (unit tests) they
     *        fall back to the raw $ctx primitives. BEHAVIOUR-IDENTICAL: PageContext's
     *        raw option/view/rawId ARE getCurrentOption/View/Id — the article gate
     *        therefore fires on exactly the same pages as before, including a
     *        single-article homepage. T1·S7 — the article gates are now
     *        homepage-aware (isArticlePage() == PageContext::isArticle()): a
     *        single-article / featured / category-blog HOME no longer emits
     *        article-scoped schema (the homepage takes the Free WebSite/home graph).
     */
    public function __construct(
        array $settings,
        AppContextInterface $ctx,
        DatabaseInterface $db,
        ?TranslationService $translations = null,
        ?PageContext $pageContext = null
    ) {
        $this->settings     = $settings;
        $this->ctx          = $ctx;
        $this->db           = $db;
        $this->translations = $translations;
        $this->option       = $pageContext !== null ? $pageContext->option : $ctx->getCurrentOption();
        $this->view         = $pageContext !== null ? $pageContext->view   : $ctx->getCurrentView();
        $this->id           = $pageContext !== null ? $pageContext->rawId  : $ctx->getCurrentId();
        // T1·S7: the menu-home truth. When the resolver is present (production) the
        // homepage is the menu home=1 page whatever it is built from; the null
        // fallback (unit tests) is $ctx->isHomepage() (the same menu-home flag).
        $this->isHomepage   = $pageContext !== null ? $pageContext->isHomepage : $ctx->isHomepage();
        // T1·S6: the active page language comes from the single resolver source
        // (PageContext::language) when provided, falling back to the raw ctx value
        // (unit tests). PageContext::language IS getActiveLanguage(), so this is
        // byte-identical to the per-request active language used before.
        $this->lang         = $pageContext !== null ? $pageContext->language : $ctx->getActiveLanguage();
    }

    /**
     * T1·S7 — the homepage-first article gate (== PageContext::isArticle()): a real
     * article page that is NOT the menu home. A single-article / featured /
     * category-blog HOME returns false, so no article-scoped schema (Article,
     * Event, HowTo, auto-detected FAQ, author/datePublished) emits on the home —
     * the homepage takes the Free WebSite/home graph instead.
     */
    private function isArticlePage(): bool
    {
        return !$this->isHomepage
            && $this->option === 'com_content'
            && $this->view === 'article'
            && $this->id > 0;
    }

    /**
    * Decorate core blocks + append extended blocks.
     *
     * @param  array<int, array<string,mixed>> $freeBlocks
     * @return array<int, array<string,mixed>>
     */
    public function decorateAll(array $freeBlocks): array
    {
        $out = [];
        foreach ($freeBlocks as $block) {
            $type = (string) ($block['@type'] ?? '');
            // Overlay translations on the identity block whatever its specific
            // type (Restaurant, LodgingBusiness, Dentist, …) — not only the
            // generic Organization / LocalBusiness ones.
            if (SiteTypePresetService::isBusinessIdentityType($type)) {
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
    * Decorate the Organization block: upgraded @type, translations,
     * openingHours, aggregateRating, type-specific properties.
     *
     * @param  array<string,mixed> $block
     * @return array<string,mixed>
     */
    private function decorateOrganization(array $block): array
    {
        // The free SchemaBuilder already emits the upgraded @type, @id, and
        // every type-specific property (hours, ratings, cuisine, services,
        // amenities, payments, …). The Pro layer's sole job here is to overlay
        // the active-language translation of the identity fields.
        if ($this->translations === null) {
            return $block;
        }

        $lc = $this->lang;

        $orgNameRaw    = trim((string) ($this->settings['org_name']           ?? ''));
        $orgDescRaw    = trim((string) ($this->settings['org_description']    ?? ''));
        $addrStreetRaw = trim((string) ($this->settings['org_address_street'] ?? ''));
        $addrCityRaw   = trim((string) ($this->settings['org_address_city']   ?? ''));
        $logoRaw       = trim((string) ($this->settings['org_logo']           ?? ''));

        $orgName = $this->translations->get('org_name', $lc, $orgNameRaw);
        if ($orgName !== '') {
            $block['name'] = $orgName;
        }

        $orgDesc = $this->translations->get('org_description', $lc, $orgDescRaw);
        if ($orgDesc !== '') {
            $block['description'] = $orgDesc;
        }

        $logo = $this->translations->get('org_logo', $lc, $logoRaw);
        if ($logo !== '') {
            $block['logo'] = ['@type' => 'ImageObject', 'url' => $this->absoluteUrl($logo)];
        }

        // Patch translated address fields (preserves region/zip/country from
        // the core block's PostalAddress when present).
        $addrStreet = $this->translations->get('org_address_street', $lc, $addrStreetRaw);
        $addrCity   = $this->translations->get('org_address_city', $lc, $addrCityRaw);

        if (isset($block['address']) && is_array($block['address'])) {
            if ($addrStreet !== '') {
                $block['address']['streetAddress'] = $addrStreet;
            }
            if ($addrCity !== '') {
                $block['address']['addressLocality'] = $addrCity;
            }
        }

        // Faza 2c — translate makesOffer service NAMES (text content). The Free
        // builder emits makesOffer in filtered order (rows without a name are
        // skipped); the admin keys each translation by that same filtered index
        // (service_{i}_name), so makesOffer[i] aligns with service_{i}_name.
        if (isset($block['makesOffer']) && is_array($block['makesOffer'])) {
            foreach ($block['makesOffer'] as $i => &$offer) {
                if (isset($offer['itemOffered']['name'])) {
                    $tr = $this->translations->get('service_' . $i . '_name', $lc, '');
                    if ($tr !== '') {
                        $offer['itemOffered']['name'] = $tr;
                    }
                }
            }
            unset($offer);
        }

        // Faza 2b (rest) — translate slogan + awards (text content).
        $sloganTr = $this->translations->get('schema_slogan', $lc, '');
        if ($sloganTr !== '' && isset($block['slogan'])) {
            $block['slogan'] = $sloganTr;
        }
        $awardTr = $this->translations->get('schema_award', $lc, '');
        if ($awardTr !== '' && isset($block['award'])) {
            $block['award'] = array_values(array_filter(array_map('trim', explode(',', $awardTr))));
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

        $lc = $this->translations !== null ? $this->lang : '';

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

        $lc = $this->translations !== null ? $this->lang : '';

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

        if ((int) ($this->settings['faq_auto_detect'] ?? 0) && $this->isArticlePage()) {
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
        if (!$this->isArticlePage()) {
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
        // Schema 'url' should match the canonical URL — strip any query/fragment.
        $articleUrl  = $this->ctx->getCurrentUrl();
        $articleUrl  = preg_replace('/[?#].*$/s', '', $articleUrl) ?? $articleUrl;

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
            $lc2     = $this->lang;
            $orgName = $this->translations->get('org_name', $lc2, $orgName);
            $logo    = $this->translations->get('org_logo', $lc2, $logo);
        }
        if ($orgName !== '') {
            // Carry the shared Organization @id so this publisher merges with
            // the page's Organization node into one entity graph.
            $publisher = ['@type' => 'Organization', '@id' => $this->organizationId(), 'name' => $orgName];
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
        if (!$this->isArticlePage()) {
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
            $lc      = $this->lang;
            $orgName = $this->translations->get('org_name', $lc, $orgName);

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
        if (!$this->isArticlePage()) {
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

        // Per-language resolution (mirrors buildFaq): when a TranslationService
        // is wired (the Pro + Multilang null-thread), each HowTo string is looked
        // up by its translation key for the active language, falling back to the
        // base (default-language) value entered in schema_howto.
        $lc = $this->translations !== null ? $this->lang : '';
        if ($lc !== '' && $this->translations !== null) {
            $name = $this->translations->get('howto_name', $lc, $name);
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'HowTo',
            'name'     => $name,
        ];

        $desc = trim((string) ($data['description'] ?? ''));
        if ($lc !== '' && $this->translations !== null && $desc !== '') {
            $desc = $this->translations->get('howto_desc', $lc, $desc);
        }
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
            if ($lc !== '' && $this->translations !== null) {
                if ($stepName !== '') {
                    $stepName = $this->translations->get('howto_step_' . $i . '_name', $lc, $stepName);
                }
                if ($stepText !== '') {
                    $stepText = $this->translations->get('howto_step_' . $i . '_text', $lc, $stepText);
                }
            }
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

            $lang = strtolower(substr($this->lang, 0, 2));
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

    /**
     * Stable @id for the publishing Organization node — must match the value
     * SchemaBuilder emits on the Organization block so consumers merge them.
     */
    private function organizationId(): string
    {
        $orgUrl = trim((string) ($this->settings['org_url'] ?? ''));
        $base   = $orgUrl !== '' ? $orgUrl : $this->ctx->getBaseUrl();
        return rtrim($base, '/') . '/#organization';
    }
}
