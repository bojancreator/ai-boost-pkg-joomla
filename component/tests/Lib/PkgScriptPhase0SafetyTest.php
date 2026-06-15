<?php

namespace AiBoost\Tests\Lib;

use PHPUnit\Framework\TestCase;

/**
 * Phase 0 safety-net guards for pkg_script.php (the "Pro replaces Free" work).
 *
 * The collapse phases multiply how many install/uninstall cycles a customer DB
 * will see, so these invariants must hold BEFORE any layout change ships:
 *
 *  - postflight() must NEVER act on $type='uninstall'. Joomla calls
 *    postflight('uninstall') AFTER uninstall(); without an early return the
 *    table-creation + settings migrations + plugin re-enable all re-run over a
 *    half-removed filesystem and rewrite the customer's settings blob. (B1)
 *  - every settings_json writer must go through the hardened encoder so a stray
 *    bad byte / empty array can never write '' or false into the NOT NULL
 *    column (which a later install reads as "no settings" and wipes). (M3)
 *  - the Free-only artifact cleanup must be vetoed when a live Pro plugin is
 *    present, and the new edition marker + pre-upgrade backup helpers exist.
 */
final class PkgScriptPhase0SafetyTest extends TestCase
{
    private const SCRIPT_PATH = __DIR__ . '/../../package/pkg_script.php';

    public static function setUpBeforeClass(): void
    {
        // Plain (unnamespaced) Joomla installer script, no load-time side
        // effects — requiring it only defines the class. _JEXEC + the
        // Joomla\CMS\Factory stub come from the test bootstrap.
        require_once self::SCRIPT_PATH;
    }

    protected function tearDown(): void
    {
        \Joomla\CMS\Factory::setDbo(null);
    }

    /**
     * Functional proof of B1: with a DB that flags any access, postflight on an
     * uninstall type returns before touching the database at all.
     */
    public function testPostflightDoesNotTouchDbOnUninstall(): void
    {
        $fakeDb = new class {
            public bool $touched = false;
            public function __call($name, $args)
            {
                $this->touched = true;
                return $this; // chainable so a regressed (guard-removed) path still runs
            }
            public function __toString(): string
            {
                $this->touched = true;
                return '';
            }
        };
        \Joomla\CMS\Factory::setDbo($fakeDb);

        $script = new \Pkg_AiboostInstallerScript();

        foreach (['uninstall', 'discover_uninstall'] as $type) {
            $fakeDb->touched = false;
            $script->postflight($type, new \stdClass());
            $this->assertFalse(
                $fakeDb->touched,
                "postflight('$type') must return before any DB access — it must "
                . 'never re-run migrations / table-creation on an uninstall.'
            );
        }
    }

    /**
     * Source guard: the uninstall early-return is the FIRST thing postflight
     * does — before ensureNewTables() and before the settings backup — so no
     * regression can reorder a DB-touching call ahead of it.
     */
    public function testUninstallGuardIsFirstStatementInPostflight(): void
    {
        $src  = (string) file_get_contents(self::SCRIPT_PATH);
        $body = $this->methodBody($src, 'postflight');

        $guardPos  = strpos($body, "if (!in_array(\$type, ['install', 'update', 'discover_install'], true)) {");
        $this->assertNotFalse($guardPos, 'postflight() must early-return on non-install/update types.');

        $tablesPos = strpos($body, '$this->ensureNewTables();');
        $backupPos = strpos($body, '$this->backupMainSettings();');
        $this->assertNotFalse($tablesPos);
        $this->assertNotFalse($backupPos);
        $this->assertLessThan($tablesPos, $guardPos, 'the uninstall guard must precede ensureNewTables().');
        $this->assertLessThan($backupPos, $guardPos, 'the uninstall guard must precede the settings backup.');
    }

    /**
     * M3: no settings writer may pass a naked json_encode($data) into a quoted
     * UPDATE/INSERT, and the hardened encoder must exist with the safety flags.
     */
    public function testAllSettingsWritersUseTheHardenedEncoder(): void
    {
        $src = (string) file_get_contents(self::SCRIPT_PATH);

        $this->assertStringNotContainsString(
            'quote(json_encode($data))',
            $src,
            'a naked json_encode($data) into the settings_json column can write '
            . '"" / false into a NOT NULL column — route every write through '
            . 'encodeSettingsBlobSafe() instead.'
        );
        $this->assertStringContainsString('private function encodeSettingsBlobSafe(array $data): ?string', $src);
        $this->assertStringContainsString('JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR', $src);
    }

