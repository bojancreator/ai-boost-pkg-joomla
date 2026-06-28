<?php

declare(strict_types=1);

namespace AiBoost\Tests\Lib;

use AiBoost\Lib\SettingsSaveDefinition;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * #16 — the #__aiboost_settings 'main' row is ONE JSON blob, so any writer that
 * REPLACES it with a subset silently wipes every key it left out. This test
 * locks the two safe shapes that ship today, as an enforced project rule:
 *
 *   - read-modify-write : load the whole blob, change only your key(s), write
 *                         the whole blob back; or
 *   - full snapshot     : rebuild the whole blob from a payload that already
 *                         carries every key (SettingsController::save — the Vue
 *                         SPA always posts the complete settings snapshot).
 *
 * The forbidden anti-pattern is a PARTIAL replace: build a fresh array holding
 * only the changed key(s) and write it. SettingsPersistenceService::saveSettings()
 * is exactly that shape and survives ONLY as the documented "what not to do"
 * marker — it has NO production caller. BACKLOG (post-1.0): delete it so the
 * dormant mine physically disappears, and harden SettingsController::save() to
 * merge-on-existing.
 *
 * The finding behind this test: every live writer is already read-modify-write
 * or full snapshot (audited 2026-06-24). pkg_script.php even carries a HARD
 * SAFETY guard against an "original bug" that once silently wiped all user data.
 * So this changes NO behaviour — it freezes what already ships so a future
 * regression (a new "save one field" endpoint that forgets to load first, or a
 * writer that drops to subset-replace) fails CI instead of wiping users'
 * settings. It elevates the hand-written read-modify-write warning in
 * IntegrationsController::saveToggle()/saveOptions() into a discoverable rule.
 *
 * Red-green: break any 'rmw' writer to write without loading first and
 * testEveryReadModifyWriteWriterLoadsBeforeItWrites() goes red.
 */
final class SettingsWriterRmwContractTest extends TestCase
{
    /** component/ root, relative to this test (component/tests/Lib/). */
    private const COMPONENT = __DIR__ . '/../../';

    /**
     * Every writer of the settings 'main' blob, hand-verified against source on
     * 2026-06-24. Path (relative to component/) => [ writer method => shape ].
     * A NEW writer FILE makes testEverySettingsBlobWriterIsClassified() fail
     * until it is added here with its shape.
     *
     * Shapes:
     *   'rmw'         loads settings_json then writes it, in the SAME method
     *   'helper'      pure writer; receives the whole blob, the CALLER loads it
     *   'snapshot'    rebuilds the whole blob from a full posted snapshot
     *   'install_rmw' install/migration script; loads then writes the whole blob
     *   'import'      deliberate full restore from a backup file
     *   'dead'        subset-replace writer with NO production caller (anti-pattern)
     *
     * Only 'rmw' methods are structurally load-before-write checked below; the
     * others are verified by reading + frozen by the discovery guard. (For the
     * 'helper' writers the loader lives in the caller: ConflictsController::
     * savePolicy, PluginRegistry::saveLicenseState, LicenseReconcile's caller.)
     *
     * @var array<string, array<string,string>>
     */
    private const WRITERS = [
        'com_aiboost/admin/src/Administrator/Controller/SettingsController.php'    => ['export' => 'rmw', 'save' => 'snapshot'],
        'com_aiboost/admin/src/Administrator/Controller/IntegrationsController.php' => ['saveToggle' => 'rmw', 'saveOptions' => 'rmw'],
        'com_aiboost/admin/src/Administrator/Controller/DashboardController.php'   => ['markVersionSeen' => 'rmw'],
        'com_aiboost/admin/src/Administrator/Controller/HealthController.php'      => ['dismiss' => 'rmw'],
        'com_aiboost/admin/src/Administrator/Controller/AnalyzerController.php'    => ['applyFix' => 'rmw'],
        'com_aiboost/admin/src/Administrator/Controller/UrlcheckerController.php'  => ['upsertCanonicalOverride' => 'rmw'],
        'com_aiboost/admin/src/Administrator/Controller/ConflictsController.php'   => ['writeSettings' => 'helper'],
        'com_aiboost/admin/src/Administrator/Controller/ImportController.php'      => ['upload' => 'import'],
        'lib/src/PluginRegistry.php'                                               => ['updateMainSettingsQuery' => 'helper'],
        'lib/src/LicenseHeartbeat.php'                                             => ['persistVerdict' => 'rmw'],
        'lib/src/LicenseReconcile.php'                                             => ['persist' => 'helper'],
        'lib/src/SettingsPersistenceService.php'                                   => ['saveSettings' => 'dead'],
        'package/pkg_script.php'                                                   => ['*' => 'install_rmw'],
        'plugins/system/aiboost_int_falang/script.php'                             => ['*' => 'install_rmw'],
        'plugins/system/aiboost_int_yootheme/script.php'                           => ['*' => 'install_rmw'],
    ];

    // ── 1. The rule, as pure semantics ──────────────────────────────────────

    public function testReadModifyWriteKeepsSiblingsWhileSubsetReplaceWipesThem(): void
    {
        $existing = [
            'org_name'         => 'Acme',
            'title_separator'  => '-',
            'enable_schema'    => '1',
            'enable_sitemap'   => '1',
            'pro_activated'    => '1',
            'dismissed_checks' => '["x"]',
        ];

        // SAFE — read-modify-write: load the whole blob, change one key, write it back.
        $rmw = array_merge($existing, ['enable_schema' => '0']);
        $this->assertSame('0', $rmw['enable_schema'], 'the change is applied');
        foreach ($existing as $key => $value) {
            if ($key === 'enable_schema') {
                continue;
            }
            $this->assertArrayHasKey($key, $rmw, "read-modify-write must keep sibling '$key'");
            $this->assertSame($value, $rmw[$key], "read-modify-write must not alter sibling '$key'");
        }

        // FORBIDDEN — subset replace: write only the changed key. Every sibling is lost.
        $subsetReplace = ['enable_schema' => '0'];
        foreach (array_keys($existing) as $key) {
            if ($key === 'enable_schema') {
                continue;
            }
            $this->assertArrayNotHasKey(
                $key,
                $subsetReplace,
                "subset-replace WIPES sibling '$key' — this is why every settings writer must be "
                . "read-modify-write or a full snapshot"
            );
        }
    }

