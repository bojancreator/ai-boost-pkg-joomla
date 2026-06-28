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
}
