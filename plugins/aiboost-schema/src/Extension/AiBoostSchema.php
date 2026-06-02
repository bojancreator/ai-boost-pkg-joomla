<?php
/**
 * AI Boost — Schema.org Rich Snippets Plugin (standalone, Joomla 4/5/6)
 *
 * Handles: Organization/LocalBusiness (all 14 site types), Article/BlogPosting,
 *          WebSite+SearchAction, FAQ (custom fields + manual JSON), BreadcrumbList,
 *          Events JSON-LD, Hreflang XML Sitemap (/sitemap-hreflang.xml).
 * Standalone: reads all settings from Joomla-native plugin params ($this->params).
 *
 * @package     AiBoost\Plugin\System\AiBoostSchema
 * @version     1.1.0
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostSchema\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;

class AiBoostSchema extends CMSPlugin
{
    protected $autoloadLanguage = true;

    /** @var array<array{lang_id:string,lang_code:string,sef:string,title:string}> */
    private array $detectedLanguages = [];

    // ── Entry points ────────────────────────────────────────────────────────

    public function onAfterInitialise(): void
    {
        if (!(int) $this->params->get('hreflang_sitemap_enabled', 0)) {
            return;
        }
        if (!(int) $this->params->get('enabled', 1)) {
            return;
        }
        if ((int) $this->params->get('staging_mode', 0)) {
            return;
        }

        $uri  = $_SERVER['REQUEST_URI'] ?? '';
        $path = ltrim(parse_url($uri, PHP_URL_PATH) ?? '', '/');
        if ($path === 'sitemap-hreflang.xml') {
            $this->serveHreflangSitemap();
        }
    }

    public function onBeforeCompileHead(): void
    {
        $app = Factory::getApplication();
        if (!$app->isClient('site')) {
            return;
        }
        $document = $app->getDocument();
        if (!$document || $document->getType() !== 'html') {
            return;
        }
        if (!(int) $this->params->get('enabled', 1)) {
            return;
        }
        if ((int) $this->params->get('staging_mode', 0)) {
            return;
        }

        $this->injectSchemas($document);
    }

    // ── Schema dispatch ─────────────────────────────────────────────────────

    private function injectSchemas($document): void
    {
        $app = Factory::getApplication();

        if ((int) $this->params->get('website_schema_enabled', 1)) {
            $this->injectWebSiteSchema($document, $app);
        }

        if ((int) $this->params->get('article_schema_enabled', 1)) {
            $this->injectArticleSchema($document, $app);
        }

        $orgName = trim((string) $this->params->get('org_name', ''));
        if ($orgName) {
            $this->injectOrganizationSchema($document, $orgName);
        }

        if ((int) $this->params->get('faq_enabled', 1)) {
            $this->injectFaqSchema($document, $app);
        }

        if ((int) $this->params->get('breadcrumb_enabled', 1)) {
            $this->injectBreadcrumbSchema($document, $app);
        }

        if ((int) $this->params->get('enable_manual_faqs', 0)) {
            $this->injectManualFaqSchema($document, $app);
        }

        if ((int) $this->params->get('schema_events_enabled', 0)) {
            $this->injectEventsSchema($document);
        }
    }

    // ── WebSite + SearchAction ──────────────────────────────────────────────

    private function injectWebSiteSchema($document, $app): void
    {
        $input  = $app->getInput();
        $option = $input->get('option', '');
        $view   = $input->get('view', '');
        $id     = (int) $input->get('id', 0);

        $isHomepage = ($option === '' || ($option === 'com_content' && $view === 'featured'))
            || ($option === 'com_content' && $view === '' && $id === 0);

        if (!$isHomepage) {
            $path = ltrim(Uri::getInstance()->getPath(), '/');
            if ($path === '' || $path === 'index.php') {
                $isHomepage = true;
            }
        }
        if (!$isHomepage) {
            return;
        }

        $orgUrl = trim((string) $this->params->get('org_url', ''));
        if (!$orgUrl) {
            $uri    = Uri::getInstance();
            $orgUrl = $uri->getScheme() . '://' . $uri->getHost();
        }
        $orgUrl  = rtrim($orgUrl, '/');
        $orgName = trim((string) $this->params->get('org_name', '')) ?: $app->get('sitename', '');

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            'name'     => $orgName,
            'url'      => $orgUrl . '/',
        ];

        if ((int) $this->params->get('searchbox_enabled', 1)) {
            $schema['potentialAction'] = [
                '@type'       => 'SearchAction',
                'target'      => [
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => $orgUrl . '/index.php?option=com_search&searchword={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ];
        }

        $this->addJsonLd($document, $schema);
    }

    // ── Organization / LocalBusiness (all site types) ───────────────────────

    private function injectOrganizationSchema($document, string $orgName): void
    {
        $siteType = (string) $this->params->get('schema_type', 'organization');

        $typeMap = [
            'organization'  => 'Organization',
            'localbusiness' => 'LocalBusiness',
            'hotel'         => 'LodgingBusiness',
            'restaurant'    => 'Restaurant',
            'ecommerce'     => 'Store',
            'medical'       => 'MedicalClinic',
            'lawyer'        => 'LegalService',
            'school'        => 'EducationalOrganization',
            'gym'           => 'ExerciseGym',
            'dentist'       => 'Dentist',
            'realestate'    => 'RealEstateAgent',
            'portfolio'     => 'Person',
            'news'          => 'NewsMediaOrganization',
            'blog'          => 'Organization',
        ];
        $type = $typeMap[$siteType] ?? 'Organization';

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => $type,
            'name'     => $orgName,
        ];

        $orgUrl = trim((string) $this->params->get('org_url', ''));
        if ($orgUrl) {
            $schema['url'] = $orgUrl;
        }

        $desc = trim((string) $this->params->get('org_description', ''));
        if ($desc) {
            $schema['description'] = $desc;
        }

        $phone = trim((string) $this->params->get('org_phone', ''));
        if ($phone) {
            $schema['telephone'] = $phone;
        }

        $email = trim((string) $this->params->get('org_email', ''));
        if ($email) {
            $schema['email'] = $email;
        }

        $logo = trim((string) $this->params->get('org_logo', ''));
        if ($logo) {
            $schema['logo'] = ['@type' => 'ImageObject', 'url' => $this->resolveUrl($logo)];
        }

        $image = trim((string) $this->params->get('org_image', ''));
        if ($image) {
            $schema['image'] = ['@type' => 'ImageObject', 'url' => $this->resolveUrl($image)];
        }

        // Address
        $street  = trim((string) $this->params->get('address_street', ''));
        $city    = trim((string) $this->params->get('address_city', ''));
        $zip     = trim((string) $this->params->get('address_zip', ''));
        $country = trim((string) $this->params->get('address_country', ''));
        if ($street || $city || $zip || $country) {
            $addr = ['@type' => 'PostalAddress'];
            if ($street)  { $addr['streetAddress']   = $street; }
            if ($city)    { $addr['addressLocality']  = $city; }
            if ($zip)     { $addr['postalCode']       = $zip; }
            if ($country) { $addr['addressCountry']   = $country; }
            $schema['address'] = $addr;
        }

        // Geo coordinates
        $lat = trim((string) $this->params->get('geo_latitude', ''));
        $lng = trim((string) $this->params->get('geo_longitude', ''));
        if ($lat && $lng) {
            $schema['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $lat, 'longitude' => $lng];
        }

        // Price range
        $priceRange = trim((string) $this->params->get('price_range', ''));
        if ($priceRange) {
            $schema['priceRange'] = $priceRange;
        }

        // AggregateRating
        $rv = trim((string) $this->params->get('rating_value', ''));
        $rc = trim((string) $this->params->get('rating_count', ''));
        if ($rv !== '' && $rc !== '') {
            $rating = [
                '@type'       => 'AggregateRating',
                'ratingValue' => $rv,
                'reviewCount' => $rc,
                'bestRating'  => trim((string) $this->params->get('rating_best', '5')) ?: '5',
                'worstRating' => trim((string) $this->params->get('rating_worst', '1')) ?: '1',
            ];
            $ratingSource = trim((string) $this->params->get('rating_source', ''));
            if ($ratingSource) {
                $rating['ratingExplanation'] = $ratingSource;
            }
            $schema['aggregateRating'] = $rating;
        }

        // ── Opening Hours ─────────────────────────────────────────────────
        $this->applyOpeningHours($schema, $type);

        // ── Site-type specific fields ─────────────────────────────────────
        $this->applyTypeSpecificFields($schema, $siteType);

        // ── sameAs (social links) ─────────────────────────────────────────
        $socialLinks = [];
        foreach (['facebook', 'instagram', 'youtube', 'twitter', 'linkedin', 'tiktok', 'pinterest'] as $net) {
            $u = trim((string) $this->params->get('social_' . $net, ''));
            if ($u) {
                $socialLinks[] = $u;
            }
        }
        if ($socialLinks) {
            $schema['sameAs'] = $socialLinks;
        }

        $this->addJsonLd($document, $schema);
    }

    /**
     * Apply opening hours fields to the schema array.
     * Supports simple text mode, advanced JSON schedule, seasonal hours, and holiday closures.
     */
    private function applyOpeningHours(array &$schema, string $schemaType): void
    {
        // Temporarily closed overrides everything
        if ((int) $this->params->get('schema_hours_temp_closed', 0)) {
            $schema['temporarilyClosed'] = true;
            return;
        }

        $mode = trim((string) $this->params->get('schema_hours_mode', 'none'));
        if ($mode === 'none') {
            return;
        }

        $apptOnly = (int) $this->params->get('schema_hours_appointment_only', 0);

        if ($mode === 'simple') {
            $raw   = trim((string) $this->params->get('schema_opening_hours', ''));
            $lines = array_values(array_filter(array_map('trim', explode("\n", $raw))));
            if ($lines) {
                if ($apptOnly) {
                    $schema['openingHours'] = 'By appointment only';
                } else {
                    $schema['openingHours'] = $lines;
                }
            }
        } elseif ($mode === 'advanced') {
            $raw = trim((string) $this->params->get('schema_business_hours', ''));
            if ($raw) {
                $specs = $this->buildOpeningHoursSpecification($raw);
                if ($specs) {
                    // Add seasonal validity
                    $seasonFrom = trim((string) $this->params->get('schema_season_from', ''));
                    $seasonTo   = trim((string) $this->params->get('schema_season_to', ''));
                    if ($seasonFrom && $seasonTo) {
                        $year = (int) date('Y');
                        foreach ($specs as &$spec) {
                            $spec['validFrom']    = $year . '-' . $seasonFrom;
                            $spec['validThrough'] = $year . '-' . $seasonTo;
                        }
                        unset($spec);
                    }
                    $schema['openingHoursSpecification'] = $specs;
                }
            }
        }

        // Holiday / special closures (specialOpeningHoursSpecification)
        $holidayRaw = trim((string) $this->params->get('schema_holiday_closed', ''));
        if ($holidayRaw) {
            $dates   = array_values(array_filter(array_map('trim', explode("\n", $holidayRaw))));
            $special = [];
            foreach ($dates as $date) {
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    $special[] = [
                        '@type'     => 'OpeningHoursSpecification',
                        'validFrom' => $date,
                        'validThrough' => $date,
                        'opens'     => '00:00',
                        'closes'    => '00:00',
                    ];
                }
            }
            if ($special) {
                $schema['specialOpeningHoursSpecification'] = $special;
            }
        }
    }

    /**
     * Parse the advanced JSON schedule into openingHoursSpecification entries.
     * Expected JSON format: {"mon":{"open":true,"slots":[{"from":"09:00","to":"17:00"}]}, ...}
     */
    private function buildOpeningHoursSpecification(string $json): array
    {
        $dayCodeMap = [
            'mon' => 'Monday',    'tue' => 'Tuesday', 'wed' => 'Wednesday',
            'thu' => 'Thursday',  'fri' => 'Friday',  'sat' => 'Saturday',
            'sun' => 'Sunday',
        ];
        try {
            $schedule = json_decode($json, true);
            if (!is_array($schedule)) {
                return [];
            }
            $specs = [];
            foreach ($dayCodeMap as $code => $dayName) {
                $day = $schedule[$code] ?? null;
                if (!is_array($day) || empty($day['open'])) {
                    continue;
                }
                $slots = $day['slots'] ?? [];
                if (empty($slots)) {
                    continue;
                }
                foreach ($slots as $slot) {
                    $from = trim((string) ($slot['from'] ?? ''));
                    $to   = trim((string) ($slot['to'] ?? ''));
                    if ($from && $to) {
                        $specs[] = [
                            '@type'     => 'OpeningHoursSpecification',
                            'dayOfWeek' => 'https://schema.org/' . $dayName,
                            'opens'     => $from,
                            'closes'    => $to,
                        ];
                    }
                }
            }
            return $specs;
        } catch (\Throwable $e) {
            error_log('[AI Boost Schema] Business hours JSON parse error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Apply site-type-specific Schema.org properties based on the selected schema_type.
     */
    private function applyTypeSpecificFields(array &$schema, string $siteType): void
    {
        switch ($siteType) {
            case 'hotel':
                $stars = trim((string) $this->params->get('hotel_star_rating', ''));
                if ($stars) {
                    $schema['starRating'] = [
                        '@type'       => 'Rating',
                        'ratingValue' => $stars,
                    ];
                }
                $checkin  = trim((string) $this->params->get('hotel_checkin_time', ''));
                $checkout = trim((string) $this->params->get('hotel_checkout_time', ''));
                if ($checkin)  { $schema['checkinTime']  = $checkin; }
                if ($checkout) { $schema['checkoutTime'] = $checkout; }
                if ((int) $this->params->get('hotel_pets_allowed', 0)) {
                    $schema['petsAllowed'] = true;
                }
                break;

            case 'medical':
                $specialty = trim((string) $this->params->get('medical_specialty', ''));
                if ($specialty) {
                    $specs = array_values(array_filter(array_map('trim', explode(',', $specialty))));
                    $schema['medicalSpecialty'] = count($specs) === 1 ? $specs[0] : $specs;
                }
                break;

            case 'lawyer':
                $area = trim((string) $this->params->get('legal_area', ''));
                if ($area) {
                    $areas = array_values(array_filter(array_map('trim', explode(',', $area))));
                    $schema['serviceType'] = count($areas) === 1 ? $areas[0] : $areas;
                }
                break;

            case 'school':
                $eduLevel = trim((string) $this->params->get('edu_level', ''));
                if ($eduLevel) {
                    $schema['educationalCredentialAwarded'] = $eduLevel;
                }
                break;

            case 'gym':
                $sport = trim((string) $this->params->get('gym_sport', ''));
                if ($sport) {
                    $sports = array_values(array_filter(array_map('trim', explode(',', $sport))));
                    $schema['sport'] = count($sports) === 1 ? $sports[0] : $sports;
                }
                $amenities = trim((string) $this->params->get('gym_amenities', ''));
                if ($amenities) {
                    $amenityList = array_values(array_filter(array_map('trim', explode(',', $amenities))));
                    $schema['amenityFeature'] = array_map(
                        fn($a) => ['@type' => 'LocationFeatureSpecification', 'name' => $a, 'value' => true],
                        $amenityList
                    );
                }
                break;

            case 'dentist':
                $dental = trim((string) $this->params->get('dental_specialty', ''));
                if ($dental) {
                    $specs = array_values(array_filter(array_map('trim', explode(',', $dental))));
                    $schema['medicalSpecialty'] = count($specs) === 1 ? $specs[0] : $specs;
                }
                break;

            case 'realestate':
                $areaServed = trim((string) $this->params->get('realestate_area_served', ''));
                if ($areaServed) {
                    $schema['areaServed'] = $areaServed;
                }
                $propTypes = trim((string) $this->params->get('realestate_property_types', ''));
                if ($propTypes) {
                    $types = array_values(array_filter(array_map('trim', explode(',', $propTypes))));
                    $schema['serviceType'] = count($types) === 1 ? $types[0] : $types;
                }
                break;

            case 'portfolio':
                $jobTitle = trim((string) $this->params->get('job_title', ''));
                if ($jobTitle) {
                    $schema['jobTitle'] = $jobTitle;
                }
                $portfolioUrl = trim((string) $this->params->get('portfolio_url', ''));
                if ($portfolioUrl) {
                    $existing = (array) ($schema['sameAs'] ?? []);
                    $schema['sameAs'] = array_merge($existing, [$portfolioUrl]);
                }
                break;

            case 'news':
                $topics = trim((string) $this->params->get('news_topics', ''));
                if ($topics) {
                    $topicList = array_values(array_filter(array_map('trim', explode(',', $topics))));
                    $schema['about'] = array_map(fn($t) => ['@type' => 'Thing', 'name' => $t], $topicList);
                }
                $principles = trim((string) $this->params->get('news_principles', ''));
                if ($principles) {
                    $schema['publishingPrinciples'] = $principles;
                }
                break;
        }
    }

    // ── Article / BlogPosting ───────────────────────────────────────────────

    private function injectArticleSchema($document, $app): void
    {
        $input = $app->getInput();
        if ($input->get('option') !== 'com_content' || $input->get('view') !== 'article') {
            return;
        }
        $articleId = (int) $input->get('id', 0);
        if (!$articleId) {
            return;
        }

        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select(['id', 'title', 'introtext', 'created', 'modified', 'created_by', 'images', 'catid'])
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('id') . ' = ' . $articleId)
                ->where($db->quoteName('state') . ' = 1');
            $db->setQuery($query);
            $row = $db->loadObject();
        } catch (\Throwable $e) {
            error_log('[AI Boost Schema] Article load error: ' . $e->getMessage());
            return;
        }
        if (!$row) {
            return;
        }

        $this->applyFalangTranslation($row, $articleId);

        $schemaTypeRaw   = (string) $this->params->get('schema_type', 'organization');
        $alwaysBlogPost  = (int) $this->params->get('article_schema_type_auto', 0);
        $articleTypeMap  = ['news' => 'NewsArticle', 'blog' => 'BlogPosting', 'portfolio' => 'BlogPosting'];
        $articleType     = $alwaysBlogPost ? 'BlogPosting' : ($articleTypeMap[$schemaTypeRaw] ?? 'Article');

        // Per-article schema type override via Joomla custom field "aiboost_article_type"
        try {
            $db  = Factory::getDbo();
            $cfQ = $db->getQuery(true)
                ->select([$db->quoteName('fv.value')])
                ->from($db->quoteName('#__fields_values', 'fv'))
                ->join('INNER', $db->quoteName('#__fields', 'f') . ' ON f.id = fv.field_id')
                ->where($db->quoteName('fv.item_id') . ' = ' . $articleId)
                ->where($db->quoteName('f.name') . ' = ' . $db->quote('aiboost_article_type'))
                ->where($db->quoteName('f.state') . ' = 1');
            $db->setQuery($cfQ);
            $cfType = trim((string) ($db->loadResult() ?? ''));
            if ($cfType !== '') {
                $articleType = $cfType;
            }
        } catch (\Throwable $e) {
            error_log('[AI Boost Schema] Custom field article type error: ' . $e->getMessage());
        }

        $uri     = Uri::getInstance();
        $baseUrl = $uri->getScheme() . '://' . $uri->getHost();
        $orgUrl  = trim((string) $this->params->get('org_url', '')) ?: $baseUrl;
        $orgName = trim((string) $this->params->get('org_name', ''));
        $logo    = trim((string) $this->params->get('org_logo', ''));

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => $articleType,
            'headline' => $row->title ?? '',
            'url'      => $baseUrl . $uri->getPath(),
        ];

        if (!empty($row->created)) {
            try { $schema['datePublished'] = (new \DateTime($row->created))->format('c'); } catch (\Throwable) {}
        }
        if (!empty($row->modified) && $row->modified !== '0000-00-00 00:00:00') {
            try { $schema['dateModified'] = (new \DateTime($row->modified))->format('c'); } catch (\Throwable) {}
        }

        // Author: prefer plugin param override, then DB lookup
        $authorName = trim((string) $this->params->get('schema_author_name', ''));
        $authorUrl  = trim((string) $this->params->get('schema_author_url', ''));
        if (!$authorName && !empty($row->created_by)) {
            try {
                $user       = Factory::getUser($row->created_by);
                $authorName = $user->name ?: $user->username;
            } catch (\Throwable $e) {
                error_log('[AI Boost Schema] Author lookup error: ' . $e->getMessage());
            }
        }
        if ($authorName) {
            $author = ['@type' => 'Person', 'name' => $authorName];
            if ($authorUrl) {
                $author['url'] = $authorUrl;
            }
            $schema['author'] = $author;
        }

        // Publisher
        if ($orgName) {
            $pub = ['@type' => 'Organization', 'name' => $orgName];
            if ($orgUrl) { $pub['url'] = $orgUrl; }
            if ($logo)   { $pub['logo'] = ['@type' => 'ImageObject', 'url' => $this->resolveUrl($logo)]; }
            $schema['publisher'] = $pub;
        }

        // Article image
        $imagesJson = (string) ($row->images ?? '');
        if ($imagesJson) {
            $images = json_decode($imagesJson, true);
            $img    = $images['image_intro'] ?? $images['image_fulltext'] ?? '';
            if ($img) {
                $schema['image'] = ['@type' => 'ImageObject', 'url' => $this->resolveUrl($img)];
            }
        }

        // Description from introtext
        $intro = trim(strip_tags((string) ($row->introtext ?? '')));
        if ($intro) {
            $schema['description'] = mb_substr($intro, 0, 300);
        }

        if ($orgUrl) {
            $schema['isPartOf'] = ['@type' => 'WebSite', 'url' => rtrim($orgUrl, '/') . '/'];
        }

        $this->addJsonLd($document, $schema);
    }

    // ── FAQ Schema (auto-detect from custom fields) ─────────────────────────

    private function injectFaqSchema($document, $app): void
    {
        $input = $app->getInput();
        if ($input->get('option') !== 'com_content' || $input->get('view') !== 'article') {
            return;
        }
        $articleId = (int) $input->get('id', 0);
        if (!$articleId) {
            return;
        }

        $faqItems = $this->loadFaqFromCustomFields($articleId);
        if (empty($faqItems)) {
            return;
        }

        $schema = [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => [],
        ];
        foreach ($faqItems as $item) {
            $schema['mainEntity'][] = [
                '@type'          => 'Question',
                'name'           => $item['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']],
            ];
        }

        if (!empty($schema['mainEntity'])) {
            $this->addJsonLd($document, $schema);
        }
    }

    private function loadFaqFromCustomFields(int $articleId): array
    {
        $items = [];
        try {
            $db = Factory::getDbo();
            $q  = $db->getQuery(true)
                ->select([$db->quoteName('f.name', 'fname'), $db->quoteName('fv.value', 'fval')])
                ->from($db->quoteName('#__fields_values', 'fv'))
                ->join('INNER', $db->quoteName('#__fields', 'f') . ' ON f.id = fv.field_id')
                ->where($db->quoteName('fv.item_id') . ' = ' . $articleId)
                ->where($db->quoteName('f.state') . ' = 1')
                ->where($db->quoteName('f.context') . ' = ' . $db->quote('com_content.article'))
                ->order($db->quoteName('f.ordering'));
            $db->setQuery($q);
            $fields = $db->loadObjectList();

            $questions = [];
            $answers   = [];
            foreach ($fields as $field) {
                $name = (string) ($field->fname ?? '');
                $val  = trim(strip_tags((string) ($field->fval ?? '')));
                if (!$val) {
                    continue;
                }
                if (preg_match('/^faq_q[_\-]?(\d+)$/i', $name, $m)) {
                    $questions[(int) $m[1]] = $val;
                } elseif (preg_match('/^faq_a[_\-]?(\d+)$/i', $name, $m)) {
                    $answers[(int) $m[1]] = $val;
                }
            }
            foreach ($questions as $idx => $q) {
                $a = $answers[$idx] ?? '';
                if ($q && $a) {
                    $items[] = ['q' => $q, 'a' => $a];
                }
            }
        } catch (\Throwable $e) {
            error_log('[AI Boost Schema] FAQ custom field load error: ' . $e->getMessage());
        }
        return $items;
    }

    // ── Manual FAQ (global JSON) ────────────────────────────────────────────

    private function injectManualFaqSchema($document, $app): void
    {
        $raw = trim((string) $this->params->get('manual_faqs_json', ''));
        if (!$raw) {
            return;
        }

        $scope = trim((string) $this->params->get('manual_faq_scope', 'fallback_all'));
        if ($scope === 'disabled') {
            return;
        }

        $input      = $app->getInput();
        $option     = $input->get('option', '');
        $view       = $input->get('view', '');
        $isHomepage = ($option === '' || ($option === 'com_content' && $view === 'featured'));
        if (!$isHomepage) {
            $path = ltrim(Uri::getInstance()->getPath(), '/');
            if ($path === '' || $path === 'index.php') {
                $isHomepage = true;
            }
        }

        $isArticle = $option === 'com_content' && $view === 'article';

        // Scope filtering
        if (($scope === 'fallback_home' || $scope === 'always_home') && !$isHomepage) {
            return;
        }

        // "fallback" modes: only inject if no auto-detected FAQ was already injected
        if ($scope === 'fallback_all' && $isArticle) {
            $articleId = (int) $input->get('id', 0);
            if ($articleId && !empty($this->loadFaqFromCustomFields($articleId))) {
                return;
            }
        }
        if ($scope === 'fallback_home' && !$isHomepage) {
            return;
        }

        try {
            $faqData = json_decode($raw, true);
        } catch (\Throwable $e) {
            error_log('[AI Boost Schema] Manual FAQ JSON parse error: ' . $e->getMessage());
            return;
        }
        if (!is_array($faqData) || empty($faqData)) {
            return;
        }

        $schema = [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => [],
        ];
        foreach ($faqData as $item) {
            $q = trim((string) ($item['question'] ?? ''));
            $a = trim((string) ($item['answer'] ?? ''));
            if ($q && $a) {
                $schema['mainEntity'][] = [
                    '@type'          => 'Question',
                    'name'           => $q,
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a],
                ];
            }
        }

        if (!empty($schema['mainEntity'])) {
            $this->addJsonLd($document, $schema);
        }
    }

    // ── Events Schema ───────────────────────────────────────────────────────

    private function injectEventsSchema($document): void
    {
        $raw = trim((string) $this->params->get('schema_events_json', ''));
        if (!$raw) {
            return;
        }

        try {
            $events = json_decode($raw, true);
        } catch (\Throwable $e) {
            error_log('[AI Boost Schema] Events JSON parse error: ' . $e->getMessage());
            return;
        }
        if (!is_array($events) || empty($events)) {
            return;
        }

        $attendanceModeMap = [
            'online'  => 'https://schema.org/OnlineEventAttendanceMode',
            'offline' => 'https://schema.org/OfflineEventAttendanceMode',
            'mixed'   => 'https://schema.org/MixedEventAttendanceMode',
        ];

        foreach ($events as $event) {
            $name      = trim((string) ($event['name'] ?? ''));
            $startDate = trim((string) ($event['startDate'] ?? ''));
            if (!$name || !$startDate) {
                continue;
            }

            $schema = [
                '@context'  => 'https://schema.org',
                '@type'     => 'Event',
                'name'      => $name,
                'startDate' => $startDate,
            ];

            $endDate = trim((string) ($event['endDate'] ?? ''));
            if ($endDate) { $schema['endDate'] = $endDate; }

            $description = trim((string) ($event['description'] ?? ''));
            if ($description) { $schema['description'] = $description; }

            $url = trim((string) ($event['url'] ?? ''));
            if ($url) { $schema['url'] = $url; }

            // Location
            $locationName = trim((string) ($event['location'] ?? ''));
            if ($locationName) {
                $schema['location'] = ['@type' => 'Place', 'name' => $locationName];
            }

            // Offers (price + currency)
            $price    = trim((string) ($event['price'] ?? ''));
            $currency = trim((string) ($event['currency'] ?? 'EUR'));
            if ($price !== '') {
                $schema['offers'] = [
                    '@type'         => 'Offer',
                    'price'         => $price,
                    'priceCurrency' => $currency,
                    'availability'  => 'https://schema.org/InStock',
                ];
            }

            // Attendance mode
            $attendanceMode = strtolower(trim((string) ($event['attendanceMode'] ?? '')));
            if (isset($attendanceModeMap[$attendanceMode])) {
                $schema['eventAttendanceMode'] = $attendanceModeMap[$attendanceMode];
            }

            // Organizer (org name from plugin)
            $orgName = trim((string) $this->params->get('org_name', ''));
            $orgUrl  = trim((string) $this->params->get('org_url', ''));
            if ($orgName) {
                $organizer = ['@type' => 'Organization', 'name' => $orgName];
                if ($orgUrl) { $organizer['url'] = $orgUrl; }
                $schema['organizer'] = $organizer;
            }

            $this->addJsonLd($document, $schema);
        }
    }

    // ── BreadcrumbList ──────────────────────────────────────────────────────

    private function injectBreadcrumbSchema($document, $app): void
    {
        $input  = $app->getInput();
        $option = $input->get('option', '');
        $view   = $input->get('view', '');

        if ($option === '' || $view === 'featured') {
            return;
        }

        $uri     = Uri::getInstance();
        $baseUrl = $uri->getScheme() . '://' . $uri->getHost();
        $orgUrl  = trim((string) $this->params->get('org_url', '')) ?: $baseUrl;
        $orgName = trim((string) $this->params->get('org_name', '')) ?: $app->get('sitename', '');

        $items = [];
        $pos   = 1;

        $items[] = [
            '@type'    => 'ListItem',
            'position' => $pos++,
            'name'     => $orgName,
            'item'     => rtrim($orgUrl, '/') . '/',
        ];

        if ($option === 'com_content' && $view === 'article') {
            $articleId = (int) $input->get('id', 0);
            if ($articleId) {
                try {
                    $db = Factory::getDbo();
                    $q  = $db->getQuery(true)
                        ->select(['c.title AS cat_title', 'c.alias AS cat_alias'])
                        ->from($db->quoteName('#__content', 'a'))
                        ->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON c.id = a.catid')
                        ->where($db->quoteName('a.id') . ' = ' . $articleId);
                    $db->setQuery($q);
                    $row = $db->loadObject();
                    if ($row && !empty($row->cat_alias)) {
                        $items[] = [
                            '@type'    => 'ListItem',
                            'position' => $pos++,
                            'name'     => $row->cat_title ?? $row->cat_alias,
                            'item'     => $baseUrl . '/' . $row->cat_alias,
                        ];
                    }
                } catch (\Throwable $e) {
                    error_log('[AI Boost Schema] Breadcrumb category lookup error: ' . $e->getMessage());
                }
            }
        }

        $currentTitle = $app->getDocument()->getTitle();
        $currentUrl   = Uri::current();
        if ($currentTitle && $currentUrl) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $pos,
                'name'     => $currentTitle,
                'item'     => $currentUrl,
            ];
        }

        if (count($items) < 2) {
            return;
        }

        $this->addJsonLd($document, [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ]);
    }

    // ── Hreflang XML Sitemap ────────────────────────────────────────────────

    private function serveHreflangSitemap(): void
    {
        try {
            $app     = Factory::getApplication();
            $db      = Factory::getDbo();
            $uri     = Uri::getInstance();
            $baseUrl = $uri->getScheme() . '://' . $uri->getHost();

            $langs = $this->detectJoomlaLanguages();
            if (empty($langs)) {
                return;
            }

            $primarySef = trim((string) $this->params->get('hreflang_primary_language', 'en'));
            $xDefaultOverride = trim((string) $this->params->get('hreflang_x_default_url', ''));

            $urls = [];
            $q    = $db->getQuery(true)
                ->select(['a.alias', 'c.alias AS cat_alias', 'a.modified'])
                ->from($db->quoteName('#__content', 'a'))
                ->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON c.id = a.catid')
                ->where('a.state = 1')
                ->order('a.modified DESC');
            $db->setQuery($q, 0, 500);
            foreach ($db->loadObjectList() as $art) {
                $slug   = ($art->cat_alias ? $art->cat_alias . '/' : '') . $art->alias;
                $urls[] = [
                    'path'    => '/' . $slug,
                    'lastmod' => $art->modified ? date('Y-m-d', strtotime($art->modified)) : date('Y-m-d'),
                ];
            }

            $xmlNs = 'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml"';
            $xml   = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $xml  .= '<urlset ' . $xmlNs . '>' . "\n";

            foreach ($urls as $u) {
                $xml .= "  <url>\n";
                $xml .= '    <loc>' . htmlspecialchars($baseUrl . $u['path']) . "</loc>\n";
                $xml .= '    <lastmod>' . htmlspecialchars($u['lastmod']) . "</lastmod>\n";

                $xDefault = $xDefaultOverride ?: null;
                foreach ($langs as $idx => $lang) {
                    $sef      = (string) ($lang['sef'] ?? '');
                    $langCode = htmlspecialchars(strtolower(str_replace('_', '-', (string) ($lang['lang_code'] ?? ''))));
                    $altUrl   = htmlspecialchars($baseUrl . '/' . $sef . $u['path']);
                    $xml     .= '    <xhtml:link rel="alternate" hreflang="' . $langCode . '" href="' . $altUrl . '"/>' . "\n";
                    if ($xDefault === null && ($idx === 0 || $sef === $primarySef)) {
                        $xDefault = htmlspecialchars($baseUrl . '/' . $sef . $u['path']);
                    }
                }
                if ($xDefault !== null) {
                    $xml .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . $xDefault . '"/>' . "\n";
                }
                $xml .= "  </url>\n";
            }

            $xml .= '</urlset>';

            header('Content-Type: application/xml; charset=utf-8');
            header('Cache-Control: public, max-age=3600');
            header('X-Robots-Tag: noindex');
            echo $xml;
            $app->close();
        } catch (\Throwable $e) {
            error_log('[AI Boost Schema] Hreflang sitemap error: ' . $e->getMessage());
        }
    }

    private function detectJoomlaLanguages(): array
    {
        if (!empty($this->detectedLanguages)) {
            return $this->detectedLanguages;
        }
        try {
            $allLangs = \Joomla\CMS\Language\LanguageHelper::getLanguages();
            $result   = [];
            foreach ($allLangs as $lang) {
                if ((int) ($lang->published ?? 0) !== 1) {
                    continue;
                }
                $code = (string) ($lang->lang_code ?? '');
                $sef  = strtolower(trim((string) ($lang->sef ?? '')));
                if (!$code || !$sef) {
                    continue;
                }
                $result[] = [
                    'lang_id'   => (string) ($lang->lang_id ?? ''),
                    'lang_code' => $code,
                    'sef'       => $sef,
                    'title'     => (string) ($lang->title ?? $code),
                ];
            }
            return $this->detectedLanguages = $result;
        } catch (\Throwable $e) {
            error_log('[AI Boost Schema] Language detection error: ' . $e->getMessage());
            return [];
        }
    }

    // ── Falang multilingual support ─────────────────────────────────────────

    private function applyFalangTranslation(object $row, int $articleId): void
    {
        try {
            $app         = Factory::getApplication();
            $langTag     = $app->getLanguage()->getTag();
            $siteDefault = trim((string) Factory::getApplication()->get('language', ''));
            if (!$langTag || ($siteDefault && strtolower($langTag) === strtolower($siteDefault))) {
                return;
            }

            $db     = Factory::getDbo();
            $tables = $db->getTableList();
            $prefix = $db->getPrefix();
            if (!in_array($prefix . 'falang_content', $tables, true)) {
                return;
            }

            $qLang = $db->getQuery(true)
                ->select($db->quoteName('lang_id'))
                ->from($db->quoteName('#__languages'))
                ->where($db->quoteName('lang_code') . ' = ' . $db->quote($langTag))
                ->where($db->quoteName('published') . ' = 1');
            $db->setQuery($qLang);
            $langId = (int) $db->loadResult();
            if (!$langId) {
                return;
            }

            $q = $db->getQuery(true)
                ->select([$db->quoteName('fc.reference_field', 'field'), $db->quoteName('fc.value', 'val')])
                ->from($db->quoteName('#__falang_content', 'fc'))
                ->where($db->quoteName('fc.reference_table') . ' = ' . $db->quote('#__content'))
                ->where($db->quoteName('fc.reference_id')    . ' = ' . $articleId)
                ->where($db->quoteName('fc.language_id')     . ' = ' . $langId)
                ->where($db->quoteName('fc.published')       . ' = 1')
                ->where($db->quoteName('fc.reference_field') . ' IN (' . implode(',', array_map([$db, 'quote'], ['title', 'introtext'])) . ')');
            $db->setQuery($q);

            foreach ($db->loadObjectList() as $t) {
                $val = trim((string) ($t->val ?? ''));
                if ($t->field === 'title' && $val !== '') {
                    $row->title = $val;
                } elseif ($t->field === 'introtext' && $val !== '') {
                    $row->introtext = $val;
                }
            }
        } catch (\Throwable $e) {
            error_log('[AI Boost Schema] Falang translation error: ' . $e->getMessage());
        }
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function resolveUrl(string $path): string
    {
        if (!$path || strpos($path, 'http') === 0) {
            return $path;
        }
        $uri = Uri::getInstance();
        return $uri->getScheme() . '://' . $uri->getHost() . '/' . ltrim($path, '/');
    }

    private function addJsonLd($document, array $schema): void
    {
        $json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $document->addCustomTag('<script type="application/ld+json">' . $json . '</script>');
    }
}
