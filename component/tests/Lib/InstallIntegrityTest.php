<?php

namespace AiBoost\Tests\Lib;

use AiBoost\Lib\InstallIntegrity;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for the installation integrity audit (Task #527).
 *
 * The audit classifies every AI Boost extension as ok / missing / disabled /
 * orphan / mismatch for the current Free-vs-Pro edition. A silent change to the
 * expected plugin sets, the edition logic, or the prefix-scan would
 * misclassify a perfectly healthy install and trip a Health warning on
 * customers. These table-driven scenarios pin the behaviour so any such drift
 * fails CI loudly.
 *
 * Edition is passed to audit() explicitly (the live caller derives it from
 * isProEdition()), so each scenario seeds the #__extensions rows the audit
 * scans via a fake DatabaseInterface and asserts the resulting buckets.
 */
final class InstallIntegrityTest extends TestCase
{
    private const VERSION = '0.6.0';

    /**
     * Build one #__extensions row in the shape scanRows() consumes.
     *
     * @return array<string,mixed>
     */
    private function row(
        string $element,
        string $type = 'plugin',
        int $enabled = 1,
        ?string $version = self::VERSION,
        string $folder = 'system'
    ): array {
        return [
            'element'        => $element,
            'type'           => $type,
            'folder'         => $folder,
            'enabled'        => $enabled,
            'manifest_cache' => $version === null ? '{}' : json_encode(['version' => $version]),
        ];
    }

    /**
        * Component row, present and on-version, that every edition expects.
        * Scenarios that only care about plugins prepend this so they never produce
        * spurious "missing" noise.
     *
     * @return list<array<string,mixed>>
     */
    private function coreRows(): array
    {
        return [
            $this->row(InstallIntegrity::COMPONENT_ELEMENT, 'component', 1, self::VERSION, ''),
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function proModuleRows(): array
    {
        return [
            $this->row(InstallIntegrity::MODULE_ELEMENT, 'module', 1, self::VERSION, ''),
        ];
    }

    /**
     * Every Free system plugin, installed + enabled + on-version.
     *
     * @return list<array<string,mixed>>
     */
    private function freePluginRows(): array
    {
        return array_map(fn (string $el): array => $this->row($el), InstallIntegrity::FREE_SYSTEM_PLUGINS);
    }

    /**
     * Every Pro system plugin, installed + enabled + on-version.
     *
     * @return list<array<string,mixed>>
     */
    private function proPluginRows(): array
    {
        return array_map(fn (string $el): array => $this->row($el), InstallIntegrity::PRO_SYSTEM_PLUGINS);
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private function db(array $rows, int $proPackageCount = 0): FakeExtensionsDatabase
    {
        return new FakeExtensionsDatabase($rows, $proPackageCount);
    }

    public function testCleanFreeBaselineReportsNoProblems(): void
    {
        $db    = $this->db(array_merge($this->coreRows(), $this->freePluginRows()));
        $audit = InstallIntegrity::audit($db, false, self::VERSION);

        $this->assertSame('Free', $audit['edition']);
        $this->assertSame([], $audit['missing']);
        $this->assertSame([], $audit['disabled']);
        $this->assertSame([], $audit['orphan']);
        $this->assertSame([], $audit['mismatch']);
        // Free expects 7 plugins + component.
        $this->assertSame(
            count(InstallIntegrity::FREE_SYSTEM_PLUGINS) + 1,
            $audit['active_count']
        );
        $this->assertSame($audit['expected_count'], $audit['active_count']);
    }

    public function testCleanProBaselineReportsNoProblems(): void
    {
        // The Health module (proModuleRows) is seeded as a LEFTOVER: as of
        // v0.76.6 it is pulled from the product, so it is no longer expected.
        // The audit must silently ignore a stray module row — neither count it
        // (not "ok"), nor flag it ("missing"/"orphan").
        $rows  = array_merge($this->coreRows(), $this->proModuleRows(), $this->freePluginRows(), $this->proPluginRows());
        $db    = $this->db($rows);
        $audit = InstallIntegrity::audit($db, true, self::VERSION);

        $this->assertSame('Pro', $audit['edition']);
        $this->assertSame([], $audit['missing']);
        $this->assertSame([], $audit['disabled']);
        $this->assertSame([], $audit['orphan']);
        $this->assertSame([], $audit['mismatch']);
        // Pro now expects only the Free + Pro plugins + the component (the
        // module is no longer part of the count).
        $this->assertSame(
            count(InstallIntegrity::FREE_SYSTEM_PLUGINS) + count(InstallIntegrity::PRO_SYSTEM_PLUGINS) + 1,
            $audit['active_count']
        );
        $this->assertSame($audit['expected_count'], $audit['active_count']);
    }

    public function testProPluginOnFreePackageIsFlaggedAsOrphan(): void
    {
        // A *_pro plugin physically present while the edition is Free: it is
        // installed but not expected, so it must be an orphan (not ok, not
        // missing). The other Free plugins stay clean.
        $rows  = array_merge(
            $this->coreRows(),
            $this->freePluginRows(),
            [$this->row('aiboost_schema_pro')]
        );
        $audit = InstallIntegrity::audit($this->db($rows), false, self::VERSION);

        $this->assertContains('aiboost_schema_pro', $audit['orphan']);
        $this->assertNotContains('aiboost_schema_pro', $audit['ok']);
        $this->assertSame([], $audit['missing']);
        $this->assertSame([], $audit['disabled']);
    }

    public function testIntegrationBridgesAreExcludedFromOrphanDetection(): void
    {
        // aiboost_int_* bridges are sold separately and may legitimately be
        // installed on any edition; they must never be reported as orphans.
        $rows  = array_merge(
            $this->coreRows(),
            $this->freePluginRows(),
            [
                $this->row('aiboost_int_falang'),
                $this->row('aiboost_int_yootheme', 'plugin', 0), // even disabled bridges are ignored
            ]
        );
        $audit = InstallIntegrity::audit($this->db($rows), false, self::VERSION);

        $this->assertNotContains('aiboost_int_falang', $audit['orphan']);
        $this->assertNotContains('aiboost_int_yootheme', $audit['orphan']);
        $this->assertSame([], $audit['orphan']);
    }

    public function testDisabledExpectedPluginIsFlaggedAsDisabled(): void
    {
        $rows = $this->coreRows();
        foreach (InstallIntegrity::FREE_SYSTEM_PLUGINS as $el) {
            // Disable exactly one expected plugin; the rest stay enabled.
            $rows[] = $this->row($el, 'plugin', $el === 'aiboost_aeo' ? 0 : 1);
        }
        $audit = InstallIntegrity::audit($this->db($rows), false, self::VERSION);

        $this->assertContains('aiboost_aeo', $audit['disabled']);
        $this->assertNotContains('aiboost_aeo', $audit['ok']);
        $this->assertNotContains('aiboost_aeo', $audit['orphan']);
        $this->assertSame([], $audit['missing']);
    }

    public function testVersionMismatchIsDetected(): void
    {
        $rows = $this->coreRows();
        foreach (InstallIntegrity::FREE_SYSTEM_PLUGINS as $el) {
            // One plugin lags a version behind the package.
            $rows[] = $this->row($el, 'plugin', 1, $el === 'aiboost_sitemap' ? '0.5.9' : self::VERSION);
        }
        $audit = InstallIntegrity::audit($this->db($rows), false, self::VERSION);

        $this->assertCount(1, $audit['mismatch']);
        $this->assertSame('aiboost_sitemap', $audit['mismatch'][0]['element']);
        $this->assertSame('0.5.9', $audit['mismatch'][0]['version']);
        $this->assertNotContains('aiboost_sitemap', $audit['ok']);
    }

    public function testMissingExpectedPluginIsFlaggedAsMissing(): void
    {
        // Drop one Free plugin entirely (no #__extensions row).
        $rows = $this->coreRows();
        foreach (InstallIntegrity::FREE_SYSTEM_PLUGINS as $el) {
            if ($el === 'aiboost_social') {
                continue;
            }
            $rows[] = $this->row($el);
        }
        $audit = InstallIntegrity::audit($this->db($rows), false, self::VERSION);

        $this->assertContains('aiboost_social', $audit['missing']);
        $this->assertSame([], $audit['disabled']);
        $this->assertSame([], $audit['orphan']);
    }

    public function testNonAiBoostSystemPluginsAreIgnoredByPrefixScan(): void
    {
        // The scan filters the `aiboost_` prefix in PHP (str_starts_with), NOT
        // via a SQL LIKE, so the broad #__extensions query may legitimately
        // return core/third-party system plugins. None of those may leak into
        // the orphan bucket. Seeding them proves the PHP-side prefix filter is
        // what gates inclusion.
        $rows = array_merge(
            $this->coreRows(),
            $this->freePluginRows(),
            [
                $this->row('webauthn'),       // Joomla core system plugin
                $this->row('actionlogs'),     // Joomla core system plugin
                $this->row('jchoptimize'),    // third-party
                // A name that merely contains, but does not start with, the
                // prefix must also be ignored — proving it is a prefix test.
                $this->row('not_aiboost_thing'),
            ]
        );
        $audit = InstallIntegrity::audit($this->db($rows), false, self::VERSION);

        $this->assertSame([], $audit['orphan']);
        $this->assertSame([], $audit['missing']);
        $this->assertSame([], $audit['disabled']);
        // The aiboost_ prefixed plugins are still picked up and counted ok.
        foreach (InstallIntegrity::FREE_SYSTEM_PLUGINS as $el) {
            $this->assertContains($el, $audit['ok']);
        }
    }

    public function testIsProEditionTrueWhenPackageRowPresent(): void
    {
        $this->assertTrue(InstallIntegrity::isProEdition($this->db([], 1)));
    }

    public function testIsProEditionFalseWhenNoPackageRow(): void
    {
        $this->assertFalse(InstallIntegrity::isProEdition($this->db([], 0)));
    }
}

/**
 * Minimal DatabaseInterface fake: scanRows() reads loadAssocList() (the
 * #__extensions rows) and isProEdition() reads loadResult() (the package-row
 * COUNT). The query builder is a no-op since the audit filters in PHP.
 */
final class FakeExtensionsDatabase implements \Joomla\Database\DatabaseInterface
{
    /**
     * @param list<array<string,mixed>> $rows
     */
    public function __construct(private array $rows, private int $proPackageCount = 0) {}

    public function getQuery(bool $new = false): object
    {
        return new FakeExtensionsQuery();
    }

    public function setQuery(object $query, int $offset = 0, int $limit = 0): static
    {
        return $this;
    }

    public function loadResult(): mixed
    {
        return $this->proPackageCount;
    }

    public function loadAssocList(?string $key = null): array
    {
        return $this->rows;
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

final class FakeExtensionsQuery
{
    public function __call(string $name, array $args): self
    {
        return $this;
    }
}
