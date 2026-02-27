<?php

/**
 * Translation Service for Multi-Language Schema.org Fields
 *
 * @package     JoomlaBoost
 * @subpackage  Plugin.System.Services
 * @since       0.6.0
 * @author      JoomlaBoost Team
 * @copyright   (C) 2026 JoomlaBoost
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Services;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\Database\DatabaseInterface;
use Exception;

/**
 * Translation Service
 *
 * Manages multi-language translations stored in database
 */
class TranslationService extends AbstractService
{
    /**
     * Database instance
     *
     * @var DatabaseInterface
     */
    private DatabaseInterface $db;

    /**
     * Cache for translations to avoid repeated DB queries
     *
     * @var array<string, array<string, string>>
     */
    private array $cache = [];

    /**
     * Initialize service
     */
    public function __construct($app, $params)
    {
        parent::__construct($app, $params);
        $this->db = Factory::getContainer()->get(DatabaseInterface::class);
    }

    /**
     * Get localized value with smart fallback
     *
     * Priority: current lang → en → any available → default
     *
     * @param string $fieldKey Field identifier (e.g., 'org_name', 'schema_description')
     * @param string|null $langCode Optional language code override
     * @param mixed $default Default value if no translation found
     * @return string Translation value or default
     */
    public function get(string $fieldKey, ?string $langCode = null, $default = ''): string
    {
        if ($langCode === null) {
            $langCode = $this->getCurrentLanguageCode();
        }

        // Check cache first
        if (isset($this->cache[$fieldKey][$langCode])) {
            return $this->cache[$fieldKey][$langCode];
        }

        // Load all translations for this field key into cache
        $this->loadFieldTranslations($fieldKey);

        // Try requested language
        if (isset($this->cache[$fieldKey][$langCode])) {
            return $this->cache[$fieldKey][$langCode];
        }

        // Fallback to English
        if ($langCode !== 'en' && isset($this->cache[$fieldKey]['en'])) {
            $this->logDebug("Translation fallback: {$fieldKey} ({$langCode} → en)");
            return $this->cache[$fieldKey]['en'];
        }

        // Fallback to any available translation
        if (!empty($this->cache[$fieldKey])) {
            $anyLang = array_key_first($this->cache[$fieldKey]);
            $this->logDebug("Translation fallback: {$fieldKey} ({$langCode} → {$anyLang})");
            return $this->cache[$fieldKey][$anyLang];
        }

        // Return default
        return (string)$default;
    }

