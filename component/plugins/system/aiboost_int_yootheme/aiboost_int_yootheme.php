<?php
/**
 * AI Boost — YOOtheme Pro Integration (entry point).
 *
 * @package     AiBoost\Plugin\System\AiBoostIntYootheme
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

// Bail out gracefully when com_aiboost is absent (uninstalled separately,
// failed update, partial deploy) — the Extension below extends a lib class
// (AbstractIntegrationPlugin), so loading it without the lib would fatal and
// take down both the site and the administrator. Without the lib the plugin
// simply no-ops.
$loader = JPATH_ADMINISTRATOR . '/components/com_aiboost/lib/autoload.php';

if (!file_exists($loader)) {
    return;
}

require_once $loader;

// Partial-lib guard: autoload.php can survive on disk while individual
// lib/src class files were already removed (interrupted or partial base
// uninstall). The Extension below extends AbstractIntegrationPlugin, so
// requiring it while the parent is unloadable fatals site-wide. The
// try/catch matters: under JDEBUG Joomla's debug class loader THROWS on a
// missing class file instead of returning false.
try {
    if (!class_exists('AiBoost\\Lib\\Integration\\AbstractIntegrationPlugin')) {
        return;
    }
} catch (\Throwable $e) {
    return;
}

require_once __DIR__ . '/src/Extension/AiBoostIntYootheme.php';

if (!class_exists('PlgSystemAiboost_int_yootheme', false)) {
    class_alias(
        \AiBoost\Plugin\System\AiBoostIntYootheme\Extension\AiBoostIntYootheme::class,
        'PlgSystemAiboost_int_yootheme'
    );
}
