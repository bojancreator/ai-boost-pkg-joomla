<?php

namespace AiBoost\Tests\Lib;

use AiBoost\Lib\RobotsTxtBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Table-driven verification of RobotsTxtBuilder (extracted in Task #470
 * from SettingsController::regenerateRobotsTxt). Every section the builder
 * can emit gets at least one positive and one negative case so a regression
 * in the per-section ordering or in opt-out behaviour fails CI loudly.
 *
 * The expected outputs are intentionally written verbatim in the test
 * (not regenerated from the implementation) so a behaviour change must be
 * a conscious test edit, not a silent rebuild.
 */
final class RobotsTxtBuilderTest extends TestCase
{
    private const BASE_URL = 'https://example.com';

    private function headerBlock(): string
    {
        return RobotsTxtBuilder::BEGIN_MARKER . "\n\n"
            . "User-agent: *\nAllow: /\n\n"
            . "# Joomla system paths\n"
            . "Disallow: /administrator/\nDisallow: /api/\nDisallow: /bin/\n"
            . "Disallow: /cache/\nDisallow: /cli/\nDisallow: /components/\n"
            . "Disallow: /includes/\nDisallow: /installation/\nDisallow: /language/\n"
            . "Disallow: /layouts/\nDisallow: /libraries/\nDisallow: /logs/\n"
            . "Disallow: /modules/\nDisallow: /plugins/\nDisallow: /tmp/\n\n"
            . "# Allow public assets\n"
            . "Allow: /templates/\nAllow: /media/\nAllow: /images/";
    }

    public function testHeaderAndSitemapEmittedByDefault(): void
    {
        // ai_crawlers_enabled defaults ON; disable to assert the header +
        // sitemap byte-for-byte without the AI Crawler block. (The crawler
        // block now always emits when enabled — see the Task #482 tests.)
        $out = RobotsTxtBuilder::build(['ai_crawlers_enabled' => '0'], self::BASE_URL);

        $expected = $this->headerBlock()
            . "\n\nSitemap: https://example.com/sitemap.xml\n"
            . "\n" . RobotsTxtBuilder::END_MARKER . "\n";

        $this->assertSame($expected, $out);
    }

    public function testSitemapOmittedWhenEnableSitemapZero(): void
    {
        $out = RobotsTxtBuilder::build(['enable_sitemap' => '0'], self::BASE_URL);

        $this->assertStringNotContainsString('Sitemap:', $out);
        $this->assertStringStartsWith($this->headerBlock(), $out);
    }

    public function testSitemapEmittedWhenEnableSitemapOne(): void
    {
        $out = RobotsTxtBuilder::build(['enable_sitemap' => '1'], self::BASE_URL);

        $this->assertStringContainsString('Sitemap: https://example.com/sitemap.xml', $out);
    }

    public function testSeoScraperBlocksEmittedInCanonicalOrder(): void
    {
        $settings = [
            'enable_sitemap'        => '0',
            // Intentionally set in REVERSE order to prove emitted order
            // follows SCRAPER_MAP definition, not input order.
            'scraper_petalbot'      => '1',
            'scraper_ahrefsbot'     => '1',
            'scraper_semrushbot'    => '1',
            // ai_crawlers default ON would add the crawler block; disable so
            // we can assert the scraper block bytes deterministically.
            'ai_crawlers_enabled'   => '0',
        ];

        $out = RobotsTxtBuilder::build($settings, self::BASE_URL);

        $tail = "\n\n# AI Boost — blocked SEO scrapers\n"
            . "\nUser-agent: AhrefsBot\nDisallow: /\n"
            . "\nUser-agent: SemrushBot\nDisallow: /\n"
            . "\nUser-agent: PetalBot\nDisallow: /\n"
            . "\n" . RobotsTxtBuilder::END_MARKER . "\n";

        $this->assertStringEndsWith($tail, $out);
    }

