<?php

/**
 * Language Service for JoomlaBoost
 *
 * Detects active Joomla languages and generates per-language URLs.
 * Compatible with both Falang and native Joomla multilingual approach.
 *
 * @package     JoomlaBoost
 * @subpackage  Plugin.System.Services
 * @since       Joomla 4.0, PHP 8.1+
 * @author      JoomlaBoost Team
 * @copyright   (C) 2025 JoomlaBoost. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Services;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * Language Service - Detects active languages and builds multilingual URLs
 *
 * Works with:
 * - Falang (single Joomla install + Falang translation layer)
 * - Native Joomla multilingual (language associations, separate menu items)
 * - Single language sites (returns only default language)
 */
class LanguageService extends AbstractService
{
    /**
     * Cached list of active languages
     * @var array<string, object>|null  keyed by lang_code (e.g. 'en-GB')
     */
    private ?array $languages = null;

    protected function getServiceKey(): string
    {
        return 'enable_sitemap'; // Tied to sitemap feature
    }

    /**
     * Get all published content languages from Joomla
     *
     * @return array<string, object>  keyed by lang_code, each object has:
     *   - lang_code  (e.g. 'en-GB', 'sr-RS')
     *   - sef        (e.g. 'en', 'sr') — URL prefix
     *   - title      (e.g. 'English (UK)')
     *   - image      (flag code)
     *   - is_default (bool)
     */
    public function getActiveLanguages(): array
    {
        if ($this->languages !== null) {
            return $this->languages;
        }

        try {
            $db = Factory::getDbo();

            $query = $db->getQuery(true)
                ->select('lang_id, lang_code, sef, title, image, published')
                ->from('#__languages')
                ->where('published = 1')
                ->order('ordering ASC');

            $db->setQuery($query);
            $rawRows = $db->loadObjectList();

            // Key by lang_code manually
            $rows = [];
            foreach ($rawRows as $row) {
                $rows[$row->lang_code] = $row;
            }

            if (empty($rows)) {
                // Single-language site — return current language only
                $lang = Language::getInstance(Factory::getApplication()->get('language', 'en-GB'));
                $code = $lang->getTag();
                $sef  = substr($code, 0, 2);

                $single             = new \stdClass();
                $single->lang_code  = $code;
                $single->sef        = $sef;
                $single->title      = $lang->getName();
                $single->image      = $sef;
                $single->published  = 1;
                $single->is_default = true;

                $rows = [$code => $single];
            }

            // Mark default language (first published = default, or check *-language setting)
            $defaultCode        = $this->getDefaultLanguageCode();
            foreach ($rows as $code => $lang) {
                $lang->is_default = ($code === $defaultCode);
            }

            $this->languages = $rows;
            $this->logDebug('LanguageService: detected ' . count($rows) . ' active languages');
        } catch (\Throwable $e) {
            $this->logDebug('LanguageService: failed to load languages - ' . $e->getMessage());
            $this->languages = [];
        }

        return $this->languages;
    }

    /**
     * Get the default (x-default) language code
     * Reads from Joomla site language setting
     */
    public function getDefaultLanguageCode(): string
    {
        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('value')
                ->from('#__extensions')
                ->where('type = ' . $db->quote('language'))
                ->where('element = ' . $db->quote('*'))
                ->where('client_id = 0');

            $db->setQuery($query);
            $result = $db->loadResult();

            if ($result) {
                return $result;
            }
        } catch (\Throwable $e) {
            // Fall through
        }

        // Fallback: use the application's current language setting
        return Factory::getApplication()->get('language', 'en-GB');
    }

    /**
     * Generate a URL for a given Joomla internal link in a specific language
     *
     * This works by appending &lang={code} to the internal link.
     * Joomla Language Filter plugin will then generate the correct SEF URL with prefix.
     *
     * @param string $internalLink  e.g. "index.php?option=com_content&view=article&id=5&catid=3"
     * @param string $langCode      e.g. "en-GB"
     * @param string $baseUrl       site base URL
     * @return string               e.g. "https://example.com/en/article-alias/"
     */
    public function buildUrlForLanguage(string $internalLink, string $langCode, string $baseUrl): string
    {
        try {
            // Append lang param so Joomla's Language Filter router adds the right prefix
            $link = rtrim($internalLink, '&') . '&lang=' . $langCode;
            $url  = $baseUrl . Route::_($link);
            return $url;
        } catch (\Throwable $e) {
            // Fallback: just return base URL
            return $baseUrl;
        }
    }

    /**
     * Check if this is actually a multilingual site (more than 1 language)
     */
    public function isMultilingual(): bool
    {
        return count($this->getActiveLanguages()) > 1;
    }

    /**
     * Get the hreflang code for a language
     * Converts Joomla lang_code (e.g. 'en-GB') to hreflang format (e.g. 'en-gb')
     *
     * Google accepts both 'en' and 'en-US' — using full code for precision
     */
    public function getHreflangCode(string $langCode): string
    {
        // Joomla uses en-GB, Google accepts en-GB (case-insensitive)
        // We normalize to lowercase per RFC 5646 recommendation
        return strtolower($langCode);
    }

    /**
     * Check if Falang is installed and active
     */
    public function isFalangActive(): bool
    {
        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('enabled')
                ->from('#__extensions')
                ->where('type = ' . $db->quote('component'))
                ->where('element = ' . $db->quote('com_falang'));

            $db->setQuery($query);
            $enabled = $db->loadResult();
            return (bool)$enabled;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get Falang language ID for a given Joomla lang_code
     *
     * @param string $langCode  e.g. 'en-GB'
     * @return int|null  Falang language ID or null if not found
     */
    public function getFalangLanguageId(string $langCode): ?int
    {
        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('id')
                ->from('#__falang_languages')
                ->where('joomla_lang_id = (SELECT lang_id FROM #__languages WHERE lang_code = ' . $db->quote($langCode) . ')')
                ->where('published = 1');

            $db->setQuery($query);
            $id = $db->loadResult();
            return $id ? (int)$id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get Falang-translated alias for an article or category in a specific language
     *
     * @param int    $referenceId    Article or category ID
     * @param string $referenceTable 'content' for articles, 'categories' for categories
     * @param int    $falangLangId   Falang language ID
     * @return string|null  Translated alias or null if not translated
     */
    public function getFalangAlias(int $referenceId, string $referenceTable, int $falangLangId): ?string
    {
        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('value')
                ->from('#__falang_content')
                ->where('reference_id = ' . (int)$referenceId)
                ->where('reference_table = ' . $db->quote($referenceTable))
                ->where('reference_field = ' . $db->quote('alias'))
                ->where('language_id = ' . (int)$falangLangId)
                ->where('published = 1');

            $db->setQuery($query);
            $alias = $db->loadResult();
            return $alias ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
