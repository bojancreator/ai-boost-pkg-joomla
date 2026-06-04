<?php

declare(strict_types=1);

namespace AiBoost\Tests\Lib;

use AiBoost\Lib\Cms\AdapterRegistry;
use AiBoost\Lib\Manifest\Registry as ManifestRegistry;
use AiBoost\Lib\ProFeatureRegistry;
use AiBoost\Lib\PluginRegistry;
use AiBoost\Lib\SettingsSaveDefinition;
use AiBoost\Tests\Lib\Integration\Support\InMemoryEventDispatcher;
use PHPUnit\Framework\TestCase;

final class SettingsSaveDefinitionTest extends TestCase
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

    public function testAcceptedKeysPreserveCompatibilityFloor(): void
    {
        $accepted = SettingsSaveDefinition::acceptedKeys();

        $this->assertSame($accepted, array_values(array_unique($accepted)));
        $this->assertSame([], array_values(array_diff(SettingsSaveDefinition::legacyKeys(), $accepted)));

        foreach ([
            'license_key',
            'enable_schema',
            'schema_opening_hours',
            'enable_canonical',
            'meta_pixel_ids',
            'gsc_codes',
            'crawler_bot_rules',
            'hours_mon_opens',
            'hours_mo_opens',
            'hours_monday_opens',
            'custom_code_footer_menu_ids',
            'error_log_min_severity',
        ] as $key) {
            $this->assertContains($key, $accepted, "Missing compatibility key $key");
        }
    }

    public function testAdvancedOpeningHoursVariantsAreAcceptedAndProStripped(): void
    {
        $accepted = SettingsSaveDefinition::acceptedKeys();
        $locked = ProFeatureRegistry::lockedSettingsKeys();

        foreach (['mon', 'mo', 'monday'] as $day) {
            foreach (['opens', 'closes', 'closed'] as $suffix) {
                $key = 'hours_' . $day . '_' . $suffix;
                $this->assertContains($key, $accepted, "$key must be accepted for compatibility.");
                $this->assertContains($key, $locked, "$key must be stripped on Free saves.");
            }
        }
    }

    public function testFreeManifestFieldsCanExtendAcceptedKeys(): void
    {
        $accepted = SettingsSaveDefinition::acceptedKeys();
        $defaults = SettingsSaveDefinition::defaults();

        $this->assertContains('translation_source_priority', $accepted);
        $this->assertSame('joomla_native', $defaults['translation_source_priority'] ?? null);
        $this->assertSame('cooperative', $defaults['conflict_mode'] ?? null);
    }

    public function testGatedProManifestFieldsCanExtendAcceptedKeys(): void
    {
        $accepted = SettingsSaveDefinition::acceptedKeys();
        $locked = ProFeatureRegistry::lockedSettingsKeys();

        foreach (['schema_breadcrumb_pro', 'schema_howto_enabled', 'hreflang_enabled'] as $key) {
            $this->assertContains(
                $key,
                $accepted,
                "$key can be accepted because ProFeatureRegistry strips it on Free saves."
            );
            $this->assertContains(
                $key,
                $locked,
                "$key must stay locked for Free saves."
            );
        }
    }

    public function testRuntimeFreeIntegrationFieldsAreAcceptedWhenContributed(): void
    {
        $events = new InMemoryEventDispatcher();
        $events->on('onAiBoostRegisterFields', static fn(): array => [[
            'key'         => 'falang_hreflang_mode',
            'tab'         => 'sitemap',
            'section'     => 'hreflang',
            'label'       => 'Hreflang source mode',
            'type'        => 'select',
            'default'     => 'auto',
            'tier'        => 'free',
            'sku'         => 'core',
            'integration' => 'falang',
        ]]);
        AdapterRegistry::setEvents($events);
        ManifestRegistry::reset();

        $accepted = SettingsSaveDefinition::acceptedKeys();
        $field = SettingsSaveDefinition::field('falang_hreflang_mode');

        $this->assertContains('falang_hreflang_mode', $accepted);
        $this->assertSame('auto', $field['default'] ?? null);
        $this->assertSame('manifest', $field['source'] ?? null);
    }

    public function testAcceptedKeysExposeManifestAndCompatibilitySources(): void
    {
        $byKey = $this->settingsFieldsByKey();

        $this->assertSame(SettingsSaveDefinition::acceptedKeys(), array_keys($byKey));
        $this->assertOnlyKnownFieldSources($byKey);
        $this->assertFieldSources($byKey, [
            'translation_source_priority' => 'manifest',
            'schema_breadcrumb_pro'       => 'manifest',
            'license_key'                 => 'compatibility',
            'hours_monday_opens'          => 'compatibility',
            'custom_code_scope'           => 'compatibility',
        ]);
    }

    public function testCustomCodePerFieldScopeKeysAreManifestBacked(): void
    {
        $defaults = SettingsSaveDefinition::defaults();

        foreach (['head', 'body', 'footer'] as $position) {
            $scopeKey = 'custom_code_' . $position . '_scope';
            $menuIdsKey = 'custom_code_' . $position . '_menu_ids';

            $this->assertCustomCodeManifestField($scopeKey, $position, 'select', 'all');
            $this->assertCustomCodeManifestField($menuIdsKey, $position, 'json', '[]');
            $this->assertSame(
                [$scopeKey => 'all', $menuIdsKey => '[]'],
                array_intersect_key($defaults, array_flip([$scopeKey, $menuIdsKey]))
            );
        }

        foreach (['custom_code_scope', 'custom_code_menu_ids'] as $key) {
            $this->assertSame('compatibility', SettingsSaveDefinition::field($key)['source'] ?? null);
        }
    }

    public function testAeoLlmsActiveKeysAreManifestBacked(): void
    {
        $this->assertAeoManifestField('llmstxt_recent_articles', 'llmstxt', 'number', '5', 'free');
        $this->assertAeoManifestField('llmstxt_custom_pages', 'llmstxt', 'json', '[]', 'free');
        $this->assertAeoManifestField('llmstxt_faq_items', 'llmstxt', 'json', '[]', 'free');
        $this->assertAeoManifestField('llms_full_max_articles', 'llms_full', 'number', '500', 'pro');

        $this->assertSame(
            'compatibility',
            SettingsSaveDefinition::field('llmstxt_custom_pages_en')['source'] ?? null
        );
    }

    public function testAeoRobotsActiveKeysAreManifestBacked(): void
    {
        $this->assertAeoManifestField('robots_custom_scrapers', 'robots', 'textarea', '', 'free');
        $this->assertAeoManifestField('robots_custom_rules', 'robots', 'textarea', '', 'free');

        foreach ($this->seoScraperKeys() as $key) {
            $this->assertAeoManifestField($key, 'robots_scrapers', 'toggle', '0', 'free');
        }

        $this->assertSame('compatibility', SettingsSaveDefinition::field('robots_block_scrapers')['source'] ?? null);
    }

    public function testAeoIndexNowPayloadKeysAreManifestBackedAndLocked(): void
    {
        $this->assertAeoManifestField('indexnow_api_key', 'indexnow', 'text', '', 'pro');
        $this->assertAeoManifestField('indexnow_auto_submit', 'indexnow', 'toggle', '0', 'pro');

        foreach (['indexnow_enabled', 'indexnow_api_key', 'indexnow_auto_submit'] as $key) {
            $this->assertContains($key, ProFeatureRegistry::lockedSettingsKeys());
        }
    }

    public function testCoreGeneralAndDebugActiveKeysAreManifestBacked(): void
    {
        foreach ($this->coreGeneralManifestExpectations() as $key => $expected) {
            $this->assertCoreManifestField($key, $expected['tab'], $expected['section'], $expected['type'], $expected['default']);
        }

        foreach ($this->coreDebugManifestExpectations() as $key => $expected) {
            $this->assertManifestAttributes($key, [
                'source'  => 'manifest',
                'tab'     => 'debug',
                'section' => $expected['section'],
                'type'    => $expected['type'],
                'default' => $expected['default'],
                'tier'    => 'pro',
                'sku'     => 'core',
            ]);
            $this->assertContains($key, ProFeatureRegistry::lockedSettingsKeys());
        }

        foreach (['show_advanced_options', 'dev_license_preview', 'dev_force_free_tier'] as $key) {
            $this->assertSame('compatibility', SettingsSaveDefinition::field($key)['source'] ?? null);
        }
    }

    public function testMetaPixelSaveOnlyKeysAreManifestBacked(): void
    {
        $defaults = SettingsSaveDefinition::defaults();

        foreach (['meta_pixel_id', 'meta_pixel_ids', 'pixel_consent_mode', 'meta_custom_events'] as $key) {
            $this->assertManifestAttributes($key, [
                'source' => 'manifest',
                'tab'    => 'social',
                'tier'   => 'pro',
                'sku'    => 'og',
            ]);
        }

        $this->assertSame(
            ['meta_pixel_id' => '', 'meta_pixel_ids' => '[""]', 'pixel_consent_mode' => 'none'],
            array_intersect_key($defaults, array_flip(['meta_pixel_id', 'meta_pixel_ids', 'pixel_consent_mode']))
        );
    }

    public function testSocialActiveKeysAreManifestBacked(): void
    {
        $this->assertSocialManifestField('og_image_width', 'og', 'number', '1200', 'free');
        $this->assertSocialManifestField('og_image_height', 'og', 'number', '630', 'free');
        $this->assertSocialManifestField('enable_per_article_fields', 'og', 'toggle', '1', 'free');
        $this->assertSocialManifestField('enable_article_og_type', 'og', 'toggle', '1', 'free');
        $this->assertSocialManifestField('fb_app_id', 'facebook', 'text', '', 'free');
        $this->assertSocialManifestField('twitter_site_handle', 'twitter', 'text', '', 'free');

        foreach (['og_site_name', 'og_default_image'] as $key) {
            $this->assertSame('compatibility', SettingsSaveDefinition::field($key)['source'] ?? null);
        }
    }

    public function testSitemapFreeActiveKeysAreManifestBacked(): void
    {
        foreach ($this->sitemapFreeManifestExpectations() as $key => $expected) {
            $this->assertCoreManifestField(
                $key,
                $expected['tab'],
                $expected['section'],
                $expected['type'],
                $expected['default']
            );
        }

        foreach (['include_tags', 'ping_on_publish', 'enable_sitemap_index', 'enable_news_sitemap'] as $key) {
            $this->assertSame('compatibility', SettingsSaveDefinition::field($key)['source'] ?? null);
        }
    }

    public function testSchemaOrgActiveKeysAreManifestBacked(): void
    {
        foreach ($this->schemaFreeManifestExpectations() as $key => $expected) {
            $this->assertSchemaManifestField($key, $expected['tab'], $expected['section'], $expected['type'], $expected['default'], 'free');
        }

        foreach (['org_name_en', 'org_description_en', 'schema_logo_url', 'schema_rating_value'] as $key) {
            $this->assertSame('compatibility', SettingsSaveDefinition::field($key)['source'] ?? null);
        }
    }

    public function testSchemaProSectionPayloadKeysAreManifestBacked(): void
    {
        foreach ($this->schemaProSectionExpectations() as $key => $expected) {
            $this->assertSchemaManifestField($key, 'schema', $expected['section'], $expected['type'], $expected['default'], 'pro');
            $this->assertContains($key, ProFeatureRegistry::lockedSettingsKeys());
        }

        foreach ($this->activeOpeningHoursExpectations() as $key => $expected) {
            $this->assertSchemaManifestField($key, 'schema', 'hours_advanced', $expected['type'], $expected['default'], 'pro');
            $this->assertContains($key, ProFeatureRegistry::lockedSettingsKeys());
        }

        foreach (['hours_monday_opens', 'hours_mo_opens', 'schema_events_enabled', 'schema_events_en'] as $key) {
            $this->assertSame('compatibility', SettingsSaveDefinition::field($key)['source'] ?? null);
        }
    }

    public function testGscSaveOnlyKeysRemainCompatibilityUntilAnalyticsOwnershipReview(): void
    {
        $manifestKeys = SettingsSaveDefinition::manifestKeys();

        foreach (['gsc_verification_code', 'gsc_codes'] as $key) {
            $this->assertContains($key, SettingsSaveDefinition::acceptedKeys());
            $this->assertNotContains($key, $manifestKeys);
            $this->assertSame('compatibility', SettingsSaveDefinition::field($key)['source'] ?? null);
        }
    }

    public function testImportControllerBoundaryIsSeparateFromSettingsSaveDefinition(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/com_aiboost/admin/src/Administrator/Controller/ImportController.php'
        );

        $this->assertIsString($source);
        $this->assertStringContainsString('private const IMPORT_DENYLIST', $source);
        $this->assertStringContainsString("'license_state'", $source);
        $this->assertStringContainsString("'dev_force_free_tier'", $source);
        $this->assertStringNotContainsString(
            'SettingsSaveDefinition',
            $source,
            'Import remains a separate persistence boundary and needs its own XHigh review before reuse.'
        );
    }

    /** @return array<string,array<string,mixed>> */
    private function settingsFieldsByKey(): array
    {
        $byKey = [];
        foreach (SettingsSaveDefinition::fields() as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key !== '') {
                $byKey[$key] = $field;
            }
        }
        return $byKey;
    }

    /** @param array<string,array<string,mixed>> $fields */
    private function assertOnlyKnownFieldSources(array $fields): void
    {
        $sources = array_unique(array_column($fields, 'source'));

        $this->assertSame([], array_values(array_diff($sources, ['manifest', 'compatibility'])));
    }

    /**
     * @param array<string,array<string,mixed>> $fields
     * @param array<string,string>              $expectedSources
     */
    private function assertFieldSources(array $fields, array $expectedSources): void
    {
        foreach ($expectedSources as $key => $source) {
            $this->assertSame($source, $fields[$key]['source'] ?? null);
        }
    }

    private function assertCustomCodeManifestField(
        string $key,
        string $section,
        string $type,
        string $default
    ): void {
        $this->assertManifestAttributes($key, [
            'source'  => 'manifest',
            'tab'     => 'code',
            'section' => $section,
            'type'    => $type,
            'default' => $default,
            'tier'    => 'pro',
            'sku'     => 'code',
        ]);
    }

    private function assertAeoManifestField(
        string $key,
        string $section,
        string $type,
        string $default,
        string $tier
    ): void {
        $this->assertManifestAttributes($key, [
            'source'  => 'manifest',
            'tab'     => 'aeo',
            'section' => $section,
            'type'    => $type,
            'default' => $default,
            'tier'    => $tier,
            'sku'     => 'aeo',
        ]);
    }

    private function assertSocialManifestField(
        string $key,
        string $section,
        string $type,
        string $default,
        string $tier
    ): void {
        $this->assertManifestAttributes($key, [
            'source'  => 'manifest',
            'tab'     => 'social',
            'section' => $section,
            'type'    => $type,
            'default' => $default,
            'tier'    => $tier,
            'sku'     => 'og',
        ]);
    }

    private function assertCoreManifestField(
        string $key,
        string $tab,
        string $section,
        string $type,
        string $default
    ): void {
        $this->assertManifestAttributes($key, [
            'source'  => 'manifest',
            'tab'     => $tab,
            'section' => $section,
            'type'    => $type,
            'default' => $default,
            'tier'    => 'free',
            'sku'     => 'core',
        ]);
    }

    private function assertSchemaManifestField(
        string $key,
        string $tab,
        string $section,
        string $type,
        string $default,
        string $tier
    ): void {
        $this->assertManifestAttributes($key, [
            'source'  => 'manifest',
            'tab'     => $tab,
            'section' => $section,
            'type'    => $type,
            'default' => $default,
            'tier'    => $tier,
            'sku'     => 'schema',
        ]);
    }

    /** @param array<string,string> $expected */
    private function assertManifestAttributes(string $key, array $expected): void
    {
        $field = SettingsSaveDefinition::field($key);
        $actual = array_intersect_key($field, $expected);

        $this->assertIsArray($field);
        ksort($expected);
        ksort($actual);
        $this->assertSame($expected, $actual);
    }

    /** @return array<string,array{tab:string,section:string,type:string,default:string}> */
    private function sitemapFreeManifestExpectations(): array
    {
        return [
            'enable_sitemap' => ['tab' => 'sitemap', 'section' => 'xml', 'type' => 'toggle', 'default' => '1'],
            'include_articles' => ['tab' => 'sitemap', 'section' => 'xml_content', 'type' => 'toggle', 'default' => '1'],
            'include_categories' => ['tab' => 'sitemap', 'section' => 'xml_content', 'type' => 'toggle', 'default' => '1'],
            'include_menu_items' => ['tab' => 'sitemap', 'section' => 'xml_content', 'type' => 'toggle', 'default' => '1'],
            'sitemap_limit' => ['tab' => 'sitemap', 'section' => 'xml', 'type' => 'number', 'default' => '1000'],
            'default_changefreq' => ['tab' => 'sitemap', 'section' => 'xml', 'type' => 'select', 'default' => 'weekly'],
            'default_priority' => ['tab' => 'sitemap', 'section' => 'xml', 'type' => 'number', 'default' => '0.8'],
            'exclude_category_ids' => ['tab' => 'sitemap', 'section' => 'xml_exclusions', 'type' => 'text', 'default' => ''],
            'exclude_menu_ids' => ['tab' => 'sitemap', 'section' => 'xml_exclusions', 'type' => 'text', 'default' => ''],
            'ping_google' => ['tab' => 'sitemap', 'section' => 'ping_legacy', 'type' => 'toggle', 'default' => '1'],
            'ping_bing' => ['tab' => 'sitemap', 'section' => 'ping_legacy', 'type' => 'toggle', 'default' => '1'],
            'redirect_404_log_enabled' => ['tab' => 'sitemap', 'section' => 'redirects', 'type' => 'toggle', 'default' => '1'],
            'enable_canonical' => ['tab' => 'sitemap', 'section' => 'canonical', 'type' => 'toggle', 'default' => '1'],
            'canonical_url_map' => ['tab' => 'sitemap', 'section' => 'canonical', 'type' => 'textarea', 'default' => ''],
        ];
    }

    /** @return array<string,array{tab:string,section:string,type:string,default:string}> */
    private function coreGeneralManifestExpectations(): array
    {
        return [
            'auto_domain_detection' => ['tab' => 'general', 'section' => 'domain', 'type' => 'toggle', 'default' => '1'],
            'manual_domain' => ['tab' => 'general', 'section' => 'domain', 'type' => 'url', 'default' => ''],
            'enable_robots' => ['tab' => 'general', 'section' => 'robots', 'type' => 'toggle', 'default' => '1'],
            'robots_auto_sync' => ['tab' => 'general', 'section' => 'robots', 'type' => 'toggle', 'default' => '0'],
        ];
    }

    /** @return array<string,array{section:string,type:string,default:string}> */
    private function coreDebugManifestExpectations(): array
    {
        return [
            'debug_mode' => ['section' => 'diagnostics', 'type' => 'toggle', 'default' => '0'],
            'hide_comments' => ['section' => 'diagnostics', 'type' => 'toggle', 'default' => '0'],
            'staging_mode' => ['section' => 'diagnostics', 'type' => 'toggle', 'default' => '0'],
        ];
    }

    /** @return array<string,array{tab:string,section:string,type:string,default:string}> */
    private function schemaFreeManifestExpectations(): array
    {
        return [
            'specific_price_range' => ['tab' => 'schema', 'section' => 'business', 'type' => 'select', 'default' => ''],
            'specific_serves_cuisine' => ['tab' => 'schema', 'section' => 'business', 'type' => 'text', 'default' => ''],
            'specific_available_service' => ['tab' => 'schema', 'section' => 'business', 'type' => 'text', 'default' => ''],
            'org_name' => ['tab' => 'org', 'section' => 'identity', 'type' => 'text', 'default' => ''],
            'org_description' => ['tab' => 'org', 'section' => 'identity', 'type' => 'textarea', 'default' => ''],
            'org_logo' => ['tab' => 'org', 'section' => 'identity', 'type' => 'media', 'default' => ''],
            'org_url' => ['tab' => 'org', 'section' => 'contact', 'type' => 'text', 'default' => ''],
            'org_email' => ['tab' => 'org', 'section' => 'contact', 'type' => 'text', 'default' => ''],
            'org_phone' => ['tab' => 'org', 'section' => 'contact', 'type' => 'text', 'default' => ''],
            'social_facebook' => ['tab' => 'org', 'section' => 'social', 'type' => 'text', 'default' => ''],
            'social_instagram' => ['tab' => 'org', 'section' => 'social', 'type' => 'text', 'default' => ''],
            'social_youtube' => ['tab' => 'org', 'section' => 'social', 'type' => 'text', 'default' => ''],
            'social_twitter' => ['tab' => 'org', 'section' => 'social', 'type' => 'text', 'default' => ''],
            'social_linkedin' => ['tab' => 'org', 'section' => 'social', 'type' => 'text', 'default' => ''],
            'social_tiktok' => ['tab' => 'org', 'section' => 'social', 'type' => 'text', 'default' => ''],
            'org_address_street' => ['tab' => 'org', 'section' => 'address', 'type' => 'text', 'default' => ''],
            'org_address_city' => ['tab' => 'org', 'section' => 'address', 'type' => 'text', 'default' => ''],
            'org_address_state' => ['tab' => 'org', 'section' => 'address', 'type' => 'text', 'default' => ''],
            'org_address_zip' => ['tab' => 'org', 'section' => 'address', 'type' => 'text', 'default' => ''],
            'org_address_country' => ['tab' => 'org', 'section' => 'address', 'type' => 'text', 'default' => ''],
            'org_latitude' => ['tab' => 'org', 'section' => 'geo', 'type' => 'text', 'default' => ''],
            'org_longitude' => ['tab' => 'org', 'section' => 'geo', 'type' => 'text', 'default' => ''],
            'rating_value' => ['tab' => 'org', 'section' => 'rating', 'type' => 'text', 'default' => ''],
            'rating_count' => ['tab' => 'org', 'section' => 'rating', 'type' => 'number', 'default' => ''],
            'rating_best' => ['tab' => 'org', 'section' => 'rating', 'type' => 'number', 'default' => ''],
            'rating_worst' => ['tab' => 'org', 'section' => 'rating', 'type' => 'number', 'default' => ''],
            'rating_source' => ['tab' => 'org', 'section' => 'rating', 'type' => 'text', 'default' => ''],
            'schema_opening_hours' => ['tab' => 'schema', 'section' => 'hours', 'type' => 'text', 'default' => ''],
            'enable_search_action' => ['tab' => 'schema', 'section' => 'website', 'type' => 'toggle', 'default' => '1'],
        ];
    }

    /** @return array<string,array{section:string,type:string,default:string}> */
    private function schemaProSectionExpectations(): array
    {
        return [
            'schema_hours_temp_closed' => ['section' => 'hours_advanced', 'type' => 'toggle', 'default' => '0'],
            'schema_holiday_closed' => ['section' => 'hours_advanced', 'type' => 'textarea', 'default' => ''],
            'manual_faq_scope' => ['section' => 'faq', 'type' => 'select', 'default' => 'fallback_all'],
            'faq_items' => ['section' => 'faq', 'type' => 'json', 'default' => '[]'],
            'schema_faq_output_type' => ['section' => 'faq', 'type' => 'select', 'default' => 'faqpage'],
            'schema_howto' => ['section' => 'howto', 'type' => 'json', 'default' => ''],
            'events_category_id' => ['section' => 'event', 'type' => 'number', 'default' => '0'],
        ];
    }

    /** @return array<string,array{type:string,default:string}> */
    private function activeOpeningHoursExpectations(): array
    {
        $expectations = [];
        foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $day) {
            $expectations['hours_' . $day . '_opens'] = ['type' => 'text', 'default' => '09:00'];
            $expectations['hours_' . $day . '_closes'] = ['type' => 'text', 'default' => '17:00'];
            $expectations['hours_' . $day . '_closed'] = ['type' => 'toggle', 'default' => '0'];
        }

        return $expectations;
    }

    /** @return array<int,string> */
    private function seoScraperKeys(): array
    {
        return [
            'scraper_ahrefsbot',
            'scraper_semrushbot',
            'scraper_dotbot',
            'scraper_mj12bot',
            'scraper_blexbot',
            'scraper_rogerbot',
            'scraper_screamingfrog',
            'scraper_sitebulb',
            'scraper_siteauditor',
            'scraper_serpstatbot',
            'scraper_bytespider',
            'scraper_petalbot',
        ];
    }
}