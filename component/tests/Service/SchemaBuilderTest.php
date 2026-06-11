<?php

namespace AiBoost\Tests\Service;

use AiBoost\Lib\AppContextInterface;
use AiBoost\Plugin\System\AiBoostSchema\Service\SchemaBuilder;
use Joomla\Database\DatabaseInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Confirms SchemaBuilder can be instantiated with injected interfaces only.
 * No Joomla bootstrap, no Factory:: calls.
 */
final class SchemaBuilderTest extends TestCase
{
    public function testCanBeInstantiatedWithInjectedDependencies(): void
    {
        $ctx = $this->createMock(AppContextInterface::class);
        $db  = $this->createMock(DatabaseInterface::class);

        $service = new SchemaBuilder([], $ctx, $db);

        $this->assertInstanceOf(SchemaBuilder::class, $service);
    }

    public function testFreeBaselineWithOrgName(): void
    {
        $ctx = $this->createMock(AppContextInterface::class);
        $ctx->method('getBaseUrl')->willReturn('https://example.com');
        $ctx->method('isHomepage')->willReturn(false);
        $ctx->method('getPathway')->willReturn([]);
        $db  = $this->createMock(DatabaseInterface::class);

        $service = new SchemaBuilder(['org_name' => 'Test Org'], $ctx, $db);
        $blocks  = $service->buildAll();

        $this->assertNotEmpty($blocks);
        $this->assertSame('Organization', $blocks[0]['@type']);
        $this->assertSame('Test Org', $blocks[0]['name']);
    }

    /** @return iterable<string, array{0: string, 1: string}> */
    public static function schemaTypeProvider(): iterable
    {
        yield 'restaurant' => ['Restaurant', 'Restaurant'];
        yield 'hotel schema.org value' => ['LodgingBusiness', 'LodgingBusiness'];
        yield 'legacy hotel key' => ['hotel', 'LodgingBusiness'];
        yield 'automotive' => ['AutomotiveBusiness', 'AutomotiveBusiness'];
        yield 'tourist attraction' => ['TouristAttraction', 'TouristAttraction'];
        yield 'professional service' => ['ProfessionalService', 'ProfessionalService'];
        yield 'person' => ['Person', 'Person'];
        yield 'news media' => ['NewsMediaOrganization', 'NewsMediaOrganization'];
        yield 'unknown' => ['UnknownThing', 'Organization'];
        // ── Wide type set (Korak 3.2) ──
        yield 'cafe' => ['CafeOrCoffeeShop', 'CafeOrCoffeeShop'];
        yield 'bakery' => ['Bakery', 'Bakery'];
        yield 'bar or pub' => ['BarOrPub', 'BarOrPub'];
        yield 'physician' => ['Physician', 'Physician'];
        yield 'pharmacy' => ['Pharmacy', 'Pharmacy'];
        yield 'hospital' => ['Hospital', 'Hospital'];
        yield 'veterinary' => ['VeterinaryCare', 'VeterinaryCare'];
        yield 'bed and breakfast' => ['BedAndBreakfast', 'BedAndBreakfast'];
        yield 'resort' => ['Resort', 'Resort'];
        yield 'beauty salon' => ['BeautySalon', 'BeautySalon'];
        yield 'hair salon' => ['HairSalon', 'HairSalon'];
        yield 'day spa' => ['DaySpa', 'DaySpa'];
        yield 'health club' => ['HealthClub', 'HealthClub'];
        yield 'accounting' => ['AccountingService', 'AccountingService'];
        yield 'childcare' => ['ChildCare', 'ChildCare'];
        yield 'bank' => ['BankOrCreditUnion', 'BankOrCreditUnion'];
        yield 'financial service' => ['FinancialService', 'FinancialService'];
        yield 'insurance agency' => ['InsuranceAgency', 'InsuranceAgency'];
        // legacy aliases resolve to canonical @type
        yield 'cafe alias' => ['cafe', 'CafeOrCoffeeShop'];
        yield 'bank alias' => ['bank', 'BankOrCreditUnion'];
        yield 'vet alias' => ['vet', 'VeterinaryCare'];
    }

