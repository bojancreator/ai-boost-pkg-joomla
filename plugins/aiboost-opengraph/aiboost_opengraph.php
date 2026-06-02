<?php
/**
 * AI Boost — OpenGraph Plugin — entry point
 *
 * @package     AiBoost\Plugin\System\AiBoostOpengraph
 * @version     1.0.0
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

require_once __DIR__ . '/src/Extension/AiBoostOpengraph.php';

if (!class_exists('PlgSystemAiboost_opengraph', false)) {
    class_alias(
        \AiBoost\Plugin\System\AiBoostOpengraph\Extension\AiBoostOpengraph::class,
        'PlgSystemAiboost_opengraph'
    );
}
