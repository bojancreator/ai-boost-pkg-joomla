<?php

namespace AiBoost\Tests\Lib;

use AiBoost\Lib\PluginRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Plan 2a — anti-leak regression for PluginRegistry::coreLicenseActive().
 *
 * This is the single most important rule of per-integration licensing: an
 * integration (`int_*`) licence must NEVER count as a CORE activation.
 * saveLicenseState() uses coreLicenseActive() to decide whether to set the
 * permanent `pro_activated` flag (which unlocks the WHOLE core bundle forever)
 * and the back-compat `license_tier`. If an integration key counted here,
 * buying YOOtheme Pro or Multilang would unlock all of core Pro for free.
 *
 * coreLicenseActive() is pure (it only walks the states array via
 * resolveRealStatus), so these tests need no DB seam.
 */
final class PluginRegistryCoreLicenseActiveTest extends TestCase
{
    /** A license_state record that resolves to 'active' (key present, status active, no expiry). */
    private static function activeRecord(): array
    {
        return ['key' => 'LS-KEY-XYZ', 'status' => 'active', 'expires_at' => null];
    }

    public function testEmptyStatesAreNotCoreActive(): void
    {
        $this->assertFalse(PluginRegistry::coreLicenseActive([]));
    }

    public function testActiveCoreSkuIsCoreActive(): void
    {
        foreach (['schema', 'og', 'hreflang', 'code', 'aeo', 'bundle'] as $sku) {
            $this->assertTrue(
                PluginRegistry::coreLicenseActive([$sku => self::activeRecord()]),
                "core SKU '{$sku}' active must count as a core activation"
            );
        }
    }

    public function testActiveIntegrationKeyAloneIsNotCoreActive(): void
    {
        // THE anti-leak case: an active integration licence must not unlock core.
        foreach (['int_yootheme', 'int_falang'] as $sku) {
            $this->assertFalse(
                PluginRegistry::coreLicenseActive([$sku => self::activeRecord()]),
                "integration SKU '{$sku}' active must NOT count as a core activation"
            );
        }
    }

    public function testIntegrationKeysAreIgnoredAlongsideInactiveCore(): void
    {
        $states = [
            'int_yootheme' => self::activeRecord(),
            'int_falang'   => self::activeRecord(),
            'schema'       => ['key' => 'LS-OLD', 'status' => 'expired'],
        ];
        $this->assertFalse(PluginRegistry::coreLicenseActive($states));
    }

    public function testActiveCoreStillCountsWhenIntegrationsAlsoActive(): void
    {
        $states = [
            'int_yootheme' => self::activeRecord(),
            'bundle'       => self::activeRecord(),
        ];
        $this->assertTrue(PluginRegistry::coreLicenseActive($states));
    }

    public function testKeylessOrInactiveCoreRecordsAreNotActive(): void
    {
        $this->assertFalse(PluginRegistry::coreLicenseActive(['schema' => ['key' => '', 'status' => 'active']]));
        $this->assertFalse(PluginRegistry::coreLicenseActive(['og' => ['key' => 'X', 'status' => 'expired']]));
        $this->assertFalse(PluginRegistry::coreLicenseActive(['aeo' => 'not-an-array']));
    }
}
