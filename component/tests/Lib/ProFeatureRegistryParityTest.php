<?php

namespace AiBoost\Tests\Lib;

use AiBoost\Lib\ProFeatureRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Static parity test between the Vue SPA's <ProGate gate-key="…"> wrappers
 * and ProFeatureRegistry's PHP manifest.
 *
 * Why: Task #449 had to be verified manually by walking two live Joomla
 * admins and ticking 21 rows. The most common regression is a new Pro
 * option added to ProFeatureRegistry without a matching <ProGate> wrapper
 * (or vice versa) — which leaves a Pro field editable on a Free install,
 * or shows a "Pro" lock for a key the server doesn't actually strip.
 *
 * This test parses every component/com_aiboost/vue-admin/src/**\/*.vue
 * file for `gate-key="…"` attributes and asserts the resulting set is
 * exactly equal (in both directions) to ProFeatureRegistry::all() keys.
 *
 * It also asserts every `section:*` entry in all() has a matching row in
 * sectionFields(), so stripLocked() can never silently miss a Pro key.
 *
 * The Vue source lives in the repo (not under PSR-4 autoload), so this is
 * a pure-PHP static scan — no Node, no build step required in CI.
 */
final class ProFeatureRegistryParityTest extends TestCase
{
    private const VUE_SRC = __DIR__ . '/../../com_aiboost/vue-admin/src';

