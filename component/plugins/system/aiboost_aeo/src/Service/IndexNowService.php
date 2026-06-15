<?php
/**
 * AI Boost — IndexNowService
 *
 * Submits a single URL to the IndexNow API endpoint (Bing).
 * Logs each submission — including the HTTP response code and body — to
 * Joomla's log system so administrators can audit IndexNow activity via
 * Administrator → System → Joomla! Logs.
 *
 * Usage (Pro tier only):
 *   $siteRoot = rtrim(Uri::root(), '/');   // resolved in the Extension class
 *   $svc = new IndexNowService($apiKey, $siteRoot);
 *   $svc->submit('https://example.com/my-article');
 *
 * The service itself makes no Factory:: or Uri:: calls — both $apiKey and
 * $siteRoot are injected by the Extension class (which remains CMS-specific).
 *
 * @package     AiBoost\Plugin\System\AiBoostAeo
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostAeo\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Log\Log;

class IndexNowService
{
    private const API_ENDPOINT = 'https://api.indexnow.org/indexnow';
    private const LOG_CATEGORY = 'aiboost.aeo';
    private const TIMEOUT      = 5;

    private bool $loggerRegistered = false;

    /**
     * @param string $apiKey    IndexNow API key.
     * @param string $siteRoot  Absolute site root URL without trailing slash,
     *                          e.g. 'https://example.com'. Must be provided by
     *                          the Extension class (e.g. rtrim(Uri::root(), '/')).
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $siteRoot,
    ) {}

    /**
     * Submit a single URL to IndexNow.
     *
     * Logs the HTTP status code and response body to Joomla's log system.
     * Fire-and-forget — never throws; errors are logged and suppressed.
     *
     * @param string $url Canonical URL of the page to submit.
     */
    public function submit(string $url): void
    {
        if (!function_exists('curl_init') || $this->apiKey === '') {
            return;
        }

        $this->ensureLogger();

        $keyLocation = $this->siteRoot . '/' . $this->apiKey . '.txt';
        $host        = (string) parse_url($url, PHP_URL_HOST);

        $payload = json_encode([
            'host'        => $host,
            'key'         => $this->apiKey,
            'keyLocation' => $keyLocation,
            'urlList'     => [$url],
        ]);

        try {
            $ch = curl_init(self::API_ENDPOINT);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=utf-8'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => 3,
            ]);

            $body     = (string) curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($curlErr) {
                Log::add(
                    '[IndexNow] cURL error submitting ' . $url . ': ' . $curlErr,
                    Log::WARNING,
                    self::LOG_CATEGORY
                );
                return;
            }

            $level   = ($httpCode >= 200 && $httpCode < 300) ? Log::INFO : Log::WARNING;
            $snippet = $body !== '' ? ' | response: ' . mb_substr($body, 0, 200) : '';

            Log::add(
                '[IndexNow] Submitted ' . $url . ' → HTTP ' . $httpCode . $snippet,
                $level,
                self::LOG_CATEGORY
            );
        } catch (\Throwable $e) {
            Log::add(
                '[IndexNow] Exception submitting ' . $url . ': ' . $e->getMessage(),
                Log::ERROR,
                self::LOG_CATEGORY
            );
        }
    }

    /**
     * Register the Joomla file logger for LOG_CATEGORY once per request.
     */
    private function ensureLogger(): void
    {
        if ($this->loggerRegistered) {
            return;
        }
        Log::addLogger(
            ['text_file' => 'aiboost_aeo.php'],
            Log::ALL,
            [self::LOG_CATEGORY]
        );
        $this->loggerRegistered = true;
    }
}
