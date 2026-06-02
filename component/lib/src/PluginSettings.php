<?php
/**
 * AI Boost — Plugin Settings Helper
 * Lightweight static helper that plugins use to read settings from
 * #__aiboost_settings without needing to instantiate a service class.
 *
 * Call PluginSettings::init($db) once during plugin bootstrap before
 * using get() or all(). Typically done in the plugin's onAfterInitialise
 * or services/provider.php boot step.
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib;

defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;

final class PluginSettings
{
    private const TABLE = '#__aiboost_settings';
    private const KEY   = 'main';

    /** @var array<string,mixed>|null */
    private static ?array $cache = null;

    /** Injected database connection — set once via init(). */
    private static ?DatabaseInterface $db = null;

    /**
     * Provide the database connection.
     * Must be called before the first get() / all() call.
     */
    public static function init(DatabaseInterface $db): void
    {
        self::$db    = $db;
        self::$cache = null;
    }

    /**
     * Returns a single setting value from the #__aiboost_settings JSON blob.
     * Results are cached in memory for the lifetime of the request.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$cache === null) {
            self::$cache = self::loadAll();
        }
        return self::$cache[$key] ?? $default;
    }

    /**
     * Returns the full settings array (all keys).
     * Results are cached in memory for the lifetime of the request.
     *
     * @return array<string,mixed>
     */
    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = self::loadAll();
        }
        return self::$cache;
    }

    /**
     * Clears the in-memory cache (call after settings are saved, if needed).
     */
    public static function clearCache(): void
    {
        self::$cache = null;
    }

    /** @return array<string,mixed> */
    private static function loadAll(): array
    {
        if (self::$db === null) {
            return [];
        }
        try {
            $db    = self::$db;
            $query = $db->getQuery(true)
                ->select($db->quoteName('settings_json'))
                ->from(self::TABLE)
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote(self::KEY));
            $json  = $db->setQuery($query)->loadResult();
            if (empty($json)) {
                return [];
            }
            $data = json_decode($json, true);
            return (is_array($data) && json_last_error() === JSON_ERROR_NONE) ? $data : [];
        } catch (\Throwable $e) {
            // Cannot route through Logger here — Logger::loadSettings() calls
            // PluginSettings::all(), so logging this failure would recurse.
            // Use native error_log() as last resort (Task #511 review fix).
            @error_log('[AiBoost][PluginSettings::loadAll] ' . $e->getMessage());
            return [];
        }
    }
}
