<?php
/**
 * AI Boost — AEO Plugin — entry point
 *
 * @package     AiBoost\Plugin\System\AiBoostAeo
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

require_once JPATH_ADMINISTRATOR . '/components/com_aiboost/lib/autoload.php';
require_once __DIR__ . '/src/Extension/AiBoostAeo.php';

// ucfirst('aiboost_aeo') = 'Aiboost_aeo' → PlgSystemAiboost_aeo
if (!class_exists('PlgSystemAiboost_aeo', false)) {
    class_alias(
        \AiBoost\Plugin\System\AiBoostAeo\Extension\AiBoostAeo::class,
        'PlgSystemAiboost_aeo'
    );
}
