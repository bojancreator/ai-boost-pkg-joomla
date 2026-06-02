<?php
/**
 * AI Boost — Hreflang & Multilingual SEO Plugin (standalone, Joomla 4/5/6)
 *
 * Handles: <link rel="alternate" hreflang> tags, Falang Pro integration,
 *          language alternates in XML sitemap (standalone, no sitemap plugin needed).
 * Standalone: reads all settings from Joomla-native plugin params ($this->params).
 *
 * @package     AiBoost\Plugin\System\AiBoostHreflang
 * @version     1.0.0
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostHreflang\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;

class AiBoostHreflang extends CMSPlugin
{
    protected $autoloadLanguage = true;

    /** @var array<array{lang_id:string,lang_code:string,sef:string,title:string}> */
    private array $detectedLanguages = [];

    public function onAfterInitialise(): void
    {
        // Serve standalone hreflang sitemap — honour staging_mode and enabled flag
        if (!(int) $this->params->get('enabled', 1)) {
            return;
        }
        if ((int) $this->params->get('staging_mode', 0)) {
            return;
        }
        $uri  = $_SERVER['REQUEST_URI'] ?? '';
        $path = ltrim(parse_url($uri, PHP_URL_PATH) ?? '', '/');
        if ($path === 'sitemap-hreflang.xml') {
            $this->serveHreflangSitemap();
        }
    }

    public function onBeforeCompileHead(): void
    {
        $app = Factory::getApplication();
        if (!$app->isClient('site')) {
            return;
        }
        $document = $app->getDocument();
        if (!$document || $document->getType() !== 'html') {
            return;
        }

        if (!(int) $this->params->get('enabled', 1)) {
            return;
        }
        if ((int) $this->params->get('staging_mode', 0)) {
            return;
        }

        $this->injectHreflangTags($document, $app);
    }

    private function injectHreflangTags($document, $app): void
    {
        $mode = trim((string) $this->params->get('detection_mode', 'auto'));
        $langs = $mode === 'manual'
            ? $this->parseManualLanguages()
            : $this->detectJoomlaLanguages();

        if (empty($langs)) {
            return;
        }

        $baseUrl    = Uri::getInstance()->getScheme() . '://' . Uri::getInstance()->getHost();
        $primarySef = trim((string) $this->params->get('primary_language', 'en'));
        $xDefault   = trim((string) $this->params->get('x_default_url', ''));

        $input     = $app->getInput();
        $option    = $input->get('option', '');
        $view      = $input->get('view', '');
        $itemId    = (int) $input->get('id', 0);
        $associationUrls = $this->loadAssociationUrls($option, $view, $itemId, $langs, $baseUrl);

        $falangMap = (int) $this->params->get('falang_enabled', 1)
            ? $this->loadFalangAliasMap()
            : [];

        $currentPath = $this->getCurrentCleanPath($langs);
        $defaultUrl  = $xDefault ?: null;

        foreach ($langs as $lang) {
            $sef      = (string) ($lang['sef'] ?? '');
            $langCode = strtolower(str_replace('_', '-', (string) ($lang['lang_code'] ?? '')));

            if (!$sef || !$langCode) {
                continue;
            }

            $url = $associationUrls[$langCode]
                ?? $associationUrls[$sef]
                ?? $this->buildAlternateUrl($baseUrl, $sef, $currentPath, $falangMap);

            $document->addCustomTag(
                '<link rel="alternate" hreflang="' . htmlspecialchars($langCode) . '" href="' . htmlspecialchars($url) . '">'
            );

            try {
                $currentLangTag = strtolower(str_replace('_', '-', Factory::getLanguage()->getTag()));
                if ($langCode === $currentLangTag) {
                    $document->addCustomTag('<link rel="canonical" href="' . htmlspecialchars($url) . '">');
                }
            } catch (\Throwable $e) {
                error_log('[AI Boost Hreflang] Canonical error: ' . $e->getMessage());
            }

            if ($defaultUrl === null && $sef === $primarySef) {
                $defaultUrl = $url;
            }
        }

        // x-default
        if ($defaultUrl === null && !empty($langs)) {
            $first      = $langs[0];
            $firstSef   = (string) ($first['sef'] ?? '');
            $firstCode  = strtolower(str_replace('_', '-', (string) ($first['lang_code'] ?? '')));
            $defaultUrl = $associationUrls[$firstCode]
                ?? $associationUrls[$firstSef]
                ?? $this->buildAlternateUrl($baseUrl, $firstSef, $currentPath, $falangMap);
        }

        if ($defaultUrl) {
            $document->addCustomTag(
                '<link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($defaultUrl) . '">'
            );
        }
    }

    private function loadAssociationUrls(string $option, string $view, int $itemId, array $langs, string $baseUrl): array
    {
        $urls = [];
        if (!$itemId || !$option) {
            return $urls;
        }

        $contextMap = [
            'com_content:article'  => 'com_content.item',
            'com_content:category' => 'com_categories.item',
            'com_menus:item'       => 'com_menus.item',
        ];
        $contextKey = $contextMap[$option . ':' . $view] ?? null;
        if (!$contextKey) {
            return $urls;
        }

        try {
            $db = Factory::getDbo();

            $q = $db->getQuery(true)
                ->select([$db->quoteName('a2.id', 'assoc_id')])
                ->from($db->quoteName('#__associations', 'a1'))
                ->join('INNER', $db->quoteName('#__associations', 'a2') . ' ON '
                    . $db->quoteName('a2.key') . ' = ' . $db->quoteName('a1.key')
                    . ' AND ' . $db->quoteName('a2.context') . ' = ' . $db->quoteName('a1.context'))
                ->where($db->quoteName('a1.context') . ' = ' . $db->quote($contextKey))
                ->where($db->quoteName('a1.id') . ' = ' . $itemId)
                ->where($db->quoteName('a2.id') . ' != ' . $itemId);
            $db->setQuery($q);
            $assocs = $db->loadObjectList();

            $allAssocItems = array_merge(
                [['id' => $itemId]],
                array_map(fn($r) => ['id' => (int) $r->assoc_id], $assocs)
            );

            $langSefMap = [];
            foreach ($langs as $l) {
                $code = strtolower(str_replace('_', '-', (string) ($l['lang_code'] ?? '')));
                $langSefMap[$code] = (string) ($l['sef'] ?? '');
                if (!empty($l['lang_code'])) {
                    $langSefMap[strtolower((string) $l['lang_code'])] = (string) ($l['sef'] ?? '');
                }
            }

            if ($option === 'com_content' && $view === 'article') {
                $ids = array_column($allAssocItems, 'id');
                if (empty($ids)) {
                    return $urls;
                }
                $intIds = array_map('intval', $ids);
                $qA = $db->getQuery(true)
                    ->select(['a.id', 'a.alias', 'a.language', 'c.alias AS cat_alias'])
                    ->from($db->quoteName('#__content', 'a'))
                    ->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON c.id = a.catid')
                    ->where($db->quoteName('a.id') . ' IN (' . implode(',', $intIds) . ')')
                    ->where($db->quoteName('a.state') . ' = 1');
                $db->setQuery($qA);
                $articleRows = $db->loadObjectList('id');

                foreach ($allAssocItems as $assocItem) {
                    $aid = (int) $assocItem['id'];
                    if (!isset($articleRows[$aid])) {
                        continue;
                    }
                    $artRow  = $articleRows[$aid];
                    $artLang = strtolower(str_replace('_', '-', (string) ($artRow->language ?? '')));
                    $sef     = $langSefMap[$artLang] ?? $langSefMap[strtolower((string) ($artRow->language ?? ''))] ?? '';
                    if (!$sef || !$artLang) {
                        continue;
                    }
                    $slug = ($artRow->cat_alias ? $artRow->cat_alias . '/' : '') . $artRow->alias;
                    $url  = $baseUrl . '/' . $sef . '/' . $slug;
                    $urls[$artLang] = $url;
                    $urls[$sef]     = $urls[$sef] ?? $url;
                }
            } elseif ($option === 'com_content' && $view === 'category') {
                $ids    = array_column($allAssocItems, 'id');
                $intIds = array_map('intval', $ids);
                $qC = $db->getQuery(true)
                    ->select(['c.id', 'c.alias', 'c.language', 'p.alias AS parent_alias'])
                    ->from($db->quoteName('#__categories', 'c'))
                    ->join('LEFT', $db->quoteName('#__categories', 'p') . ' ON p.id = c.parent_id AND p.level > 0')
                    ->where($db->quoteName('c.id') . ' IN (' . implode(',', $intIds) . ')')
                    ->where($db->quoteName('c.published') . ' = 1');
                $db->setQuery($qC);
                $catRows = $db->loadObjectList('id');

                foreach ($allAssocItems as $assocItem) {
                    $cid = (int) $assocItem['id'];
                    if (!isset($catRows[$cid])) {
                        continue;
                    }
                    $catRow  = $catRows[$cid];
                    $catLang = strtolower(str_replace('_', '-', (string) ($catRow->language ?? '')));
                    $sef     = $langSefMap[$catLang] ?? '';
                    if (!$sef || !$catLang) {
                        continue;
                    }
                    $slug = ($catRow->parent_alias ? $catRow->parent_alias . '/' : '') . $catRow->alias;
                    $url  = $baseUrl . '/' . $sef . '/' . $slug;
                    $urls[$catLang] = $url;
                    $urls[$sef]     = $urls[$sef] ?? $url;
                }
            } elseif ($option === 'com_menus' || ($contextKey === 'com_menus.item')) {
                $ids    = array_column($allAssocItems, 'id');
                $intIds = array_map('intval', $ids);
                $qM = $db->getQuery(true)
                    ->select(['m.id', 'm.language', 'm.link', 'm.route'])
                    ->from($db->quoteName('#__menu', 'm'))
                    ->where($db->quoteName('m.id') . ' IN (' . implode(',', $intIds) . ')')
                    ->where($db->quoteName('m.published') . ' = 1')
                    ->where($db->quoteName('m.client_id') . ' = 0');
                $db->setQuery($qM);
                $menuRows = $db->loadObjectList('id');

                foreach ($allAssocItems as $assocItem) {
                    $mid = (int) $assocItem['id'];
                    if (!isset($menuRows[$mid])) {
                        continue;
                    }
                    $menuRow  = $menuRows[$mid];
                    $menuLang = strtolower(str_replace('_', '-', (string) ($menuRow->language ?? '')));
                    if ($menuLang === '*' || !$menuLang) {
                        continue;
                    }
                    $sef = $langSefMap[$menuLang] ?? '';
                    if (!$sef) {
                        continue;
                    }
                    // Use the Joomla-stored SEF route if available, otherwise fall back to base
                    $route = trim((string) ($menuRow->route ?? ''));
                    $url   = $route
                        ? $baseUrl . '/' . ltrim($route, '/')
                        : $baseUrl . '/' . $sef . '/';
                    $urls[$menuLang] = $url;
                    $urls[$sef]      = $urls[$sef] ?? $url;
                }
            }
        } catch (\Throwable $e) {
            error_log('[AI Boost Hreflang] Associations lookup error: ' . $e->getMessage());
        }

        return $urls;
    }

    // ── Language detection ──────────────────────────────────────────────────

    private function detectJoomlaLanguages(): array
    {
        if (!empty($this->detectedLanguages)) {
            return $this->detectedLanguages;
        }
        try {
            // Use default key ('default') so getLanguages() returns a flat indexed array of ALL
            // language objects. Passing any other key (e.g. 'published') causes Joomla 4/5 to
            // index the result by that field value, returning at most one entry per value (bug).
            $allLangs = \Joomla\CMS\Language\LanguageHelper::getLanguages();
            $result = [];
            foreach ($allLangs as $lang) {
                // Skip unpublished languages
                if ((int) ($lang->published ?? 0) !== 1) {
                    continue;
                }
                $code = (string) ($lang->lang_code ?? '');
                // Use the real Joomla SEF prefix (e.g. 'en', 'fr', 'de', 'sr-lat') NOT substr fallback
                $sef  = strtolower(trim((string) ($lang->sef ?? '')));
                if (!$code || !$sef) {
                    continue;
                }
                $result[] = [
                    'lang_id'   => (string) ($lang->lang_id ?? ''),
                    'lang_code' => $code,
                    'sef'       => $sef,
                    'title'     => (string) ($lang->title ?? $code),
                ];
            }
            return $this->detectedLanguages = $result;
        } catch (\Throwable $e) {
            error_log('[AI Boost Hreflang] Language detection error: ' . $e->getMessage());
            return [];
        }
    }

    private function parseManualLanguages(): array
    {
        $json  = trim((string) $this->params->get('manual_languages', '[]'));
        $langs = json_decode($json, true);
        if (!is_array($langs)) {
            return [];
        }
        // Expected: [{"lang_code":"en-GB","sef":"en","title":"English"},...]
        $result = [];
        foreach ($langs as $lang) {
            if (!empty($lang['lang_code']) && !empty($lang['sef'])) {
                $result[] = [
                    'lang_id'   => $lang['lang_id'] ?? '',
                    'lang_code' => $lang['lang_code'],
                    'sef'       => $lang['sef'],
                    'title'     => $lang['title'] ?? $lang['lang_code'],
                ];
            }
        }
        return $result;
    }

    // ── URL building ────────────────────────────────────────────────────────

    private function getCurrentCleanPath(array $langs): string
    {
        $path = Uri::getInstance()->getPath();
        $path = '/' . ltrim($path, '/');

        // Strip existing language SEF prefix
        $sefs = array_column($langs, 'sef');
        foreach ($sefs as $sef) {
            if (!$sef) {
                continue;
            }
            if (str_starts_with($path, '/' . $sef . '/')) {
                return substr($path, strlen('/' . $sef));
            }
            if ($path === '/' . $sef) {
                return '/';
            }
        }
        return $path;
    }

    private function buildAlternateUrl(string $baseUrl, string $sef, string $cleanPath, array $falangMap): string
    {
        // Try Falang translation for last path segment
        if (!empty($falangMap) && $cleanPath !== '/') {
            $segments  = array_values(array_filter(explode('/', $cleanPath)));
            $lastAlias = (string) ($segments[count($segments) - 1] ?? '');
            if ($lastAlias !== '' && isset($falangMap[$lastAlias][$sef])) {
                $translated     = $falangMap[$lastAlias][$sef];
                $parentSegments = array_slice($segments, 0, -1);
                $parentPath     = $parentSegments ? '/' . implode('/', $parentSegments) . '/' : '/';
                return $baseUrl . '/' . $sef . $parentPath . $translated;
            }
        }

        return $baseUrl . '/' . $sef . $cleanPath;
    }

    // ── Falang alias map ────────────────────────────────────────────────────

    private function loadFalangAliasMap(): array
    {
        try {
            $db     = Factory::getDbo();
            $prefix = $db->getPrefix();
            $tables = $db->getTableList();
            if (!in_array($prefix . 'falang_content', $tables, true)) {
                return [];
            }

            $map   = [];
            $fetch = function (string $refTable, string $joinTable, string $joinAlias) use ($db, &$map): void {
                try {
                    $q = $db->getQuery(true)
                        ->select([
                            $db->quoteName($joinAlias . '.alias', 'orig'),
                            $db->quoteName('fc.value', 'translated'),
                            $db->quoteName('l.sef', 'sef'),
                        ])
                        ->from($db->quoteName('#__falang_content', 'fc'))
                        ->join('INNER', $db->quoteName($joinTable, $joinAlias) . ' ON ' . $db->quoteName($joinAlias . '.id') . ' = ' . $db->quoteName('fc.reference_id'))
                        ->join('INNER', $db->quoteName('#__languages', 'l') . ' ON ' . $db->quoteName('l.lang_id') . ' = ' . $db->quoteName('fc.language_id'))
                        ->where($db->quoteName('fc.reference_table') . ' = ' . $db->quote($refTable))
                        ->where($db->quoteName('fc.reference_field') . ' = ' . $db->quote('alias'))
                        ->where($db->quoteName('fc.published') . ' = 1')
                        ->where($db->quoteName('l.published') . ' = 1');
                    $db->setQuery($q);
                    foreach ($db->loadObjectList() as $row) {
                        $orig       = trim((string) ($row->orig ?? ''));
                        $translated = trim((string) ($row->translated ?? ''));
                        $sef        = trim((string) ($row->sef ?? ''));
                        if ($orig !== '' && $translated !== '' && $sef !== '') {
                            $map[$orig][$sef] = $translated;
                        }
                    }
                } catch (\Throwable $e) {
                    error_log('[AI Boost Hreflang] Falang alias fetch error: ' . $e->getMessage());
                }
            };

            $fetch('menu',          '#__menu',       'm');
            $fetch('#__content',    '#__content',    'a');
            $fetch('#__categories', '#__categories', 'c');

            return $map;
        } catch (\Throwable) {
            return [];
        }
    }

    // ── Standalone hreflang sitemap ─────────────────────────────────────────

    private function serveHreflangSitemap(): void
    {
        try {
            $app     = Factory::getApplication();
            $db      = Factory::getDbo();
            $uri     = Uri::getInstance();
            $baseUrl = $uri->getScheme() . '://' . $uri->getHost();

            // Honour detection_mode: same logic as injectHreflangTags()
            $detectionMode = trim((string) $this->params->get('detection_mode', 'auto'));
            $langs = $detectionMode === 'manual'
                ? $this->parseManualLanguages()
                : $this->detectJoomlaLanguages();
            if (empty($langs)) {
                return;
            }

            $falangMap = (int) $this->params->get('falang_enabled', 1)
                ? $this->loadFalangAliasMap()
                : [];

            $sefs = array_column($langs, 'sef');
            $urls = [];

            // Articles
            $q = $db->getQuery(true)
                ->select(['a.alias', 'c.alias AS cat_alias', 'a.modified'])
                ->from($db->quoteName('#__content', 'a'))
                ->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON c.id = a.catid')
                ->where('a.state = 1')
                ->order('a.modified DESC');
            $db->setQuery($q, 0, 500);
            foreach ($db->loadObjectList() as $art) {
                $slug    = ($art->cat_alias ? $art->cat_alias . '/' : '') . $art->alias;
                $urls[]  = [
                    'path'    => '/' . $slug,
                    'lastmod' => $art->modified ? date('Y-m-d', strtotime($art->modified)) : date('Y-m-d'),
                ];
            }

            $xmlNs  = 'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml"';
            $xml    = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $xml   .= '<urlset ' . $xmlNs . '>' . "\n";

            foreach ($urls as $u) {
                $xml .= "  <url>\n";
                $xml .= '    <loc>' . htmlspecialchars($baseUrl . $u['path']) . "</loc>\n";
                $xml .= '    <lastmod>' . htmlspecialchars($u['lastmod']) . "</lastmod>\n";

                $primarySef = trim((string) $this->params->get('primary_language', 'en'));
                $xDefault   = null;
                foreach ($langs as $idx => $lang) {
                    $sef      = (string) ($lang['sef'] ?? '');
                    $langCode = htmlspecialchars(strtolower(str_replace('_', '-', (string) ($lang['lang_code'] ?? ''))));
                    $altUrl   = htmlspecialchars($this->buildAlternateUrl($baseUrl, $sef, $u['path'], $falangMap));
                    $xml     .= '    <xhtml:link rel="alternate" hreflang="' . $langCode . '" href="' . $altUrl . '"/>' . "\n";
                    if ($xDefault === null && ($idx === 0 || $sef === $primarySef)) {
                        $xDefault = $altUrl;
                    }
                }
                if ($xDefault !== null) {
                    $xml .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . $xDefault . '"/>' . "\n";
                }
                $xml .= "  </url>\n";
            }

            $xml .= '</urlset>';

            header('Content-Type: application/xml; charset=utf-8');
            header('Cache-Control: public, max-age=3600');
            header('X-Robots-Tag: noindex');
            echo $xml;
            $app->close();
        } catch (\Throwable $e) {
            error_log('[AI Boost Hreflang] Sitemap error: ' . $e->getMessage());
        }
    }
}
