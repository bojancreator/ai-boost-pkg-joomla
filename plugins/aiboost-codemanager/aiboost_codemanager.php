<?php
/**
 * AI Boost — Code Manager Plugin — entry point
 *
 * @package     AiBoost\Plugin\System\AiBoostCodemanager
 * @version     1.0.0
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

require_once __DIR__ . '/src/Extension/AiBoostCodemanager.php';

if (!class_exists('PlgSystemAiboost_codemanager', false)) {
    class_alias(
        \AiBoost\Plugin\System\AiBoostCodemanager\Extension\AiBoostCodemanager::class,
        'PlgSystemAiboost_codemanager'
    );
}
