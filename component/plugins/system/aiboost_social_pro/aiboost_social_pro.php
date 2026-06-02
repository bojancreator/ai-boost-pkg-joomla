<?php
/**
 * AI Boost — OpenGraph Pro entry point.
 *
 * Closed-source upgrade plugin for the 'og' SKU. Skeleton only —
 * physical extraction of the Pro logic from the free plugin is staged
 * as a follow-up to Task #429.
 *
 * @package     AiBoost\Plugin\System\AiBoostSocialPro
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

require_once __DIR__ . '/src/Extension/AiBoostSocialPro.php';

if (!class_exists('PlgSystemAiboost_social_pro', false)) {
    class_alias(
        \AiBoost\Plugin\System\AiBoostSocialPro\Extension\AiBoostSocialPro::class,
        'PlgSystemAiboost_social_pro'
    );
}
