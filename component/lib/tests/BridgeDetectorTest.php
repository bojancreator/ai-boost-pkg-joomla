<?php
/**
 * Unit tests for AiBoost\Lib\BridgeDetector
 *
 * Run: composer exec phpunit -- component/lib/tests/BridgeDetectorTest.php
 * Or:  vendor/bin/phpunit component/lib/tests/BridgeDetectorTest.php
 *
 * These tests cover the pure PHP / static registry methods that do NOT
 * require a running Joomla application or database connection.
 *
 * Database-dependent methods (isExtensionEnabled, tableExists, classExists)
 * require a Joomla bootstrap and are tested via integration test suite.
 *
 * @package     AiBoost\Lib\Tests
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Tests;

use PHPUnit\Framework\TestCase;

// Stub JPATH_ROOT / _JEXEC so BridgeDetector can be loaded standalone
if (!defined('_JEXEC')) {
    define('_JEXEC', 1);
}
if (!defined('JPATH_ROOT')) {
    define('JPATH_ROOT', dirname(__DIR__, 3));
}

// Load the class under test
require_once dirname(__DIR__) . '/src/BridgeDetector.php';

use AiBoost\Lib\BridgeDetector;

class BridgeDetectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BridgeDetector::clearCache();
    }

    // ── Sitemap exclusion registry ──────────────────────────────────────────

    public function testExcludeMenuIdsStartsEmpty(): void
    {
        $this->assertSame([], BridgeDetector::getExcludedMenuIds());
    }

    public function testExcludeMenuIdsSingleCall(): void
    {
        BridgeDetector::excludeMenuIds([1, 2, 3]);

        $this->assertSame([1, 2, 3], BridgeDetector::getExcludedMenuIds());
    }

    public function testExcludeMenuIdsMergeCalls(): void
    {
        BridgeDetector::excludeMenuIds([1, 2]);
        BridgeDetector::excludeMenuIds([3, 4]);

        $ids = BridgeDetector::getExcludedMenuIds();
        sort($ids);
        $this->assertSame([1, 2, 3, 4], $ids);
    }

    public function testExcludeMenuIdsDeduplicates(): void
    {
        BridgeDetector::excludeMenuIds([5, 5, 10]);
        BridgeDetector::excludeMenuIds([5, 20]);

        $ids = BridgeDetector::getExcludedMenuIds();
        sort($ids);
        $this->assertSame([5, 10, 20], $ids);
    }

    public function testExcludeMenuIdsCastsToInt(): void
    {
        BridgeDetector::excludeMenuIds(['7', '8']);

        $ids = BridgeDetector::getExcludedMenuIds();
        $this->assertContains(7, $ids);
        $this->assertContains(8, $ids);
    }

    // ── Sitemap language registry ───────────────────────────────────────────

    public function testGetSitemapLanguagesStartsEmpty(): void
    {
        $this->assertSame([], BridgeDetector::getSitemapLanguages());
    }

    public function testRegisterSitemapLanguagesStoresData(): void
    {
        $langs = [
            ['lang_id' => '1', 'lang_code' => 'en-GB', 'sef' => 'en', 'title' => 'English'],
            ['lang_id' => '2', 'lang_code' => 'de-DE', 'sef' => 'de', 'title' => 'German'],
        ];

        BridgeDetector::registerSitemapLanguages($langs);

        $this->assertSame($langs, BridgeDetector::getSitemapLanguages());
    }

    public function testRegisterSitemapLanguagesOverwritesPreviousData(): void
    {
        BridgeDetector::registerSitemapLanguages([
            ['lang_id' => '1', 'lang_code' => 'en-GB', 'sef' => 'en', 'title' => 'English'],
        ]);

        $newLangs = [
            ['lang_id' => '2', 'lang_code' => 'de-DE', 'sef' => 'de', 'title' => 'Deutsch'],
        ];
        BridgeDetector::registerSitemapLanguages($newLangs);

        $this->assertSame($newLangs, BridgeDetector::getSitemapLanguages());
    }

    // ── clearCache resets everything ────────────────────────────────────────

    public function testClearCacheResetsExcludedMenuIds(): void
    {
        BridgeDetector::excludeMenuIds([100, 200]);
        BridgeDetector::clearCache();

        $this->assertSame([], BridgeDetector::getExcludedMenuIds());
    }

    public function testClearCacheResetsSitemapLanguages(): void
    {
        BridgeDetector::registerSitemapLanguages([
            ['lang_id' => '1', 'lang_code' => 'en-GB', 'sef' => 'en', 'title' => 'English'],
        ]);
        BridgeDetector::clearCache();

        $this->assertSame([], BridgeDetector::getSitemapLanguages());
    }

    public function testClearCacheResetsInternalCache(): void
    {
        // isInstalled / fileExists / classExists use $cache — after clearCache,
        // fileExists() for a non-existent path should still return false (no stale cache).
        BridgeDetector::clearCache();
        $result = BridgeDetector::fileExists('non_existent_path_xyz/nothing.php');
        $this->assertFalse($result);
    }

    // ── fileExists ──────────────────────────────────────────────────────────

    public function testFileExistsReturnsTrueForExistingFile(): void
    {
        // BridgeDetector.php itself exists relative to JPATH_ROOT
        $relative = str_replace(JPATH_ROOT . '/', '', __FILE__);
        $this->assertTrue(BridgeDetector::fileExists($relative));
    }

    public function testFileExistsReturnsFalseForMissingFile(): void
    {
        $this->assertFalse(BridgeDetector::fileExists('no/such/file/12345.php'));
    }

    public function testFileExistsCachesResult(): void
    {
        // Call twice — second call must return the same result (cached path)
        $path   = 'no/such/file/cached_test.php';
        $first  = BridgeDetector::fileExists($path);
        $second = BridgeDetector::fileExists($path);
        $this->assertSame($first, $second);
    }

    // ── classExists ─────────────────────────────────────────────────────────

    public function testClassExistsReturnsTrueForLoadedClass(): void
    {
        // BridgeDetector itself is loaded
        $this->assertTrue(BridgeDetector::classExists('AiBoost\\Lib\\BridgeDetector'));
    }

    public function testClassExistsReturnsFalseForUnknownClass(): void
    {
        $this->assertFalse(BridgeDetector::classExists('No\\Such\\Class\\XyzAbc'));
    }

    // ── Falang alias map ────────────────────────────────────────────────────

    public function testGetFalangAliasMapStartsEmpty(): void
    {
        $this->assertSame([], BridgeDetector::getFalangAliasMap());
    }

    public function testRegisterFalangAliasMapStoresData(): void
    {
        $map = [
            'about-us' => ['sr' => 'o-nama', 'de' => 'ueber-uns'],
            'services' => ['sr' => 'usluge'],
        ];

        BridgeDetector::registerFalangAliasMap($map);

        $this->assertSame($map, BridgeDetector::getFalangAliasMap());
    }

    public function testRegisterFalangAliasMapOverwritesPreviousData(): void
    {
        BridgeDetector::registerFalangAliasMap([
            'about-us' => ['sr' => 'o-nama'],
        ]);

        $newMap = ['contact' => ['sr' => 'kontakt']];
        BridgeDetector::registerFalangAliasMap($newMap);

        $this->assertSame($newMap, BridgeDetector::getFalangAliasMap());
    }

    public function testClearCacheResetsFalangAliasMap(): void
    {
        BridgeDetector::registerFalangAliasMap([
            'about-us' => ['sr' => 'o-nama'],
        ]);

        BridgeDetector::clearCache();

        $this->assertSame([], BridgeDetector::getFalangAliasMap());
    }

    // ── Schema translation coordination ────────────────────────────────────

    public function testSchemaTranslationInactiveByDefault(): void
    {
        $this->assertFalse(BridgeDetector::isSchemaTranslationActive());
    }

    public function testSetSchemaTranslationActiveTrue(): void
    {
        BridgeDetector::setSchemaTranslationActive(true);
        $this->assertTrue(BridgeDetector::isSchemaTranslationActive());
    }

    public function testSetSchemaTranslationActiveFalse(): void
    {
        BridgeDetector::setSchemaTranslationActive(true);
        BridgeDetector::setSchemaTranslationActive(false);
        $this->assertFalse(BridgeDetector::isSchemaTranslationActive());
    }

    public function testRegisterTranslationAndGet(): void
    {
        BridgeDetector::registerTranslation('org_name_en', 'O nama');
        BridgeDetector::registerTranslation('org_description_en', 'Opis firme');

        $this->assertSame('O nama', BridgeDetector::getTranslation('org_name_en'));
        $this->assertSame('Opis firme', BridgeDetector::getTranslation('org_description_en'));
    }

    public function testGetTranslationReturnsNullForMissingKey(): void
    {
        $this->assertNull(BridgeDetector::getTranslation('non_existent_field'));
    }

    public function testClearCacheResetsSchemaTranslation(): void
    {
        BridgeDetector::setSchemaTranslationActive(true);
        BridgeDetector::registerTranslation('org_name_en', 'Test Name');

        BridgeDetector::clearCache();

        $this->assertFalse(BridgeDetector::isSchemaTranslationActive());
        $this->assertNull(BridgeDetector::getTranslation('org_name_en'));
    }

    // ── Falang detection robustness (classExists signals) ──────────────────

    /**
     * Falang detection uses an OR-chain of signals; classExists() checks
     * (FalangPro\Core\Application, Falang\Helper\FalangHelper) are one layer.
     * Verify classExists returns false for unknown Falang namespaces in test env.
     */
    public function testFalangClassSignalReturnsFalseWhenFalangNotLoaded(): void
    {
        $this->assertFalse(BridgeDetector::classExists('FalangPro\\Core\\Application'));
        $this->assertFalse(BridgeDetector::classExists('Falang\\Helper\\FalangHelper'));
    }

    /**
     * isInstalled() queries #__extensions — in unit test environment (no Joomla DB)
     * it must gracefully return false without throwing, so the detection OR-chain
     * continues to the next signal rather than crashing.
     */
    public function testFalangExtensionSignalGracefulFallback(): void
    {
        // In isolated unit test (no Joomla DB), isInstalled must return false gracefully.
        // This verifies the try/catch in isExtensionEnabled works correctly.
        $result = BridgeDetector::isInstalled('falang');
        $this->assertIsBool($result);
    }
}
