<?php

declare(strict_types=1);

namespace AiBoost\Tests\Lib;

use AiBoost\Lib\Cms\AdapterRegistry;
use AiBoost\Lib\Manifest\Registry as ManifestRegistry;
use AiBoost\Lib\ProFeatureRegistry;
use AiBoost\Lib\PluginRegistry;
use AiBoost\Lib\SettingsSaveDefinition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SettingsSaveSchemaBusinessDetailsTest extends TestCase
{
    protected function setUp(): void
    {
        AdapterRegistry::reset();
        ManifestRegistry::reset();
        PluginRegistry::reset();
    }

    protected function tearDown(): void
    {
        AdapterRegistry::reset();
        ManifestRegistry::reset();
        PluginRegistry::reset();
    }

    #[DataProvider('businessDetails')]
    public function testActiveBusinessDetailsAreManifestBackedAndLocked(string $key, string $type): void
    {
        $field = SettingsSaveDefinition::field($key);
        $expected = [
            'source' => 'manifest',
            'tab' => 'schema',
            'section' => 'business_details',
            'type' => $type,
            'default' => '',
            'tier' => 'pro',
            'sku' => 'schema',
        ];
        $actual = array_intersect_key($field, $expected);
        ksort($expected);
        ksort($actual);

        $this->assertSame($expected, $actual);
        $this->assertContains($key, ProFeatureRegistry::lockedSettingsKeys());
    }

    /** @return array<string,array{key:string,type:string}> */
    public static function businessDetails(): array
    {
        return [
            'star rating' => ['key' => 'specific_star_rating', 'type' => 'select'],
            'check-in time' => ['key' => 'specific_checkin_time', 'type' => 'text'],
            'check-out time' => ['key' => 'specific_checkout_time', 'type' => 'text'],
            'pets allowed' => ['key' => 'specific_pets_allowed', 'type' => 'select'],
            'area served' => ['key' => 'specific_area_served', 'type' => 'text'],
        ];
    }
}