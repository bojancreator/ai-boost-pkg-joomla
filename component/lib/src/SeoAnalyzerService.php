<?php
/**
 * AI Boost — SeoAnalyzerService
 * Fetches a URL using Joomla's HTTP client and performs a structured SEO analysis.
 * Returns a list of categorized checks with severity levels and a 0–100 score.
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib;

defined('_JEXEC') or defined('ABSPATH') or die;

use Joomla\CMS\Http\HttpFactory;
use Joomla\Http\Http;

class SeoAnalyzerService
{
    /** Points contributed by each check when passing. Total = 100. */
    private const WEIGHTS = [
        'https'          => 10,
        'title'          => 10,
        'meta_desc'      => 10,
        'canonical'      => 5,
        'og_title'       => 5,
        'og_description' => 3,
        'og_image'       => 4,
        'schema_jsonld'  => 15,
        'h1'             => 10,
        'robots_noindex' => 8,
        'robots_txt'     => 5,
        'sitemap_xml'    => 8,
        'img_alt'        => 7,
    ];

    /**
     * Per-check fix metadata. fix_action ∈ {none, apply_setting, open_tab, open_editor}.
     *  - apply_setting → server flips a whitelisted boolean setting to '1' on demand
     *  - open_tab      → deep-links the admin to Settings #tab=<tab>&field=<field>
     *  - open_editor   → user fixes inside the article editor (we can't auto-fix content)
     *  - none          → informational only
     */
    private const CHECK_META = [
        'https' => [
            'why'        => 'HTTPS is a confirmed Google ranking signal and required for modern browser features.',
            'suggestion' => 'Install a TLS certificate (Let’s Encrypt is free) and force redirect HTTP → HTTPS at the web server.',
            'fix_action' => 'none',
        ],
        'title' => [
            'why'        => 'The <title> tag is the strongest on-page SEO signal and the headline shown in search results.',
            'suggestion' => 'Configure AI Boost title templates so every page falls back to a 30–60 char branded title.',
            'fix_action' => 'open_tab',
            'fix_payload'=> ['tab' => 'general', 'field' => 'title_template'],
        ],
        'meta_desc' => [
            'why'        => 'Meta description is the snippet users see in search results — it strongly affects click-through rate.',
            'suggestion' => 'Set a fallback meta description template in AI Boost General settings so every page has 100–160 chars.',
            'fix_action' => 'open_tab',
            'fix_payload'=> ['tab' => 'general', 'field' => 'meta_desc_template'],
        ],
        'canonical' => [
            'why'        => 'Canonical URLs prevent duplicate-content penalties when the same page is reachable via multiple URLs.',
            'suggestion' => 'Enable “Canonical URL management” in AI Boost so every page emits a self-canonical link.',
            'fix_action' => 'apply_setting',
            'fix_payload'=> ['setting' => 'enable_canonical', 'value' => '1', 'tab' => 'technical', 'field' => 'enable_canonical'],
        ],
        'og_title' => [
            'why'        => 'Without og:title, Facebook, LinkedIn and Slack fall back to a generic share preview.',
            'suggestion' => 'Enable OpenGraph in AI Boost Social tab — og:title auto-populates from the page title.',
            'fix_action' => 'apply_setting',
            'fix_payload'=> ['setting' => 'enable_opengraph', 'value' => '1', 'tab' => 'social', 'field' => 'enable_opengraph'],
        ],
        'og_description' => [
            'why'        => 'og:description controls the body text social platforms show under your link.',
            'suggestion' => 'Enable OpenGraph in AI Boost Social tab — og:description auto-populates from the meta description.',
            'fix_action' => 'apply_setting',
            'fix_payload'=> ['setting' => 'enable_opengraph', 'value' => '1', 'tab' => 'social', 'field' => 'enable_opengraph'],
        ],
        'og_image' => [
            'why'        => 'Posts with images get ~2× more engagement on Facebook, LinkedIn, X and WhatsApp.',
            'suggestion' => 'Set a default OG image (1200×630 px) in AI Boost Social → Default OG Image.',
            'fix_action' => 'open_tab',
            'fix_payload'=> ['tab' => 'social', 'field' => 'default_og_image'],
        ],
        'schema_jsonld' => [
            'why'        => 'Schema.org JSON-LD is how Google, Bing and AI engines (ChatGPT, Perplexity) understand your content.',
            'suggestion' => 'Enable Schema.org in AI Boost so Organization, WebSite and Article schema are emitted automatically.',
            'fix_action' => 'apply_setting',
            'fix_payload'=> ['setting' => 'enable_schema', 'value' => '1', 'tab' => 'schema', 'field' => 'enable_schema'],
        ],
        'h1' => [
            'why'        => 'Search engines and screen readers use the H1 as the page’s main topic. Missing or duplicate H1s confuse both.',
            'suggestion' => 'Open the article and make sure the body starts with exactly one H1 heading.',
            'fix_action' => 'open_editor',
        ],
        'robots_noindex' => [
            'why'        => 'A noindex tag tells search engines to drop this page from results — usually unintentional.',
            'suggestion' => 'In Joomla article options or AI Boost Title Templates, ensure robots meta is set to “index, follow”.',
            'fix_action' => 'open_editor',
        ],
        'robots_txt' => [
            'why'        => 'robots.txt tells crawlers which paths to fetch and where the sitemap lives. Without it, crawlers waste budget.',
            'suggestion' => 'Enable AI Boost robots.txt management — it generates a Joomla-aware robots.txt automatically.',
            'fix_action' => 'apply_setting',
            'fix_payload'=> ['setting' => 'enable_robots', 'value' => '1', 'tab' => 'crawlers', 'field' => 'enable_robots'],
        ],
        'sitemap_xml' => [
            'why'        => 'XML sitemaps tell search engines about every page on your site, including new and orphaned content.',
            'suggestion' => 'Enable AI Boost XML Sitemap — auto-includes articles, categories and menu items.',
            'fix_action' => 'apply_setting',
            'fix_payload'=> ['setting' => 'enable_sitemap', 'value' => '1', 'tab' => 'sitemap', 'field' => 'enable_sitemap'],
        ],
        'img_alt' => [
            'why'        => 'Alt text is required for accessibility (WCAG) and is the primary signal for Google Image search.',
            'suggestion' => 'Open the article in the editor and add descriptive alt text to each image (avoid “image1.jpg”).',
            'fix_action' => 'open_editor',
        ],
    ];

    /** Whitelist of settings the applyFix endpoint is allowed to mutate. */
    public const APPLY_FIX_WHITELIST = [
        'enable_canonical', 'enable_opengraph', 'enable_schema',
        'enable_robots', 'enable_sitemap',
    ];

    private Http $http;

    public function __construct(?Http $http = null)
    {
        $this->http = $http ?? \AiBoost\Lib\Cms\AdapterRegistry::http()->getClient(['userAgent' => 'AiBoost-SEO-Analyzer/1.0']);
    }

    /**
     * Analyze the given URL and return structured results.
     *
     * @return array{score: int, url: string, checks: list<array>, error: string|null}
     */
    public function analyze(string $url): array
    {
        $url = filter_var(trim($url), FILTER_VALIDATE_URL) ? trim($url) : '';
        if ($url === '') {
            return ['score' => 0, 'url' => $url, 'checks' => [], 'error' => 'Invalid URL.'];
        }

        // Only allow http and https
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return ['score' => 0, 'url' => $url, 'checks' => [], 'error' => 'Only http and https URLs are supported.'];
        }

        $html = $this->fetchHtml($url);
        if ($html === null) {
            return ['score' => 0, 'url' => $url, 'checks' => [], 'error' => 'Could not fetch URL. Check that the site is accessible.'];
        }

        $doc    = $this->parseHtml($html);
        $checks = [];

        // ── HTTPS ──────────────────────────────────────────────────────────
        $isHttps = str_starts_with($url, 'https://');
        $checks[] = $this->check(
            'https', 'HTTPS Enabled', $isHttps,
            'Site uses HTTPS — great for security and search ranking.',
            'Site is not using HTTPS. Migrate to HTTPS to improve security and SEO.'
        );

        // ── Title ──────────────────────────────────────────────────────────
        $title    = $this->getTitle($doc);
        $titleLen = $title !== null ? mb_strlen($title) : 0;
        $titleOk  = $title !== null && $titleLen >= 30 && $titleLen <= 60;
        if ($title === null) {
            $titleMsg = ['Title tag is missing — every page needs a unique <title> tag.', 'error'];
        } elseif ($titleLen < 30) {
            $titleMsg = ["Title is too short ({$titleLen} chars). Aim for 30–60 characters.", 'warning'];
        } elseif ($titleLen > 60) {
            $titleMsg = ["Title is too long ({$titleLen} chars). Search engines truncate over 60 characters.", 'warning'];
        } else {
            $titleMsg = ["Title looks good ({$titleLen} chars): \"" . mb_substr($title, 0, 80) . '"', 'pass'];
        }
        $checks[] = $this->checkDetailed('title', 'Page Title', $titleOk, $titleMsg[0], $titleMsg[1]);

        // ── Meta Description ───────────────────────────────────────────────
        $metaDesc    = $this->getMetaContent($doc, 'description');
        $metaDescLen = $metaDesc !== null ? mb_strlen($metaDesc) : 0;
        $metaOk      = $metaDesc !== null && $metaDescLen >= 100 && $metaDescLen <= 160;
        if ($metaDesc === null) {
            $metaMsg = ['Meta description is missing. Add a 100–160 character summary for search snippets.', 'error'];
        } elseif ($metaDescLen < 100) {
            $metaMsg = ["Meta description is too short ({$metaDescLen} chars). Aim for 100–160 characters.", 'warning'];
        } elseif ($metaDescLen > 160) {
            $metaMsg = ["Meta description is too long ({$metaDescLen} chars). Search engines truncate at ~160 characters.", 'warning'];
        } else {
            $metaMsg = ["Meta description looks good ({$metaDescLen} chars).", 'pass'];
        }
        $checks[] = $this->checkDetailed('meta_desc', 'Meta Description', $metaOk, $metaMsg[0], $metaMsg[1]);

        // ── Canonical ──────────────────────────────────────────────────────
        $canonical = $this->getCanonical($doc);
        $checks[]  = $this->check(
            'canonical', 'Canonical URL', $canonical !== null,
            'Canonical URL is set: ' . ($canonical ?? ''),
            'No canonical URL found. Add <link rel="canonical"> to prevent duplicate content issues.'
        );

        // ── OpenGraph ─────────────────────────────────────────────────────
        $ogTitle = $this->getMetaProperty($doc, 'og:title');
        $ogDesc  = $this->getMetaProperty($doc, 'og:description');
        $ogImage = $this->getMetaProperty($doc, 'og:image');
        $checks[] = $this->check(
            'og_title', 'OG Title (og:title)', $ogTitle !== null,
            'og:title is set — social media previews will show the correct title.',
            'og:title is missing. Add OpenGraph tags for better social media sharing.'
        );
        $checks[] = $this->check(
            'og_description', 'OG Description (og:description)', $ogDesc !== null,
            'og:description is set.',
            'og:description is missing. Social media previews will show a generic excerpt.'
        );
        $checks[] = $this->check(
            'og_image', 'OG Image (og:image)', $ogImage !== null,
            'og:image is set — social cards will show a preview image.',
            'og:image is missing. Without an image, social media cards look less attractive.'
        );

        // ── Schema.org JSON-LD ─────────────────────────────────────────────
        $jsonLdCount = $this->countJsonLd($doc);
        $checks[]    = $this->check(
            'schema_jsonld', 'Schema.org (JSON-LD)', $jsonLdCount > 0,
            "Found {$jsonLdCount} JSON-LD structured data block(s). Search engines and AI can understand your content.",
            'No JSON-LD structured data found. Add Schema.org markup to help search engines and AI understand your content.'
        );

        // ── H1 ─────────────────────────────────────────────────────────────
        $h1Count = $this->countElements($doc, 'h1');
        $h1Ok    = $h1Count === 1;
        if ($h1Count === 0) {
            $h1Msg = ['No H1 tag found. Every page should have exactly one H1 as the main heading.', 'error'];
        } elseif ($h1Count > 1) {
            $h1Msg = ["Found {$h1Count} H1 tags — a page should have exactly one H1.", 'warning'];
        } else {
            $h1Msg = ['Exactly one H1 tag found.', 'pass'];
        }
        $checks[] = $this->checkDetailed('h1', 'H1 Heading', $h1Ok, $h1Msg[0], $h1Msg[1]);

        // ── Robots noindex ─────────────────────────────────────────────────
        $robotsMeta = strtolower((string) $this->getMetaContent($doc, 'robots'));
        $isNoindex  = str_contains($robotsMeta, 'noindex');
        $checks[]   = $this->check(
            'robots_noindex', 'Robots Meta (indexable)', !$isNoindex,
            'Page is set to be indexed by search engines.',
            'robots meta tag contains "noindex" — this page will not be indexed by search engines.'
        );

        // ── robots.txt ─────────────────────────────────────────────────────
        $parsed   = parse_url($url);
        $baseUrl  = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
        [$rtStatus] = $this->fetchStatus($baseUrl . '/robots.txt');
        $checks[] = $this->check(
            'robots_txt', 'robots.txt', $rtStatus >= 200 && $rtStatus < 400,
            'robots.txt is accessible (HTTP ' . $rtStatus . ').',
            'robots.txt not accessible (HTTP ' . ($rtStatus ?: 'timeout') . '). Search engines need robots.txt to understand crawl rules.'
        );

        // ── sitemap.xml ────────────────────────────────────────────────────
        [$smStatus] = $this->fetchStatus($baseUrl . '/sitemap.xml');
        $checks[] = $this->check(
            'sitemap_xml', 'XML Sitemap', $smStatus >= 200 && $smStatus < 400,
            'sitemap.xml is accessible (HTTP ' . $smStatus . ').',
            'sitemap.xml not accessible (HTTP ' . ($smStatus ?: 'timeout') . '). Provide a sitemap to ensure all pages are discoverable.'
        );

        // ── Image alt attributes ───────────────────────────────────────────
        $imgMissingAlt = $this->countImagesWithoutAlt($doc);
        $checks[]      = $this->check(
            'img_alt', 'Image Alt Attributes',
            $imgMissingAlt === 0,
            'All images have alt attributes.',
            "{$imgMissingAlt} image(s) missing alt text. Alt attributes improve accessibility and image search visibility."
        );

        // Try to resolve the analyzed URL to a Joomla article edit URL so
        // open_editor checks can deep-link straight into the editor.
        $editUrl = $this->resolveArticleEditUrl($url);

        // Attach fix metadata (why / suggestion / fix_action) to every check
        foreach ($checks as &$c) {
            $meta = self::CHECK_META[$c['id']] ?? null;
            if ($meta) {
                $c['why']        = $meta['why']        ?? '';
                $c['suggestion'] = $meta['suggestion'] ?? '';
                $c['fix_action'] = $meta['fix_action'] ?? 'none';
                $c['fix_payload']= $meta['fix_payload']?? null;
            } else {
                $c['why']        = '';
                $c['suggestion'] = '';
                $c['fix_action'] = 'none';
                $c['fix_payload']= null;
            }

            // For content-side issues, inject the resolved editor URL when available
            if ($c['fix_action'] === 'open_editor' && $editUrl !== null) {
                $c['fix_payload'] = array_merge((array) ($c['fix_payload'] ?? []), [
                    'edit_url' => $editUrl,
                ]);
            }

            // H1 and Image Alt failures include a per-URL issue list so the Vue
            // component can render a collapsible list of affected pages with edit
            // links. For a single-URL scan this is always one entry; the structure
            // supports aggregation if multi-URL scanning is added in the future.
            if (in_array($c['id'], ['h1', 'img_alt'], true) && !$c['pass']) {
                $entry = ['url' => $url];
                if ($editUrl !== null) {
                    $entry['edit_url'] = $editUrl;
                }
                $c['affected_urls'] = [$entry];
            }
        }
        unset($c);

        $score = $this->calculateScore($checks);

        return [
            'score'  => $score,
            'url'    => $url,
            'checks' => $checks,
            'error'  => null,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SCORE
    // ─────────────────────────────────────────────────────────────────────────

    private function calculateScore(array $checks): int
    {
        $total  = array_sum(self::WEIGHTS);
        $earned = 0;

        foreach ($checks as $c) {
            if ($c['pass'] === true) {
                $earned += self::WEIGHTS[$c['id']] ?? 0;
            } elseif ($c['severity'] === 'warning') {
                $earned += (int) floor((self::WEIGHTS[$c['id']] ?? 0) * 0.5);
            }
        }

        return (int) round(min(100, max(0, ($earned / max(1, $total)) * 100)));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HTML HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function getTitle(\DOMDocument $doc): ?string
    {
        $nodes = $doc->getElementsByTagName('title');
        if ($nodes->length > 0) {
            $text = trim((string) $nodes->item(0)->textContent);
            return $text !== '' ? $text : null;
        }
        return null;
    }

    private function getMetaContent(\DOMDocument $doc, string $name): ?string
    {
        foreach ($doc->getElementsByTagName('meta') as $meta) {
            if (strtolower((string) $meta->getAttribute('name')) === strtolower($name)) {
                $content = trim((string) $meta->getAttribute('content'));
                return $content !== '' ? $content : null;
            }
        }
        return null;
    }

    private function getMetaProperty(\DOMDocument $doc, string $property): ?string
    {
        foreach ($doc->getElementsByTagName('meta') as $meta) {
            if (strtolower((string) $meta->getAttribute('property')) === strtolower($property)) {
                $content = trim((string) $meta->getAttribute('content'));
                return $content !== '' ? $content : null;
            }
        }
        return null;
    }

    private function getCanonical(\DOMDocument $doc): ?string
    {
        foreach ($doc->getElementsByTagName('link') as $link) {
            if (strtolower((string) $link->getAttribute('rel')) === 'canonical') {
                $href = trim((string) $link->getAttribute('href'));
                return $href !== '' ? $href : null;
            }
        }
        return null;
    }

    private function countJsonLd(\DOMDocument $doc): int
    {
        $count = 0;
        foreach ($doc->getElementsByTagName('script') as $s) {
            if (strtolower(trim((string) $s->getAttribute('type'))) === 'application/ld+json') {
                $count++;
            }
        }
        return $count;
    }

    private function countElements(\DOMDocument $doc, string $tag): int
    {
        return $doc->getElementsByTagName($tag)->length;
    }

    private function countImagesWithoutAlt(\DOMDocument $doc): int
    {
        $missing = 0;
        foreach ($doc->getElementsByTagName('img') as $img) {
            if (trim((string) $img->getAttribute('alt')) === '') {
                $missing++;
            }
        }
        return $missing;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HTTP HELPERS  (Joomla HttpFactory — no raw file_get_contents)
    // ─────────────────────────────────────────────────────────────────────────

    private function fetchHtml(string $url): ?string
    {
        try {
            $response = $this->http->get($url, [], 10);
            $body     = (string) $response->body;
            return $body !== '' ? $body : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return array{int, string}  [HTTP status code (0 = timeout), body]
     */
    private function fetchStatus(string $url): array
    {
        try {
            $response = $this->http->get($url, [], 5);
            return [(int) $response->code, (string) $response->body];
        } catch (\Throwable $e) {
            return [0, ''];
        }
    }

    /**
     * Best-effort: turn a public site URL into an admin article-edit URL.
     * Tries leading-numeric-id pattern first (e.g. /42-my-article), then
     * falls back to looking up the last path segment as an article alias.
     * Returns a relative admin URL (works because the analyzer UI lives at
     * /administrator/) or null when we cannot confidently resolve.
     */
    private function resolveArticleEditUrl(string $url): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        if ($path === '' || $path === '/') {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $path), static fn($s) => $s !== ''));
        if (!$segments) {
            return null;
        }

        $last = preg_replace('/\.(html?|php)$/i', '', (string) end($segments));
        if ($last === '' || $last === null) {
            return null;
        }

        // Pattern: "<id>-<alias>"
        if (preg_match('/^(\d+)(?:-.*)?$/', $last, $m)) {
            $articleId = (int) $m[1];
            if ($articleId > 0) {
                return 'index.php?option=com_content&task=article.edit&id=' . $articleId;
            }
        }

        // Fallback: look up by alias in #__content
        try {
            $db = \AiBoost\Lib\Cms\AdapterRegistry::database()->getConnection();
            $q  = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('alias') . ' = ' . $db->quote($last));
            $id = (int) $db->setQuery($q, 0, 1)->loadResult();
            if ($id > 0) {
                return 'index.php?option=com_content&task=article.edit&id=' . $id;
            }
        } catch (\Throwable $e) {
            // ignore — fall through to null
        }

        return null;
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
    // CHECK BUILDERS
    // ─────────────────────────────────────────────────────────────────────────

    private function check(
        string $id,
        string $label,
        bool   $pass,
        string $passMsg,
        string $failMsg
    ): array {
        return [
            'id'       => $id,
            'label'    => $label,
            'pass'     => $pass,
            'severity' => $pass ? 'pass' : 'error',
            'message'  => $pass ? $passMsg : $failMsg,
        ];
    }

    private function checkDetailed(
        string $id,
        string $label,
        bool   $pass,
        string $message,
        string $severity
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
