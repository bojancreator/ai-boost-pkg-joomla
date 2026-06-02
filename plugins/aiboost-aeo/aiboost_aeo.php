<?php
/**
 * AI Boost — AEO & AI Signals Plugin — entry point
 *
 * @package     AiBoost\Plugin\System\AiBoostAeo
 * @version     1.0.0
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

require_once __DIR__ . '/src/Extension/AiBoostAeo.php';

if (!class_exists('PlgSystemAiboost_aeo', false)) {
    class_alias(
        \AiBoost\Plugin\System\AiBoostAeo\Extension\AiBoostAeo::class,
        'PlgSystemAiboost_aeo'
    );
}
