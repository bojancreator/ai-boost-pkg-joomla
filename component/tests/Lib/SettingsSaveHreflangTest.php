<?php

declare(strict_types=1);

namespace AiBoost\Tests\Lib;

use AiBoost\Lib\Cms\AdapterRegistry;
use AiBoost\Lib\Manifest\Registry as ManifestRegistry;
use AiBoost\Lib\PluginRegistry;
use AiBoost\Lib\ProFeatureRegistry;
use AiBoost\Lib\SettingsSaveDefinition;
use PHPUnit\Framework\TestCase;

final class SettingsSaveHreflangTest extends TestCase
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

    public function testActiveSitemapHreflangToggleIsManifestBackedAndLocked(): void
    {
        $field = SettingsSaveDefinition::field('enable_hreflang');
        $expected = [
            'source' => 'manifest',
            'tab' => 'sitemap',
            'section' => 'hreflang',
            'type' => 'toggle',
            'default' => '0',
            'tier' => 'pro',
            'sku' => 'hreflang',
        ];
        $actual = array_intersect_key($field, $expected);
        ksort($expected);
        ksort($actual);

        $this->assertSame($expected, $actual);
    }
}