<?php
/**
 * AI Boost — SEO Plugin — Hreflang Builder (Pro)
 *
 * Builds <link rel="alternate" hreflang="..."> tags for multilingual pages.
 *
 * How it works for article pages:
 *   1. Looks up the association key for the current article in #__associations.
 *   2. Fetches all article IDs sharing that key.
 *   3. Joins #__content to resolve the language tag for each associated article.
 *   4. Builds absolute SEF URLs for each language version.
 *   5. Adds an x-default link for the configured default language.
 *
 * How it works for non-article pages:
 *   - Uses the `associations` property on the active Joomla menu item (Joomla 4+).
 *
 * Notes:
 *   - The #__associations table does NOT have a `language` column. Language is
 *     resolved by joining on the associated content/menu record.
 *   - Only articles with state=1 (published) are included.
 *
 * @package     AiBoost\Plugin\System\AiBoostSeo
 * @version     0.7.0
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostSeo\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

class HreflangBuilder
{
    private Registry $params;
    private CMSApplication $app;

    public function __construct(Registry $params, CMSApplication $app)
    {
        $this->params = $params;
        $this->app    = $app;
    }

    /**
     * Build all hreflang <link> tags for the current page.
     *
     * @param  int      $articleId  Article ID (0 for non-article pages).
     * @return string[]             Array of <link ...> tag strings.
     */
    public function buildTags(int $articleId): array
    {
        if ($articleId > 0) {
            $associations = $this->getArticleAssociations($articleId);
        } else {
            $associations = $this->getMenuAssociations();
        }

        if (empty($associations)) {
            return [];
        }

        $xDefault    = trim((string) $this->params->get('x_default_language', 'en-GB'));
        $xDefaultUrl = '';
        $tags        = [];

        foreach ($associations as $lang => $url) {
            if ($url === '') {
                continue;
            }

            // Normalise to BCP 47 (replaces underscore with hyphen, e.g. en_GB → en-GB)
            $hreflang = str_replace('_', '-', $lang);

            $tags[] = '<link rel="alternate" hreflang="'
                . htmlspecialchars($hreflang, ENT_QUOTES, 'UTF-8')
                . '" href="'
                . htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
                . '">';

            if ($hreflang === str_replace('_', '-', $xDefault)) {
                $xDefaultUrl = $url;
            }
        }

        if ($xDefaultUrl !== '') {
            $tags[] = '<link rel="alternate" hreflang="x-default" href="'
                . htmlspecialchars($xDefaultUrl, ENT_QUOTES, 'UTF-8')
                . '">';
        }

        return $tags;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Association resolvers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * For article pages: resolve language versions via #__associations + #__content.
     *
     * #__associations schema:
     *   - id        (int)    — the item ID (article ID for com_content.item)
     *   - context   (string) — e.g. 'com_content.item'
     *   - key       (string) — UUID grouping all language versions of one item
     *
     * Language tag is NOT in #__associations; it is in #__content.language.
     *
     * @param  int                   $articleId  The current article ID.
     * @return array<string, string>             language tag → absolute URL map.
     */
    private function getArticleAssociations(int $articleId): array
    {
        try {
            $db = Factory::getDbo();

            // Step 1: get the group key for this article
            $keyQuery = $db->getQuery(true)
                ->select($db->quoteName('key'))
                ->from($db->quoteName('#__associations'))
                ->where($db->quoteName('context') . ' = ' . $db->quote('com_content.item'))
                ->where($db->quoteName('id') . ' = ' . $articleId);
            $db->setQuery($keyQuery, 0, 1);
            $key = (string) ($db->loadResult() ?? '');

            if ($key === '') {
                return []; // Article is not part of any language association group
            }

            // Step 2: fetch all article IDs in the group + their language from #__content
            // We JOIN #__content to get the language tag (not available in #__associations).
            $assocQuery = $db->getQuery(true)
                ->select([$db->quoteName('a.id'), $db->quoteName('c.language')])
                ->from($db->quoteName('#__associations', 'a'))
                ->join(
                    'INNER',
                    $db->quoteName('#__content', 'c')
                    . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('a.id')
                )
                ->where($db->quoteName('a.context') . ' = ' . $db->quote('com_content.item'))
                ->where($db->quoteName('a.key') . ' = ' . $db->quote($key))
                ->where($db->quoteName('c.state') . ' = 1'); // published articles only
            $db->setQuery($assocQuery);
            $rows = $db->loadObjectList() ?: [];
        } catch (\Throwable $e) {
            return [];
        }

        $associations = [];
        foreach ($rows as $row) {
            $lang = trim((string) ($row->language ?? ''));
            $id   = (int) ($row->id ?? 0);

            // Skip wildcard language (*) and invalid entries
            if ($lang === '' || $lang === '*' || $id <= 0) {
                continue;
            }

            $url = $this->buildArticleUrl($id, $lang);
            if ($url !== '') {
                $associations[$lang] = $url;
            }
        }

        return $associations;
    }

    /**
     * For non-article pages: use the `associations` property on the active menu item.
     *
     * In Joomla 4+, the active menu item object carries an `associations` array
     * (language → menu item ID) populated by the Language Associations component.
     *
     * @return array<string, string>  language tag → absolute URL map.
     */
    private function getMenuAssociations(): array
    {
        try {
            $menu       = $this->app->getMenu();
            $activeItem = $menu ? $menu->getActive() : null;
            if (!$activeItem) {
                return [];
            }

            $assoc = $activeItem->associations ?? [];
            if (empty($assoc) || !is_array($assoc)) {
                return [];
            }

            $result = [];
            foreach ($assoc as $lang => $menuItemId) {
                $item = $menu->getItem((int) $menuItemId);
                if (!$item) {
                    continue;
                }
                $url = Route::_($item->link . '&Itemid=' . $item->id, true, Route::TLS_IGNORE, true);
                if ($url !== '') {
                    $result[(string) $lang] = $url;
                }
            }
            return $result;
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // URL builder
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build an absolute SEF URL for a given article + language.
     *
     * @param  int    $articleId  The article ID.
     * @param  string $lang       Language tag (e.g. 'fr-FR').
     * @return string             Absolute URL, or '' on failure.
     */
    private function buildArticleUrl(int $articleId, string $lang): string
    {
        try {
            $internalUrl = 'index.php?option=com_content&view=article&id=' . $articleId
                . '&lang=' . urlencode($lang);

            // Route::_ with absolute=true builds a full URL including scheme+host
            $url = Route::_($internalUrl, true, Route::TLS_IGNORE, true);

            // Fallback: prepend base URL if result is relative
            if ($url !== '' && strncmp($url, 'http', 4) !== 0) {
                $url = rtrim(Uri::base(), '/') . '/' . ltrim($url, '/');
            }

            return (string) $url;
        } catch (\Throwable $e) {
            return '';
        }
    }
}