    public function testNoScraperBlockWhenAllZeroOrMissing(): void
    {
        $out = RobotsTxtBuilder::build(
            ['enable_sitemap' => '0', 'ai_crawlers_enabled' => '0'],
            self::BASE_URL
        );

        $this->assertStringNotContainsString('blocked SEO scrapers', $out);
        $this->assertStringEndsWith(
            "Allow: /images/\n\n" . RobotsTxtBuilder::END_MARKER . "\n",
            $out
        );
    }

    public function testCustomScraperRulesAppendedWhenSet(): void
    {
        $out = RobotsTxtBuilder::build(
            [
                'enable_sitemap'         => '0',
                'ai_crawlers_enabled'    => '0',
                'robots_custom_scrapers' => "User-agent: EvilBot\nDisallow: /",
            ],
            self::BASE_URL
        );

        $this->assertStringEndsWith(
            "# AI Boost — custom scraper rules\n"
            . "User-agent: EvilBot\nDisallow: /\n"
            . "\n" . RobotsTxtBuilder::END_MARKER . "\n",
            $out
        );
    }

    public function testCustomScraperRulesIgnoredWhenWhitespaceOnly(): void
    {
        $out = RobotsTxtBuilder::build(
            [
                'enable_sitemap'         => '0',
                'ai_crawlers_enabled'    => '0',
                'robots_custom_scrapers' => "   \n  \t",
            ],
            self::BASE_URL
        );

        $this->assertStringNotContainsString('custom scraper rules', $out);
    }

    public function testCrawlerBlockEmittedByDefaultWhenBotRulesProvided(): void
    {
        // ai_crawlers_enabled defaults to ON ('1'). Per-bot map with allow/block.
        // Task #482: legacy 'default' (and missing bots) now fall back to the
        // page-level `aeo_crawler_default_policy` (default = 'allow').
        $out = RobotsTxtBuilder::build(
            [
                'enable_sitemap'    => '0',
                'crawler_bot_rules' => json_encode([
                    'gptbot'    => 'block',
                    'claudebot' => 'allow',
                    'youbot'    => 'default',   // ← falls back to page-level policy (allow)
                ]),
            ],
            self::BASE_URL
        );

        $this->assertStringContainsString(
            "# -------------------------------------------------------\n"
            . "# AI Crawler Rules — AI Boost (per-bot configuration)\n"
            . "# -------------------------------------------------------\n",
            $out
        );
        $this->assertStringContainsString(
            "# OpenAI (ChatGPT)\nUser-agent: GPTBot\nDisallow: /",
            $out
        );
        $this->assertStringContainsString(
            "# Anthropic (Claude)\nUser-agent: ClaudeBot\nAllow: /",
            $out
        );
        // 'default' falls back to page-level policy = 'allow'.
        $this->assertStringContainsString("User-agent: YouBot\nAllow: /", $out);
    }

    public function testLegacyDisallowValueStillEmitsBlock(): void
    {
        // Backward compat — pre-Task #482 installs that wrote 'disallow' (or
        // upgraded ones the migration left intact) must still produce
        // Disallow: / for that bot regardless of the page-level default.
        $out = RobotsTxtBuilder::build(
            [
                'enable_sitemap'             => '0',
                'aeo_crawler_default_policy' => 'allow',
                'crawler_bot_rules'          => json_encode(['gptbot' => 'disallow']),
            ],
            self::BASE_URL
        );

        $this->assertStringContainsString("User-agent: GPTBot\nDisallow: /", $out);
    }

    public function testCrawlerDefaultPolicyBlockAppliesToUnspecifiedBots(): void
    {
        // Task #482: page-level Block-all + one explicit Allow override.
        $out = RobotsTxtBuilder::build(
            [
                'enable_sitemap'             => '0',
                'aeo_crawler_default_policy' => 'block',
                'crawler_bot_rules'          => json_encode([
                    'claudebot' => 'allow',
                    'gptbot'    => 'default',   // ← falls back to page-level (block)
                ]),
            ],
            self::BASE_URL
        );

        $this->assertStringContainsString("User-agent: ClaudeBot\nAllow: /", $out);
        $this->assertStringContainsString("User-agent: GPTBot\nDisallow: /", $out);
        // A bot not even mentioned in the map must also follow page-level block.
        $this->assertStringContainsString("User-agent: PerplexityBot\nDisallow: /", $out);
    }

