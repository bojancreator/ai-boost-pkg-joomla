<?php
/**
 * AI Boost — SchemaBuilder
 *
 * Builds the JSON-LD blocks for the current page request and returns them as
 * PHP arrays. The Organization block can be upgraded to a more specific
 * schema.org type via the shared `schema_type` setting.
 *
 * Baseline output:
 *   - Organization (@type=Organization)         — identity, address, logo, social
 *   - WebSite + SearchAction (homepage only)
 *   - BreadcrumbList                            — from Joomla pathway
 *
 * @package     AiBoost\Plugin\System\AiBoostSchema
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostSchema\Service;

defined('_JEXEC') or die;

use AiBoost\Lib\AppContextInterface;
use Joomla\Database\DatabaseInterface;

class SchemaBuilder
{
    private const SCHEMA_TYPE_ALIASES = [
        'organization'            => 'Organization',
        'localbusiness'           => 'LocalBusiness',
        'foodestablishment'       => 'FoodEstablishment',
        'restaurant'              => 'Restaurant',
        'educationalorganization' => 'EducationalOrganization',
        'educationalorg'          => 'EducationalOrganization',
        'school'                  => 'EducationalOrganization',
        'lodgingbusiness'         => 'LodgingBusiness',
        'hotel'                   => 'LodgingBusiness',
        'medicalclinic'           => 'MedicalClinic',
        'medical'                 => 'MedicalClinic',
        'legalservice'            => 'LegalService',
        'lawyer'                  => 'LegalService',
        'sportsactivitylocation'  => 'SportsActivityLocation',
        'sportsactivity'          => 'SportsActivityLocation',
        'gym'                     => 'SportsActivityLocation',
        'dentist'                 => 'Dentist',
        'realestateagent'         => 'RealEstateAgent',
        'realestate'              => 'RealEstateAgent',
        'automotivebusiness'      => 'AutomotiveBusiness',
        'store'                   => 'Store',
        'touristattraction'       => 'TouristAttraction',
        'professionalservice'     => 'ProfessionalService',
        'person'                  => 'Person',
        'portfolio'               => 'Person',
        'newsmediaorganization'   => 'NewsMediaOrganization',
        'news'                    => 'NewsMediaOrganization',
    ];

    private const LOCAL_BUSINESS_TYPES = [
        'LocalBusiness' => true,
        'FoodEstablishment' => true,
        'Restaurant' => true,
        'LodgingBusiness' => true,
        'MedicalClinic' => true,
        'LegalService' => true,
        'SportsActivityLocation' => true,
        'Dentist' => true,
        'RealEstateAgent' => true,
        'AutomotiveBusiness' => true,
        'Store' => true,
        'TouristAttraction' => true,
        'ProfessionalService' => true,
    ];

    private const BUSINESS_HOURS_DAYS = [
        'mon' => 'Monday',
        'tue' => 'Tuesday',
        'wed' => 'Wednesday',
        'thu' => 'Thursday',
        'fri' => 'Friday',
        'sat' => 'Saturday',
        'sun' => 'Sunday',
    ];

    /** @var array<string,mixed> */
    private array               $settings;
    private AppContextInterface $ctx;
    private DatabaseInterface   $db;

    /**
     * @param array<string,mixed> $settings  Shared AI Boost settings array.
     */
    public function __construct(
        array $settings,
        AppContextInterface $ctx,
        DatabaseInterface $db
    ) {
        $this->settings = $settings;
        $this->ctx      = $ctx;
        $this->db       = $db;
    }

    /**
    * Build the schema blocks.
     *
     * @return array<int, array<string, mixed>>  One PHP array per JSON-LD block.
     */
    public function buildAll(): array
    {
        $schemas = [];

        if ((int)($this->settings['website_schema_enabled'] ?? 1)) {
            $ws = $this->buildWebSite();
            if ($ws) {
                $schemas[] = $ws;
            }
        }

        $org = $this->buildOrganization();
        if ($org) {
            $schemas[] = $org;
        }

        $bc = $this->buildBreadcrumb();
        if ($bc) {
            $schemas[] = $bc;
        }

        return $schemas;
    }

    /**
     * Organization JSON-LD, upgraded to the selected schema.org type when set.
     *
     * @return array<string, mixed>|null
     */
    private function buildOrganization(): ?array
    {
        $orgName = trim((string)($this->settings['org_name'] ?? ''));
        if ($orgName === '') {
            return null;
        }

        $schemaType = $this->resolveSchemaType((string) ($this->settings['schema_type'] ?? 'Organization'));

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => $schemaType,
            'name'     => $orgName,
        ];

        $orgUrl = trim((string)($this->settings['org_url'] ?? ''));
        $schema['url'] = $orgUrl !== '' ? $orgUrl : $this->ctx->getBaseUrl() . '/';

        $orgDesc = trim((string)($this->settings['org_description'] ?? ''));
        if ($orgDesc !== '') {
            $schema['description'] = $orgDesc;
        }

        $logo = trim((string)($this->settings['org_logo'] ?? ''));
        if ($logo !== '') {
            $schema['logo'] = ['@type' => 'ImageObject', 'url' => $this->absoluteUrl($logo)];
        }

        $phone = trim((string)($this->settings['org_phone'] ?? ''));
        if ($phone !== '') {
            $schema['telephone'] = $phone;
        }

        $email = trim((string)($this->settings['org_email'] ?? ''));
        if ($email !== '') {
            $schema['email'] = $email;
        }

        $addrStreet  = trim((string)($this->settings['org_address_street']  ?? ''));
        $addrCity    = trim((string)($this->settings['org_address_city']    ?? ''));
        $addrState   = trim((string)($this->settings['org_address_state']   ?? ''));
        $addrZip     = trim((string)($this->settings['org_address_zip']     ?? ''));
        $addrCountry = trim((string)($this->settings['org_address_country'] ?? ''));
        if ($addrStreet || $addrCity || $addrZip || $addrCountry) {
            $addr = ['@type' => 'PostalAddress'];
            if ($addrStreet)  $addr['streetAddress']  = $addrStreet;
            if ($addrCity)    $addr['addressLocality'] = $addrCity;
            if ($addrState)   $addr['addressRegion']   = $addrState;
            if ($addrZip)     $addr['postalCode']      = $addrZip;
            if ($addrCountry) $addr['addressCountry']  = $addrCountry;
            $schema['address'] = $addr;
        }

        $lat = trim((string)($this->settings['org_latitude']  ?? ''));
        $lng = trim((string)($this->settings['org_longitude'] ?? ''));
        if ($lat !== '' && $lng !== '') {
            $schema['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $lat, 'longitude' => $lng];
        }

        $sameAs = [];
        foreach (['facebook', 'instagram', 'youtube', 'twitter', 'linkedin'] as $net) {
            $url = trim((string)($this->settings["social_{$net}"] ?? ''));
            if ($url !== '') {
                $sameAs[] = $url;
            }
        }
        if ($sameAs) {
            $schema['sameAs'] = $sameAs;
        }

        $this->decorateBusinessDetails($schema, $schemaType);

        return $schema;
    }

    private function resolveSchemaType(string $type): string
    {
        $normalized = strtolower(preg_replace('/[^a-z0-9]/i', '', trim($type)) ?: '');
        return self::SCHEMA_TYPE_ALIASES[$normalized] ?? 'Organization';
    }

    /** @param array<string, mixed> $schema */
    private function decorateBusinessDetails(array &$schema, string $schemaType): void
    {
        $ratingValue = trim((string) ($this->settings['rating_value'] ?? ''));
        $ratingCount = trim((string) ($this->settings['rating_count'] ?? ''));
        if ($ratingValue !== '' && $ratingCount !== '' && (float) $ratingValue > 0 && (int) $ratingCount > 0) {
            $schema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $ratingValue,
                'reviewCount' => $ratingCount,
                'bestRating' => trim((string) ($this->settings['rating_best'] ?? '5')) ?: '5',
                'worstRating' => trim((string) ($this->settings['rating_worst'] ?? '1')) ?: '1',
            ];
        }

        if (isset(self::LOCAL_BUSINESS_TYPES[$schemaType])) {
            $hours = $this->buildBusinessHours();
            if ($hours) {
                $schema['openingHoursSpecification'] = $hours;
            }

            $priceRange = trim((string) ($this->settings['specific_price_range'] ?? ''));
            if ($priceRange !== '') {
                $schema['priceRange'] = $priceRange;
            }
        }

        if (in_array($schemaType, ['Restaurant', 'FoodEstablishment', 'LodgingBusiness'], true)) {
            $cuisine = trim((string) ($this->settings['specific_serves_cuisine'] ?? ''));
            if ($cuisine !== '') {
                $schema['servesCuisine'] = $cuisine;
            }
        }

        if ($schemaType === 'LodgingBusiness') {
            $starRating = trim((string) ($this->settings['specific_star_rating'] ?? ''));
            if ($starRating !== '' && (int) $starRating > 0) {
                $schema['starRating'] = ['@type' => 'Rating', 'ratingValue' => $starRating];
            }

            $checkin = trim((string) ($this->settings['specific_checkin_time'] ?? ''));
            if ($checkin !== '') {
                $schema['checkinTime'] = $checkin;
            }

            $checkout = trim((string) ($this->settings['specific_checkout_time'] ?? ''));
            if ($checkout !== '') {
                $schema['checkoutTime'] = $checkout;
            }

            $petsAllowed = trim((string) ($this->settings['specific_pets_allowed'] ?? ''));
            if (in_array($petsAllowed, ['true', 'false'], true)) {
                $schema['petsAllowed'] = $petsAllowed === 'true';
            }
        }

        if (in_array($schemaType, ['MedicalClinic', 'Dentist', 'LegalService', 'EducationalOrganization', 'SportsActivityLocation', 'AutomotiveBusiness', 'ProfessionalService'], true)) {
            $service = trim((string) ($this->settings['specific_available_service'] ?? ''));
            if ($service !== '') {
                $schema['availableService'] = $service;
            }
        }

        if (in_array($schemaType, ['LocalBusiness', 'FoodEstablishment', 'Restaurant', 'MedicalClinic', 'LegalService', 'EducationalOrganization', 'SportsActivityLocation', 'Dentist', 'RealEstateAgent', 'AutomotiveBusiness', 'ProfessionalService', 'Store', 'TouristAttraction'], true)) {
            $areaServed = trim((string) ($this->settings['specific_area_served'] ?? ''));
            if ($areaServed !== '') {
                $schema['areaServed'] = $areaServed;
            }
        }

        if (isset(self::LOCAL_BUSINESS_TYPES[$schemaType])) {
            $paymentAccepted = trim((string) ($this->settings['specific_payment_accepted'] ?? ''));
            if ($paymentAccepted !== '') {
                $schema['paymentAccepted'] = $paymentAccepted;
            }
        }

        if (in_array($schemaType, ['FoodEstablishment', 'Restaurant', 'LodgingBusiness', 'SportsActivityLocation', 'Store', 'TouristAttraction'], true)) {
            $amenities = $this->csvList((string) ($this->settings['specific_amenity_feature'] ?? ''));
            if ($amenities) {
                $schema['amenityFeature'] = array_map(
                    static fn(string $amenity): array => [
                        '@type' => 'LocationFeatureSpecification',
                        'name' => $amenity,
                        'value' => true,
                    ],
                    $amenities
                );
            }
        }

        if ($schemaType === 'Person') {
            $jobTitle = trim((string) ($this->settings['specific_job_title'] ?? ''));
            if ($jobTitle !== '') {
                $schema['jobTitle'] = $jobTitle;
            }

            $affiliation = trim((string) ($this->settings['specific_affiliation'] ?? ''));
            if ($affiliation !== '') {
                $schema['affiliation'] = ['@type' => 'Organization', 'name' => $affiliation];
            }
        }

        if (in_array($schemaType, ['Person', 'MedicalClinic', 'LegalService', 'EducationalOrganization', 'Dentist', 'ProfessionalService'], true)) {
            $knowsAbout = $this->csvList((string) ($this->settings['specific_knows_about'] ?? ''));
            if ($knowsAbout) {
                $schema['knowsAbout'] = $knowsAbout;
            }
        }

        if ($schemaType === 'NewsMediaOrganization') {
            $foundingDate = trim((string) ($this->settings['specific_founding_date'] ?? ''));
            if ($foundingDate !== '') {
                $schema['foundingDate'] = $foundingDate;
            }

            $masthead = trim((string) ($this->settings['specific_masthead_url'] ?? ''));
            if ($masthead !== '') {
                $schema['masthead'] = $this->absoluteUrl($masthead);
            }

            $ethicsPolicy = trim((string) ($this->settings['specific_ethics_policy_url'] ?? ''));
            if ($ethicsPolicy !== '') {
                $schema['ethicsPolicy'] = $this->absoluteUrl($ethicsPolicy);
            }
        }
    }

    /** @return array<int, string> */
    private function csvList(string $value): array
    {
        $items = array_map('trim', explode(',', $value));
        $items = array_values(array_filter($items, static fn(string $item): bool => $item !== ''));

        return array_values(array_unique($items));
    }

    /** @return array<int, array<string, string>> */
    private function buildBusinessHours(): array
    {
        $specs = [];

        foreach (self::BUSINESS_HOURS_DAYS as $key => $dayName) {
            if ((int) ($this->settings["hours_{$key}_closed"] ?? 0)) {
                continue;
            }

            $opens = trim((string) ($this->settings["hours_{$key}_opens"] ?? ''));
            $closes = trim((string) ($this->settings["hours_{$key}_closes"] ?? ''));
            if (!$this->isValidBusinessTime($opens) || !$this->isValidBusinessTime($closes)) {
                continue;
            }

            $specs[] = [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'https://schema.org/' . $dayName,
                'opens' => $opens,
                'closes' => $closes,
            ];
        }

        return $specs;
    }

    private function isValidBusinessTime(string $time): bool
    {
        if (!preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            return false;
        }

        [$hour, $minute] = explode(':', $time);
        return (int) $hour <= 23 && (int) $minute <= 59;
    }

    /**
     * WebSite + SearchAction JSON-LD — homepage only.
     *
     * @return array<string, mixed>|null
     */
    private function buildWebSite(): ?array
    {
        if (!$this->ctx->isHomepage()) {
            return null;
        }

        $baseUrl = trim((string)($this->settings['org_url'] ?? ''));
        if ($baseUrl === '') {
            $baseUrl = $this->ctx->getBaseUrl();
        }
        $baseUrl = rtrim($baseUrl, '/');

        $orgName = trim((string)($this->settings['org_name'] ?? ''));
        $schema  = [
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            'name'     => $orgName !== '' ? $orgName : $this->ctx->getSiteName(),
            'url'      => $baseUrl . '/',
        ];

        if ((int)($this->settings['enable_search_action'] ?? 1)) {
            $schema['potentialAction'] = [
                '@type'       => 'SearchAction',
                'target'      => [
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => $baseUrl . '/index.php?option=com_search&searchword={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ];
        }

        return $schema;
    }

    /**
     * BreadcrumbList JSON-LD — auto-generated from the CMS pathway.
     *
     * @return array<string, mixed>|null
     */
    private function buildBreadcrumb(): ?array
    {
        $pathway = $this->ctx->getPathway();
        if (empty($pathway)) {
            return null;
        }

        $root     = $this->ctx->getBaseUrl();
        $items    = [];
        $position = 1;

        $homeName = $this->ctx->translate('HOME');
        $items[]  = [
            '@type'    => 'ListItem',
            'position' => $position++,
            'name'     => $homeName !== '' ? $homeName : 'Home',
            'item'     => $root . '/',
        ];

        foreach ($pathway as $step) {
            $name = trim(strip_tags((string) $step['name']));
            if ($name === '') {
                continue;
            }
            $item = [
                '@type'    => 'ListItem',
                'position' => $position++,
                'name'     => $name,
            ];
            $link = trim((string) $step['link']);
            if ($link !== '' && $link !== 'index.php') {
                if (!str_starts_with($link, 'http')) {
                    $link = $root . '/' . ltrim($link, '/');
                }
                $item['item'] = $link;
            }
            $items[] = $item;
        }

        if (count($items) <= 1) {
            return null;
        }

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /** Ensure a path or URL is absolute (prepend base URL for relative paths). */
    private function absoluteUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return $this->ctx->getBaseUrl() . '/' . ltrim($path, '/');
    }
}
