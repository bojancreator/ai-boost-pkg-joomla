<?php
/**
 * @package     AiBoost\Plugin\System\AiBoostAeo
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostAeo\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

/**
 * Shared helper utilities for the AI Boost AEO plugin.
 */
final class PluginHelper
{
    public static function siteBaseUrl(): string
    {
        $uri = Uri::getInstance();
        return $uri->getScheme() . '://' . $uri->getHost();
    }

    public static function siteName(): string
    {
        try {
            return (string) Factory::getApplication()->get('sitename', '');
        } catch (\Throwable) {
            return '';
        }
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
