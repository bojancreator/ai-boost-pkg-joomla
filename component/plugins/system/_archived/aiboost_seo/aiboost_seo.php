<?php
/**
 * AI Boost — SEO Plugin — legacy entry point
 *
 * @package     AiBoost\Plugin\System\AiBoostSeo
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

// Load shared AiBoost library before requiring the extension class.
// This ensures ProGate, ConflictManager, and LicenseValidator traits/classes
// are available when PHP resolves the 'use' declarations in the extension class.
(static function () {
    // Bundled lib — always present inside this plugin after install
    $dir = __DIR__ . '/lib/src/';
    foreach (['ProGate', 'ConflictManager', 'LicenseValidator'] as $cls) {
        if (!class_exists("AiBoost\Lib\{$cls}", false) && !trait_exists("AiBoost\Lib\{$cls}", false) && file_exists($dir . $cls . '.php')) {
            require_once $dir . $cls . '.php';
        }
    }
})();

require_once __DIR__ . '/src/Extension/AiBoostSeo.php';

// Joomla legacy loader looks for: PlgSystem + ucfirst(element)
// ucfirst('aiboost_seo') = 'Aiboost_seo' → PlgSystemAiboost_seo
if (!class_exists('PlgSystemAiboost_seo', false)) {
    class_alias(
        \AiBoost\Plugin\System\AiBoostSeo\Extension\AiBoostSeo::class,
        'PlgSystemAiboost_seo'
    );
}
