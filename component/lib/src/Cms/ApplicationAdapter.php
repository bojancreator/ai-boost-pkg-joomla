<?php
/**
 * AI Boost — ApplicationAdapter
 *
 * Thin boundary around the host CMS's request body / client-type
 * primitives. HeadBlockBuilder / BodyBlockBuilder use it to read and
 * rewrite the rendered HTML in onAfterRender. Joomla wraps
 * CMSApplication; the WP port will wrap the buffered output filter
 * (ob_start in wp_head/wp_footer).
 *
 * @package     AiBoost\Lib\Cms
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Cms;

defined('_JEXEC') or defined('ABSPATH') or die;

interface ApplicationAdapter
{
    /** True when the current request is the public site (not admin/api/cli). */
    public function isSite(): bool;

    /** Current rendered response body. */
    public function getBody(): string;

    /** Replace the rendered response body. */
    public function setBody(string $body): void;

    /** Host portion of the current request URL (no scheme, no path). */
    public function getHost(): string;
}
