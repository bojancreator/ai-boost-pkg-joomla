<?php

/**
 * AI Boost — IndexabilityPolicy
 *
 * The SINGLE definition of "may this be indexed?", so the four notions of
 * "indexable" scattered across the codebase (sitemap / news / llms / llms-full)
 * can converge onto one authority and never drift again
 * (docs/analysis/T1-resolver-design.md §2.3; architecture.md §3 + B2).
 *
 * Two faces of the same decision:
 *  - PER-REQUEST — `forRenderedPage()` decides whether the page currently being
 *    rendered should be marked noindex. A page rendered to a guest is published
 *    and visible by definition, so it is indexable UNLESS the per-page noindex
 *    capability marks it otherwise. That capability defaults OFF (decision D2),
 *    so today every rendered page stays indexable — i.e. NO behaviour change.
 *  - ITEM-LEVEL — `isIndexableItem()` is the pure rule the bulk enumerators will
 *    apply to each candidate item (a future slice, S4). Pure + CMS-neutral; the
 *    Joomla WHERE-fragment that expresses the same rule for thousands of rows is
 *    lifted to the policy at S4. Nothing consumes it yet (slice S0 is dormant).
 *
 * @package     AiBoost\Lib\Page
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Page;

use Joomla\Database\DatabaseInterface;

defined('_JEXEC') or defined('ABSPATH') or die;

final class IndexabilityPolicy
{
    /**
     * Per-request verdict for the page being rendered.
     *
     * @param  bool $perPageNoindex  Whether a per-page "noindex this page" flag is
     *                               set for this request. Defaults to false — the
     *                               per-page noindex capability is OFF by default
     *                               (decision D2), so the verdict is "indexable"
     *                               and today's behaviour is preserved exactly.
     * @return array{0:bool,1:string}  [indexable, noindexReason] (reason '' when indexable).
     */
    public function forRenderedPage(PageType $type, bool $perPageNoindex = false): array
    {
        if ($perPageNoindex) {
            return [false, 'per-page noindex requested'];
        }

        return [true, ''];
    }

    /**
     * The one definition of a publicly-indexable content ITEM. Pure; the bulk
     * enumerators (sitemap / news / llms / llms-full) will apply this same rule
     * at slice S4 instead of each hand-writing its own state/access/window SQL.
     *
     * @param  bool $published          Item is published (state = 1).
     * @param  bool $guestVisible       Visible to the public/guest access level.
     * @param  bool $withinPublishWindow  Now is within publish_up / publish_down.
     * @param  bool $excludedType       Item is an explicitly excluded type/route.
     * @return array{0:bool,1:string}   [indexable, reason] (reason '' when indexable).
     */
    public function isIndexableItem(
        bool $published,
        bool $guestVisible,
        bool $withinPublishWindow,
        bool $excludedType = false
    ): array {
        if (!$published) {
            return [false, 'unpublished'];
        }
        if (!$withinPublishWindow) {
            return [false, 'outside publish window'];
        }
        if (!$guestVisible) {
            return [false, 'restricted access (not guest-visible)'];
        }
        if ($excludedType) {
            return [false, 'excluded page type'];
        }

        return [true, ''];
    }

    /**
     * T1 · S4 — the centralised, parameterised SQL-constraint contributor the
     * design (§2.3) intended for the BULK enumerators. Returns the item-level
     * indexability WHERE-clause fragments (published / publish-window / access)
     * for a bulk query, so sitemap / news / llms / llms-full stop hand-writing
     * four private filters and share ONE authority — one place to tighten later.
     *
     * BEHAVIOUR-PRESERVING BY CONTRACT: every parameter mirrors a current
     * enumerator's filter, so calling with EXACTLY today's parameters reproduces
     * that enumerator's rows (zero URL-set change). The genuine cross-list
     * unification (making the four consistent, which WOULD change some URLs) is
     * the deferred Option B — see BACKLOG; do NOT force it by changing defaults.
     *
     * The caller supplies `$now`/`$recentCutoff` (so the clock stays caller-side)
     * and the non-indexability clauses it owns (e.g. news `catid`, category
     * `extension`). Apply the returned fragments via QueryInterface::where().
     *
     * @param  string      $publishedExpr  Published/state column ('a.state'|'state'|'published').
     * @param  string      $window         'none' | 'sitemap' | 'llms' | 'recent'.
     * @param  string      $timeColumnPrefix  Alias qualifying publish_up/down ('' or e.g. 'a').
     * @param  string      $now            Caller's "now" timestamp (SQL datetime).
     * @param  string      $recentCutoff   Lower bound for window='recent'.
     * @param  string      $nullDate       Caller's DB null-date (e.g. $db->getNullDate());
     *                                     used only by the 'sitemap' publish-window variant.
     * @param  int[]|null  $guestLevels    Guest-authorised view levels → `$accessExpr IN (...)`.
     *                                     Null/empty = no access filter.
     * @param  string      $accessExpr     Access-level column for the IN / =1 check.
     * @param  bool        $publicAccessOnly  Add `$accessExpr = 1` (only when $guestLevels empty).
     * @param  bool        $requireCategoryGuest  Also require category guest-visibility + published.
     * @param  string      $catAccessExpr     Category access column (sitemap).
     * @param  string      $catPublishedExpr  Category published column (sitemap).
     * @return string[]    WHERE-clause fragments.
     */
    public function itemWhereClauses(
        DatabaseInterface $db,
        string $publishedExpr,
        string $window = 'none',
        string $timeColumnPrefix = '',
        string $now = '',
        string $recentCutoff = '',
        string $nullDate = '',
        ?array $guestLevels = null,
        string $accessExpr = '',
        bool $publicAccessOnly = false,
        bool $requireCategoryGuest = false,
        string $catAccessExpr = 'c.access',
        string $catPublishedExpr = 'c.published'
    ): array {
        $clauses = [];

        // Qualify publish_up/publish_down with the table alias when one is given.
        $time = static function (string $col) use ($db, $timeColumnPrefix): string {
            return $db->quoteName($timeColumnPrefix !== '' ? $timeColumnPrefix . '.' . $col : $col);
        };

        // Published / state.
        $clauses[] = $db->quoteName($publishedExpr) . ' = 1';

        // Publish window (each variant reproduces a specific enumerator's filter).
        switch ($window) {
            case 'sitemap':
                $clauses[] = '(' . $time('publish_up') . ' IS NULL OR ' . $time('publish_up') . ' <= ' . $db->quote($now) . ')';
                $clauses[] = '(' . $time('publish_down') . ' IS NULL OR ' . $time('publish_down') . ' = ' . $db->quote($nullDate) . ' OR ' . $time('publish_down') . ' > ' . $db->quote($now) . ')';
                break;
            case 'llms':
                $clauses[] = '(' . $time('publish_up') . ' IS NULL OR ' . $time('publish_up') . ' <= ' . $db->quote($now) . ')';
                $clauses[] = '(' . $time('publish_down') . ' IS NULL OR ' . $time('publish_down') . ' >= ' . $db->quote($now) . ')';
                break;
            case 'recent':
                $clauses[] = $time('publish_up') . ' IS NOT NULL';
                $clauses[] = $time('publish_up') . ' >= ' . $db->quote($recentCutoff);
                $clauses[] = $time('publish_up') . ' <= ' . $db->quote($now);
                break;
            case 'none':
            default:
                // No publish-window filter.
                break;
        }

        // Access.
        if ($guestLevels !== null && $guestLevels !== []) {
            $levels = implode(',', array_map('intval', $guestLevels));
            $clauses[] = $db->quoteName($accessExpr) . ' IN (' . $levels . ')';
            if ($requireCategoryGuest) {
                $clauses[] = $db->quoteName($catAccessExpr) . ' IN (' . $levels . ')';
                $clauses[] = $db->quoteName($catPublishedExpr) . ' = 1';
            }
        } elseif ($publicAccessOnly) {
            $clauses[] = $db->quoteName($accessExpr) . ' = 1';
        }

        return $clauses;
    }
}
