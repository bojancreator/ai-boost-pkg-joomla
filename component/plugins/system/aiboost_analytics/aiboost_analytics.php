<?php
/**
 * AI Boost — Analytics Plugin — legacy entry point
 *
 * @package     AiBoost\Plugin\System\AiBoostAnalytics
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

require_once JPATH_ADMINISTRATOR . '/components/com_aiboost/lib/autoload.php';
require_once __DIR__ . '/src/Extension/AiBoostAnalytics.php';

// ucfirst('aiboost_analytics') = 'Aiboost_analytics' → PlgSystemAiboost_analytics
if (!class_exists('PlgSystemAiboost_analytics', false)) {
    class_alias(
        \AiBoost\Plugin\System\AiBoostAnalytics\Extension\AiBoostAnalytics::class,
        'PlgSystemAiboost_analytics'
    );
}
