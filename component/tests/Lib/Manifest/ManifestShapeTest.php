<?php

namespace AiBoost\Tests\Lib\Manifest;

use PHPUnit\Framework\TestCase;

/**
 * Validate the per-tab manifest files at component/lib/src/Manifest/*.php
 * load cleanly, return well-shaped arrays, and don't drift away from the
 * conventions documented in Manifest/Registry.php.
 *
 * These tests load the manifest files directly via `require` (not through
 * Registry::all()) so no Joomla bootstrap is needed and the static
 * source-of-truth is verified in isolation from runtime contributions.
 */
final class ManifestShapeTest extends TestCase
{
    private const MANIFEST_DIR = __DIR__ . '/../../../lib/src/Manifest';
    private const TABS = ['core', 'schema', 'aeo', 'og', 'hreflang', 'code'];

    /** @return array<int, array<string,mixed>> */
    private function loadAllFields(): array
    {
        $all = [];
        foreach (self::TABS as $tab) {
            $path = self::MANIFEST_DIR . '/' . $tab . '.php';
            $this->assertFileExists($path, "Missing manifest file for tab '$tab'");
            $entries = require $path;
            $this->assertIsArray($entries, "Manifest $tab.php must return an array");
            foreach ($entries as $idx => $entry) {
                $this->assertIsArray($entry, "Entry #$idx in $tab.php is not an array");
                $all[] = $entry + ['__file' => $tab . '.php'];
            }
        }
        return $all;
    }

    public function testEveryFieldHasRequiredCoreKeys(): void
    {
        foreach ($this->loadAllFields() as $f) {
            $where = $f['__file'] . ': key=' . ($f['key'] ?? '<missing>');
            $this->assertArrayHasKey('key',  $f, "Missing 'key' in $where");
            $this->assertArrayHasKey('tab',  $f, "Missing 'tab' in $where");
            $this->assertArrayHasKey('type', $f, "Missing 'type' in $where");
            $this->assertNotSame('', (string) $f['key'],  "Empty 'key' in $where");
            $this->assertNotSame('', (string) $f['tab'],  "Empty 'tab' in $where");
            $this->assertNotSame('', (string) $f['type'], "Empty 'type' in $where");
        }
    }

    public function testEveryTierIsValid(): void
    {
        foreach ($this->loadAllFields() as $f) {
            $tier = $f['tier'] ?? 'free';
            $this->assertContains(
                $tier,
                ['free', 'pro'],
                "Invalid tier '$tier' for key '{$f['key']}' in {$f['__file']}"
            );
        }
    }

    public function testEveryTypeIsValid(): void
    {
        $allowed = ['toggle', 'text', 'textarea', 'select', 'number', 'media', 'json'];
        foreach ($this->loadAllFields() as $f) {
            $this->assertContains(
                $f['type'],
                $allowed,
                "Invalid type '{$f['type']}' for key '{$f['key']}' in {$f['__file']}"
            );
        }
    }

    public function testEverySkuIsValid(): void
    {
        $allowed = ['core', 'schema', 'aeo', 'og', 'hreflang', 'code'];
        foreach ($this->loadAllFields() as $f) {
            $sku = $f['sku'] ?? 'core';
            $this->assertContains(
                $sku,
                $allowed,
                "Invalid sku '$sku' for key '{$f['key']}' in {$f['__file']}"
            );
        }
    }

    public function testNoDuplicateKeysAcrossManifests(): void
    {
        $seen = [];
        foreach ($this->loadAllFields() as $f) {
            $key = (string) $f['key'];
            if (isset($seen[$key])) {
                $this->fail("Duplicate manifest key '$key' in {$f['__file']} (also in {$seen[$key]})");
            }
            $seen[$key] = $f['__file'];
        }
        $this->assertNotEmpty($seen);
    }

    public function testSelectTypeAlwaysHasOptions(): void
    {
        foreach ($this->loadAllFields() as $f) {
            if ($f['type'] !== 'select') {
                continue;
            }
            $this->assertIsArray(
                $f['options'] ?? null,
                "select-type field '{$f['key']}' must declare an 'options' array"
            );
            $this->assertNotEmpty(
                $f['options'],
                "select-type field '{$f['key']}' has empty 'options' map"
            );
        }
    }

    public function testFeatureClassOnlyOnProFields(): void
    {
        foreach ($this->loadAllFields() as $f) {
            if (empty($f['feature_class'])) {
                continue;
            }
            $this->assertSame(
                'pro',
                $f['tier'] ?? 'free',
                "feature_class on non-Pro field '{$f['key']}' — feature classes are Pro-only by convention"
            );
        }
    }

    public function testHealthBlockHasRequiredFields(): void
    {
        foreach ($this->loadAllFields() as $f) {
            $h = $f['health'] ?? null;
            if ($h === null) {
                continue;
            }
            $this->assertIsArray($h, "health block on '{$f['key']}' must be an array");
            $this->assertArrayHasKey('id', $h, "health.id missing on '{$f['key']}'");
            $this->assertArrayHasKey('category', $h, "health.category missing on '{$f['key']}'");
            $this->assertArrayHasKey('message', $h, "health.message missing on '{$f['key']}'");
            $this->assertMatchesRegularExpression(
                '/^(info|warning|critical|duplicate|conflict)_/',
                (string) $h['id'],
                "health.id '{$h['id']}' on '{$f['key']}' must start with a known prefix"
            );
        }
    }

    public function testI18nKeysFollowConvention(): void
    {
        foreach ($this->loadAllFields() as $f) {
            $i18n = $f['i18n'] ?? null;
            if ($i18n === null) {
                continue;
            }
            $this->assertIsArray($i18n);
            foreach (['label_key', 'description_key'] as $sub) {
                if (!isset($i18n[$sub])) {
                    continue;
                }
                $this->assertMatchesRegularExpression(
                    '/^PLG_SYSTEM_AIBOOST_[A-Z0-9_]+$/',
                    (string) $i18n[$sub],
                    "i18n.$sub '{$i18n[$sub]}' on '{$f['key']}' violates naming convention"
                );
            }
        }
    }
}
