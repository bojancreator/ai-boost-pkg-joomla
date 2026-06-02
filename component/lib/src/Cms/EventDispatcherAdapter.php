<?php
/**
 * AI Boost — EventDispatcherAdapter interface
 *
 * Abstracts the host CMS event/hook dispatcher used by the manifest layer
 * to discover Pro-plugin field contributions. On Joomla this wraps
 * CMSApplication::triggerEvent(); on WordPress it will wrap apply_filters().
 *
 * @package     AiBoost\Lib\Cms
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Cms;

defined('_JEXEC') or defined('ABSPATH') or die;

interface EventDispatcherAdapter
{
    /**
     * Dispatch an event/hook and return an array of listener return values.
     *
     * @param string $event Event name (e.g. 'onAiBoostRegisterFields').
     * @param array<int,mixed> $args Positional arguments for listeners.
     * @return array<int,mixed> Listener return values (any non-array entry is left in place).
     */
    public function trigger(string $event, array $args = []): array;
}
