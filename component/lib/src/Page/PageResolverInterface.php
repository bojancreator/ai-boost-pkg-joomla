<?php

/**
 * AI Boost — PageResolverInterface
 *
 * Contract for the single per-request page resolver. Implemented by
 * PageResolver (Joomla v1.0) and, later, a WordPress equivalent. Consumers
 * obtain it via AdapterRegistry::pageResolver() and call resolve() instead of
 * re-deriving page context themselves.
 *
 * Part of T1 slice S0 (docs/analysis/T1-resolver-design.md §2.2).
 *
 * @package     AiBoost\Lib\Page
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Page;

defined('_JEXEC') or defined('ABSPATH') or die;

interface PageResolverInterface
{
    /**
     * Resolve (and memoise) the PageContext for the current request.
     *
     * @param ?string $canonicalUrlMap  T1·S5: the raw `canonical_url_map` setting
     *        (a JSON map of path-prefix → canonical target), threaded in only by
     *        the canonical consumer (aiboost_core). null = no map → the bare
     *        scheme://host/path canonical that the base context already carries.
     *        Every other consumer passes nothing and is byte-unaffected. A map
     *        hit returns a context carrying the mapped canonical WITHOUT mutating
     *        the memoised base, so the answer is the same regardless of which
     *        consumer resolves first.
     */
    public function resolve(?string $canonicalUrlMap = null): PageContext;
}
