<?php
/**
 * AI Boost — HeadBlockBuilder
 *
 * Request-scoped accumulator that collects HTML emitted by every AI Boost
 * plugin during `onBeforeCompileHead` and renders ONE consolidated block —
 * Yoast / GTM style — wrapped by a single outer marker pair, placed once in
 * `<head>` just before the closing tag.
 *
 * Two render modes (task #382 → revised #384):
 *
 *   DEFAULT (Debug → "Hide comments" OFF — production-friendly + verbose):
 *     <!-- AI Boost for Joomla v0.34.0 — https://aiboostnow.com - Start -->
 *     <!-- Also emitted via Joomla head: canonical, title template -->
 *     <!-- Skipped: OpenGraph & Twitter — already emitted by 4SEO -->
 *
 *     <!-- Schema.org -->
 *     …JSON-LD blocks…
 *     <!-- OpenGraph & Twitter -->
 *     …og:* / twitter:* meta tags…
 *     <!-- AEO -->
 *     …ai-content-verified / markdown discovery…
 *     <!-- Analytics -->
 *     …GSC verification / GTM / GA4 / Meta Pixel…
 *     <!-- Custom Code -->
 *     …user-defined head HTML…
 *
 *     <!-- AI Boost for Joomla v0.34.0 - End -->
 *
 *   HIDE (Debug → "Hide comments" ON — minimal source view):
 *     <!-- AI Boost for Joomla - Start -->
 *     …schema bodies, OpenGraph tags, AEO meta, analytics, custom code…
 *     <!-- AI Boost for Joomla - End -->
 *
 * Excluded from the block (by design — they need Joomla's dedicated
 * head streams so templates and other extensions can dedup/override):
 *   - `<link rel="canonical">`            via addHeadLink()
 *   - `<link rel="alternate" hreflang>`   via addHeadLink()
 *   - Tags written via setMetaData()
 *
 * Such tags should still call noteNative() so the consolidated header
 * comment can list them.
 *
 * Cooperative-mode skips (4SEO / Sh404SEF / EFSEO already emitting a tag)
 * should call noteSkip() — surfaced in default mode, hidden when hide_comments.
 *
 * The single write point is finalize(): it runs in onAfterRender and
 * splices the rendered block in front of </head>. All AI Boost plugins
 * call finalize() in their onAfterRender; a static flag makes the first
 * call do the work and the rest no-ops — plugin order does not matter.
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib;

defined('_JEXEC') or defined('ABSPATH') or die;

use AiBoost\Lib\Cms\ApplicationAdapter;
use AiBoost\Lib\Cms\Joomla\JoomlaApplicationAdapter;
use Joomla\CMS\Application\CMSApplication;

final class HeadBlockBuilder
{
    public const SECTION_SCHEMA    = 'schema';
    public const SECTION_SOCIAL    = 'social';
    public const SECTION_AEO       = 'aeo';
    public const SECTION_ANALYTICS = 'analytics';
    public const SECTION_CODE      = 'code';

    /** Fixed render order — Schema first (most important), Custom Code last. */
    private const ORDER = [
        self::SECTION_SCHEMA,
        self::SECTION_SOCIAL,
        self::SECTION_AEO,
        self::SECTION_ANALYTICS,
        self::SECTION_CODE,
    ];

    private const LABELS = [
        self::SECTION_SCHEMA    => 'Schema.org',
        self::SECTION_SOCIAL    => 'OpenGraph & Twitter',
        self::SECTION_AEO       => 'AEO',
        self::SECTION_ANALYTICS => 'Analytics',
        self::SECTION_CODE      => 'Custom Code',
    ];

    /** @var array<string,string[]> section => list of body strings (already inner-HTML, no markers) */
    private static array $sections = [];

    /** @var string[] human-readable names of tags emitted through Joomla native APIs */
    private static array $nativeTags = [];

    /** @var array<int,array{section:string,reason:string}> */
    private static array $skipped = [];

    private static bool $finalized = false;

    /** Request-scoped hide-comments flag. Any plugin can set it from onBeforeCompileHead. */
    private static bool $hideComments = false;

    // ─── Accumulation API (called from plugins' onBeforeCompileHead) ──────────

    public static function pushSection(string $section, string $body): void
    {
        $body = trim($body);
        if ($body === '' || !isset(self::LABELS[$section])) {
            return;
        }
        self::$sections[$section][] = $body;
    }

    public static function noteNative(string $name): void
    {
        $name = trim($name);
        if ($name === '' || in_array($name, self::$nativeTags, true)) {
            return;
        }
        self::$nativeTags[] = $name;
    }

    public static function noteSkip(string $section, string $reason): void
    {
        if (!isset(self::LABELS[$section])) {
            return;
        }
        self::$skipped[] = ['section' => $section, 'reason' => trim($reason)];
    }

    /**
     * Toggle the "hide comments" render mode. Last-write-wins per request —
     * all plugins read the same `hide_comments` setting so the value is
     * consistent regardless of which plugin calls this first.
     */
    public static function setHideComments(bool $hide): void
    {
        self::$hideComments = $hide;
    }

    public static function isHideComments(): bool
    {
        return self::$hideComments;
    }

    public static function reset(): void
    {
        self::$sections      = [];
        self::$nativeTags    = [];
        self::$skipped       = [];
        self::$finalized     = false;
        self::$hideComments  = false;
    }

    // ─── Render & inject ──────────────────────────────────────────────────────

    public static function hasContent(): bool
    {
        return !empty(self::$sections) || !empty(self::$nativeTags) || !empty(self::$skipped);
    }

    /**
     * Build the consolidated head block. Returns '' when nothing was pushed.
     *
     * @param string $version Plugin version string (rendered in default-mode header only).
     */
    public static function render(string $version): string
    {
        if (!self::hasContent()) {
            return '';
        }

        $lines = [];

        // Outer pair is ALWAYS the minimal form — version + URL would
        // duplicate information already visible in `<meta name="generator">`
        // and bloat View Source. Sub-section labels + Also-emitted + Skipped
        // lines carry all the diagnostic value and remain visible by default.
        $lines[] = '<!-- AI Boost for Joomla - Start -->';

        if (!self::$hideComments) {
            if (!empty(self::$nativeTags)) {
                $lines[] = '<!-- Also emitted via Joomla head: ' . implode(', ', self::$nativeTags) . ' -->';
            }

            foreach (self::$skipped as $skip) {
                $label  = self::LABELS[$skip['section']];
                $reason = $skip['reason'] !== '' ? ' — ' . $skip['reason'] : '';
                $lines[] = '<!-- Skipped: ' . $label . $reason . ' -->';
            }

            foreach (self::ORDER as $section) {
                if (empty(self::$sections[$section])) {
                    continue;
                }
                $lines[] = '';
                $lines[] = '<!-- ' . self::LABELS[$section] . ' -->';
                foreach (self::$sections[$section] as $body) {
                    $lines[] = $body;
                }
            }
        } else {
            foreach (self::ORDER as $section) {
                if (empty(self::$sections[$section])) {
                    continue;
                }
                foreach (self::$sections[$section] as $body) {
                    $lines[] = $body;
                }
            }
        }

        $lines[] = '<!-- AI Boost for Joomla - End -->';

        return implode("\n", $lines);
    }

    /**
     * Inject the consolidated block into the rendered page body immediately
     * before `</head>`. Idempotent — only the first call per request rewrites
     * the body; subsequent calls are no-ops. Plugin order does not matter.
     *
     * Safe to call from any onAfterRender handler.
     */
    public static function finalize(CMSApplication|ApplicationAdapter $app, string $version): void
    {
        if (self::$finalized) {
            return;
        }
        self::$finalized = true;

        if (!self::hasContent()) {
            return;
        }

        // Accept either the raw Joomla CMSApplication (legacy callers) or an
        // ApplicationAdapter (WP port + tests). Wrap CMSApplication so the
        // body manipulation below runs through the adapter interface only.
        if ($app instanceof CMSApplication) {
            $app = new JoomlaApplicationAdapter($app);
        }

        try {
            if (!$app->isSite()) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }

        $body = $app->getBody();
        if ($body === '') {
            return;
        }

        // Locate `</head>` case-insensitively WITHOUT preg_replace — the
        // accumulated block contains raw user-supplied head HTML (custom code,
        // verification tokens, GTM IDs, etc.) which may legitimately include
        // `$1`, `\1`, backslashes, or other regex back-reference syntax.
        // preg_replace would interpret those tokens in the replacement string
        // and silently corrupt the output. Substring splice is byte-safe.
        if (!preg_match('/<\/head\s*>/i', $body, $m, PREG_OFFSET_CAPTURE)) {
            return;
        }
        $closeTag = $m[0][0];
        $offset   = (int) $m[0][1];

        $block = self::render($version);
        if ($block === '') {
            return;
        }

        // Task #486 — let registered integration bridges mutate the
        // consolidated head block (e.g. inject extra <meta> rows or strip
        // duplicates) right before splice. Listeners receive the rendered
        // HTML string under the 'html' key. Falls back to original on any
        // unexpected shape so a buggy bridge can never blank the head.
        if (class_exists(\AiBoost\Lib\Integration\FilterDispatcher::class)) {
            $filtered = \AiBoost\Lib\Integration\FilterDispatcher::dispatch(
                \AiBoost\Lib\Integration\Sdk::EVENT_FILTER_HEAD_OUTPUT,
                ['html' => $block]
            );
            if (isset($filtered['html']) && is_string($filtered['html']) && $filtered['html'] !== '') {
                $block = $filtered['html'];
            }
        }

        $newBody = substr($body, 0, $offset)
                 . $block . "\n"
                 . $closeTag
                 . substr($body, $offset + strlen($closeTag));

        $app->setBody($newBody);
    }
}
