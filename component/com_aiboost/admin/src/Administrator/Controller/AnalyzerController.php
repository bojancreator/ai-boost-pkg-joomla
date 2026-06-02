<?php
/**
 * AI Boost — Analyzer Controller
 * AJAX endpoints for SEO, JSON-LD and AI Visibility analyzers.
 *
 * URL policy: all user-supplied URLs must use http or https.
 * `runAiVisibility` only accepts the current site's own base URL.
 *
 * @package     AiBoost\Component\AiBoost\Administrator\Controller
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Component\AiBoost\Administrator\Controller;

defined('_JEXEC') or die;

use AiBoost\Lib\AiVisibilityAnalyzerService;
use AiBoost\Lib\JsonLdAnalyzerService;
use AiBoost\Lib\SeoAnalyzerService;
use Joomla\CMS\Factory;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

class AnalyzerController extends BaseController
{
    /**
     * AJAX: run SEO analysis on the given URL.
     * POST: url, <token>
     * URL:  index.php?option=com_aiboost&task=analyzer.runSeo&format=json
     */
    public function runSeo(): void
    {
        if (!Session::checkToken('post')) {
            $this->sendJson(false, 'Invalid security token.', []);
            return;
        }
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJson(false, 'Access denied.', []);
            return;
        }

        $url = trim((string) $this->app->getInput()->post->getString('url', ''));
        if ($url === '') {
            $this->sendJson(false, 'No URL provided.', []);
            return;
        }

        if (!$this->isValidHttpUrl($url)) {
            $this->sendJson(false, 'Invalid URL. Only http and https URLs are allowed.', []);
            return;
        }

        // SEO Analyzer is scoped to the current site only (same-host enforcement, SSRF prevention)
        $siteHost = parse_url(Uri::root(), PHP_URL_HOST);
        $reqHost  = parse_url($url, PHP_URL_HOST);
        if ($siteHost !== $reqHost) {
            $this->sendJson(false, 'SEO analysis is only available for URLs on this site (' . $siteHost . ').', []);
            return;
        }

        try {
            $http    = HttpFactory::getHttp(new \Joomla\Registry\Registry(['userAgent' => 'AiBoost-SEO-Analyzer/1.0']));
            $service = new SeoAnalyzerService($http);
            $result  = $service->analyze($url);
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode(['success' => true, 'result' => $result]);
            $this->app->close();
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning('[AiBoost] AnalyzerController::runSeo error: ' . $e->getMessage());
            $this->sendJson(false, 'Analysis error: ' . $e->getMessage(), []);
        }
    }

    /**
     * AJAX: fetch JSON-LD blocks from a URL (for the JSON-LD "Fetch from URL" feature).
     * Restricted to the current site's host to avoid SSRF.
     * POST: url, <token>
     * URL:  index.php?option=com_aiboost&task=analyzer.fetchUrl&format=json
     */
    public function fetchUrl(): void
    {
        if (!Session::checkToken('post')) {
            $this->sendJson(false, 'Invalid security token.', []);
            return;
        }
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJson(false, 'Access denied.', []);
            return;
        }

        $url = trim((string) $this->app->getInput()->post->getString('url', ''));
        if (!$this->isValidHttpUrl($url)) {
            $this->sendJson(false, 'Invalid URL. Only http and https URLs are allowed.', []);
            return;
        }

        // Restrict to same host as the current Joomla site
        $siteHost = parse_url(Uri::root(), PHP_URL_HOST);
        $reqHost  = parse_url($url, PHP_URL_HOST);
        if ($siteHost !== $reqHost) {
            $this->sendJson(false, 'Fetch is only allowed for URLs on this site (' . $siteHost . ').', []);
            return;
        }

        try {
            $http     = HttpFactory::getHttp(new \Joomla\Registry\Registry(['userAgent' => 'AiBoost-Analyzer/1.0']));
            $response = $http->get($url, [], 10);

            if ((int) $response->code < 200 || (int) $response->code >= 400) {
                $this->sendJson(false, 'Could not fetch URL (HTTP ' . $response->code . ').', []);
                return;
            }

            $html = (string) $response->body;
            if ($html === '') {
                $this->sendJson(false, 'Empty response from URL.', []);
                return;
            }

            $jsonLdBlocks = [];
            if (preg_match_all(
                '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si',
                $html,
                $matches
            )) {
                foreach ($matches[1] as $block) {
                    $jsonLdBlocks[] = trim($block);
                }
            }

            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode([
                'success'      => true,
                'jsonldBlocks' => $jsonLdBlocks,
                'count'        => count($jsonLdBlocks),
            ]);
            $this->app->close();
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning('[AiBoost] AnalyzerController::fetchUrl error: ' . $e->getMessage());
            $this->sendJson(false, 'Fetch error: ' . $e->getMessage(), []);
        }
    }

    /**
     * AJAX: validate a JSON-LD string.
     * POST: json_string, <token>
     * URL:  index.php?option=com_aiboost&task=analyzer.validateJsonLd&format=json
     */
    public function validateJsonLd(): void
    {
        if (!Session::checkToken('post')) {
            $this->sendJson(false, 'Invalid security token.', []);
            return;
        }
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJson(false, 'Access denied.', []);
            return;
        }

        $jsonString = (string) $this->app->getInput()->post->getString('json_string', '');

        try {
            $service = new JsonLdAnalyzerService();
            $result  = $service->analyze($jsonString);
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode(['success' => true, 'result' => $result]);
            $this->app->close();
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning('[AiBoost] AnalyzerController::validateJsonLd error: ' . $e->getMessage());
            $this->sendJson(false, 'Validation error: ' . $e->getMessage(), []);
        }
    }

    /**
     * AJAX: run AI Visibility analysis for the current site.
     * Restricted to the current Joomla site's root URL.
     * POST: <token> [, base_url (optional override, must match site host)]
     * URL:  index.php?option=com_aiboost&task=analyzer.runAiVisibility&format=json
     */
    public function runAiVisibility(): void
    {
        if (!Session::checkToken('post')) {
            $this->sendJson(false, 'Invalid security token.', []);
            return;
        }
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJson(false, 'Access denied.', []);
            return;
        }

        try {
            // Uri::root() returns the frontend root URL (e.g. https://example.com/).
            $siteRoot    = rtrim(Uri::root(), '/');
            $siteHost    = parse_url($siteRoot, PHP_URL_HOST);

            $requestedUrl = trim((string) $this->app->getInput()->post->getString('base_url', ''));
            if ($requestedUrl !== '') {
                if (!$this->isValidHttpUrl($requestedUrl)) {
                    $this->sendJson(false, 'Invalid URL. Only http and https URLs are allowed.', []);
                    return;
                }
                // Enforce same-host: AI Visibility is designed for the current site only
                $reqHost = parse_url($requestedUrl, PHP_URL_HOST);
                if ($reqHost !== $siteHost) {
                    $this->sendJson(false, 'AI Visibility analysis is only available for this site (' . $siteHost . ').', []);
                    return;
                }
                $baseUrl = rtrim($requestedUrl, '/');
            } else {
                $baseUrl = $siteRoot;
            }

            $http    = HttpFactory::getHttp(new \Joomla\Registry\Registry(['userAgent' => 'AiBoost-Analyzer/1.0']));
            $service = new AiVisibilityAnalyzerService(Factory::getDbo(), $http);
            $result  = $service->analyze($baseUrl);

            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode(['success' => true, 'result' => $result]);
            $this->app->close();
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning('[AiBoost] AnalyzerController::runAiVisibility error: ' . $e->getMessage());
            $this->sendJson(false, 'Analysis error: ' . $e->getMessage(), []);
        }
    }

    /**
     * AJAX: apply a whitelisted boolean-setting fix from an SEO Analyzer suggestion.
     * POST: setting, value (must be '1' or '0'), <token>
     * URL:  index.php?option=com_aiboost&task=analyzer.applyFix&format=json
     */
    public function applyFix(): void
    {
        if (!Session::checkToken('post')) {
            $this->sendJson(false, 'Invalid security token.', []);
            return;
        }
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJson(false, 'Access denied.', []);
            return;
        }

        $setting = trim((string) $this->app->getInput()->post->getString('setting', ''));
        $value   = trim((string) $this->app->getInput()->post->getString('value', '1'));

        if (!in_array($setting, SeoAnalyzerService::APPLY_FIX_WHITELIST, true)) {
            $this->sendJson(false, 'That setting is not allowed to be auto-fixed.', []);
            return;
        }
        if (!in_array($value, ['0', '1'], true)) {
            $this->sendJson(false, 'Invalid value — only 0 or 1 supported.', []);
            return;
        }

        try {
            $db  = Factory::getDbo();
            $now = Factory::getDate()->toSql();

            $q   = $db->getQuery(true)
                ->select($db->quoteName('settings_json'))
                ->from('#__aiboost_settings')
                ->where($db->quoteName('setting_key') . '=' . $db->quote('main'));
            $raw = (string) $db->setQuery($q)->loadResult();
            $settings = $raw !== '' ? (json_decode($raw, true) ?: []) : [];

            $settings[$setting] = $value;
            $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            // Upsert
            $idQuery = $db->getQuery(true)
                ->select('id')->from('#__aiboost_settings')
                ->where($db->quoteName('setting_key') . '=' . $db->quote('main'));
            $existingId = (int) $db->setQuery($idQuery)->loadResult();

            if ($existingId) {
                $update = $db->getQuery(true)->update('#__aiboost_settings')
                    ->set($db->quoteName('settings_json') . '=' . $db->quote($json))
                    ->set($db->quoteName('updated_at')    . '=' . $db->quote($now))
                    ->where($db->quoteName('id') . '=' . $existingId);
                $db->setQuery($update)->execute();
            } else {
                $insert = $db->getQuery(true)->insert('#__aiboost_settings')
                    ->columns(['setting_key', 'settings_json', 'created_at', 'updated_at'])
                    ->values($db->quote('main') . ',' . $db->quote($json) . ',' . $db->quote($now) . ',' . $db->quote($now));
                $db->setQuery($insert)->execute();
            }

            $this->sendJson(true, 'Setting "' . $setting . '" updated. Re-run the analyzer to verify.', [
                'setting' => $setting,
                'value'   => $value,
            ]);
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning('[AiBoost] AnalyzerController::applyFix error: ' . $e->getMessage());
            $this->sendJson(false, 'Could not apply fix: ' . $e->getMessage(), []);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function isValidHttpUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true);
    }

    private function sendJson(bool $success, string $message, array $extra = []): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
        $this->app->close();
    }
}
