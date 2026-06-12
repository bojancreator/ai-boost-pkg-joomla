<?php

namespace AiBoost\Tests\Lib;

use PHPUnit\Framework\TestCase;

/**
 * Regression guards for the partial-lib incident class (2026-06-11).
 *
 * The base package (pkg_aiboost) was uninstalled while the Pro package's
 * system plugins stayed installed and enabled. The uninstall left a PARTIAL
 * filesystem — lib/autoload.php present, some lib/src class files deleted —
 * and every admin page 500ed with "Attempted to load class Logger from
 * namespace AiBoost\Lib". Two code patterns caused it:
 *
 *   1. catch blocks that themselves referenced \AiBoost\Lib\Logger: when the
 *      try failed because a lib class could not load, the catch triggered a
 *      SECOND autoload failure that no catch can intercept.
 *   2. event handlers touching lib classes right after boot() with no guard:
 *      boot() only checks file_exists(autoload.php), which says nothing about
 *      the individual class files.
 *
 * These are STATIC SOURCE SCANS (regex/brace-level tripwires), deliberately
 * cheap and dependency-free. Known limits, accepted by design:
 *   - brace matching is textual: a literal '{' / '}' inside a string or
 *     comment inside a catch block can skew the extracted block boundary;
 *   - the guard check only asserts the class_exists(..., false) text appears
 *     somewhere inside the same catch block, not that it strictly wraps the
 *     Logger call;
 *   - the libReady() marker check asserts presence and per-handler call
 *     counts, not the position of the call inside each handler.
 * They cannot prove runtime safety — they exist to make silently REMOVING the
 * guards (the regression that matters) fail CI loudly.
 */
final class PluginLibGuardTest extends TestCase
{
    private const PLUGINS_DIR = __DIR__ . '/../../plugins/system';

    /**
     * Plugin elements whose Extension class must carry the cached libReady()
     * guard. The *_pro decorators are auto-discovered (so a future Pro plugin
     * is covered automatically); the integration bridge and the Free
     * orchestrators are pinned explicitly because they reference lib classes
     * from their handlers too.
     */
    private const EXTRA_GUARDED_ELEMENTS = [
        'aiboost_int_falang',
        'aiboost_aeo',
        'aiboost_analytics',
        'aiboost_code',
        'aiboost_core',
        'aiboost_schema',
        'aiboost_sitemap',
        'aiboost_social',
    ];

    /**
     * All PHP files of every active (non-archived) system plugin.
     *
     * @return array<int, string>
     */
    private static function activePluginPhpFiles(): array
    {
        $root = realpath(self::PLUGINS_DIR);
        self::assertNotFalse($root, 'System plugins directory not found: ' . self::PLUGINS_DIR);

        $files = [];
        foreach (new \FilesystemIterator($root, \FilesystemIterator::SKIP_DOTS) as $dir) {
            /** @var \SplFileInfo $dir */
            if (!$dir->isDir() || str_starts_with($dir->getFilename(), '_')) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir->getPathname(), \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                /** @var \SplFileInfo $file */
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }
        sort($files);
        self::assertNotEmpty($files, 'No plugin PHP files discovered — wrong path?');

        return $files;
    }

    /**
     * Extract the body of every catch block in $src via textual brace
     * matching (see the class docblock for the accepted limits).
     *
     * @return array<int, string> catch block bodies (braces included)
     */
    private static function extractCatchBlocks(string $src): array
    {
        $blocks = [];
        if (!preg_match_all('/catch\s*\([^)]*\)\s*\{/', $src, $m, PREG_OFFSET_CAPTURE)) {
            return $blocks;
        }
        foreach ($m[0] as [$match, $offset]) {
            $open  = strpos($src, '{', $offset + strlen($match) - 1);
            $depth = 0;
            $len   = strlen($src);
            for ($i = $open; $i < $len; $i++) {
                if ($src[$i] === '{') {
                    $depth++;
                } elseif ($src[$i] === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $blocks[] = substr($src, $open, $i - $open + 1);
                        break;
                    }
                }
            }
        }

