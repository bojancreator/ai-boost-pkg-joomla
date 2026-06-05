<?php
/**
 * AI Boost — Custom Code Injection manifest.
 *
 * Historical tier metadata is retained for compatibility while the
 * one-product admin keeps the tab editable.
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
    [
        'key' => 'custom_code_head_menu_ids', 'tab' => 'code', 'section' => 'head',
        'label' => 'Head code menu item IDs', 'type' => 'json', 'default' => '[]',
        'tier' => 'pro', 'sku' => 'code',
    ],
    [
        'key' => 'custom_code_body_scope', 'tab' => 'code', 'section' => 'body',
        'label' => 'Body code scope', 'type' => 'select', 'default' => 'all',
        'tier' => 'pro', 'sku' => 'code',
        'options' => [ 'all' => 'All pages', 'specific' => 'Specific menu items only' ],
    ],
    [
        'key' => 'custom_code_body_menu_ids', 'tab' => 'code', 'section' => 'body',
        'label' => 'Body code menu item IDs', 'type' => 'json', 'default' => '[]',
        'tier' => 'pro', 'sku' => 'code',
    ],
    [
        'key' => 'custom_code_footer_scope', 'tab' => 'code', 'section' => 'footer',
        'label' => 'Footer code scope', 'type' => 'select', 'default' => 'all',
        'tier' => 'pro', 'sku' => 'code',
        'options' => [ 'all' => 'All pages', 'specific' => 'Specific menu items only' ],
    ],
    [
        'key' => 'custom_code_footer_menu_ids', 'tab' => 'code', 'section' => 'footer',
        'label' => 'Footer code menu item IDs', 'type' => 'json', 'default' => '[]',
        'tier' => 'pro', 'sku' => 'code',
    ],
];
