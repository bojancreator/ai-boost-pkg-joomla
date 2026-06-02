<?php
/**
 * AI Boost — PSR-4 Autoloader
 * Loaded by services/provider.php and all aiboost_* system plugins.
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'AiBoost\\')) {
        return;
    }

    $base = JPATH_ADMINISTRATOR . '/components/com_aiboost';

    if (str_starts_with($class, 'AiBoost\\Component\\AiBoost\\')) {
        $relative = str_replace('\\', '/', substr($class, strlen('AiBoost\\Component\\AiBoost\\')));
        $path     = $base . '/src/' . $relative . '.php';
    } elseif (str_starts_with($class, 'AiBoost\\Lib\\')) {
        $relative = str_replace('\\', '/', substr($class, strlen('AiBoost\\Lib\\')));
        $path     = $base . '/lib/src/' . $relative . '.php';
    } elseif ($class === 'AiBoost\\Version') {
        $path = $base . '/Version.php';
    } else {
        return;
    }

    if (file_exists($path)) {
        require_once $path;
    }
});
