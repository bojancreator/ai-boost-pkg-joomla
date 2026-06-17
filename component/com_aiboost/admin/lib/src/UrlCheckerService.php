<?php
/**
 * AI Boost — URL Checker Service
 * Resolves the sitemap URL from plugin/component settings and parses it
 * into a flat list of <loc> entries for the URL Checker to scan.
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Lib;

defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;

class UrlCheckerService
{
    private array $settings;

    public function __construct(array $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Resolve the primary sitemap URL.
     * Priority:
     *  1. Custom sitemap URL stored in settings (sitemap_custom_url)
     *  2. Site root + /sitemap.xml (standard AI Boost Sitemap plugin output)
     */
    public function getSitemapUrl(): string
    {
        $custom = trim((string) ($this->settings['sitemap_custom_url'] ?? ''));
        if ($custom !== '' && filter_var($custom, FILTER_VALIDATE_URL)) {
            return $custom;
        }
        return rtrim(Uri::root(), '/') . '/sitemap.xml';
    }

    /**
     * Fetch and parse a sitemap (regular or sitemap-index) into a flat URL list.
     * Handles one level of sitemap-index nesting.
     *
     * @return array{urls: string[], duplicates: string[], sitemapUrl: string, error: ?string}
     */
    public function fetchSitemapUrls(): array
    {
        $sitemapUrl = $this->getSitemapUrl();
        $xml        = $this->fetchRaw($sitemapUrl);

        if ($xml === null) {
            return [
                'urls'       => [],
                'duplicates' => [],
                'sitemapUrl' => $sitemapUrl,
                'error'      => 'Could not fetch sitemap at ' . $sitemapUrl
                              . '. Make sure the AI Boost Sitemap plugin is enabled.',
            ];
        }

        $doc = $this->parseXml($xml);
        if ($doc === false) {
            return [
                'urls'       => [],
                'duplicates' => [],
                'sitemapUrl' => $sitemapUrl,
                'error'      => 'Could not parse sitemap XML at ' . $sitemapUrl,
            ];
        }

        $raw = [];

        if ($doc->getName() === 'sitemapindex') {
            // Sitemap index: iterate child sitemaps (one level deep)
            foreach ($doc->sitemap as $entry) {
                $childUrl = trim((string) $entry->loc);
                if (!$childUrl) {
                    continue;
                }
                $childXml = $this->fetchRaw($childUrl);
                if ($childXml === null) {
                    continue;
                }
                $childDoc = $this->parseXml($childXml);
                if ($childDoc !== false) {
                    foreach ($childDoc->url as $u) {
                        $loc = trim((string) $u->loc);
                        if ($loc) {
                            $raw[] = $loc;
                        }
                    }
                }
            }
        } else {
            // Regular urlset
            foreach ($doc->url as $u) {
                $loc = trim((string) $u->loc);
                if ($loc) {
                    $raw[] = $loc;
                }
            }
        }

        // Detect duplicates before deduplication
        $seen       = [];
        $duplicates = [];
        foreach ($raw as $loc) {
            if (isset($seen[$loc])) {
                $duplicates[] = $loc;
            } else {
                $seen[$loc] = true;
            }
        }
        $duplicates = array_values(array_unique($duplicates));
        $urls       = array_values(array_keys($seen));

        return [
            'urls'       => $urls,
            'duplicates' => $duplicates,
            'sitemapUrl' => $sitemapUrl,
            'error'      => null,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * SSRF guard — only permit fetching public http(s) URLs. The site's own
     * host is always allowed (its sitemap normally lives there); any other
     * host (a custom sitemap URL or a sitemap-index child <loc>) must resolve
     * exclusively to routable public addresses, so loopback, private (RFC1918),
     * link-local and reserved ranges — including 169.254.169.254 cloud
     * metadata — are rejected.
     */
    private function isFetchTargetAllowed(string $url): bool
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return false;
        }
        $host = strtolower(trim((string) $parts['host'], '[]'));
        if ($host === '') {
            return false;
        }

        $siteHost = strtolower((string) parse_url(Uri::root(), PHP_URL_HOST));
        if ($siteHost !== '' && $host === $siteHost) {
            return true;
        }

        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $v4 = @gethostbynamel($host);
            if (is_array($v4)) {
                $ips = array_merge($ips, $v4);
            }
            $aaaa = @dns_get_record($host, DNS_AAAA);
            if (is_array($aaaa)) {
                foreach ($aaaa as $rec) {
                    if (!empty($rec['ipv6'])) {
                        $ips[] = $rec['ipv6'];
                    }
                }
            }
        }
        if (empty($ips)) {
            return false; // unresolvable — fail closed
        }
        foreach ($ips as $ip) {
            if (!$this->isPublicIp((string) $ip)) {
                return false;
            }
        }
        return true;
    }

    /**
     * True only for a routable public IP (rejects loopback/private/link-local/
     * unique-local/reserved ranges for IPv4 and IPv6).
     */
    private function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) !== false;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if (strpos($ip, '.') !== false) {
                $v4 = substr($ip, (int) strrpos($ip, ':') + 1);
                if (filter_var($v4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    return $this->isPublicIp($v4);
                }
            }
            $bin = @inet_pton($ip);
            if ($bin === false) {
                return false;
            }
            if ($ip === '::1' || $ip === '::') {
                return false;
            }
            $first = ord($bin[0]);
            if (($first & 0xFE) === 0xFC) {
                return false; // fc00::/7
            }
            if ($first === 0xFE && (ord($bin[1]) & 0xC0) === 0x80) {
                return false; // fe80::/10
            }
            return true;
        }
        return false;
    }

    /**
     * Fetch raw content from a URL (first 256 KB, with timeout).
     * Returns null on failure.
     */
    private function fetchRaw(string $url): ?string
    {
        if (!$this->isFetchTargetAllowed($url)) {
            return null;
        }

        if (!function_exists('curl_init')) {
            $ctx = stream_context_create([
                'http' => ['timeout' => 15, 'user_agent' => 'AI Boost URL Checker/1.0'],
                'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $raw = @file_get_contents($url, false, $ctx);
            return ($raw === false) ? null : substr($raw, 0, 262144);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT      => 'AI Boost URL Checker/1.0 (aiboostnow.com)',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_RANGE          => '0-262143',
        ]);
        $body  = curl_exec($ch);
        $errno = curl_errno($ch);
        // curl_close() omitted — deprecated no-op since PHP 8.0.

        if ($errno || $body === false) {
            return null;
        }
        return (string) $body;
    }

    /**
     * Parse XML string, suppressing errors.
     *
     * @return \SimpleXMLElement|false
     */
    private function parseXml(string $xml)
    {
        $prev   = libxml_use_internal_errors(true);
        $result = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        return $result;
    }
}
