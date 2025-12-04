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
            foreach ($articles as $article) {
                $urls[] = $this->createUrlEntry(
                    $article->url,
                    '0.8',
                    'weekly',
                    $article->modified
                );
            }
        }

        // Categories (if enabled)
        if ($this->params->get('sitemap_include_categories', 1)) {
            $categories = $this->getPublishedCategories();
            foreach ($categories as $category) {
                $urls[] = $this->createUrlEntry($category->url, '0.7', 'weekly');
            }
        }

        // Menu items (if enabled)
        if ($this->params->get('sitemap_include_menu', 0)) {
            $menuItems = $this->getMenuItems();
            foreach ($menuItems as $item) {
                $urls[] = $this->createUrlEntry($item->url, '0.6', 'monthly');
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

            $query = $db->getQuery(true)
                ->select('a.id, a.alias, a.modified, a.catid, c.alias AS cat_alias')
                ->from('#__content AS a')
                ->leftJoin('#__categories AS c ON c.id = a.catid')
                ->where('a.state = 1');

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
            $query = $db->getQuery(true)
                ->select('id, alias, link, type')
                ->from('#__menu')
                ->where('published = 1')
                ->where('client_id = 0')  // Site menu only
                ->where('type != ' . $db->quote('url'));  // Exclude external links

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

            $this->logDebug("Loaded {count} menu items for sitemap", ['count' => count($items)]);
            return array_filter($items, fn($i) => !empty($i->url));
        } catch (\Throwable $e) {
            $this->logDebug('Menu loading failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Create URL entry for sitemap
     */
    private function createUrlEntry(string $url, string $priority = '0.5', string $changefreq = 'weekly', ?string $lastmod = null): array
    {
        $entry = [
            'loc' => $url,
            'priority' => $priority,
            'changefreq' => $changefreq
        ];

        if ($lastmod) {
            try {
                $date = Factory::getDate($lastmod);
                $entry['lastmod'] = $date->format('Y-m-d');
            } catch (\Exception $e) {
                // Skip lastmod if date parsing fails
            }
        }

        return $entry;
    }

    /**
     * Build XML from URL entries
     */
    private function buildXml(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8') . '</loc>' . "\n";
            $xml .= '    <changefreq>' . $url['changefreq'] . '</changefreq>' . "\n";
            $xml .= '    <priority>' . $url['priority'] . '</priority>' . "\n";

            if (isset($url['lastmod'])) {
                $xml .= '    <lastmod>' . $url['lastmod'] . '</lastmod>' . "\n";
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
