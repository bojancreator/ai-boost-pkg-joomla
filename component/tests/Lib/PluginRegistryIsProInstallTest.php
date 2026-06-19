<?php

namespace AiBoost\Tests\Lib;

use AiBoost\Lib\PluginRegistry;
use Joomla\CMS\Factory;
use PHPUnit\Framework\TestCase;

/**
 * Phase 5a — PluginRegistry::isProInstall() is the single source of truth the
 * admin views use to unlock the Pro UI (ProGate / the Licenses surface). It must
 * survive the single-plugin collapse, when the Pro package shares the
 * `pkg_aiboost` element with Free and the package element alone can no longer
 * distinguish the edition. True on: the `pro_installed` install marker, a live
 * activation, OR a legacy split layout; false only on genuine Free.
 *
 * These cases exercise the marker + activation branches (the new logic), which
 * short-circuit before the #__extensions presence query. The genuine-Free case
 * falls through to the count, which the fake resolves to 0.
 */
final class PluginRegistryIsProInstallTest extends TestCase
{
    private function withSettings(string $json): void
    {
        Factory::setDbo(new FakeProInstallDatabase($json));
        PluginRegistry::reset();
    }

    protected function tearDown(): void
    {
        Factory::setDbo(null);
        PluginRegistry::reset();
    }

    public function testTrueWhenInstallMarkerSet(): void
    {
        $this->withSettings('{"pro_installed":"1"}');
        $this->assertTrue(
            PluginRegistry::isProInstall(),
            'the pro_installed marker is the signal that survives the collapse.'
        );
    }

    public function testTrueWhenActivated(): void
    {
        $this->withSettings('{"pro_activated":"1"}');
        $this->assertTrue(PluginRegistry::isProInstall());
    }

    public function testRemovedDevPreviewDoesNotCountAsProInstall(): void
    {
        // The legacy dev_license_preview override was removed from the shipping
        // product, so it no longer marks a Pro install. With no marker, no
        // activation and the presence COUNT resolving to 0, this is Free.
        $this->withSettings('{"dev_license_preview":"1"}');
        $this->assertFalse(PluginRegistry::isProInstall());
    }

    public function testFalseOnGenuineFree(): void
    {
        // No marker, not active; the presence COUNT resolves to 0 → Free.
        $this->withSettings('{}');
        $this->assertFalse(PluginRegistry::isProInstall());
    }

    public function testMarkerKeepsLicensesReachableEvenWithoutActivation(): void
    {
        // A paying customer's Licenses surface must stay reachable on a Pro
        // install (pro_installed marker) even before a key is entered.
        $this->withSettings('{"pro_installed":"1"}');
        $this->assertTrue(PluginRegistry::isProInstall());
    }
}

/**
 * Minimal DatabaseInterface fake: loadResult() returns the seeded settings JSON
 * for loadMainSettings(); the presence COUNT reads the same value, which casts
 * to int 0 for a non-numeric blob → "no split layout present".
 */
final class FakeProInstallDatabase implements \Joomla\Database\DatabaseInterface
{
    public function __construct(private string $settingsJson)
    {
    }

    public function getQuery(bool $new = false): object
    {
        return new class {
            public function __call($name, $args)
            {
                return $this;
            }
        };
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

    public function loadObjectList(?string $key = null): array
    {
        return [];
    }

    public function quote(mixed $text, bool $escape = true): string
    {
        return "'" . (string) $text . "'";
    }

    public function quoteName(mixed $name, mixed $as = null): mixed
    {
        return is_array($name) ? array_map(fn ($n) => "`$n`", $name) : "`$name`";
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
