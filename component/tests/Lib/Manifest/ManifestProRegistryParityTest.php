<?php

namespace AiBoost\Tests\Lib\Manifest;

use AiBoost\Lib\ProFeatureRegistry;
use AiBoost\Lib\SettingsSaveDefinition;
use PHPUnit\Framework\TestCase;

/**
 * Parity test: every Pro-tier field in the manifest (`tier=pro`) must be
 * gated somewhere in ProFeatureRegistry — either as a direct `field` entry
 * in all() or inside a `section:*` group via sectionFields().
 *
 * Why: a Pro option declared in the manifest but missing from the registry
 * is silently editable on Free installs because stripLocked() never sees
 * its key. The codegen creates Vue partials with <ProGate gate-key="…">
 * for these keys, so the SPA looks correct, but the server save endpoint
 * accepts the value. This test fails before the bug reaches a build.
 *
 * The opposite direction (registry → manifest) is intentionally limited here:
 * ProFeatureRegistry also lists `section:*` entries and legacy single-key
 * registry rows that may not have a 1:1 manifest field yet. The section-fields
 * test below verifies concrete stripLocked() keys against manifest, save
 * compatibility, or a documented legacy allowlist.
 */
final class ManifestProRegistryParityTest extends TestCase
{
    private const MANIFEST_DIR = __DIR__ . '/../../../lib/src/Manifest';
    private const TABS = ['core', 'schema', 'aeo', 'og', 'hreflang', 'code'];

    /** @return list<array<string,mixed>> */
    private function loadAll(): array
    {
        $all = [];
        foreach (self::TABS as $tab) {
            $entries = require self::MANIFEST_DIR . '/' . $tab . '.php';
            if (is_array($entries)) {
                foreach ($entries as $e) {
                    if (is_array($e)) {
                        $all[] = $e;
                    }
                }
            }
        }
        return $all;
    }

    public function testEveryProManifestFieldIsGatedByRegistry(): void
    {
        $orphans = $this->proManifestFieldsMissingRegistryGating();

        sort($orphans);
        $this->assertSame(
            [],
            $orphans,
            "Pro manifest fields with no ProFeatureRegistry gating — stripLocked() "
            . "will silently accept them on Free installs:\n  - "
            . implode("\n  - ", $orphans)
        );
    }

    public function testEverySectionFieldsKeyIsAKnownManifestKey(): void
    {
        $unknown = $this->unknownSectionFieldKeys();

        sort($unknown);
        $this->assertSame(
            [],
            $unknown,
            "ProFeatureRegistry::sectionFields() lists keys that are not in any manifest, not accepted by "
            . "SettingsSaveDefinition, and not in the legacy allowlist. Either add the manifest field, "
            . "add the key to SettingsSaveDefinition, document the alias in the allowlist, or remove the "
            . "stale row:\n  - " . implode("\n  - ", $unknown)
        );
    }

    /** @return array<string,bool> */
    private function registryKeySet(): array
    {
        return array_flip(array_map(
            static fn(array $entry): string => (string) $entry['key'],
            ProFeatureRegistry::all()
        ));
    }

    /** @return array<string,string> */
    private function sectionFieldKeySet(): array
    {
        $sectionUnion = [];
        foreach (ProFeatureRegistry::sectionFields() as $sectionKey => $keys) {
            foreach ($keys as $key) {
                $sectionUnion[$key] = $sectionKey;
            }
        }
        return $sectionUnion;
    }

    /** @return list<string> */
    private function proManifestFieldsMissingRegistryGating(): array
    {
        $gatedKeys = $this->registryKeySet()
            + $this->sectionFieldKeySet();
        $orphans = [];

        foreach ($this->loadAll() as $field) {
            $key = $this->fieldKey($field);
            if ($this->isUngatedProField($field, $key, $gatedKeys)) {
                $orphans[] = $key . ' (sku=' . ($field['sku'] ?? '?') . ', tab=' . ($field['tab'] ?? '?') . ')';
            }
        }

        return $orphans;
    }

    /**
     * @param array<string,mixed> $field
     * @param array<string,mixed> $gatedKeys
     */
    private function isUngatedProField(array $field, string $key, array $gatedKeys): bool
    {
        return $key !== '' && $this->isProTier($field) && !isset($gatedKeys[$key]);
    }

    /** @param array<string,mixed> $field */
    private function fieldKey(array $field): string
    {
        return isset($field['key']) ? (string) $field['key'] : '';
    }

    /** @param array<string,mixed> $field */
    private function isProTier(array $field): bool
    {
        return isset($field['tier']) && $field['tier'] === 'pro';
    }

    /** @return list<string> */
    private function unknownSectionFieldKeys(): array
    {
        $manifestKeys = array_flip(array_map(
            static fn(array $field): string => (string) ($field['key'] ?? ''),
            $this->loadAll()
        ));
        $saveKeys = array_flip(SettingsSaveDefinition::acceptedKeys());
        $allow = array_flip($this->legacySectionFieldAllowlist());
        $unknown = [];

        foreach (ProFeatureRegistry::sectionFields() as $section => $keys) {
            foreach ($keys as $key) {
                if (!isset($manifestKeys[$key]) && !isset($saveKeys[$key]) && !isset($allow[$key])) {
                    $unknown[] = "$section → $key";
                }
            }
        }

        return $unknown;
    }

    /** @return list<string> */
    private function legacySectionFieldAllowlist(): array
    {
        return [
            'schema_event_article_ids',
            'schema_events_enabled',
            'schema_events_en',
            'sitemap_priority_articles',
            'sitemap_priority_categories',
            'sitemap_priority_menu',
            'priority_homepage',
            'priority_articles',
            'priority_categories',
            'priority_tags',
            'meta_pixel_standard_events',
            'meta_custom_events',
            'enable_sitemap_index',
            'enable_image_sitemap',
            'enable_news_sitemap',
            'news_category_id',
            'news_publication_name',
            'indexnow_api_key',
            'indexnow_auto_submit',
            'schema_howto',
            'llms_full_max_articles',
            'events_category_id',
        ];
    }
}
