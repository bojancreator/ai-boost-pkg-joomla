<?php

declare(strict_types=1);

namespace AiBoost\Tests\Lib;

use AiBoost\Lib\Integration\AbstractIntegrationPlugin;
use AiBoost\Lib\SettingsSaveDefinition;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Plan 1 — integration master toggles. Verifies the static manifest keys, the
 * SDK gate plumbing (isActive/isAdminEnabled), and that runtime handlers gate
 * on isActive() so the switch actually pauses output.
 */
final class MasterToggleTest extends TestCase
{
    /** @return array<int,array<string,mixed>> */
    private function coreManifest(): array
    {
        $path = dirname(__DIR__, 2) . '/lib/src/Manifest/core.php';
        self::assertFileExists($path);
        /** @var array<int,array<string,mixed>> $fields */
        $fields = require $path;
        return $fields;
    }

    private function field(string $key): ?array
    {
        foreach ($this->coreManifest() as $f) {
            if (($f['key'] ?? '') === $key) {
                return $f;
            }
        }
        return null;
    }

    public function testMasterToggleKeysExistAsStaticFreeFields(): void
    {
        foreach (['integration_falang_enabled', 'integration_yootheme_enabled'] as $key) {
            $f = $this->field($key);
            self::assertNotNull($f, "$key must be a static core manifest field.");
            self::assertSame('1', (string) $f['default'], "$key must default to ON.");
            self::assertSame('toggle', $f['type']);
            self::assertSame('free', $f['tier']);
            // No `integration` tag — otherwise applyLockState would lock the
            // switch itself whenever the bridge is off (the bug the design avoids).
            self::assertArrayNotHasKey('integration', $f, "$key must NOT carry an integration tag.");
        }
    }

    public function testToggleKeysAreInSaveWhitelist(): void
    {
        $accepted = SettingsSaveDefinition::acceptedKeys();
        self::assertContains('integration_falang_enabled', $accepted);
        self::assertContains('integration_yootheme_enabled', $accepted);
    }

    public function testAbstractIntegrationPluginExposesActivationGate(): void
    {
        $rc = new ReflectionClass(AbstractIntegrationPlugin::class);
        self::assertTrue($rc->hasMethod('isActive'), 'SDK base must provide isActive().');
        self::assertTrue($rc->hasMethod('isAdminEnabled'), 'SDK base must provide isAdminEnabled().');
        self::assertTrue($rc->hasMethod('readAiBoostSetting'), 'SDK base must provide readAiBoostSetting().');
    }

    public function testFalangRuntimeHandlersGateOnIsActive(): void
    {
        $src = file_get_contents(
            dirname(__DIR__, 2) . '/plugins/system/aiboost_int_falang/src/Extension/AiBoostIntFalang.php'
        );
        // The output handlers must check isActive() (host + admin toggle), not
        // the bare isDetected() (host only), so the master switch pauses output.
        self::assertStringContainsString('$this->isActive()', $src);
    }

    public function testYoothemeRuntimeHandlersGateOnIsActive(): void
    {
        $src = file_get_contents(
            dirname(__DIR__, 2) . '/plugins/system/aiboost_int_yootheme/src/Extension/AiBoostIntYootheme.php'
        );
        self::assertStringContainsString('$this->isActive()', $src);
    }
}
