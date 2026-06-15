<?php

namespace AiBoost\Tests\Lib\Manifest;

use PHPUnit\Framework\TestCase;

/**
 * Every manifest field that declares `feature_class` must have a real PHP
 * stub at the expected location inside the matching Pro plugin directory.
 *
 * Why: scripts/codegen-from-manifest.py creates these stubs idempotently,
 * but the codegen runs in `--check` mode only as part of the build. A
 * test here makes the binding part of `composer phpunit` so missing stubs
 * fail PR CI immediately rather than at zip time.
 */
final class ManifestFeatureClassPathsTest extends TestCase
{
    private const MANIFEST_DIR = __DIR__ . '/../../../lib/src/Manifest';
    private const PLUGINS_DIR  = __DIR__ . '/../../../plugins/system';
    private const TABS = ['core', 'schema', 'aeo', 'og', 'hreflang', 'code'];

    /** Mirrors SKU_TO_PRO_DIR in scripts/codegen-from-manifest.py */
    private const SKU_TO_PRO_DIR = [
        'schema'   => 'aiboost_schema',   // collapsed: Pro classes relocated into the free plugin
        'aeo'      => 'aiboost_aeo',      // collapsed: Pro classes relocated into the free plugin
        'og'       => 'aiboost_social',   // collapsed: Pro classes relocated into the free plugin
        'hreflang' => 'aiboost_aeo',      // collapsed: hreflang renders inside the AEO tab/plugin
        'code'     => 'aiboost_code_pro',
    ];

    /** @return array<int, array<string,mixed>> */
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

    public function testEveryFeatureClassHasMatchingStub(): void
    {
        $missing = [];
        foreach ($this->loadAll() as $f) {
            $cls = $f['feature_class'] ?? null;
            if (!$cls) {
                continue;
            }
            $sku = (string) ($f['sku'] ?? '');
            $dir = self::SKU_TO_PRO_DIR[$sku] ?? null;
            if (!$dir) {
                $missing[] = "key={$f['key']}: unknown sku '$sku' for feature_class='$cls'";
                continue;
            }
            $path = self::PLUGINS_DIR . '/' . $dir . '/src/Features/' . $cls . '.php';
            if (!is_file($path)) {
                $missing[] = "key={$f['key']}: expected stub at $path";
            }
        }
        $this->assertSame([], $missing, "Missing Pro feature stubs:\n  - " . implode("\n  - ", $missing));
    }

    public function testHealthOverrideFilesAreSelfConsistent(): void
    {
        // Every file inside Manifest/Health/ must match an existing manifest
        // health.id (otherwise it's an orphan from a removed option).
        $healthDir = self::MANIFEST_DIR . '/Health';
        if (!is_dir($healthDir)) {
            $this->markTestSkipped('No Health/ overrides directory yet.');
        }

        $declared = [];
        foreach ($this->loadAll() as $f) {
            $h = $f['health']['id'] ?? null;
            if ($h) {
                $declared[$this->studlyFromHealthId((string) $h)] = $h;
            }
        }

        $orphans = [];
        foreach (glob($healthDir . '/*.php') ?: [] as $file) {
            $cls = basename($file, '.php');
            if (!isset($declared[$cls])) {
                $orphans[] = $cls . ' (' . $file . ')';
            }
        }
        $this->assertSame(
            [],
            $orphans,
            "Health override files with no matching manifest.health.id:\n  - " . implode("\n  - ", $orphans)
        );
    }

    private function studlyFromHealthId(string $id): string
    {
        $parts = preg_split('/[^A-Za-z0-9]+/', $id) ?: [];
        return implode('', array_map(static fn(string $p): string => ucfirst($p), array_filter($parts)));
    }
}
