<?php
/**
 * AI Boost — robots.txt builder
 *
 * Pure, side-effect-free generator for the managed robots.txt file. Takes a
 * decoded #__aiboost_settings JSON blob plus the site's base URL and returns
 * the file contents as a single string ending in "\n".
 *
 * Extracted from SettingsController::regenerateRobotsTxt() so the per-section
 * ordering (Joomla system paths → sitemap → SEO scraper blocks → custom
 * scrapers → per-bot AI crawler matrix → custom AI crawler rules) can be
 * verified by a table-driven PHPUnit suite without booting Joomla.
 *
 * The controller's regenerateRobotsTxt() now delegates here, then file_puts
 * the result so production behaviour is byte-identical to the old inline impl.
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib;

defined('_JEXEC') or die;

final class RobotsTxtBuilder
{
    /**
     * Fenced markers that delimit the AI Boost managed block inside the user's
     * robots.txt. AI Boost owns ONLY the text between these markers — anything
     * outside them belongs to the site owner and is never touched. The strings
     * MUST stay byte-identical with the inline copies in pkg_script.php and the
     * component installer script.php (which cannot autoload this class during
     * uninstall).
     */
    public const BEGIN_MARKER = '# BEGIN AI Boost for Joomla managed block (aiboostnow.com) - do not edit between these markers';
    public const END_MARKER   = '# END AI Boost for Joomla managed block';

    /**
     * Pre-fence legacy marker. Old installs overwrote the WHOLE robots.txt with
     * a body that started with this line (no end marker). When we see it at the
     * very top of the file we treat the entire file as ours for stripping.
     */
    private const LEGACY_MARKER = '# Managed by AI Boost';

    /**
     * Per-bot SEO scraper blocks — canonical scraper_* keys from the AEO tab.
     * Order is intentionally preserved (UI list order = robots.txt order).
     */
    private const SCRAPER_MAP = [
        'scraper_ahrefsbot'     => 'AhrefsBot',
        'scraper_semrushbot'    => 'SemrushBot',
        'scraper_dotbot'        => 'DotBot',
        'scraper_mj12bot'       => 'MJ12bot',
        'scraper_blexbot'       => 'BLEXBot',
        'scraper_rogerbot'      => 'rogerbot',
        'scraper_screamingfrog' => 'Screaming Frog SEO Spider',
        'scraper_sitebulb'      => 'Sitebulb',
        'scraper_siteauditor'   => 'SiteAuditBot',
        'scraper_serpstatbot'   => 'SerpstatBot',
        'scraper_bytespider'    => 'Bytespider',
        'scraper_petalbot'      => 'PetalBot',
    ];

    /**
     * AI bot id → [user-agent string, comment line] for the per-bot
     * Allow/Block matrix written when ai_crawlers_enabled=1.
     * Mirrors the BOTS list in component/.../AeoTab.vue.
     */
    private const BOT_UA_MAP = [
        'gptbot'        => ['GPTBot',            '# OpenAI (ChatGPT)'],
        'claudebot'     => ['ClaudeBot',         '# Anthropic (Claude)'],
        'perplexitybot' => ['PerplexityBot',     '# Perplexity AI'],
        'geminibot'     => ['Google-Extended',   '# Google AI (Gemini, AI Overviews)'],
        'bingbot'       => ['bingbot',           '# Bing / Bing Copilot'],
        'facebookbot'   => ['FacebookBot',       '# Meta AI (Llama)'],
        'applebot'      => ['Applebot-Extended', '# Apple (Siri, Spotlight)'],
        'duckassistbot' => ['DuckAssistBot',     '# DuckDuckGo AI (DuckAssist)'],
        'youbot'        => ['YouBot',            '# You.com AI Search'],
    ];

    /**
     * Render robots.txt as a single string (trailing newline included).
     *
     * @param array<string,mixed> $settings Decoded #__aiboost_settings blob.
     * @param string              $baseUrl  e.g. 'https://example.com' (no trailing slash).
     */
    public static function build(array $settings, string $baseUrl): string
    {
        return self::buildManagedBlock($settings, $baseUrl);
    }

    /**
     * Render the AI Boost managed block — the fenced section we own inside the
     * user's robots.txt. Always opens with BEGIN_MARKER and closes with
     * END_MARKER so {@see stripManagedBlock()} can remove exactly this region
     * later, leaving any user-authored rules intact. Ends in a single newline.
     *
     * @param array<string,mixed> $settings Decoded #__aiboost_settings blob.
     * @param string              $baseUrl  e.g. 'https://example.com' (no trailing slash).
     */
    public static function buildManagedBlock(array $settings, string $baseUrl): string
    {
        $lines = [
            self::BEGIN_MARKER,
            '',
            'User-agent: *',
            'Allow: /',
            '',
            '# Joomla system paths',
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

        // Sitemap line — opt-out only (default ON, written unless explicit '0').
        if (!isset($settings['enable_sitemap']) || (string) $settings['enable_sitemap'] !== '0') {
            $lines[] = '';
            $lines[] = "Sitemap: {$baseUrl}/sitemap.xml";
        }

        // Per-bot SEO scraper blocks.
        $blockedScrapers = [];
        foreach (self::SCRAPER_MAP as $key => $ua) {
            if ((string) ($settings[$key] ?? '0') === '1') {
                $blockedScrapers[] = $ua;
            }
        }
        if (!empty($blockedScrapers)) {
            $lines[] = '';
            $lines[] = '# AI Boost — blocked SEO scrapers';
            foreach ($blockedScrapers as $ua) {
                $lines[] = '';
                $lines[] = 'User-agent: ' . $ua;
                $lines[] = 'Disallow: /';
            }
        }

        // Custom free-form scraper rules textarea.
        $customScrapers = trim((string) ($settings['robots_custom_scrapers'] ?? ''));
        if ($customScrapers !== '') {
            $lines[] = '';
            $lines[] = '# AI Boost — custom scraper rules';
            $lines[] = $customScrapers;
        }

        // AI Crawler Rules per-bot matrix (Free, default ON unless explicit '0').
        $crawlerMgmtOn = (string) ($settings['ai_crawlers_enabled'] ?? '1') !== '0';
        if ($crawlerMgmtOn) {
            $botRules = [];
            $decoded = json_decode((string) ($settings['crawler_bot_rules'] ?? '{}'), true);
            if (is_array($decoded)) {
                $botRules = $decoded;
            }

            // Task #482 — page-level default policy for crawlers with no
            // explicit per-bot rule (or the legacy 'default' value). Allow|Block.
            $defaultPolicy = strtolower(trim((string) ($settings['aeo_crawler_default_policy'] ?? 'allow')));
            if ($defaultPolicy !== 'block') {
                $defaultPolicy = 'allow';
            }

            $emitted = [];
            foreach (self::BOT_UA_MAP as $botId => [$ua, $comment]) {
                $rule = strtolower(trim((string) ($botRules[$botId] ?? '')));
                // Legacy 'default' (or empty / unknown) falls back to the
                // page-level default policy.
                if ($rule !== 'allow' && $rule !== 'block' && $rule !== 'disallow') {
                    $rule = $defaultPolicy;
                }
                $directive = ($rule === 'block' || $rule === 'disallow') ? 'Disallow: /' : 'Allow: /';
                $emitted[] = [$ua, $comment, $directive];
            }

            if (!empty($emitted)) {
                $lines[] = '';
                $lines[] = '# -------------------------------------------------------';
                $lines[] = '# AI Crawler Rules — AI Boost (per-bot configuration)';
                $lines[] = '# -------------------------------------------------------';
                foreach ($emitted as [$ua, $comment, $directive]) {
                    $lines[] = '';
                    $lines[] = $comment;
                    $lines[] = 'User-agent: ' . $ua;
                    $lines[] = $directive;
                }
            }

            // Custom AI crawler rules textarea — appended verbatim.
            $customCrawlerRules = trim((string) ($settings['crawler_rules'] ?? ''));
            if ($customCrawlerRules !== '') {
                $lines[] = '';
                $lines[] = '# AI Boost — custom AI crawler rules (from AEO tab textarea)';
                $lines[] = $customCrawlerRules;
            }
        }

        $lines[] = '';
        $lines[] = self::END_MARKER;

        return implode("\n", $lines) . "\n";
    }

    /**
     * Remove the AI Boost managed block from existing robots.txt content,
     * returning whatever the site owner authored around it. Handles both the
     * current fenced format (BEGIN_MARKER … END_MARKER) and the pre-fence legacy
     * format (whole file was ours → returns '').
     *
     * @param string $existing Raw current robots.txt content.
     * @return string Content with our block removed (may be empty).
     */
    public static function stripManagedBlock(string $existing): string
    {
        if ($existing === '') {
            return '';
        }

        // Legacy pre-fence install: the entire file was generated by us.
        if (str_starts_with(ltrim($existing, "\xEF\xBB\xBF \t\r\n"), self::LEGACY_MARKER)) {
            return '';
        }

        $pattern = '/\n*' . preg_quote(self::BEGIN_MARKER, '/')
            . '.*?' . preg_quote(self::END_MARKER, '/') . '[^\n]*\n?/su';

        return (string) preg_replace($pattern, '', $existing);
    }

    /**
     * Merge our freshly built managed block into the user's existing robots.txt:
     * strip any previous AI Boost block, then append the new one. User-authored
     * rules are preserved; never clobbers the whole file.
     *
     * @param string              $existing Raw current robots.txt content ('' if none).
     * @param array<string,mixed> $settings Decoded #__aiboost_settings blob.
     * @param string              $baseUrl  e.g. 'https://example.com'.
     */
    public static function injectManagedBlock(string $existing, array $settings, string $baseUrl): string
    {
        $clean = rtrim(self::stripManagedBlock($existing));
        $block = self::buildManagedBlock($settings, $baseUrl);

        if ($clean === '') {
            return $block;
        }

        return $clean . "\n\n" . $block;
    }
}
