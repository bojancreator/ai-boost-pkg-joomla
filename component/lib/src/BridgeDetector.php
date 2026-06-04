<?php
/**
 * AI Boost — Bridge Detector
 *
 * Shared helper for detecting whether a third-party Joomla extension is
 * installed and enabled. All add-on bridge plugins use this class so that
 * extension-presence checks are centralised and cached per request.
 *
 * Call BridgeDetector::init($db) once during bootstrap before any
 * isExtensionEnabled() / tableExists() calls.
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib;

defined('_JEXEC') or defined('ABSPATH') or die;

use AiBoost\Lib\Cms\AdapterRegistry;
use Joomla\Database\DatabaseInterface;

final class BridgeDetector
{
    /** @var array<string,bool> Per-request cache so DB is only queried once per key. */
    private static array $cache = [];

    /** Injected database connection — set once via init(). */
    private static ?DatabaseInterface $db = null;

    /**
     * Provide the database connection.
     * Must be called before the first DB-backed check.
     */
    public static function init(DatabaseInterface $db): void
    {
        self::$db    = $db;
        self::$cache = [];
    }

    /**
     * Check whether a Joomla extension is installed and enabled.
     *
     * @param string $element  Extension element name (e.g. 'yootheme', 'falang').
     * @param string $type     Extension type: 'plugin', 'component', 'template', 'module'.
     * @param string $folder   Plugin folder (only relevant when $type='plugin').
     */
    public static function isExtensionEnabled(
        string $element,
        string $type   = 'plugin',
        string $folder = 'system'
    ): bool {
        $cacheKey = "{$type}:{$folder}:{$element}";

        if (array_key_exists($cacheKey, self::$cache)) {
            return self::$cache[$cacheKey];
        }

        $db = self::db();
        if ($db === null) {
            return self::$cache[$cacheKey] = false;
        }

        try {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('element') . ' = ' . $db->quote($element))
                ->where($db->quoteName('type')    . ' = ' . $db->quote($type))
                ->where($db->quoteName('enabled') . ' = 1');

            if ($type === 'plugin') {
                $query->where($db->quoteName('folder') . ' = ' . $db->quote($folder));
            }

            $db->setQuery($query);
            return self::$cache[$cacheKey] = (bool) $db->loadResult();
        } catch (\Throwable) {
            return self::$cache[$cacheKey] = false;
        }
    }

    /**
     * Check whether a PHP class is already loaded (without triggering autoload).
     * Useful for detecting extensions that register their own classes early.
     */
    public static function classExists(string $className): bool
    {
        return class_exists($className, false);
    }

    /**
     * Check whether a database table exists (using #__ prefix notation).
     *
     * @param string $tableName  E.g. '#__falang_content'.
     */
    public static function tableExists(string $tableName): bool
    {
        $cacheKey = 'table:' . $tableName;

        if (array_key_exists($cacheKey, self::$cache)) {
            return self::$cache[$cacheKey];
        }

        $db = self::db();
        if ($db === null) {
            return self::$cache[$cacheKey] = false;
        }

        try {
            $suffix = str_replace('#__', '', $tableName);
            $query  = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('information_schema.tables'))
                ->where($db->quoteName('table_schema') . ' = DATABASE()')
                ->where($db->quoteName('table_name') . ' LIKE ' . $db->quote('%' . $suffix));
            $db->setQuery($query);
            return self::$cache[$cacheKey] = (bool) $db->loadResult();
        } catch (\Throwable) {
            return self::$cache[$cacheKey] = false;
        }
    }

    /**
     * Check whether a file exists relative to JPATH_ROOT.
     * Useful for detecting template files or third-party config files.
     */
    public static function fileExists(string $relativePath): bool
    {
        $cacheKey = 'file:' . $relativePath;

        if (array_key_exists($cacheKey, self::$cache)) {
            return self::$cache[$cacheKey];
        }

        return self::$cache[$cacheKey] = AdapterRegistry::filesystem()->siteFileExists($relativePath);
    }

    /**
     * Convenience shorthand: check whether a named extension is installed and
     * enabled, searching across plugin (system), component, and template types.
     * Equivalent to calling isExtensionEnabled() with each type in turn.
     *
     * @param string $extensionName  Element name (e.g. 'yootheme', 'falang').
     */
    public static function isInstalled(string $extensionName): bool
    {
        return self::isExtensionEnabled($extensionName, 'plugin',    'system')
            || self::isExtensionEnabled($extensionName, 'component', '')
            || self::isExtensionEnabled($extensionName, 'template',  '');
    }

    private static function db(): ?DatabaseInterface
    {
        if (self::$db !== null) {
            return self::$db;
        }
        try {
            self::$db = AdapterRegistry::database()->getConnection();
            return self::$db;
        } catch (\Throwable) {
            return null;
        }
    }

    // ── Sitemap exclusion registry ──────────────────────────────────────────

    /**
     * Joomla language objects registered by aiboost_falang for hreflang sitemap alternates.
     * Each entry: ['lang_id', 'lang_code', 'sef', 'title'].
     * Populated by AiBoostFalang::onAiBoostBeforeSitemapBuild() when Falang is installed + licensed.
     *
     * @var array<array{lang_id:string,lang_code:string,sef:string,title:string}>
     */
    private static array $sitemapLanguages = [];

    /**
     * Register Joomla/Falang language list for use by aiboost_sitemap when
     * generating <xhtml:link hreflang> alternates in the XML sitemap.
     * Called by the aiboost_falang plugin.
     *
     * @param array<array{lang_id:string,lang_code:string,sef:string,title:string}> $languages
     */
    public static function registerSitemapLanguages(array $languages): void
    {
        self::$sitemapLanguages = $languages;
    }

    /**
     * Retrieve the language list registered for sitemap hreflang alternates.
     * Returns an empty array when aiboost_falang is not active.
     *
     * @return array<array{lang_id:string,lang_code:string,sef:string,title:string}>
     */
    public static function getSitemapLanguages(): array
    {
        return self::$sitemapLanguages;
    }

    /** @var int[] Menu item IDs that add-ons request be excluded from the sitemap. */
    private static array $excludedMenuIds = [];

    /**
     * Add-on plugins call this to request that specific menu item IDs are
     * excluded from the XML sitemap generated by aiboost_sitemap.
     *
     * @param int[] $ids  Menu item IDs to exclude.
     */
    public static function excludeMenuIds(array $ids): void
    {
        self::$excludedMenuIds = array_merge(self::$excludedMenuIds, array_map('intval', $ids));
    }

    /**
     * Read all menu item IDs that have been requested for sitemap exclusion.
     * Called by the aiboost_sitemap plugin before building the URL list.
     *
     * @return int[]
     */
    public static function getExcludedMenuIds(): array
    {
        return array_unique(self::$excludedMenuIds);
    }

    // ── Schema translation coordination ────────────────────────────────────

    /**
     * Flag set by aiboost_falang when it is active + licensed + schema translate ON.
     * When true, aiboost_schema plugin defers per-language Organization output to
     * aiboost_falang, avoiding duplicate/conflicting JSON-LD Organization entities.
     */
    private static bool $schemaTranslationActive = false;

    /**
     * Translated field values registered by aiboost_falang for the current language.
     * Used by aiboost_schema to override EN-default field values with translations.
     * Keys match AI Boost settings field names (e.g. 'org_name_en', 'org_description_en').
     *
     * @var array<string,string>
     */
    private static array $registeredTranslations = [];

    /**
     * Called by aiboost_falang when it is handling per-language schema output.
     * Prevents aiboost_schema from outputting a conflicting EN-only Organization.
     */
    public static function setSchemaTranslationActive(bool $active): void
    {
        self::$schemaTranslationActive = $active;
    }

    /** @return bool True when aiboost_falang is handling per-language Organisation schema. */
    public static function isSchemaTranslationActive(): bool
    {
        return self::$schemaTranslationActive;
    }

    /**
     * Register a translated field value for the current request language.
     * Called by aiboost_falang; read by aiboost_schema to override EN defaults.
     *
     * @param string $field  AI Boost settings field name (e.g. 'org_name_en').
     * @param string $value  Translated string value.
     */
    public static function registerTranslation(string $field, string $value): void
    {
        self::$registeredTranslations[$field] = $value;
    }

    /**
     * Get a translated field value registered by aiboost_falang, or null if absent.
     *
     * @param string $field  AI Boost settings field name.
     */
    public static function getTranslation(string $field): ?string
    {
        return self::$registeredTranslations[$field] ?? null;
    }

    // ── Falang URL alias map ────────────────────────────────────────────────

    /**
     * Falang Pro translated alias map built by AiBoostFalang::onAiBoostBeforeSitemapBuild().
     *
     * Structure: [ original_alias => [ sef => translated_alias, ... ], ... ]
     *
     * Example:
     *   [ 'about-us' => ['sr' => 'o-nama', 'de' => 'ueber-uns'], ... ]
     *
     * Used by aiboost_sitemap::buildSitemapAlternateUrl() to generate per-language
     * hreflang alternate URLs that point to the actual translated page paths,
     * rather than simply prepending the language SEF prefix to the canonical URL.
     *
     * @var array<string,array<string,string>>
     */
    private static array $falangAliasMap = [];

    /**
     * Register the Falang translated alias map.
     * Called by AiBoostFalang in onAiBoostBeforeSitemapBuild().
     *
     * @param array<string,array<string,string>> $map  [original_alias => [sef => translated_alias]]
     */
    public static function registerFalangAliasMap(array $map): void
    {
        self::$falangAliasMap = $map;
    }

    /**
     * Retrieve the Falang alias map for use in sitemap URL generation.
     * Returns an empty array when aiboost_falang is not installed or not licensed.
     *
     * @return array<string,array<string,string>>
     */
    public static function getFalangAliasMap(): array
    {
        return self::$falangAliasMap;
    }

    // ── Primary language SEF for sitemap x-default ─────────────────────────

    /** SEF code of the primary language, registered by aiboost_falang. */
    private static string $primaryLanguageSef = '';

    /** Hreflang source mode: auto, joomla_native, or falang. */
    private static string $hreflangMode = 'auto';

    /**
     * Register the primary language SEF (used for sitemap x-default hreflang).
     * Called by aiboost_falang in onAiBoostBeforeSitemapBuild().
     */
    public static function registerPrimaryLanguageSef(string $sef): void
    {
        self::$primaryLanguageSef = $sef;
    }

    /**
     * Get the primary language SEF for x-default hreflang.
     * Returns an empty string when not registered.
     */
    public static function getPrimaryLanguageSef(): string
    {
        return self::$primaryLanguageSef;
    }

    public static function registerHreflangMode(string $mode): void
    {
        $allowed = ['auto', 'joomla_native', 'falang'];
        self::$hreflangMode = in_array($mode, $allowed, true) ? $mode : 'auto';
    }

    public static function getHreflangMode(): string
    {
        return self::$hreflangMode;
    }

    /** Clear all cached results (useful in tests or CLI contexts). */
    public static function clearCache(): void
    {
        self::$db                      = null;
        self::$cache                   = [];
        self::$excludedMenuIds         = [];
        self::$sitemapLanguages        = [];
        self::$falangAliasMap          = [];
        self::$schemaTranslationActive = false;
        self::$registeredTranslations  = [];
        self::$primaryLanguageSef      = '';
        self::$hreflangMode            = 'auto';
    }
}
