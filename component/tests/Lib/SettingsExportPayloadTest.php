<?php

declare(strict_types=1);

namespace AiBoost\Tests\Lib;

use AiBoost\Component\AiBoost\Administrator\Controller\ImportController;
use AiBoost\Component\AiBoost\Administrator\Controller\SettingsController;
use AiBoost\Lib\SettingsSaveDefinition;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/stubs/JoomlaMvcController.php';
require_once dirname(__DIR__, 2) . '/com_aiboost/admin/src/Administrator/Controller/SettingsController.php';
require_once dirname(__DIR__, 2) . '/com_aiboost/admin/src/Administrator/Controller/ImportController.php';

/**
 * Regression tests for the settings export payload.
 *
 * Background: export() used to serialise the RAW settings blob — including
 * the customer's plaintext licence key, license_state and install_id — into
 * the downloadable backup JSON, while the import side correctly denylisted
 * them (a leak with zero round-trip value). It also omitted the
 * #__aiboost_translations rows, so the "backup" silently failed to back up a
 * flagship Pro feature that ImportController::upload() could already restore.
 *
 * SettingsController::buildExportPayload() is static and side-effect free so
 * the redaction + translations contract can be asserted directly here.
 */
final class SettingsExportPayloadTest extends TestCase
{
    /** A settings row mixing ordinary options with every system-managed key. */
    private function settingsRow(): array
    {
        $row = [
            'org_name'      => 'Acme Travel',
            'enable_schema' => '1',
            'schema_type'   => 'TravelAgency',
            'change_counter' => 42,
        ];
        foreach (SettingsSaveDefinition::SYSTEM_PRESERVED_KEYS as $i => $key) {
            $row[$key] = 'SECRET-VALUE-' . $i;
        }
        return $row;
    }

    /** @return array<int,array<string,string>> #__aiboost_translations row shape. */
    private function translationRows(): array
    {
        return [
            ['field_key' => 'org_name', 'lang_code' => 'de-DE', 'field_value' => 'Acme Reisen'],
            ['field_key' => 'org_name', 'lang_code' => 'sr-RS', 'field_value' => 'Acme Putovanja'],
        ];
    }

    private function buildPayload(): array
    {
        return SettingsController::buildExportPayload(
            $this->settingsRow(),
            $this->translationRows(),
            '2026-06-11T12:00:00+00:00',
            '6.1.0'
        );
    }

    public function testExportStripsEveryLicenseIdentityAndDevKey(): void
    {
        $payload = $this->buildPayload();

        foreach (SettingsSaveDefinition::SYSTEM_PRESERVED_KEYS as $key) {
            $this->assertArrayNotHasKey(
                $key,
                $payload['params'],
                "export must not leak '{$key}' into the backup file"
            );
        }

        // Belt and braces: none of the secret values may appear anywhere in
        // the serialised file, under any key.
        $json = json_encode($payload);
        $this->assertStringNotContainsString('SECRET-VALUE-', $json);

        // Ordinary settings survive untouched.
        $this->assertSame('Acme Travel', $payload['params']['org_name']);
        $this->assertSame('1', $payload['params']['enable_schema']);
        $this->assertSame('TravelAgency', $payload['params']['schema_type']);
    }

    public function testExportKeepsBackwardCompatibleShapeAndMeta(): void
    {
        $payload = $this->buildPayload();

        $this->assertSame('1.0', $payload['meta']['version']);
        $this->assertSame('pkg_aiboost', $payload['meta']['plugin']);
        $this->assertSame('2026-06-11T12:00:00+00:00', $payload['meta']['exported_at']);
        $this->assertSame('6.1.0', $payload['meta']['joomla']);
        $this->assertIsArray($payload['params']);
    }

    public function testExportIncludesTranslationsInTheImportRowShape(): void
    {
        $payload = $this->buildPayload();

        // ImportController::upload() iterates $data['translations'] and reads
        // field_key / lang_code / field_value per row — exactly this shape.
        $this->assertSame($this->translationRows(), $payload['translations']);
        foreach ($payload['translations'] as $row) {
            $this->assertArrayHasKey('field_key', $row);
            $this->assertArrayHasKey('lang_code', $row);
            $this->assertArrayHasKey('field_value', $row);
        }
    }

    public function testExportedParamsSurviveTheImportDenylistUntouched(): void
    {
        // Round-trip guarantee: the import side strips IMPORT_DENYLIST keys
        // from uploads. A freshly exported file must already be clean, so
        // import skips nothing and every exported param lands.
        $payload  = $this->buildPayload();
        $denylist = (new \ReflectionClassConstant(ImportController::class, 'IMPORT_DENYLIST'))->getValue();

        $afterImportStrip = $payload['params'];
        foreach ($denylist as $denied) {
            unset($afterImportStrip[$denied]);
        }

        $this->assertSame($payload['params'], $afterImportStrip);
    }
}
