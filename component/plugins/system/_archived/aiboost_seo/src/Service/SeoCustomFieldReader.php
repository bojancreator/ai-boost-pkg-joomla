<?php
/**
 * AI Boost — SEO Plugin — SEO Custom Field Reader
 *
 * Reads Joomla custom field values for AI Boost SEO overrides
 * (aiboost_seo_title, aiboost_seo_description, aiboost_robots) with an
 * optional Falang translation overlay.
 *
 * Strategy:
 *   1. Load the raw value from #__fields_values (standard Joomla custom fields).
 *   2. If Falang is installed (#__falang_content table exists) and the active
 *      site language is not the default language, attempt to load the Falang-
 *      translated value for that field value row.
 *   3. Return the Falang value when found; fall back to the Joomla raw value.
 *
 * Falang table schema (relevant columns):
 *   #__falang_content:
 *     - reference_table  VARCHAR  — table name without DB prefix (e.g. 'fields_values')
 *     - reference_id     INT      — PK of the row in reference_table
 *     - reference_field  VARCHAR  — column name holding the translatable text (e.g. 'value')
 *     - language_id      INT      — FK to #__falang_languages.id
 *     - value            TEXT     — translated text
 *     - published        TINYINT  — 1 = active translation
 *
 * #__languages.lang_id maps Joomla language tags (e.g. 'fr-FR') to Falang language IDs.
 *
 * @package     AiBoost\Plugin\System\AiBoostSeo
 * @version     0.7.0
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostSeo\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

final class SeoCustomFieldReader
{
    /**
     * Cached Falang availability flag (null = not yet checked).
     * @var bool|null
     */
    private static ?bool $falangAvailable = null;

    /**
     * Cached Falang language ID for the current request (-1 = not found).
     * @var int|null
     */
    private static ?int $falangLangId = null;

    /**
     * Read an AI Boost SEO custom field value for an article,
     * with Falang translation overlay applied when available.
     *
     * @param  int    $articleId  The Joomla article ID (#__content.id).
     * @param  string $fieldName  Custom field name (e.g. 'aiboost_seo_title').
     * @return string             Translated (or raw) field value, or '' if not set.
     */
    public static function read(int $articleId, string $fieldName): string
    {
        if ($articleId <= 0 || $fieldName === '') {
            return '';
        }

        try {
            [$fieldValueId, $rawValue] = self::loadNativeField($articleId, $fieldName);
        } catch (\Throwable $e) {
            return '';
        }

        if ($fieldValueId <= 0) {
            return ''; // Field not set for this article
        }

        // Falang overlay — only when Falang is installed and non-default language
        if (self::shouldAttemptFalang()) {
            try {
                $translated = self::loadFalangValue($fieldValueId);
                if ($translated !== '') {
                    return $translated;
                }
            } catch (\Throwable $e) {
                // Falang table may exist but translation absent — fall through
            }
        }

        return $rawValue;
    }

    /**
     * Reset all static caches. Intended for unit testing only.
     */
    public static function reset(): void
    {
        self::$falangAvailable = null;
        self::$falangLangId    = null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Load the native Joomla custom field value + its row ID.
     *
     * The row ID (#__fields_values.id) is needed as the Falang reference_id.
     *
     * @param  int    $articleId  Article ID.
     * @param  string $fieldName  Custom field name.
     * @return array{int, string} [fieldValueRowId, rawValue]
     */
    private static function loadNativeField(int $articleId, string $fieldName): array
    {
        $db    = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select([$db->quoteName('fv.id'), $db->quoteName('fv.value')])
            ->from($db->quoteName('#__fields_values', 'fv'))
            ->join(
                'INNER',
                $db->quoteName('#__fields', 'f')
                . ' ON ' . $db->quoteName('f.id') . ' = ' . $db->quoteName('fv.field_id')
            )
            ->where($db->quoteName('f.name') . ' = ' . $db->quote($fieldName))
            ->where($db->quoteName('fv.item_id') . ' = ' . $articleId)
            ->where($db->quoteName('f.state') . ' = 1');
        $db->setQuery($query, 0, 1);
        $row = $db->loadObject();

        if (!$row) {
            return [0, ''];
        }

        return [(int) $row->id, trim((string) ($row->value ?? ''))];
    }

    /**
     * Load the Falang-translated value for a given #__fields_values row.
     *
     * @param  int    $fieldValueId  The #__fields_values.id of the row to translate.
     * @return string                Translated value, or '' if not found.
     */
    private static function loadFalangValue(int $fieldValueId): string
    {
        $langId = self::getFalangLanguageId();
        if ($langId <= 0) {
            return '';
        }

        $db    = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('value'))
            ->from($db->quoteName('#__falang_content'))
            ->where($db->quoteName('reference_table') . ' = ' . $db->quote('fields_values'))
            ->where($db->quoteName('reference_id') . ' = ' . $fieldValueId)
            ->where($db->quoteName('reference_field') . ' = ' . $db->quote('value'))
            ->where($db->quoteName('language_id') . ' = ' . $langId)
            ->where($db->quoteName('published') . ' = 1');
        $db->setQuery($query, 0, 1);
        $val = (string) ($db->loadResult() ?? '');

        return trim($val);
    }

    /**
     * Resolve the Falang language_id for the currently active Joomla language.
     *
     * Joomla's #__languages table has both `lang_code` (BCP 47, e.g. 'fr-FR')
     * and `lang_id` (PK). Falang's own language table maps the same lang_id.
     *
     * Returns -1 when no matching language found (suppresses further lookups).
     */
    private static function getFalangLanguageId(): int
    {
        if (self::$falangLangId !== null) {
            return self::$falangLangId;
        }

        try {
            $langTag = Factory::getLanguage()->getTag(); // e.g. 'fr-FR'
            $db      = Factory::getDbo();
            $query   = $db->getQuery(true)
                ->select($db->quoteName('lang_id'))
                ->from($db->quoteName('#__languages'))
                ->where($db->quoteName('lang_code') . ' = ' . $db->quote($langTag));
            $db->setQuery($query, 0, 1);
            $id = (int) ($db->loadResult() ?? 0);
            self::$falangLangId = $id > 0 ? $id : -1;
        } catch (\Throwable $e) {
            self::$falangLangId = -1;
        }

        return self::$falangLangId;
    }

    /**
     * Return true when Falang is installed and the active language is non-default.
     *
     * Caches the table-existence check for the entire request.
     */
    private static function shouldAttemptFalang(): bool
    {
        // Skip Falang overlay for the default (English) language
        try {
            $tag = Factory::getLanguage()->getTag();
            if (str_starts_with(strtolower($tag), 'en')) {
                return false;
            }
        } catch (\Throwable $e) {
            return false;
        }

        if (self::$falangAvailable !== null) {
            return self::$falangAvailable;
        }

        try {
            $db     = Factory::getDbo();
            $prefix = $db->getPrefix();
            $db->setQuery('SHOW TABLES LIKE ' . $db->quote($prefix . 'falang_content'));
            self::$falangAvailable = (bool) $db->loadResult();
        } catch (\Throwable $e) {
            self::$falangAvailable = false;
        }

        return self::$falangAvailable;
    }
}
