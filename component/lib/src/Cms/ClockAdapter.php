<?php
/**
 * AI Boost — ClockAdapter
 *
 * Thin boundary around the host CMS's "now" timestamp helper. Joomla
 * uses Factory::getDate()->toSql(); the WP port will use current_time().
 * Centralised so tests can freeze time and lib services don't reach for
 * Factory directly.
 *
 * @package     AiBoost\Lib\Cms
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Cms;

defined('_JEXEC') or defined('ABSPATH') or die;

interface ClockAdapter
{
    /** Current UTC timestamp formatted for SQL ('Y-m-d H:i:s'). */
    public function nowSql(): string;
}
