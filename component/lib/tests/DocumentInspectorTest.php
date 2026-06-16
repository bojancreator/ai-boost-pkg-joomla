<?php
/**
 * Unit tests for AiBoost\Lib\DocumentInspector::shouldSkip() once it routes
 * through the per-feature ConflictPolicy. Proves takeover always emits, defer
 * inspects+skips, and that the six output features are steered independently.
 *
 * Run: vendor/bin/phpunit component/lib/tests/DocumentInspectorTest.php
 *
 * @package     AiBoost\Lib\Tests
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Tests;

use AiBoost\Lib\DocumentInspector;
use PHPUnit\Framework\TestCase;

if (!defined('_JEXEC')) {
    define('_JEXEC', 1);
}

require_once dirname(__DIR__) . '/src/ConflictPolicy.php';
require_once dirname(__DIR__) . '/src/DocumentInspector.php';

final class DocumentInspectorTest extends TestCase
{
    /**
     * Minimal Joomla-document stub. $meta keyed "attribType|name"; $custom is a
     * list of raw head strings (JSON-LD, analytics loaders, link tags).
     *
     * @param array<string,string> $meta
     * @param array<int,string>    $custom
     */
    private function doc(array $meta = [], array $custom = []): object
    {
        return new class ($meta, $custom) {
            /** @param array<string,string> $meta @param array<int,string> $custom */
            public function __construct(private array $meta, private array $custom)
            {
            }

            public function getMetaData($name, $attribs = 'name')
            {
                return $this->meta[$attribs . '|' . $name] ?? '';
            }

            public function getHeadData()
            {
                return ['custom' => $this->custom, 'links' => [], 'scripts' => []];
            }
        };
    }

    private function orgDoc(): object
    {
        return $this->doc([], ['<script type="application/ld+json">{"@type":"Organization","name":"Theirs"}</script>']);
    }

    /** Global cooperative (default) → every feature defers → a present competitor is skipped. */
    public function testDeferInspectsAndSkipsWhenCompetitorPresent(): void
    {
        $this->assertTrue(
            DocumentInspector::shouldSkip($this->orgDoc(), DocumentInspector::SIG_SCHEMA_ORG, ['conflict_mode' => 'cooperative'])
        );
    }

    /** Per-feature takeover → never skip, even with the competitor present. */
    public function testTakeoverAlwaysEmitsEvenWithCompetitor(): void
    {
        $this->assertFalse(
            DocumentInspector::shouldSkip($this->orgDoc(), DocumentInspector::SIG_SCHEMA_ORG, ['conflict_schema' => 'takeover'])
        );
    }

    /** Global aggressive takes over everything regardless of competitors. */
    public function testGlobalAggressiveTakesOverAll(): void
    {
        $this->assertFalse(
            DocumentInspector::shouldSkip($this->orgDoc(), DocumentInspector::SIG_SCHEMA_ORG, ['conflict_mode' => 'aggressive'])
        );
    }

    /**
     * THE per-feature isolation case: schema set to takeover, OG left to defer
     * (inherit→cooperative). A doc carrying BOTH a competitor Organization and a
     * competitor og:title must emit our schema but skip our OG.
     */
    public function testFeaturesAreSteeredIndependently(): void
    {
        $doc = $this->doc(
            ['property|og:title' => 'Theirs'],
            ['<script type="application/ld+json">{"@type":"Organization","name":"Theirs"}</script>']
        );
        $settings = ['conflict_mode' => 'cooperative', 'conflict_schema' => 'takeover'];

        $this->assertFalse(DocumentInspector::shouldSkip($doc, DocumentInspector::SIG_SCHEMA_ORG, $settings), 'schema takeover → emit');
        $this->assertTrue(DocumentInspector::shouldSkip($doc, DocumentInspector::SIG_OG_TITLE, $settings), 'og defer → skip');
    }

    /** Analytics signatures resolve via conflict_analytics, not conflict_schema. */
    public function testAnalyticsFeatureRouting(): void
    {
        $doc = $this->doc([], ['<script async src="https://www.googletagmanager.com/gtag/js?id=G-THEIRS"></script>']);

        // analytics takeover → emit ours; schema (inherit→defer) is unrelated.
        $this->assertFalse(DocumentInspector::shouldSkip($doc, DocumentInspector::SIG_GA4, ['conflict_analytics' => 'takeover']));
        // analytics defer (global cooperative) → skip ours when GA4 already present.
        $this->assertTrue(DocumentInspector::shouldSkip($doc, DocumentInspector::SIG_GA4, ['conflict_mode' => 'cooperative']));
    }

    /** Canonical signature resolves via conflict_canonical. */
    public function testCanonicalFeatureRouting(): void
    {
        $doc = $this->doc([], ['<link rel="canonical" href="https://theirs/">']);

        $this->assertFalse(DocumentInspector::shouldSkip($doc, DocumentInspector::SIG_CANONICAL, ['conflict_canonical' => 'takeover']));
        $this->assertTrue(DocumentInspector::shouldSkip($doc, DocumentInspector::SIG_CANONICAL, ['conflict_canonical' => 'defer']));
    }

    /** The AEO ai-content-verified meta folds under the schema feature. */
    public function testAiMetaFoldsUnderSchema(): void
    {
        $doc = $this->doc(['name|ai-content-verified' => '1']);

        $this->assertTrue(DocumentInspector::shouldSkip($doc, DocumentInspector::SIG_AI_META_VERIFIED, ['conflict_schema' => 'defer']));
        $this->assertFalse(DocumentInspector::shouldSkip($doc, DocumentInspector::SIG_AI_META_VERIFIED, ['conflict_schema' => 'takeover']));
    }

    /** No document → nothing to inspect → never skip (even when deferring). */
    public function testNoDocNeverSkips(): void
    {
        $this->assertFalse(
            DocumentInspector::shouldSkip(null, DocumentInspector::SIG_SCHEMA_ORG, ['conflict_mode' => 'cooperative'])
        );
    }

    /** Defer but no competitor present → emit ours (byte-for-byte legacy behaviour). */
    public function testDeferWithoutCompetitorEmits(): void
    {
        $this->assertFalse(
            DocumentInspector::shouldSkip($this->doc(), DocumentInspector::SIG_SCHEMA_ORG, ['conflict_mode' => 'cooperative'])
        );
    }
}
