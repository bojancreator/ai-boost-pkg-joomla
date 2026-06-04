<?php
/**
 * AI Boost — Hreflang manifest.
 * The hreflang Pro SKU is independent from Schema/AEO/OG bundles per the
 * pricing model (€15/year standalone) and toggles per-language alternate
 * link generation in <head> and the XML sitemap.
 */

defined('_JEXEC') or die;

return [
    [
        'key' => 'hreflang_enabled', 'tab' => 'social', 'section' => 'hreflang',
        'label' => 'Emit hreflang <link> alternates', 'type' => 'toggle', 'default' => '0',
        'tier' => 'pro', 'sku' => 'hreflang',
        'description' => 'Outputs <link rel="alternate" hreflang> tags for every language on the site.',
    ],
    [
        'key' => 'hreflang_sitemap', 'tab' => 'sitemap', 'section' => 'hreflang',
        'label' => 'Emit <xhtml:link> hreflang alternates in sitemap', 'type' => 'toggle', 'default' => '0',
        'tier' => 'pro', 'sku' => 'hreflang',
    ],
    [
        'key' => 'enable_hreflang', 'tab' => 'sitemap', 'section' => 'hreflang',
        'label' => 'Add hreflang to sitemap', 'type' => 'toggle', 'default' => '0',
        'tier' => 'pro', 'sku' => 'hreflang',
    ],
    [
        'key' => 'hreflang_primary_language', 'tab' => 'social', 'section' => 'hreflang',
        'label' => 'Primary language SEF', 'type' => 'text', 'default' => 'en',
        'tier' => 'pro', 'sku' => 'hreflang',
    ],
];
