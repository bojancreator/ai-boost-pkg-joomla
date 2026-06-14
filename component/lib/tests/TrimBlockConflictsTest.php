<?php
/**
 * Unit tests for AiBoost\Lib\HeadBlockBuilder::trimBlockConflicts() — the pure,
 * marker/signature-based cooperative dedup (Deliverable B). No Joomla/DB needed.
 *
 * Run: vendor/bin/phpunit component/lib/tests/TrimBlockConflictsTest.php
 *
 * @package     AiBoost\Lib\Tests
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Tests;

use AiBoost\Lib\HeadBlockBuilder;
use PHPUnit\Framework\TestCase;

if (!defined('_JEXEC')) {
    define('_JEXEC', 1);
}
if (!defined('JPATH_ROOT')) {
    define('JPATH_ROOT', dirname(__DIR__, 3));
}

require_once dirname(__DIR__) . '/src/HeadBlockBuilder.php';

final class TrimBlockConflictsTest extends TestCase
{
    private function block(): string
    {
        return implode("\n", [
            '<!-- AI Boost for Joomla - Start -->',
            '<!-- Schema.org -->',
            '<script type="application/ld+json">{"@type":"Organization","name":"Acme"}</script>',
            '<script type="application/ld+json">{"@type":"BreadcrumbList","x":1}</script>',
            '<!-- OpenGraph & Twitter -->',
            '<meta property="og:title" content="Ours">',
            '<meta property="og:type" content="website">',
            '<meta name="twitter:card" content="summary">',
            '<!-- AI Boost for Joomla - End -->',
        ]);
    }

    protected function setUp(): void
    {
        HeadBlockBuilder::reset();
    }

    /** Competing OG present (4SEO-style) → our WHOLE social set goes, schema stays. */
    public function testCooperativeRemovesOurOgSetWhenThirdPartyEmitsOg(): void
    {
        $out = HeadBlockBuilder::trimBlockConflicts(
            $this->block(),
            '<meta property="og:title" content="Theirs" class="4SEO_ogp_tag">',
            'cooperative'
        );
        $this->assertStringNotContainsString('og:title', $out);
        $this->assertStringNotContainsString('og:type', $out);
        $this->assertStringNotContainsString('twitter:card', $out);
        $this->assertStringContainsString('Organization', $out, 'schema must survive an OG conflict');
        $this->assertStringContainsString('BreadcrumbList', $out);
    }

    /** Our schema is NEVER trimmed in Phase 1b (JSON-LD dedup deferred to 1c). */
    public function testSchemaNeverTrimmedYet(): void
    {
        $out = HeadBlockBuilder::trimBlockConflicts(
            $this->block(),
            '<head><script type="application/ld+json">{"@type":"Organization"}</script></head>',
            'cooperative'
        );
        $this->assertStringContainsString('Organization', $out, 'JSON-LD dedup is Phase 1c — schema must stay');
        $this->assertSame(2, substr_count($out, 'application/ld+json'));
    }

    /** Detection is <head>-scoped: an og: mention in the BODY must NOT trim our OG. */
    public function testHeadScopedDetectionIgnoresBodyContent(): void
    {
        $theirs = '<html><head><title>x</title></head><body>'
                . '<pre>&lt;meta property="og:title"&gt;</pre>'
                . '<meta property="og:title" content="example-in-body">'
                . '</body></html>';
        $out = HeadBlockBuilder::trimBlockConflicts($this->block(), $theirs, 'cooperative');
        $this->assertStringContainsString('og:title', $out, 'body content must not trigger a trim of our OG');
        $this->assertSame($this->block(), $out);
    }

    /** A real competing OG tag in the <head> DOES trim our set. */
    public function testHeadOgTriggersTrim(): void
    {
        $theirs = '<html><head><meta property="og:title" content="Theirs"></head><body></body></html>';
        $out = HeadBlockBuilder::trimBlockConflicts($this->block(), $theirs, 'cooperative');
        $this->assertStringNotContainsString('og:title', $out);
    }

    public function testNoCompetitorLeavesBlockByteIdentical(): void
    {
        $this->assertSame($this->block(), HeadBlockBuilder::trimBlockConflicts($this->block(), '', 'cooperative'));
        $this->assertSame($this->block(), HeadBlockBuilder::trimBlockConflicts($this->block(), '<p>hi</p>', 'cooperative'));
    }

    public function testAggressiveAndOffNeverTrim(): void
    {
        $theirs = '<meta property="og:title" content="Theirs">';
        $this->assertSame($this->block(), HeadBlockBuilder::trimBlockConflicts($this->block(), $theirs, 'aggressive'));
        $this->assertSame($this->block(), HeadBlockBuilder::trimBlockConflicts($this->block(), $theirs, 'off'));
    }

    /** The safety invariant: the third-party HTML is an input string, never mutated. */
    public function testNeverTouchesTheirs(): void
    {
        $theirs = '<meta property="og:title" content="FOREIGN">';
        HeadBlockBuilder::trimBlockConflicts($this->block(), $theirs, 'cooperative');
        $this->assertSame('<meta property="og:title" content="FOREIGN">', $theirs);
    }

    /** OG is all-or-nothing: even a lone og:image competitor removes our full set. */
    public function testOgAllOrNothing(): void
    {
        $out = HeadBlockBuilder::trimBlockConflicts(
            $this->block(),
            '<meta property="og:image" content="x.jpg">',
            'cooperative'
        );
        $this->assertStringNotContainsString('og:title', $out);
        $this->assertStringNotContainsString('twitter:card', $out);
    }
}
