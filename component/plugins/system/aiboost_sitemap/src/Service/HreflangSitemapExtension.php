<?php
/**
 * AI Boost — Hreflang Sitemap Extension (Pro)
 *
 * Adds <xhtml:link rel="alternate" hreflang="..."> entries inside each <url>
 * block, telling Google about language alternates for every article.
 *
 * Two-strategy approach:
 *   1. Association strategy (default): query #__associations to find all
 *      language-linked content items, JOIN #__content to get language tag and
 *      alias, build SEF URLs via Route::_(), emit one <xhtml:link> per
 *      language plus x-default.
 *   2. All-active-languages strategy (fallback): when a content item has no
 *      Joomla language associations, but the site has multiple published
 *      languages (multilingual Joomla install), emit x-default only, pointing
 *      to the current item URL. This prevents Google penalties for items
 *      deliberately assigned to a single language without associations.
 *      Use getPublishedLanguages() to detect the multilingual context.
 *
 * Requires the `xhtml:` namespace declared on <urlset>:
 *   xmlns:xhtml="http://www.w3.org/1999/xhtml"
 *
 * DatabaseInterface is injected; $defaultLang is resolved by the Extension class
 * (Factory::getApplication()->get('language', 'en-GB')). This service makes
 * no Factory:: calls.
 *
 * @package     AiBoost\Plugin\System\AiBoostSitemap
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Plugin\System\AiBoostSitemap\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;

class HreflangSitemapExtension
{
    /** Cache: cacheKey → [['hreflang'=>'en-gb','href'=>'https://...']] */
    private array $cache = [];

    /**
     * Cached list of all published languages from #__languages.
     * null = not yet loaded; [] = loaded, site is mono-lingual.
     *
     * @var array<int,array{lang_code:string,sef:string}>|null
     */
    private ?array $publishedLanguages = null;

    public function __construct(
        private readonly string $baseUrl,
        private readonly DatabaseInterface $db,
        private readonly string $defaultLang = 'en-GB',
    ) {}

    /**
     * Return all published languages (lang_code + sef) from #__languages.
     * Used by callers to decide whether the all-active-languages strategy
     * should be applied (site is multilingual ↔ count > 1).
     *
     * @return array<int,array{lang_code:string,sef:string}>
     */
    public function getPublishedLanguages(): array
    {
        if ($this->publishedLanguages !== null) {
            return $this->publishedLanguages;
        }

        try {
            $query = $this->db->getQuery(true)
                ->select([$this->db->quoteName('lang_code'), $this->db->quoteName('sef')])
                ->from($this->db->quoteName('#__languages'))
                ->where($this->db->quoteName('published') . ' = 1')
                ->order($this->db->quoteName('ordering') . ' ASC');

            $this->db->setQuery($query);
            $rows = $this->db->loadObjectList();

            $this->publishedLanguages = [];
            foreach ($rows as $row) {
                $this->publishedLanguages[] = [
                    'lang_code' => (string) $row->lang_code,
                    'sef'       => (string) $row->sef,
                ];
            }
        } catch (\Throwable) {
            $this->publishedLanguages = [];
        }

        return $this->publishedLanguages;
    }

    /**
     * Render all <xhtml:link rel="alternate"> tags for a single article.
     *
     * @param  int    $articleId  Content item ID.
     * @param  string $language   Language tag of the current item (e.g. 'en-GB').
     * @return string             XML fragment (empty if site is monolingual).
     */
    public function renderForArticle(int $articleId, string $language, string $currentUrl = ''): string
    {
        if ($this->usesRegisteredArticleLanguages($language)) {
            return $this->renderForRegisteredLanguages($currentUrl);
        }

        $alternates = $this->resolveAlternates($articleId, $language, 'com_content.item');

        // Association strategy: 2+ linked translations found
        if (count($alternates) >= 2) {
            return $this->renderAlternates($alternates);
        }

        return $this->renderArticleWithoutAssociations($language, $currentUrl);
    }

    private function usesRegisteredArticleLanguages(string $language): bool
    {
        // Language-neutral Joomla items ('*') have no valid hreflang value.
        return $this->hreflangMode() === 'falang' || $language === '' || $language === '*';
    }

    private function renderArticleWithoutAssociations(string $language, string $currentUrl): string
    {
        if ($currentUrl === '' || count($this->getPublishedLanguages()) <= 1) {
            return '';
        }

        if ($this->hreflangMode() !== 'joomla_native') {
            $registered = $this->renderForRegisteredLanguages($currentUrl);
            if ($registered !== '') {
                return $registered;
            }
        }

        return $this->renderSingleLanguageFallback($language, $currentUrl);
    }

    private function renderSingleLanguageFallback(string $language, string $currentUrl): string
    {
        return $this->renderAlternateLink(strtolower(str_replace('_', '-', $language)), $currentUrl)
            . $this->renderAlternateLink('x-default', $currentUrl);
    }

    /** @param array<int,array{hreflang:string,href:string,is_default:bool}> $alternates */
    private function renderAlternates(array $alternates): string
    {
        $xml      = '';
        $xDefault = null;

        foreach ($alternates as $alt) {
            $xml .= $this->renderAlternateLink($alt['hreflang'], $alt['href']);

            if ($xDefault === null || !empty($alt['is_default'])) {
                $xDefault = $alt['href'];
            }
        }

        return $xDefault !== null
            ? $xml . $this->renderAlternateLink('x-default', $xDefault)
            : $xml;
    }

    private function renderAlternateLink(string $hreflang, string $href): string
    {
        return '    <xhtml:link rel="alternate" hreflang="'
            . htmlspecialchars($hreflang, ENT_XML1)
            . '" href="'
            . htmlspecialchars($href, ENT_XML1)
            . '"/>' . "\n";
    }

    /**
     * Render all <xhtml:link rel="alternate"> tags for a single menu item.
     *
     * @param  int    $menuId      Menu item ID from #__menu.
     * @param  string $currentUrl  Current sitemap URL for Falang fallback mode.
     * @return string          XML fragment (empty if no associations found).
     */
    public function renderForMenu(int $menuId, string $currentUrl = ''): string
    {
        if ($this->hreflangMode() === 'falang') {
            return $this->renderForRegisteredLanguages($currentUrl);
        }

        $alternates = $this->resolveMenuAlternates($menuId);

        if (count($alternates) < 2) {
            return $this->hreflangMode() === 'auto'
                ? $this->renderForRegisteredLanguages($currentUrl)
                : '';
        }

        $xml      = '';
        $xDefault = null;

        foreach ($alternates as $alt) {
            $hreflang = htmlspecialchars($alt['hreflang'], ENT_XML1);
            $href     = htmlspecialchars($alt['href'],     ENT_XML1);

            $xml .= '    <xhtml:link rel="alternate" hreflang="' . $hreflang . '" href="' . $href . '"/>' . "\n";

            if ($xDefault === null || !empty($alt['is_default'])) {
                $xDefault = $href;
            }
        }

        if ($xDefault !== null) {
            $xml .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . $xDefault . '"/>' . "\n";
        }

        return $xml;
    }

    private function renderForRegisteredLanguages(string $currentUrl): string
    {
        if ($this->hreflangMode() === 'joomla_native') {
            return '';
        }

        if ($currentUrl === '' || !class_exists('AiBoost\\Lib\\BridgeDetector')) {
            return '';
        }

        $languages = \AiBoost\Lib\BridgeDetector::getSitemapLanguages();
        if (count($languages) < 2) {
            return '';
        }

        return $this->renderRegisteredLanguageLinks(
            $languages,
            \AiBoost\Lib\BridgeDetector::getPrimaryLanguageSef(),
            $this->pathWithoutLanguagePrefix($currentUrl, $this->languageSefs($languages)),
            $currentUrl
        );
    }

    private function hreflangMode(): string
    {
        if (!class_exists('AiBoost\\Lib\\BridgeDetector')) {
            return 'auto';
        }

        return \AiBoost\Lib\BridgeDetector::getHreflangMode();
    }

    /** @param array<int,array<string,string>> $languages */
    private function renderRegisteredLanguageLinks(array $languages, string $primarySef, string $path, string $currentUrl): string
    {
        $xml       = '';
        $xDefault  = '';

        foreach ($languages as $lang) {
            $linkData = $this->registeredLanguageLinkData($lang, $path);
            if ($linkData === null) {
                continue;
            }

            [$sef, $hreflang, $href] = $linkData;
            $xml .= $this->renderAlternateLink($hreflang, $href);

            if ($sef === $primarySef) {
                $xDefault = $href;
            }
        }

        return $xml !== ''
            ? $xml . $this->renderAlternateLink('x-default', $xDefault ?: $currentUrl)
            : '';
    }

    /** @param array<string,string> $lang */
    private function registeredLanguageLinkData(array $lang, string $path): ?array
    {
        $sef      = trim((string) ($lang['sef'] ?? ''));
        $langCode = trim((string) ($lang['lang_code'] ?? ''));

        return $sef !== '' && $langCode !== ''
            ? [$sef, strtolower(str_replace('_', '-', $langCode)), rtrim($this->baseUrl, '/') . '/' . $sef . $path]
            : null;
    }

    /** @param array<int,array<string,string>> $languages */
    private function languageSefs(array $languages): array
    {
        return array_values(array_filter(array_map(
            static fn (array $lang): string => (string) ($lang['sef'] ?? ''),
            $languages
        )));
    }

    /** @param array<int,string> $allSefs */
    private function pathWithoutLanguagePrefix(string $url, array $allSefs): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        if ($path === '') {
            return '/';
        }

        $cleanPath = '/' . ltrim($path, '/');
        foreach ($allSefs as $sef) {
            if ($sef === '') {
                continue;
            }
            if ($cleanPath === '/' . $sef) {
                return '/';
            }
            if (str_starts_with($cleanPath, '/' . $sef . '/')) {
                return substr($cleanPath, strlen('/' . $sef)) ?: '/';
            }
        }

        return $cleanPath;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Query all language associations for a content article (com_content.item).
     *
     * @return array<int,array{hreflang:string,href:string,is_default:bool}>
     */
    private function resolveAlternates(int $articleId, string $language, string $context): array
    {
        $cacheKey = $context . '_' . $articleId;

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $result = [];

        try {
            $db = $this->db;

            $query = $db->getQuery(true)
                ->select($db->quoteName('key'))
                ->from($db->quoteName('#__associations'))
                ->where($db->quoteName('context') . ' = ' . $db->quote($context))
                ->where($db->quoteName('id') . ' = ' . $articleId);
            $db->setQuery($query, 0, 1);
            $assocKey = (string) ($db->loadResult() ?? '');

            if ($assocKey === '') {
                $this->cache[$cacheKey] = [];
                return [];
            }

            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('a.id'),
                    $db->quoteName('a.language'),
                    $db->quoteName('a.alias'),
                    $db->quoteName('a.catid'),
                ])
                ->from($db->quoteName('#__content', 'a'))
                ->join(
                    'INNER',
                    $db->quoteName('#__associations', 'assoc')
                    . ' ON assoc.id = a.id'
                    . ' AND assoc.context = ' . $db->quote($context)
                )
                ->where('assoc.key = ' . $db->quote($assocKey))
                ->where('a.state = 1');

            $db->setQuery($query);
            $rows = $db->loadObjectList();
        } catch (\Throwable) {
            $this->cache[$cacheKey] = [];
            return [];
        }

        foreach ($rows as $row) {
            $hreflang = strtolower(str_replace('_', '-', (string) $row->language));
            $href     = $this->buildArticleUrl((int) $row->id, (string) $row->alias, (int) $row->catid);

            if ($href === '') {
                continue;
            }

            $result[] = [
                'hreflang'   => $hreflang,
                'href'       => $href,
                'is_default' => ($row->language === $this->defaultLang),
            ];
        }

        $this->cache[$cacheKey] = $result;
        return $result;
    }

    /**
     * Query language associations for a menu item via com_menus.item context.
     *
     * @return array<int,array{hreflang:string,href:string,is_default:bool}>
     */
    private function resolveMenuAlternates(int $menuId): array
    {
        $cacheKey = 'menu_' . $menuId;

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $result = [];

        try {
            $db = $this->db;

            $query = $db->getQuery(true)
                ->select($db->quoteName('key'))
                ->from($db->quoteName('#__associations'))
                ->where($db->quoteName('context') . ' = ' . $db->quote('com_menus.item'))
                ->where($db->quoteName('id') . ' = ' . $menuId);
            $db->setQuery($query, 0, 1);
            $assocKey = (string) ($db->loadResult() ?? '');

            if ($assocKey === '') {
                $this->cache[$cacheKey] = [];
                return [];
            }

            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('m.id'),
                    $db->quoteName('m.language'),
                    $db->quoteName('m.link'),
                ])
                ->from($db->quoteName('#__menu', 'm'))
                ->join(
                    'INNER',
                    $db->quoteName('#__associations', 'assoc')
                    . ' ON assoc.id = m.id'
                    . ' AND assoc.context = ' . $db->quote('com_menus.item')
                )
                ->where('assoc.key = ' . $db->quote($assocKey))
                ->where('m.published = 1')
                ->where('m.client_id = 0');

            $db->setQuery($query);
            $rows = $db->loadObjectList();
        } catch (\Throwable) {
            $this->cache[$cacheKey] = [];
            return [];
        }

        foreach ($rows as $row) {
            $hreflang = strtolower(str_replace('_', '-', (string) $row->language));
            $href     = $this->buildMenuItemUrl((int) $row->id, (string) $row->link);

            if ($href === '') {
                continue;
            }

            $result[] = [
                'hreflang'   => $hreflang,
                'href'       => $href,
                'is_default' => ($row->language === $this->defaultLang),
            ];
        }

        $this->cache[$cacheKey] = $result;
        return $result;
    }

    private function buildArticleUrl(int $id, string $alias, int $catid): string
    {
        try {
            $sef = Route::_(
                'index.php?option=com_content&view=article'
                . '&id=' . $id . ':' . $alias
                . '&catid=' . $catid,
                false
            );

            if (str_starts_with($sef, 'http://') || str_starts_with($sef, 'https://')) {
                return $sef;
            }

            return $this->baseUrl . '/' . ltrim($sef, '/');
        } catch (\Throwable) {
            return '';
        }
    }

    private function buildMenuItemUrl(int $menuId, string $link): string
    {
        if ($link === '' || !str_starts_with($link, 'index.php')) {
            return '';
        }

        try {
            $sef = Route::_($link . '&Itemid=' . $menuId, false);

            if (str_starts_with($sef, 'http://') || str_starts_with($sef, 'https://')) {
                return $sef;
            }

            return $this->baseUrl . '/' . ltrim($sef, '/');
        } catch (\Throwable) {
            return '';
        }
    }
}
