<?php
/**
 * AI Boost — Core Plugin — legacy entry point
 *
 * @package     AiBoost\Plugin\System\AiBoostCore
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

require_once JPATH_ADMINISTRATOR . '/components/com_aiboost/lib/autoload.php';
require_once __DIR__ . '/src/Extension/AiBoostCore.php';

// ucfirst('aiboost_core') = 'Aiboost_core' → PlgSystemAiboost_core
if (!class_exists('PlgSystemAiboost_core', false)) {
    class_alias(
        \AiBoost\Plugin\System\AiBoostCore\Extension\AiBoostCore::class,
        'PlgSystemAiboost_core'
    );
}
