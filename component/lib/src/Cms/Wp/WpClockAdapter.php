<?php
/**
 * AI Boost — WpClockAdapter (WordPress placeholder)
 *
 * Stub implementation of ClockAdapter. Real impl will call
 * current_time('mysql', 1). Until then returns gmdate('Y-m-d H:i:s').
 *
 * @package     AiBoost\Lib\Cms\Wp
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Cms\Wp;

defined('_JEXEC') or defined('ABSPATH') or die;

use AiBoost\Lib\Cms\ClockAdapter;

final class WpClockAdapter implements ClockAdapter
{
    public function nowSql(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
