<?php

namespace AiBoost\Tests\Lib;

use AiBoost\Lib\ProFeatureRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Static guard for the v0.5 one-product transition.
 *
 * The SPA may keep the ProGate component as a pass-through compatibility
 * wrapper, but feature/page locks and route-level proGate metadata must not
 * reappear.
 *
 * The Vue source lives in the repo (not under PSR-4 autoload), so this is
 * a pure-PHP static scan — no Node, no build step required in CI.
 */
final class ProFeatureRegistryParityTest extends TestCase
{
    private const VUE_SRC = __DIR__ . '/../../com_aiboost/vue-admin/src';
    private const ROUTER_SRC = self::VUE_SRC . '/router.js';

    /**
     * Recursively collect every .vue file under the SPA source directory.
     *
     * @return array<int, string>
     */
    private function collectVueFiles(bool $includeGenerated = false): array
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
            // Task #470 — generated partials may satisfy registry→Vue parity
            // for direct manifest field gates, but Vue→registry parity ignores
            // them because many generated fields are intentionally gated by a
            // parent section instead of a direct registry entry.
            if (!$includeGenerated && str_contains(str_replace(DIRECTORY_SEPARATOR, '/', $file->getPathname()), '/tabs/generated/')) {
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
        if (preg_match_all('/(?:^|\s)gate-key\s*=\s*"([^"]+)"/', $template, $m)) {
            $static = $m[1];
        }
        $dynamic = [];
        if (preg_match_all('/:gate-key\s*=\s*"([^"]+)"/', $template, $m)) {
            $dynamic = $m[1];
        }
        return ['static' => $static, 'dynamic' => $dynamic];
    }

    /** @return array<int,string> */
    private function extractRouterProGateKeys(): array
    {
        $src = file_get_contents(self::ROUTER_SRC);
        $this->assertNotFalse($src, 'Failed to read router.js');

        $keys = [];
        if (preg_match_all('/\bproGate\s*:\s*\'([^\']+)\'/', $src, $m)) {
            $keys = $m[1];
        }
        $keys = array_values(array_unique($keys));
        sort($keys);
        return $keys;
    }

    public function testNoVueProGateWrappersRemain(): void
    {
        $vueKeys = [];
        foreach ($this->collectVueFiles(true) as $f) {
            $extracted = $this->extractGateKeys($f);
            foreach ($extracted['static'] as $k) {
                $vueKeys[] = $k;
            }
        }
        foreach ($this->extractRouterProGateKeys() as $k) {
            $vueKeys[] = $k;
        }
        $vueKeys = array_values(array_unique($vueKeys));
        sort($vueKeys);

        $this->assertSame(
            [],
            $vueKeys,
            "The one-product admin must not render feature/page <ProGate gate-key=\"…\"> locks:\n  - "
            . implode("\n  - ", $vueKeys)
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
        foreach ($this->extractRouterProGateKeys() as $k) {
            if (!isset($registrySet[$k])) {
                $orphans[] = $k . '  (in router.js meta.proGate)';
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

    public function testOnlyRouteLevelGateUsesDynamicGateKeyBinding(): void
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
            "The one-product admin must not use dynamic route-level :gate-key bindings:\n  - "
            . implode("\n  - ", $dynamic)
        );
    }

    /**
     * Schema Type options are all available in the one-product model.
     */
    public function testSchemaTypeProSetMatchesVueDropdown(): void
    {
        $proSet = ProFeatureRegistry::proOptions()['schema_type'] ?? [];
        $this->assertSame([], $proSet, 'Schema Type must not retain a server-side Pro option subset.');

        $tabPath = realpath(self::VUE_SRC . '/tabs/SchemaTab.vue');
        $this->assertNotFalse($tabPath, 'SchemaTab.vue not found.');
        $src = (string) file_get_contents($tabPath);

        $this->assertStringContainsString('SCHEMA_TYPE_OPTIONS', $src);
        $this->assertStringNotContainsString('pro: true', $src);
        $this->assertStringNotContainsString('PRO_SCHEMA_TYPES', $src);
        $this->assertStringNotContainsString('(Pro)', $src);
    }

    /** @return array{0:list<string>,1:list<string>} */
    private function schemaTypeOptionSets(string $src): array
    {
        if (!preg_match('/const\s+SCHEMA_TYPE_OPTIONS\s*=\s*\[(.*?)\]/s', $src, $block)) {
            return [[], []];
        }
        if (!preg_match_all('/\{\s*value:\s*\'([^\']+)\'[^}]*?pro:\s*(true|false)\s*\}/', $block[1], $matches, PREG_SET_ORDER)) {
            return [[], []];
        }

        $all = [];
        $pro = [];
        foreach ($matches as $row) {
            $all[] = $row[1];
            if ($row[2] === 'true') {
                $pro[] = $row[1];
            }
        }

        return [$all, $pro];
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
