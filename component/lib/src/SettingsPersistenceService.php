<?php
/**
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib;

defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

class SettingsPersistenceService extends AbstractService
{
    private const SETTING_KEY_MAIN = 'main';
    private const TABLE_NAME       = '#__aiboost_settings';

    private DatabaseInterface $db;

    public function __construct(?AppContextInterface $ctx, Registry $params, DatabaseInterface $db)
    {
        parent::__construct($ctx, $params);
        $this->db = $db;
    }

    protected function getServiceKey(): string
    {
        return 'enable_settings_persistence';
    }

    public function saveSettings(array $settings): bool
    {
        try {
            $now = gmdate('Y-m-d H:i:s');

            $settingsJson = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($settingsJson === false) {
                return false;
            }

            $query = $this->db->getQuery(true)
                ->select('id')
                ->from(self::TABLE_NAME)
                ->where($this->db->quoteName('setting_key') . ' = ' . $this->db->quote(self::SETTING_KEY_MAIN));
            $this->db->setQuery($query);
            $existingId = $this->db->loadResult();

            if ($existingId) {
                $query = $this->db->getQuery(true)
                    ->update(self::TABLE_NAME)
                    ->set($this->db->quoteName('settings_json') . ' = ' . $this->db->quote($settingsJson))
                    ->set($this->db->quoteName('updated_at') . ' = ' . $this->db->quote($now))
                    ->where($this->db->quoteName('id') . ' = ' . (int) $existingId);
            } else {
                $query = $this->db->getQuery(true)
                    ->insert(self::TABLE_NAME)
                    ->columns([
                        $this->db->quoteName('setting_key'),
                        $this->db->quoteName('settings_json'),
                        $this->db->quoteName('created_at'),
                        $this->db->quoteName('updated_at'),
                    ])
                    ->values(
                        $this->db->quote(self::SETTING_KEY_MAIN) . ', ' .
                        $this->db->quote($settingsJson) . ', ' .
                        $this->db->quote($now) . ', ' .
                        $this->db->quote($now)
                    );
            }

            $this->db->setQuery($query)->execute();
            return true;
        } catch (\Throwable $e) {
            $this->logDebug('Error saving settings: ' . $e->getMessage());
            return false;
        }
    }

    public function loadSettings(): ?array
    {
        try {
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName('settings_json'))
                ->from(self::TABLE_NAME)
                ->where($this->db->quoteName('setting_key') . ' = ' . $this->db->quote(self::SETTING_KEY_MAIN));
            $this->db->setQuery($query);
            $json = $this->db->loadResult();

            if (empty($json)) {
                return null;
            }

            $settings = json_decode($json, true);
            return ($settings !== null && json_last_error() === JSON_ERROR_NONE) ? $settings : null;
        } catch (\Throwable $e) {
            $this->logDebug('Error loading settings: ' . $e->getMessage());
            return null;
        }
    }

    public function getParam(string $key, mixed $default = null): mixed
    {
        $settings = $this->loadSettings();
        return $settings[$key] ?? $default;
    }

    public function hasSettings(): bool
    {
        try {
            $query = $this->db->getQuery(true)
                ->select('COUNT(*)')
                ->from(self::TABLE_NAME)
                ->where($this->db->quoteName('setting_key') . ' = ' . $this->db->quote(self::SETTING_KEY_MAIN));
            return (int) $this->db->setQuery($query)->loadResult() > 0;
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning($e, ['where' => 'SettingsPersistenceService::hasSettings']);
            return false;
        }
    }

    public function exportJson(array $settings, string $version = '0.6.0'): string
    {
        $export = [
            'meta'     => [
                'plugin'      => 'AI Boost for Joomla',
                'version'     => $version,
                'exported_at' => gmdate(\DateTimeInterface::ATOM),
            ],
            'settings' => $settings,
        ];
        return json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function importFromJson(string $json): ?array
    {
        $data = json_decode($json, true);
        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        if (isset($data['settings']) && is_array($data['settings'])) {
            return $data['settings'];
        }
        if (isset($data['params'])) {
            return is_array($data['params']) ? $data['params'] : json_decode($data['params'], true);
        }
        return null;
    }
}
