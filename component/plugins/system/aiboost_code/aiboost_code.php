<?php
/**
 * AI Boost — Custom Code Plugin — legacy entry point.
 *
 * Bridges Joomla's legacy class-based plugin loader with the namespaced
 * Extension class. Joomla instantiates plugins by class name derived
 * from element/folder; this alias satisfies that lookup.
 *
 * @package     AiBoost\Plugin\System\AiBoostCode
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

require_once JPATH_ADMINISTRATOR . '/components/com_aiboost/lib/autoload.php';
require_once __DIR__ . '/src/Extension/AiBoostCode.php';

if (!class_exists('PlgSystemAiboost_code', false)) {
    class_alias(
        \AiBoost\Plugin\System\AiBoostCode\Extension\AiBoostCode::class,
        'PlgSystemAiboost_code'
    );
}
