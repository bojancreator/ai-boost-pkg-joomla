<?php
/**
 * AI Boost — DuplicateTagScanner
 * Fetches the site homepage via HTTP GET and scans the HTML for duplicate SEO tags
 * that indicate two conflicting plugins are both writing to the document <head>.
 *
 * Detects: duplicate <title>, <meta name="description">, <link rel="canonical">,
 *          <meta property="og:title">, and excessive JSON-LD blocks.
 *
 * HTTP timeout: 5 seconds. SSL peer verification is disabled to tolerate staging/self-signed
 * certificates; failures are non-fatal (returns empty result set).
 * This scanner is intentionally skipped in module/lightweight context (skipHttpScan flag).
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib;

defined('_JEXEC') or die;

class DuplicateTagScanner
{
    private string $baseUrl;
    private array  $dismissed;

    /** Flag when more than this many JSON-LD blocks are found (>1 = duplicates present) */
    private const JSONLD_THRESHOLD = 1;

    /**
     * @param AppContextInterface $ctx       Provides the site base URL for the homepage fetch.
     * @param array               $dismissed Check IDs the admin has already dismissed.
     */
    public function __construct(AppContextInterface $ctx, array $dismissed = [])
    {
        $this->baseUrl   = rtrim($ctx->getBaseUrl(), '/');
        $this->dismissed = $dismissed;
    }

    /**
     * Fetch homepage and return duplicate-tag check results.
     *
     * @return list<array>
     */
    public function scan(): array
    {
        if ($this->baseUrl === '') {
            return [];
        }

        $html = $this->fetchHtml($this->baseUrl . '/');
        if ($html === null) {
            return [];
        }

        return $this->detectDuplicates($html);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FETCH
    // ─────────────────────────────────────────────────────────────────────────

    private function fetchHtml(string $url): ?string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        try {
            $ctx  = stream_context_create([
                'http' => [
                    'method'          => 'GET',
                    'timeout'         => 5.0,
                    'follow_location' => 1,
                    'max_redirects'   => 5,
                    'ignore_errors'   => true,
                    'user_agent'      => 'AI Boost Duplicate Tag Scanner/1.0',
                ],
                'ssl'  => [
                    'verify_peer'      => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $html = @file_get_contents($url, false, $ctx);
            if ($html === false || strlen($html) < 200) {
                return null;
            }

            return $html;
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning($e, ['where' => 'DuplicateTagScanner::fetchHtml']);
            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DETECTION
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Count page <title> elements, excluding inline SVG <title> children
     * (icons/illustrations) which are not the document title and must not
     * trip the duplicate-title check.
     */
    private function countPageTitles(\DOMDocument $doc): int
    {
        $count = 0;
        foreach ($doc->getElementsByTagName('title') as $title) {
            if (!$this->isInsideSvg($title)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * True when $node has an <svg> ancestor anywhere up the tree.
     */
    private function isInsideSvg(\DOMNode $node): bool
    {
        for ($parent = $node->parentNode; $parent !== null; $parent = $parent->parentNode) {
            if ($parent->nodeType === XML_ELEMENT_NODE && strtolower($parent->nodeName) === 'svg') {
                return true;
            }
        }
        return false;
    }

    private function detectDuplicates(string $html): array
    {
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument('1.0', 'UTF-8');
        // PHP 8.2+: mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8') is deprecated.
        // Prepend a UTF-8 XML declaration so libxml respects the encoding without
        // relying on the deprecated HTML-ENTITIES conversion. (v0.12.10)
        $doc->loadHTML(
            '<?xml encoding="UTF-8" ?>' . $html,
            LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();

        $results = [];

        // ── <title> ──────────────────────────────────────────────────────────
        // Count only real page titles — getElementsByTagName('title') also
        // matches inline SVG <title> children (very common in modern templates),
        // which previously produced a false "duplicate title" critical.
        $titleCount = $this->countPageTitles($doc);
        if ($titleCount > 1) {
            $results[] = $this->make(
                'duplicate_title', 'critical', 'Duplicate <title> Tag',
                "{$titleCount} <title> tags found in the homepage HTML — only one is allowed. "
                . 'Another SEO plugin may be outputting a duplicate title tag.',
                [
                    ['label' => 'Check plugin conflicts', 'url' => 'index.php?option=com_aiboost&view=health'],
                    ['label' => 'Disable Joomla Core title', 'url' => 'index.php?option=com_config'],
                ]
            );
        }

        // ── <meta name="description"> ────────────────────────────────────────
        $count = $this->countMetaName($doc, 'description');
        if ($count > 1) {
            $results[] = $this->make(
                'duplicate_meta_description', 'critical', 'Duplicate Meta Description',
                "{$count} <meta name=\"description\"> tags on the homepage — Google only reads the first. "
                . 'Disable conflicting SEO plugins that also output this tag.',
                [
                    ['label' => 'Check plugin conflicts', 'url' => 'index.php?option=com_aiboost&view=health'],
                ]
            );
        }

        // ── <link rel="canonical"> ───────────────────────────────────────────
        $count = $this->countLinkRel($doc, 'canonical');
        if ($count > 1) {
            $results[] = $this->make(
                'duplicate_canonical', 'critical', 'Duplicate Canonical Tag',
                "{$count} <link rel=\"canonical\"> tags found — conflicting canonical signals "
                . 'confuse search engines and may result in both being ignored.',
                [
                    ['label' => 'Disable Canonical in Settings', 'url' => 'index.php?option=com_aiboost&view=settings#tab-sitemap-btn'],
                    ['label' => 'Check plugin conflicts', 'url' => 'index.php?option=com_aiboost&view=health'],
                ]
            );
        }

        // ── <meta property="og:title|og:description|og:image"> ───────────────
        foreach (['og:title' => 'duplicate_og_title', 'og:description' => 'duplicate_og_description', 'og:image' => 'duplicate_og_image'] as $prop => $id) {
            $count = $this->countMetaProperty($doc, $prop);
            if ($count > 1) {
                $results[] = $this->make(
                    $id,
                    'critical',
                    'Duplicate ' . $prop . ' Tag',
                    "{$count} <meta property=\"{$prop}\"> tags found — duplicate OG tags confuse social "
                    . 'crawlers (Facebook, LinkedIn, X) and produce unpredictable share previews. '
                    . 'A second SEO/social plugin (or Joomla Core OG) is producing the same tag.',
                    [
                        ['label' => 'Disable Joomla Core OG in Global Config', 'url' => 'index.php?option=com_config'],
                        ['label' => 'Switch AI Boost to Cooperative mode', 'url' => 'index.php?option=com_aiboost&view=settings#tab-general-btn'],
                        ['label' => 'AI Boost Social Settings', 'url' => 'index.php?option=com_aiboost&view=settings#tab-social-btn'],
                    ]
                );
            }
        }

        // ── Schema.org Organization / WebSite duplicates ─────────────────────
        $schemaTypes = $this->countSchemaTypes($doc);
        foreach (['Organization', 'WebSite'] as $stype) {
            $stypeCount = $schemaTypes[$stype] ?? 0;
            if ($stypeCount > 1) {
                $results[] = $this->make(
                    'duplicate_schema_' . strtolower($stype),
                    'critical',
                    'Duplicate Schema.org ' . $stype,
                    "{$stypeCount} Schema.org {$stype} blocks found on the homepage — Google merges or "
                    . 'rejects duplicate structured-data blocks. Another SEO extension (e.g. 4SEO, Sh404SEF) '
                    . 'is emitting its own ' . $stype . ' JSON-LD alongside AI Boost.',
                    [
                        ['label' => 'Switch AI Boost to Cooperative mode', 'url' => 'index.php?option=com_aiboost&view=settings#tab-general-btn'],
                        ['label' => 'Check plugin conflicts', 'url' => 'index.php?option=com_aiboost&view=health'],
                    ]
                );
            }
        }

        // ── Total JSON-LD blocks safety net ──────────────────────────────────
        $count = $this->countJsonLd($doc);
        if ($count > 6) {
            $results[] = $this->make(
                'duplicate_jsonld', 'warning', 'Excessive JSON-LD Blocks',
                "{$count} <script type=\"application/ld+json\"> blocks detected on the homepage — "
                . 'this is unusually high. Multiple SEO plugins may be outputting structured data simultaneously.',
                [
                    ['label' => 'Switch AI Boost to Cooperative mode', 'url' => 'index.php?option=com_aiboost&view=settings#tab-general-btn'],
                    ['label' => 'Check plugin conflicts', 'url' => 'index.php?option=com_aiboost&view=health'],
                ]
            );
        }

        // ── Duplicate GA4 (gtag) loader ──────────────────────────────────────
        $ga4Count = $this->countScriptPattern($doc, '/googletagmanager\\.com\\/gtag\\/js\\?id=G-/i');
        if ($ga4Count > 1) {
            $results[] = $this->make(
                'duplicate_ga4', 'critical', 'Duplicate Google Analytics (GA4) Loader',
                "{$ga4Count} gtag.js loader scripts detected. Sending pageviews twice from two GA4 tags "
                . 'inflates your traffic numbers and breaks attribution.',
                [
                    ['label' => 'Disable extra GA4 sources', 'url' => 'index.php?option=com_plugins&filter[search]=analytics'],
                    ['label' => 'AI Boost Analytics Settings', 'url' => 'index.php?option=com_aiboost&view=settings#tab-analytics-btn'],
                ]
            );
        }

        // ── Duplicate facebook-domain-verification meta tag ──────────────────
        $fbVerifyCount = $this->countMetaName($doc, 'facebook-domain-verification');
        if ($fbVerifyCount > 1) {
            $results[] = $this->make(
                'duplicate_fb_domain_verification', 'critical', 'Duplicate Facebook Domain Verification',
                "{$fbVerifyCount} <meta name=\"facebook-domain-verification\"> tags found on the homepage — "
                . 'Facebook only accepts a single verification token per domain. Another extension or template '
                . 'is emitting the same tag alongside AI Boost.',
                [
                    ['label' => 'AI Boost Social Settings', 'url' => 'index.php?option=com_aiboost&view=settings#tab-social-btn'],
                    ['label' => 'Check plugin conflicts', 'url' => 'index.php?option=com_aiboost&view=health'],
                ]
            );
        }

        // ── Duplicate GTM container loader ───────────────────────────────────
        $gtmCount = $this->countScriptPattern($doc, '/googletagmanager\\.com\\/gtm\\.js\\?id=GTM-/i');
        if ($gtmCount > 1) {
            $results[] = $this->make(
                'duplicate_gtm', 'critical', 'Duplicate Google Tag Manager Container',
                "{$gtmCount} GTM container loaders detected. Multiple GTM containers double-fire every "
                . 'event and cause duplicated conversions in Google Ads & GA4.',
                [
                    ['label' => 'AI Boost Analytics Settings', 'url' => 'index.php?option=com_aiboost&view=settings#tab-analytics-btn'],
                ]
            );
        }

        // ── Duplicate Meta (Facebook) Pixel base code ────────────────────────
        // The base code loads fbevents.js from connect.facebook.net inside an
        // inline IIFE, so countScriptPattern (which scans inline content too)
        // catches it. Two pixels double-fire PageView and inflate conversions.
        $pixelCount = $this->countScriptPattern($doc, '/connect\\.facebook\\.net\\/[^\\/"\']+\\/fbevents\\.js/i');
        if ($pixelCount > 1) {
            $results[] = $this->make(
                'duplicate_meta_pixel', 'critical', 'Duplicate Meta Pixel',
                "{$pixelCount} Meta Pixel base codes detected on the homepage. Firing PageView from two "
                . 'pixels inflates your conversions and can get your pixel flagged by Meta. Another '
                . 'extension or your template is loading a pixel alongside AI Boost.',
                [
                    ['label' => 'AI Boost Analytics Settings', 'url' => 'index.php?option=com_aiboost&view=settings#tab-analytics-btn'],
                    ['label' => 'Check plugin conflicts', 'url' => 'index.php?option=com_aiboost&view=health'],
                ]
            );
        }

        return $results;
    }

    private function countSchemaTypes(\DOMDocument $doc): array
    {
        $counts = [];
        foreach ($doc->getElementsByTagName('script') as $script) {
            if (strtolower((string) $script->getAttribute('type')) !== 'application/ld+json') {
                continue;
            }
            $raw = trim((string) $script->textContent);
            if ($raw === '') {
                continue;
            }
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                continue;
            }
            // JSON-LD can be one object or @graph array
            $nodes = isset($data['@graph']) && is_array($data['@graph']) ? $data['@graph'] : [$data];
            foreach ($nodes as $node) {
                if (!is_array($node) || empty($node['@type'])) {
                    continue;
                }
                $types = (array) $node['@type'];
                foreach ($types as $t) {
                    $t = (string) $t;
                    $counts[$t] = ($counts[$t] ?? 0) + 1;
                }
            }
        }
        return $counts;
    }

    private function countScriptPattern(\DOMDocument $doc, string $regex): int
    {
        $count = 0;
        foreach ($doc->getElementsByTagName('script') as $script) {
            $src    = (string) $script->getAttribute('src');
            $inline = (string) $script->textContent;
            if ($src !== '' && preg_match($regex, $src)) {
                $count++;
                continue;
            }
            if ($inline !== '' && preg_match($regex, $inline)) {
                $count++;
            }
        }
        return $count;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DOM HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function countMetaName(\DOMDocument $doc, string $name): int
    {
        $count = 0;
        foreach ($doc->getElementsByTagName('meta') as $meta) {
            if (strtolower((string) $meta->getAttribute('name')) === strtolower($name)) {
                $count++;
            }
        }
        return $count;
    }

    private function countMetaProperty(\DOMDocument $doc, string $property): int
    {
        $count = 0;
        foreach ($doc->getElementsByTagName('meta') as $meta) {
            if (strtolower((string) $meta->getAttribute('property')) === strtolower($property)) {
                $count++;
            }
        }
        return $count;
    }

    private function countLinkRel(\DOMDocument $doc, string $rel): int
    {
        $count = 0;
        foreach ($doc->getElementsByTagName('link') as $link) {
            if (strtolower((string) $link->getAttribute('rel')) === strtolower($rel)) {
                $count++;
            }
        }
        return $count;
    }

    private function countJsonLd(\DOMDocument $doc): int
    {
        $count = 0;
        foreach ($doc->getElementsByTagName('script') as $script) {
            if (strtolower((string) $script->getAttribute('type')) === 'application/ld+json') {
                $count++;
            }
        }
        return $count;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // RESULT BUILDER
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param list<array{label:string,url:string}> $fixActions
     */
    private function make(
        string $id,
        string $severity,
        string $label,
        string $message,
        array  $fixActions = []
    ): array {
        return [
            'id'          => $id,
            'status'      => $severity,
            'category'    => 'Conflicts',
            'label'       => $label,
            'pass'        => false,
            'show_pass'   => false,
            'message'     => $message,
            'fix_url'     => $fixActions[0]['url'] ?? '',
            'fix_actions' => $fixActions,
            'dismissed'   => in_array($id, $this->dismissed, true),
        ];
    }
}
