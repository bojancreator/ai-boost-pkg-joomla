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

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Services;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * Enhanced Sitemap Service with Configurable Content
 */
class SitemapService extends AbstractService
{
    /**
     * Generate complete sitemap with configurable content
     */
    public function generateSitemap(): string
    {
        if (!$this->isEnabled()) {
            return $this->getEmptySitemap();
        }

        $urls = [];

        // Homepage (always included)
        $urls[] = $this->createUrlEntry($this->getBaseUrl(), '1.0', 'daily');

        // Articles (if enabled)
        if ($this->params->get('sitemap_include_articles', 1)) {
            $articles = $this->getPublishedArticles();
            $priority = $this->params->get('sitemap_priority_articles', '0.8');
            $changefreq = $this->params->get('sitemap_changefreq_articles', 'weekly');

            foreach ($articles as $article) {
                // Get featured images for this article
                $images = $this->getArticleImages($article->id);

                $urls[] = $this->createUrlEntry(
                    $article->url,
                    $priority,
                    $changefreq,
                    $article->modified,
                    $images
                );
            }
        }

        // Categories (if enabled)
        if ($this->params->get('sitemap_include_categories', 1)) {
            $categories = $this->getPublishedCategories();
            $priority = $this->params->get('sitemap_priority_categories', '0.7');
            $changefreq = $this->params->get('sitemap_changefreq_categories', 'weekly');

            foreach ($categories as $category) {
                $urls[] = $this->createUrlEntry($category->url, $priority, $changefreq);
            }
        }

        // Menu items (if enabled)
        if ($this->params->get('sitemap_include_menu', 0)) {
            $menuItems = $this->getMenuItems();
            $priority = $this->params->get('sitemap_priority_menu', '0.6');
            $changefreq = $this->params->get('sitemap_changefreq_menu', 'monthly');

            foreach ($menuItems as $item) {
                $urls[] = $this->createUrlEntry($item->url, $priority, $changefreq);
            }
        }

        return $this->buildXml($urls);
    }

    /**
     * Get published articles with exclusions
     */
    private function getPublishedArticles(): array
    {
        try {
            $db = Factory::getDbo();
            $excludeIds = $this->params->get('sitemap_exclude_ids', '');
            $excludeArray = array_filter(array_map('trim', explode(',', $excludeIds)));
            $selectedCats = $this->params->get('sitemap_article_categories', []);
            $maxArticles = (int)$this->params->get('sitemap_max_articles', 0);


            $query = $db->getQuery(true)
                ->select('a.id, a.alias, a.modified, a.catid, c.alias AS cat_alias')
                ->from('#__content AS a')
                ->leftJoin('#__categories AS c ON c.id = a.catid')
                ->where('a.state = 1');

            // Filter by selected categories
            if (!empty($selectedCats) && is_array($selectedCats)) {
                $query->where('a.catid IN (' . implode(',', array_map('intval', $selectedCats)) . ')');
            }


            if (!empty($excludeArray)) {
                $query->where('a.id NOT IN (' . implode(',', array_map('intval', $excludeArray)) . ')');
            }

            $db->setQuery($query);
            $articles = $db->loadObjectList();

            // Build URLs
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
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('id, alias, title')
                ->from('#__categories')
                ->where('published = 1')
                ->where('extension = ' . $db->quote('com_content'));

            $db->setQuery($query);
            $categories = $db->loadObjectList();

            // Build URLs
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
            $db = Factory::getDbo();
            $maxDepth = (int)$this->params->get('sitemap_menu_depth', 0);

            $query = $db->getQuery(true)
                ->select('id, alias, link, type, level')
                ->from('#__menu')
                ->where('published = 1')
                ->where('client_id = 0')  // Site menu only
                ->where('type != ' . $db->quote('url'));  // Exclude external links

            // Apply depth filtering if configured
            if ($maxDepth > 0) {
                $query->where('level <= ' . (int)$maxDepth);
            }

            $db->setQuery($query);
            $items = $db->loadObjectList();

            // Build URLs
            foreach ($items as $item) {
                // Skip if link is empty or external
                if (empty($item->link) || str_starts_with($item->link, 'http')) {
                    continue;
                }

                $item->url = $this->getBaseUrl() . Route::_($item->link);
            }

            $this->logDebug("Loaded {count} menu items for sitemap (depth: {depth})", [
                'count' => count($items),
                'depth' => $maxDepth === 0 ? 'unlimited' : $maxDepth
            ]);
            return array_filter($items, fn($i) => !empty($i->url));
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
            $db = Factory::getDbo();

            // Get images JSON from article
            $query = $db->getQuery(true)
                ->select('images')
                ->from('#__content')
                ->where('id = ' . (int)$articleId);

            $db->setQuery($query);
            $imagesJson = $db->loadResult();

            $images = [];
            if ($imagesJson) {
                $imageData = json_decode($imagesJson);

                // Add intro image
                if (!empty($imageData->image_intro)) {
                    $images[] = [
                        'loc' => Uri::root() . $imageData->image_intro,
                        'caption' => $imageData->image_intro_alt ?? '',
                    ];
                }

                // Add fulltext image (only if different from intro)
                if (!empty($imageData->image_fulltext) && $imageData->image_fulltext !== $imageData->image_intro) {
                    $images[] = [
                        'loc' => Uri::root() . $imageData->image_fulltext,
                        'caption' => $imageData->image_fulltext_alt ?? '',
                    ];
                }
            }

            return $images;
        } catch (\Exception $e) {
            // Return empty array on error
            return [];
        }
    }

    /**
     * Create URL entry for sitemap
     */
    private function createUrlEntry(
        string $url,
        string $priority = '0.5',
        string $changefreq = 'weekly',
        ?string $lastmod = null,
        array $images = []
    ): array {
        $entry = [
            'loc' => $url,
            'priority' => $priority,
            'changefreq' => $changefreq
        ];

        if ($lastmod) {
            try {
                $date = Factory::getDate($lastmod);
                // ISO 8601 format for AI crawlers (e.g., 2025-12-08T10:30:00+01:00)
                $entry['lastmod'] = $date->format('c');
            } catch (\Exception $e) {
                // Skip lastmod if date parsing fails
            }
        }

        if (!empty($images)) {
            $entry['images'] = $images;
        }

        return $entry;
    }

    /**
     * Build XML from URL entries
     */
    private function buildXml(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        $xml .= '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

        foreach ($urls as $url) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8') . '</loc>' . "\n";
            $xml .= '    <changefreq>' . $url['changefreq'] . '</changefreq>' . "\n";
            $xml .= '    <priority>' . $url['priority'] . '</priority>' . "\n";

            if (isset($url['lastmod'])) {
                $xml .= '    <lastmod>' . $url['lastmod'] . '</lastmod>' . "\n";
            }

            // Render images if present
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

        $this->logDebug("Generated sitemap with {count} URLs", ['count' => count($urls)]);
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
