<?php

namespace AiBoost\Tests\Lib;

use AiBoost\Lib\LicenseHeartbeat;
use AiBoost\Lib\LicenseReconcile;
use PHPUnit\Framework\TestCase;

/**
 * Plan 2a — anti-leak / isolation regression for the two server-talking license
 * helpers that pick a "current license" out of the license_state map:
 *
 *   • LicenseReconcile::shouldRun()  — must treat an install that holds ONLY an
 *     active integration (int_*) licence as STILL eligible for core
 *     reconciliation (the int_ key must not masquerade as an active CORE
 *     licence and suppress recovery of a lapsed core purchaser).
 *   • LicenseHeartbeat::shouldRun()  — must NOT fire a core-endpoint heartbeat
 *     for an install whose only key is an integration key; heartbeating an
 *     int_ key against the CORE licence endpoint would surface an integration's
 *     status as the core status and bind the wrong key to this install.
 *
 * Both shouldRun() methods are pure functions of $settings (no DB/HTTP), so
 * these tests need no adapter seam. They mirror the coreLicenseActive() guard
 * proven in PluginRegistryCoreLicenseActiveTest.
 */
final class LicenseIntegrationKeyIsolationTest extends TestCase
{
    /** A license_state record that resolves to 'active'. */
    private static function activeRecord(): array
    {
        return ['key' => 'LS-KEY-XYZ', 'status' => 'active', 'expires_at' => null];
    }

    // ── LicenseReconcile ────────────────────────────────────────────────────

    public function testReconcileRunsWhenOnlyIntegrationLicenceIsActive(): void
    {
        // A lapsed CORE purchaser who also bought YOOtheme Pro: the int_ key is
        // active but must NOT count as "core already handled", so reconcile is
        // still eligible to recover the core entitlement from the server.
        $settings = [
            'install_id'    => '11111111-1111-4111-8111-111111111111',
            'license_state' => ['int_yootheme' => self::activeRecord()],
        ];
        $this->assertTrue(
            LicenseReconcile::shouldRun($settings),
            'an active integration licence alone must not suppress core reconciliation'
        );
    }

    public function testReconcileDoesNotRunWhenCoreLicenceIsActive(): void
    {
        // Control: an active CORE/bundle licence is handled by the normal verify
        // flow, so reconcile correctly backs off.
        $settings = [
            'install_id'    => '22222222-2222-4222-8222-222222222222',
            'license_state' => ['bundle' => self::activeRecord()],
        ];
        $this->assertFalse(
            LicenseReconcile::shouldRun($settings),
            'an active core licence must suppress reconciliation'
        );
    }

    public function testReconcileStillRunsWithIntegrationActiveAlongsideExpiredCore(): void
    {
        $settings = [
            'install_id'    => '33333333-3333-4333-8333-333333333333',
            'license_state' => [
                'int_falang' => self::activeRecord(),
                'schema'     => ['key' => 'LS-OLD', 'status' => 'expired'],
            ],
        ];
        $this->assertTrue(
            LicenseReconcile::shouldRun($settings),
            'expired core + active integration must remain eligible for reconciliation'
        );
    }

    // ── LicenseHeartbeat ────────────────────────────────────────────────────

    public function testHeartbeatDoesNotRunForIntegrationOnlyInstall(): void
    {
        // THE isolation case: the only key is an integration key → no core
        // heartbeat key exists → shouldRun() is false (no POST of an int_ key to
        // the core endpoint).
        foreach (['int_yootheme', 'int_falang'] as $sku) {
            $settings = [
                'install_id'    => '44444444-4444-4444-8444-444444444444',
                'license_state' => [$sku => self::activeRecord()],
            ];
            $this->assertFalse(
                LicenseHeartbeat::shouldRun($settings),
                "integration-only install ({$sku}) must not fire a core heartbeat"
            );
        }
    }

    public function testHeartbeatRunsForCoreBundleKey(): void
    {
        // Control: a real core key still heartbeats (never checked before → due).
        $settings = [
            'install_id'    => '55555555-5555-4555-8555-555555555555',
            'license_state' => ['bundle' => self::activeRecord()],
        ];
        $this->assertTrue(
            LicenseHeartbeat::shouldRun($settings),
            'a core bundle key must drive the heartbeat'
        );
    }

    public function testHeartbeatRunsForCoreKeyEvenAlongsideIntegrationKey(): void
    {
        // A bundle buyer who also owns an integration: the CORE key is selected
        // (passes 1-4), never the int_ key.
        $settings = [
            'install_id'    => '66666666-6666-4666-8666-666666666666',
            'license_state' => [
                'int_yootheme' => self::activeRecord(),
                'schema'       => self::activeRecord(),
            ],
        ];
        $this->assertTrue(
            LicenseHeartbeat::shouldRun($settings),
            'core schema key must drive the heartbeat even when an integration key is present'
        );
    }
}
