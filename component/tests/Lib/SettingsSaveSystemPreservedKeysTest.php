<?php

declare(strict_types=1);

namespace AiBoost\Tests\Lib;

use AiBoost\Lib\SettingsSaveDefinition;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the SYSTEM_PRESERVED_KEYS carry-forward in the
 * Settings save flow.
 *
 * Background: SettingsController::save() rebuilds the whole settings blob
 * from the posted whitelist keys and REPLACES the row. Before this contract
 * existed, only dismissed_checks and the three tier/dev keys were carried
 * forward — so an ordinary settings save WIPED pro_activated, license_state,
 * license_key, install_id and the rest of the system-managed state. A paying
 * customer who activated Pro lost their perpetual activation (and stored
 * licence key) the first time they saved any setting.
 *
 * The fix routes the carry-forward through the pure
 * SettingsSaveDefinition::mergeSystemPreservedKeys(), tested here, which the
 * controller calls verbatim (asserted structurally below). The merge is
 * fail-closed in both directions: existing values always win, and keys
 * absent from the existing row are stripped from the payload so a client
 * posting `pro_activated=1` cannot self-promote a Free install.
 */
final class SettingsSaveSystemPreservedKeysTest extends TestCase
{
    /** A realistic activated-Pro settings row (system-managed keys only). */
    private function activatedProRow(): array
    {
        return [
            'pro_activated'         => '1',
            'pro_activated_at'      => '2026-01-15T10:00:00+00:00',
            'pro_activated_version' => '0.73.0',
            'license_key'           => 'LS-REAL-KEY-1234',
            'license_tier'          => 'pro',
            'license_state'         => [
                'bundle' => ['key' => 'LS-REAL-KEY-1234', 'status' => 'active', 'verified_at' => '2026-01-15T10:00:00+00:00'],
            ],
            'license_heartbeat'     => ['last_run' => '2026-06-01T00:00:00+00:00', 'verdict' => 'ok'],
            'license_reconcile'     => ['last_run' => '2026-06-01T00:00:00+00:00'],
            'license_simulation'    => [],
            'pro_skus'              => ['bundle'],
            'install_id'            => 'inst-aaaa-bbbb-cccc',
            'last_backup_at'        => '2026-05-20T08:00:00+00:00',
        ];
    }

    public function testOrdinarySaveCannotWipeActivationLicenseOrIdentity(): void
    {
        $existing = $this->activatedProRow() + ['org_name' => 'Old Name'];

        // An ordinary settings save posts only form fields — none of the
        // system-managed keys are in the payload.
        $posted = ['org_name' => 'New Name', 'enable_schema' => '1'];

        $merged = SettingsSaveDefinition::mergeSystemPreservedKeys($posted, $existing);

        foreach ($this->activatedProRow() as $key => $value) {
            $this->assertArrayHasKey($key, $merged, "save must carry '{$key}' forward");
            $this->assertSame($value, $merged[$key], "save must not alter '{$key}'");
        }

        // Ordinary form fields are untouched by the merge.
        $this->assertSame('New Name', $merged['org_name']);
        $this->assertSame('1', $merged['enable_schema']);
    }

    public function testClientCannotSelfPromoteOnAFreeInstall(): void
    {
        // Fresh Free install: no system-managed keys in the existing row.
        $existing = ['org_name' => 'Free Site'];

        // Hostile payload: client posts activation/licence/dev keys directly.
        $posted = [
            'org_name'            => 'Free Site',
            'pro_activated'       => '1',
            'pro_activated_at'    => '2026-06-11T00:00:00+00:00',
            'license_tier'        => 'pro',
            'license_key'         => 'FORGED-KEY',
            'license_state'       => ['bundle' => ['key' => 'FORGED-KEY', 'status' => 'active']],
            'dev_license_preview' => '1',
            'install_id'          => 'spoofed-id',
        ];

        $merged = SettingsSaveDefinition::mergeSystemPreservedKeys($posted, $existing);

        foreach (SettingsSaveDefinition::SYSTEM_PRESERVED_KEYS as $key) {
            $this->assertArrayNotHasKey(
                $key,
                $merged,
                "posted '{$key}' must be dropped when absent from the existing row (fail-closed)"
            );
        }
        $this->assertSame('Free Site', $merged['org_name']);
    }

    public function testExistingValueAlwaysWinsOverPostedValue(): void
    {
        // Activated install: a posted pro_activated=0 (or a stale licence
        // key) must never overwrite the stored state either.
        $existing = $this->activatedProRow();
        $posted   = [
            'pro_activated' => '0',
            'license_key'   => 'TAMPERED',
            'license_tier'  => 'free',
        ];

        $merged = SettingsSaveDefinition::mergeSystemPreservedKeys($posted, $existing);

        $this->assertSame('1', $merged['pro_activated']);
        $this->assertSame('LS-REAL-KEY-1234', $merged['license_key']);
        $this->assertSame('pro', $merged['license_tier']);
    }

    public function testConstantCoversEveryActivationLicenseAndIdentityKey(): void
    {
        // Exact-list regression: removing any of these keys silently
        // reintroduces the save-wipes-activation bug for that key.
        $this->assertEqualsCanonicalizing(
            [
                'license_key',
                'license_tier',
                'license_state',
                'license_heartbeat',
                'license_reconcile',
                'license_simulation',
                'pro_activated',
                'pro_activated_at',
                'pro_activated_version',
                'pro_skus',
                'pro_installed',
                'install_id',
                'last_backup_at',
                'dev_license_preview',
                'dev_force_free_tier',
            ],
            SettingsSaveDefinition::SYSTEM_PRESERVED_KEYS
        );
    }

    public function testSettingsControllerSaveUsesTheSharedMerge(): void
    {
        // Structural guard: the controller must route its carry-forward
        // through the pure helper tested above (no private re-implementation
        // that could drift), and must exclude the preserved keys from the
        // change-based backup-reminder counter.
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/com_aiboost/admin/src/Administrator/Controller/SettingsController.php'
        );

        $this->assertIsString($source);
        $this->assertStringContainsString(
            'SettingsSaveDefinition::mergeSystemPreservedKeys($settings, $existingForMerge)',
            $source,
            'save() must carry system-preserved keys forward via the shared merge helper.'
        );
        $this->assertStringContainsString(
            'SettingsSaveDefinition::SYSTEM_PRESERVED_KEYS',
            $source,
            'save() must exclude system-preserved keys from the change counter bookkeeping.'
        );
    }
}
