<?php

declare(strict_types=1);

namespace AiBoost\Tests\Lib;

use AiBoost\Component\AiBoost\Administrator\Controller\ImportController;
use AiBoost\Lib\SettingsSaveDefinition;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/stubs/JoomlaMvcController.php';
require_once dirname(__DIR__, 2) . '/com_aiboost/admin/src/Administrator/Controller/ImportController.php';

/**
 * Parity guard between the three settings boundaries:
 *
 *   save   — SettingsController::save() carries SYSTEM_PRESERVED_KEYS forward,
 *   export — SettingsController::export() strips SYSTEM_PRESERVED_KEYS,
 *   import — ImportController::IMPORT_DENYLIST refuses them from uploads.
 *
 * The import denylist must always be a superset of the shared constant: any
 * key the save endpoint treats as system-managed must also be impossible to
 * smuggle in through an uploaded backup file (forged pro_activated,
 * license_state, spoofed install_id, ...).
 */
final class ImportDenylistParityTest extends TestCase
{
    /** @return array<int,string> */
    private function importDenylist(): array
    {
        $const = new \ReflectionClassConstant(ImportController::class, 'IMPORT_DENYLIST');

        return (array) $const->getValue();
    }

    public function testImportDenylistIsASupersetOfSystemPreservedKeys(): void
    {
        $missing = array_values(array_diff(
            SettingsSaveDefinition::SYSTEM_PRESERVED_KEYS,
            $this->importDenylist()
        ));

        $this->assertSame(
            [],
            $missing,
            'IMPORT_DENYLIST must contain every SYSTEM_PRESERVED_KEYS entry; missing: '
            . implode(', ', $missing)
        );
    }

    public function testImportDenylistKeepsItsHistoricalCoverage(): void
    {
        // The pre-refactor hand-maintained denylist — building on the shared
        // constant must never DROP a key that was already refused on import.
        $historical = [
            'license_key',
            'license_tier',
            'license_state',
            'license_simulation',
            'pro_skus',
            'pro_activated',
            'pro_activated_at',
            'pro_activated_version',
            'dev_license_preview',
            'dev_force_free_tier',
            'install_id',
            'last_backup_at',
        ];

        $this->assertSame(
            [],
            array_values(array_diff($historical, $this->importDenylist())),
            'Refactoring IMPORT_DENYLIST must not drop historical denylist coverage.'
        );
    }
}
