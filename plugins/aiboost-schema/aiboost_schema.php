<?php
/**
 * AI Boost — Schema.org Rich Snippets Plugin — entry point
 *
 * @package     AiBoost\Plugin\System\AiBoostSchema
 * @version     1.0.0
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

require_once __DIR__ . '/src/Extension/AiBoostSchema.php';

if (!class_exists('PlgSystemAiboost_schema', false)) {
    class_alias(
        \AiBoost\Plugin\System\AiBoostSchema\Extension\AiBoostSchema::class,
        'PlgSystemAiboost_schema'
    );
}
