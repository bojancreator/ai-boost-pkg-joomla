<?php
/**
 * AI Boost — Falang Pro Bridge Plugin — legacy entry point
 *
 * @package     AiBoost\Plugin\System\AiBoostFalang
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

require_once __DIR__ . '/src/Extension/AiBoostFalang.php';

if (!class_exists('PlgSystemAiboost_falang', false)) {
    class_alias(
        \AiBoost\Plugin\System\AiBoostFalang\Extension\AiBoostFalang::class,
        'PlgSystemAiboost_falang'
    );
}
