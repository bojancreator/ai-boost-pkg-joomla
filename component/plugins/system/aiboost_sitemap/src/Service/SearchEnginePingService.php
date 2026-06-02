<?php
/**
 * AI Boost — Search Engine Ping Service
 *
 * Sends HTTP GET requests to the Google and Bing sitemap ping endpoints.
 * Both services respond with HTTP 200 on success; errors are logged silently.
 *
 * Google ping: https://www.google.com/ping?sitemap={sitemap_url}
 * Bing ping:   https://www.bing.com/ping?sitemap={sitemap_url}
 *
 * Note: Google announced in 2023 that it no longer actively processes sitemap
 * pings, but the endpoint still works. Bing continues to support pings actively.
 *
 * @package     AiBoost\Plugin\System\AiBoostSitemap
 * @version     0.7.0
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Plugin\System\AiBoostSitemap\Service;

defined('_JEXEC') or die;

class SearchEnginePingService
{
    private const GOOGLE_ENDPOINT = 'https://www.google.com/ping';
    private const BING_ENDPOINT   = 'https://www.bing.com/ping';
    private const TIMEOUT_SECONDS = 5;
    private const USER_AGENT      = 'AI Boost Sitemap Plugin/0.7.0 (aiboostnow.com)';

    /**
     * Ping Google with the sitemap URL.
     *
     * @param  string $sitemapUrl  Absolute URL to the sitemap (e.g. https://example.com/sitemap.xml).
     * @return bool                True if ping was accepted (HTTP 2xx).
     */
    public function pingGoogle(string $sitemapUrl): bool
    {
        return $this->ping(self::GOOGLE_ENDPOINT, $sitemapUrl);
    }

    /**
     * Ping Bing with the sitemap URL.
     *
     * @param  string $sitemapUrl  Absolute URL to the sitemap.
     * @return bool                True if ping was accepted (HTTP 2xx).
     */
    public function pingBing(string $sitemapUrl): bool
    {
        return $this->ping(self::BING_ENDPOINT, $sitemapUrl);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Send a GET ping to a search engine endpoint.
     *
     * @param  string $endpoint   Base ping URL (no query string).
     * @param  string $sitemapUrl Sitemap URL to submit.
     * @return bool               True on HTTP 2xx response.
     */
    private function ping(string $endpoint, string $sitemapUrl): bool
    {
        if ($sitemapUrl === '' || !str_starts_with($sitemapUrl, 'http')) {
            return false;
        }

        $pingUrl = $endpoint . '?sitemap=' . urlencode($sitemapUrl);

        // Prefer cURL if available
        if (function_exists('curl_init')) {
            return $this->pingViaCurl($pingUrl);
        }

        // Fallback: file_get_contents with stream context
        return $this->pingViaStream($pingUrl);
    }

    private function pingViaCurl(string $url): bool
    {
        try {
            $ch = curl_init($url);
            if ($ch === false) {
                return false;
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_USERAGENT      => self::USER_AGENT,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPGET        => true, // Explicit GET request (required by Google/Bing ping endpoints)
            ]);

            curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error    = curl_error($ch);
            curl_close($ch);

            if ($error !== '') {
                error_log('[AI Boost Sitemap] Ping cURL error for ' . $url . ': ' . $error);
            }

            return $httpCode >= 200 && $httpCode < 300;
        } catch (\Throwable $e) {
            error_log('[AI Boost Sitemap] Ping exception: ' . $e->getMessage());
            return false;
        }
    }

    private function pingViaStream(string $url): bool
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'method'          => 'GET',
                    'timeout'         => self::TIMEOUT_SECONDS,
                    'user_agent'      => self::USER_AGENT,
                    'ignore_errors'   => true,
                ],
                'ssl'  => [
                    'verify_peer' => true,
                ],
            ]);

            $result = @file_get_contents($url, false, $context);

            if ($result === false) {
                return false;
            }

            // Check HTTP status from response headers
            if (isset($http_response_header[0])) {
                preg_match('/HTTP\/\S+ (\d{3})/', $http_response_header[0], $m);
                $status = (int) ($m[1] ?? 0);
                return $status >= 200 && $status < 300;
            }

            return true;
        } catch (\Throwable $e) {
            error_log('[AI Boost Sitemap] Ping stream exception: ' . $e->getMessage());
            return false;
        }
    }
}
