<?php

/**
 * Environment Type Enum for JoomlaBoost
 *
 * @package     JoomlaBoost
 * @subpackage  Plugin.System.Enums
 * @since       4.0
 */

declare(strict_types=1);

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Enums;

\defined('_JEXEC') or die;

/**
 * Represents the deployment environment type
 */
enum EnvironmentType: string
{
    case Production = 'production';
    case Staging    = 'staging';
    case Local      = 'local';

    /**
     * Detect environment from a domain string
     */
    public static function detectFromDomain(string $domain): self
    {
        $lower = strtolower($domain);

        $stagingKeywords = ['staging', 'stage', 'dev', 'test', 'localhost', '127.0.0.1', '.local'];

        foreach ($stagingKeywords as $keyword) {
            if (str_contains($lower, $keyword)) {
                return self::Staging;
            }
        }

        return self::Production;
    }

    /**
     * Whether search engines should be allowed to index this environment
     */
    public function allowSearchEngines(): bool
    {
        return $this === self::Production;
    }

    /**
     * Whether this is a production environment
     */
    public function isProduction(): bool
    {
        return $this === self::Production;
    }

    /**
     * Get a human-readable label for this environment
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Production => 'Production',
            self::Staging    => 'Staging',
            self::Local      => 'Local',
        };
    }

    /**
     * Get robots.txt rules for this environment.
     *
     * @param  array<string,int>  $crawlerParams  Map of param name → 1 (Allow) or 0 (Disallow).
     *                                            All crawlers default to Allow (1) if not present.
     * @return array<int, string>
     */
    public function getRobotsRules(array $crawlerParams = []): array
    {
        if (!$this->isProduction()) {
            return [
                '# AI Boost for Joomla - Robots.txt - ' . strtoupper($this->value) . ' ENVIRONMENT',
                '',
                'User-agent: *',
                'Disallow: /',
            ];
        }

        $rules = [
            '# AI Boost for Joomla - Robots.txt - PRODUCTION ENVIRONMENT',
            '',
            'User-agent: *',
            'Allow: /',
            '',
            '# Disallow admin and system folders',
            'Disallow: /administrator/',
            'Disallow: /api/',
            'Disallow: /bin/',
            'Disallow: /cache/',
            'Disallow: /cli/',
            'Disallow: /components/',
            'Disallow: /includes/',
            'Disallow: /installation/',
            'Disallow: /language/',
            'Disallow: /layouts/',
            'Disallow: /libraries/',
            'Disallow: /logs/',
            'Disallow: /modules/',
            'Disallow: /plugins/',
            'Disallow: /tmp/',
            '',
            '# Allow public assets',
            'Allow: /templates/',
            'Allow: /media/',
            'Allow: /images/',
        ];

        // ── AI & SEO Crawler rules ─────────────────────────────────────────────
        // All crawlers are allowed by default (param value 1).
        // Set param to 0 in plugin settings to disallow a specific bot.
        $crawlers = [
            // AI Content & Training Crawlers
            ['param' => 'ai_allow_gptbot',       'ua' => 'GPTBot',        'comment' => '# OpenAI (ChatGPT, GPT-4o search)'],
            ['param' => 'ai_allow_oaisearchbot',  'ua' => 'OAI-SearchBot', 'comment' => '# OpenAI Search'],
            ['param' => 'ai_allow_claudebot',     'ua' => 'ClaudeBot',     'comment' => '# Anthropic (Claude)'],
            ['param' => 'ai_allow_anthropicai',   'ua' => 'anthropic-ai',  'comment' => '# Anthropic (secondary)'],
            ['param' => 'ai_allow_perplexity',    'ua' => 'PerplexityBot', 'comment' => '# Perplexity AI'],
            ['param' => 'ai_allow_googleext',     'ua' => 'Google-Extended','comment' => '# Google AI (Gemini, AI Overviews)'],
            ['param' => 'ai_allow_cohereai',      'ua' => 'cohere-ai',     'comment' => '# Cohere AI'],
            ['param' => 'ai_allow_facebookbot',   'ua' => 'FacebookBot',   'comment' => '# Meta AI (Llama)'],
            ['param' => 'ai_allow_amazonbot',     'ua' => 'Amazonbot',     'comment' => '# Amazon Alexa / AI'],
            ['param' => 'ai_allow_applebot',      'ua' => 'Applebot',      'comment' => '# Apple (Siri, Spotlight)'],
            ['param' => 'ai_allow_ccbot',         'ua' => 'CCBot',         'comment' => '# Common Crawl (AI training datasets)'],
            ['param' => 'ai_allow_youbot',        'ua' => 'YouBot',        'comment' => '# You.com AI Search'],
            ['param' => 'ai_allow_timpibot',      'ua' => 'Timpibot',      'comment' => '# Timpi AI'],
            ['param' => 'ai_allow_bytespider',    'ua' => 'Bytespider',    'comment' => '# ByteDance / TikTok AI'],
            ['param' => 'ai_allow_duckassist',    'ua' => 'DuckAssistBot', 'comment' => '# DuckDuckGo AI (DuckAssist)'],
            // SEO & Analysis Crawlers
            ['param' => 'ai_allow_semrush',       'ua' => 'SemrushBot',    'comment' => '# Semrush'],
            ['param' => 'ai_allow_ahrefs',        'ua' => 'AhrefsBot',     'comment' => '# Ahrefs'],
            ['param' => 'ai_allow_mj12bot',       'ua' => 'MJ12bot',       'comment' => '# Majestic'],
            ['param' => 'ai_allow_dotbot',        'ua' => 'DotBot',        'comment' => '# Moz'],
            ['param' => 'ai_allow_dataforseo',    'ua' => 'DataForSeoBot', 'comment' => '# DataForSEO'],
            ['param' => 'ai_allow_diffbot',       'ua' => 'Diffbot',       'comment' => '# Diffbot AI'],
            ['param' => 'ai_allow_yandexbot',     'ua' => 'YandexBot',     'comment' => '# Yandex Search'],
            ['param' => 'ai_allow_omgili',        'ua' => 'omgili',        'comment' => '# Webhose.io'],
            ['param' => 'ai_allow_ia_archiver',   'ua' => 'ia_archiver',   'comment' => '# Internet Archive / Wayback Machine'],
            ['param' => 'ai_allow_scrapy',        'ua' => 'Scrapy',        'comment' => '# Scrapy (Python scraping framework)'],
            ['param' => 'ai_allow_kangaroo',      'ua' => 'KangoorooBot',  'comment' => '# Kangaroo Bot'],
        ];

        $rules[] = '';
        $rules[] = '# -------------------------------------------------------';
        $rules[] = '# AI & SEO Crawlers — configured via AI Boost plugin';
        $rules[] = '# -------------------------------------------------------';

        foreach ($crawlers as $crawler) {
            $allowed = (int) ($crawlerParams[$crawler['param']] ?? 1);
            $directive = $allowed ? 'Allow: /' : 'Disallow: /';
            $rules[] = '';
            $rules[] = $crawler['comment'];
            $rules[] = 'User-agent: ' . $crawler['ua'];
            $rules[] = $directive;
        }

        return $rules;
    }
}
