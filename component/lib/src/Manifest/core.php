<?php
/**
 * AI Boost — Core (free) manifest.
 * Fields shared across core settings, technical SEO, and crawler policy.
 */

defined('_JEXEC') or die;

return [
    [
        'key'         => 'conflict_mode',
        'tab'         => 'general',
        'section'     => 'conflicts',
        'label'       => 'Conflict Resolution Mode',
        'type'        => 'select',
        'default'     => 'cooperative',
        'tier'        => 'free',
        'sku'         => 'core',
        'description' => 'How AI Boost behaves when another extension is already emitting the same meta tag, JSON-LD block, or analytics snippet.',
        'options'     => [
            'cooperative' => 'Cooperative — skip our tag when one exists (recommended)',
            'aggressive'  => 'Aggressive — always emit our tag (may produce duplicates)',
            'off'         => 'Off — disable conflict handling entirely',
        ],
    ],
    [
        'key'         => 'translation_source_priority',
        'tab'         => 'general',
        'section'     => 'multilingual',
        'label'       => 'Translation source priority',
        'type'        => 'select',
        'default'     => 'joomla_native',
        'tier'        => 'free',
        'sku'         => 'core',
        'description' => 'When a site mixes Joomla native multilingual with Falang or JoomFish, AI Boost uses this source as primary.',
        'options'     => [
            'joomla_native' => 'Joomla native (recommended if both exist)',
            'falang'        => 'Falang',
            'joomfish'      => 'JoomFish',
            'auto'          => 'Auto-detect best',
        ],
    ],

    // ── Integration master switches (Free) ──────────────────────────
    // Per-integration on/off, driven from the Integrations page (NOT a
    // settings tab). These keys deliberately carry NO 'integration' tag: they
    // must enter the save whitelist and stay UNLOCKED even when the bridge is
    // switched off — a switch can never lock itself — and a bridge being off
    // must not stop AI Boost from registering its fields (otherwise a plain
    // Settings save would drop the user's falang_*/yootheme_* values).
    [
        'key' => 'integration_falang_enabled', 'tab' => 'general', 'section' => 'integrations',
        'label' => 'Falang integration', 'type' => 'toggle', 'default' => '1',
        'tier' => 'free', 'sku' => 'core',
        'description' => 'When on, AI Boost adds Falang-aware hreflang, Schema.org and OpenGraph output for translated content. Turning it off stops that output but keeps all your Falang settings.',
    ],
    [
        'key' => 'integration_yootheme_enabled', 'tab' => 'general', 'section' => 'integrations',
        'label' => 'YOOtheme Pro integration', 'type' => 'toggle', 'default' => '1',
        'tier' => 'free', 'sku' => 'core',
        'description' => 'When on, AI Boost reads YOOtheme Pro page content for FAQ/gallery Schema.org and per-page meta. Turning it off stops that output but keeps all your YOOtheme settings.',
    ],

    // ── General settings (Free) ─────────────────────────────────────
    [
        'key' => 'auto_domain_detection', 'tab' => 'general', 'section' => 'domain',
        'label' => 'Auto-detect domain', 'type' => 'toggle', 'default' => '1',
        'tier' => 'free', 'sku' => 'core',
    ],
    [
        'key' => 'manual_domain', 'tab' => 'general', 'section' => 'domain',
        'label' => 'Manual Domain', 'type' => 'text', 'default' => '',
        'tier' => 'free', 'sku' => 'core',
    ],

    // ── Runtime SEO templates (Free) ───────────────────────────────
    [
        'key' => 'title_template', 'tab' => 'general', 'section' => 'seo_templates',
        'label' => 'Global Title Template', 'type' => 'text', 'default' => '',
        'tier' => 'free', 'sku' => 'core',
    ],
    [
        'key' => 'title_separator', 'tab' => 'general', 'section' => 'seo_templates',
        'label' => 'Title Separator', 'type' => 'text', 'default' => ' | ',
        'tier' => 'free', 'sku' => 'core',
    ],
    [
        'key' => 'title_template_home', 'tab' => 'general', 'section' => 'seo_templates',
        'label' => 'Homepage Title Template', 'type' => 'text', 'default' => '',
        'tier' => 'free', 'sku' => 'core',
    ],
    [
        'key' => 'title_template_article', 'tab' => 'general', 'section' => 'seo_templates',
        'label' => 'Article Title Template', 'type' => 'text', 'default' => '',
        'tier' => 'free', 'sku' => 'core',
    ],
    [
        'key' => 'title_template_category', 'tab' => 'general', 'section' => 'seo_templates',
        'label' => 'Category Title Template', 'type' => 'text', 'default' => '',
        'tier' => 'free', 'sku' => 'core',
    ],
    [
        'key' => 'title_template_search', 'tab' => 'general', 'section' => 'seo_templates',
        'label' => 'Search Title Template', 'type' => 'text', 'default' => '',
        'tier' => 'free', 'sku' => 'core',
    ],
    [
        'key' => 'title_template_tag', 'tab' => 'general', 'section' => 'seo_templates',
        'label' => 'Tag Title Template', 'type' => 'text', 'default' => '',
        'tier' => 'free', 'sku' => 'core',
    ],
    [
        'key' => 'title_template_default', 'tab' => 'general', 'section' => 'seo_templates',
        'label' => 'Default Title Template', 'type' => 'text', 'default' => '',
        'tier' => 'free', 'sku' => 'core',
    ],
    [
        'key' => 'title_template_maxlen', 'tab' => 'general', 'section' => 'seo_templates',
        'label' => 'Title Maximum Length', 'type' => 'number', 'default' => '0',
        'tier' => 'free', 'sku' => 'core',
    ],
    [
        'key' => 'meta_desc_template', 'tab' => 'general', 'section' => 'seo_templates',
        'label' => 'Global Meta Description Template', 'type' => 'textarea', 'default' => '',
        'tier' => 'free', 'sku' => 'core',
    ],
    [
        'key' => 'meta_desc_template_article', 'tab' => 'general', 'section' => 'seo_templates',
        'label' => 'Article Meta Description Template', 'type' => 'textarea', 'default' => '',
        'tier' => 'free', 'sku' => 'core',
    ],
    [
        'key' => 'meta_desc_template_category', 'tab' => 'general', 'section' => 'seo_templates',
        'label' => 'Category Meta Description Template', 'type' => 'textarea', 'default' => '',
        'tier' => 'free', 'sku' => 'core',
    ],
    [
        'key' => 'meta_desc_template_default', 'tab' => 'general', 'section' => 'seo_templates',
        'label' => 'Default Meta Description Template', 'type' => 'textarea', 'default' => '',
        'tier' => 'free', 'sku' => 'core',
    ],
    [
        'key' => 'meta_desc_maxlen', 'tab' => 'general', 'section' => 'seo_templates',
        'label' => 'Meta Description Maximum Length', 'type' => 'number', 'default' => '160',
        'tier' => 'free', 'sku' => 'core',
    ],

    // ── Crawler policy (Free) ────────────────────────────────────────
    [
        'key' => 'enable_robots', 'tab' => 'crawlers', 'section' => 'robots',
        'label' => 'Enable robots.txt management', 'type' => 'toggle', 'default' => '1',
        'tier' => 'free', 'sku' => 'core',
    ],
    [
        'key' => 'robots_auto_sync', 'tab' => 'crawlers', 'section' => 'robots',
        'label' => 'Auto-sync physical robots.txt file', 'type' => 'toggle', 'default' => '0',
        'tier' => 'free', 'sku' => 'core',
    ],

    // ── Sitemap (Free) ───────────────────────────────────────────────
    [
        'key' => 'enable_sitemap', 'tab' => 'sitemap', 'section' => 'xml',
        'label' => 'Enable XML Sitemap', 'type' => 'toggle', 'default' => '1',
        'tier' => 'free', 'sku' => 'core',
    ],
    [
        'key' => 'include_articles', 'tab' => 'sitemap', 'section' => 'xml_content',
        'label' => 'Include Articles', 'type' => 'toggle', 'default' => '1',
        'tier' => 'free', 'sku' => 'core',
    ],
    [
        'key' => 'include_categories', 'tab' => 'sitemap', 'section' => 'xml_content',
        'label' => 'Include Categories', 'type' => 'toggle', 'default' => '1',
        'tier' => 'free', 'sku' => 'core',
    ],
    [
        'key' => 'include_menu_items', 'tab' => 'sitemap', 'section' => 'xml_content',
        'label' => 'Include Menu Items', 'type' => 'toggle', 'default' => '1',
        'tier' => 'free', 'sku' => 'core',
    ],
    [
        'key' => 'sitemap_limit', 'tab' => 'sitemap', 'section' => 'xml',
        'label' => 'URL Limit', 'type' => 'number', 'default' => '1000',
        'tier' => 'free', 'sku' => 'core',
    ],
    [
        'key' => 'default_changefreq', 'tab' => 'sitemap', 'section' => 'xml',
        'label' => 'Default changefreq', 'type' => 'select', 'default' => 'weekly',
        'tier' => 'free', 'sku' => 'core',
        'options' => [
            'always' => 'Always',
            'hourly' => 'Hourly',
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
            'yearly' => 'Yearly',
            'never' => 'Never',
        ],
    ],
    [
        'key' => 'default_priority', 'tab' => 'sitemap', 'section' => 'xml',
        'label' => 'Default Priority', 'type' => 'number', 'default' => '0.8',
        'tier' => 'free', 'sku' => 'core',
    ],
    [
        'key' => 'exclude_category_ids', 'tab' => 'sitemap', 'section' => 'xml_exclusions',
        'label' => 'Exclude Article Category IDs', 'type' => 'text', 'default' => '',
        'tier' => 'free', 'sku' => 'core',
    ],
    [
        'key' => 'exclude_menu_ids', 'tab' => 'sitemap', 'section' => 'xml_exclusions',
        'label' => 'Exclude Menu Item IDs', 'type' => 'text', 'default' => '',
        'tier' => 'free', 'sku' => 'core',
    ],
    [
        'key' => 'ping_google', 'tab' => 'sitemap', 'section' => 'ping_legacy',
        'label' => 'Ping Google on sitemap request', 'type' => 'toggle', 'default' => '1',
        'tier' => 'free', 'sku' => 'core',
    ],
    [
        'key' => 'ping_bing', 'tab' => 'sitemap', 'section' => 'ping_legacy',
        'label' => 'Ping Bing on sitemap request', 'type' => 'toggle', 'default' => '1',
        'tier' => 'free', 'sku' => 'core',
    ],
    // ── Technical SEO (Free) ─────────────────────────────────────────
    [
        'key' => 'redirect_404_log_enabled', 'tab' => 'technical', 'section' => 'redirects',
        'label' => 'Log 404 Errors', 'type' => 'toggle', 'default' => '1',
        'tier' => 'free', 'sku' => 'core',
    ],
    [
        'key' => 'enable_canonical', 'tab' => 'technical', 'section' => 'canonical',
        'label' => 'Enable canonical URL management', 'type' => 'toggle', 'default' => '1',
        'tier' => 'free', 'sku' => 'core',
    ],
    [
        'key' => 'canonical_url_map', 'tab' => 'technical', 'section' => 'canonical',
        'label' => 'Canonical URL Map', 'type' => 'textarea', 'default' => '',
        'tier' => 'free', 'sku' => 'core',
    ],
    // ── Task #511 — Central error logging ─────────────────────────────
    [
        'key'         => 'error_log_enabled',
        'tab'         => 'debug',
        'section'     => 'logging',
        'label'       => 'Enable AI Boost error log',
        'type'        => 'toggle',
        'default'     => '1',
        'tier'        => 'free',
        'sku'         => 'core',
        'description' => 'Write AI Boost warnings/errors to the #__aiboost_error_log table (and Joomla log) so they can be reviewed from the admin instead of being lost in PHP error_log.',
    ],
    [
        'key'         => 'error_log_min_severity',
        'tab'         => 'debug',
        'section'     => 'logging',
        'label'       => 'Minimum severity to log',
        'type'        => 'select',
        'default'     => 'warning',
        'tier'        => 'free',
        'sku'         => 'core',
        'description' => 'Events below this severity are dropped. Use "debug" temporarily when troubleshooting; "warning" is safe for production.',
        'options'     => [
            'debug'   => 'Debug (very verbose — troubleshooting only)',
            'info'    => 'Info',
            'warning' => 'Warning (recommended)',
            'error'   => 'Error only',
        ],
    ],

    // ── Debug tab (Pro section) ─────────────────────────────────────
    [
        'key' => 'debug_mode', 'tab' => 'debug', 'section' => 'diagnostics',
        'label' => 'Enable debug mode', 'type' => 'toggle', 'default' => '0',
        'tier' => 'pro', 'sku' => 'core',
    ],
    [
        'key' => 'hide_comments', 'tab' => 'debug', 'section' => 'diagnostics',
        'label' => 'Hide comments in HTML source', 'type' => 'toggle', 'default' => '0',
        'tier' => 'pro', 'sku' => 'core',
    ],
    [
        'key' => 'staging_mode', 'tab' => 'debug', 'section' => 'diagnostics',
        'label' => 'Staging mode', 'type' => 'toggle', 'default' => '0',
        'tier' => 'pro', 'sku' => 'core',
    ],
];
