<?php
/**
 * AI Boost — RobotsBotRules (Pro)
 *
 * Per-bot rules for the 26 supported AI + SEO crawlers. Appended to the
 * Free baseline robots.txt by AiBoostAeo::onAiBoostFilterRobotsRules.
 *
 * Each bot has a default policy (allow/disallow) that customers can
 * override via `bot_*` settings keys bound to the AEO tab UI.
 *
 * @package     AiBoost\Plugin\System\AiBoostAeo
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostAeo\Service;

defined('_JEXEC') or die;

final class RobotsBotRules
{
    /**
     * All 26 supported crawlers.
     * key = settings key, value = [ua => User-agent, comment => robots.txt comment]
     *
     * @var array<string, array{ua: string, comment: string}>
     */
    public const BOT_DEFINITIONS = [
        // ── AI Content & Training Crawlers ──
        'bot_gptbot'        => ['ua' => 'GPTBot',          'comment' => '# OpenAI (ChatGPT, GPT-4o Search)'],
        'bot_oaisearchbot'  => ['ua' => 'OAI-SearchBot',   'comment' => '# OpenAI Search'],
        'bot_claudebot'     => ['ua' => 'ClaudeBot',       'comment' => '# Anthropic (Claude)'],
        'bot_anthropicai'   => ['ua' => 'anthropic-ai',    'comment' => '# Anthropic (secondary UA)'],
        'bot_perplexitybot' => ['ua' => 'PerplexityBot',   'comment' => '# Perplexity AI'],
        'bot_googleext'     => ['ua' => 'Google-Extended', 'comment' => '# Google AI (Gemini, AI Overviews)'],
        'bot_cohereai'      => ['ua' => 'cohere-ai',       'comment' => '# Cohere AI'],
        'bot_facebookbot'   => ['ua' => 'FacebookBot',     'comment' => '# Meta AI (Llama)'],
        'bot_amazonbot'     => ['ua' => 'Amazonbot',       'comment' => '# Amazon Alexa / AI'],
        'bot_applebot'      => ['ua' => 'Applebot',        'comment' => '# Apple (Siri, Spotlight)'],
        'bot_ccbot'         => ['ua' => 'CCBot',           'comment' => '# Common Crawl (AI training datasets)'],
        'bot_youbot'        => ['ua' => 'YouBot',          'comment' => '# You.com AI Search'],
        'bot_timpibot'      => ['ua' => 'Timpibot',        'comment' => '# Timpi AI'],
        'bot_bytespider'    => ['ua' => 'Bytespider',      'comment' => '# ByteDance / TikTok AI'],
        'bot_duckassistbot' => ['ua' => 'DuckAssistBot',   'comment' => '# DuckDuckGo AI (DuckAssist)'],
        // ── SEO & Analysis Crawlers ──
        'bot_googlebot'     => ['ua' => 'Googlebot',       'comment' => '# Google Search'],
        'bot_bingbot'       => ['ua' => 'bingbot',         'comment' => '# Bing / Bing Copilot'],
        'bot_yandexbot'     => ['ua' => 'YandexBot',       'comment' => '# Yandex Search'],
        'bot_semrushbot'    => ['ua' => 'SemrushBot',      'comment' => '# Semrush'],
        'bot_ahrefsbot'     => ['ua' => 'AhrefsBot',       'comment' => '# Ahrefs'],
        'bot_mj12bot'       => ['ua' => 'MJ12bot',         'comment' => '# Majestic'],
        'bot_dotbot'        => ['ua' => 'DotBot',          'comment' => '# Moz'],
        'bot_dataforseobot' => ['ua' => 'DataForSeoBot',   'comment' => '# DataForSEO'],
        'bot_diffbot'       => ['ua' => 'Diffbot',         'comment' => '# Diffbot AI'],
        'bot_ia_archiver'   => ['ua' => 'ia_archiver',     'comment' => '# Internet Archive / Wayback Machine'],
        'bot_scrapy'        => ['ua' => 'Scrapy',          'comment' => '# Scrapy (scraping framework)'],
        'bot_omgili'        => ['ua' => 'omgili',          'comment' => '# Webhose.io'],
        'bot_kangaroobot'   => ['ua' => 'KangoorooBot',    'comment' => '# Kangaroo Bot'],
    ];

    /**
     * Default per-bot value when setting is not present in DB.
     *
     * @var array<string, string>
     */
    public const BOT_DEFAULTS = [
        'bot_gptbot'        => 'allow',
        'bot_oaisearchbot'  => 'allow',
        'bot_claudebot'     => 'allow',
        'bot_anthropicai'   => 'allow',
        'bot_perplexitybot' => 'allow',
        'bot_googleext'     => 'allow',
        'bot_cohereai'      => 'allow',
        'bot_facebookbot'   => 'allow',
        'bot_amazonbot'     => 'allow',
        'bot_applebot'      => 'allow',
        'bot_ccbot'         => 'allow',
        'bot_youbot'        => 'allow',
        'bot_timpibot'      => 'allow',
        'bot_bytespider'    => 'disallow',
        'bot_duckassistbot' => 'allow',
        'bot_googlebot'     => 'allow',
        'bot_bingbot'       => 'allow',
        'bot_yandexbot'     => 'allow',
        'bot_semrushbot'    => 'disallow',
        'bot_ahrefsbot'     => 'disallow',
        'bot_mj12bot'       => 'disallow',
        'bot_dotbot'        => 'disallow',
        'bot_dataforseobot' => 'disallow',
        'bot_diffbot'       => 'allow',
        'bot_ia_archiver'   => 'allow',
        'bot_scrapy'        => 'disallow',
        'bot_omgili'        => 'disallow',
        'bot_kangaroobot'   => 'disallow',
    ];

    /**
     * Decorate the Free baseline robots.txt managed section with Pro
     * per-bot crawler rules. The Free section ends with custom-rule
     * blocks — the Pro per-bot block is inserted before any
     * "# Custom rules" footer so the layout matches the legacy file.
     *
     * @param array<string,mixed> $settings
     */
    public function decorate(string $managedSection, array $settings): string
    {
        $botBlock = $this->buildBotBlock($settings);
        if ($botBlock === '') {
            return $managedSection;
        }

        // Insert before the trailing "# Custom rules" block if present;
        // otherwise append at the end.
        $needle = "\n# Custom rules\n";
        $pos    = strpos($managedSection, $needle);
        if ($pos !== false) {
            return substr($managedSection, 0, $pos) . "\n" . $botBlock . substr($managedSection, $pos);
        }

        return rtrim($managedSection) . "\n\n" . $botBlock;
    }

    /** @param array<string,mixed> $settings */
    private function buildBotBlock(array $settings): string
    {
        $lines   = [];
        $lines[] = '# -------------------------------------------------------';
        $lines[] = '# AI & SEO Crawlers — configured via AI Boost plugin';
        $lines[] = '# -------------------------------------------------------';

        foreach (self::BOT_DEFINITIONS as $key => $bot) {
            $rule      = trim((string) ($settings[$key] ?? self::BOT_DEFAULTS[$key] ?? 'allow'));
            $directive = ($rule === 'disallow') ? 'Disallow: /' : 'Allow: /';

            $lines[] = '';
            $lines[] = $bot['comment'];
            $lines[] = 'User-agent: ' . $bot['ua'];
            $lines[] = $directive;
        }

        $lines[] = '';
        return implode("\n", $lines);
    }
}