    #[DataProvider('schemaTypeProvider')]
    public function testSchemaTypeSettingNormalizesToSchemaOrgType(string $setting, string $expected): void
    {
        $blocks = $this->buildBlocks([
            'org_name' => 'Test Org',
            'schema_type' => $setting,
        ]);

        $this->assertSame($expected, $blocks[0]['@type']);
    }

    public function testRestaurantBusinessDetailsAreEmitted(): void
    {
        $blocks = $this->buildBlocks([
            'org_name' => 'Test Restaurant',
            'schema_type' => 'Restaurant',
            'specific_price_range' => '$$',
            'specific_serves_cuisine' => 'Mediterranean',
            'rating_value' => '4.8',
            'rating_count' => '120',
            'hours_mon_opens' => '10:00',
            'hours_mon_closes' => '22:00',
            'hours_tue_closed' => '1',
        ]);

        $restaurant = $blocks[0];
        $this->assertSame('Restaurant', $restaurant['@type']);
        $this->assertSame('$$', $restaurant['priceRange']);
        $this->assertSame('Mediterranean', $restaurant['servesCuisine']);
        $this->assertSame('4.8', $restaurant['aggregateRating']['ratingValue']);
        $this->assertSame('120', $restaurant['aggregateRating']['reviewCount']);
        $this->assertSame('https://schema.org/Monday', $restaurant['openingHoursSpecification'][0]['dayOfWeek']);
    }

    public function testAutomotiveServiceAndAreaServedAreEmitted(): void
    {
        $blocks = $this->buildBlocks([
            'org_name' => 'Test Garage',
            'schema_type' => 'AutomotiveBusiness',
            'specific_available_service' => 'Repairs and diagnostics',
            'specific_area_served' => 'Belgrade',
        ]);

        $business = $blocks[0];
        $this->assertSame('AutomotiveBusiness', $business['@type']);
        $this->assertSame('Repairs and diagnostics', $business['availableService']);
        $this->assertSame('Belgrade', $business['areaServed']);
    }

    public function testLodgingSpecificDetailsAreEmitted(): void
    {
        $blocks = $this->buildBlocks([
            'org_name' => 'Test Hotel',
            'schema_type' => 'LodgingBusiness',
            'specific_serves_cuisine' => 'Mediterranean',
            'specific_pets_allowed' => 'true',
            'specific_payment_accepted' => 'Cash, Credit Card',
            'specific_amenity_feature' => 'Parking, Pool',
        ]);

        $hotel = $blocks[0];
        $this->assertSame('LodgingBusiness', $hotel['@type']);
        $this->assertSame('Mediterranean', $hotel['servesCuisine']);
        $this->assertTrue($hotel['petsAllowed']);
        $this->assertSame('Cash, Credit Card', $hotel['paymentAccepted']);
        $this->assertSame('Parking', $hotel['amenityFeature'][0]['name']);
        $this->assertTrue($hotel['amenityFeature'][0]['value']);
    }

    public function testEducationServiceAreaAndExpertiseAreEmitted(): void
    {
        $blocks = $this->buildBlocks([
            'org_name' => 'Test Academy',
            'schema_type' => 'EducationalOrganization',
            'specific_available_service' => 'Language courses',
            'specific_area_served' => 'Online students',
            'specific_knows_about' => 'English, German, IELTS',
        ]);

        $school = $blocks[0];
        $this->assertSame('EducationalOrganization', $school['@type']);
        $this->assertSame('Language courses', $school['availableService']);
        $this->assertSame('Online students', $school['areaServed']);
        $this->assertSame(['English', 'German', 'IELTS'], $school['knowsAbout']);
    }

    public function testPersonProfileDetailsAreEmitted(): void
    {
        $blocks = $this->buildBlocks([
            'org_name' => 'Jane Doe',
            'schema_type' => 'Person',
            'specific_job_title' => 'Consultant',
            'specific_affiliation' => 'Example Studio',
            'specific_knows_about' => 'Joomla, SEO',
        ]);

        $person = $blocks[0];
        $this->assertSame('Person', $person['@type']);
        $this->assertSame('Consultant', $person['jobTitle']);
        $this->assertSame('Example Studio', $person['affiliation']['name']);
        $this->assertSame(['Joomla', 'SEO'], $person['knowsAbout']);
    }

