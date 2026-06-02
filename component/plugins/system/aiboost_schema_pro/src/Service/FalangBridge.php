<?php
/**
 * AI Boost — FalangBridge
 *
 * Detects whether Falang (or a compatible multilingual translation component)
 * is installed and provides translated values for key Organisation fields that
 * appear in Schema.org JSON-LD output.
 *
 * Strategy:
 *   1. When the current site language is the default (en-GB or any en-* tag),
 *      the raw plugin param values are used without querying the database.
 *   2. When a non-default language is active and the #__aiboost_translations
 *      table exists (installed by com_aiboost), translated values are fetched
 *      for the given language and fall back to the English param value when no
 *      translation row is found.
 *   3. When neither condition is met, the original param value is returned.
 *
 * Fields translated via this bridge:
 *   org_name, org_description, org_address_street, org_address_city
 *
 * @package     AiBoost\Plugin\System\AiBoostSchema
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostSchemaPro\Service;

defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;

final class FalangBridge
{
    private ?bool  $available = null;
    private ?array $cache     = null;

    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly string $langTag,
    ) {}

    /**
     * Return the translated value for $key in the current site language,
     * or $fallback when no translation is available.
     *
     * This method is safe to call even when Falang is not installed — it simply
     * returns the $fallback value without any database overhead after the first
     * availability check.
     *
     * @param  string $key      Setting key as stored in #__aiboost_translations.
     * @param  string $fallback The raw English param value.
     * @return string           Translated value, or $fallback.
     */
    public function translate(string $key, string $fallback): string
    {
        if ($this->isDefaultLanguage()) {
            return $fallback;
        }

        if (!$this->isAvailable()) {
            return $fallback;
        }

        $all = $this->loadAll();
        $val = $all[$key] ?? '';
        return $val !== '' ? $val : $fallback;
    }

    /**
     * Return true when translations may differ from English params.
     *
     * Useful to skip the bridge entirely when the site is in the default language.
     */
    public function isMultilingual(): bool
    {
        return !$this->isDefaultLanguage() && $this->isAvailable();
    }

    // ──────────────────────────────────────────────────────────────────────────

    /** Return true when $langTag is any English variant (en-GB, en-US, …). */
    private function isDefaultLanguage(): bool
    {
        return str_starts_with(strtolower($this->langTag), 'en');
    }

    /**
     * Check whether the #__aiboost_translations table exists in the database.
     * Result is cached for the lifetime of this object.
     */
    private function isAvailable(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }

        try {
            $db     = $this->db;
            $prefix = $db->getPrefix();
            $db->setQuery("SHOW TABLES LIKE " . $db->quote("{$prefix}aiboost_translations"));
            $this->available = (bool) $db->loadResult();
        } catch (\Throwable $e) {
            $this->available = false;
        }

        return $this->available;
    }

    /**
     * Load all translation rows for the current langTag from #__aiboost_translations.
     *
     * Returns an associative array keyed by setting_key.
     *
     * @return array<string, string>
     */
    private function loadAll(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $this->cache = [];

        try {
            $db    = $this->db;
            $query = $db->getQuery(true)
                ->select([$db->quoteName('setting_key'), $db->quoteName('value')])
                ->from($db->quoteName('#__aiboost_translations'))
                ->where($db->quoteName('lang_code') . ' = ' . $db->quote($this->langTag));
            $db->setQuery($query);
            foreach ($db->loadObjectList() ?: [] as $row) {
                $key = (string) ($row->setting_key ?? '');
                $val = (string) ($row->value ?? '');
                if ($key !== '' && $val !== '') {
                    $this->cache[$key] = $val;
                }
            }
        } catch (\Throwable $e) {
            // Table may exist but be empty or schema may differ — fall through
        }

        return $this->cache;
    }
}
