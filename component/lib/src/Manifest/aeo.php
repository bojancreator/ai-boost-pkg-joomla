<?php
/**
 * AI Boost — AEO manifest (llms.txt, robots.txt, AI crawler rules, IndexNow,
 * Markdown pages, AI signals).
 */

defined('_JEXEC') or die;

return [
    // ── llms.txt (free) ────────────────────────────────────────────
    [
        'key'         => 'llmstxt_enabled',
        'tab'         => 'aeo',
        'section'     => 'llmstxt',
        'label'       => 'Enable /llms.txt',
        'type'        => 'toggle',
        'default'     => '1',
        'tier'        => 'free',
        'sku'         => 'aeo',
    ],
    [
        'key'         => 'llmstxt_description',
        'tab'         => 'aeo',
        'section'     => 'llmstxt',
        'label'       => 'Site Description for AI',
        'type'        => 'textarea',
        'default'     => '',
        'tier'        => 'free',
        'sku'         => 'aeo',
    ],
    [
        'key'         => 'llmstxt_recent_articles',
        'tab'         => 'aeo',
        'section'     => 'llmstxt',
        'label'       => 'Recent Articles',
        'type'        => 'number',
        'default'     => '5',
        'tier'        => 'free',
        'sku'         => 'aeo',
    ],
    [
        'key'         => 'llmstxt_custom_pages',
        'tab'         => 'aeo',
        'section'     => 'llmstxt',
        'label'       => 'Custom Pages',
        'type'        => 'json',
        'default'     => '[]',
        'tier'        => 'free',
        'sku'         => 'aeo',
    ],
    [
        'key'         => 'llmstxt_faq_auto_detect',
        'tab'         => 'aeo',
        'section'     => 'llmstxt',
        'label'       => 'Auto-Detect FAQ from Articles',
        'type'        => 'toggle',
        'default'     => '0',
        'tier'        => 'free',
        'sku'         => 'aeo',
    ],
    [
        'key'         => 'llmstxt_faq_items',
        'tab'         => 'aeo',
        'section'     => 'llmstxt',
        'label'       => 'Manual FAQ Items',
        'type'        => 'json',
        'default'     => '[]',
        'tier'        => 'free',
        'sku'         => 'aeo',
    ],

    // ── llms-full.txt ──────────────────────────────────────────────
    [
        'key'         => 'llms_full_txt_enabled',
        'tab'         => 'aeo',
        'section'     => 'llms_full',
        'label'       => 'Enable /llms-full.txt',
        'type'        => 'toggle',
        'default'     => '0',
        'tier'        => 'pro',
        'sku'         => 'aeo',
    ],
    [
        'key'         => 'llms_full_max_articles',
        'tab'         => 'aeo',
        'section'     => 'llms_full',
        'label'       => 'Max Articles to Include',
        'type'        => 'number',
        'default'     => '500',
        'tier'        => 'pro',
        'sku'         => 'aeo',
    ],

    // ── AI crawler rules (Free, consolidated in Task #463) ───────
    // Single card in AeoTab.vue: master toggle + per-bot allow/block/default
    // matrix (`crawler_bot_rules` JSON map) + free-form custom robots.txt
    // textarea (`crawler_rules`). All three keys are Free.
    [
        'key'           => 'ai_crawlers_enabled',
        'tab'           => 'aeo',
        'section'       => 'crawlers',
        'label'         => 'Enable AI crawler rules',
        'type'          => 'toggle',
        'default'       => '0',
        'tier'          => 'free',
        'sku'           => 'aeo',
        'health'        => [
            'id'                => 'info_ai_crawlers_active',
            'category'          => 'AEO',
            'message'           => 'AI Crawler Rules are active — per-bot allow/block directives are appended to robots.txt.',
            'expected_artifact' => 'robots.txt section "# AI Crawler Rules — AI Boost (per-bot configuration)" with User-agent + Allow/Disallow blocks',
            'fix_actions'       => [
                ['label' => 'Open AEO tab → AI Crawler Rules', 'target_tab' => 'aeo', 'target_field' => 'ai_crawlers_enabled'],
            ],
        ],
    ],
    // Page-level default policy for crawlers not given an explicit per-bot
    // rule (Task #482). Replaces the legacy per-row "Default" select option.
    [
        'key'         => 'aeo_crawler_default_policy',
        'tab'         => 'aeo',
        'section'     => 'crawlers',
        'label'       => 'Default policy for unspecified crawlers',
        'type'        => 'select',
        'options'     => ['allow' => 'Allow all', 'block' => 'Block all'],
        'default'     => 'allow',
        'tier'        => 'free',
        'sku'         => 'aeo',
    ],
    [
        'key'         => 'crawler_bot_rules',
        'tab'         => 'aeo',
        'section'     => 'crawlers',
        'label'       => 'Per-bot AI crawler rules',
        'type'        => 'json',
        'default'     => '{}',
        'tier'        => 'free',
        'sku'         => 'aeo',
    ],
    [
        'key'         => 'crawler_rules',
        'tab'         => 'aeo',
        'section'     => 'crawlers',
        'label'       => 'Custom robots.txt rules',
        'type'        => 'textarea',
        'default'     => '',
        'tier'        => 'free',
        'sku'         => 'aeo',
    ],

    // ── robots.txt scraper controls (Free) ────────────────────────
    [
        'key'         => 'robots_custom_scrapers',
        'tab'         => 'aeo',
        'section'     => 'robots',
        'label'       => 'Additional user-agent blocks',
        'type'        => 'textarea',
        'default'     => '',
        'tier'        => 'free',
        'sku'         => 'aeo',
    ],
    [
        'key'         => 'robots_custom_rules',
        'tab'         => 'aeo',
        'section'     => 'robots',
        'label'       => 'Free-form robots.txt rules',
        'type'        => 'textarea',
        'default'     => '',
        'tier'        => 'free',
        'sku'         => 'aeo',
    ],
    [
        'key' => 'scraper_ahrefsbot', 'tab' => 'aeo', 'section' => 'robots_scrapers',
        'label' => 'Block AhrefsBot', 'type' => 'toggle', 'default' => '0',
        'tier' => 'free', 'sku' => 'aeo',
    ],
    [
        'key' => 'scraper_semrushbot', 'tab' => 'aeo', 'section' => 'robots_scrapers',
        'label' => 'Block SemrushBot', 'type' => 'toggle', 'default' => '0',
        'tier' => 'free', 'sku' => 'aeo',
    ],
    [
        'key' => 'scraper_dotbot', 'tab' => 'aeo', 'section' => 'robots_scrapers',
        'label' => 'Block DotBot', 'type' => 'toggle', 'default' => '0',
        'tier' => 'free', 'sku' => 'aeo',
    ],
    [
        'key' => 'scraper_mj12bot', 'tab' => 'aeo', 'section' => 'robots_scrapers',
        'label' => 'Block MJ12bot', 'type' => 'toggle', 'default' => '0',
        'tier' => 'free', 'sku' => 'aeo',
    ],
    [
        'key' => 'scraper_blexbot', 'tab' => 'aeo', 'section' => 'robots_scrapers',
        'label' => 'Block BLEXBot', 'type' => 'toggle', 'default' => '0',
        'tier' => 'free', 'sku' => 'aeo',
    ],
    [
        'key' => 'scraper_rogerbot', 'tab' => 'aeo', 'section' => 'robots_scrapers',
        'label' => 'Block rogerbot', 'type' => 'toggle', 'default' => '0',
        'tier' => 'free', 'sku' => 'aeo',
    ],
    [
        'key' => 'scraper_screamingfrog', 'tab' => 'aeo', 'section' => 'robots_scrapers',
        'label' => 'Block Screaming Frog SEO Spider', 'type' => 'toggle', 'default' => '0',
        'tier' => 'free', 'sku' => 'aeo',
    ],
    [
        'key' => 'scraper_sitebulb', 'tab' => 'aeo', 'section' => 'robots_scrapers',
        'label' => 'Block Sitebulb', 'type' => 'toggle', 'default' => '0',
        'tier' => 'free', 'sku' => 'aeo',
    ],
    [
        'key' => 'scraper_siteauditor', 'tab' => 'aeo', 'section' => 'robots_scrapers',
        'label' => 'Block SiteAuditBot', 'type' => 'toggle', 'default' => '0',
        'tier' => 'free', 'sku' => 'aeo',
    ],
    [
        'key' => 'scraper_serpstatbot', 'tab' => 'aeo', 'section' => 'robots_scrapers',
        'label' => 'Block SerpstatBot', 'type' => 'toggle', 'default' => '0',
        'tier' => 'free', 'sku' => 'aeo',
    ],
    [
        'key' => 'scraper_bytespider', 'tab' => 'aeo', 'section' => 'robots_scrapers',
        'label' => 'Block Bytespider', 'type' => 'toggle', 'default' => '0',
        'tier' => 'free', 'sku' => 'aeo',
    ],
    [
        'key' => 'scraper_petalbot', 'tab' => 'aeo', 'section' => 'robots_scrapers',
        'label' => 'Block PetalBot', 'type' => 'toggle', 'default' => '0',
        'tier' => 'free', 'sku' => 'aeo',
    ],

    // ── IndexNow ───────────────────────────────────────────────────
    [
        'key'         => 'indexnow_enabled',
        'tab'         => 'aeo',
        'section'     => 'indexnow',
        'label'       => 'Enable IndexNow',
        'type'        => 'toggle',
        'default'     => '0',
        'tier'        => 'pro',
        'sku'         => 'aeo',
    ],
    [
        'key'         => 'indexnow_api_key',
        'tab'         => 'aeo',
        'section'     => 'indexnow',
        'label'       => 'IndexNow API Key',
        'type'        => 'text',
        'default'     => '',
        'tier'        => 'pro',
        'sku'         => 'aeo',
    ],
    [
        'key'         => 'indexnow_auto_submit',
        'tab'         => 'aeo',
        'section'     => 'indexnow',
        'label'       => 'Auto-submit URLs on publish/update',
        'type'        => 'toggle',
        'default'     => '0',
        'tier'        => 'pro',
        'sku'         => 'aeo',
    ],

    // ── Markdown pages ─────────────────────────────────────────────
    [
        'key'         => 'markdown_pages_enabled',
        'tab'         => 'aeo',
        'section'     => 'markdown',
        'label'       => 'Serve pages as Markdown for AI agents',
        'type'        => 'toggle',
        'default'     => '0',
        'tier'        => 'pro',
        'sku'         => 'aeo',
    ],

    // ── AI signals (free) ──────────────────────────────────────────
    [
        'key'         => 'aeo_ai_meta_enabled',
        'tab'         => 'aeo',
        'section'     => 'ai_signals',
        'label'       => 'Enable AI meta tags',
        'type'        => 'toggle',
        'default'     => '1',
        'tier'        => 'free',
        'sku'         => 'aeo',
    ],
];
