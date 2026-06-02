<?php
/**
 * AI Boost — AEO Pro entry point.
 *
 * Closed-source upgrade plugin for the 'aeo' SKU. Skeleton only —
 * physical extraction of the Pro logic from the free plugin is staged
 * as a follow-up to Task #429.
 *
 * @package     AiBoost\Plugin\System\AiBoostAeoPro
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

require_once __DIR__ . '/src/Extension/AiBoostAeoPro.php';

if (!class_exists('PlgSystemAiboost_aeo_pro', false)) {
    class_alias(
        \AiBoost\Plugin\System\AiBoostAeoPro\Extension\AiBoostAeoPro::class,
        'PlgSystemAiboost_aeo_pro'
    );
}
