<?php
/**
 * AI Boost — Sitemap Generator Service
 *
 * Queries Joomla's database for published content and returns an array of
 * sitemap entries ready to be serialised as XML.
 *
 * Each entry is an associative array:
 *   loc         string  Absolute URL of the resource
 *   lastmod     string  ISO 8601 date (Y-m-d)
 *   changefreq  string  always|hourly|daily|weekly|monthly|yearly|never
 *   priority    string  0.1 – 1.0
 *   type        string  article|menu|category
 *   id          int     Database row ID (0 for menu/non-article entries)
 *   title       string  Article/category title (for image alt text)
 *   language    string  Joomla language tag, e.g. 'en-GB' (articles only)
 *   intro_image string  Relative path to intro image (articles only, may be '')
 *
 * DatabaseInterface is injected; this service makes no Factory:: or Uri:: calls.
 * Factory::getDate()->toSql() is replaced with date('Y-m-d H:i:s').
 *
 * @package     AiBoost\Plugin\System\AiBoostSitemap
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Plugin\System\AiBoostSitemap\Service;

defined('_JEXEC') or die;

use AiBoost\Lib\Page\IndexabilityPolicy;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;

class SitemapGenerator
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly DatabaseInterface $db,
        private readonly bool   $includeArticles    = true,
        private readonly bool   $includeMenuItems   = true,
        private readonly bool   $includeCategories  = false,
        private readonly bool   $includeTags        = false,
        private readonly string $defaultChangefreq  = 'weekly',
        private readonly string $defaultPriority    = '0.8',
        private readonly string $priorityHomepage   = '1.0',
        private readonly string $priorityArticles   = '0.8',
        private readonly string $priorityCategories = '0.6',
        private readonly string $priorityTags       = '0.4',
        /** @var int[] */
        private readonly array  $excludeMenuIds     = [],
        /** @var int[] */
        private readonly array  $excludeCatIds      = [],
        /**
         * View access levels an anonymous (guest) visitor is allowed to see.
         * Resolved by the extension layer (Access::getAuthorisedViewLevels(0))
         * and injected here so this service makes no CMS calls. When non-empty,
         * articles, categories, menu items and tags are filtered to these
         * levels (and articles additionally require a visible, published
         * category) so restricted (Registered / Special / custom) content
         * never appears in the sitemap.
         *
         * @var int[]
         */
        private readonly array  $guestViewLevels    = [],
    ) {}

    /**
     * Build and return all sitemap entries.
     *
     * @return array<int,array<string,mixed>>
     */
    public function generate(): array
    {
        $entries = [];

        // Homepage — always included (priority 1.0)
        $entries[] = [
            'loc'        => $this->baseUrl . '/',
            'lastmod'    => date('Y-m-d'),
            'changefreq' => 'daily',
            'priority'   => $this->priorityHomepage,
            'type'       => 'homepage',
            'id'         => 0,
            'title'      => '',
            'language'   => '',
            'intro_image'=> '',
        ];

        if ($this->includeArticles) {
            foreach ($this->fetchArticles() as $entry) {
                $entries[] = $entry;
            }
        }

        if ($this->includeCategories) {
            foreach ($this->fetchCategories() as $entry) {
                $entries[] = $entry;
            }
        }

        if ($this->includeMenuItems) {
            foreach ($this->fetchMenuItems() as $entry) {
                $entries[] = $entry;
            }
        }

        if ($this->includeTags) {
            foreach ($this->fetchTags() as $entry) {
                $entries[] = $entry;
            }
        }

        return $this->deduplicateByLoc($entries);
    }

    /**
     * Collapse entries that resolve to the same URL, keeping the first one.
     *
     * Different sources can produce the same <loc> — e.g. the homepage entry
     * and a menu item whose route also resolves to the site root, or (on
     * multilingual installs) several items a router maps onto one URL. Emitting
     * duplicate <loc> blocks is invalid sitemap markup, so we keep the first
     * occurrence (the homepage, added first with priority 1.0, wins).
     *
     * @param  array<int,array<string,mixed>> $entries
     * @return array<int,array<string,mixed>>
     */
    private function deduplicateByLoc(array $entries): array
    {
        $seen   = [];
        $unique = [];

        foreach ($entries as $entry) {
            $loc = (string) ($entry['loc'] ?? '');

            if ($loc === '' || isset($seen[$loc])) {
                continue;
            }

            $seen[$loc] = true;
            $unique[]   = $entry;
        }

        return $unique;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private fetchers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchArticles(): array
    {
        $entries = [];

        try {
            $db    = $this->db;
            $now   = date('Y-m-d H:i:s');
            $query = $db->getQuery(true)
                ->select([
                    'a.id',
                    'a.title',
                    'a.alias',
                    'a.catid',
                    'a.language',
                    'a.images',
                    'a.modified',
                    'c.alias AS cat_alias',
                ])
                ->from($db->quoteName('#__content', 'a'))
                ->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON c.id = a.catid')
                ->order('a.modified DESC');

            // T1·S4 — item indexability via the shared IndexabilityPolicy instead
            // of an inline filter. Called with exactly this enumerator's current
            // parameters (state + sitemap publish-window + guest-access on the
            // article AND its category, only when guest view levels are known), so
            // the row set — and the sitemap URL set — is unchanged.
            $hasGuestLevels = !empty($this->guestViewLevels);
            foreach ((new IndexabilityPolicy())->itemWhereClauses(
                $db,
                publishedExpr: 'a.state',
                window: 'sitemap',
                timeColumnPrefix: 'a',
                now: $now,
                nullDate: $db->getNullDate(),
                guestLevels: $hasGuestLevels ? $this->guestViewLevels : null,
                accessExpr: 'a.access',
                requireCategoryGuest: $hasGuestLevels,
            ) as $where) {
                $query->where($where);
            }

            $db->setQuery($query);
            $rows = $db->loadObjectList();
        } catch (\Throwable) {
            return [];
        }

        foreach ($rows as $row) {
            if (!empty($this->excludeCatIds) && in_array((int) $row->catid, $this->excludeCatIds, true)) {
                continue;
            }

            $loc         = $this->buildArticleUrl($row);
            $introImage  = $this->extractIntroImage((string) ($row->images ?? ''));
            $lastmod     = $row->modified ? date('Y-m-d', strtotime($row->modified)) : date('Y-m-d');

            $entries[] = [
                'loc'        => $loc,
                'lastmod'    => $lastmod,
                'changefreq' => $this->defaultChangefreq,
                'priority'   => $this->priorityArticles,
                'type'       => 'article',
                'id'         => (int) $row->id,
                'title'      => (string) ($row->title ?? ''),
                'language'   => (string) ($row->language ?? ''),
                'intro_image'=> $introImage,
            ];
        }

        return $entries;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchCategories(): array
    {
        $entries = [];

        try {
            $db    = $this->db;
            $query = $db->getQuery(true)
                ->select(['id', 'title', 'alias', 'modified_time'])
                ->from($db->quoteName('#__categories'))
                ->where('extension = ' . $db->quote('com_content'))
                ->where('published = 1')
                ->where('level > 0')
                ->order('lft ASC');

            // Restrict to access levels a guest can see (mirrors front-end ACL).
            if (!empty($this->guestViewLevels)) {
                $levels = implode(',', array_map('intval', $this->guestViewLevels));
                $query->where($db->quoteName('access') . ' IN (' . $levels . ')');
            }

            $db->setQuery($query);
            $rows = $db->loadObjectList();
        } catch (\Throwable) {
            return [];
        }

        foreach ($rows as $row) {
            if (!empty($this->excludeCatIds) && in_array((int) $row->id, $this->excludeCatIds, true)) {
                continue;
            }

            $loc     = $this->buildCategoryUrl($row);
            $lastmod = $row->modified_time ? date('Y-m-d', strtotime($row->modified_time)) : date('Y-m-d');

            $entries[] = [
                'loc'        => $loc,
                'lastmod'    => $lastmod,
                'changefreq' => 'monthly',
                'priority'   => $this->priorityCategories,
                'type'       => 'category',
                'id'         => (int) $row->id,
                'title'      => (string) ($row->title ?? ''),
                'language'   => '',
                'intro_image'=> '',
            ];
        }

        return $entries;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchMenuItems(): array
    {
        $entries = [];

        try {
            $db    = $this->db;
            $query = $db->getQuery(true)
                ->select(['id', 'title', 'alias', 'link', 'modified'])
                ->from($db->quoteName('#__menu'))
                ->where('published = 1')
                ->where('client_id = 0')
                ->where('type = ' . $db->quote('component'))
                ->where('home = 0')
                ->where('level > 0')
                ->order('lft ASC');

            // Restrict to access levels a guest can see (mirrors front-end ACL).
            if (!empty($this->guestViewLevels)) {
                $levels = implode(',', array_map('intval', $this->guestViewLevels));
                $query->where($db->quoteName('access') . ' IN (' . $levels . ')');
            }

            $db->setQuery($query);
            $rows = $db->loadObjectList();
        } catch (\Throwable) {
            return [];
        }

        $seen = [];

        foreach ($rows as $row) {
            if (!empty($this->excludeMenuIds) && in_array((int) $row->id, $this->excludeMenuIds, true)) {
                continue;
            }

            $loc = $this->buildMenuUrl($row);
            if ($loc === '' || isset($seen[$loc])) {
                continue;
            }
            $seen[$loc] = true;

            $lastmod = $row->modified ? date('Y-m-d', strtotime($row->modified)) : date('Y-m-d');

            $entries[] = [
                'loc'        => $loc,
                'lastmod'    => $lastmod,
                'changefreq' => 'monthly',
                'priority'   => $this->defaultPriority,
                'type'       => 'menu',
                'id'         => (int) $row->id,
                'title'      => (string) ($row->title ?? ''),
                'language'   => '',
                'intro_image'=> '',
            ];
        }

        return $entries;
    }

    /**
     * Fetch published Joomla tags and build sitemap entries for each tag page.
     *
     * @return array<int,array<string,mixed>>
     */
    private function fetchTags(): array
    {
        $entries = [];

        try {
            $db    = $this->db;
            $query = $db->getQuery(true)
                ->select(['t.id', 't.alias', 't.modified_time'])
                ->from($db->quoteName('#__tags', 't'))
                ->join(
                    'INNER',
                    $db->quoteName('#__contentitem_tag_map', 'tm') . ' ON tm.tag_id = t.id'
                )
                ->where('t.published = 1')
                ->where('t.level > 0')
                ->group('t.id')
                ->order('t.modified_time DESC');

            // Restrict to access levels a guest can see (mirrors front-end ACL).
            if (!empty($this->guestViewLevels)) {
                $levels = implode(',', array_map('intval', $this->guestViewLevels));
                $query->where($db->quoteName('t.access') . ' IN (' . $levels . ')');
            }

            $db->setQuery($query);
            $rows = $db->loadObjectList();
        } catch (\Throwable) {
            return [];
        }

        $seen = [];

        foreach ($rows as $row) {
            try {
                $sef = Route::_(
                    'index.php?option=com_tags&view=tag&id=' . (int) $row->id . ':' . $row->alias,
                    false
                );
                $loc = $this->absolute($sef);
            } catch (\Throwable) {
                $loc = $this->baseUrl . '/tags/' . $row->alias;
            }

            if ($loc === '' || isset($seen[$loc])) {
                continue;
            }
            $seen[$loc] = true;

            $lastmod = $row->modified_time
                ? date('Y-m-d', strtotime($row->modified_time))
                : date('Y-m-d');

            $entries[] = [
                'loc'        => $loc,
                'lastmod'    => $lastmod,
                'changefreq' => 'weekly',
                'priority'   => $this->priorityTags,
                'type'       => 'tag',
                'id'         => (int) $row->id,
                'title'      => '',
                'language'   => '',
                'intro_image'=> '',
            ];
        }

        return $entries;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // URL builders
    // ─────────────────────────────────────────────────────────────────────────

    private function buildArticleUrl(object $row): string
    {
        try {
            $sef = Route::_(
                'index.php?option=com_content&view=article'
                . '&id=' . (int) $row->id . ':' . $row->alias
                . '&catid=' . (int) $row->catid,
                false
            );
            return $this->absolute($sef);
        } catch (\Throwable) {
            $cat = $row->cat_alias ? '/' . $row->cat_alias : '';
            return $this->baseUrl . $cat . '/' . $row->alias;
        }
    }

    private function buildCategoryUrl(object $row): string
    {
        try {
            $sef = Route::_(
                'index.php?option=com_content&view=category&layout=blog&id=' . (int) $row->id,
                false
            );
            return $this->absolute($sef);
        } catch (\Throwable) {
            return $this->baseUrl . '/' . $row->alias;
        }
    }

    private function buildMenuUrl(object $row): string
    {
        try {
            $link = (string) ($row->link ?? '');
            if ($link === '' || !str_starts_with($link, 'index.php')) {
                return '';
            }
            $sef = Route::_($link . '&Itemid=' . (int) $row->id, false);
            return $this->absolute($sef);
        } catch (\Throwable) {
            return '';
        }
    }

    private function absolute(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return $this->baseUrl . '/' . ltrim($path, '/');
    }

    private function extractIntroImage(string $json): string
    {
        if ($json === '') {
            return '';
        }

        try {
            $data = json_decode($json, true, 4, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return '';
        }

        $path = trim((string) ($data['image_intro'] ?? ''));
        if ($path === '' || $path === '0') {
            return '';
        }

        if (!str_starts_with($path, '/') && !str_starts_with($path, 'http')) {
            $path = '/' . $path;
        }

        return $path;
    }
}
