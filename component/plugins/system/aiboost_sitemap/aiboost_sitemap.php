<?php
/**
 * AI Boost — Sitemap Plugin — entry point
 *
 * @package     AiBoost\Plugin\System\AiBoostSitemap
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

// Bail out gracefully when com_aiboost is absent (uninstalled separately,
// failed update, partial deploy) — a fatal here would take down both the
// site and the administrator. Without the lib the plugin simply no-ops.
$loader = JPATH_ADMINISTRATOR . '/components/com_aiboost/lib/autoload.php';

if (!file_exists($loader)) {
    return;
}

require_once $loader;
require_once __DIR__ . '/src/Extension/AiBoostSitemap.php';

// ucfirst('aiboost_sitemap') = 'Aiboost_sitemap' → PlgSystemAiboost_sitemap
if (!class_exists('PlgSystemAiboost_sitemap', false)) {
    class_alias(
        \AiBoost\Plugin\System\AiBoostSitemap\Extension\AiBoostSitemap::class,
        'PlgSystemAiboost_sitemap'
    );
}