    public function testCrawlerBlockOmittedWhenAiCrawlersDisabled(): void
    {
        $out = RobotsTxtBuilder::build(
            [
                'enable_sitemap'      => '0',
                'ai_crawlers_enabled' => '0',
                'crawler_bot_rules'   => json_encode(['gptbot' => 'block']),
                'crawler_rules'       => 'User-agent: CCBot',
            ],
            self::BASE_URL
        );

        $this->assertStringNotContainsString('AI Crawler Rules', $out);
        $this->assertStringNotContainsString('GPTBot', $out);
        $this->assertStringNotContainsString('CCBot', $out);
    }

    public function testCrawlerHeaderEmittedWhenAllBotsDefaultUsingPageLevelPolicy(): void
    {
        // Task #482: with the page-level default policy in effect, every bot
        // now gets an explicit Allow/Disallow line — there is no "skip"
        // outcome for a bot with rule='default' or missing.
        $out = RobotsTxtBuilder::build(
            [
                'enable_sitemap'    => '0',
                'crawler_bot_rules' => json_encode(['gptbot' => 'default']),
            ],
            self::BASE_URL
        );

        $this->assertStringContainsString('AI Crawler Rules', $out);
        // Default page-level policy = 'allow'.
        $this->assertStringContainsString("User-agent: GPTBot\nAllow: /", $out);
    }

    public function testCustomCrawlerRulesAppendedAfterMatrix(): void
    {
        $out = RobotsTxtBuilder::build(
            [
                'enable_sitemap'    => '0',
                'crawler_bot_rules' => json_encode(['gptbot' => 'block']),
                'crawler_rules'     => "User-agent: CCBot\nDisallow: /",
            ],
            self::BASE_URL
        );

        $matrixPos = strpos($out, 'GPTBot');
        $customPos = strpos($out, 'CCBot');
        $this->assertNotFalse($matrixPos);
        $this->assertNotFalse($customPos);
        $this->assertGreaterThan($matrixPos, $customPos, 'Custom AI crawler rules must follow the per-bot matrix.');
        $this->assertStringEndsWith(
            "Disallow: /\n\n" . RobotsTxtBuilder::END_MARKER . "\n",
            $out
        );
    }

    public function testInvalidCrawlerBotRulesJsonDoesNotCrashAndUsesDefaults(): void
    {
        // Task #482: invalid JSON → decoded as null → empty bot map → all
        // bots fall back to the page-level default policy (Allow by default).
        // The builder must not crash and must still emit a valid block.
        $out = RobotsTxtBuilder::build(
            ['enable_sitemap' => '0', 'crawler_bot_rules' => 'not-json{{{'],
            self::BASE_URL
        );

        $this->assertStringContainsString('AI Crawler Rules', $out);
        $this->assertStringContainsString("User-agent: GPTBot\nAllow: /", $out);
    }

    public function testOutputAlwaysEndsWithSingleNewline(): void
    {
        foreach (
            [
                [],
                ['enable_sitemap' => '0'],
                ['scraper_ahrefsbot' => '1'],
                ['crawler_bot_rules' => json_encode(['gptbot' => 'block'])],
                ['crawler_rules' => 'User-agent: X'],
            ] as $i => $settings
        ) {
            $out = RobotsTxtBuilder::build($settings, self::BASE_URL);
            $this->assertSame(
                "\n",
                substr($out, -1),
                "Case #$i must end with exactly one newline."
            );
            $this->assertNotSame(
                "\n\n",
                substr($out, -2),
                "Case #$i must not end with a double newline."
            );
        }
    }
}
