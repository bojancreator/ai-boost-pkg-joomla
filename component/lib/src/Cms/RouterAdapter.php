<?php
/**
 * AI Boost — RouterAdapter interface
 *
 * Abstracts URL generation. On Joomla this wraps Route::_() and Uri::root();
 * on WordPress it will wrap home_url() / get_permalink() / add_query_arg().
 *
 * Lib services that today reach for Uri::root() / Route::_() go through
 * this adapter instead so the WP port can map them onto WordPress URL
 * helpers without touching the call sites.
 *
 * @package     AiBoost\Lib\Cms
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Cms;

defined('_JEXEC') or defined('ABSPATH') or die;

interface RouterAdapter
{
    /** Absolute site root URL with no trailing slash (e.g. https://example.com). */
    public function getSiteRoot(): string;

    /** Absolute URL of the current request. */
    public function getCurrentUrl(): string;

    /**
     * Build an SEF/canonical URL for an internal path or query string.
     * On Joomla this wraps Route::_($path, false, …, true) to return an
     * absolute URL; on WordPress it builds via home_url($path).
     */
    public function buildUrl(string $pathOrQuery, bool $absolute = true): string;
}