    /**
     * Set translation value
     *
     * @param string $fieldKey Field identifier
     * @param string $langCode Language code
     * @param string $value Translation value
     * @return bool Success status
     */
    public function set(string $fieldKey, string $langCode, string $value): bool
    {
        try {
            $now = Factory::getDate()->toSql();

            // Check if translation exists
            $query = $this->db->getQuery(true)
                ->select('id')
                ->from($this->db->quoteName('#__joomlaboost_translations'))
                ->where($this->db->quoteName('field_key') . ' = :fieldKey')
                ->where($this->db->quoteName('lang_code') . ' = :langCode')
                ->bind(':fieldKey', $fieldKey)
                ->bind(':langCode', $langCode);

            $this->db->setQuery($query);
            $existingId = $this->db->loadResult();

            if ($existingId) {
                // Update existing
                $query = $this->db->getQuery(true)
                    ->update($this->db->quoteName('#__joomlaboost_translations'))
                    ->set($this->db->quoteName('field_value') . ' = :value')
                    ->set($this->db->quoteName('updated_at') . ' = :now')
                    ->where($this->db->quoteName('id') . ' = :id')
                    ->bind(':value', $value)
                    ->bind(':now', $now)
                    ->bind(':id', $existingId, \Joomla\Database\ParameterType::INTEGER);
            } else {
                // Insert new
                $query = $this->db->getQuery(true)
                    ->insert($this->db->quoteName('#__joomlaboost_translations'))
                    ->columns([
                        $this->db->quoteName('field_key'),
                        $this->db->quoteName('lang_code'),
                        $this->db->quoteName('field_value'),
                        $this->db->quoteName('created_at'),
                        $this->db->quoteName('updated_at')
                    ])
                    ->values(':fieldKey, :langCode, :value, :now, :now')
                    ->bind(':fieldKey', $fieldKey)
                    ->bind(':langCode', $langCode)
                    ->bind(':value', $value)
                    ->bind(':now', $now);
            }

            $this->db->setQuery($query);
            $this->db->execute();

            // Update cache
            $this->cache[$fieldKey][$langCode] = $value;

            return true;
        } catch (Exception $e) {
            $this->logDebug("Translation save error: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Get all translations for a field key
     *
     * @param string $fieldKey Field identifier
     * @return array<string, string> Array of lang_code => value
     */
    public function getAll(string $fieldKey): array
    {
        $this->loadFieldTranslations($fieldKey);
        return $this->cache[$fieldKey] ?? [];
    }

    /**
     * Get installed language codes from Joomla
     *
     * @return array<int, array{code: string, name: string, tag: string}> Language info
     */
    public function getInstalledLanguages(): array
    {
        $languages = LanguageHelper::getInstalledLanguages(0); // 0 = site languages
        $result = [];

        foreach ($languages as $lang) {
            // Extract 2-letter code from tag (e.g., 'en-GB' → 'en')
            $code = strtolower(substr($lang->lang_code, 0, 2));

            $result[] = [
                'code' => $code,
                'name' => $lang->name,
                'tag' => $lang->lang_code
            ];
        }

        return $result;
    }

    /**
     * Batch save translations from array
     *
     * @param string $fieldKey Field identifier
     * @param array<string, string> $translations Array of lang_code => value
     * @return bool Success status
     */
    public function saveBatch(string $fieldKey, array $translations): bool
    {
        $success = true;

        foreach ($translations as $langCode => $value) {
            if (!empty($value)) { // Only save non-empty values
                if (!$this->set($fieldKey, $langCode, $value)) {
                    $success = false;
                }
            }
        }

        return $success;
    }

    /**
     * Delete all translations for a field key
     *
     * @param string $fieldKey Field identifier
     * @return bool Success status
     */
    public function delete(string $fieldKey): bool
    {
        try {
            $query = $this->db->getQuery(true)
                ->delete($this->db->quoteName('#__joomlaboost_translations'))
                ->where($this->db->quoteName('field_key') . ' = :fieldKey')
                ->bind(':fieldKey', $fieldKey);

            $this->db->setQuery($query);
            $this->db->execute();

            // Clear cache
            unset($this->cache[$fieldKey]);

            return true;
        } catch (Exception $e) {
            $this->logDebug("Translation delete error: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Get current language code (2-letter ISO)
     *
     * @return string Language code (e.g., 'en', 'sr', 'ru')
     */
    private function getCurrentLanguageCode(): string
    {
        $lang = Factory::getLanguage();
        $langTag = $lang->getTag(); // e.g., 'en-GB', 'sr-RS'
        return strtolower(substr($langTag, 0, 2));
    }

    /**
     * Load all translations for a field key into cache
     *
     * @param string $fieldKey Field identifier
     * @return void
     */
    private function loadFieldTranslations(string $fieldKey): void
    {
        if (isset($this->cache[$fieldKey])) {
            return; // Already loaded
        }

        try {
            $query = $this->db->getQuery(true)
                ->select([
                    $this->db->quoteName('lang_code'),
                    $this->db->quoteName('field_value')
                ])
                ->from($this->db->quoteName('#__joomlaboost_translations'))
                ->where($this->db->quoteName('field_key') . ' = :fieldKey')
                ->bind(':fieldKey', $fieldKey);

            $this->db->setQuery($query);
            $results = $this->db->loadAssocList('lang_code', 'field_value');

            $this->cache[$fieldKey] = $results ?: [];
        } catch (Exception $e) {
            $this->logDebug("Translation load error: {$e->getMessage()}");
            $this->cache[$fieldKey] = [];
        }
    }

    /**
     * Get service key for identification
     *
     * @return string Service identifier
     */
    protected function getServiceKey(): string
    {
        return 'translation';
    }
}
