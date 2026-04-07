<?php

/**
 * Sitemap Service for JoomlaBoost
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
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * Enhanced Sitemap Service with Configurable Content and Multilingual Support
 *
 * Multilingual hreflang strategy:
 * - Auto-detects all published Joomla content languages from #__languages
 * - Generates xhtml:link alternate tags for each URL in each language
 * - Uses Route::_(...&lang=xx-XX) so Language Filter plugin adds correct /lang/ prefix
 * - Works with both Falang and native Joomla multilingual
 */
class SitemapService extends AbstractService
{
    /**
     * Language service instance (lazy loaded)
     */
    private ?LanguageService $languageService = null;

    /**
     * Generate complete sitemap with configurable content
     */
    public function generateSitemap(): string
    {
        if (!$this->isEnabled()) {
            return $this->getEmptySitemap();
        }

        $urls     = [];
        $seenUrls = []; // Track normalized URLs to prevent duplicates

        // Normalize URL for comparison: strip trailing slash, lowercase scheme+host
        $normalize = static function (string $url): string {
            return rtrim($url, '/');
        };

        // Detect if multilingual sitemap is requested
        $multilingual = (bool)$this->params->get('sitemap_hreflang', 0);
        $languages    = $multilingual ? $this->getLanguageService()->getActiveLanguages() : [];

        // Homepage (always included)
        $homeUrl = $this->getBaseUrl();
        $urls[] = $this->createUrlEntry(
            $homeUrl,
            '1.0',
            'daily',
            null,
            [],
            $multilingual ? $this->buildAlternatesForHomepage($languages) : []
        );
        $seenUrls[$normalize($homeUrl)] = true;

        // Articles (if enabled)
        if ($this->params->get('sitemap_include_articles', 1)) {
            $articles    = $this->getPublishedArticles();
            $priority    = $this->params->get('sitemap_priority_articles', '0.8');
            $changefreq  = $this->params->get('sitemap_changefreq_articles', 'weekly');

            foreach ($articles as $article) {
                $normalized = $normalize($article->url);
                if (isset($seenUrls[$normalized])) {
                    continue; // Skip duplicate
                }
                $images     = $this->getArticleImages($article->id);
                $alternates = $multilingual
                    ? $this->buildAlternatesForArticle($article, $languages)
                    : [];

                $urls[] = $this->createUrlEntry(
                    $article->url,
                    $priority,
                    $changefreq,
                    $article->modified,
                    $images,
                    $alternates
                );
                $seenUrls[$normalized] = true;
            }
        }

        // Categories (if enabled)
        if ($this->params->get('sitemap_include_categories', 1)) {
            $categories = $this->getPublishedCategories();
            $priority   = $this->params->get('sitemap_priority_categories', '0.7');
            $changefreq = $this->params->get('sitemap_changefreq_categories', 'weekly');

            foreach ($categories as $category) {
                $normalized = $normalize($category->url);
                if (isset($seenUrls[$normalized])) {
                    continue; // Skip duplicate
                }
                $alternates = $multilingual
                    ? $this->buildAlternatesForCategory($category, $languages)
                    : [];

                $urls[] = $this->createUrlEntry(
                    $category->url,
                    $priority,
                    $changefreq,
                    null,
                    [],
                    $alternates
                );
                $seenUrls[$normalized] = true;
            }
        }

        // Menu items (if enabled)
        if ($this->params->get('sitemap_include_menu', 0)) {
            $menuItems  = $this->getMenuItems();
            $priority   = $this->params->get('sitemap_priority_menu', '0.6');
            $changefreq = $this->params->get('sitemap_changefreq_menu', 'monthly');

            foreach ($menuItems as $item) {
                $normalized = $normalize($item->url);
                if (isset($seenUrls[$normalized])) {
                    continue; // Skip duplicate (e.g. homepage menu item = already added)
                }
                $urls[] = $this->createUrlEntry(
                    $item->url,
                    $priority,
                    $changefreq,
                    null,
                    [],
                    [] // Menu items: multilingual would need separate language menus
                );
                $seenUrls[$normalized] = true;
            }
        }

        return $this->buildXml($urls, $multilingual);
    }


