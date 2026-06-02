<?php
/**
 * AI Boost — SEO Plugin — Canonical Builder
 *
 * Builds the canonical URL for the current page by:
 *   1. Starting from the current request URI.
 *   2. Stripping known tracking / session query parameters
 *      (utm_*, fbclid, gclid, mc_eid, msclkid, lang, etc.).
 *   3. Always stripping ?start= / ?limitstart= pagination offset so the
 *      canonical always points to the first page of a list (page 1).
 *
 * Pro: also builds <link rel="prev"> and <link rel="next"> for paginated
 *      category/blog views when pagination_canonical is enabled. These are
 *      built from the actual request offset, not from a model total (which
 *      is not reliably accessible from a plugin context). We emit prev when
 *      offset > 0, and next only when offset + limit < total (total resolved
 *      via a DB count on the current category/featured view).
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
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

class CanonicalBuilder
{
    private Registry $params;
    private bool $isPro;
    private CMSApplication $app;

    /**
     * Query parameters always stripped from the canonical URL.
     * Includes tracking params, session params, and multilingual ?lang=.
     */
    private const STRIP_PARAMS = [
        // UTM campaign tracking
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        // Social / advertising click IDs
        'fbclid', 'gclid', 'msclkid', 'mc_eid', 'twclid', 'igshid',
        // Joomla language / formatting params
        'lang', 'format', 'no_html', 'tmpl',
    ];

    public function __construct(Registry $params, bool $isPro, CMSApplication $app)
    {
        $this->params = $params;
        $this->isPro  = $isPro;
        $this->app    = $app;
    }

    /**
     * Return the canonical URL for the current page.
     *
     * Always points to page 1 of paginated content (strips ?start= and
     * ?limitstart= regardless of their value). Returns '' on failure.
     */
    public function getCanonical(): string
    {
        $uri = clone Uri::getInstance();

        // Always strip the language routing param — including it in the canonical
        // causes duplicate-content fragmentation between language versions.
        // This is unconditional and not tied to the strip_query_strings toggle.
        $uri->delVar('lang');

        if ((bool) $this->params->get('strip_query_strings', 1)) {
            // Strip remaining tracking / session params when toggle is on
            foreach (self::STRIP_PARAMS as $param) {
                $uri->delVar($param); // 'lang' already removed above, no-op here
            }
        }

        // Canonical always = page 1: strip pagination offset unconditionally.
        $uri->delVar('start');
        $uri->delVar('limitstart');

        return $this->buildAbsolute($uri);
    }

    /**
     * Return the <link rel="prev"> URL, or '' if there is no previous page.
     *
     * Gated to the same com_content category/featured list views as getNext()
     * so that prev links are never emitted outside paginated list contexts
     * (e.g. single article pages with ?start= in the URL for something else).
     */
    public function getPrev(): string
    {
        // Only emit on paginated list views
        if (!$this->isPaginatedListView()) {
            return '';
        }

        $start = $this->getStart();
        if ($start <= 0) {
            return ''; // Already on page 1 — no prev
        }

        $limit      = $this->getLimit();
        $prevOffset = max(0, $start - $limit);
        return $this->buildPaginatedUrl($prevOffset);
    }

    /**
     * Return the <link rel="next"> URL, or '' if we cannot confirm a next page.
     *
     * Next is only emitted when we can confirm that more items exist beyond
     * the current offset. We query the item count for the current view's
     * category (com_content category/featured) to avoid guessing.
     */
    public function getNext(): string
    {
        $start = $this->getStart();
        $limit = $this->getLimit();
        $total = $this->resolveTotal();

        // Only emit next when total is known and next offset is within range
        if ($total <= 0) {
            return '';
        }

        $nextOffset = $start + $limit;
        if ($nextOffset >= $total) {
            return ''; // Last page — no next
        }

        return $this->buildPaginatedUrl($nextOffset);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build an absolute URL from a Uri object.
     * Ensures scheme and host are present (uses Uri::base() as fallback).
     */
    private function buildAbsolute(Uri $uri): string
    {
        if ($uri->getHost() === '') {
            $baseUri = new Uri(Uri::base(false));
            $uri->setScheme($baseUri->getScheme());
            $uri->setHost($baseUri->getHost());
            $port = $baseUri->getPort();
            if ($port) {
                $uri->setPort($port);
            }
        }

        return $uri->toString(['scheme', 'host', 'port', 'path', 'query']);
    }

    /**
     * Build a paginated URL by setting ?start= on the current base URL.
     * Tracking / language params are stripped; pagination offset is applied.
     *
     * @param  int    $offset  The ?start= value (0 = strip the param).
     * @return string          Absolute URL.
     */
    private function buildPaginatedUrl(int $offset): string
    {
        $uri = clone Uri::getInstance();

        foreach (self::STRIP_PARAMS as $param) {
            $uri->delVar($param);
        }

        if ($offset > 0) {
            $uri->setVar('start', $offset);
            $uri->delVar('limitstart');
        } else {
            $uri->delVar('start');
            $uri->delVar('limitstart');
        }

        return $this->buildAbsolute($uri);
    }

    /**
     * Get the current ?start= (or ?limitstart=) offset from the request.
     */
    private function getStart(): int
    {
        $input = $this->app->getInput();
        $start = max(
            (int) $input->get('start', 0),
            (int) $input->get('limitstart', 0)
        );
        return max(0, $start);
    }

    /**
     * Get the configured list limit (items per page).
     */
    private function getLimit(): int
    {
        return max(1, (int) $this->app->get('list_limit', 20));
    }

    /**
     * Return true when the current request is a com_content list view
     * (category, blog, or featured) that Joomla paginates with ?start=.
     *
     * Both getPrev() and getNext() are gated behind this check to prevent
     * emitting pagination links on single-article pages or other views that
     * happen to have a ?start= query parameter for unrelated reasons.
     */
    private function isPaginatedListView(): bool
    {
        $input  = $this->app->getInput();
        $option = $input->get('option', '');
        $view   = $input->get('view', '');

        if ($option !== 'com_content') {
            return false;
        }

        return in_array($view, ['category', 'blog', 'featured'], true);
    }

    /**
     * Resolve the total number of items for the current list view.
     *
     * For com_content category/featured views, queries the DB directly using
     * the category ID from the request so the count is scoped to the right set.
     * Returns 0 if total cannot be determined (safe: suppresses next link).
     */
    private function resolveTotal(): int
    {
        $input  = $this->app->getInput();
        $option = $input->get('option', '');
        $view   = $input->get('view', '');

        if ($option !== 'com_content') {
            return 0;
        }

        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('state') . ' = 1');

            if ($view === 'category' || $view === 'blog') {
                $catId = (int) $input->get('id', 0);
                if ($catId > 0) {
                    $query->where($db->quoteName('catid') . ' = ' . $catId);
                } else {
                    return 0;
                }
            } elseif ($view === 'featured') {
                $query->where($db->quoteName('featured') . ' = 1');
            } else {
                return 0;
            }

            $db->setQuery($query);
            return max(0, (int) $db->loadResult());
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
