<?php
/**
 * AI Boost Shared Library — Language Service
 *
 * Resolves the active language tag for the current request and
 * provides helpers for hreflang / language-aware logic in plugins.
 *
 * DatabaseInterface and AppContextInterface are injected so this service
 * makes no Factory:: / Uri:: calls.
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Lib;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Multilanguage;
use Joomla\Database\DatabaseInterface;

class LanguageService
{
    public function __construct(
        private readonly AppContextInterface $ctx,
        private readonly DatabaseInterface   $db
    ) {}

    /**
     * Return the language tag for the current request (e.g. 'en-GB', 'de-DE').
     * Falls back to 'en-GB' if nothing is active.
     */
    public function getCurrentTag(): string
    {
        try {
            return $this->ctx->getActiveLanguage();
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning('[AiBoost] LanguageService: could not get current language tag: ' . $e->getMessage());
            return 'en-GB';
        }
    }

    /**
     * Return all published content languages as an array of objects with
     * at minimum `lang_code` and `sef` properties (from #__languages).
     *
     * Requires Joomla Multilanguage to be active (plg_system_languagefilter
     * enabled). Use getInstalledLanguages() for admin-UI purposes where
     * Multilanguage activation is not a prerequisite.
     *
     * @return list<object>
     */
    public function getPublishedLanguages(): array
    {
        if (!Multilanguage::isEnabled()) {
            return [];
        }

        return $this->getInstalledLanguages();
    }

    /**
     * Return all installed + published languages regardless of whether
     * Joomla Multilanguage (languagefilter plugin) is active.
     *
     * Used by the admin UI (TranslationExpander) so that language rows are
     * shown whenever more than one language pack is installed, even if the
     * site has not yet activated the Joomla Multilanguage routing system.
     *
     * @return list<object>
     */
    public function getInstalledLanguages(): array
    {
        try {
            $query = $this->db->getQuery(true)
                ->select(['lang_code', 'sef', 'title'])
                ->from('#__languages')
                ->where($this->db->quoteName('published') . '=1')
                ->order('ordering ASC');

            return $this->db->setQuery($query)->loadObjectList() ?? [];
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning('[AiBoost] LanguageService: could not load installed languages: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Return true when Joomla Multilanguage is active and more than one
     * content language is published.
     */
    public function isMultilingual(): bool
    {
        return Multilanguage::isEnabled() && count($this->getPublishedLanguages()) > 1;
    }
}
