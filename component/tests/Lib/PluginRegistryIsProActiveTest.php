<?php

namespace AiBoost\Tests\Lib;

use AiBoost\Lib\PluginRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for PluginRegistry::isProActive() — the canonical
 * bundle-level "is this install Pro right now" gate used by the admin
 * bootstrap, the settings-save endpoint and the sitemap runtime.
 *
 * Task #565 — PERPETUAL ACTIVATION. Pro is unlocked by a permanent
 * `pro_activated` flag that saveLicenseState() sets the first time a key
 * verifies active. From then on Pro stays unlocked FOREVER — expiry, a
 * lapsed heartbeat or a non-active license_state status NEVER relock it
 * (an expired licence only pauses updates + support, enforced by the
 * update server). This reverses the v0.54.2 "Pro without a currently
 * verified key looks like Free" rule.
 *
 * Precedence: dev_force_free_tier → pro_activated → dev_license_preview → false.
 *
 * isProActive() reads only its $settings argument (no DB), so these tests
 * need no Factory / DB seam.
 */
final class PluginRegistryIsProActiveTest extends TestCase
{
    public function testFreeInstallIsNotPro(): void
    {
        $this->assertFalse(PluginRegistry::isProActive([]));
    }

    public function testActivationFlagUnlocksPro(): void
    {
        $this->assertTrue(PluginRegistry::isProActive(['pro_activated' => '1']));
    }

    public function testActivationSurvivesExpiredLicenseState(): void
    {
        // The whole point of perpetual activation: once activated, a later
        // expired / non-active license_state status must NOT relock Pro.
        foreach (['expired', 'disabled', 'not_licensed', ''] as $status) {
            $settings = [
                'pro_activated' => '1',
                'license_state' => ['schema' => ['key' => 'AB-X', 'status' => $status]],
            ];
            $this->assertTrue(
                PluginRegistry::isProActive($settings),
                "activated install must stay Pro even when license_state status is '{$status}'"
            );
        }
    }

    public function testActivationSurvivesLapsedHeartbeat(): void
    {
        $settings = [
            'pro_activated'     => '1',
            'license_heartbeat' => ['last_verdict' => 'soft_warning', 'status' => 'expired'],
        ];
        $this->assertTrue(PluginRegistry::isProActive($settings));
    }

    public function testLicenseTierAloneDoesNotUnlock(): void
    {
        // A materialised tier without the activation flag must NOT count as Pro.
        $this->assertFalse(PluginRegistry::isProActive(['license_tier' => 'pro']));
        $this->assertFalse(PluginRegistry::isProActive(['license_tier' => 'agency']));
        $this->assertFalse(PluginRegistry::isProActive(['license_tier' => 'developer']));
    }

    public function testActiveLicenseStateWithoutActivationFlagDoesNotUnlock(): void
    {
        // isProActive() no longer walks license_state — only saveLicenseState()
        // does, and it sets pro_activated. A license_state with no flag (which
        // should never happen in practice) must not unlock on its own.
        $settings = ['license_state' => ['schema' => ['key' => 'AB-XXXX', 'status' => 'active']]];
        $this->assertFalse(PluginRegistry::isProActive($settings));
    }

    public function testDevLicensePreviewForcesPro(): void
    {
        $this->assertTrue(PluginRegistry::isProActive(['dev_license_preview' => '1']));
    }

    public function testDevForceFreeTierOverridesActivation(): void
    {
        $settings = [
            'pro_activated'       => '1',
            'dev_force_free_tier' => '1',
        ];
        $this->assertFalse(PluginRegistry::isProActive($settings));
    }

    public function testDevForceFreeTierWinsOverDevLicensePreview(): void
    {
        $settings = [
            'dev_license_preview' => '1',
            'dev_force_free_tier' => '1',
        ];
        $this->assertFalse(PluginRegistry::isProActive($settings));
    }
}
