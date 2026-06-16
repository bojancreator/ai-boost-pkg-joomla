<?php
/**
 * AI Boost — DocumentInspector
 *
 * Helper used by every AI Boost system plugin to support **Cooperative**
 * conflict-resolution mode (Task #362).
 *
 * The plugin asks the inspector: "before I inject <signature> into the
 * <head>, is anything already there?" — if yes, the plugin silently
 * skips its injection so we never produce duplicate SEO tags.
 *
 * The decision is per OUTPUT FEATURE via {@see ConflictPolicy}: each signature
 * maps to a feature (og / schema / analytics / canonical) whose effective mode
 * is resolved from the per-feature override `conflict_<feature>` or, when that is
 * `inherit`, the global `conflict_mode`:
 *   - defer (← cooperative) → inspector inspects existing head content and may skip
 *   - takeover (← aggressive | off) → inspector always returns false (we always inject)
 *
 * Inspection sources (read-only on the Joomla\CMS\Document\HtmlDocument):
 *   - getMetaData()          → meta tags set by Joomla core / other plugins
 *   - getHeadData()['custom']→ raw HTML strings other plugins pushed via
 *                              addCustomTag (covers JSON-LD <script>,
 *                              gtag/GTM <script>, og: <meta> tags, etc.)
 *   - getHeadData()['links'] → <link rel="canonical"|"alternate"> etc.
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib;

defined('_JEXEC') or die;

final class DocumentInspector
{
    public const SIG_OG_TITLE        = 'og:title';
    public const SIG_OG_DESCRIPTION  = 'og:description';
    public const SIG_OG_IMAGE        = 'og:image';
    public const SIG_TWITTER_CARD    = 'twitter:card';
    public const SIG_CANONICAL       = 'canonical';
    public const SIG_SCHEMA_ORG      = 'application/ld+json';
    public const SIG_GA4             = 'googletagmanager.com/gtag/js';
    public const SIG_GTM             = 'googletagmanager.com/gtm.js';
    public const SIG_META_PIXEL      = 'connect.facebook.net';
    public const SIG_AI_META_VERIFIED = 'ai-content-verified';

    /**
     * Returns true when the plugin should SKIP its injection because
     * another extension has already provided the same signal AND
     * conflict_mode = cooperative.
     *
     * @param  object|null         $doc       Joomla\CMS\Document\Document (typed loosely so the file
     *                                        loads in any Joomla version).
     * @param  string              $signature One of the SIG_* constants.
     * @param  array<string,mixed> $settings  AI Boost settings array.
     */
    public static function shouldSkip(?object $doc, string $signature, array $settings): bool
    {
        // Resolve the per-feature conflict policy for this signature. Only DEFER
        // inspects the existing head and may skip; TAKEOVER always emits ours.
        // 'inherit' on the feature falls back to the global conflict_mode, so a
        // legacy settings blob (cooperative everywhere) behaves exactly as before.
        if (ConflictPolicy::effectiveMode(self::featureForSignature($signature), $settings) !== ConflictPolicy::MODE_DEFER) {
            return false; // takeover → always emit ours
        }
        if (!$doc) {
            return false;
        }

        // 1. Meta-tag signatures: ask Joomla directly (covers core OG + many plugins).
        if (in_array($signature, [self::SIG_OG_TITLE, self::SIG_OG_DESCRIPTION, self::SIG_OG_IMAGE], true)) {
            try {
                $existing = method_exists($doc, 'getMetaData') ? (string) $doc->getMetaData($signature, 'property') : '';
                if ($existing !== '') {
                    return true;
                }
            } catch (\Throwable $e) {
                // fall through to head-data scan
            }
        }
        if ($signature === self::SIG_TWITTER_CARD) {
            try {
                $existing = method_exists($doc, 'getMetaData') ? (string) $doc->getMetaData('twitter:card', 'name') : '';
                if ($existing !== '') {
                    return true;
                }
            } catch (\Throwable $e) {}
        }
        if ($signature === self::SIG_AI_META_VERIFIED) {
            try {
                $existing = method_exists($doc, 'getMetaData') ? (string) $doc->getMetaData('ai-content-verified', 'name') : '';
                if ($existing !== '') {
                    return true;
                }
            } catch (\Throwable $e) {}
        }

        // 2. Custom-tag / link / script scan via getHeadData().
        $blob = '';
        try {
            if (method_exists($doc, 'getHeadData')) {
                $hd = $doc->getHeadData();
                if (is_array($hd)) {
                    foreach (($hd['custom'] ?? []) as $line) {
                        $blob .= "\n" . (string) $line;
                    }
                    foreach (($hd['links'] ?? []) as $href => $linkAttrs) {
                        $blob .= "\n" . (string) $href . ' ' . json_encode($linkAttrs);
                    }
                    foreach (($hd['scripts'] ?? []) as $src => $scriptAttrs) {
                        $blob .= "\n" . (string) $src . ' ' . (is_array($scriptAttrs) ? json_encode($scriptAttrs) : (string) $scriptAttrs);
                    }
                }
            }
        } catch (\Throwable $e) {
            return false;
        }

        if ($blob === '') {
            return false;
        }

        // Substring/regex check per signature.
        switch ($signature) {
            case self::SIG_OG_TITLE:
            case self::SIG_OG_DESCRIPTION:
            case self::SIG_OG_IMAGE:
                return (bool) preg_match('/property\s*=\s*["\']' . preg_quote($signature, '/') . '["\']/i', $blob);
            case self::SIG_TWITTER_CARD:
                return (bool) preg_match('/name\s*=\s*["\']twitter:card["\']/i', $blob);
            case self::SIG_CANONICAL:
                return (bool) preg_match('/rel\s*=\s*["\']canonical["\']/i', $blob);
            case self::SIG_SCHEMA_ORG:
                // Only block when an Organization OR LocalBusiness JSON-LD block is already present.
                if (!str_contains(strtolower($blob), 'application/ld+json')) {
                    return false;
                }
                return (bool) preg_match('/"@type"\s*:\s*"(Organization|LocalBusiness|Hotel|Restaurant|MedicalBusiness|LegalService|EducationalOrganization|Dentist|RealEstateAgent|NewsMediaOrganization)"/i', $blob);
            case self::SIG_GA4:
                return str_contains($blob, 'googletagmanager.com/gtag/js') || (bool) preg_match("/gtag\s*\(\s*['\"]config['\"]/", $blob);
            case self::SIG_GTM:
                return str_contains($blob, 'googletagmanager.com/gtm.js');
            case self::SIG_META_PIXEL:
                return str_contains($blob, 'connect.facebook.net') || (bool) preg_match("/fbq\s*\(\s*['\"]init['\"]/", $blob);
            case self::SIG_AI_META_VERIFIED:
                return (bool) preg_match('/name\s*=\s*["\']ai-content-verified["\']/i', $blob);
        }

        return false;
    }

    /**
     * Map a SIG_* signature to its ConflictPolicy output feature. OG + Twitter
     * share the 'og' feature; GA4/GTM/Pixel share 'analytics'; the AEO
     * ai-content-verified meta folds under 'schema' (it has no dedicated
     * competitor and rides with structured-data takeover/defer).
     */
    private static function featureForSignature(string $signature): string
    {
        switch ($signature) {
            case self::SIG_OG_TITLE:
            case self::SIG_OG_DESCRIPTION:
            case self::SIG_OG_IMAGE:
            case self::SIG_TWITTER_CARD:
                return ConflictPolicy::FEATURE_OG;
            case self::SIG_GA4:
            case self::SIG_GTM:
            case self::SIG_META_PIXEL:
                return ConflictPolicy::FEATURE_ANALYTICS;
            case self::SIG_CANONICAL:
                return ConflictPolicy::FEATURE_CANONICAL;
            case self::SIG_SCHEMA_ORG:
            case self::SIG_AI_META_VERIFIED:
            default:
                return ConflictPolicy::FEATURE_SCHEMA;
        }
    }
}
