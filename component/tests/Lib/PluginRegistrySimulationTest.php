<?php

namespace AiBoost\Tests\Lib;

use AiBoost\Lib\PluginRegistry;
use Joomla\CMS\Factory;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Regression test for the license simulator.
 *
 * Background: Task #432's first code review caught a HIGH severity bug —
 * persisted `license_simulation` in #__aiboost_settings was being honored
 * by PluginRegistry::capabilities() even when Joomla debug mode (JDEBUG)
 * was OFF. The fix gated the simulator on `defined('JDEBUG') && JDEBUG === true`,
 * but had no automated regression test. A future refactor could silently
 * reintroduce the leak and turn every customer's "Expired" simulation
 * (used by support to reproduce bugs) into a production outage.
 *
 * Each test runs in a separate process so we can `define('JDEBUG', ...)`
 * once per scenario without polluting the others.
 *
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PluginRegistrySimulationTest extends TestCase
{
    /** Seed the fake DB with a simulation map that flips every SKU. */
    private function seedSimulation(): void
    {
        $sim = [
            // Pro SKUs.
            'schema'       => 'active',
            'og'           => 'expired',
            'hreflang'     => 'disabled',
            'code'         => 'not_licensed',
            'aeo'          => 'active',
            'bundle'       => 'active',
            // Integration SKUs.
            'int_falang'   => 'active',
            'int_yootheme' => 'expired',
        ];
        $json = json_encode(['license_simulation' => $sim]);
        Factory::setDbo(new FakeSimulationDatabase($json));
        PluginRegistry::reset();
    }

    public function testSimulatorIsHonoredWhenJdebugIsOn(): void
    {
        $this->defineJdebug(true);
        $this->seedSimulation();

        $caps = PluginRegistry::capabilities();

        // Pro SKU 'schema' simulated active → installed, enabled, marked simulated.
        $this->assertTrue($caps['pro_schema']['installed']);
        $this->assertTrue($caps['pro_schema']['enabled']);
        $this->assertTrue($caps['pro_schema']['simulated']);
        $this->assertSame('active', $caps['pro_schema']['license_state']);

        // 'og' simulated expired → installed but not enabled.
        $this->assertTrue($caps['pro_og']['installed']);
        $this->assertFalse($caps['pro_og']['enabled']);
        $this->assertSame('expired', $caps['pro_og']['license_state']);

        // 'code' simulated not_licensed → neither installed nor enabled.
        $this->assertFalse($caps['pro_code']['installed']);
        $this->assertFalse($caps['pro_code']['enabled']);
        $this->assertSame('not_licensed', $caps['pro_code']['license_state']);

        // Integration SKU 'int_falang' simulated active.
        $this->assertTrue($caps['int_falang']['installed']);
        $this->assertTrue($caps['int_falang']['enabled']);
        $this->assertTrue($caps['int_falang']['simulated']);
        $this->assertSame('active', $caps['int_falang']['license_state']);

        // Integration SKU 'int_yootheme' simulated expired.
        $this->assertFalse($caps['int_yootheme']['enabled']);
        $this->assertSame('expired', $caps['int_yootheme']['license_state']);

        // High-level helpers must agree with the capability map.
        $this->assertSame('active',   PluginRegistry::licenseStatus('schema'));
        $this->assertSame('expired',  PluginRegistry::licenseStatus('og'));
        $this->assertSame('active',   PluginRegistry::licenseStatus('int_falang'));
        $this->assertSame('expired',  PluginRegistry::licenseStatus('int_yootheme'));

        $this->assertTrue(PluginRegistry::hasPro('schema'));
        $this->assertFalse(PluginRegistry::hasPro('og'));
        $this->assertTrue(PluginRegistry::hasPro('int_falang'));
        $this->assertFalse(PluginRegistry::hasPro('int_yootheme'));
    }

    public function testSimulatorIsIgnoredWhenJdebugIsOff(): void
    {
        $this->defineJdebug(false);
        $this->seedSimulation();

        $caps = PluginRegistry::capabilities();

        // With no real plugin rows AND simulator suppressed, every Pro SKU
        // must report uninstalled / not_licensed regardless of the persisted
        // simulation map. This is the production safety contract.
        foreach (['schema', 'og', 'hreflang', 'code', 'aeo'] as $sku) {
            $this->assertFalse(
                $caps['pro_' . $sku]['installed'],
                "pro_{$sku} must NOT be installed when JDEBUG is off"
            );
            $this->assertFalse(
                $caps['pro_' . $sku]['enabled'],
                "pro_{$sku} must NOT be enabled when JDEBUG is off"
            );
            $this->assertFalse(
                $caps['pro_' . $sku]['simulated'],
                "pro_{$sku} must NOT be marked simulated when JDEBUG is off"
            );
            $this->assertSame(
                'not_licensed',
                $caps['pro_' . $sku]['license_state'],
                "pro_{$sku} license_state must be not_licensed when JDEBUG is off"
            );
        }

        // Virtual bundle SKU must also fall back to not_licensed.
        $this->assertFalse($caps['pro_bundle']['installed']);
        $this->assertFalse($caps['pro_bundle']['enabled']);
        $this->assertSame('not_licensed', $caps['pro_bundle']['license_state']);

        // Integration SKUs — explicitly required by the task.
        $this->assertFalse($caps['int_falang']['installed']);
        $this->assertFalse($caps['int_falang']['enabled']);
        $this->assertFalse($caps['int_falang']['simulated']);
        $this->assertSame('not_licensed', $caps['int_falang']['license_state']);

        $this->assertFalse($caps['int_yootheme']['enabled']);
        $this->assertSame('not_licensed', $caps['int_yootheme']['license_state']);

        // High-level helpers must also refuse to honor the simulation.
        foreach (['schema', 'og', 'hreflang', 'code', 'aeo'] as $sku) {
            $this->assertSame('not_licensed', PluginRegistry::licenseStatus($sku));
            $this->assertFalse(PluginRegistry::hasPro($sku));
        }
        $this->assertSame('not_licensed', PluginRegistry::licenseStatus('int_falang'));
        $this->assertSame('not_licensed', PluginRegistry::licenseStatus('int_yootheme'));
        $this->assertFalse(PluginRegistry::hasPro('int_falang'));
        $this->assertFalse(PluginRegistry::hasPro('int_yootheme'));

        // simulatedStatus() must also refuse to leak the persisted state.
        $this->assertNull(PluginRegistry::simulatedStatus('schema'));
        $this->assertNull(PluginRegistry::simulatedStatus('int_falang'));
    }

    private function defineJdebug(bool $enabled): void
    {
        if (!defined('JDEBUG')) {
            define('JDEBUG', $enabled);
        }
    }
}

