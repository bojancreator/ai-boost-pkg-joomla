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
use AiBoost\Lib\Page\PageContext;
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
        'accountingservice'       => 'AccountingService',
        'person'                  => 'Person',
        'portfolio'               => 'Person',
        'newsmediaorganization'   => 'NewsMediaOrganization',
        'news'                    => 'NewsMediaOrganization',
        // ── Food & Drink ──
        'cafeorcoffeeshop'        => 'CafeOrCoffeeShop',
        'cafe'                    => 'CafeOrCoffeeShop',
        'coffeeshop'              => 'CafeOrCoffeeShop',
        'bakery'                  => 'Bakery',
        'barorpub'                => 'BarOrPub',
        'bar'                     => 'BarOrPub',
        'pub'                     => 'BarOrPub',
        // ── Health & Medical ──
        'physician'               => 'Physician',
        'doctor'                  => 'Physician',
        'pharmacy'                => 'Pharmacy',
        'hospital'                => 'Hospital',
        'veterinarycare'          => 'VeterinaryCare',
        'vet'                     => 'VeterinaryCare',
        // ── Lodging & Travel ──
        'bedandbreakfast'         => 'BedAndBreakfast',
        'resort'                  => 'Resort',
        // ── Beauty & Fitness ──
        'beautysalon'             => 'BeautySalon',
        'hairsalon'               => 'HairSalon',
        'nailsalon'               => 'NailSalon',
        'dayspa'                  => 'DaySpa',
        'spa'                     => 'DaySpa',
        'healthclub'              => 'HealthClub',
        // ── Education & Childcare ──
        'childcare'               => 'ChildCare',
        'preschool'               => 'ChildCare',
        // ── Finance ──
        'bankorcreditunion'       => 'BankOrCreditUnion',
        'bank'                    => 'BankOrCreditUnion',
        'financialservice'        => 'FinancialService',
        'insuranceagency'         => 'InsuranceAgency',
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
        'AccountingService' => true,
        // Food & Drink
        'CafeOrCoffeeShop' => true,
        'Bakery' => true,
        'BarOrPub' => true,
        // Health & Medical
        'Physician' => true,
        'Pharmacy' => true,
        'Hospital' => true,
        'VeterinaryCare' => true,
        // Lodging & Travel
        'BedAndBreakfast' => true,
        'Resort' => true,
        // Beauty & Fitness
        'BeautySalon' => true,
        'HairSalon' => true,
        'NailSalon' => true,
        'DaySpa' => true,
        'HealthClub' => true,
        // Education & Childcare
        'EducationalOrganization' => true,
        'ChildCare' => true,
        // Finance
        'BankOrCreditUnion' => true,
        'FinancialService' => true,
        'InsuranceAgency' => true,
    ];

    // Type groups — used to gate which type-specific detail fields emit for
    // which @type. Adding a new type to the right group (here + in SchemaTab.vue)
    // makes all of that group's fields available. (Korak 3.2 #1.)
    private const FOOD_TYPES          = ['Restaurant', 'CafeOrCoffeeShop', 'Bakery', 'BarOrPub', 'FoodEstablishment'];
    private const MEDICAL_TYPES       = ['MedicalClinic', 'Dentist', 'Physician', 'Pharmacy', 'Hospital', 'VeterinaryCare'];
    private const LODGING_TYPES       = ['LodgingBusiness', 'BedAndBreakfast', 'Resort'];
    private const BEAUTY_FITNESS_TYPES = ['BeautySalon', 'HairSalon', 'NailSalon', 'DaySpa', 'HealthClub', 'SportsActivityLocation'];
    private const PRO_SERVICE_TYPES   = ['ProfessionalService', 'LegalService', 'AccountingService', 'RealEstateAgent'];
    private const FINANCE_TYPES       = ['BankOrCreditUnion', 'FinancialService', 'InsuranceAgency'];

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
    private ?PageContext        $pageContext;

    /**
     * @param array<string,mixed> $settings     Shared AI Boost settings array.
     * @param ?PageContext        $pageContext  T1·S2: the resolved per-request page
     *        context. When provided (production, via AdapterRegistry::pageResolver()),
     *        the homepage gate reads its injected isHomepage truth; when null (unit
     *        tests) it falls back to $ctx->isHomepage(). BEHAVIOUR-IDENTICAL:
     *        PageContext::isHomepage IS $ctx->isHomepage() (the menu-home truth).
     */
    public function __construct(
        array $settings,
        AppContextInterface $ctx,
        DatabaseInterface $db,
        ?PageContext $pageContext = null
    ) {
        $this->settings    = $settings;
        $this->ctx         = $ctx;
        $this->db          = $db;
        $this->pageContext = $pageContext;
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
            '@id'      => $this->organizationId(),
            'name'     => $orgName,
        ];

        $orgUrl = trim((string)($this->settings['org_url'] ?? ''));
        $schema['url'] = $orgUrl !== '' ? $orgUrl : $this->ctx->getBaseUrl() . '/';

        $orgDesc = trim((string)($this->settings['org_description'] ?? ''));
        if ($orgDesc !== '') {
            $schema['description'] = $orgDesc;
        }

        // Universal identity fields (Free, Korak 3.2 #1) — apply to any type.
        $legalName = trim((string)($this->settings['org_legal_name'] ?? ''));
        if ($legalName !== '') {
            $schema['legalName'] = $legalName;
        }

        $vatId = trim((string)($this->settings['org_vat_id'] ?? ''));
        if ($vatId !== '') {
            $schema['vatID'] = $vatId;
        }

        $foundingDate = trim((string)($this->settings['org_founding_date'] ?? ''));
        if ($foundingDate !== '') {
            $schema['foundingDate'] = $foundingDate;
        }

        $logo = trim((string)($this->settings['org_logo'] ?? ''));
        if ($logo !== '') {
            $logoObj = ['@type' => 'ImageObject', 'url' => $this->absoluteUrl($logo)];
            $logoAlt = trim((string)($this->settings['org_logo_alt'] ?? ''));
            if ($logoAlt !== '') {
                $logoObj['caption'] = $logoAlt;
            }
            $schema['logo'] = $logoObj;
        }

        $image = trim((string)($this->settings['org_image'] ?? ''));
        if ($image !== '') {
            $schema['image'] = $this->absoluteUrl($image);
        }

        $phone = trim((string)($this->settings['org_phone'] ?? ''));
        if ($phone !== '') {
            $schema['telephone'] = $phone;
        }

        $email = trim((string)($this->settings['org_email'] ?? ''));
        if ($email !== '') {
            $schema['email'] = $email;
        }

        $mapUrl = trim((string)($this->settings['org_map_url'] ?? ''));
        if ($mapUrl !== '') {
            $schema['hasMap'] = $mapUrl;
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
        foreach (['facebook', 'instagram', 'youtube', 'twitter', 'linkedin', 'tiktok'] as $net) {
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

            $specialHours = $this->buildSpecialOpeningHours();
            if ($specialHours) {
                $schema['specialOpeningHoursSpecification'] = $specialHours;
            }

            $priceRange = trim((string) ($this->settings['specific_price_range'] ?? ''));
            if ($priceRange !== '') {
                $schema['priceRange'] = $priceRange;
            }
        }

        if (in_array($schemaType, [...self::FOOD_TYPES, 'LodgingBusiness'], true)) {
            $cuisine = trim((string) ($this->settings['specific_serves_cuisine'] ?? ''));
            if ($cuisine !== '') {
                $schema['servesCuisine'] = $cuisine;
            }
        }

        if (in_array($schemaType, self::FOOD_TYPES, true)) {
            $menuUrl = trim((string) ($this->settings['specific_menu_url'] ?? ''));
            if ($menuUrl !== '') {
                $schema['hasMenu'] = $this->absoluteUrl($menuUrl);
            }

            $acceptsReservations = trim((string) ($this->settings['specific_accepts_reservations'] ?? ''));
            if (in_array($acceptsReservations, ['true', 'false'], true)) {
                $schema['acceptsReservations'] = $acceptsReservations === 'true';
            }
        }

        if (in_array($schemaType, self::LODGING_TYPES, true)) {
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

        if (in_array($schemaType, [...self::MEDICAL_TYPES, ...self::BEAUTY_FITNESS_TYPES, ...self::PRO_SERVICE_TYPES, ...self::FINANCE_TYPES, 'EducationalOrganization', 'AutomotiveBusiness', 'Store', 'ChildCare'], true)) {
            $service = trim((string) ($this->settings['specific_available_service'] ?? ''));
            if ($service !== '') {
                $schema['availableService'] = $service;
            }

            // Pro: rich services list → makesOffer (Offer → itemOffered Service).
            // Field is tier=pro (UI-locked in Free); we emit whatever is stored,
            // matching how the other type-specific Pro properties are handled.
            $offers = $this->buildMakesOffer((string) ($this->settings['schema_services'] ?? ''));
            if ($offers !== []) {
                $schema['makesOffer'] = $offers;
            }
        }

        if (in_array($schemaType, self::MEDICAL_TYPES, true)) {
            $medicalSpecialty = trim((string) ($this->settings['specific_medical_specialty'] ?? ''));
            if ($medicalSpecialty !== '') {
                $schema['medicalSpecialty'] = $medicalSpecialty;
            }
        }

        if (isset(self::LOCAL_BUSINESS_TYPES[$schemaType])) {
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

            $currenciesAccepted = trim((string) ($this->settings['specific_currencies_accepted'] ?? ''));
            if ($currenciesAccepted !== '') {
                $schema['currenciesAccepted'] = $currenciesAccepted;
            }
        }

        if (in_array($schemaType, [...self::FOOD_TYPES, ...self::LODGING_TYPES, ...self::BEAUTY_FITNESS_TYPES, 'Store', 'TouristAttraction'], true)) {
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

        if (in_array($schemaType, ['Person', ...self::MEDICAL_TYPES, ...self::PRO_SERVICE_TYPES, ...self::FINANCE_TYPES, 'EducationalOrganization'], true)) {
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

        // ── Faza 2b — per-type Pro detail fields ──────────────────────

        // Accepting new patients (Medical) — top patient-facing question.
        if (in_array($schemaType, self::MEDICAL_TYPES, true)) {
            $accepting = trim((string) ($this->settings['specific_accepting_patients'] ?? ''));
            if (in_array($accepting, ['true', 'false'], true)) {
                $schema['isAcceptingNewPatients'] = $accepting === 'true';
            }
        }

        // Credentials / licences (Medical + Professional + Finance + Education).
        if (in_array($schemaType, [...self::MEDICAL_TYPES, ...self::PRO_SERVICE_TYPES, ...self::FINANCE_TYPES, 'EducationalOrganization'], true)) {
            $creds = $this->csvList((string) ($this->settings['specific_credentials'] ?? ''));
            if ($creds) {
                $schema['hasCredential'] = array_map(
                    static fn(string $c): array => ['@type' => 'EducationalOccupationalCredential', 'name' => $c],
                    $creds
                );
            }
        }

        // Languages spoken (service businesses where it matters).
        if (in_array($schemaType, [...self::LODGING_TYPES, ...self::MEDICAL_TYPES, ...self::PRO_SERVICE_TYPES, ...self::FINANCE_TYPES, ...self::BEAUTY_FITNESS_TYPES], true)) {
            $langs = $this->csvList((string) ($this->settings['specific_languages'] ?? ''));
            if ($langs) {
                $schema['knowsLanguage'] = $langs;
            }
        }

        // Suitable for diet (Food) — emit full schema.org RestrictedDiet URIs.
        if (in_array($schemaType, self::FOOD_TYPES, true)) {
            $diets = $this->mapDiets((string) ($this->settings['specific_diets'] ?? ''));
            if ($diets) {
                $schema['suitableForDiet'] = $diets;
            }
        }

        // Number of rooms (Lodging).
        if (in_array($schemaType, self::LODGING_TYPES, true)) {
            $rooms = (int) ($this->settings['specific_number_of_rooms'] ?? 0);
            if ($rooms > 0) {
                $schema['numberOfRooms'] = $rooms;
            }
        }

        // Merchant return policy (Store / local businesses) — the one property
        // with a real Google rich result. Google REQUIRES returnPolicyCountry,
        // so we emit nothing without a valid ISO-3166 country to avoid invalid
        // structured data.
        if ($schemaType === 'Store' || isset(self::LOCAL_BUSINESS_TYPES[$schemaType])) {
            $returnCat     = trim((string) ($this->settings['specific_return_category'] ?? ''));
            $returnCountry = strtoupper(trim((string) ($this->settings['specific_return_country'] ?? '')));
            $validCats     = ['MerchantReturnFiniteReturnWindow', 'MerchantReturnUnlimitedWindow', 'MerchantReturnNotPermitted'];
            if (in_array($returnCat, $validCats, true) && preg_match('/^[A-Z]{2}$/', $returnCountry)) {
                $policy = [
                    '@type'                => 'MerchantReturnPolicy',
                    'returnPolicyCategory' => 'https://schema.org/' . $returnCat,
                    'returnPolicyCountry'  => $returnCountry,
                ];
                if ($returnCat === 'MerchantReturnFiniteReturnWindow') {
                    $days = (int) ($this->settings['specific_return_days'] ?? 0);
                    if ($days > 0) {
                        $policy['merchantReturnDays'] = $days;
                    }
                }
                $schema['hasMerchantReturnPolicy'] = $policy;
            }
        }

        // ── Faza 2b (rest) — more per-type Pro detail fields ──────────

        // Universal business signals (not Person): employees, slogan.
        if ($schemaType !== 'Person') {
            $employees = (int) ($this->settings['specific_number_of_employees'] ?? 0);
            if ($employees > 0) {
                $schema['numberOfEmployees'] = ['@type' => 'QuantitativeValue', 'value' => $employees];
            }
            $slogan = trim((string) ($this->settings['specific_slogan'] ?? ''));
            if ($slogan !== '') {
                $schema['slogan'] = $slogan;
            }
        }

        // Awards — valid on any entity (business or Person).
        $awards = $this->csvList((string) ($this->settings['specific_award'] ?? ''));
        if ($awards) {
            $schema['award'] = $awards;
        }

        // Smoking allowed (Food + Lodging).
        if (in_array($schemaType, [...self::FOOD_TYPES, ...self::LODGING_TYPES], true)) {
            $smoking = trim((string) ($this->settings['specific_smoking_allowed'] ?? ''));
            if (in_array($smoking, ['true', 'false'], true)) {
                $schema['smokingAllowed'] = $smoking === 'true';
            }
        }

        // Drive-through service (Food + Pharmacy + Bank).
        if (in_array($schemaType, [...self::FOOD_TYPES, 'Pharmacy', 'BankOrCreditUnion'], true)) {
            $driveThrough = trim((string) ($this->settings['specific_drive_through'] ?? ''));
            if (in_array($driveThrough, ['true', 'false'], true)) {
                $schema['hasDriveThroughService'] = $driveThrough === 'true';
            }
        }

        // Free admission (TouristAttraction).
        if ($schemaType === 'TouristAttraction') {
            $accessibleFree = trim((string) ($this->settings['specific_accessible_free'] ?? ''));
            if (in_array($accessibleFree, ['true', 'false'], true)) {
                $schema['isAccessibleForFree'] = $accessibleFree === 'true';
            }
        }

        // Target audience (Lodging + TouristAttraction).
        if (in_array($schemaType, [...self::LODGING_TYPES, 'TouristAttraction'], true)) {
            $audience = trim((string) ($this->settings['specific_audience'] ?? ''));
            if ($audience !== '') {
                $schema['audience'] = ['@type' => 'Audience', 'audienceType' => $audience];
            }
        }

        // Brands serviced / sold (Automotive).
        if ($schemaType === 'AutomotiveBusiness') {
            $brands = $this->csvList((string) ($this->settings['specific_brand'] ?? ''));
            if ($brands) {
                $schema['brand'] = array_map(
                    static fn(string $b): array => ['@type' => 'Brand', 'name' => $b],
                    $brands
                );
            }
        }
    }

    /**
     * Map a comma-separated list of friendly diet names to schema.org
     * RestrictedDiet URIs. Unknown values are dropped.
     *
     * @return array<int, string>
     */
    private function mapDiets(string $value): array
    {
        static $map = [
            'diabetic'    => 'DiabeticDiet',
            'glutenfree'  => 'GlutenFreeDiet',
            'halal'       => 'HalalDiet',
            'hindu'       => 'HinduDiet',
            'kosher'      => 'KosherDiet',
            'lowcalorie'  => 'LowCalorieDiet',
            'lowfat'      => 'LowFatDiet',
            'lowlactose'  => 'LowLactoseDiet',
            'lowsalt'     => 'LowSaltDiet',
            'vegan'       => 'VeganDiet',
            'vegetarian'  => 'VegetarianDiet',
        ];

        $out = [];
        foreach ($this->csvList($value) as $raw) {
            $key = preg_replace('/[^a-z]/', '', strtolower($raw));
            if (isset($map[$key])) {
                $out[] = 'https://schema.org/' . $map[$key];
            }
        }

        return array_values(array_unique($out));
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
     * Date-specific opening-hours exceptions (holidays / seasonal hours) from the
     * schema_special_hours JSON repeater, emitted as specialOpeningHoursSpecification.
     * Each row: { label?, from (YYYY-MM-DD), to? (YYYY-MM-DD), closed (bool), opens, closes }.
     * A row with `closed` emits opens=closes=00:00 (the Schema.org "closed all day"
     * convention); otherwise opens/closes must be valid HH:MM. Missing `to` ⇒ single day.
     *
     * @return array<int, array<string, string>>
     */
    private function buildSpecialOpeningHours(): array
    {
        $raw = (string) ($this->settings['schema_special_hours'] ?? '');
        if ($raw === '') {
            return [];
        }

        $rows = json_decode($raw, true);
        if (!is_array($rows)) {
            return [];
        }

        $specs = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $from = trim((string) ($row['from'] ?? ''));
            if (!$this->isValidBusinessDate($from)) {
                continue;
            }

            $to = trim((string) ($row['to'] ?? ''));
            if (!$this->isValidBusinessDate($to)) {
                $to = $from;
            }

            $closedRaw = $row['closed'] ?? false;
            $closed    = $closedRaw === true || $closedRaw === 1 || $closedRaw === '1';
            if ($closed) {
                $opens  = '00:00';
                $closes = '00:00';
            } else {
                $opens  = trim((string) ($row['opens'] ?? ''));
                $closes = trim((string) ($row['closes'] ?? ''));
                if (!$this->isValidBusinessTime($opens) || !$this->isValidBusinessTime($closes)) {
                    continue;
                }
            }

            $specs[] = [
                '@type'        => 'OpeningHoursSpecification',
                'opens'        => $opens,
                'closes'       => $closes,
                'validFrom'    => $from,
                'validThrough' => $to,
            ];
        }

        return $specs;
    }

    private function isValidBusinessDate(string $date): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
    }

    /**
     * WebSite + SearchAction JSON-LD — homepage only.
     *
     * @return array<string, mixed>|null
     */
    private function buildWebSite(): ?array
    {
        // T1·S2: homepage gate reads the resolver's injected truth when present;
        // identical value to $ctx->isHomepage() (the active menu-home flag).
        $isHomepage = $this->pageContext !== null
            ? $this->pageContext->isHomepage
            : $this->ctx->isHomepage();
        if (!$isHomepage) {
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
            '@id'      => rtrim($baseUrl, '/') . '/#website',
            'name'     => $orgName !== '' ? $orgName : $this->ctx->getSiteName(),
            'url'      => $baseUrl . '/',
        ];

        // Link the site to its publishing organization (shared @id node) so
        // search engines and AI crawlers merge them into one entity graph.
        if ($orgName !== '') {
            $schema['publisher'] = ['@id' => $this->organizationId()];
        }

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

    /**
     * Parse the stored services JSON into a schema.org makesOffer array.
     * Input: JSON array of {name, price?, currency?} objects.
     * Output: a list of Offer nodes each wrapping a Service; price/priceCurrency
     * are emitted only when a price is present and the currency is a valid
     * ISO-4217 code. Invalid rows are skipped; capped at 50 entries.
     *
     * @return array<int, array<string,mixed>>
     */
    private function buildMakesOffer(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '' || $raw === '[]') {
            return [];
        }

        $rows = json_decode($raw, true);
        if (!is_array($rows)) {
            return [];
        }

        $offers = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $offer = [
                '@type'       => 'Offer',
                'itemOffered' => ['@type' => 'Service', 'name' => $name],
            ];

            $price = trim((string) ($row['price'] ?? ''));
            if ($price !== '') {
                $offer['price'] = $price;
                $currency = strtoupper(trim((string) ($row['currency'] ?? '')));
                if (preg_match('/^[A-Z]{3}$/', $currency)) {
                    $offer['priceCurrency'] = $currency;
                }
            }

            $offers[] = $offer;
            if (count($offers) >= 50) {
                break;
            }
        }

        return $offers;
    }

    /** Ensure a path or URL is absolute (prepend base URL for relative paths). */
    private function absoluteUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return $this->ctx->getBaseUrl() . '/' . ltrim($path, '/');
    }

    /**
     * Stable @id for the publishing Organization node, shared across the
     * Organization, WebSite, and Article publisher blocks so consumers merge
     * them into a single entity. Derived from the configured org URL, falling
     * back to the site base URL.
     */
    private function organizationId(): string
    {
        $orgUrl = trim((string) ($this->settings['org_url'] ?? ''));
        $base   = $orgUrl !== '' ? $orgUrl : $this->ctx->getBaseUrl();
        return rtrim($base, '/') . '/#organization';
    }
}