        return $blocks;
    }

    /**
     * Incident root cause #1: no catch block in any active plugin may call
     * \AiBoost\Lib\Logger:: without the class_exists('AiBoost\Lib\Logger',
     * false) guard. The autoload=false argument is the load-bearing part —
     * it never triggers an autoload, so it can neither load a half-deleted
     * class nor throw under JDEBUG's debug class loader.
     */
    public function testNoCatchBlockCallsLibLoggerUnguarded(): void
    {
        // Matches the source literal class_exists('AiBoost\\Lib\\Logger', false)
        // in single or double quotes, tolerating whitespace differences.
        $guardPattern = '/class_exists\(\s*[\'"]AiBoost\\\\+Lib\\\\+Logger[\'"]\s*,\s*false\s*\)/';

        $offenders = [];
        foreach (self::activePluginPhpFiles() as $file) {
            $src = file_get_contents($file);
            $this->assertNotFalse($src, "Failed to read {$file}");

            $usesLoggerImport = (bool) preg_match('/^use\s+AiBoost\\\\Lib\\\\Logger\s*;/m', $src);

            foreach (self::extractCatchBlocks($src) as $block) {
                $callsLogger = str_contains($block, 'AiBoost\\Lib\\Logger::')
                    || ($usesLoggerImport && preg_match('/(?<![\\\\\w])Logger::/', $block));
                if ($callsLogger && !preg_match($guardPattern, $block)) {
                    $offenders[] = $file;
                    break;
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "catch block(s) call \\AiBoost\\Lib\\Logger:: without the "
            . "class_exists('AiBoost\\\\Lib\\\\Logger', false) guard. If the try "
            . "failed because a lib class could not load (partial base-package "
            . "uninstall), the catch then throws a SECOND, uncatchable autoload "
            . "error and 500s every page — guard the call or use error_log():\n  - "
            . implode("\n  - ", $offenders)
        );
    }

    /**
     * Incident root cause #2: every *_pro Extension class (auto-discovered)
     * plus the pinned integration/Free Extension classes must carry the
     * cached libReady() guard — the property, the probe method with its
     * JDEBUG-safe try/catch, and at least one call site.
     */
    public function testEveryGuardedExtensionContainsTheLibReadyGuard(): void
    {
        $root = realpath(self::PLUGINS_DIR);
        $this->assertNotFalse($root, 'System plugins directory not found: ' . self::PLUGINS_DIR);

        $elements = self::EXTRA_GUARDED_ELEMENTS;
        foreach (new \FilesystemIterator($root, \FilesystemIterator::SKIP_DOTS) as $dir) {
            /** @var \SplFileInfo $dir */
            if ($dir->isDir() && str_ends_with($dir->getFilename(), '_pro')
                && !str_starts_with($dir->getFilename(), '_')) {
                $elements[] = $dir->getFilename();
            }
        }
        $elements = array_unique($elements);
        sort($elements);

        $proSeen = 0;
        foreach ($elements as $element) {
            $matches = glob("{$root}/{$element}/src/Extension/*.php") ?: [];
            $this->assertNotEmpty($matches, "{$element}: no Extension class file found");

            foreach ($matches as $file) {
                $src = file_get_contents($file);
                $this->assertNotFalse($src, "Failed to read {$file}");

                $this->assertStringContainsString(
                    'private ?bool $libReady = null;',
                    $src,
                    "{$element}: Extension class lost the cached \$libReady property"
                );
                $this->assertStringContainsString(
                    'private function libReady(): bool',
                    $src,
                    "{$element}: Extension class lost the libReady() probe method"
                );
                $this->assertMatchesRegularExpression(
                    '/function libReady\(\): bool\s*\{.*?try\s*\{.*?class_exists\(\s*\'AiBoost\\\\\\\\Lib\\\\\\\\PluginRegistry\'\s*\).*?catch\s*\(\\\\Throwable/s',
                    $src,
                    "{$element}: libReady() lost its try/catch — under JDEBUG Joomla's "
                    . 'debug class loader THROWS on a missing class file instead of '
                    . 'returning false, so an unguarded class_exists() still fatals'
                );

                // Every *_pro plugin must call the guard from EVERY public event
                // handler (the count is a cheap positional proxy for that).
                // A handler may opt out ONLY with an explicit, documented
                // @libReady-exempt docblock tag (e.g. the deliberately lib-free
                // onCustomFieldsPrepareField normaliser, which is exercised
                // standalone without Joomla by scripts/test-og-field-guard.php).
                if (str_ends_with($element, '_pro')) {
                    $proSeen++;
                    $handlers  = preg_match_all('/public function on\w+\s*\(/', $src);
                    $callSites = substr_count($src, '$this->libReady()');
                    $exempt    = substr_count($src, '@libReady-exempt');
                    $this->assertGreaterThanOrEqual(
                        $handlers,
                        $callSites + $exempt,
                        "{$element}: every public event handler must check "
                        . '$this->libReady() right after boot() (or carry an '
                        . 'explicit @libReady-exempt tag) — found '
                        . "{$handlers} handler(s) but only {$callSites} guard call(s) "
                        . "+ {$exempt} exemption(s)"
                    );
                }
            }
        }

        $this->assertGreaterThanOrEqual(
            5,
            $proSeen,
            'Expected at least the 5 known *_pro plugins (schema/aeo/social/hreflang/code) '
            . '— plugin discovery is broken, update this test'
        );
    }
}
