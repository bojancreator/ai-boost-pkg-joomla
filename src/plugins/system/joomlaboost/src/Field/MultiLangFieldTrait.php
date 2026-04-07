<?php

/**
 * MultiLang Field Trait for JoomlaBoost
 *
 * Shared helpers for dynamic language-aware form fields.
 * Reads active site languages from #__languages DB table instead of filesystem,
 * ensuring Falang / multilingual setups are fully supported.
 *
 * @package     JoomlaBoost
 * @subpackage  Plugin.System.Field
 * @since       0.9.7
 */

declare(strict_types=1);

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Field;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Shared functionality for multilingual plugin param fields.
 *
 * @since 0.9.7
 */
trait MultiLangFieldTrait
{
    /**
     * Get site languages for form field rendering.
     *
     * Priority:
     * 1. #__falang_languages — Falang translation overlay (no table-existence pre-check,
     *    just catch DB error if table missing)
     * 2. #__languages        — Native Joomla multilingual
     * 3. English fallback
     *
     * @return array<int, array{code: string, name: string, tag: string}>
     */
    private function getInstalledLanguages(): array
    {
        $db = Factory::getDbo();

        // ── 1. Falang ─────────────────────────────────────────────────────────
        // Do NOT pre-check table existence (getTableList can be case-sensitive).
        // Simply try the query and fall through on any DB exception.
        try {
            $db->setQuery(
                'SELECT fl.lang_code,'
                . ' COALESCE(l.title, fl.lang_code) AS name'
                . ' FROM ' . $db->quoteName('#__falang_languages') . ' AS fl'
                . ' LEFT JOIN ' . $db->quoteName('#__languages') . ' AS l'
                . '   ON l.lang_id = fl.joomla_lang_id'
                . ' ORDER BY fl.id ASC'
            );
            $rows = $db->loadObjectList();

            if (!empty($rows)) {
                $seen   = [];
                $result = [];
                foreach ($rows as $row) {
                    $code = strtolower(substr((string) $row->lang_code, 0, 2));
                    if (!isset($seen[$code])) {
                        $seen[$code] = true;
                        $result[]    = [
                            'code' => $code,
                            'name' => (string) $row->name,
                            'tag'  => (string) $row->lang_code,
                        ];
                    }
                }
                if (!empty($result)) {
                    return $result;
                }
            }
        } catch (\Throwable $e) {
            // #__falang_languages doesn't exist — fall through to #__languages
        }

        // ── 2. Native Joomla #__languages ────────────────────────────────────
        try {
            $db->setQuery(
                'SELECT lang_code, title AS name'
                . ' FROM ' . $db->quoteName('#__languages')
                . ' WHERE published = 1'
                . ' ORDER BY ordering ASC'
            );
            $rows = $db->loadObjectList();

            if (!empty($rows)) {
                $seen   = [];
                $result = [];
                foreach ($rows as $row) {
                    $code = strtolower(substr((string) $row->lang_code, 0, 2));
                    if (!isset($seen[$code])) {
                        $seen[$code] = true;
                        $result[]    = [
                            'code' => $code,
                            'name' => (string) $row->name,
                            'tag'  => (string) $row->lang_code,
                        ];
                    }
                }
                if (!empty($result)) {
                    return $result;
                }
            }
        } catch (\Throwable $e) {
            // fall through
        }

        // ── 3. Fallback ───────────────────────────────────────────────────────
        return [['code' => 'en', 'name' => 'English', 'tag' => 'en-GB']];
    }

    /**
     * Read plugin params from DB for reading existing values.
     */
    private function getPluginParams(): Registry
    {
        try {
            $plugin = PluginHelper::getPlugin('system', 'joomlaboost');
            return new Registry($plugin->params ?? '{}');
        } catch (\Throwable $e) {
            return new Registry();
        }
    }

    /**
     * Language flag emoji map.
     */
    private function getFlag(string $code): string
    {
        $map = [
            'en' => '🇬🇧', 'sr' => '🇷🇸', 'me' => '🇲🇪', 'hr' => '🇭🇷',
            'bs' => '🇧🇦', 'ru' => '🇷🇺', 'de' => '🇩🇪', 'fr' => '🇫🇷',
            'it' => '🇮🇹', 'es' => '🇪🇸', 'pt' => '🇵🇹', 'nl' => '🇳🇱',
            'pl' => '🇵🇱', 'cs' => '🇨🇿', 'sk' => '🇸🇰', 'hu' => '🇭🇺',
            'ro' => '🇷🇴', 'bg' => '🇧🇬', 'sl' => '🇸🇮', 'uk' => '🇺🇦',
            'tr' => '🇹🇷', 'ar' => '🇸🇦', 'zh' => '🇨🇳', 'ja' => '🇯🇵',
        ];
        return $map[$code] ?? '🏳️';
    }
}
