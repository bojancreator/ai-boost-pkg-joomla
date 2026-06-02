<?php
/**
 * AI Boost — Code Manager Pro entry point.
 *
 * Closed-source upgrade plugin for the 'code' SKU. Skeleton only —
 * physical extraction of the Pro logic from the free plugin is staged
 * as a follow-up to Task #429.
 *
 * @package     AiBoost\Plugin\System\AiBoostCodePro
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

require_once __DIR__ . '/src/Extension/AiBoostCodePro.php';

if (!class_exists('PlgSystemAiboost_code_pro', false)) {
    class_alias(
        \AiBoost\Plugin\System\AiBoostCodePro\Extension\AiBoostCodePro::class,
        'PlgSystemAiboost_code_pro'
    );
}
