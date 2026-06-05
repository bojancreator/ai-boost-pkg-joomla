<?php

namespace AiBoost\Tests\Service;

use AiBoost\Plugin\System\AiBoostSchemaPro\Service\SiteTypePresetService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/plugins/system/aiboost_schema_pro/src/Service/SiteTypePresetService.php';

final class SiteTypePresetServiceTest extends TestCase
{
    /** @return iterable<string, array{0: string, 1: string}> */
    public static function schemaTypeProvider(): iterable
    {
        yield 'Organization' => ['Organization', 'Organization'];
        yield 'LocalBusiness' => ['LocalBusiness', 'LocalBusiness'];
        yield 'FoodEstablishment' => ['FoodEstablishment', 'FoodEstablishment'];
        yield 'Restaurant' => ['Restaurant', 'Restaurant'];
        yield 'EducationalOrganization' => ['EducationalOrganization', 'EducationalOrganization'];
        yield 'LodgingBusiness' => ['LodgingBusiness', 'LodgingBusiness'];
        yield 'MedicalClinic' => ['MedicalClinic', 'MedicalClinic'];
        yield 'LegalService' => ['LegalService', 'LegalService'];
        yield 'SportsActivityLocation' => ['SportsActivityLocation', 'SportsActivityLocation'];
        yield 'Dentist' => ['Dentist', 'Dentist'];
        yield 'RealEstateAgent' => ['RealEstateAgent', 'RealEstateAgent'];
        yield 'AutomotiveBusiness' => ['AutomotiveBusiness', 'AutomotiveBusiness'];
        yield 'Store' => ['Store', 'Store'];
        yield 'TouristAttraction' => ['TouristAttraction', 'TouristAttraction'];
        yield 'ProfessionalService' => ['ProfessionalService', 'ProfessionalService'];
        yield 'Person' => ['Person', 'Person'];
        yield 'NewsMediaOrganization' => ['NewsMediaOrganization', 'NewsMediaOrganization'];
        yield 'legacy hotel' => ['hotel', 'LodgingBusiness'];
        yield 'legacy school' => ['school', 'EducationalOrganization'];
        yield 'legacy gym' => ['gym', 'SportsActivityLocation'];
    }

    #[DataProvider('schemaTypeProvider')]
    public function testSchemaTypeValuesResolveToSchemaOrgTypes(string $value, string $expected): void
    {
        $this->assertSame($expected, SiteTypePresetService::getSchemaType($value, true));
    }

    public function testUnknownTypeFallsBackToOrganization(): void
    {
        $this->assertSame('Organization', SiteTypePresetService::getSchemaType('UnknownThing', true));
    }
}
