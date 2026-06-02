<?php
/**
 * AI Boost — News Sitemap Generator (Pro)
 *
 * Generates /sitemap-news.xml for Google News with articles published in the
 * last 48 hours from a configured Joomla category.
 *
 * Namespace: http://www.google.com/schemas/sitemap-news/0.9
 *
 * DatabaseInterface is injected; this service makes no Factory:: calls.
 * Factory::getDate()->toSql() is replaced with date('Y-m-d H:i:s').
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

class NewsSitemapGenerator
{
    private const NEWS_WINDOW_HOURS = 48;

    public function __construct(
        private readonly string $baseUrl,
        private readonly int    $categoryId,
        private readonly string $publicationName,
        private readonly DatabaseInterface $db,
    ) {}

    /**
     * Generate the full /sitemap-news.xml document.
     */
    public function generate(): string
    {
        $articles = $this->fetchRecentArticles();

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset'
            . ' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
            . ' xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"'
            . ">\n";

        $pubName = htmlspecialchars(
            $this->publicationName !== '' ? $this->publicationName : 'News',
            ENT_XML1
        );

        foreach ($articles as $art) {
            $loc  = $this->buildArticleUrl($art);
            $lang = strtolower(substr((string) ($art->language ?? 'en'), 0, 2));
            $date = $art->publish_up
                ? date('Y-m-d\TH:i:sP', strtotime($art->publish_up))
                : date('Y-m-d\TH:i:sP');

            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars($loc, ENT_XML1) . "</loc>\n";
            $xml .= "    <news:news>\n";
            $xml .= "      <news:publication>\n";
            $xml .= '        <news:name>'     . $pubName . "</news:name>\n";
            $xml .= '        <news:language>' . htmlspecialchars($lang, ENT_XML1) . "</news:language>\n";
            $xml .= "      </news:publication>\n";
            $xml .= '      <news:publication_date>' . htmlspecialchars($date, ENT_XML1) . "</news:publication_date>\n";
            $xml .= '      <news:title>'           . htmlspecialchars((string) $art->title, ENT_XML1) . "</news:title>\n";
            $xml .= "    </news:news>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Fetch articles published within the news window from the configured category.
     *
     * @return object[]
     */
    private function fetchRecentArticles(): array
    {
        if ($this->categoryId <= 0) {
            return [];
        }

        try {
            $db     = $this->db;
            $cutoff = date('Y-m-d H:i:s', time() - self::NEWS_WINDOW_HOURS * 3600);
            $now    = date('Y-m-d H:i:s');

            $query = $db->getQuery(true)
                ->select(['a.id', 'a.title', 'a.alias', 'a.catid', 'a.language', 'a.publish_up', 'c.alias AS cat_alias'])
                ->from($db->quoteName('#__content', 'a'))
                ->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON c.id = a.catid')
                ->where('a.state = 1')
                ->where('a.catid = ' . $this->categoryId)
                ->where('a.publish_up IS NOT NULL')
                ->where('a.publish_up >= ' . $db->quote($cutoff))
                ->where('a.publish_up <= ' . $db->quote($now))
                ->order('a.publish_up DESC');

            $db->setQuery($query, 0, 1000);
            return $db->loadObjectList() ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function buildArticleUrl(object $art): string
    {
        try {
            $sef = Route::_(
                'index.php?option=com_content&view=article'
                . '&id=' . (int) $art->id . ':' . $art->alias
                . '&catid=' . (int) $art->catid,
                false
            );

            if (str_starts_with($sef, 'http')) {
                return $sef;
            }

            return $this->baseUrl . '/' . ltrim($sef, '/');
        } catch (\Throwable) {
            $cat = $art->cat_alias ? '/' . $art->cat_alias : '';
            return $this->baseUrl . $cat . '/' . $art->alias;
        }
    }
}