    public function testNewsMediaDetailsAreEmitted(): void
    {
        $blocks = $this->buildBlocks([
            'org_name' => 'Test Newsroom',
            'schema_type' => 'NewsMediaOrganization',
            'specific_founding_date' => '2020-05-15',
            'specific_masthead_url' => '/masthead',
            'specific_ethics_policy_url' => '/editorial-policy',
        ]);

        $news = $blocks[0];
        $this->assertSame('NewsMediaOrganization', $news['@type']);
        $this->assertSame('2020-05-15', $news['foundingDate']);
        $this->assertSame('https://example.com/masthead', $news['masthead']);
        $this->assertSame('https://example.com/editorial-policy', $news['ethicsPolicy']);
    }

    public function testRestaurantMenuReservationsAndCurrenciesAreEmitted(): void
    {
        $restaurant = $this->buildBlocks([
            'org_name' => 'Test Bistro',
            'schema_type' => 'Restaurant',
            'specific_menu_url' => '/menu.pdf',
            'specific_accepts_reservations' => 'true',
            'specific_currencies_accepted' => 'EUR, RSD',
        ])[0];

        $this->assertSame('Restaurant', $restaurant['@type']);
        $this->assertSame('https://example.com/menu.pdf', $restaurant['hasMenu']);
        $this->assertTrue($restaurant['acceptsReservations']);
        $this->assertSame('EUR, RSD', $restaurant['currenciesAccepted']);
    }

    public function testReservationsFalseIsEmittedAsBoolean(): void
    {
        $cafe = $this->buildBlocks([
            'org_name' => 'No Booking Cafe',
            'schema_type' => 'FoodEstablishment',
            'specific_accepts_reservations' => 'false',
        ])[0];

        $this->assertFalse($cafe['acceptsReservations']);
    }

    public function testMenuAndReservationsNotEmittedForNonRestaurant(): void
    {
        $hotel = $this->buildBlocks([
            'org_name' => 'Test Hotel',
            'schema_type' => 'LodgingBusiness',
            'specific_menu_url' => '/menu',
            'specific_accepts_reservations' => 'true',
        ])[0];

        $this->assertArrayNotHasKey('hasMenu', $hotel);
        $this->assertArrayNotHasKey('acceptsReservations', $hotel);
    }

    public function testMedicalSpecialtyIsEmittedForClinicAndDentist(): void
    {
        $clinic = $this->buildBlocks([
            'org_name' => 'Test Clinic',
            'schema_type' => 'MedicalClinic',
            'specific_medical_specialty' => 'Cardiology',
        ])[0];
        $this->assertSame('MedicalClinic', $clinic['@type']);
        $this->assertSame('Cardiology', $clinic['medicalSpecialty']);

        $dentist = $this->buildBlocks([
            'org_name' => 'Test Dental',
            'schema_type' => 'Dentist',
            'specific_medical_specialty' => 'Orthodontics',
        ])[0];
        $this->assertSame('Dentist', $dentist['@type']);
        $this->assertSame('Orthodontics', $dentist['medicalSpecialty']);
    }

    public function testMedicalSpecialtyNotEmittedForNonMedicalType(): void
    {
        $store = $this->buildBlocks([
            'org_name' => 'Test Store',
            'schema_type' => 'Store',
            'specific_medical_specialty' => 'Cardiology',
        ])[0];

        $this->assertArrayNotHasKey('medicalSpecialty', $store);
    }

    public function testOrganizationCarriesStableIdAndWebsitePublisherReference(): void
    {
        $ctx = $this->createMock(AppContextInterface::class);
        $ctx->method('getBaseUrl')->willReturn('https://example.com');
        $ctx->method('isHomepage')->willReturn(true);
        $ctx->method('getSiteName')->willReturn('Example');
        $ctx->method('getPathway')->willReturn([]);
        $db = $this->createMock(DatabaseInterface::class);

        $blocks = (new SchemaBuilder([
            'org_name' => 'Test Org',
            'org_url'  => 'https://example.com',
        ], $ctx, $db))->buildAll();

        $website = null;
        $org     = null;
        foreach ($blocks as $b) {
            if (($b['@type'] ?? '') === 'WebSite')      { $website = $b; }
            if (($b['@type'] ?? '') === 'Organization') { $org = $b; }
        }

        $this->assertNotNull($org);
        $this->assertSame('https://example.com/#organization', $org['@id']);
        $this->assertNotNull($website);
        $this->assertSame('https://example.com/#website', $website['@id']);
        $this->assertSame('https://example.com/#organization', $website['publisher']['@id']);
    }

