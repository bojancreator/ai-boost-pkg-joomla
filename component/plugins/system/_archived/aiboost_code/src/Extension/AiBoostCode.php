<?php
/**
 * AI Boost — Code Manager & Social Snippets Plugin
 *
 * Orchestrator — delegates to service classes and injects code via onAfterRender.
 *
 * Free tier:
 *   - head_code         → injected before </head>
 *   - body_top_code     → injected after <body ...>
 *   - body_bottom_code  → injected before </body>
 *
 * Pro tier (valid license):
 *   - All Free features
 *   - Unlimited named snippets via subform (pro_snippets_list), each with:
 *       position (head/body_top/body_bottom), enable toggle, page targeting,
 *       user-state filtering (all/logged-in/guests)
 *   - Social Snippets library (Facebook Pixel, Google Ads, LinkedIn Insight,
 *     TikTok Pixel, Pinterest Base Tag) — built by SocialSnippetsBuilder
 *
 * Dev mode suppression:
 *   - When Joomla debug mode is ON and dev_mode_suppress=1 (default), ALL snippet
 *     output is skipped so tracking pixels do not fire in development.
 *
 * @package     AiBoost\Plugin\System\AiBoostCode
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostCode\Extension;

defined('_JEXEC') or die;

use AiBoost\Plugin\System\AiBoostCode\Service\SnippetTargetingService;
use AiBoost\Plugin\System\AiBoostCode\Service\SocialSnippetsBuilder;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;

class AiBoostCode extends CMSPlugin
{
    use \AiBoost\Lib\ProGate;

    protected $autoloadLanguage = true;

    /**
     * Collected code buckets, populated once per request and consumed in onAfterRender.
     *
     * @var array{head:string[], body_top:string[], body_bottom:string[]}
     */
    private array $buckets = [
        'head'        => [],
        'body_top'    => [],
        'body_bottom' => [],
    ];

    /** @var bool Whether buckets have been populated this request. */
    private bool $prepared = false;

    /**
     * onAfterRender — collect all active snippets and inject into rendered HTML.
     *
     * Single injection point for all snippet types avoids multiple passes over
     * the response body.
     */
    public function onAfterRender(): void
    {
        $app = Factory::getApplication();
        if (!$app->isClient('site')) {
            return;
        }

        // Only inject into HTML responses — skip JSON, XML, PDF, etc.
        $doc = $app->getDocument();
        if (!$doc || $doc->getType() !== 'html') {
            return;
        }

        // Dev mode suppression — skip all output when Joomla debug is on
        if ((int) $this->params->get('dev_mode_suppress', 1) && (int) $app->get('debug', 0)) {
            return;
        }

        if (!$this->prepared) {
            $this->collectSnippets();
        }

        if ($this->isEmpty()) {
            return;
        }

        $body = $app->getBody();
        if ($body === '') {
            return;
        }

        // Inject <head> snippets — before </head>
        if (!empty($this->buckets['head'])) {
            $injection = "\n" . implode("\n", $this->buckets['head']) . "\n";
            $body      = str_ireplace('</head>', $injection . '</head>', $body);
        }

        // Inject body-top snippets — immediately after <body ...>
        if (!empty($this->buckets['body_top'])) {
            $injection = "\n" . implode("\n", $this->buckets['body_top']) . "\n";
            $body      = preg_replace('/<body([^>]*)>/i', '<body$1>' . $injection, $body, 1);
        }

        // Inject body-bottom snippets — before </body>
        if (!empty($this->buckets['body_bottom'])) {
            $injection = "\n" . implode("\n", $this->buckets['body_bottom']) . "\n";
            $body      = str_ireplace('</body>', $injection . '</body>', $body);
        }

        $app->setBody($body);
    }

    // ── Private collection logic ──────────────────────────────────────────────

    /**
     * Populate the buckets from all configured snippet sources.
     * Called once per request, result cached in $this->buckets.
     */
    private function collectSnippets(): void
    {
        $this->prepared = true;

        // ── Free: global single-slot snippets ─────────────────────────────────
        $this->addToBucket('head',        trim((string) $this->params->get('head_code', '')));
        $this->addToBucket('body_top',    trim((string) $this->params->get('body_top_code', '')));
        $this->addToBucket('body_bottom', trim((string) $this->params->get('body_bottom_code', '')));

        // ── Pro: named multi-snippet list ─────────────────────────────────────
        if ($this->isProEnabled()) {
            $this->collectProSnippets();
            $this->collectSocialSnippets();
        }
    }

    /**
     * Collect Pro named snippets from the subform param (pro_snippets_list).
     * Applies SnippetTargetingService for page/user-state filtering.
     */
    private function collectProSnippets(): void
    {
        $raw = $this->params->get('pro_snippets_list');

        // Normalize: Joomla subform may store/return data as object, array, or JSON string
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (is_object($raw)) {
            $raw = (array) $raw;
        }
        if (!is_array($raw) || empty($raw)) {
            return;
        }

        $targeting = new SnippetTargetingService();

        foreach ($raw as $item) {
            if (is_object($item)) {
                $item = (array) $item;
            }
            if (!is_array($item)) {
                continue;
            }

            // Skip disabled rows
            if ((int) ($item['snippet_enabled'] ?? 1) === 0) {
                continue;
            }

            // Apply targeting rules
            if (!$targeting->shouldFire($item)) {
                continue;
            }

            $code     = trim((string) ($item['snippet_code'] ?? ''));
            $position = trim((string) ($item['snippet_position'] ?? 'head'));

            if ($code === '') {
                continue;
            }

            $this->addToBucket($position, $code);
        }
    }

    /**
     * Build and collect Social Snippet head tags via SocialSnippetsBuilder.
     */
    private function collectSocialSnippets(): void
    {
        $builder  = new SocialSnippetsBuilder($this->params);
        $snippets = $builder->buildHeadSnippets();

        foreach ($snippets as $snippet) {
            $this->addToBucket('head', $snippet);
        }
    }

    /**
     * Add a non-empty code string to the named bucket.
     * Unknown bucket names fall through to 'head' as a safe default.
     */
    private function addToBucket(string $position, string $code): void
    {
        if ($code === '') {
            return;
        }
        $key = match ($position) {
            'body_top'    => 'body_top',
            'body_bottom' => 'body_bottom',
            default       => 'head',
        };
        $this->buckets[$key][] = $code;
    }

    /** Return true if all buckets are empty (nothing to inject). */
    private function isEmpty(): bool
    {
        return empty($this->buckets['head'])
            && empty($this->buckets['body_top'])
            && empty($this->buckets['body_bottom']);
    }
}
