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
        'key'         => 'llmstxt_faq_auto_detect',
        'tab'         => 'aeo',
        'section'     => 'llmstxt',
        'label'       => 'Auto-Detect FAQ from Articles',
        'type'        => 'toggle',
        'default'     => '0',
        'tier'        => 'free',
        'sku'         => 'aeo',
    ],

    // ── llms-full.txt (Pro) ────────────────────────────────────────
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

    // ── IndexNow (Pro) ─────────────────────────────────────────────
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

    // ── Markdown pages (Pro) ───────────────────────────────────────
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