    /** @param array<string, mixed> $settings */
    public function testUniversalIdentityFieldsEmit(): void
    {
        $org = $this->buildBlocks([
            'org_name'          => 'Acme',
            'schema_type'       => 'Organization',
            'org_legal_name'    => 'Acme Incorporated',
            'org_vat_id'        => 'RS123456789',
            'org_founding_date' => '1998',
            'org_image'         => 'images/storefront.jpg',
            'org_map_url'       => 'https://maps.example/acme',
        ])[0];

        $this->assertSame('Organization', $org['@type']);
        $this->assertSame('Acme Incorporated', $org['legalName'] ?? null);
        $this->assertSame('RS123456789', $org['vatID'] ?? null);
        $this->assertSame('1998', $org['foundingDate'] ?? null);
        $this->assertStringContainsString('storefront.jpg', (string) ($org['image'] ?? ''));
        $this->assertSame('https://maps.example/acme', $org['hasMap'] ?? null);
    }

    public function testMakesOfferEmitsFromServicesJson(): void
    {
        $salon = $this->buildBlocks([
            'org_name'        => 'Glow Studio',
            'schema_type'     => 'BeautySalon',
            'schema_services' => json_encode([
                ['name' => 'Haircut',   'price' => '25', 'currency' => 'EUR'],
                ['name' => 'Manicure',  'price' => '',   'currency' => ''],    // no price
                ['name' => '',          'price' => '99', 'currency' => 'EUR'], // skipped — no name
                ['name' => 'Colouring', 'price' => '60', 'currency' => 'eur'], // lower-case → EUR
            ]),
        ])[0];

        $offers = $salon['makesOffer'] ?? [];
        $this->assertCount(3, $offers, 'rows without a name are skipped');

        // Row 1: full Offer → Service with price + currency.
        $this->assertSame('Offer', $offers[0]['@type']);
        $this->assertSame('Service', $offers[0]['itemOffered']['@type']);
        $this->assertSame('Haircut', $offers[0]['itemOffered']['name']);
        $this->assertSame('25', $offers[0]['price']);
        $this->assertSame('EUR', $offers[0]['priceCurrency']);

        // Row 2: name only — no price / priceCurrency keys.
        $this->assertSame('Manicure', $offers[1]['itemOffered']['name']);
        $this->assertArrayNotHasKey('price', $offers[1]);
        $this->assertArrayNotHasKey('priceCurrency', $offers[1]);

        // Row 4 (index 2): lower-case currency normalised to ISO upper-case.
        $this->assertSame('Colouring', $offers[2]['itemOffered']['name']);
        $this->assertSame('EUR', $offers[2]['priceCurrency']);
    }

    public function testMakesOfferAbsentForEmptyListAndNonServiceType(): void
    {
        // Empty list → no makesOffer key.
        $salon = $this->buildBlocks([
            'org_name' => 'X', 'schema_type' => 'BeautySalon', 'schema_services' => '[]',
        ])[0];
        $this->assertArrayNotHasKey('makesOffer', $salon);

        // Restaurant uses hasMenu, not makesOffer — type gate excludes it.
        $rest = $this->buildBlocks([
            'org_name' => 'X', 'schema_type' => 'Restaurant',
            'schema_services' => json_encode([['name' => 'Catering', 'price' => '100', 'currency' => 'EUR']]),
        ])[0];
        $this->assertArrayNotHasKey('makesOffer', $rest);
    }

