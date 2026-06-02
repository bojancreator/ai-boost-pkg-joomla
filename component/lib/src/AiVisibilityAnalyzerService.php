<?php
/**
 * AI Boost — AiVisibilityAnalyzerService
 * Checks a site's AI discoverability signals using Joomla's HTTP client:
 *   - llms.txt presence and content
 *   - robots.txt AI crawler directives (GPTBot, ClaudeBot, PerplexityBot, etc.)
 *   - IndexNow key file
 *   - AI-specific meta tags in HTML <head>
 *   - AI Boost AEO plugin status
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib;

defined('_JEXEC') or defined('ABSPATH') or die;

use Joomla\CMS\Http\HttpFactory;
use Joomla\Database\DatabaseInterface;
use Joomla\Http\Http;
use Joomla\Registry\Registry;

class AiVisibilityAnalyzerService
{
    private const AI_CRAWLERS = [
        'GPTBot'             => 'OpenAI ChatGPT training crawler',
        'ClaudeBot'          => 'Anthropic Claude crawler',
        'PerplexityBot'      => 'Perplexity AI crawler',
        'Google-Extended'    => 'Google Gemini/AI Overview training crawler',
        'Bytespider'         => 'TikTok/ByteDance AI crawler',
        'Applebot-Extended'  => 'Apple AI features crawler',
        'cohere-ai'          => 'Cohere AI training crawler',
        'Meta-ExternalAgent' => 'Meta AI crawler',
    ];

    /** Points per passing check (for the main score calculation) */
    private const WEIGHTS = [
        'llms_txt'      => 25,
        'robots_ai'     => 20,
        'indexnow'      => 15,
        'ai_meta_tags'  => 10,
        'og_image'      => 10,
        'canonical'     => 10,
        'schema_jsonld' => 10,
    ];

    private DatabaseInterface $db;
    private Http $http;

    public function __construct(DatabaseInterface $db, ?Http $http = null)
    {
        $this->db   = $db;
        $this->http = $http ?? \AiBoost\Lib\Cms\AdapterRegistry::http()->getClient(['userAgent' => 'AiBoost-Analyzer/1.0']);
    }

    /**
     * Run a full AI visibility analysis for the given base URL.
     *
     * @return array{score: int, baseUrl: string, checks: list<array>, error: string|null}
     */
    public function analyze(string $baseUrl): array
    {
        $baseUrl = rtrim(trim($baseUrl), '/');
        if ($baseUrl === '') {
            return ['score' => 0, 'baseUrl' => $baseUrl, 'checks' => [], 'error' => 'No base URL provided.'];
        }

        $scheme = strtolower((string) parse_url($baseUrl, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return ['score' => 0, 'baseUrl' => $baseUrl, 'checks' => [], 'error' => 'Only http and https URLs are supported.'];
        }

        $checks = [];

        // ── llms.txt ───────────────────────────────────────────────────────
        $checks[] = $this->checkLlmsTxt($baseUrl);

        // ── robots.txt AI directives ───────────────────────────────────────
        $robotsContent = $this->fetchBody($baseUrl . '/robots.txt');
        $checks[]      = $this->checkRobotsAiDirectives($baseUrl, $robotsContent);
        foreach ($this->checkIndividualCrawlers($robotsContent) as $c) {
            $checks[] = $c;
        }

        // ── IndexNow key file ──────────────────────────────────────────────
        $checks[] = $this->checkIndexNow($baseUrl);

        // ── HTML <head> AI signals + X-Robots-Tag header ──────────────────
        [$homepageHtml, $homepageHeaders] = $this->fetchBodyAndHeaders($baseUrl . '/');
        if ($homepageHtml !== null) {
            $doc = $this->parseHtml($homepageHtml);
            $checks[] = $this->checkAiMetaTags($doc);
            $checks[] = $this->checkOgImage($doc);
            $checks[] = $this->checkCanonical($doc);
            $checks[] = $this->checkSchemaJsonLd($doc);
            $checks[] = $this->checkXRobotsTag($homepageHeaders);
        } else {
            $checks[] = $this->makeCheck('html_fetch', 'Homepage HTML', false, 'warning',
                'Could not fetch homepage HTML. AI signal checks for meta tags and structured data were skipped.');
        }

        // ── AEO Plugin status (DB) ─────────────────────────────────────────
        $checks[] = $this->checkAeoPlugin();

        $score = $this->calculateScore($checks);

        return [
            'score'   => $score,
            'baseUrl' => $baseUrl,
            'checks'  => $checks,
            'error'   => null,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INDIVIDUAL CHECKS
    // ─────────────────────────────────────────────────────────────────────────

    private function checkLlmsTxt(string $baseUrl): array
    {
        $status  = $this->fetchStatus($baseUrl . '/llms.txt');
        $pass    = $status >= 200 && $status < 300;

        if ($pass) {
            $content = $this->fetchBody($baseUrl . '/llms.txt');
            if ($content !== null && trim($content) !== '') {
                return $this->makeCheck('llms_txt', 'llms.txt', true, 'pass',
                    'llms.txt found and has content. AI engines can discover your site\'s AI instructions.');
            }
            return $this->makeCheck('llms_txt', 'llms.txt', false, 'warning',
                'llms.txt exists but appears to be empty. Add content to guide AI crawlers.');
        }

        return $this->makeCheck('llms_txt', 'llms.txt', false, 'error',
            "llms.txt not found at {$baseUrl}/llms.txt (HTTP {$status}). Enable the AI Boost AEO plugin to generate llms.txt automatically.");
    }

    private function checkRobotsAiDirectives(string $baseUrl, ?string $robotsContent): array
    {
        if ($robotsContent === null) {
            return $this->makeCheck('robots_ai', 'robots.txt (AI Crawlers)', false, 'warning',
                'robots.txt not accessible. Cannot verify AI crawler directives.');
        }

        $blockedCrawlers = [];
        foreach (array_keys(self::AI_CRAWLERS) as $bot) {
            if ($this->isBotExplicitlyBlocked($robotsContent, $bot)) {
                $blockedCrawlers[] = $bot;
            }
        }

        if (!empty($blockedCrawlers)) {
            return $this->makeCheck('robots_ai', 'robots.txt (AI Crawlers)', false, 'warning',
                'Some AI crawlers are blocked in robots.txt: ' . implode(', ', $blockedCrawlers) . '. '
                . 'Remove Disallow rules for these bots to improve AI discoverability.');
        }

        return $this->makeCheck('robots_ai', 'robots.txt (AI Crawlers)', true, 'pass',
            'No AI crawlers are blocked in robots.txt. AI search engines can freely crawl your content.');
    }

    private function checkIndividualCrawlers(?string $robotsContent): array
    {
        if ($robotsContent === null) {
            return [];
        }

        $checks = [];
        foreach (self::AI_CRAWLERS as $bot => $description) {
            $pattern  = '/User-agent:\s*' . preg_quote($bot, '/') . '/i';
            $hasRule  = preg_match($pattern, $robotsContent) === 1;
            $isAllowed = !$this->isBotExplicitlyBlocked($robotsContent, $bot);
            $id = 'crawler_' . preg_replace('/[^a-z0-9]/', '_', strtolower($bot));

            $checks[] = $this->makeCheck(
                $id,
                $bot . ' (' . $description . ')',
                $isAllowed,
                $hasRule ? 'pass' : 'info',
                $hasRule
                    ? ($isAllowed
                        ? "{$bot} has an explicit Allow rule in robots.txt."
                        : "{$bot} is explicitly blocked in robots.txt.")
                    : "{$bot} has no explicit rule — follows the default (usually allow). Consider adding \"User-agent: {$bot}\\nAllow: /\" for clarity."
            );
        }

        return $checks;
    }

    private function isBotExplicitlyBlocked(string $robots, string $bot): bool
    {
        preg_match_all(
            '/User-agent:\s*' . preg_quote($bot, '/') . '.*?(?=User-agent:|$)/si',
            $robots,
            $m
        );
        foreach (($m[0] ?? []) as $block) {
            if (preg_match('/Disallow:\s*\/\s*$/m', $block)) {
                return true;
            }
        }
        return false;
    }

    private function checkIndexNow(string $baseUrl): array
    {
        $settingsKey = $this->getIndexNowKey();
        if ($settingsKey !== '') {
            $keyUrl  = $baseUrl . '/' . $settingsKey . '.txt';
            $status  = $this->fetchStatus($keyUrl);
            if ($status >= 200 && $status < 300) {
                return $this->makeCheck('indexnow', 'IndexNow Key File', true, 'pass',
                    "IndexNow key file found at {$keyUrl}. New content is submitted to Bing/Yandex/others automatically.");
            }
            return $this->makeCheck('indexnow', 'IndexNow Key File', false, 'warning',
                "IndexNow key is configured in AI Boost but the key file was not found (HTTP {$status}). Enable the AEO plugin to serve the key file.");
        }

        return $this->makeCheck('indexnow', 'IndexNow Key File', false, 'error',
            'No IndexNow key configured in AI Boost settings. Configure an IndexNow key in the AEO tab to enable instant URL submission to AI-powered search engines.');
    }

    private function checkAiMetaTags(\DOMDocument $doc): array
    {
        $found = [];
        foreach ($doc->getElementsByTagName('meta') as $meta) {
            $name = strtolower(trim((string) $meta->getAttribute('name')));
            if (in_array($name, ['ai-content-type', 'ai-instructions', 'robots-ai'], true)) {
                $found[] = $name;
            }
        }

        if (!empty($found)) {
            return $this->makeCheck('ai_meta_tags', 'AI Meta Tags', true, 'pass',
                'Found AI meta tags: ' . implode(', ', $found) . '. These help AI engines understand content type and instructions.');
        }

        return $this->makeCheck('ai_meta_tags', 'AI Meta Tags', false, 'info',
            'No AI-specific meta tags found. These are optional signals for AI engines — configure them in the AEO settings.');
    }

    private function checkOgImage(\DOMDocument $doc): array
    {
        foreach ($doc->getElementsByTagName('meta') as $meta) {
            if (strtolower((string) $meta->getAttribute('property')) === 'og:image') {
                $url = trim((string) $meta->getAttribute('content'));
                if ($url !== '') {
                    return $this->makeCheck('og_image', 'OG Image (og:image)', true, 'pass',
                        'og:image is set. AI engines and social previews use this as the visual representation of your content.');
                }
            }
        }
        return $this->makeCheck('og_image', 'OG Image (og:image)', false, 'warning',
            'og:image is not set. AI answer engines (Perplexity, ChatGPT) often display images in responses.');
    }

    private function checkCanonical(\DOMDocument $doc): array
    {
        foreach ($doc->getElementsByTagName('link') as $link) {
            if (strtolower((string) $link->getAttribute('rel')) === 'canonical') {
                $href = trim((string) $link->getAttribute('href'));
                if ($href !== '') {
                    return $this->makeCheck('canonical', 'Canonical URL', true, 'pass',
                        'Canonical URL is set. This helps AI engines identify the authoritative version of the page.');
                }
            }
        }
        return $this->makeCheck('canonical', 'Canonical URL', false, 'warning',
            'No canonical URL found. Without a canonical, AI engines may index duplicate versions of your pages.');
    }

    private function checkSchemaJsonLd(\DOMDocument $doc): array
    {
        $count = 0;
        $types = [];
        foreach ($doc->getElementsByTagName('script') as $s) {
            if (strtolower(trim((string) $s->getAttribute('type'))) === 'application/ld+json') {
                $count++;
                $parsed = json_decode(trim((string) $s->textContent), true);
                if (is_array($parsed) && isset($parsed['@type'])) {
                    $types[] = (string) $parsed['@type'];
                }
            }
        }

        if ($count > 0) {
            $typeStr = !empty($types) ? ' (' . implode(', ', $types) . ')' : '';
            return $this->makeCheck('schema_jsonld', 'Schema.org (JSON-LD)', true, 'pass',
                "Found {$count} JSON-LD block(s){$typeStr}. Structured data is the most reliable signal for AI to understand your entity.");
        }

        return $this->makeCheck('schema_jsonld', 'Schema.org (JSON-LD)', false, 'error',
            'No Schema.org JSON-LD found. Enable the AI Boost Schema plugin to generate structured data automatically.');
    }

    private function checkAeoPlugin(): array
    {
        try {
            $query = $this->db->getQuery(true)
                ->select(['enabled'])
                ->from('#__extensions')
                ->where($this->db->quoteName('type')    . '=' . $this->db->quote('plugin'))
                ->where($this->db->quoteName('element') . '=' . $this->db->quote('aiboost_aeo'))
                ->where($this->db->quoteName('folder')  . '=' . $this->db->quote('system'));
            $row = $this->db->setQuery($query)->loadObject();

            if ($row === null) {
                return $this->makeCheck('aeo_plugin', 'AI Boost AEO Plugin', false, 'error',
                    'AI Boost AEO plugin is not installed. Install the full AI Boost package to enable llms.txt, IndexNow, and AI signals.');
            }
            if (!(bool) $row->enabled) {
                return $this->makeCheck('aeo_plugin', 'AI Boost AEO Plugin', false, 'warning',
                    'AI Boost AEO plugin is installed but not enabled. Enable it in Plugin Manager to activate llms.txt and IndexNow features.');
            }
            return $this->makeCheck('aeo_plugin', 'AI Boost AEO Plugin', true, 'pass',
                'AI Boost AEO plugin is active. llms.txt, IndexNow, and AI signal features are enabled.');
        } catch (\Throwable $e) {
            return $this->makeCheck('aeo_plugin', 'AI Boost AEO Plugin', false, 'warning',
                'Could not verify AEO plugin status.');
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SCORE
    // ─────────────────────────────────────────────────────────────────────────

    private function calculateScore(array $checks): int
    {
        $total  = array_sum(self::WEIGHTS);
        $earned = 0;

        foreach ($checks as $c) {
            $weight = self::WEIGHTS[$c['id']] ?? 0;
            if ($c['pass']) {
                $earned += $weight;
            } elseif ($c['severity'] === 'warning') {
                $earned += (int) floor($weight * 0.4);
            }
        }

        return max(0, min(100, (int) round(($earned / max(1, $total)) * 100)));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HTTP HELPERS  (Joomla HttpFactory — no raw file_get_contents)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Fetch URL body and response headers using Joomla HttpFactory.
     *
     * @return array{?string, array}  [$body|null, $headers]
     */
    private function fetchBodyAndHeaders(string $url): array
    {
        try {
            $response = $this->http->get($url, [], 8);
            $body     = (string) $response->body;
            $headers  = $response->headers ?? [];
            return [$body !== '' ? $body : null, $headers];
        } catch (\Throwable $e) {
            return [null, []];
        }
    }

    private function fetchBody(string $url): ?string
    {
        [$body] = $this->fetchBodyAndHeaders($url);
        return $body;
    }

    /**
     * Inspect X-Robots-Tag response headers for noindex or AI-crawler restrictions.
     *
     * @param array $headers  Response headers from Joomla HTTP client (header name => value|string[])
     */
    private function checkXRobotsTag(array $headers): array
    {
        // Collect all X-Robots-Tag values (may be multi-valued)
        $values = [];
        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) === 'x-robots-tag') {
                if (is_array($value)) {
                    foreach ($value as $v) {
                        $values[] = strtolower(trim((string) $v));
                    }
                } else {
                    $values[] = strtolower(trim((string) $value));
                }
            }
        }

        if (empty($values)) {
            return $this->makeCheck('x_robots_tag', 'X-Robots-Tag Header', true, 'info',
                'No X-Robots-Tag response header found. This is normal — X-Robots-Tag is usually only needed for non-HTML resources. Search engines and AI crawlers rely on the robots meta tag instead.');
        }

        $combined = implode(' ', $values);

        // Hard block: noindex directive applies to all bots
        if (str_contains($combined, 'noindex')) {
            return $this->makeCheck('x_robots_tag', 'X-Robots-Tag Header', false, 'error',
                'X-Robots-Tag header contains "noindex". This page will not be indexed by search engines or AI crawlers: ' . implode('; ', $values));
        }

        // Check for bot-specific noindex (e.g. "GPTBot: noindex")
        $restricted = [];
        foreach (array_keys(self::AI_CRAWLERS) as $bot) {
            $botPattern = strtolower($bot) . ':';
            if (str_contains($combined, $botPattern)) {
                $pos = strpos($combined, $botPattern);
                $directive = substr($combined, $pos + strlen($botPattern), 20);
                if (str_contains(trim($directive), 'noindex') || str_contains(trim($directive), 'none')) {
                    $restricted[] = $bot;
                }
            }
        }

        if (!empty($restricted)) {
            return $this->makeCheck('x_robots_tag', 'X-Robots-Tag Header', false, 'warning',
                'X-Robots-Tag restricts specific AI crawlers: ' . implode(', ', $restricted) . '. Remove these directives to allow AI engines to index your content.');
        }

        return $this->makeCheck('x_robots_tag', 'X-Robots-Tag Header', true, 'pass',
            'X-Robots-Tag is set and does not block AI crawlers: ' . implode('; ', $values));
    }

    private function fetchStatus(string $url): int
    {
        try {
            $response = $this->http->get($url, [], 5);
            return (int) $response->code;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function parseHtml(string $html): \DOMDocument
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        return $doc;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DB HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function getIndexNowKey(): string
    {
        try {
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName('settings_json'))
                ->from('#__aiboost_settings')
                ->where($this->db->quoteName('setting_key') . '=' . $this->db->quote('main'));
            $json     = (string) $this->db->setQuery($query)->loadResult();
            $settings = json_decode($json, true);
            // Primary key: indexnow_api_key (canonical field name across AEO plugin + settings UI)
            // Fallback: indexnow_key (legacy field name, kept for backward compatibility)
            $key = trim((string) ($settings['indexnow_api_key'] ?? $settings['indexnow_key'] ?? ''));
            return $key;
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function makeCheck(
        string $id,
        string $label,
        bool   $pass,
        string $severity,
        string $message
    ): array {
        return [
            'id'       => $id,
            'label'    => $label,
            'pass'     => $pass,
            'severity' => $severity,
            'message'  => $message,
        ];
    }
}
