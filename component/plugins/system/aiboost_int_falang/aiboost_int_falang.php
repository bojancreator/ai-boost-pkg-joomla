<?php
/**
 * AI Boost — Falang Integration (entry point).
 *
 * @package     AiBoost\Plugin\System\AiBoostIntFalang
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

require_once __DIR__ . '/src/Extension/AiBoostIntFalang.php';

if (!class_exists('PlgSystemAiboost_int_falang', false)) {
    class_alias(
        \AiBoost\Plugin\System\AiBoostIntFalang\Extension\AiBoostIntFalang::class,
        'PlgSystemAiboost_int_falang'
    );
}