    public function testFaza2bPerTypeFieldsEmit(): void
    {
        // Medical: isAcceptingNewPatients (bool) + credentials + languages.
        $clinic = $this->buildBlocks([
            'org_name' => 'X', 'schema_type' => 'MedicalClinic',
            'specific_accepting_patients' => 'true',
            'specific_credentials' => 'Board Certified, ISO 9001',
            'specific_languages' => 'English, Serbian',
        ])[0];
        $this->assertTrue($clinic['isAcceptingNewPatients'] ?? null);
        $this->assertSame('EducationalOccupationalCredential', $clinic['hasCredential'][0]['@type'] ?? null);
        $this->assertSame('Board Certified', $clinic['hasCredential'][0]['name'] ?? null);
        $this->assertSame(['English', 'Serbian'], $clinic['knowsLanguage'] ?? null);

        // Lodging: numberOfRooms (int).
        $hotel = $this->buildBlocks([
            'org_name' => 'X', 'schema_type' => 'LodgingBusiness',
            'specific_number_of_rooms' => '24',
        ])[0];
        $this->assertSame(24, $hotel['numberOfRooms'] ?? null);

        // Food: suitableForDiet → schema.org URIs; unknown values dropped.
        $rest = $this->buildBlocks([
            'org_name' => 'X', 'schema_type' => 'Restaurant',
            'specific_diets' => 'Vegan, Gluten-free, Halal, Bogus',
        ])[0];
        $this->assertSame([
            'https://schema.org/VeganDiet',
            'https://schema.org/GlutenFreeDiet',
            'https://schema.org/HalalDiet',
        ], $rest['suitableForDiet'] ?? null);

        // Store: merchant return policy (finite window + days + ISO country).
        $store = $this->buildBlocks([
            'org_name' => 'X', 'schema_type' => 'Store',
            'specific_return_category' => 'MerchantReturnFiniteReturnWindow',
            'specific_return_days' => '30',
            'specific_return_country' => 'rs',
        ])[0];
        $policy = $store['hasMerchantReturnPolicy'] ?? [];
        $this->assertSame('MerchantReturnPolicy', $policy['@type'] ?? null);
        $this->assertSame('https://schema.org/MerchantReturnFiniteReturnWindow', $policy['returnPolicyCategory'] ?? null);
        $this->assertSame('RS', $policy['returnPolicyCountry'] ?? null);
        $this->assertSame(30, $policy['merchantReturnDays'] ?? null);
    }

    public function testMerchantReturnPolicyOmittedWithoutCountry(): void
    {
        // Google requires returnPolicyCountry — without it, emit nothing.
        $store = $this->buildBlocks([
            'org_name' => 'X', 'schema_type' => 'Store',
            'specific_return_category' => 'MerchantReturnUnlimitedWindow',
            'specific_return_country' => '',
        ])[0];
        $this->assertArrayNotHasKey('hasMerchantReturnPolicy', $store);
    }

    public function testFaza2bRestFieldsEmit(): void
    {
        // Universal business signals: numberOfEmployees + slogan + award.
        $org = $this->buildBlocks([
            'org_name' => 'X', 'schema_type' => 'LocalBusiness',
            'specific_number_of_employees' => '25',
            'specific_slogan' => 'We deliver',
            'specific_award' => 'Best of 2024, Editor Choice',
        ])[0];
        $this->assertSame('QuantitativeValue', $org['numberOfEmployees']['@type'] ?? null);
        $this->assertSame(25, $org['numberOfEmployees']['value'] ?? null);
        $this->assertSame('We deliver', $org['slogan'] ?? null);
        $this->assertSame(['Best of 2024', 'Editor Choice'], $org['award'] ?? null);

        // Food: smokingAllowed + drive-through.
        $rest = $this->buildBlocks([
            'org_name' => 'X', 'schema_type' => 'Restaurant',
            'specific_smoking_allowed' => 'false', 'specific_drive_through' => 'true',
        ])[0];
        $this->assertFalse($rest['smokingAllowed'] ?? null);
        $this->assertTrue($rest['hasDriveThroughService'] ?? null);

        // TouristAttraction: free admission + audience.
        $attr = $this->buildBlocks([
            'org_name' => 'X', 'schema_type' => 'TouristAttraction',
            'specific_accessible_free' => 'true', 'specific_audience' => 'Families',
        ])[0];
        $this->assertTrue($attr['isAccessibleForFree'] ?? null);
        $this->assertSame('Audience', $attr['audience']['@type'] ?? null);
        $this->assertSame('Families', $attr['audience']['audienceType'] ?? null);

        // Automotive: brand list.
        $auto = $this->buildBlocks([
            'org_name' => 'X', 'schema_type' => 'AutomotiveBusiness',
            'specific_brand' => 'Toyota, BMW',
        ])[0];
        $this->assertSame('Brand', $auto['brand'][0]['@type'] ?? null);
        $this->assertSame('Toyota', $auto['brand'][0]['name'] ?? null);
        $this->assertSame('BMW', $auto['brand'][1]['name'] ?? null);

        // Person: slogan/employees suppressed; award still allowed.
        $person = $this->buildBlocks([
            'org_name' => 'X', 'schema_type' => 'Person',
            'specific_slogan' => 'nope', 'specific_number_of_employees' => '5',
            'specific_award' => 'Nobel Prize',
        ])[0];
        $this->assertArrayNotHasKey('slogan', $person);
        $this->assertArrayNotHasKey('numberOfEmployees', $person);
        $this->assertSame(['Nobel Prize'], $person['award'] ?? null);
    }

