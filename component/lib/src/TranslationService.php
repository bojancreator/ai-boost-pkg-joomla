<?php
/**
 * AI Boost Shared Library — Translation Service
 *
 * Reads per-field, per-language text values from #__aiboost_translations.
 * Falls back to the default (en-GB) value when a translation is absent.
 *
 * All rows are loaded in a single query on first access (lazy loading).
 * Subsequent calls are served from the in-memory cache.
 *
 * Usage:
 *   $ts = new TranslationService($db);
 *   $name = $ts->get('org_name', 'de-DE', $fallbackEnValue);
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib;

defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;

class TranslationService
{
    /** @var array<string, array<string, string>> field_key → [lang_code → value] */
    private array $cache = [];

    private bool $loaded = false;

    /**
     * @param DatabaseInterface $db
     * @param string $defaultLangCode  The installation default language (e.g. 'en-GB').
     *        Values in this language are the source and are never looked up in #__aiboost_translations.
     *        Defaults to 'en-GB' for back-compat; callers should pass
     *        (string) Factory::getApplication()->get('language', 'en-GB') instead.
     */
    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly string $defaultLangCode = 'en-GB'
    ) {}

    /**
     * Get the translated value for a field in the given language.
     * Falls back to $default when no translation exists for the lang/field combo,
     * or when the stored value is an empty string.
     *
     * @param string $fieldKey  The translation field key (e.g. 'org_name').
     * @param string $langCode  The Joomla language tag (e.g. 'de-DE').
     *                          Passing the default language always returns $default unchanged.
     * @param string $default   Fallback value when no translation exists.
     */
    public function get(string $fieldKey, string $langCode = '', string $default = ''): string
    {
        $effectiveLang = $langCode !== '' ? $langCode : $this->defaultLangCode;

        // Source language — return the master value unchanged
        if ($effectiveLang === $this->defaultLangCode) {
            return $default;
        }

        $this->ensureLoaded();

        $entry = $this->cache[$fieldKey] ?? [];
        $value = $entry[$effectiveLang] ?? '';

        return ($value !== '') ? $value : $default;
    }

    /**
     * Return the installation default language code.
     */
    public function getDefaultLangCode(): string
    {
        return $this->defaultLangCode;
    }

    /**
     * Return all translations for a given field, keyed by lang_code.
     *
     * @param  string $fieldKey
     * @return array<string, string>
     */
    public function getAll(string $fieldKey): array
    {
        $this->ensureLoaded();
        return $this->cache[$fieldKey] ?? [];
    }

    /**
     * Return true when at least one non-empty translation exists for $langCode.
     */
    public function hasTranslations(string $langCode): bool
    {
        if ($langCode === '' || $langCode === $this->defaultLangCode) {
            return false;
        }
        $this->ensureLoaded();
        foreach ($this->cache as $langs) {
            if (isset($langs[$langCode]) && $langs[$langCode] !== '') {
                return true;
            }
        }
        return false;
    }

    /**
     * Invalidate the in-memory cache (e.g. after a save).
     */
    public function clearCache(): void
    {
        $this->cache  = [];
        $this->loaded = false;
    }

    private function ensureLoaded(): void
    {
        if ($this->loaded) {
            return;
        }

        try {
            $rows = $this->db
                ->setQuery(
                    $this->db->getQuery(true)
                        ->select([$this->db->quoteName('field_key'), $this->db->quoteName('lang_code'), $this->db->quoteName('field_value')])
                        ->from($this->db->quoteName('#__aiboost_translations'))
                )
                ->loadObjectList();

            foreach ($rows as $row) {
                $this->cache[$row->field_key][$row->lang_code] = $row->field_value ?? '';
            }
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning('[AiBoost] TranslationService: could not load translations: ' . $e->getMessage());
        }

        $this->loaded = true;
    }
}