    /**
     * Recursively collect every .vue file under the SPA source directory.
     *
     * @return array<int, string>
     */
    private function collectVueFiles(): array
    {
        $root = realpath(self::VUE_SRC);
        $this->assertNotFalse($root, 'Vue SPA source directory not found: ' . self::VUE_SRC);

        $files = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'vue') {
                continue;
            }
            // Task #470 — `tabs/generated/` partials are pure derivations of the
            // manifest (codegen-from-manifest.py); their gate-keys are verified
            // by ManifestProRegistryParityTest, not by this Vue↔registry scan.
            // Including them here produces spurious "orphan" reports for keys
            // that are intentionally gated at section level in ProFeatureRegistry.
            if (str_contains(str_replace(DIRECTORY_SEPARATOR, '/', $file->getPathname()), '/tabs/generated/')) {
                continue;
            }
            $files[] = $file->getPathname();
        }
        sort($files);
        return $files;
    }

    /**
     * Extract every literal gate-key="…" attribute value from a .vue file.
     *
     * Intentionally only matches static string literals — dynamic
     * `:gate-key="expr"` bindings are deliberately rejected because they
     * cannot be statically verified against the PHP registry. If anyone
     * introduces one, the parity check would silently let them through;
     * we surface that by collecting them separately and asserting none.
     *
     * @return array{static: array<int,string>, dynamic: array<int,string>}
     */
    private function extractGateKeys(string $vuePath): array
    {
        $src = file_get_contents($vuePath);
        $this->assertNotFalse($src, "Failed to read $vuePath");

        // Skip ProGate.vue itself — it is the gate component implementation,
        // not a consumer. Its <script> block references gate-key in JS code
        // (e.g. console.warn) which would otherwise be misparsed as a gate.
        if (basename($vuePath) === 'ProGate.vue') {
            return ['static' => [], 'dynamic' => []];
        }

        // Strip <script> and <style> blocks so we only scan template markup.
        $template = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $src) ?? $src;
        $template = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $template) ?? $template;

        $static = [];
        if (preg_match_all('/\bgate-key\s*=\s*"([^"]+)"/', $template, $m)) {
            $static = $m[1];
        }
        $dynamic = [];
        if (preg_match_all('/:gate-key\s*=\s*"([^"]+)"/', $template, $m)) {
            $dynamic = $m[1];
        }
        return ['static' => $static, 'dynamic' => $dynamic];
    }

    public function testEveryRegistryEntryHasMatchingVueProGateWrapper(): void
    {
        $registryKeys = array_map(
            static fn(array $e): string => (string) $e['key'],
            ProFeatureRegistry::all()
        );
        sort($registryKeys);

        $vueKeys = [];
        foreach ($this->collectVueFiles() as $f) {
            $extracted = $this->extractGateKeys($f);
            foreach ($extracted['static'] as $k) {
                $vueKeys[] = $k;
            }
        }
        $vueKeys = array_values(array_unique($vueKeys));
        sort($vueKeys);

        $missingInVue = array_values(array_diff($registryKeys, $vueKeys));
        $this->assertSame(
            [],
            $missingInVue,
            "ProFeatureRegistry::all() lists Pro keys that have no matching <ProGate gate-key=\"…\"> "
            . "wrapper in the Vue SPA. Either add the wrapper or remove the registry entry:\n  - "
            . implode("\n  - ", $missingInVue)
        );
    }

    public function testEveryVueProGateWrapperHasMatchingRegistryEntry(): void
    {
        $registryKeys = array_map(
            static fn(array $e): string => (string) $e['key'],
            ProFeatureRegistry::all()
        );
        $registrySet = array_flip($registryKeys);

        $orphans = [];
        foreach ($this->collectVueFiles() as $f) {
            $extracted = $this->extractGateKeys($f);
            foreach ($extracted['static'] as $k) {
                if (!isset($registrySet[$k])) {
                    $orphans[] = $k . '  (in ' . basename($f) . ')';
                }
            }
        }
        $orphans = array_values(array_unique($orphans));
        sort($orphans);

        $this->assertSame(
            [],
            $orphans,
            "The Vue SPA contains <ProGate gate-key=\"…\"> wrappers that point at keys missing "
            . "from ProFeatureRegistry::all(). Add the registry entry (so stripLocked() can "
            . "actually strip the value on save) or fix the typo:\n  - "
            . implode("\n  - ", $orphans)
        );
    }

    public function testNoDynamicGateKeyBindings(): void
    {
        $dynamic = [];
        foreach ($this->collectVueFiles() as $f) {
            $extracted = $this->extractGateKeys($f);
            foreach ($extracted['dynamic'] as $k) {
                $dynamic[] = basename($f) . ': :gate-key="' . $k . '"';
            }
        }
        $this->assertSame(
            [],
            $dynamic,
            "Dynamic :gate-key bindings cannot be statically verified against ProFeatureRegistry. "
            . "Use a static gate-key=\"…\" literal so CI can guarantee parity:\n  - "
            . implode("\n  - ", $dynamic)
        );
    }

    /**
     * Task #459 — enum gating parity for the Schema Type dropdown.
     *
     * proOptions()['schema_type'] is the Pro subset that stripProOptions()
     * rewrites on a Free save. The SPA dropdown in SchemaTab.vue must list
     * EXACTLY the same Pro values (no more, no less) so the per-option
     * lock state matches the server-side enforcement. A drift here would
     * either leave a Pro option unlocked in the UI (silent Pro save attempt
     * → server rewrites it without any UX feedback) or lock a Free option
     * pointlessly.
     */
    public function testSchemaTypeProSetMatchesVueDropdown(): void
    {
        $proSet = ProFeatureRegistry::proOptions()['schema_type'] ?? [];
        $this->assertNotEmpty(
            $proSet,
            'ProFeatureRegistry::proOptions()[schema_type] must list at least one Pro value, '
            . 'otherwise enum gating is a no-op.'
        );

        $default = ProFeatureRegistry::proOptionDefaults()['schema_type'] ?? '';
        $this->assertNotSame('', $default, 'No Free fallback documented for schema_type.');
        $this->assertNotContains(
            $default,
            $proSet,
            'schema_type Free fallback "' . $default . '" is itself a Pro value — stripProOptions() '
            . 'would loop a Pro save back into a Pro save.'
        );

        $tabPath = realpath(self::VUE_SRC . '/tabs/SchemaTab.vue');
        $this->assertNotFalse($tabPath, 'SchemaTab.vue not found.');
        $src = (string) file_get_contents($tabPath);

        // Parse the SCHEMA_TYPE_OPTIONS constant: collect every {value:'X', ..., pro:true|false}.
        $proVue  = [];
        $allVue  = [];
        if (preg_match('/const\s+SCHEMA_TYPE_OPTIONS\s*=\s*\[(.*?)\]/s', $src, $block)
            && preg_match_all('/\{\s*value:\s*\'([^\']+)\'[^}]*?pro:\s*(true|false)\s*\}/', $block[1], $m, PREG_SET_ORDER)
        ) {
            foreach ($m as $row) {
                $allVue[] = $row[1];
                if ($row[2] === 'true') {
                    $proVue[] = $row[1];
                }
            }
        }
        $this->assertNotEmpty(
            $allVue,
            'Could not parse SCHEMA_TYPE_OPTIONS in SchemaTab.vue. Has the constant been renamed?'
        );

        sort($proSet); sort($proVue);
        $this->assertSame(
            $proSet,
            $proVue,
            "Schema Type Pro subset drift between ProFeatureRegistry and SchemaTab.vue.\n"
            . "Registry: " . implode(',', $proSet) . "\n"
            . "Vue:      " . implode(',', $proVue)
        );

        // The Free fallback must be one of the offered options.
        $this->assertContains(
            $default,
            $allVue,
            'Free fallback "' . $default . '" is not even listed in SchemaTab.vue dropdown.'
        );
    }

    public function testEverySectionEntryHasMatchingSectionFieldsRow(): void
    {
        $sectionEntries = array_filter(
            ProFeatureRegistry::all(),
            static fn(array $e): bool => strpos((string) $e['key'], 'section:') === 0
        );
        $sectionKeys = array_map(
            static fn(array $e): string => (string) $e['key'],
            $sectionEntries
        );
        sort($sectionKeys);

        $mappedKeys = array_keys(ProFeatureRegistry::sectionFields());
        sort($mappedKeys);

        $missingMapping = array_values(array_diff($sectionKeys, $mappedKeys));
        $this->assertSame(
            [],
            $missingMapping,
            "ProFeatureRegistry::all() declares section: entries with no row in sectionFields(). "
            . "Without a mapping, stripLocked() cannot drop the section's underlying Pro keys on "
            . "a Free save:\n  - "
            . implode("\n  - ", $missingMapping)
        );

        $orphanMapping = array_values(array_diff($mappedKeys, $sectionKeys));
        $this->assertSame(
            [],
            $orphanMapping,
            "ProFeatureRegistry::sectionFields() has rows for section keys that no longer appear "
            . "in all(). Either re-add the registry entry or remove the stale mapping:\n  - "
            . implode("\n  - ", $orphanMapping)
        );
    }
}