    /**
     * The Phase 0 helpers + the live-Pro veto are present and wired.
     */
    public function testPhase0HelpersExistAndVetoIsWired(): void
    {
        $src = (string) file_get_contents(self::SCRIPT_PATH);

        $this->assertStringContainsString('private function backupMainSettings(): void', $src);
        $this->assertStringContainsString("'main_backup_' . \$version", $src);
        $this->assertStringContainsString('private function setInstalledEdition(bool $isProBuild): void', $src);
        $this->assertStringContainsString("\$data['pro_installed'] = \$isProBuild ? '1' : '0';", $src);
        $this->assertStringContainsString('private function hasLiveProExtension(): bool', $src);

        // The veto is the first thing the Free-only cleanup checks.
        $cleanup = $this->methodBody($src, 'removeLegacySplitPackageArtifacts');
        $vetoPos    = strpos($cleanup, 'if ($this->hasLiveProExtension()) {');
        $deletePos  = strpos($cleanup, '$legacyPlugins = [');
        $this->assertNotFalse($vetoPos, 'removeLegacySplitPackageArtifacts() must veto on a live Pro plugin.');
        $this->assertNotFalse($deletePos);
        $this->assertLessThan($deletePos, $vetoPos, 'the veto must run before the legacy-plugin removal list.');
    }

    /**
     * Functional proof of 0.3: backupMainSettings() copies the live `main` blob
     * into a `main_backup_<version>` row and NEVER updates the `main` row.
     */
    public function testBackupMainSettingsInsertsBackupRowAndNeverTouchesMain(): void
    {
        // A fake that is both the connection and the query builder: builder
        // methods chain on $this; reads pop a queued result list in call order.
        $db = new class {
            /** @var list<array{0:string,1:array}> */
            public array $calls = [];
            /** @var list<mixed> */
            public array $results = [];
            public function getPrefix(): string { return 'jos_'; }
            public function quote($s, $e = true): string { return "'" . $s . "'"; }
            public function quoteName($n, $a = null): string { return '`' . $n . '`'; }
            public function getQuery($new = false): object { $this->calls[] = ['getQuery', []]; return $this; }
            public function setQuery($q, $o = 0, $l = 0): object { $this->calls[] = ['setQuery', [$q]]; return $this; }
            public function loadColumn() { $this->calls[] = ['loadColumn', []]; return array_shift($this->results); }
            public function loadResult() { $this->calls[] = ['loadResult', []]; return array_shift($this->results); }
            public function execute(): bool { $this->calls[] = ['execute', []]; return true; }
            public function __call($name, $args): object { $this->calls[] = [$name, $args]; return $this; }
        };
        // Consumption order: SHOW TABLES → main blob → COUNT(backup) → prune ids.
        $db->results = [
            ['jos_aiboost_settings'],            // table exists
            '{"org_name":"Keep","pro_activated":"1"}', // the live main blob
            0,                                   // no existing backup row yet
            [42],                                // one backup id after insert → no prune
        ];
        \Joomla\CMS\Factory::setDbo($db);

        $script = new \Pkg_AiboostInstallerScript();
        $m = new \ReflectionMethod(\Pkg_AiboostInstallerScript::class, 'backupMainSettings');
        if (PHP_VERSION_ID < 80500) {
            $m->setAccessible(true);
        }
        $m->invoke($script);

        // An INSERT carrying a main_backup_* key + the verbatim blob was issued.
        $values = array_values(array_filter(
            $db->calls,
            static fn ($c) => $c[0] === 'values'
        ));
        $this->assertNotEmpty($values, 'backupMainSettings() must INSERT a backup row.');
        $valueSql = (string) ($values[0][1][0] ?? '');
        $this->assertStringContainsString('main_backup_', $valueSql, 'the backup row key must be main_backup_<version>.');
        $this->assertStringContainsString('org_name', $valueSql, 'the backup must copy the live blob verbatim.');

        // The INSERT must include created_at/updated_at (DATETIME NOT NULL, no
        // default) — a 2-column INSERT throws under strict sql_mode and silently
        // disables the backup safety net.
        $columns = array_values(array_filter($db->calls, static fn ($c) => $c[0] === 'columns'));
        $this->assertNotEmpty($columns, 'backupMainSettings() must declare its INSERT columns.');
        $colList = (array) ($columns[0][1][0] ?? []);
        $this->assertContains('`created_at`', $colList, 'INSERT must set created_at (NOT NULL, no default).');
        $this->assertContains('`updated_at`', $colList, 'INSERT must set updated_at (NOT NULL, no default).');

        // It must NEVER UPDATE the main settings row.
        $updates = array_filter($db->calls, static fn ($c) => $c[0] === 'update');
        $this->assertSame([], array_values($updates), 'backupMainSettings() must never UPDATE — only the sibling backup row is inserted.');
    }

    /**
     * Extract a method body by brace-matching from its signature. Good enough
     * for ordering assertions on this single, well-formed source file.
     */
    private function methodBody(string $src, string $method): string
    {
        $sigPos = strpos($src, 'function ' . $method . '(');
        $this->assertNotFalse($sigPos, "method $method() not found in pkg_script.php");
        $bracePos = strpos($src, '{', $sigPos);
        $depth = 0;
        for ($i = $bracePos, $n = strlen($src); $i < $n; $i++) {
            if ($src[$i] === '{') {
                $depth++;
            } elseif ($src[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($src, $bracePos, $i - $bracePos + 1);
                }
            }
        }
        $this->fail("could not brace-match $method()");
    }
}
