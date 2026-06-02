<?php
/**
 * AI Boost — Sitemap Plugin — entry point
 *
 * @package     AiBoost\Plugin\System\AiBoostSitemap
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

require_once JPATH_ADMINISTRATOR . '/components/com_aiboost/lib/autoload.php';
require_once __DIR__ . '/src/Extension/AiBoostSitemap.php';

// ucfirst('aiboost_sitemap') = 'Aiboost_sitemap' → PlgSystemAiboost_sitemap
if (!class_exists('PlgSystemAiboost_sitemap', false)) {
    class_alias(
        \AiBoost\Plugin\System\AiBoostSitemap\Extension\AiBoostSitemap::class,
        'PlgSystemAiboost_sitemap'
    );
}