    public function testWidenedTypeGuardsEmitForNewTypes(): void
    {
        // Cafe / Bakery / Bar now inherit cuisine + menu + reservations (Korak 3.2 #1).
        $cafe = $this->buildBlocks([
            'org_name' => 'X', 'schema_type' => 'CafeOrCoffeeShop',
            'specific_serves_cuisine' => 'Coffee, Pastries', 'specific_menu_url' => '/menu',
            'specific_accepts_reservations' => 'true',
        ])[0];
        $this->assertSame('CafeOrCoffeeShop', $cafe['@type']);
        $this->assertSame('Coffee, Pastries', $cafe['servesCuisine'] ?? null);
        $this->assertStringContainsString('/menu', (string) ($cafe['hasMenu'] ?? ''));
        $this->assertTrue($cafe['acceptsReservations'] ?? null);

        // Physician / Hospital / Vet / Pharmacy now get medicalSpecialty + availableService.
        $phys = $this->buildBlocks([
            'org_name' => 'X', 'schema_type' => 'Physician',
            'specific_medical_specialty' => 'Cardiology', 'specific_available_service' => 'Check-ups',
        ])[0];
        $this->assertSame('Cardiology', $phys['medicalSpecialty'] ?? null);
        $this->assertSame('Check-ups', $phys['availableService'] ?? null);

        // Beauty/Fitness get availableService + amenityFeature.
        $salon = $this->buildBlocks([
            'org_name' => 'X', 'schema_type' => 'BeautySalon',
            'specific_available_service' => 'Haircut', 'specific_amenity_feature' => 'WiFi, Parking',
        ])[0];
        $this->assertSame('Haircut', $salon['availableService'] ?? null);
        $this->assertNotEmpty($salon['amenityFeature'] ?? []);

        // Finance / RealEstate get areaServed + knowsAbout + availableService.
        $bank = $this->buildBlocks([
            'org_name' => 'X', 'schema_type' => 'BankOrCreditUnion',
            'specific_area_served' => 'Belgrade', 'specific_knows_about' => 'Loans, Mortgages',
        ])[0];
        $this->assertSame('Belgrade', $bank['areaServed'] ?? null);
        $this->assertNotEmpty($bank['knowsAbout'] ?? []);

        // Lodging family (B&B, Resort) get star rating + check-in.
        $bnb = $this->buildBlocks([
            'org_name' => 'X', 'schema_type' => 'BedAndBreakfast',
            'specific_star_rating' => '4', 'specific_checkin_time' => '14:00',
        ])[0];
        $this->assertSame('4', $bnb['starRating']['ratingValue'] ?? null);
        $this->assertSame('14:00', $bnb['checkinTime'] ?? null);
    }

    private function buildBlocks(array $settings): array
    {
        $ctx = $this->createMock(AppContextInterface::class);
        $ctx->method('getBaseUrl')->willReturn('https://example.com');
        $ctx->method('isHomepage')->willReturn(false);
        $ctx->method('getPathway')->willReturn([]);
        $db = $this->createMock(DatabaseInterface::class);

        return (new SchemaBuilder($settings, $ctx, $db))->buildAll();
    }
}
