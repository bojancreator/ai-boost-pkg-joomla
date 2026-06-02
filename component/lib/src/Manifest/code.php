<?php
/**
 * AI Boost — Custom Code Injection manifest.
 *
 * Whole tab is Pro (Task #473) — every field marked tier:pro and the
 * CodeTab.vue itself is wrapped in <ProGate gate-key="section:code">.
 */

defined('_JEXEC') or die;

return [
    [
        'key' => 'enable_custom_code', 'tab' => 'code', 'section' => 'general',
        'label' => 'Enable Custom Code Injection', 'type' => 'toggle', 'default' => '0',
        'tier' => 'pro', 'sku' => 'code',
    ],
    [
        'key' => 'custom_code_head', 'tab' => 'code', 'section' => 'head',
        'label' => 'Inject before </head>', 'type' => 'textarea', 'default' => '',
        'tier' => 'pro', 'sku' => 'code',
    ],
    [
        'key' => 'custom_code_body', 'tab' => 'code', 'section' => 'body',
        'label' => 'Inject after opening <body>', 'type' => 'textarea', 'default' => '',
        'tier' => 'pro', 'sku' => 'code',
    ],
    [
        'key' => 'custom_code_footer', 'tab' => 'code', 'section' => 'footer',
        'label' => 'Inject before </body>', 'type' => 'textarea', 'default' => '',
        'tier' => 'pro', 'sku' => 'code',
    ],
    [
        'key' => 'custom_code_head_scope', 'tab' => 'code', 'section' => 'head',
        'label' => 'Head code scope', 'type' => 'select', 'default' => 'all',
        'tier' => 'pro', 'sku' => 'code',
        'options' => [ 'all' => 'All pages', 'specific' => 'Specific menu items only' ],
    ],
];
