<?php

declare(strict_types=1);

namespace AiBoost\Tests\Lib;

use AiBoost\Component\AiBoost\Administrator\Controller\SettingsController;
use AiBoost\Lib\PluginRegistry;
use Joomla\CMS\Factory;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/stubs/JoomlaMvcController.php';
require_once dirname(__DIR__, 2) . '/com_aiboost/admin/src/Administrator/Controller/SettingsController.php';

/**
 * Regression tests for the JDEBUG licence-mock path.
 *
 * Background: with Joomla debug mode on, entering an AB-VALID-* key used to
 * flow through PluginRegistry::saveLicenseState(), which calls
 * markPerpetualActivation() — permanently setting `pro_activated='1'` and
 * unlocking Pro FOR FREE, even after debug mode was turned off
 * (pro_activated is by design never cleared).
 *
 * The fix routes mock results through the existing license_simulation
 * machinery instead (SettingsController::verifyLicense() →
 * PluginRegistry::saveSimulation()): the simulator is only honoured while
 * JDEBUG is on, so mock Pro behaves like dev preview (ephemeral), never like
 * a real activation. These tests prove markPerpetualActivation() is
 * unreachable from the mock flow:
 *
 *   1. the mock branch only ever produces simulator states, and
 *   2. saveSimulation() never writes pro_activated / license_tier /
 *      license_state — while saveLicenseState() with an active state does
 *      (the contrast that makes the routing decision load-bearing).
 */
final class LicenseMockPathTest extends TestCase
{
    protected function setUp(): void
    {
        \AiBoost\Lib\Cms\AdapterRegistry::reset();
        PluginRegistry::reset();
    }

    protected function tearDown(): void
    {
        Factory::setDbo(null);
        \AiBoost\Lib\Cms\AdapterRegistry::reset();
        PluginRegistry::reset();
    }

    public function testMockStatusesOnlyEverMapToSimulatorStates(): void
    {
        $expected = [
            'active'        => 'active',
            'expired'       => 'expired',
            'limit_reached' => 'expired',
            'deactivated'   => 'disabled',
            'invalid'       => 'not_licensed',
        ];

        foreach ($expected as $mockStatus => $simState) {
            $mapped = SettingsController::mockSimulationState(['status' => $mockStatus]);
            $this->assertSame($simState, $mapped, "mock status '{$mockStatus}' must map to '{$simState}'");
            $this->assertContains($mapped, PluginRegistry::SIM_STATES);
        }

        // Unknown / missing statuses fail closed to not_licensed.
        $this->assertSame('not_licensed', SettingsController::mockSimulationState(['status' => 'whatever']));
        $this->assertSame('not_licensed', SettingsController::mockSimulationState([]));
    }

    public function testSaveSimulationNeverTouchesActivationOrLicenseState(): void
    {
        // Existing row of a never-activated install with an old expired
        // licence record — the worst case for an accidental promotion.
        $existing = [
            'org_name'      => 'Free Site',
            'license_state' => ['bundle' => ['key' => 'OLD', 'status' => 'expired']],
        ];
        $db = new MockPathCapturingDatabase([json_encode($existing), '1']);
        Factory::setDbo($db);
        PluginRegistry::reset();

        // What verifyLicense() now does for AB-VALID-* under JDEBUG.
        PluginRegistry::saveSimulation(['bundle' => 'active']);

        $persisted = end($db->persistedSettings);
        $this->assertIsArray($persisted, 'saveSimulation must persist the settings row');

        // The simulation itself lands ...
        $this->assertSame('active', $persisted['license_simulation']['bundle'] ?? null);

        // ... but NOTHING activation-related is written: the perpetual
        // activation flag stays absent and the real licence record untouched.
        $this->assertArrayNotHasKey('pro_activated', $persisted);
        $this->assertArrayNotHasKey('pro_activated_at', $persisted);
        $this->assertArrayNotHasKey('pro_activated_version', $persisted);
        $this->assertArrayNotHasKey('license_tier', $persisted);
        $this->assertSame('expired', $persisted['license_state']['bundle']['status']);

        // And the canonical Pro gate still reports Free for that row.
        $this->assertFalse(PluginRegistry::isProActive($persisted));
    }

