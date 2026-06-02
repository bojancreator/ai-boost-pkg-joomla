<?php
/**
 * AI Boost — JoomlaEventDispatcherAdapter
 *
 * Joomla implementation of EventDispatcherAdapter. Wraps
 * CMSApplication::triggerEvent().
 *
 * @package     AiBoost\Lib\Cms\Joomla
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Cms\Joomla;

defined('_JEXEC') or die;

use AiBoost\Lib\Cms\EventDispatcherAdapter;
use Joomla\CMS\Factory;

final class JoomlaEventDispatcherAdapter implements EventDispatcherAdapter
{
    public function trigger(string $event, array $args = []): array
    {
        try {
            if (!class_exists('Joomla\\CMS\\Factory')) {
                return [];
            }
            $app = Factory::getApplication();
            $out = $app->triggerEvent($event, $args);
            return is_array($out) ? $out : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
