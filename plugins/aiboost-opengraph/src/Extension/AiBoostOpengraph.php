<?php
/**
 * AI Boost — OpenGraph + Hreflang Plugin (standalone, Joomla 4/5/6)
 *
 * Handles: OG tags, Twitter/X Cards, per-article/category overrides, Falang integration,
 *          Hreflang <link rel="alternate"> tags (merged from standalone Hreflang plugin).
 * Standalone: reads all settings from Joomla-native plugin params ($this->params).
 *
 * @package     AiBoost\Plugin\System\AiBoostOpengraph
 * @version     1.1.0
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostOpengraph\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;

class AiBoostOpengraph extends CMSPlugin
{
    protected $autoloadLanguage = true;

    /** @var array<array{lang_id:string,lang_code:string,sef:string,title:string}> */
    private array $detectedLanguages = [];

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

        $this->injectOpenGraph($app, $document);

        if ((int) $this->params->get('hreflang_enabled', 0)) {
            $this->injectHreflangTags($document, $app);
        }
    }

    // ── OpenGraph ───────────────────────────────────────────────────────────

    private function injectOpenGraph($app, $document): void
    {
        $siteName    = trim((string) $this->params->get('og_site_name', '')) ?: $app->get('sitename', '');
        $defaultImg  = trim((string) $this->params->get('og_default_image', ''));
        $pageTitle   = $document->getTitle();
        $description = $document->getDescription();
        $currentUrl  = Uri::current();
        $baseUrl     = Uri::getInstance()->getScheme() . '://' . Uri::getInstance()->getHost();

        $input      = $app->getInput();
        $option     = $input->get('option', '');
        $view       = $input->get('view', '');
        $isArticle  = $option === 'com_content' && $view === 'article';
        $isCategory = $option === 'com_content' && $view === 'category';
        $articleId  = $isArticle  ? (int) $input->get('id', 0) : 0;
        $categoryId = $isCategory ? (int) $input->get('id', 0) : 0;

        $ogType     = 'website';
        $ogImage    = $defaultImg;
        $ogTitle    = $pageTitle;
        $ogDesc     = $description;
        $pubTime    = '';
        $modTime    = '';
        $section    = '';
        $authorName = '';

        if ($articleId) {
            $ogType = 'article';
            try {
                $db    = Factory::getDbo();
                $query = $db->getQuery(true)
                    ->select(['id', 'title', 'introtext', 'images', 'created', 'modified', 'created_by', 'catid'])
                    ->from($db->quoteName('#__content'))
                    ->where($db->quoteName('id') . ' = ' . $articleId)
                    ->where($db->quoteName('state') . ' = 1');
                $db->setQuery($query);
                $row = $db->loadObject();

                if ($row) {
                    $imagesJson = (string) ($row->images ?? '');
                    if ($imagesJson) {
                        $imgs = json_decode($imagesJson, true);
                        $img  = $imgs['image_intro'] ?? $imgs['image_fulltext'] ?? '';
                        if ($img) {
                            $ogImage = strpos($img, 'http') === 0
                                ? $img
                                : $baseUrl . '/' . ltrim($img, '/');
                        }
                    }

                    if (!empty($row->created)) {
                        $pubTime = date('c', strtotime($row->created));
                    }
                    if (!empty($row->modified) && $row->modified !== '0000-00-00 00:00:00') {
                        $modTime = date('c', strtotime($row->modified));
                    }

                    if ($row->catid) {
                        try {
                            $catQ = $db->getQuery(true)
                                ->select($db->quoteName('title'))
                                ->from($db->quoteName('#__categories'))
                                ->where($db->quoteName('id') . ' = ' . (int) $row->catid);
                            $db->setQuery($catQ);
                            $section = (string) ($db->loadResult() ?? '');
                        } catch (\Throwable $e) {
                            error_log('[AI Boost OG] Category lookup error: ' . $e->getMessage());
                        }
                    }

                    if (!empty($row->created_by)) {
                        try {
                            $user       = Factory::getUser($row->created_by);
                            $authorName = $user->name ?: $user->username;
                        } catch (\Throwable $e) {
                            error_log('[AI Boost OG] Author lookup error: ' . $e->getMessage());
                        }
                    }
                }
            } catch (\Throwable $e) {
                error_log('[AI Boost OG] Article data error: ' . $e->getMessage());
            }

            $this->applyArticleCustomFieldOverrides($ogTitle, $ogDesc, $ogImage, $articleId, $baseUrl);
        }

        if ($categoryId) {
            $ogType = 'website';
            $this->applyCategoryCustomFieldOverrides($ogTitle, $ogDesc, $ogImage, $categoryId, $baseUrl);
        }

        $this->applyFalangOverrides($ogTitle, $ogDesc);

        if ($ogImage && strpos($ogImage, 'http') !== 0) {
            $ogImage = $baseUrl . '/' . ltrim($ogImage, '/');
        }

        $typeParam = trim((string) $this->params->get('og_type', ''));
        if ($typeParam && !$isArticle) {
            $ogType = $typeParam;
        }

        $ogTags = [
            'og:type'        => $ogType,
            'og:url'         => $currentUrl,
            'og:site_name'   => $siteName,
            'og:title'       => $ogTitle,
            'og:description' => $ogDesc,
        ];
        if ($ogImage) {
            $ogTags['og:image'] = $ogImage;
        }

        foreach ($ogTags as $property => $content) {
            if ($content !== '' && $content !== null) {
                $document->addCustomTag(
                    '<meta property="' . htmlspecialchars($property) . '" content="' . htmlspecialchars((string) $content) . '">'
                );
            }
        }

        if ($isArticle) {
            if ($pubTime)    { $document->addCustomTag('<meta property="article:published_time" content="' . htmlspecialchars($pubTime) . '">'); }
            if ($modTime)    { $document->addCustomTag('<meta property="article:modified_time" content="' . htmlspecialchars($modTime) . '">'); }
            if ($section)    { $document->addCustomTag('<meta property="article:section" content="' . htmlspecialchars($section) . '">'); }
            if ($authorName) { $document->addCustomTag('<meta property="article:author" content="' . htmlspecialchars($authorName) . '">'); }
        }

        if ((int) $this->params->get('enable_twitter_cards', 1)) {
            $cardType = trim((string) $this->params->get('twitter_card_type', 'summary_large_image')) ?: 'summary_large_image';
            $twTags   = [
                'twitter:card'        => $cardType,
                'twitter:title'       => $ogTitle,
                'twitter:description' => $ogDesc,
            ];
            if ($ogImage) {
                $twTags['twitter:image'] = $ogImage;
            }
            $twitterSite = trim((string) $this->params->get('twitter_site', ''));
            if ($twitterSite) {
                $twTags['twitter:site'] = '@' . ltrim($twitterSite, '@');
            }
            foreach ($twTags as $name => $content) {
                if ($content !== '' && $content !== null) {
                    $document->addCustomTag(
                        '<meta name="' . htmlspecialchars($name) . '" content="' . htmlspecialchars((string) $content) . '">'
                    );
                }
            }
        }
    }

    // ── Custom field overrides (article / category) ─────────────────────────

    private function applyArticleCustomFieldOverrides(
        string &$ogTitle, string &$ogDesc, string &$ogImage,
        int $articleId, string $baseUrl
    ): void {
        try {
            $db  = Factory::getDbo();
            $cfQ = $db->getQuery(true)
                ->select([$db->quoteName('f.name', 'fname'), $db->quoteName('fv.value', 'fval')])
                ->from($db->quoteName('#__fields_values', 'fv'))
                ->join('INNER', $db->quoteName('#__fields', 'f') . ' ON f.id = fv.field_id')
                ->where($db->quoteName('fv.item_id') . ' = ' . $articleId)
                ->where($db->quoteName('f.state') . ' = 1')
                ->where($db->quoteName('f.context') . ' = ' . $db->quote('com_content.article'))
                ->where($db->quoteName('f.name') . ' IN (' . implode(',', array_map([$db, 'quote'], [
                    'aiboost_og_title', 'aiboost_og_description', 'aiboost_og_image',
                ])) . ')');
            $db->setQuery($cfQ);
            foreach ($db->loadObjectList() as $cf) {
                $cfName = (string) ($cf->fname ?? '');
                $cfVal  = trim((string) ($cf->fval ?? ''));
                if ($cfVal === '') {
                    continue;
                }
                if ($cfName === 'aiboost_og_title')       { $ogTitle = $cfVal; }
                elseif ($cfName === 'aiboost_og_description') { $ogDesc = $cfVal; }
                elseif ($cfName === 'aiboost_og_image') {
                    $ogImage = strpos($cfVal, 'http') === 0 ? $cfVal : $baseUrl . '/' . ltrim($cfVal, '/');
                }
            }
        } catch (\Throwable $e) {
            error_log('[AI Boost OG] Article custom field override error: ' . $e->getMessage());
        }
    }

    private function applyCategoryCustomFieldOverrides(
        string &$ogTitle, string &$ogDesc, string &$ogImage,
        int $categoryId, string $baseUrl
    ): void {
        try {
            $db  = Factory::getDbo();
            $cfQ = $db->getQuery(true)
                ->select([$db->quoteName('f.name', 'fname'), $db->quoteName('fv.value', 'fval')])
                ->from($db->quoteName('#__fields_values', 'fv'))
                ->join('INNER', $db->quoteName('#__fields', 'f') . ' ON f.id = fv.field_id')
                ->where($db->quoteName('fv.item_id') . ' = ' . $categoryId)
                ->where($db->quoteName('f.state') . ' = 1')
                ->where($db->quoteName('f.context') . ' = ' . $db->quote('com_content.category'))
                ->where($db->quoteName('f.name') . ' IN (' . implode(',', array_map([$db, 'quote'], [
                    'aiboost_og_title', 'aiboost_og_description', 'aiboost_og_image',
                ])) . ')');
            $db->setQuery($cfQ);
            foreach ($db->loadObjectList() as $cf) {
                $cfName = (string) ($cf->fname ?? '');
                $cfVal  = trim((string) ($cf->fval ?? ''));
                if ($cfVal === '') {
                    continue;
                }
                if ($cfName === 'aiboost_og_title')       { $ogTitle = $cfVal; }
                elseif ($cfName === 'aiboost_og_description') { $ogDesc = $cfVal; }
                elseif ($cfName === 'aiboost_og_image') {
                    $ogImage = strpos($cfVal, 'http') === 0 ? $cfVal : $baseUrl . '/' . ltrim($cfVal, '/');
                }
            }
        } catch (\Throwable $e) {
            error_log('[AI Boost OG] Category custom field override error: ' . $e->getMessage());
        }
    }

    // ── Falang OG overrides ─────────────────────────────────────────────────

    private function applyFalangOverrides(string &$title, string &$description): void
    {
        if (!(int) $this->params->get('falang_enabled', 1)) {
            return;
        }
        try {
            $app             = Factory::getApplication();
            $lang            = Factory::getLanguage();
            $tag             = $lang->getTag();
            $siteDefaultLang = trim((string) $app->get('language', ''));
            if ($siteDefaultLang && strtolower($tag) === strtolower($siteDefaultLang)) {
                return;
            }

            $db     = Factory::getDbo();
            $tables = $db->getTableList();
            $prefix = $db->getPrefix();
            if (!in_array($prefix . 'falang_content', $tables, true)) {
                return;
            }

            $q = $db->getQuery(true)
                ->select($db->quoteName('lang_id'))
                ->from($db->quoteName('#__languages'))
                ->where($db->quoteName('lang_code') . ' = ' . $db->quote($tag))
                ->where($db->quoteName('published') . ' = 1');
            $db->setQuery($q);
            $langId = (int) $db->loadResult();
            if (!$langId) {
                return;
            }

            $input     = $app->getInput();
            $articleId = $input->get('option') === 'com_content' && $input->get('view') === 'article'
                ? (int) $input->get('id', 0)
                : 0;
            if (!$articleId) {
                return;
            }

            $q2 = $db->getQuery(true)
                ->select([$db->quoteName('fc.reference_field', 'field'), $db->quoteName('fc.value', 'val')])
                ->from($db->quoteName('#__falang_content', 'fc'))
                ->where($db->quoteName('fc.reference_table') . ' = ' . $db->quote('#__content'))
                ->where($db->quoteName('fc.reference_id')    . ' = ' . $articleId)
                ->where($db->quoteName('fc.language_id')     . ' = ' . $langId)
                ->where($db->quoteName('fc.published')       . ' = 1')
                ->where($db->quoteName('fc.reference_field') . ' IN (' . implode(',', array_map([$db, 'quote'], ['title', 'introtext'])) . ')');
            $db->setQuery($q2);
            foreach ($db->loadObjectList() as $t) {
                $val = trim((string) ($t->val ?? ''));
                if ($t->field === 'title' && $val !== '')     { $title = $val; }
                elseif ($t->field === 'introtext' && $val !== '') {
                    $description = mb_substr(trim(strip_tags($val)), 0, 200);
                }
            }
        } catch (\Throwable $e) {
            error_log('[AI Boost OG] Falang override error: ' . $e->getMessage());
        }
    }

    // ── Hreflang link tags (merged from standalone Hreflang plugin) ──────────

    private function injectHreflangTags($document, $app): void
    {
        $mode  = trim((string) $this->params->get('hreflang_detection_mode', 'auto'));
        $langs = $mode === 'manual'
            ? $this->parseManualLanguages()
            : $this->detectJoomlaLanguages();

        if (empty($langs)) {
            return;
        }

        $baseUrl    = Uri::getInstance()->getScheme() . '://' . Uri::getInstance()->getHost();
        $primarySef = trim((string) $this->params->get('hreflang_primary_language', 'en'));
        $xDefault   = trim((string) $this->params->get('hreflang_x_default_url', ''));
        $xDefault   = $xDefault ?: null;

        $input    = $app->getInput();
        $option   = $input->get('option', '');
        $view     = $input->get('view', '');
        $itemId   = (int) $input->get('id', 0);

        $associationUrls = $this->loadAssociationUrls($option, $view, $itemId, $langs, $baseUrl);

        $falangMap = (int) $this->params->get('hreflang_falang_enabled', 1)
            ? $this->loadFalangAliasMap()
            : [];

        $currentPath = $this->getCurrentCleanPath($langs);
        $defaultUrl  = $xDefault;

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
                error_log('[AI Boost OG] Hreflang canonical error: ' . $e->getMessage());
            }

            if ($defaultUrl === null && $sef === $primarySef) {
                $defaultUrl = $url;
            }
        }

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

    // ── Language detection ──────────────────────────────────────────────────

    private function detectJoomlaLanguages(): array
    {
        if (!empty($this->detectedLanguages)) {
            return $this->detectedLanguages;
        }
        try {
            $allLangs = \Joomla\CMS\Language\LanguageHelper::getLanguages();
            $result   = [];
            foreach ($allLangs as $lang) {
                if ((int) ($lang->published ?? 0) !== 1) {
                    continue;
                }
                $code = (string) ($lang->lang_code ?? '');
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
            error_log('[AI Boost OG] Language detection error: ' . $e->getMessage());
            return [];
        }
    }

    private function parseManualLanguages(): array
    {
        $json  = trim((string) $this->params->get('hreflang_manual_languages', '[]'));
        $langs = json_decode($json, true);
        if (!is_array($langs)) {
            return [];
        }
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

    // ── Association URL lookup ──────────────────────────────────────────────

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
            $q  = $db->getQuery(true)
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
                $ids    = array_map('intval', array_column($allAssocItems, 'id'));
                $qA     = $db->getQuery(true)
                    ->select(['a.id', 'a.alias', 'a.language', 'c.alias AS cat_alias'])
                    ->from($db->quoteName('#__content', 'a'))
                    ->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON c.id = a.catid')
                    ->where($db->quoteName('a.id') . ' IN (' . implode(',', $ids) . ')')
                    ->where($db->quoteName('a.state') . ' = 1');
                $db->setQuery($qA);
                foreach ($db->loadObjectList('id') as $aid => $artRow) {
                    $artLang = strtolower(str_replace('_', '-', (string) ($artRow->language ?? '')));
                    $sef     = $langSefMap[$artLang] ?? '';
                    if (!$sef || !$artLang) { continue; }
                    $slug = ($artRow->cat_alias ? $artRow->cat_alias . '/' : '') . $artRow->alias;
                    $url  = $baseUrl . '/' . $sef . '/' . $slug;
                    $urls[$artLang] = $url;
                    $urls[$sef]     = $urls[$sef] ?? $url;
                }
            } elseif ($option === 'com_content' && $view === 'category') {
                $ids = array_map('intval', array_column($allAssocItems, 'id'));
                $qC  = $db->getQuery(true)
                    ->select(['c.id', 'c.alias', 'c.language', 'p.alias AS parent_alias'])
                    ->from($db->quoteName('#__categories', 'c'))
                    ->join('LEFT', $db->quoteName('#__categories', 'p') . ' ON p.id = c.parent_id AND p.level > 0')
                    ->where($db->quoteName('c.id') . ' IN (' . implode(',', $ids) . ')')
                    ->where($db->quoteName('c.published') . ' = 1');
                $db->setQuery($qC);
                foreach ($db->loadObjectList('id') as $cid => $catRow) {
                    $catLang = strtolower(str_replace('_', '-', (string) ($catRow->language ?? '')));
                    $sef     = $langSefMap[$catLang] ?? '';
                    if (!$sef || !$catLang) { continue; }
                    $slug = ($catRow->parent_alias ? $catRow->parent_alias . '/' : '') . $catRow->alias;
                    $url  = $baseUrl . '/' . $sef . '/' . $slug;
                    $urls[$catLang] = $url;
                    $urls[$sef]     = $urls[$sef] ?? $url;
                }
            }
        } catch (\Throwable $e) {
            error_log('[AI Boost OG] Associations lookup error: ' . $e->getMessage());
        }

        return $urls;
    }

    // ── URL building ────────────────────────────────────────────────────────

    private function getCurrentCleanPath(array $langs): string
    {
        $path = '/' . ltrim(Uri::getInstance()->getPath(), '/');
        foreach (array_column($langs, 'sef') as $sef) {
            if (!$sef) { continue; }
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
            if (!in_array($prefix . 'falang_content', $db->getTableList(), true)) {
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
                    error_log('[AI Boost OG] Falang alias fetch error: ' . $e->getMessage());
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
}