    // =========================================================================
    // MULTILINGUAL — Alternates building
    // =========================================================================

    /**
     * Get LanguageService (lazy init)
     */
    private function getLanguageService(): LanguageService
    {
        if ($this->languageService === null) {
            $this->languageService = new LanguageService($this->app, $this->params);
        }
        return $this->languageService;
    }

    /**
     * Build alternates array for the homepage
     *
     * @param array<string, object> $languages
     * @return array<array{hreflang: string, href: string}>
     */
    private function buildAlternatesForHomepage(array $languages): array
    {
        $langSvc    = $this->getLanguageService();
        $baseUrl    = $this->getBaseUrl();
        $defaultCode = $langSvc->getDefaultLanguageCode();
        $alternates = [];

        foreach ($languages as $lang) {
            $url = rtrim($baseUrl, '/') . '/' . $lang->sef . '/';
            $alternates[] = [
                'hreflang' => $langSvc->getHreflangCode($lang->lang_code),
                'href'     => $url,
            ];
        }

        // Add x-default pointing to default language homepage
        if (isset($languages[$defaultCode])) {
            $alternates[] = [
                'hreflang' => 'x-default',
                'href'     => rtrim($baseUrl, '/') . '/' . $languages[$defaultCode]->sef . '/',
            ];
        }

        return $alternates;
    }

    /**
     * Build alternates array for an article
     *
     * @param object                $article    Article with id, alias, catid, cat_alias
     * @param array<string, object> $languages
     * @return array<array{hreflang: string, href: string}>
     */
    private function buildAlternatesForArticle(object $article, array $languages): array
    {
        $langSvc     = $this->getLanguageService();
        $baseUrl     = $this->getBaseUrl();
        $defaultCode = $langSvc->getDefaultLanguageCode();
        $alternates  = [];
        $defaultHref = '';

        foreach ($languages as $lang) {
            $link = "index.php?option=com_content&view=article&id={$article->id}:{$article->alias}&catid={$article->catid}&lang={$lang->lang_code}";
            $url  = $langSvc->buildUrlForLanguage($link, $lang->lang_code, $baseUrl);

            $entry = [
                'hreflang' => $langSvc->getHreflangCode($lang->lang_code),
                'href'     => $url,
            ];
            $alternates[] = $entry;

            if ($lang->lang_code === $defaultCode) {
                $defaultHref = $url;
            }
        }

        // x-default pointing to default language
        if ($defaultHref) {
            $alternates[] = [
                'hreflang' => 'x-default',
                'href'     => $defaultHref,
            ];
        }

        return $alternates;
    }

    /**
     * Build alternates array for a category
     *
     * @param object                $category   Category with id, alias
     * @param array<string, object> $languages
     * @return array<array{hreflang: string, href: string}>
     */
    private function buildAlternatesForCategory(object $category, array $languages): array
    {
        $langSvc     = $this->getLanguageService();
        $baseUrl     = $this->getBaseUrl();
        $defaultCode = $langSvc->getDefaultLanguageCode();
        $alternates  = [];
        $defaultHref = '';

        foreach ($languages as $lang) {
            $link = "index.php?option=com_content&view=category&id={$category->id}:{$category->alias}&lang={$lang->lang_code}";
            $url  = $langSvc->buildUrlForLanguage($link, $lang->lang_code, $baseUrl);

            $entry = [
                'hreflang' => $langSvc->getHreflangCode($lang->lang_code),
                'href'     => $url,
            ];
            $alternates[] = $entry;

            if ($lang->lang_code === $defaultCode) {
                $defaultHref = $url;
            }
        }

        if ($defaultHref) {
            $alternates[] = ['hreflang' => 'x-default', 'href' => $defaultHref];
        }

        return $alternates;
    }

    // =========================================================================
    // DATABASE — Content loading
    // =========================================================================

