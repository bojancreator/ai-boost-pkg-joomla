<?php
/**
 * AI Boost — Core (free) manifest.
 * Fields shared across the General tab and license/dev section.
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
];
