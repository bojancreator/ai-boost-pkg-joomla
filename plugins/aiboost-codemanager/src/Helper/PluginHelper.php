<?php
/**
 * @package     AiBoost\Plugin\System\AiBoostCodemanager
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostCodemanager\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;

/**
 * Shared helper utilities for the AI Boost Code Manager plugin.
 */
final class PluginHelper
{
    public static function siteBaseUrl(): string
    {
        $uri = Uri::getInstance();
        return $uri->getScheme() . '://' . $uri->getHost();
    }

    public static function isStagingMode(\Joomla\Registry\Registry $params): bool
    {
        return (bool) $params->get('staging_mode', 0);
    }

    public static function isEnabled(\Joomla\Registry\Registry $params, string $key = 'enabled'): bool
    {
        return (bool) $params->get($key, 1);
    }
}