    /**
     * Get published articles with exclusions
     */
    private function getPublishedArticles(): array
    {
        try {
            $db          = Factory::getDbo();
            $excludeIds  = $this->params->get('sitemap_exclude_ids', '');
            $excludeArray = array_filter(array_map('trim', explode(',', $excludeIds)));
            $selectedCats = $this->params->get('sitemap_article_categories', []);
            $maxArticles  = (int)$this->params->get('sitemap_max_articles', 0);

            $query = $db->getQuery(true)
                ->select('a.id, a.alias, a.modified, a.catid, c.alias AS cat_alias')
                ->from('#__content AS a')
                ->leftJoin('#__categories AS c ON c.id = a.catid')
                ->where('a.state = 1');

            if (!empty($selectedCats) && is_array($selectedCats)) {
                $query->where('a.catid IN (' . implode(',', array_map('intval', $selectedCats)) . ')');
            }

            if (!empty($excludeArray)) {
                $query->where('a.id NOT IN (' . implode(',', array_map('intval', $excludeArray)) . ')');
            }

            if ($maxArticles > 0) {
                $query->setLimit($maxArticles);
            }

            $db->setQuery($query);
            $articles = $db->loadObjectList();

            // Build default-language URLs
            foreach ($articles as $article) {
                $article->url = $this->getBaseUrl() . Route::_(
                    "index.php?option=com_content&view=article&id={$article->id}:{$article->alias}&catid={$article->catid}"
                );
            }

            $this->logDebug("Loaded {count} articles for sitemap", ['count' => count($articles)]);
            return $articles;
        } catch (\Throwable $e) {
            $this->logDebug('Article loading failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get published categories
     */
    private function getPublishedCategories(): array
    {
        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('id, alias, title')
                ->from('#__categories')
                ->where('published = 1')
                ->where('extension = ' . $db->quote('com_content'));

            $db->setQuery($query);
            $categories = $db->loadObjectList();

            foreach ($categories as $category) {
                $category->url = $this->getBaseUrl() . Route::_(
                    "index.php?option=com_content&view=category&id={$category->id}:{$category->alias}"
                );
            }

            $this->logDebug("Loaded {count} categories for sitemap", ['count' => count($categories)]);
            return $categories;
        } catch (\Throwable $e) {
            $this->logDebug('Category loading failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get menu items (excluding external links)
     */
    private function getMenuItems(): array
    {
        try {
            $db             = Factory::getDbo();
            $maxDepth       = (int)$this->params->get('sitemap_menu_depth', 0);
            $selectedMenuTypes = $this->params->get('sitemap_menu_types', []);

            $query = $db->getQuery(true)
                ->select('id, alias, link, type, level, menutype')
                ->from('#__menu')
                ->where('published = 1')
                ->where('client_id = 0')
                ->where('type NOT IN (' . implode(',', [
                    $db->quote('url'),
                    $db->quote('separator'),
                    $db->quote('alias'),
                ]) . ')');

            if ($maxDepth > 0) {
                $query->where('level <= ' . (int)$maxDepth);
            }

            if (!empty($selectedMenuTypes) && is_array($selectedMenuTypes)) {
                $quotedTypes = array_map([$db, 'quote'], $selectedMenuTypes);
                $query->where('menutype IN (' . implode(',', $quotedTypes) . ')');
            }

            $db->setQuery($query);
            $items = $db->loadObjectList();

            foreach ($items as $item) {
                if (empty($item->link) || str_starts_with($item->link, 'http')) {
                    continue;
                }
                $item->url = $this->getBaseUrl() . Route::_($item->link);
            }

            $menuInfo = empty($selectedMenuTypes) ? 'all menus' : implode(', ', $selectedMenuTypes);
            $this->logDebug("Loaded {count} menu items for sitemap (menus: {menus}, depth: {depth})", [
                'count' => count($items),
                'menus' => $menuInfo,
                'depth' => $maxDepth === 0 ? 'unlimited' : $maxDepth
            ]);
            return array_filter($items, function ($i) {
                if (empty($i->url)) {
                    return false;
                }
                // Filter ?Itemid= URLs (separator/alias menu items with no real route)
                if (preg_match('/\?Itemid=\d*$/', $i->url)) {
                    return false;
                }
                return true;
            });
        } catch (\Throwable $e) {
            $this->logDebug('Menu loading failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get featured images from article
     *
     * @param int $articleId Article ID
     * @return array Array of images with 'loc' and 'caption' keys
     */
    private function getArticleImages(int $articleId): array
    {
        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('images')
                ->from('#__content')
                ->where('id = ' . (int)$articleId);

            $db->setQuery($query);
            $imagesJson = $db->loadResult();

            $images = [];
            if ($imagesJson) {
                $imageData = json_decode($imagesJson);

                if (!empty($imageData->image_intro)) {
                    $images[] = [
                        'loc'     => Uri::root() . $imageData->image_intro,
                        'caption' => $imageData->image_intro_alt ?? '',
                    ];
                }

                if (!empty($imageData->image_fulltext) && $imageData->image_fulltext !== $imageData->image_intro) {
                    $images[] = [
                        'loc'     => Uri::root() . $imageData->image_fulltext,
                        'caption' => $imageData->image_fulltext_alt ?? '',
                    ];
                }
            }

            return $images;
        } catch (\Exception $e) {
            return [];
        }
    }

    // =========================================================================
    // URL ENTRY + XML building
    // =========================================================================

    /**
     * Create URL entry for sitemap
     *
     * @param array<array{hreflang: string, href: string}> $alternates  Language alternates
     */
    private function createUrlEntry(
        string $url,
        string $priority = '0.5',
        string $changefreq = 'weekly',
        ?string $lastmod = null,
        array $images = [],
        array $alternates = []
    ): array {
        $entry = [
            'loc'        => $url,
            'priority'   => $priority,
            'changefreq' => $changefreq,
        ];

        if ($lastmod) {
            try {
                $date           = Factory::getDate($lastmod);
                $entry['lastmod'] = $date->format('c');
            } catch (\Exception $e) {
                // Skip lastmod if date parsing fails
            }
        }

        if (!empty($images)) {
            $entry['images'] = $images;
        }

        if (!empty($alternates)) {
            $entry['alternates'] = $alternates;
        }

        return $entry;
    }

    /**
     * Build XML from URL entries
     *
     * @param bool $multilingual  Whether to include xhtml namespace and hreflang tags
     */
    private function buildXml(array $urls, bool $multilingual = false): string
    {
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        $xml .= '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';

        if ($multilingual) {
            $xml .= "\n" . '        xmlns:xhtml="http://www.w3.org/1999/xhtml"';
        }

        $xml .= '>' . "\n";

        foreach ($urls as $url) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8') . '</loc>' . "\n";
            $xml .= '    <changefreq>' . $url['changefreq'] . '</changefreq>' . "\n";
            $xml .= '    <priority>' . $url['priority'] . '</priority>' . "\n";

            if (isset($url['lastmod'])) {
                $xml .= '    <lastmod>' . $url['lastmod'] . '</lastmod>' . "\n";
            }

            // Hreflang alternates (multilingual)
            if (!empty($url['alternates'])) {
                foreach ($url['alternates'] as $alt) {
                    $xml .= '    <xhtml:link' . "\n";
                    $xml .= '      rel="alternate"' . "\n";
                    $xml .= '      hreflang="' . htmlspecialchars($alt['hreflang'], ENT_XML1, 'UTF-8') . '"' . "\n";
                    $xml .= '      href="' . htmlspecialchars($alt['href'], ENT_XML1, 'UTF-8') . '"' . "\n";
                    $xml .= '    />' . "\n";
                }
            }

            // Images
            if (!empty($url['images'])) {
                foreach ($url['images'] as $image) {
                    $xml .= '    <image:image>' . "\n";
                    $xml .= '      <image:loc>' . htmlspecialchars($image['loc'], ENT_XML1, 'UTF-8') . '</image:loc>' . "\n";
                    if (!empty($image['caption'])) {
                        $xml .= '      <image:caption>' . htmlspecialchars($image['caption'], ENT_XML1, 'UTF-8') . '</image:caption>' . "\n";
                    }
                    $xml .= '    </image:image>' . "\n";
                }
            }

            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>';

        $this->logDebug("Generated sitemap with {count} URLs (multilingual: {ml})", [
            'count' => count($urls),
            'ml'    => $multilingual ? 'yes' : 'no',
        ]);

        return $xml;
    }

    /**
     * Generate empty sitemap when service is disabled
     */
    private function getEmptySitemap(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
    }

    protected function getServiceKey(): string
    {
        return 'enable_sitemap';
    }
}
