<?php
/**
 * Unit tests for AiBoost\Lib\ConflictPolicy — the pure per-feature resolver that
 * decides takeover vs defer from the global conflict_mode + per-feature overrides.
 * No Joomla/DB needed.
 *
 * Run: vendor/bin/phpunit component/lib/tests/ConflictPolicyTest.php
 *
 * @package     AiBoost\Lib\Tests
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Tests;

use AiBoost\Lib\ConflictPolicy;
use PHPUnit\Framework\TestCase;

if (!defined('_JEXEC')) {
    define('_JEXEC', 1);
}

require_once dirname(__DIR__) . '/src/ConflictPolicy.php';

final class ConflictPolicyTest extends TestCase
{
    /** Empty settings → global default (cooperative) → every feature defers. */
    public function testDefaultsToDeferWhenNothingSet(): void
    {
        foreach (ConflictPolicy::FEATURES as $feature) {
            $this->assertSame(
                ConflictPolicy::MODE_DEFER,
                ConflictPolicy::effectiveMode($feature, []),
                "$feature should default to defer (global cooperative)"
            );
        }
    }

    /** inherit + global cooperative → defer; inherit + aggressive|off → takeover. */
    public function testInheritFollowsGlobalMode(): void
    {
        $this->assertSame(ConflictPolicy::MODE_DEFER, ConflictPolicy::effectiveMode('schema', ['conflict_mode' => 'cooperative']));
        $this->assertSame(ConflictPolicy::MODE_TAKEOVER, ConflictPolicy::effectiveMode('schema', ['conflict_mode' => 'aggressive']));
        $this->assertSame(ConflictPolicy::MODE_TAKEOVER, ConflictPolicy::effectiveMode('schema', ['conflict_mode' => 'off']));

        // Explicit inherit behaves the same as an unset per-feature key.
        $this->assertSame(
            ConflictPolicy::MODE_TAKEOVER,
            ConflictPolicy::effectiveMode('og', ['conflict_mode' => 'aggressive', 'conflict_og' => 'inherit'])
        );
    }

    /** An explicit per-feature override beats the global mode either way. */
    public function testPerFeatureOverrideWinsOverGlobal(): void
    {
        // Global says defer (cooperative), but schema is forced to takeover.
        $settings = ['conflict_mode' => 'cooperative', 'conflict_schema' => 'takeover'];
        $this->assertSame(ConflictPolicy::MODE_TAKEOVER, ConflictPolicy::effectiveMode('schema', $settings));
        // Sibling features still inherit the global defer.
        $this->assertSame(ConflictPolicy::MODE_DEFER, ConflictPolicy::effectiveMode('og', $settings));

        // Global says takeover (aggressive), but canonical is forced to defer.
        $settings = ['conflict_mode' => 'aggressive', 'conflict_canonical' => 'defer'];
        $this->assertSame(ConflictPolicy::MODE_DEFER, ConflictPolicy::effectiveMode('canonical', $settings));
        $this->assertSame(ConflictPolicy::MODE_TAKEOVER, ConflictPolicy::effectiveMode('titles', $settings));
    }

    /** Values are case/space tolerant; junk falls back to inherit→global. */
    public function testNormalisationAndUnknownValues(): void
    {
        $this->assertSame(ConflictPolicy::MODE_TAKEOVER, ConflictPolicy::effectiveMode('schema', ['conflict_schema' => '  TAKEOVER ']));
        $this->assertSame(ConflictPolicy::MODE_DEFER, ConflictPolicy::effectiveMode('schema', ['conflict_schema' => 'DEFER']));
        // Unknown per-feature value → treated as inherit → global default cooperative → defer.
        $this->assertSame(ConflictPolicy::MODE_DEFER, ConflictPolicy::effectiveMode('schema', ['conflict_schema' => 'banana']));
        // Unknown global value → not 'cooperative' → takeover.
        $this->assertSame(ConflictPolicy::MODE_TAKEOVER, ConflictPolicy::effectiveMode('schema', ['conflict_mode' => 'whatever']));
    }

    public function testIsTakeoverAndIsDeferAreComplementary(): void
    {
        $settings = ['conflict_mode' => 'cooperative', 'conflict_analytics' => 'takeover'];
        $this->assertTrue(ConflictPolicy::isTakeover('analytics', $settings));
        $this->assertFalse(ConflictPolicy::isDefer('analytics', $settings));
        $this->assertTrue(ConflictPolicy::isDefer('schema', $settings));
        $this->assertFalse(ConflictPolicy::isTakeover('schema', $settings));
    }

    /** The builder bridge maps defer→cooperative and takeover→aggressive. */
    public function testLegacyModeForBridgesToBuilderVocabulary(): void
    {
        $this->assertSame('cooperative', ConflictPolicy::legacyModeFor('schema', ['conflict_mode' => 'cooperative']));
        $this->assertSame('aggressive', ConflictPolicy::legacyModeFor('schema', ['conflict_mode' => 'aggressive']));
        $this->assertSame('aggressive', ConflictPolicy::legacyModeFor('schema', ['conflict_schema' => 'takeover', 'conflict_mode' => 'cooperative']));
        $this->assertSame('cooperative', ConflictPolicy::legacyModeFor('og', ['conflict_og' => 'defer', 'conflict_mode' => 'aggressive']));
    }

    /**
     * SET / serve-type features (canonical, titles, sitemap) apply unless the
     * user EXPLICITLY defers them — the global cooperative default must never
     * silently drop them (upgrade-safety).
     */
    public function testShouldApplyExclusiveOnlyStopsOnExplicitDefer(): void
    {
        // Upgrade case: global cooperative / nothing set → still applies.
        $this->assertTrue(ConflictPolicy::shouldApplyExclusive('canonical', ['conflict_mode' => 'cooperative']));
        $this->assertTrue(ConflictPolicy::shouldApplyExclusive('titles', []));
        // inherit and takeover apply; only an explicit 'defer' skips.
        $this->assertTrue(ConflictPolicy::shouldApplyExclusive('canonical', ['conflict_canonical' => 'inherit']));
        $this->assertTrue(ConflictPolicy::shouldApplyExclusive('canonical', ['conflict_canonical' => 'takeover']));
        $this->assertFalse(ConflictPolicy::shouldApplyExclusive('canonical', ['conflict_canonical' => 'defer']));
        // The global mode never forces a SET-type to defer on its own.
        $this->assertTrue(ConflictPolicy::shouldApplyExclusive('sitemap', ['conflict_mode' => 'cooperative']));
        $this->assertFalse(ConflictPolicy::shouldApplyExclusive('sitemap', ['conflict_sitemap' => 'defer', 'conflict_mode' => 'aggressive']));
    }
}
