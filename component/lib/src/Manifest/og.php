<?php
/**
 * AI Boost — OpenGraph manifest.
 */

defined('_JEXEC') or die;

return [
    [
        'key' => 'enable_opengraph', 'tab' => 'social', 'section' => 'og',
        'label' => 'Enable OpenGraph tags', 'type' => 'toggle', 'default' => '1',
        'tier' => 'free', 'sku' => 'og',
    ],
    [
        'key' => 'site_name', 'tab' => 'social', 'section' => 'og',
        'label' => 'OG Site Name', 'type' => 'text', 'default' => '',
        'tier' => 'free', 'sku' => 'og',
    ],
    [
        'key' => 'default_og_image', 'tab' => 'social', 'section' => 'og',
        'label' => 'Default OG Image', 'type' => 'media', 'default' => '',
        'tier' => 'free', 'sku' => 'og',
    ],
    [
        'key' => 'og_description_override', 'tab' => 'social', 'section' => 'og',
        'label' => 'Default OG Description Override', 'type' => 'textarea', 'default' => '',
        'tier' => 'free', 'sku' => 'og',
    ],
    [
        'key' => 'enable_og_locale', 'tab' => 'social', 'section' => 'og_locale',
        'label' => 'Add og:locale tag', 'type' => 'toggle', 'default' => '0',
        'tier' => 'pro', 'sku' => 'og',
    ],
    [
        'key' => 'enable_twitter_cards', 'tab' => 'social', 'section' => 'twitter',
        'label' => 'Enable Twitter Card meta tags', 'type' => 'toggle', 'default' => '1',
        'tier' => 'free', 'sku' => 'og',
    ],
    [
        'key' => 'enable_meta_pixel', 'tab' => 'social', 'section' => 'pixel',
        // Task #473 — Meta Pixel is a Pro feature now (re-tier).
        'label' => 'Enable Meta (Facebook) Pixel', 'type' => 'toggle', 'default' => '0',
        'tier' => 'pro', 'sku' => 'og',
    ],
    [
        'key' => 'meta_pixel_standard_events', 'tab' => 'social', 'section' => 'pixel_events',
        'label' => 'Meta Pixel Standard Events', 'type' => 'json', 'default' => '{}',
        'tier' => 'pro', 'sku' => 'og',
    ],
    [
        'key' => 'meta_custom_events', 'tab' => 'social', 'section' => 'pixel_custom',
        'label' => 'Meta Pixel Custom Events', 'type' => 'json', 'default' => '[]',
        'tier' => 'pro', 'sku' => 'og',
    ],
];
