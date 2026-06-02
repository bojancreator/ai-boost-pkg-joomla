<?php
/**
 * AI Boost — JoomlaClockAdapter
 *
 * Joomla implementation of ClockAdapter. Delegates to
 * Factory::getDate()->toSql().
 *
 * @package     AiBoost\Lib\Cms\Joomla
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Cms\Joomla;

defined('_JEXEC') or die;

use AiBoost\Lib\Cms\ClockAdapter;
use Joomla\CMS\Factory;

final class JoomlaClockAdapter implements ClockAdapter
{
    public function nowSql(): string
    {
        try {
            return (string) Factory::getDate()->toSql();
        } catch (\Throwable) {
            return gmdate('Y-m-d H:i:s');
        }
    }
}
