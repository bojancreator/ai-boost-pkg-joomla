<?php
/**
 * AI Boost — WpEventDispatcherAdapter
 *
 * Placeholder WordPress implementation of EventDispatcherAdapter. The v2.0
 * port will route through apply_filters($event, []) so Pro plugins can
 * contribute manifest fields via add_filter('onAiBoostRegisterFields', ...).
 *
 * @package     AiBoost\Lib\Cms\Wp
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Cms\Wp;

defined('_JEXEC') or defined('ABSPATH') or die;

use AiBoost\Lib\Cms\EventDispatcherAdapter;

final class WpEventDispatcherAdapter implements EventDispatcherAdapter
{
    public function trigger(string $event, array $args = []): array
    {
        if (!function_exists('apply_filters')) {
            return [];
        }
        $result = \apply_filters($event, [], ...$args);
        return is_array($result) ? $result : [];
    }
}