    // ── 2. A partial save must not wipe protected (externally-owned) keys ────

    public function testAPartialSavePreservesLicenseProAndIdentityKeys(): void
    {
        $existing = [
            'org_name'       => 'Acme',
            'pro_activated'  => '1',
            'license_key'    => 'LS-KEY-1234',
            'license_tier'   => 'pro',
            'install_id'     => 'inst-aaaa',
            'last_backup_at' => '2026-05-20T08:00:00+00:00',
        ];

        // A partial save posts a single ordinary form field.
        $posted = ['org_name' => 'New Name'];
        $merged = SettingsSaveDefinition::mergeSystemPreservedKeys($posted, $existing);

        $this->assertSame('1', $merged['pro_activated']);
        $this->assertSame('LS-KEY-1234', $merged['license_key']);
        $this->assertSame('pro', $merged['license_tier']);
        $this->assertSame('inst-aaaa', $merged['install_id']);
        $this->assertSame('2026-05-20T08:00:00+00:00', $merged['last_backup_at']);
        $this->assertSame('New Name', $merged['org_name']);
    }

    // ── 3. A full snapshot save loses no ordinary key ───────────────────────

    public function testAFullSnapshotSaveKeepsEveryOrdinaryKey(): void
    {
        $existing = ['org_name' => 'Old', 'title_separator' => '-', 'pro_activated' => '1'];

        // Full snapshot: the SPA posts every ordinary key (the real save shape).
        $posted = ['org_name' => 'New', 'title_separator' => '|', 'enable_schema' => '1'];
        $merged = SettingsSaveDefinition::mergeSystemPreservedKeys($posted, $existing);

        $this->assertSame('New', $merged['org_name']);
        $this->assertSame('|', $merged['title_separator']);
        $this->assertSame('1', $merged['enable_schema']);
        $this->assertSame('1', $merged['pro_activated'], 'the system key is preserved from the existing row');
    }

    // ── 4. The rule, enforced over the real source ──────────────────────────

    public function testEverySettingsBlobWriterIsClassified(): void
    {
        $base  = realpath(self::COMPONENT);
        $found = [];

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if (strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $path = str_replace('\\', '/', $file->getPathname());
            if (preg_match('#/(tests|vendor|node_modules|vue-admin|media)/#', $path)) {
                continue;
            }
            $src = (string) file_get_contents($file->getPathname());
            if (preg_match('/quoteName\(\'settings_json\'\)\s*\.\s*\'/', $src)) {
                $found[ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($base))), '/')] = true;
            }
        }

        $found = array_keys($found);
        sort($found);
        $expected = array_keys(self::WRITERS);
        sort($expected);

        $this->assertSame(
            $expected,
            $found,
            "A settings-blob writer was added or removed. Classify every writer of the "
            . "#__aiboost_settings 'main' row in self::WRITERS (read-modify-write / full snapshot / "
            . "helper / install / import — never a subset replace)."
        );
    }

    public function testEveryReadModifyWriteWriterLoadsBeforeItWrites(): void
    {
        foreach (self::WRITERS as $rel => $methods) {
            foreach ($methods as $method => $shape) {
                if ($shape !== 'rmw') {
                    continue;
                }
                $src  = (string) file_get_contents(self::COMPONENT . $rel);
                $body = $this->methodBody($src, $method);
                $this->assertNotNull($body, "writer method $method() not found in $rel");

                $loadPos = preg_match('/->select\([^;]*?settings_json/', $body, $m, PREG_OFFSET_CAPTURE)
                    ? $m[0][1] : null;
                $writePos = preg_match('/quoteName\(\'settings_json\'\)\s*\.\s*\'/', $body, $m, PREG_OFFSET_CAPTURE)
                    ? $m[0][1] : null;

                $this->assertNotNull(
                    $loadPos,
                    "$rel::$method() must LOAD settings_json before writing it (read-modify-write); "
                    . "writing without loading first replaces the whole blob with a subset and wipes siblings."
                );
                $this->assertNotNull($writePos, "$rel::$method() was expected to write settings_json.");
                $this->assertLessThan(
                    $writePos,
                    $loadPos,
                    "$rel::$method() must load the whole blob BEFORE it writes it (read-modify-write)."
                );
            }
        }
    }

    /**
     * Slice a class method's source from its declaration to the next method
     * declaration (or end of file). Robust for the standard-indented methods in
     * these files — no brace counting, so closures and braces-in-strings cannot
     * fool it.
     */
    private function methodBody(string $src, string $method): ?string
    {
        if (!preg_match_all(
            '/\n[ \t]*(?:public|private|protected)[ \t]+(?:static[ \t]+)?function[ \t]+(\w+)[ \t]*\(/',
            $src,
            $all,
            PREG_OFFSET_CAPTURE
        )) {
            return null;
        }

        foreach ($all[1] as $i => $name) {
            if ($name[0] === $method) {
                $start = $all[0][$i][1];
                $end   = isset($all[0][$i + 1]) ? $all[0][$i + 1][1] : strlen($src);
                return substr($src, $start, $end - $start);
            }
        }
        return null;
    }
}
