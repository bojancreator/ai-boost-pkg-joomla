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
        'key' => 'default_og_image_alt', 'tab' => 'social', 'section' => 'og',
        'label' => 'Default OG Image Alt Text', 'type' => 'text', 'default' => '',
        'tier' => 'free', 'sku' => 'og',
    ],
    [
        'key' => 'og_description_override', 'tab' => 'social', 'section' => 'og',
        'label' => 'Default OG Description Override', 'type' => 'textarea', 'default' => '',
        'tier' => 'free', 'sku' => 'og',
    ],
    [
        'key' => 'og_image_width', 'tab' => 'social', 'section' => 'og',
        'label' => 'Default OG Image Width', 'type' => 'number', 'default' => '1200',
        'tier' => 'free', 'sku' => 'og',
    ],
    [
        'key' => 'og_image_height', 'tab' => 'social', 'section' => 'og',
        'label' => 'Default OG Image Height', 'type' => 'number', 'default' => '630',
        'tier' => 'free', 'sku' => 'og',
    ],
    [
        'key' => 'enable_per_article_fields', 'tab' => 'social', 'section' => 'og',
        'label' => 'Use per-article OG image and description', 'type' => 'toggle', 'default' => '1',
        'tier' => 'pro', 'sku' => 'og',
    ],
    [
        'key' => 'enable_article_og_type', 'tab' => 'social', 'section' => 'og',
        'label' => 'Set og:type = article on article pages', 'type' => 'toggle', 'default' => '1',
        'tier' => 'pro', 'sku' => 'og',
    ],
    [
        'key' => 'enable_og_locale', 'tab' => 'social', 'section' => 'og_locale',
        'label' => 'Add og:locale tag', 'type' => 'toggle', 'default' => '0',
        'tier' => 'pro', 'sku' => 'og',
    ],
    [
        'key' => 'fb_app_id', 'tab' => 'social', 'section' => 'facebook',
        'label' => 'Facebook App ID', 'type' => 'text', 'default' => '',
        'tier' => 'pro', 'sku' => 'og',
    ],
    [
        'key' => 'enable_twitter_cards', 'tab' => 'social', 'section' => 'twitter',
        'label' => 'Enable Twitter Card meta tags', 'type' => 'toggle', 'default' => '1',
        'tier' => 'free', 'sku' => 'og',
    ],
    [
        'key' => 'twitter_site_handle', 'tab' => 'social', 'section' => 'twitter',
        'label' => 'Twitter / X Site Handle', 'type' => 'text', 'default' => '',
        'tier' => 'pro', 'sku' => 'og',
    ],
    [
        'key' => 'enable_meta_pixel', 'tab' => 'analytics', 'section' => 'pixel',
        'label' => 'Enable Meta (Facebook) Pixel', 'type' => 'toggle', 'default' => '0',
        'tier' => 'pro', 'sku' => 'og',
    ],
    [
        'key' => 'meta_pixel_id', 'tab' => 'analytics', 'section' => 'pixel',
        'label' => 'Primary Meta Pixel ID', 'type' => 'text', 'default' => '',
        'tier' => 'pro', 'sku' => 'og',
    ],
    [
        'key' => 'meta_pixel_ids', 'tab' => 'analytics', 'section' => 'pixel',
        'label' => 'Meta Pixel IDs', 'type' => 'json', 'default' => '[""]',
        'tier' => 'pro', 'sku' => 'og',
    ],
    [
        'key' => 'pixel_consent_mode', 'tab' => 'analytics', 'section' => 'pixel',
        'label' => 'GDPR Consent Mode', 'type' => 'select', 'default' => 'none',
        'tier' => 'pro', 'sku' => 'og',
        'options' => ['none' => 'None', 'consent_required' => 'Consent required (revoke until granted)'],
    ],
    [
        'key' => 'meta_pixel_standard_events', 'tab' => 'analytics', 'section' => 'pixel_events',
        'label' => 'Meta Pixel Standard Events', 'type' => 'json', 'default' => '{}',
        'tier' => 'pro', 'sku' => 'og',
    ],
    [
        'key' => 'meta_custom_events', 'tab' => 'analytics', 'section' => 'pixel_custom',
        'label' => 'Meta Pixel Custom Events', 'type' => 'json', 'default' => '[]',
        'tier' => 'pro', 'sku' => 'og',
    ],
];
