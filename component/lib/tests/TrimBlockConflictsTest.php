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
            '<script type="application/ld+json">{"@type":"Organization","@id":"https://s/#org","name":"Acme"}</script>',
            '<script type="application/ld+json">{"@type":"BreadcrumbList","x":1}</script>',
            '<script type="application/ld+json">{"@type":"Article","headline":"Post","publisher":{"@type":"Organization","name":"Acme"}}</script>',
            '<!-- OpenGraph & Twitter -->',
            '<meta property="og:title" content="Ours">',
            '<meta property="og:type" content="website">',
            '<meta name="twitter:card" content="summary">',
            '<!-- Analytics -->',
            '<script async src="https://www.googletagmanager.com/gtag/js?id=G-OURS"></script>',
            "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','G-OURS');</script>",
            "<script>(function(w,d,s,l,i){var j=d.createElement(s);j.src='https://www.googletagmanager.com/gtm.js?id='+i;})(window,document,'script','dataLayer','GTM-OURS');</script>",
            "<script>!function(f,b,e,v,n,t,s){}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','PIXOURS');fbq('track','PageView');</script>",
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

    /** Competing standalone Organization → our TOP-LEVEL Org goes; our Article (which
     *  NESTS an Organization publisher) and BreadcrumbList survive. THE blocker case. */
    public function testSchemaDedupRemovesTopLevelOrgKeepsArticle(): void
    {
        $out = HeadBlockBuilder::trimBlockConflicts(
            $this->block(),
            '<head><script type="application/ld+json">{"@type":"Organization","name":"Theirs"}</script></head>',
            'cooperative'
        );
        $this->assertSame(2, substr_count($out, 'application/ld+json'), 'exactly our top-level Org node removed');
        $this->assertStringNotContainsString('#org', $out, 'our top-level Organization node must go');
        $this->assertStringContainsString('"@type":"Article"', $out, 'Article (nests Organization) must be kept');
        $this->assertStringContainsString('BreadcrumbList', $out);
    }

    /** A nested Organization in THEIR Article (no standalone Org) must NOT trigger our dedup. */
    public function testSchemaDedupIgnoresNestedOrgInTheirs(): void
    {
        $theirs = '<head><script type="application/ld+json">{"@type":"Article","publisher":{"@type":"Organization","name":"T"}}</script></head>';
        $out = HeadBlockBuilder::trimBlockConflicts($this->block(), $theirs, 'cooperative');
        $this->assertSame(3, substr_count($out, 'application/ld+json'), 'a nested-only Organization must not dedup ours');
        $this->assertStringContainsString('#org', $out);
    }

    /** @graph-aware: an Organization inside their @graph triggers the dedup. */
    public function testSchemaDedupGraphAware(): void
    {
        $theirs = '<head><script type="application/ld+json">{"@graph":[{"@type":"WebSite"},{"@type":"Organization"}]}</script></head>';
        $out = HeadBlockBuilder::trimBlockConflicts($this->block(), $theirs, 'cooperative');
        $this->assertSame(2, substr_count($out, 'application/ld+json'));
        $this->assertStringNotContainsString('#org', $out);
        $this->assertStringContainsString('"@type":"Article"', $out);
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

    /** GA4 competitor → BOTH our loader and our inline gtag config are removed; GTM/Pixel kept. */
    public function testAnalyticsGa4RemovesBothParts(): void
    {
        $out = HeadBlockBuilder::trimBlockConflicts(
            $this->block(),
            '<head><script async src="https://www.googletagmanager.com/gtag/js?id=G-THEIRS"></script></head>',
            'cooperative'
        );
        $this->assertStringNotContainsString('gtag/js', $out, 'our GA4 loader must go');
        $this->assertStringNotContainsString('G-OURS', $out, 'our GA4 inline config must go too (no orphan)');
        $this->assertStringContainsString('GTM-OURS', $out, 'GTM kept — only GA4 competed');
        $this->assertStringContainsString('PIXOURS', $out, 'Pixel kept — only GA4 competed');
    }

    public function testAnalyticsGtmRemoved(): void
    {
        $theirs = '<head><script>var j=0;j.src="https://www.googletagmanager.com/gtm.js?id=GTM-THEIRS";</script></head>';
        $out = HeadBlockBuilder::trimBlockConflicts($this->block(), $theirs, 'cooperative');
        $this->assertStringNotContainsString('GTM-OURS', $out);
        $this->assertStringContainsString('G-OURS', $out, 'GA4 kept — only GTM competed');
        $this->assertStringContainsString('PIXOURS', $out, 'Pixel kept — only GTM competed');
    }

    public function testAnalyticsPixelRemoved(): void
    {
        $theirs = '<head><script>fbq(\'init\',\'PIXTHEIRS\');</script></head>';
        $out = HeadBlockBuilder::trimBlockConflicts($this->block(), $theirs, 'cooperative');
        $this->assertStringNotContainsString('PIXOURS', $out);
        $this->assertStringContainsString('G-OURS', $out, 'GA4 kept — only Pixel competed');
        $this->assertStringContainsString('GTM-OURS', $out, 'GTM kept — only Pixel competed');
    }

    public function testAnalyticsKeptWhenNoCompetitor(): void
    {
        $out = HeadBlockBuilder::trimBlockConflicts($this->block(), '<head><title>x</title></head>', 'cooperative');
        $this->assertSame($this->block(), $out, 'no analytics competitor → nothing trimmed');
    }

    /**
     * Section scoping: user content in custom_code_head (SECTION_CODE) is NEVER
     * trimmed, even when a competitor emits the same OG/GA4 — only our own
     * Schema/Social/AEO/Analytics sections are deduped.
     */
    public function testCustomCodeSectionNeverTrimmed(): void
    {
        HeadBlockBuilder::reset();
        HeadBlockBuilder::pushSection('social', '<meta property="og:title" content="OURS">');
        HeadBlockBuilder::pushSection('code',
            '<meta property="og:title" content="USERCODE"><script>window.x=1;gtag(\'config\',\'G-USER\');</script>');

        $theirs = '<head><meta property="og:title" content="THEIRS">'
                . '<script async src="https://www.googletagmanager.com/gtag/js?id=G-T"></script></head>';
        $m = new \ReflectionMethod(HeadBlockBuilder::class, 'trimOwnSections');
        if (PHP_VERSION_ID < 80500) {
            $m->setAccessible(true);
        }
        // After reset(), every section defaults to 'cooperative', so the Social
        // OG is deduped while Custom Code (never trimmed) survives.
        $m->invoke(null, $theirs);

        $block = HeadBlockBuilder::render('1.0');
        $this->assertStringNotContainsString('OURS', $block, 'our Social OG is deduped');
        $this->assertStringContainsString('USERCODE', $block, 'user custom-code OG must survive');
        $this->assertStringContainsString('G-USER', $block, 'user custom-code gtag must survive');
    }
}
