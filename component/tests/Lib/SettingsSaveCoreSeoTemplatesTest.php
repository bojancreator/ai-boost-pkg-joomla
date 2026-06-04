<?php

declare(strict_types=1);

namespace AiBoost\Tests\Lib;

use AiBoost\Lib\Cms\AdapterRegistry;
use AiBoost\Lib\Manifest\Registry as ManifestRegistry;
use AiBoost\Lib\PluginRegistry;
use AiBoost\Lib\SettingsSaveDefinition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SettingsSaveCoreSeoTemplatesTest extends TestCase
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

    #[DataProvider('seoTemplates')]
    public function testCoreSeoTemplateKeysAreManifestBacked(string $key, string $type, string $default): void
    {
        $field = SettingsSaveDefinition::field($key);
        $expected = [
            'source' => 'manifest',
            'tab' => 'general',
            'section' => 'seo_templates',
            'type' => $type,
            'default' => $default,
            'tier' => 'free',
            'sku' => 'core',
        ];
        $actual = array_intersect_key($field, $expected);
        ksort($expected);
        ksort($actual);

        $this->assertSame($expected, $actual);
    }

    /** @return array<string,array{key:string,type:string,default:string}> */
    public static function seoTemplates(): array
    {
        return [
            'global title' => ['key' => 'title_template', 'type' => 'text', 'default' => ''],
            'title separator' => ['key' => 'title_separator', 'type' => 'text', 'default' => ' | '],
            'home title' => ['key' => 'title_template_home', 'type' => 'text', 'default' => ''],
            'article title' => ['key' => 'title_template_article', 'type' => 'text', 'default' => ''],
            'category title' => ['key' => 'title_template_category', 'type' => 'text', 'default' => ''],
            'search title' => ['key' => 'title_template_search', 'type' => 'text', 'default' => ''],
            'tag title' => ['key' => 'title_template_tag', 'type' => 'text', 'default' => ''],
            'default title' => ['key' => 'title_template_default', 'type' => 'text', 'default' => ''],
            'title max length' => ['key' => 'title_template_maxlen', 'type' => 'number', 'default' => '0'],
            'global meta description' => ['key' => 'meta_desc_template', 'type' => 'textarea', 'default' => ''],
            'article meta description' => ['key' => 'meta_desc_template_article', 'type' => 'textarea', 'default' => ''],
            'category meta description' => ['key' => 'meta_desc_template_category', 'type' => 'textarea', 'default' => ''],
            'default meta description' => ['key' => 'meta_desc_template_default', 'type' => 'textarea', 'default' => ''],
            'meta description max length' => ['key' => 'meta_desc_maxlen', 'type' => 'number', 'default' => '160'],
        ];
    }
}