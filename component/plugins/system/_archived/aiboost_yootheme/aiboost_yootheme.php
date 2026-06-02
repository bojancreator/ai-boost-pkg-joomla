<?php
/**
 * AI Boost — YooTheme Pro Bridge Plugin — legacy entry point
 *
 * @package     AiBoost\Plugin\System\AiBoostYootheme
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

require_once __DIR__ . '/src/Extension/AiBoostYootheme.php';

if (!class_exists('PlgSystemAiboost_yootheme', false)) {
    class_alias(
        \AiBoost\Plugin\System\AiBoostYootheme\Extension\AiBoostYootheme::class,
        'PlgSystemAiboost_yootheme'
    );
}
