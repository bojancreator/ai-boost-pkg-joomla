<?php
/**
 * AI Boost — Hreflang & Multilingual SEO Plugin — entry point
 *
 * @package     AiBoost\Plugin\System\AiBoostHreflang
 * @version     1.0.0
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

require_once __DIR__ . '/src/Extension/AiBoostHreflang.php';

if (!class_exists('PlgSystemAiboost_hreflang', false)) {
    class_alias(
        \AiBoost\Plugin\System\AiBoostHreflang\Extension\AiBoostHreflang::class,
        'PlgSystemAiboost_hreflang'
    );
}
