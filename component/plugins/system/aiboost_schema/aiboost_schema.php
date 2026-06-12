<?php
/**
 * AI Boost — Schema.org Plugin — entry point
 *
 * @package     AiBoost\Plugin\System\AiBoostSchema
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

// Bail out gracefully when com_aiboost is absent (uninstalled separately,
// failed update, partial deploy) — a fatal here would take down both the
// site and the administrator. Without the lib the plugin simply no-ops.
$loader = JPATH_ADMINISTRATOR . '/components/com_aiboost/lib/autoload.php';

if (!file_exists($loader)) {
    return;
}

require_once $loader;
require_once __DIR__ . '/src/Extension/AiBoostSchema.php';

// Joomla 3/4/5/6 legacy loader looks for: PlgSystem + ucfirst(element)
// ucfirst('aiboost_schema') = 'Aiboost_schema'  → PlgSystemAiboost_schema
if (!class_exists('PlgSystemAiboost_schema', false)) {
    class_alias(
        \AiBoost\Plugin\System\AiBoostSchema\Extension\AiBoostSchema::class,
        'PlgSystemAiboost_schema'
    );
}
