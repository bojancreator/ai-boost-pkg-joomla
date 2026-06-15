<?php

namespace AiBoost\Tests\Lib;

use PHPUnit\Framework\TestCase;

/**
 * Phase 6 guards for pkg_script.php (the combined Pro edition + the legacy
 * *_pro decorator sweep). The sweep ROW-DELETES extensions from a live Pro
 * site, so its cascade-safety invariants are pinned here:
 *
 *  - it NEVER calls Installer->uninstall() (the old pkg_aiboost_pro set
 *    blockChildUninstall → an unpredictable cascade that historically fataled);
 *  - it deletes the package row AFTER the child plugin rows (detach → delete
 *    children → delete package);
 *  - it runs ONLY from the combined Pro edition (gated by IS_PRO_EDITION);
 *  - the edition marker + the preflight downgrade-lock are wired.
 */
final class PkgScriptPhase6SafetyTest extends TestCase
{
    private const SCRIPT_PATH = __DIR__ . '/../../package/pkg_script.php';

    private static string $src = '';

    public static function setUpBeforeClass(): void
    {
        self::$src = (string) file_get_contents(self::SCRIPT_PATH);
    }

    public function testSweepNeverCallsInstallerUninstall(): void
    {
        $body = $this->methodBody('sweepCollapsedProDecorators');
        $this->assertStringContainsString('deleteLegacyExtensionRows', $body, 'the sweep must row-delete, not Installer-uninstall.');
        // Strip comments first — the method intentionally DOCUMENTS the
        // never-Installer rule in prose; the invariant is about the CODE.
        $code = $this->stripComments($body);
        $this->assertStringNotContainsString('Installer', $code, 'the sweep must NEVER use Joomla\'s Installer (blockChildUninstall cascade).');
        $this->assertStringNotContainsString('->uninstall(', $code, 'the sweep must NEVER call ->uninstall().');
    }

    private function stripComments(string $php): string
    {
        $php = preg_replace('!/\*.*?\*/!s', '', $php);  // block comments
        $php = preg_replace('!//[^\n]*!', '', $php);    // line comments
        return (string) $php;
    }

    public function testSweepDeletesChildrenBeforeThePackageRow(): void
    {
        $body = $this->methodBody('sweepCollapsedProDecorators');
        // The per-decorator foreach (child detach + delete) must precede the
        // pkg_aiboost_pro package-row deletion.
        $childrenLoop = strpos($body, 'foreach ($decorators as $element)');
        $pkgDelete    = strpos($body, "\$db->quote('pkg_aiboost_pro')");
        $this->assertNotFalse($childrenLoop);
        $this->assertNotFalse($pkgDelete);
        $this->assertLessThan($pkgDelete, $childrenLoop, 'children must be removed before the parent package row.');
        // Each child is detached (package_id=0) before being row-deleted.
        $this->assertStringContainsString("quoteName('package_id') . ' = 0'", $body, 'each child must be detached from its package before deletion.');
    }

    public function testSweepIsGatedOnTheProEdition(): void
    {
        $post = $this->methodBody('postflight');
        $this->assertMatchesRegularExpression(
            '/if \(self::IS_PRO_EDITION\)\s*\{\s*\$this->sweepCollapsedProDecorators\(\);/',
            $post,
            'the sweep must run ONLY on the combined Pro edition.'
        );
    }

    public function testEditionMarkerIsWiredFromTheInjectedFlag(): void
    {
        $this->assertStringContainsString('public const IS_PRO_EDITION = false;', self::$src, 'the build-injected edition flag must exist (default false).');
        $post = $this->methodBody('postflight');
        $this->assertStringContainsString('$this->setInstalledEdition(self::IS_PRO_EDITION);', $post, 'postflight must record the installed edition.');
    }

    public function testPreflightCarriesTheDowngradeLock(): void
    {
        $pre = $this->methodBody('preflight');
        $this->assertStringContainsString('$this->installedPackageVersion()', $pre);
        $this->assertStringContainsString('$this->isProSiteByFlags()', $pre);
        $this->assertStringContainsString('version_compare(self::VERSION', $pre);
        $this->assertStringContainsString('return false;', $pre, 'the downgrade case must abort the install.');
    }

    public function testIsProInstallConsultsTheMarker(): void
    {
        $body = $this->methodBody('isProInstall');
        $this->assertStringContainsString("\$data['pro_installed']", $body, 'isProInstall must read the install marker.');
        $this->assertStringContainsString("\$data['pro_activated']", $body, 'isProInstall must read the activation flag.');
    }

    private function methodBody(string $method): string
    {
        $sigPos = strpos(self::$src, 'function ' . $method . '(');
        $this->assertNotFalse($sigPos, "method $method() not found in pkg_script.php");
        $bracePos = strpos(self::$src, '{', $sigPos);
        $depth = 0;
        for ($i = $bracePos, $n = strlen(self::$src); $i < $n; $i++) {
            if (self::$src[$i] === '{') {
                $depth++;
            } elseif (self::$src[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr(self::$src, $bracePos, $i - $bracePos + 1);
                }
            }
        }
        $this->fail("could not brace-match $method()");
    }
}
