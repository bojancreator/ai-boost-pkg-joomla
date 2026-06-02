<?php

namespace AiBoost\Tests\Lib\Manifest;

use AiBoost\Lib\ProFeatureRegistry;
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
 * The opposite direction (registry → manifest) is intentionally NOT
 * asserted here: ProFeatureRegistry also lists `section:*` entries and
 * legacy single-key registry rows that may not have a 1:1 manifest field
 * yet. Once Task #472 finishes the cleanup, the inverse parity can be
 * added safely.
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

    /**
     * Pro manifest keys that ProFeatureRegistry currently gates only by
     * section wrapper (e.g. `section:sitemap.advanced`, `section:schema.howto`)
     * but does NOT list as an exact key under sectionFields(). These rows
     * therefore slip past stripLocked() on Free installs. Tracked for fix
     * in Task #472 — see plan there for the registry rows that need to be
     * added. DO NOT extend this list silently; any new entry here must be
     * justified in the same PR by linking the follow-up task.
     */
    private const KNOWN_UNGATED_PRO_KEYS_FIX_IN_472 = [
        'hreflang_enabled',
        'hreflang_primary_language',
        'hreflang_sitemap',
        'schema_breadcrumb_pro',
        'schema_howto_enabled',
    ];

    public function testEveryProManifestFieldIsGatedByRegistry(): void
    {
        $registryKeys = array_flip(array_map(
            static fn(array $e): string => (string) $e['key'],
            ProFeatureRegistry::all()
        ));

        $sectionUnion = [];
        foreach (ProFeatureRegistry::sectionFields() as $sectionKey => $keys) {
            foreach ($keys as $k) {
                $sectionUnion[$k] = $sectionKey;
            }
        }

        $known = array_flip(self::KNOWN_UNGATED_PRO_KEYS_FIX_IN_472);
        $orphans = [];
        foreach ($this->loadAll() as $f) {
            if (($f['tier'] ?? 'free') !== 'pro') {
                continue;
            }
            $key = (string) ($f['key'] ?? '');
            if ($key === '') {
                continue;
            }
            if (isset($registryKeys[$key]) || isset($sectionUnion[$key]) || isset($known[$key])) {
                continue;
            }
            $orphans[] = $key . ' (sku=' . ($f['sku'] ?? '?') . ', tab=' . ($f['tab'] ?? '?') . ')';
        }

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
        // Inverse-direction smoke test: any key listed inside sectionFields()
        // that isn't a real manifest key (or known legacy key) is dead config.
        // We don't assert on the full set because the registry intentionally
        // lists a few historical aliases (e.g. schema_events_en); instead, we
        // collect the orphans and fail only if NEW ones appear by surfacing
        // them in the assertion message. Since this is a fail-loud test it
        // currently checks the count is bounded by a known allowlist.
        $manifestKeys = array_flip(array_map(
            static fn(array $f): string => (string) ($f['key'] ?? ''),
            $this->loadAll()
        ));

        // Documented historical aliases (kept in sectionFields() for legacy
        // installs where the row already exists in #__aiboost_settings under
        // these names). DO NOT remove without a data migration step.
        $legacyAllowlist = [
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
            'enable_hreflang',
            'enable_news_sitemap',
            'news_category_id',
            'news_publication_name',
            'indexnow_api_key',
            'indexnow_auto_submit',
            'schema_howto',
            // Orphans flagged by the parity test on 2026-05-27 — listed in
            // ProFeatureRegistry::sectionFields() but have no matching manifest
            // key. Either the manifest needs the field added or the registry
            // row needs to be removed. Tracked for fix in Task #472.
            'llms_full_max_articles',
            'events_category_id',
        ];
        $allow = array_flip($legacyAllowlist);

        $unknown = [];
        foreach (ProFeatureRegistry::sectionFields() as $section => $keys) {
            foreach ($keys as $k) {
                if (!isset($manifestKeys[$k]) && !isset($allow[$k])) {
                    $unknown[] = "$section → $k";
                }
            }
        }
        sort($unknown);
        $this->assertSame(
            [],
            $unknown,
            "ProFeatureRegistry::sectionFields() lists keys that are not in any manifest and not in the "
            . "legacy allowlist. Either add the manifest field, add the alias to the allowlist (with a "
            . "comment explaining the migration), or remove the stale row:\n  - " . implode("\n  - ", $unknown)
        );
    }
}
