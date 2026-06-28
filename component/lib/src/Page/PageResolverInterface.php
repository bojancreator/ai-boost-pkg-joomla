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
    /** Resolve (and memoise) the PageContext for the current request. */
    public function resolve(): PageContext;
}