/**
 * Fluent query stub that simply records nothing and returns $this for every
 * builder method PluginRegistry happens to call.
 */
final class FakeQuery
{
    public function __call(string $name, array $args): self
    {
        return $this;
    }
}

/**
 * Minimal fake DB that returns a fixed settings JSON for loadResult() and an
 * empty list for loadAssocList() (i.e. no real plugin rows installed).
 */
final class FakeSimulationDatabase implements \Joomla\Database\DatabaseInterface
{
    public function __construct(private string $settingsJson) {}

    public function loadObjectList(?string $key = null): array
    {
        return [];
    }

    public function insertObject(string $table, object &$object, ?string $key = null): bool
    {
        return true;
    }

    public function updateObject(string $table, object &$object, mixed $key, bool $nulls = false): bool
    {
        return true;
    }

    public function getQuery(bool $new = false): object
    {
        return new FakeQuery();
    }

    public function setQuery(object $query, int $offset = 0, int $limit = 0): static
    {
        return $this;
    }

    public function loadResult(): mixed
    {
        return $this->settingsJson;
    }

    public function loadAssocList(?string $key = null): array
    {
        return [];
    }

    public function quote(mixed $text, bool $escape = true): string
    {
        return "'" . (string) $text . "'";
    }

    public function quoteName(mixed $name, mixed $as = null): mixed
    {
        return is_array($name) ? array_map(fn($n) => "`$n`", $name) : "`$name`";
    }

    public function execute(): bool
    {
        return true;
    }
}
