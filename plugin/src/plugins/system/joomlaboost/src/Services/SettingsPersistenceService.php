<?php

/**
 * @package     JoomlaBoost
 * @subpackage  Services
 * @author      JoomlaBoost Team
 * @copyright   Copyright (C) 2024 JoomlaBoost. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Services;

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\Registry\Registry;
use Exception;

/**
 * Settings Persistence Service
 * Handles saving and loading plugin settings to/from database for persistence across installations
 */
class SettingsPersistenceService extends AbstractService
{
    /**
     * Default setting key for main configuration
     */
    private const SETTING_KEY_MAIN = 'main';

    /**
     * Table name (without prefix)
     */
    private const TABLE_NAME = '#__joomlaboost_settings';

    /**
     * Constructor
     *
     * @param CMSApplication $app    The application instance
     * @param Registry       $params Plugin parameters
     */
    public function __construct(CMSApplication $app, Registry $params)
    {
        parent::__construct($app, $params);
    }

    /**
     * Get service key identifier
     *
     * @return string Service key
     */
    protected function getServiceKey(): string
    {
        return 'settings_persistence';
    }

    /**
     * Save current plugin settings to database
     *
     * @param array $settings Settings array to save
     * @return bool True on success, false on failure
     */
    public function saveSettings(array $settings): bool
    {
        try {
            $db = Factory::getDbo();
            $now = Factory::getDate()->toSql();

            // Convert settings array to JSON
            $settingsJson = json_encode($settings, JSON_PRETTY_PRINT);

            if ($settingsJson === false) {
                $this->logDebug('Failed to encode settings to JSON');
                return false;
            }

            // Check if settings already exist
            $query = $db->getQuery(true)
                ->select('id')
                ->from(self::TABLE_NAME)
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote(self::SETTING_KEY_MAIN));

            $db->setQuery($query);
            $existingId = $db->loadResult();

            if ($existingId) {
                // Update existing settings
                $query = $db->getQuery(true)
                    ->update(self::TABLE_NAME)
                    ->set($db->quoteName('settings_json') . ' = ' . $db->quote($settingsJson))
                    ->set($db->quoteName('updated_at') . ' = ' . $db->quote($now))
                    ->where($db->quoteName('id') . ' = ' . (int) $existingId);

                $db->setQuery($query);
                $db->execute();

                $this->logDebug('Settings updated in database (ID: ' . $existingId . ')');
            } else {
                // Insert new settings
                $query = $db->getQuery(true)
                    ->insert(self::TABLE_NAME)
                    ->columns([
                        $db->quoteName('setting_key'),
                        $db->quoteName('settings_json'),
                        $db->quoteName('created_at'),
                        $db->quoteName('updated_at')
                    ])
                    ->values(
                        $db->quote(self::SETTING_KEY_MAIN) . ', ' .
                            $db->quote($settingsJson) . ', ' .
                            $db->quote($now) . ', ' .
                            $db->quote($now)
                    );

                $db->setQuery($query);
                $db->execute();

                $this->logDebug('Settings saved to database (new entry)');
            }

            return true;
        } catch (Exception $e) {
            $this->logDebug('Error saving settings: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Load settings from database
     *
     * @return array|null Settings array or null if not found
     */
    public function loadSettings(): ?array
    {
        try {
            $db = Factory::getDbo();

            $query = $db->getQuery(true)
                ->select($db->quoteName('settings_json'))
                ->from(self::TABLE_NAME)
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote(self::SETTING_KEY_MAIN));

            $db->setQuery($query);
            $settingsJson = $db->loadResult();

            if (empty($settingsJson)) {
                $this->logDebug('No persisted settings found in database');
                return null;
            }

            $settings = json_decode($settingsJson, true);

            if ($settings === null || json_last_error() !== JSON_ERROR_NONE) {
                $this->logDebug('Failed to decode settings JSON: ' . json_last_error_msg());
                return null;
            }

            $this->logDebug('Settings loaded from database (' . count($settings) . ' items)');
            return $settings;
        } catch (Exception $e) {
            $this->logDebug('Error loading settings: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if persisted settings exist
     *
     * @return bool True if settings exist, false otherwise
     */
    public function hasPersistedSettings(): bool
    {
        try {
            $db = Factory::getDbo();

            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from(self::TABLE_NAME)
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote(self::SETTING_KEY_MAIN));

            $db->setQuery($query);
            $count = (int) $db->loadResult();

            return $count > 0;
        } catch (Exception $e) {
            $this->logDebug('Error checking for persisted settings: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete all persisted settings
     *
     * @return bool True on success, false on failure
     */
    public function deleteSettings(): bool
    {
        try {
            $db = Factory::getDbo();

            $query = $db->getQuery(true)
                ->delete(self::TABLE_NAME)
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote(self::SETTING_KEY_MAIN));

            $db->setQuery($query);
            $db->execute();

            $this->logDebug('Settings deleted from database');
            return true;
        } catch (Exception $e) {
            $this->logDebug('Error deleting settings: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Export settings as JSON string
     *
     * @param array $settings Settings to export
     * @return string JSON string with metadata
     */
    public function exportSettings(array $settings): string
    {
        $export = [
            'meta' => [
                'plugin' => 'JoomlaBoost',
                'version' => '0.5.4',
                'exported_at' => Factory::getDate()->toISO8601(),
                'site_name' => $this->app->get('sitename', 'Unknown')
            ],
            'settings' => $settings
        ];

        return json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Import settings from JSON string
     *
     * @param string $json JSON string to import
     * @return array|null Settings array or null on failure
     */
    public function importSettings(string $json): ?array
    {
        try {
            $data = json_decode($json, true);

            if ($data === null || json_last_error() !== JSON_ERROR_NONE) {
                $this->logDebug('Invalid JSON format: ' . json_last_error_msg());
                return null;
            }

            // Validate structure
            if (!isset($data['settings']) || !is_array($data['settings'])) {
                $this->logDebug('Invalid settings structure in JSON');
                return null;
            }

            // Optional: Log import metadata
            if (isset($data['meta'])) {
                $this->logDebug('Importing settings exported at: ' . ($data['meta']['exported_at'] ?? 'unknown'));
            }

            return $data['settings'];
        } catch (Exception $e) {
            $this->logDebug('Error importing settings: ' . $e->getMessage());
            return null;
        }
    }
}
