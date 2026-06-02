<?php
/**
 * AI Boost — Core Library Plugin
 *
 * Bootstraps the shared AiBoost\Lib namespace (ProGate, ConflictManager,
 * LicenseValidator) once per request. All other AI Boost plugins rely on
 * these classes/traits being available. Install this plugin FIRST with the
 * lowest ordering number so it runs before the other AI Boost plugins.
 *
 * @package     AiBoost\Plugin\System\AiBoostCore
 * @version     0.7.0
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

// Declare shared library classes/traits — each file is self-guarded with
// if (!trait_exists / !class_exists) so it is safe to require unconditionally.
(static function () {
    $dir = __DIR__ . '/lib/src/';
    foreach (['ProGate', 'ConflictManager', 'LicenseValidator'] as $cls) {
        $file = $dir . $cls . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
})();

// Minimal plugin class — no event handlers needed.
// This plugin exists solely to bootstrap the shared library.
if (!class_exists('PlgSystemAiboost_core', false)) {
    class PlgSystemAiboost_core extends \Joomla\CMS\Plugin\CMSPlugin {}
}
