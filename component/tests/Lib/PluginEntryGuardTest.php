<?php

namespace AiBoost\Tests\Lib;

use AiBoost\Lib\InstallIntegrity;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Regression guard for the Free and integration system-plugin entry points.
 *
 * Each Free entry file used to require the com_aiboost lib autoloader
 * unconditionally, and the integration add-ons (aiboost_int_*) required an
 * Extension class extending a lib class without checking the lib was there;
 * when the component was missing (uninstalled separately, failed update,
 * partial FTP deploy) PHP fataled on plugin import and took down both the
 * front-end AND the administrator, so the customer could not even reach
 * Extensions → Plugins to disable the plugin. The entry files now check
 * file_exists() and bail out before defining the plugin class, so Joomla's
 * legacy loader (class_exists() in PluginHelper::import()) silently skips
 * the plugin instead.
 *
 * The runtime tests include the real entry files, so they run in isolated
 * processes: JPATH_ADMINISTRATOR must point at a different directory per
 * scenario and constants cannot be redefined within one process.
 */
final class PluginEntryGuardTest extends TestCase
{
    private const PLUGINS_DIR = __DIR__ . '/../../plugins/system';

    /**
     * Entry-file paths for the Free system plugins, keyed by element.
     *
     * @return array<string, string>
     */
    private static function freeEntryFiles(): array
    {
        $files = [];
        foreach (InstallIntegrity::FREE_SYSTEM_PLUGINS as $element) {
            $files[$element] = self::PLUGINS_DIR . "/{$element}/{$element}.php";
        }

        return $files;
    }

    /**
     * Entry-file paths for the integration add-on plugins (aiboost_int_*),
     * keyed by element. These are not in InstallIntegrity::FREE_SYSTEM_PLUGINS
     * (installed separately, not part of pkg_aiboost), but their Extension
     * classes extend lib classes (AbstractIntegrationPlugin), so an unguarded
     * require fatals with "class not found" when com_aiboost is absent — the
     * same lockout the Free plugins guard against. Auto-discovered so future
     * bridges are covered without editing this test.
     *
     * @return array<string, string>
     */
    private static function integrationEntryFiles(): array
    {
        $files = [];
        foreach (new \FilesystemIterator(self::PLUGINS_DIR, \FilesystemIterator::SKIP_DOTS) as $dir) {
            /** @var \SplFileInfo $dir */
            if (!$dir->isDir() || !str_starts_with($dir->getFilename(), 'aiboost_int_')) {
                continue;
            }

            $element = $dir->getFilename();
            $entry   = $dir->getPathname() . "/{$element}.php";
            if (is_file($entry)) {
                $files[$element] = $entry;
            }
        }
        ksort($files);

        return $files;
    }

    /**
     * All entry files that must carry the component-absent guard:
     * the Free system plugins plus the integration add-ons.
     *
     * @return array<string, string>
     */
    private static function guardedEntryFiles(): array
    {
        return array_merge(self::freeEntryFiles(), self::integrationEntryFiles());
    }

    /**
     * Legacy loader class name for a plugin element:
     * 'Plg' + 'System' + ucfirst(element), e.g. PlgSystemAiboost_core.
     */
    private static function legacyClassName(string $element): string
    {
        return 'PlgSystem' . ucfirst($element);
    }

    /**
     * Create a throwaway JPATH_ADMINISTRATOR directory, optionally containing
     * a stub com_aiboost lib autoloader.
     */
    private static function makeAdminDir(bool $withComponent): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'aiboost-entry-guard-' . bin2hex(random_bytes(6));

        if ($withComponent) {
            $libDir = $dir . '/components/com_aiboost/lib';
            mkdir($libDir, 0777, true);
            file_put_contents(
                $libDir . '/autoload.php',
                "<?php\n// Stub lib autoloader for PluginEntryGuardTest.\n"
            );
        } else {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }

    /**
     * Static scan: no active (non-archived, non-Pro) plugin entry file may
     * require the component autoloader unguarded. The guard is the
     * file_exists() check + early return; a bare
     * `require_once JPATH_ADMINISTRATOR …` would fatal site-wide when the
     * component is absent.
     */
    public function testNoActiveEntryFileRequiresComponentAutoloadUnguarded(): void
    {
        $root = realpath(self::PLUGINS_DIR);
        $this->assertNotFalse($root, 'System plugins directory not found: ' . self::PLUGINS_DIR);

        $offenders = [];
        foreach (new \FilesystemIterator($root, \FilesystemIterator::SKIP_DOTS) as $dir) {
            /** @var \SplFileInfo $dir */
            if (!$dir->isDir() || str_starts_with($dir->getFilename(), '_')) {
                continue;
            }

            $entry = $dir->getPathname() . '/' . $dir->getFilename() . '.php';
            if (!is_file($entry)) {
                continue;
            }

            $src = file_get_contents($entry);
            $this->assertNotFalse($src, "Failed to read {$entry}");

            if (preg_match('/require(?:_once)?\s+JPATH_ADMINISTRATOR/', $src)) {
                $offenders[] = $dir->getFilename();
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Plugin entry file(s) require the com_aiboost autoloader unguarded — if the "
            . "component is missing this fatals on plugin import and locks the customer "
            . "out of the administrator. Use the file_exists() guard + early return instead:\n  - "
            . implode("\n  - ", $offenders)
        );
    }

    /**
     * Every guarded entry file (Free plugins + integration add-ons) keeps the
     * guard ingredients: the file_exists() check and the graceful bail before
     * the Extension class is defined.
     */
    public function testEveryGuardedEntryFileContainsTheGuard(): void
    {
        $files = self::guardedEntryFiles();
        $this->assertArrayHasKey(
            'aiboost_int_falang',
            $files,
            'Integration plugin discovery no longer finds aiboost_int_falang — update this test'
        );

        foreach ($files as $element => $entry) {
            $this->assertFileExists($entry, "Missing entry file for {$element}");

            $src = file_get_contents($entry);
            $this->assertNotFalse($src, "Failed to read {$entry}");

            $this->assertStringContainsString(
                "/components/com_aiboost/lib/autoload.php'",
                $src,
                "{$element}: entry file no longer references the lib autoloader — update this test"
            );
            $this->assertMatchesRegularExpression(
                '/if\s*\(\s*!\s*file_exists\s*\(/',
                $src,
                "{$element}: entry file lost the file_exists() guard on the lib autoloader"
            );
        }
    }

    /**
     * Component absent: importing every guarded entry file (Free plugins +
     * integration add-ons) must be a silent no-op — no fatal, and the legacy
     * plugin class is never defined, so PluginHelper::import() skips the
     * plugin. For the integration add-ons this also covers the "parent lib
     * class not found" fatal: the guard returns before the Extension file
     * (whose class extends AbstractIntegrationPlugin) is ever required.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEntryFilesNoOpWhenComponentIsAbsent(): void
    {
        $adminDir = self::makeAdminDir(false);
        define('JPATH_ADMINISTRATOR', $adminDir);

        try {
            foreach (self::guardedEntryFiles() as $element => $entry) {
                require $entry;

                $this->assertFalse(
                    class_exists(self::legacyClassName($element), false),
                    "{$element}: plugin class was defined even though com_aiboost is absent"
                );
            }
        } finally {
            self::removeDir($adminDir);
        }
    }

    /**
     * Component present: behaviour is unchanged — each entry file (Free
     * plugins + integration add-ons) loads the autoloader and registers the
     * legacy class alias Joomla looks up. The integration Extension classes
     * resolve their lib parent (AbstractIntegrationPlugin) through the test
     * Composer autoloader and the bootstrap's CMSPlugin stub.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEntryFilesRegisterLegacyAliasWhenComponentIsPresent(): void
    {
        $adminDir = self::makeAdminDir(true);
        define('JPATH_ADMINISTRATOR', $adminDir);

        try {
            foreach (self::guardedEntryFiles() as $element => $entry) {
                require $entry;

                $this->assertTrue(
                    class_exists(self::legacyClassName($element), false),
                    "{$element}: legacy class alias missing although com_aiboost is present"
                );
            }
        } finally {
            self::removeDir($adminDir);
        }
    }
}
