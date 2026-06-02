<?php
/**
 * AI Boost — Core Library Plugin — Installer Script
 *
 * Automatically sets ordering = 1 after install/update so that aiboost_core
 * always bootstraps the shared library before any other AI Boost plugin.
 *
 * @package     AiBoost\Plugin\System\AiBoostCore
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class PlgSystemAiboost_coreInstallerScript
{
    /**
     * Runs after every install or update.
     * Sets the plugin ordering to 1 so it loads before all other AI Boost plugins.
     */
    public function postflight(string $type, object $parent): void
    {
        if (!in_array($type, ['install', 'update'], true)) {
            return;
        }

        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('ordering') . ' = 1')
                ->where($db->quoteName('element') . ' = ' . $db->quote('aiboost_core'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('system'));
            $db->setQuery($query);
            $db->execute();
        } catch (\Throwable $e) {
            // Non-fatal — ordering can be set manually if DB call fails
        }
    }
}
