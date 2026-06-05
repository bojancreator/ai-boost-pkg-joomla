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

    /** @param array<string, mixed> $settings */
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
