<?php
/**
 * AI Boost — ConflictPolicy
 *
 * Single source of truth that resolves, PER OUTPUT FEATURE, whether AI Boost
 * should TAKE OVER (emit / apply its own tag) or DEFER (step aside) when another
 * extension already emits the same signal.
 *
 * Two layers of settings decide it:
 *   - global  `conflict_mode`          → cooperative | aggressive | off
 *   - per-feature `conflict_<feature>` → inherit | takeover | defer
 *
 * A per-feature key of `inherit` (the default) hands the decision back to the
 * global mode:  cooperative → defer ;  aggressive | off → takeover.
 * An explicit `takeover` / `defer` on the feature wins over the global mode.
 *
 * Features: schema, og, sitemap, analytics, canonical, titles.
 *
 * NOTE: distinct from {@see ConflictManager}, which is the request-scoped
 * slot-ownership registry for AiBoost↔AiBoost plugin arbitration. ConflictPolicy
 * only answers "ours vs theirs" per output type and holds no per-request state.
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib;

defined('_JEXEC') or die;

final class ConflictPolicy
{
    /** Emit / apply AI Boost's own output for the feature. */
    public const MODE_TAKEOVER = 'takeover';

    /** Step aside and let the existing extension own the feature. */
    public const MODE_DEFER = 'defer';

    public const FEATURE_SCHEMA    = 'schema';
    public const FEATURE_OG        = 'og';
    public const FEATURE_SITEMAP   = 'sitemap';
    public const FEATURE_ANALYTICS = 'analytics';
    public const FEATURE_CANONICAL = 'canonical';
    public const FEATURE_TITLES    = 'titles';

    /**
     * Every output feature the user can steer individually. The Conflict Manager
     * UI and the per-feature manifest keys (`conflict_<feature>`) derive from this.
     *
     * @var array<int,string>
     */
    public const FEATURES = [
        self::FEATURE_SCHEMA,
        self::FEATURE_OG,
        self::FEATURE_SITEMAP,
        self::FEATURE_ANALYTICS,
        self::FEATURE_CANONICAL,
        self::FEATURE_TITLES,
    ];

    /**
     * Resolve the effective behaviour for a single output feature.
     *
     * @param  string              $feature  One of the FEATURE_* constants.
     * @param  array<string,mixed> $settings AI Boost settings array.
     * @return string                        self::MODE_TAKEOVER | self::MODE_DEFER
     */
    public static function effectiveMode(string $feature, array $settings): string
    {
        $override = strtolower(trim((string) ($settings['conflict_' . $feature] ?? 'inherit')));
        if ($override === self::MODE_TAKEOVER || $override === self::MODE_DEFER) {
            return $override;
        }

        // 'inherit' (or any unknown value) → fall back to the global mode.
        // cooperative → defer (trim our duplicate); aggressive | off → takeover.
        $global = strtolower(trim((string) ($settings['conflict_mode'] ?? 'cooperative')));

        return $global === 'cooperative' ? self::MODE_DEFER : self::MODE_TAKEOVER;
    }

    /** True when AI Boost should emit / apply its own output for $feature. */
    public static function isTakeover(string $feature, array $settings): bool
    {
        return self::effectiveMode($feature, $settings) === self::MODE_TAKEOVER;
    }

    /** True when AI Boost should step aside for $feature. */
    public static function isDefer(string $feature, array $settings): bool
    {
        return self::effectiveMode($feature, $settings) === self::MODE_DEFER;
    }

    /**
     * Bridge for the head/body builders that still speak the legacy
     * cooperative|aggressive vocabulary: defer → 'cooperative' (trim our
     * duplicate when a competitor emits the same signal), takeover →
     * 'aggressive' (always emit ours).
     *
     * @param  array<string,mixed> $settings
     */
    public static function legacyModeFor(string $feature, array $settings): string
    {
        return self::effectiveMode($feature, $settings) === self::MODE_DEFER ? 'cooperative' : 'aggressive';
    }

    /**
     * Decision for SET / serve-type features (canonical, titles, sitemap) that
     * OVERWRITE or OWN a resource rather than appending a deduplicatable tag, and
     * which historically had no cooperative auto-detection.
     *
     * They apply UNLESS the user EXPLICITLY defers them (`conflict_<feature>` =
     * 'defer', e.g. the Conflict Manager wizard's "defer to all"). The global
     * cooperative mode must NOT silently drop them, so an existing site that
     * upgrades — global cooperative, no per-feature key — keeps emitting them
     * exactly as before. Returns true = apply ours.
     *
     * @param  array<string,mixed> $settings
     */
    public static function shouldApplyExclusive(string $feature, array $settings): bool
    {
        return strtolower(trim((string) ($settings['conflict_' . $feature] ?? 'inherit'))) !== self::MODE_DEFER;
    }
}
