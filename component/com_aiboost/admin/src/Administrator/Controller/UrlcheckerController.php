<?php
/**
 * AI Boost — URL Checker Controller
 * AJAX endpoints: getSitemapUrls, checkBatch, checkGscIndexation
 *
 * @package     AiBoost\Component\AiBoost\Administrator\Controller
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Component\AiBoost\Administrator\Controller;

defined('_JEXEC') or die;

use AiBoost\Lib\UrlCheckerService;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

class UrlcheckerController extends BaseController
{
    /**
     * Per-request cache of SSRF host verdicts (host => allowed?).
     *
     * @var array<string,bool>
     */
    private array $fetchTargetCache = [];

    public function display($cachable = false, $urlparams = []): static
    {
        $this->app->getInput()->set('view', 'urlchecker');
        parent::display($cachable, $urlparams);
        return $this;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Endpoint: getSitemapUrls
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * AJAX: fetch all <loc> URLs from the configured sitemap.
     * Returns { success, urls, duplicates, count, sitemapUrl }
     */
    public function getSitemapUrls(): void
    {
        if (!Session::checkToken()) {
            $this->sendJson(false, 'Invalid security token.');
            return;
        }
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJson(false, 'Access denied.');
            return;
        }

        $settings = $this->loadSettings();
        $service  = new UrlCheckerService($settings);
        $result   = $service->fetchSitemapUrls();

        if ($result['error'] !== null) {
            $this->sendJson(false, $result['error']);
            return;
        }

        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo json_encode([
            'success'    => true,
            'urls'       => $result['urls'],
            'duplicates' => $result['duplicates'],
            'count'      => count($result['urls']),
            'sitemapUrl' => $result['sitemapUrl'],
        ]);
        $this->app->close();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Endpoint: checkBatch
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * AJAX: check a batch of URLs.
     * POST: urls (JSON-encoded array, max 50)
     *
     * Each result:
     * {
     *   url, status, redirect_chain, canonical, canonical_status,
     *   is_noindex, is_thin_content, content_chars, error
     * }
     *
     * redirect_chain: [{url, status}, ...] — full chain for 3xx responses
     * canonical_status: 'ok' | 'missing' | 'mismatch' | 'skipped'
     * is_noindex: true if <meta robots noindex> or X-Robots-Tag: noindex
     * is_thin_content: true if visible text < 300 chars (200 OK only)
     */
    public function checkBatch(): void
    {
        if (!Session::checkToken()) {
            $this->sendJson(false, 'Invalid security token.');
            return;
        }
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJson(false, 'Access denied.');
            return;
        }

        $urlsJson = $this->app->getInput()->getString('urls', '[]');
        $urls     = json_decode($urlsJson, true);
        if (!is_array($urls) || empty($urls)) {
            $this->sendJson(false, 'No URLs provided.');
            return;
        }

        $urls    = array_slice(array_map('trim', $urls), 0, 50);
        $results = [];
        foreach ($urls as $url) {
            if (!filter_var($url, FILTER_VALIDATE_URL) || !$this->isFetchTargetAllowed($url)) {
                $results[] = [
                    'url'              => $url,
                    'status'           => 0,
                    'redirect_chain'   => [],
                    'canonical'        => null,
                    'canonical_status' => 'skipped',
                    'is_noindex'       => false,
                    'is_thin_content'  => false,
                    'content_chars'    => 0,
                    'error'            => 'Invalid or non-public URL',
                ];
                continue;
            }
            $results[] = $this->checkUrl($url);
        }

        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo json_encode(['success' => true, 'results' => $results]);
        $this->app->close();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Endpoint: checkGscIndexation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * AJAX: compare sitemap URLs against Google Search Console Search Analytics.
     * Requires gsc_api_token (OAuth2 access token) + gsc_site_url in settings.
     *
     * POST: urls (JSON-encoded array)
     * Returns { success, not_indexed, gsc_total, message }
     */
    public function checkGscIndexation(): void
    {
        if (!Session::checkToken()) {
            $this->sendJson(false, 'Invalid security token.');
            return;
        }
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJson(false, 'Access denied.');
            return;
        }

        $settings    = $this->loadSettings();
        $oauthToken  = trim((string) ($settings['gsc_api_token'] ?? ''));
        $siteUrl     = trim((string) ($settings['gsc_site_url']  ?? ''));

        if ($oauthToken === '' || $siteUrl === '') {
            $this->sendJson(false, 'GSC OAuth token or site URL not configured. Set them in Settings → Analytics tab.');
            return;
        }

        $urlsJson = $this->app->getInput()->getString('urls', '[]');
        $urls     = json_decode($urlsJson, true);
        if (!is_array($urls) || empty($urls)) {
            $this->sendJson(false, 'No URLs provided.');
            return;
        }

        // Fetch all pages GSC has seen via Search Analytics
        // Uses OAuth2 Bearer token (admin-provided access token from GSC)
        $gscPages = $this->fetchGscPages($oauthToken, $siteUrl);
        if ($gscPages === null) {
            $this->sendJson(
                false,
                'Failed to fetch data from Google Search Console. '
                . 'Make sure the OAuth access token is valid and the site property is verified. '
                . 'Note: access tokens expire after 1 hour — generate a fresh one from the Google API Console.'
            );
            return;
        }

        // Normalise GSC page URLs for comparison
        $gscNorm = [];
        foreach ($gscPages as $p) {
            $gscNorm[$this->normaliseUrl($p)] = true;
        }

        $notIndexed = [];
        foreach ($urls as $url) {
            if (!isset($gscNorm[$this->normaliseUrl($url)])) {
                $notIndexed[] = $url;
            }
        }

        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo json_encode([
            'success'     => true,
            'not_indexed' => $notIndexed,
            'gsc_total'   => count($gscPages),
            'message'     => count($notIndexed) . ' URL(s) not found in GSC Search Analytics data (last 16 months). '
                           . 'Pages with zero impressions may not appear even if indexed.',
        ]);
        $this->app->close();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Endpoint: startScan — kick off a background URL scan job
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * POST: urls (JSON-encoded array)
     * Returns { success, job_id }
     *
     * Inserts a row into #__aiboost_url_scans, flushes the HTTP response,
     * then continues processing in the same PHP process so the scan
     * survives the user navigating away or refreshing the admin page.
     *
     * On servers without fastcgi_finish_request() the request is still
     * processed with ignore_user_abort(true) + set_time_limit(0).
     */
    public function startScan(): void
    {
        if (!Session::checkToken()) { $this->sendJson(false, 'Invalid security token.'); return; }
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJson(false, 'Access denied.'); return;
        }

        $urlsJson = $this->app->getInput()->getString('urls', '[]');
        $urls     = json_decode($urlsJson, true);
        if (!is_array($urls) || empty($urls)) { $this->sendJson(false, 'No URLs provided.'); return; }
        $urls = array_values(array_filter(array_map('trim', $urls)));
        if (count($urls) > 2000) { $urls = array_slice($urls, 0, 2000); }

        try {
            $db  = Factory::getDbo();
            $now = Factory::getDate()->toSql();
            $row = (object) [
                'status'        => 'running',
                'total_urls'    => count($urls),
                'done_urls'     => 0,
                'current_url'   => '',
                'queue_json'    => json_encode($urls, JSON_UNESCAPED_UNICODE),
                'results_json'  => '[]',
                'error_message' => '',
                'started_at'    => $now,
                'finished_at'   => null,
                'updated_at'    => $now,
            ];
            $db->insertObject('#__aiboost_url_scans', $row, 'id');
            $jobId = (int) $row->id;

            // Trim history to last 10 scans
            $this->pruneScanHistory($db, 10);

            // Send HTTP response now, then keep PHP running to do the work.
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode(['success' => true, 'job_id' => $jobId]);
            $this->flushAndContinue();

            // ── Background worker ────────────────────────────────────────────
            @set_time_limit(0);
            ignore_user_abort(true);
            $this->runScanJob($jobId, $urls);
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning('[AiBoost] startScan failed: ' . $e->getMessage());
            $this->sendJson(false, 'Failed to start scan: ' . $e->getMessage());
        }
    }

    /**
     * GET: id
     * Returns the live status + accumulated results of a scan job.
     */
    public function scanStatus(): void
    {
        if (!Session::checkToken('request')) { $this->sendJson(false, 'Invalid security token.'); return; }
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJson(false, 'Access denied.'); return;
        }
        $id = (int) $this->app->getInput()->getInt('id', 0);
        if ($id <= 0) {
            // No id → return latest job (so UI auto-resumes after refresh)
            $row = $this->loadLatestScan();
        } else {
            $row = $this->loadScan($id);
        }
        if (!$row) { $this->sendJson(true, 'No scan found', ['job' => null]); return; }

        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo json_encode(['success' => true, 'job' => $this->normaliseScanRow($row)]);
        $this->app->close();
    }

    /**
     * GET: limit (default 10)
     * Returns recent scan history rows (no full results blob to keep it light).
     */
    public function scanHistory(): void
    {
        if (!Session::checkToken('request')) { $this->sendJson(false, 'Invalid security token.'); return; }
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJson(false, 'Access denied.'); return;
        }
        $limit = max(1, min(50, (int) $this->app->getInput()->getInt('limit', 10)));
        $db    = Factory::getDbo();
        $q     = $db->getQuery(true)
            ->select(['id', 'status', 'total_urls', 'done_urls', 'started_at', 'finished_at'])
            ->from('#__aiboost_url_scans')
            ->order('id DESC')
            ->setLimit($limit);
        $rows = $db->setQuery($q)->loadAssocList() ?: [];
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo json_encode(['success' => true, 'history' => $rows]);
        $this->app->close();
    }

    /**
     * POST: id — flag a running scan as cancelled. Worker checks between URLs.
     */
    public function cancelScan(): void
    {
        if (!Session::checkToken()) { $this->sendJson(false, 'Invalid security token.'); return; }
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJson(false, 'Access denied.'); return;
        }
        $id = (int) $this->app->getInput()->getInt('id', 0);
        if ($id <= 0) { $this->sendJson(false, 'Missing job id.'); return; }
        $db  = Factory::getDbo();
        $now = Factory::getDate()->toSql();
        $q   = $db->getQuery(true)
            ->update('#__aiboost_url_scans')
            ->set($db->quoteName('status') . '=' . $db->quote('cancelled'))
            ->set($db->quoteName('updated_at') . '=' . $db->quote($now))
            ->where($db->quoteName('id') . '=' . $id)
            ->where($db->quoteName('status') . '=' . $db->quote('running'));
        $db->setQuery($q)->execute();
        $this->sendJson(true, 'Cancellation requested.');
    }

    /**
     * POST: url, issue (one of: canonical_missing, canonical_mismatch,
     *       redirect_chain, not_found), expected (optional override)
     * Returns { success, message, result } where result is the re-scan output.
     */
    public function fixIssue(): void
    {
        if (!Session::checkToken()) { $this->sendJson(false, 'Invalid security token.'); return; }
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJson(false, 'Access denied.'); return;
        }

        $url      = trim((string) $this->app->getInput()->getString('url', ''));
        $issue    = trim((string) $this->app->getInput()->getString('issue', ''));
        $expected = trim((string) $this->app->getInput()->getString('expected', ''));
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL) || !$this->isFetchTargetAllowed($url)) {
            $this->sendJson(false, 'Invalid or non-public URL.'); return;
        }

        try {
            $message = '';
            switch ($issue) {
                case 'canonical_missing':
                case 'canonical_mismatch':
                    // Add an entry to canonical_url_map (JSON object: url => canonical)
                    $target = $expected !== '' ? $expected : $url;
                    $this->upsertCanonicalOverride($url, $target);
                    $message = 'Canonical override added → ' . $target;
                    break;

                case 'redirect_chain':
                    if ($expected === '') { $this->sendJson(false, 'Missing destination URL.'); return; }
                    $this->upsertRedirect($url, $expected, 301, 'Auto-added by URL Checker (redirect chain shortened)');
                    $message = 'Direct 301 added: ' . $url . ' → ' . $expected;
                    break;

                case 'not_found':
                    $dest = $expected !== '' ? $expected : \Joomla\CMS\Uri\Uri::root();
                    $this->upsertRedirect($url, $dest, 301, 'Auto-added by URL Checker (404 → homepage)');
                    $message = 'Redirect to ' . $dest . ' added.';
                    break;

                default:
                    $this->sendJson(false, 'Unsupported issue type: ' . $issue); return;
            }

            // Re-scan the single URL so the UI can show the updated result
            $result = $this->checkUrl($url);
            $this->sendJson(true, $message, ['result' => $result]);
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning('[AiBoost] fixIssue failed: ' . $e->getMessage());
            $this->sendJson(false, 'Fix failed: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private: background worker + fix helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Iterate the URL queue, persist progress after each URL. Honours
     * cancellation (re-reads the row's status periodically).
     */
    private function runScanJob(int $jobId, array $urls): void
    {
        $db      = Factory::getDbo();
        $results = [];
        $total   = count($urls);
        $cancelCheckEvery = 5;

        foreach ($urls as $i => $url) {
            // Periodic cancellation check
            if ($i % $cancelCheckEvery === 0) {
                $statusQ = $db->getQuery(true)
                    ->select('status')->from('#__aiboost_url_scans')
                    ->where($db->quoteName('id') . '=' . $jobId);
                $st = (string) $db->setQuery($statusQ)->loadResult();
                if ($st === 'cancelled') {
                    return; // exit early, leave row as 'cancelled'
                }
            }

            if (!filter_var($url, FILTER_VALIDATE_URL) || !$this->isFetchTargetAllowed($url)) {
                $results[] = [
                    'url'              => $url, 'status' => 0,
                    'redirect_chain'   => [], 'canonical' => null,
                    'canonical_status' => 'skipped', 'is_noindex' => false,
                    'is_thin_content'  => false, 'content_chars' => 0,
                    'error'            => 'Invalid or non-public URL',
                ];
            } else {
                $results[] = $this->checkUrl($url);
            }

            // Persist progress after every URL
            $now = Factory::getDate()->toSql();
            $q   = $db->getQuery(true)
                ->update('#__aiboost_url_scans')
                ->set($db->quoteName('done_urls') . '=' . ($i + 1))
                ->set($db->quoteName('current_url') . '=' . $db->quote(substr($url, 0, 1000)))
                ->set($db->quoteName('results_json') . '=' . $db->quote(json_encode($results, JSON_UNESCAPED_UNICODE)))
                ->set($db->quoteName('updated_at') . '=' . $db->quote($now))
                ->where($db->quoteName('id') . '=' . $jobId);
            try { $db->setQuery($q)->execute(); } catch (\Throwable $e) {
                \AiBoost\Lib\Logger::warning($e, ['where' => 'UrlcheckerController::progress(update)', 'job_id' => $jobId]);
            }
        }

        // Mark finished
        $now = Factory::getDate()->toSql();
        $q   = $db->getQuery(true)
            ->update('#__aiboost_url_scans')
            ->set($db->quoteName('status') . '=' . $db->quote('finished'))
            ->set($db->quoteName('finished_at') . '=' . $db->quote($now))
            ->set($db->quoteName('updated_at') . '=' . $db->quote($now))
            ->set($db->quoteName('current_url') . '=' . $db->quote(''))
            ->where($db->quoteName('id') . '=' . $jobId)
            ->where($db->quoteName('status') . '=' . $db->quote('running'));
        try { $db->setQuery($q)->execute(); } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning($e, ['where' => 'UrlcheckerController::progress(finalize)', 'job_id' => $jobId]);
        }
    }

    /**
     * Flush response to client and detach so the worker can continue.
     * Best-effort across PHP-FPM, FastCGI and mod_php.
     */
    private function flushAndContinue(): void
    {
        if (function_exists('session_write_close')) { @session_write_close(); }
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
            return;
        }
        // Fallback: best-effort flush + close output buffers.
        @ignore_user_abort(true);
        while (ob_get_level() > 0) { @ob_end_flush(); }
        @flush();
    }

    private function loadScan(int $id): ?object
    {
        $db = Factory::getDbo();
        $q  = $db->getQuery(true)->select('*')->from('#__aiboost_url_scans')
            ->where($db->quoteName('id') . '=' . $id);
        return $db->setQuery($q)->loadObject() ?: null;
    }

    private function loadLatestScan(): ?object
    {
        $db = Factory::getDbo();
        $q  = $db->getQuery(true)->select('*')->from('#__aiboost_url_scans')
            ->order('id DESC')->setLimit(1);
        return $db->setQuery($q)->loadObject() ?: null;
    }

    private function normaliseScanRow(object $row): array
    {
        $results = [];
        if (!empty($row->results_json)) {
            $decoded = json_decode($row->results_json, true);
            if (is_array($decoded)) { $results = $decoded; }
        }
        return [
            'id'           => (int) $row->id,
            'status'       => (string) $row->status,
            'total_urls'   => (int) $row->total_urls,
            'done_urls'    => (int) $row->done_urls,
            'current_url'  => (string) $row->current_url,
            'started_at'   => (string) $row->started_at,
            'finished_at'  => $row->finished_at ? (string) $row->finished_at : null,
            'updated_at'   => (string) $row->updated_at,
            'error'        => (string) $row->error_message,
            'results'      => $results,
        ];
    }

    private function pruneScanHistory(\Joomla\Database\DatabaseInterface $db, int $keep): void
    {
        try {
            // Delete everything older than the most-recent $keep rows
            $q = $db->getQuery(true)
                ->select('id')->from('#__aiboost_url_scans')
                ->order('id DESC')->setLimit($keep);
            $keepIds = $db->setQuery($q)->loadColumn() ?: [];
            if (empty($keepIds)) return;
            $list = implode(',', array_map('intval', $keepIds));
            $del  = $db->getQuery(true)
                ->delete('#__aiboost_url_scans')
                ->where($db->quoteName('id') . ' NOT IN (' . $list . ')');
            $db->setQuery($del)->execute();
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning($e, ['where' => 'UrlcheckerController::pruneScanHistory']);
        }
    }

    /**
     * Add / update a canonical_url_map entry inside the settings JSON blob.
     */
    private function upsertCanonicalOverride(string $url, string $canonical): void
    {
        $db = Factory::getDbo();
        $q  = $db->getQuery(true)->select('settings_json')->from('#__aiboost_settings')
            ->where($db->quoteName('setting_key') . '=' . $db->quote('main'));
        $raw      = (string) $db->setQuery($q)->loadResult();
        $settings = $raw !== '' ? (json_decode($raw, true) ?: []) : [];

        $mapRaw = $settings['canonical_url_map'] ?? '';
        if (is_string($mapRaw)) {
            $map = $mapRaw !== '' ? (json_decode($mapRaw, true) ?: []) : [];
        } elseif (is_array($mapRaw)) {
            $map = $mapRaw;
        } else {
            $map = [];
        }
        $map[$url] = $canonical;
        $settings['canonical_url_map'] = json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $now  = Factory::getDate()->toSql();
        $idQ  = $db->getQuery(true)->select('id')->from('#__aiboost_settings')
            ->where($db->quoteName('setting_key') . '=' . $db->quote('main'));
        $existingId = (int) $db->setQuery($idQ)->loadResult();
        if ($existingId) {
            $upd = $db->getQuery(true)->update('#__aiboost_settings')
                ->set($db->quoteName('settings_json') . '=' . $db->quote($json))
                ->set($db->quoteName('updated_at') . '=' . $db->quote($now))
                ->where($db->quoteName('id') . '=' . $existingId);
        } else {
            $upd = $db->getQuery(true)->insert('#__aiboost_settings')
                ->columns(['setting_key', 'settings_json', 'created_at', 'updated_at'])
                ->values($db->quote('main') . ',' . $db->quote($json) . ',' . $db->quote($now) . ',' . $db->quote($now));
        }
        $db->setQuery($upd)->execute();
    }

    /**
     * Add or update a redirect rule in #__aiboost_redirects.
     */
    private function upsertRedirect(string $fromUrl, string $toUrl, int $type, string $note): void
    {
        $db  = Factory::getDbo();
        $now = Factory::getDate()->toSql();
        $q   = $db->getQuery(true)->select('id')->from('#__aiboost_redirects')
            ->where($db->quoteName('from_url') . '=' . $db->quote($fromUrl));
        $existingId = (int) $db->setQuery($q)->loadResult();
        if ($existingId) {
            $upd = $db->getQuery(true)->update('#__aiboost_redirects')
                ->set($db->quoteName('to_url') . '=' . $db->quote($toUrl))
                ->set($db->quoteName('redirect_type') . '=' . $type)
                ->set($db->quoteName('enabled') . '=1')
                ->set($db->quoteName('note') . '=' . $db->quote($note))
                ->set($db->quoteName('updated_at') . '=' . $db->quote($now))
                ->where($db->quoteName('id') . '=' . $existingId);
            $db->setQuery($upd)->execute();
        } else {
            $row = (object) [
                'from_url'      => $fromUrl,
                'to_url'        => $toUrl,
                'redirect_type' => $type,
                'hits'          => 0,
                'enabled'       => 1,
                'note'          => $note,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
            $db->insertObject('#__aiboost_redirects', $row, 'id');
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private: URL check logic
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Fully check a single URL:
     * 1. Follow the complete redirect chain (up to 10 hops), capturing each status.
     * 2. On 200: GET first 64 KB of body → canonical, noindex, thin-content checks.
     */
    private function checkUrl(string $url): array
    {
        $result = [
            'url'              => $url,
            'status'           => 0,
            'redirect_chain'   => [],
            'canonical'        => null,
            'canonical_status' => 'skipped',
            'is_noindex'       => false,
            'is_thin_content'  => false,
            'content_chars'    => 0,
            'error'            => null,
        ];

        if (!function_exists('curl_init')) {
            $result['error'] = 'cURL not available on this server';
            return $result;
        }

        // ── Step 1: follow redirect chain manually (no CURLOPT_FOLLOWLOCATION) ─
        $chain        = $this->followRedirectChain($url);
        $result['redirect_chain'] = $chain;

        if (empty($chain)) {
            $result['error'] = 'No response from server';
            return $result;
        }

        $first = $chain[0];
        $last  = end($chain);

        $result['status'] = $first['status'];  // first-hop status (may be 3xx)

        if ($first['status'] >= 400 || $first['status'] === 0) {
            if ($first['status'] === 0) {
                $result['error'] = $first['error'] ?? 'Connection failed';
            }
            return $result;
        }

        // For redirect chains (3xx first hop), we only do content analysis on the final destination
        $targetUrl    = $last['url'];
        $targetStatus = $last['status'];

        // ── Step 2: GET body for content analysis (200 responses only) ────────
        if ($targetStatus !== 200) {
            return $result;
        }

        $responseHeaders = '';
        $body            = $this->fetchBodyWithHeaders($targetUrl, $responseHeaders);

        if ($body === null) {
            return $result;
        }

        // Check X-Robots-Tag header for noindex
        if (preg_match('/^X-Robots-Tag:[^\r\n]*noindex/im', $responseHeaders)) {
            $result['is_noindex'] = true;
        }

        // Check <meta name="robots" content="noindex"> in body
        if (preg_match('/<meta[^>]+name=["\']robots["\'][^>]*>/i', $body, $metaMatch)) {
            if (preg_match('/content=["\'][^"\']*noindex/i', $metaMatch[0])) {
                $result['is_noindex'] = true;
            }
        }

        // Extract canonical
        $canonical = $this->extractCanonical($body);
        $result['canonical'] = $canonical;

        if ($canonical === null) {
            $result['canonical_status'] = 'missing';
        } else {
            $result['canonical_status'] = (
                $this->normaliseUrl($canonical) === $this->normaliseUrl($targetUrl)
            ) ? 'ok' : 'mismatch';
        }

        // Thin-content check: strip HTML tags, measure visible text length
        $text = trim(strip_tags($body));
        $text = preg_replace('/\s+/', ' ', $text);
        $chars = mb_strlen($text);
        $result['content_chars']   = $chars;
        $result['is_thin_content'] = ($chars < 300);

        return $result;
    }

    /**
     * Follow a redirect chain step by step (one HEAD request per hop).
     * Returns array of ['url' => ..., 'status' => ..., 'error' => null|string].
     * Max 10 hops to prevent infinite loops.
     */
    private function followRedirectChain(string $startUrl): array
    {
        $chain   = [];
        $current = $startUrl;
        $visited = [];

        for ($hop = 0; $hop < 10; $hop++) {
            // SSRF guard: re-validate every hop so an external 3xx cannot
            // redirect the fetch to an internal/private address.
            if (!$this->isFetchTargetAllowed($current)) {
                $chain[] = ['url' => $current, 'status' => 0, 'error' => 'Blocked: non-public address'];
                break;
            }
            if (isset($visited[$current])) {
                // Redirect loop detected — stop here
                $chain[] = ['url' => $current, 'status' => 0, 'error' => 'Redirect loop detected'];
                break;
            }
            $visited[$current] = true;

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $current,
                CURLOPT_NOBODY         => true,
                CURLOPT_FOLLOWLOCATION => false,   // manual hop-by-hop
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER         => true,
                CURLOPT_USERAGENT      => 'AI Boost URL Checker/1.0 (aiboostnow.com)',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $headerStr = (string) curl_exec($ch);
            $status    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_errno($ch) ? curl_error($ch) : null;
            // curl_close() intentionally omitted — deprecated no-op since PHP 8.0
            // that emits an E_DEPRECATED notice on 8.5 (pollutes the JSON body).

            if ($curlError) {
                $chain[] = ['url' => $current, 'status' => 0, 'error' => $curlError];
                break;
            }

            $chain[] = ['url' => $current, 'status' => $status, 'error' => null];

            // Stop following if not a redirect
            if ($status < 300 || $status >= 400) {
                break;
            }

            // Extract Location for next hop
            if (!preg_match('/^Location:\s*(.+)$/im', $headerStr, $m)) {
                break;
            }
            $next = trim($m[1]);
            if ($next === '' || $next === $current) {
                break;
            }

            // Handle relative Location headers
            if (!preg_match('#^https?://#i', $next)) {
                $parts = parse_url($current);
                if ($parts !== false) {
                    $next = ($parts['scheme'] ?? 'https') . '://'
                          . ($parts['host'] ?? '')
                          . (str_starts_with($next, '/') ? $next : '/' . $next);
                }
            }

            $current = $next;
        }

        return $chain;
    }

    /**
     * Fetch the first 64 KB of a URL's response body, also capturing response headers.
     * $responseHeaders is filled by reference.
     */
    private function fetchBodyWithHeaders(string $url, string &$responseHeaders): ?string
    {
        // Defence in depth: the URL arrives from an already-validated redirect
        // chain, but never fetch a non-public target.
        if (!$this->isFetchTargetAllowed($url)) {
            $responseHeaders = '';
            return null;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_USERAGENT      => 'AI Boost URL Checker/1.0 (aiboostnow.com)',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_RANGE          => '0-65535',
        ]);
        $raw       = curl_exec($ch);
        $headerLen = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $errno     = curl_errno($ch);
        // curl_close() omitted — deprecated no-op since PHP 8.0.

        if ($errno || $raw === false) {
            $responseHeaders = '';
            return null;
        }

        $raw             = (string) $raw;
        $responseHeaders = substr($raw, 0, $headerLen);
        return substr($raw, $headerLen);
    }

    /**
     * Extract canonical href from HTML body.
     */
    private function extractCanonical(string $html): ?string
    {
        if (!preg_match('/<link[^>]+rel=["\']canonical["\'][^>]*>/i', $html, $m)) {
            return null;
        }
        if (!preg_match('/href=["\']([^"\']+)["\']/', $m[0], $href)) {
            return null;
        }
        $val = trim($href[1]);
        return $val !== '' ? $val : null;
    }

    /**
     * Normalise a URL for comparison: lowercase scheme+host, strip trailing slash.
     */
    private function normaliseUrl(string $url): string
    {
        if (preg_match('#^(https?://)([^/]+)(.*)$#i', $url, $m)) {
            $url = strtolower($m[1]) . strtolower($m[2]) . $m[3];
        }
        return rtrim($url, '/');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private: GSC helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Fetch all pages from GSC Search Analytics API using an OAuth2 Bearer token.
     * Paginates through all results (rows up to 25 000 per request).
     *
     * @return string[]|null  Array of page URLs, or null on API error.
     */
    private function fetchGscPages(string $oauthToken, string $siteUrl): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $endpoint = 'https://www.googleapis.com/webmasters/v3/sites/'
                  . urlencode($siteUrl)
                  . '/searchAnalytics/query';

        $pages    = [];
        $startRow = 0;
        $rowLimit = 5000;

        do {
            $payload = json_encode([
                'startDate'  => date('Y-m-d', strtotime('-16 months')),
                'endDate'    => date('Y-m-d'),
                'dimensions' => ['page'],
                'rowLimit'   => $rowLimit,
                'startRow'   => $startRow,
            ]);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $endpoint,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $oauthToken,  // OAuth2 Bearer token
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_USERAGENT      => 'AI Boost URL Checker/1.0 (aiboostnow.com)',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $body  = (string) curl_exec($ch);
            $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errno = curl_errno($ch);
            // curl_close() omitted — deprecated no-op since PHP 8.0.

            if ($errno || $code !== 200) {
                return $startRow > 0 ? $pages : null;
            }

            $data = json_decode($body, true);
            if (!is_array($data) || empty($data['rows'])) {
                break;
            }

            foreach ($data['rows'] as $row) {
                if (!empty($row['keys'][0])) {
                    $pages[] = $row['keys'][0];
                }
            }

            $count    = count($data['rows']);
            $startRow += $count;
        } while ($count === $rowLimit);

        return $pages;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private: DB + response helpers
    // ─────────────────────────────────────────────────────────────────────────

    // ─────────────────────────────────────────────────────────────────────────
    // Private: SSRF guard
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * SSRF guard — only permit fetching public http(s) URLs.
     *
     * The URL Checker must never let an authenticated admin (or a same-origin
     * XSS riding their session) coerce the server into requesting internal
     * services. The site's OWN host is always allowed (intranet/staging
     * installs legitimately scan their own pages); for any other host every
     * resolved IP must be a routable public address, so loopback, private
     * (RFC1918), link-local and reserved ranges — including 169.254.169.254
     * cloud metadata — are rejected. Re-checked on every redirect hop so an
     * external 3xx cannot pivot the fetch inward.
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
        if (isset($this->fetchTargetCache[$host])) {
            return $this->fetchTargetCache[$host];
        }

        // The site's own host is always allowed (its front-end may resolve to a
        // private address on intranet/staging installs).
        $siteHost = strtolower((string) parse_url(\Joomla\CMS\Uri\Uri::root(), PHP_URL_HOST));
        if ($siteHost !== '' && $host === $siteHost) {
            return $this->fetchTargetCache[$host] = true;
        }

        // Resolve to IP(s); every candidate must be a public address.
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
            // Unresolvable host — fail closed.
            return $this->fetchTargetCache[$host] = false;
        }
        foreach ($ips as $ip) {
            if (!$this->isPublicIp((string) $ip)) {
                return $this->fetchTargetCache[$host] = false;
            }
        }
        return $this->fetchTargetCache[$host] = true;
    }

    /**
     * True only for a routable public IP. Rejects loopback, private (RFC1918),
     * link-local, unique-local and other reserved ranges for IPv4 and IPv6.
     */
    private function isPublicIp(string $ip): bool
    {
        // IPv4 — PHP's reserved+private flags cover 0/8, 10/8, 127/8,
        // 169.254/16, 172.16/12, 192.168/16, 240/4, etc.
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) !== false;
        }
        // IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv4-mapped / -compatible (::ffff:a.b.c.d) — validate the v4 part.
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
                return false; // loopback / unspecified
            }
            $first = ord($bin[0]);
            if (($first & 0xFE) === 0xFC) {
                return false; // fc00::/7 unique-local
            }
            if ($first === 0xFE && (ord($bin[1]) & 0xC0) === 0x80) {
                return false; // fe80::/10 link-local
            }
            return true;
        }
        return false;
    }

    private function loadSettings(): array
    {
        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName('settings_json'))
                ->from('#__aiboost_settings')
                ->where($db->quoteName('setting_key') . '=' . $db->quote('main'));
            $json  = (string) $db->setQuery($query)->loadResult();
            if (empty($json)) {
                return [];
            }
            $decoded = json_decode($json, true);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function sendJson(bool $success, string $message, array $extra = []): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
        $this->app->close();
    }
}