    public function testSaveLicenseStateWithActiveKeyDoesMarkPerpetualActivation(): void
    {
        // Contrast case: the REAL Lemon Squeezy path must keep activating —
        // this is the call the mock flow may never reach.
        $db = new MockPathCapturingDatabase([json_encode(['org_name' => 'Site']), '1']);
        Factory::setDbo($db);
        PluginRegistry::reset();

        PluginRegistry::saveLicenseState('bundle', ['key' => 'LS-REAL-KEY', 'status' => 'active']);

        $persisted = end($db->persistedSettings);
        $this->assertIsArray($persisted);
        $this->assertSame('1', $persisted['pro_activated']);
        $this->assertSame('pro', $persisted['license_tier']);
        $this->assertTrue(PluginRegistry::isProActive($persisted));
    }

    public function testVerifyLicenseRoutesMockKeysThroughTheSimulatorOnly(): void
    {
        // Structural guard on the controller wiring: mock keys branch into
        // saveSimulation(); saveLicenseState() sits in the else branch and is
        // therefore unreachable for AB-* keys.
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/com_aiboost/admin/src/Administrator/Controller/SettingsController.php'
        );

        $this->assertIsString($source);
        // Normalise line endings so the multi-line assertion below is
        // checkout-independent (the repo may materialise CRLF on Windows).
        $source = str_replace("\r\n", "\n", $source);
        $this->assertStringContainsString('if ($this->isMockLicenseKey($key)) {', $source);
        $this->assertStringContainsString('$sim[$sku] = self::mockSimulationState($state);', $source);
        $this->assertStringContainsString('PluginRegistry::saveSimulation($sim);', $source);
        $this->assertStringContainsString(
            "} else {\n                PluginRegistry::saveLicenseState(\$sku, \$state);\n            }",
            $source,
            'saveLicenseState must only be reachable through the non-mock branch.'
        );
    }
}

/**
 * Fluent query stub — returns $this for every builder method PluginRegistry
 * calls while assembling its select/update queries.
 */
final class MockPathFakeQuery
{
    public function __call(string $name, array $args): self
    {
        return $this;
    }
}

/**
 * Minimal fake DB for the PluginRegistry persistence flows. loadResult()
 * returns queued values in order (settings JSON first, then the row id) and
 * quote() captures every JSON blob on its way into the UPDATE statement so
 * tests can assert exactly what would be persisted.
 */
final class MockPathCapturingDatabase implements \Joomla\Database\DatabaseInterface
{
    /** @var array<int,array<string,mixed>> Decoded settings blobs passed to quote(). */
    public array $persistedSettings = [];

    /** @param array<int,mixed> $results FIFO queue of loadResult() returns. */
    public function __construct(private array $results) {}

    public function loadResult(): mixed
    {
        return array_shift($this->results);
    }

    public function quote(mixed $text, bool $escape = true): string
    {
        if (is_string($text) && str_starts_with(ltrim($text), '{')) {
            $decoded = json_decode($text, true);
            if (is_array($decoded)) {
                $this->persistedSettings[] = $decoded;
            }
        }
        return "'" . (string) $text . "'";
    }

    public function getQuery(bool $new = false): object
    {
        return new MockPathFakeQuery();
    }

    public function setQuery(object $query, int $offset = 0, int $limit = 0): static
    {
        return $this;
    }

    public function loadAssocList(?string $key = null): array
    {
        return [];
    }

    public function loadObjectList(?string $key = null): array
    {
        return [];
    }

    public function quoteName(mixed $name, mixed $as = null): mixed
    {
        return is_array($name) ? array_map(fn($n) => "`$n`", $name) : "`$name`";
    }

    public function execute(): bool
    {
        return true;
    }

    public function insertObject(string $table, object &$object, ?string $key = null): bool
    {
        return true;
    }

    public function updateObject(string $table, object &$object, mixed $key, bool $nulls = false): bool
    {
        return true;
    }
}
