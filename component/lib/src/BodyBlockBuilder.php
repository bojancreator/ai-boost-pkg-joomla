<?php
/**
 * AI Boost — BodyBlockBuilder
 *
 * Sibling to HeadBlockBuilder for `<body>` injections. Consolidates every
 * AI Boost body and footer snippet (GTM noscript, Meta Pixel noscript,
 * custom body code, custom footer code) into ONE wrapper at the start of
 * `<body>` and ONE wrapper just before `</body>`, mirroring the Yoast /
 * GTM convention already used in `<head>`.
 *
 * Two render modes (task #384):
 *
 *   DEFAULT (Debug → "Hide comments" OFF — production-friendly + verbose):
 *     <body>
 *     <!-- AI Boost for Joomla - Start -->
 *     <!-- Google Tag Manager (noscript) -->
 *     <noscript>…</noscript>
 *     <!-- Meta Pixel (noscript) -->
 *     <noscript>…</noscript>
 *     <!-- Custom Body Code -->
 *     <!-- user supplied -->
 *     <!-- AI Boost for Joomla - End -->
 *     …
 *     <!-- AI Boost for Joomla - Start -->
 *     <!-- Custom Footer Code -->
 *     <!-- user supplied -->
 *     <!-- AI Boost for Joomla - End -->
 *     </body>
 *
 *   HIDE (Debug → "Hide comments" ON — minimal source view):
 *     <body>
 *     <!-- AI Boost for Joomla - Start -->
 *     <noscript>…</noscript>
 *     <noscript>…</noscript>
 *     <!-- user supplied body -->
 *     <!-- AI Boost for Joomla - End -->
 *     …
 *     <!-- AI Boost for Joomla - Start -->
 *     <!-- user supplied footer -->
 *     <!-- AI Boost for Joomla - End -->
 *     </body>
 *
 * Empty sections produce no wrapper at all (#376 no-empty-wrapper rule).
 *
 * All AI Boost plugins should call finalize() from their `onAfterRender`
 * handler; a static flag makes the first call do the work and the rest
 * no-ops — plugin order does not matter.
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

final class BodyBlockBuilder
{
    /** @var array<int,array{label:string,body:string}> snippets to inject after the opening <body> tag, in push order */
    private static array $body = [];

    /** @var array<int,array{label:string,body:string}> snippets to inject before the closing </body> tag, in push order */
    private static array $footer = [];

    private static bool $finalized = false;

    private static bool $hideComments = false;

    // ─── Accumulation API ─────────────────────────────────────────────────────

    /**
     * Queue a snippet to render immediately after the opening `<body>` tag.
     *
     * @param string $label Sub-section label (e.g. "GTM (noscript)") — surfaced as `<!-- {label} -->` when hide=false.
     * @param string $body  Raw HTML body (no AI Boost wrapper markers; per-feature inner comments are OK).
     */
    public static function pushBody(string $label, string $body): void
    {
        $body = trim($body);
        if ($body === '') {
            return;
        }
        self::$body[] = ['label' => trim($label), 'body' => $body];
    }

    /**
     * Queue a snippet to render immediately before the closing `</body>` tag.
     *
     * @param string $label Sub-section label — surfaced as `<!-- {label} -->` when hide=false.
     * @param string $body  Raw HTML body.
     */
    public static function pushFooter(string $label, string $body): void
    {
        $body = trim($body);
        if ($body === '') {
            return;
        }
        self::$footer[] = ['label' => trim($label), 'body' => $body];
    }

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
        self::$body          = [];
        self::$footer        = [];
        self::$finalized     = false;
        self::$hideComments  = false;
    }

    // ─── Render ──────────────────────────────────────────────────────────────

    /**
     * @param array<int,array{label:string,body:string}> $entries
     */
    private static function renderBlock(array $entries): string
    {
        if (empty($entries)) {
            return '';
        }

        $lines = ['<!-- AI Boost for Joomla - Start -->'];

        foreach ($entries as $entry) {
            if (!self::$hideComments && $entry['label'] !== '') {
                $lines[] = '<!-- ' . $entry['label'] . ' -->';
            }
            $lines[] = $entry['body'];
        }

        $lines[] = '<!-- AI Boost for Joomla - End -->';

        return implode("\n", $lines);
    }

    public static function renderBody(): string
    {
        return self::renderBlock(self::$body);
    }

    public static function renderFooter(): string
    {
        return self::renderBlock(self::$footer);
    }

    public static function hasContent(): bool
    {
        return !empty(self::$body) || !empty(self::$footer);
    }

    /**
     * Inject the consolidated body block (after `<body>`) and footer block
     * (before `</body>`). Idempotent across plugins.
     */
    public static function finalize(CMSApplication|ApplicationAdapter $app): void
    {
        if (self::$finalized) {
            return;
        }
        self::$finalized = true;

        if (!self::hasContent()) {
            return;
        }

        // Accept either the raw Joomla CMSApplication (legacy callers) or an
        // ApplicationAdapter (WP port + tests).
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

        $html = $app->getBody();
        if ($html === '') {
            return;
        }

        $changed = false;

        // ── Body block: splice immediately after the first `<body ...>` tag ──
        $bodyBlock = self::renderBody();
        if ($bodyBlock !== '' && preg_match('/<body\b[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            $tag    = $m[0][0];
            $offset = (int) $m[0][1];
            $insertAt = $offset + strlen($tag);
            $html = substr($html, 0, $insertAt)
                  . "\n" . $bodyBlock
                  . substr($html, $insertAt);
            $changed = true;
        }

        // ── Footer block: splice immediately before the first `</body>` tag ──
        $footerBlock = self::renderFooter();
        if ($footerBlock !== '' && preg_match('/<\/body\s*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
            $closeTag = $m[0][0];
            $offset   = (int) $m[0][1];
            $html = substr($html, 0, $offset)
                  . $footerBlock . "\n"
                  . $closeTag
                  . substr($html, $offset + strlen($closeTag));
            $changed = true;
        }

        if ($changed) {
            $app->setBody($html);
        }
    }
}
