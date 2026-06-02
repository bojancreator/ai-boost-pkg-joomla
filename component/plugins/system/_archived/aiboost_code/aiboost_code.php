<?php
/**
 * AI Boost — Custom Code Plugin — legacy entry point
 *
 * @package     AiBoost\Plugin\System\AiBoostCode
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

// Load shared AiBoost library before requiring the extension class.
(static function () {
    // Bundled lib — always present inside this plugin after install
    $dir = __DIR__ . '/lib/src/';
    foreach (['ProGate', 'ConflictManager', 'LicenseValidator'] as $cls) {
        if (!class_exists("AiBoost\Lib\{$cls}", false) && !trait_exists("AiBoost\Lib\{$cls}", false) && file_exists($dir . $cls . '.php')) {
            require_once $dir . $cls . '.php';
        }
    }
})();

require_once __DIR__ . '/src/Extension/AiBoostCode.php';

if (!class_exists('PlgSystemAiboost_code', false)) {
    class_alias(
        \AiBoost\Plugin\System\AiBoostCode\Extension\AiBoostCode::class,
        'PlgSystemAiboost_code'
    );
}
