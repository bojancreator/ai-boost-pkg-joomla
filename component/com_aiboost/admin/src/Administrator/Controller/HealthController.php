<?php
/**
 * AI Boost — Health Controller
 * Handles AJAX re-run and dismiss endpoints for the Health tab.
 *
 * @package     AiBoost\Component\AiBoost\Administrator\Controller
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Component\AiBoost\Administrator\Controller;

defined('_JEXEC') or die;

use AiBoost\Lib\HealthCheckService;
use AiBoost\Lib\JoomlaAppContext;
use Joomla\CMS\Factory;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

class HealthController extends BaseController
{
    /**
     * AJAX: re-run all health checks and return JSON.
     * URL: index.php?option=com_aiboost&task=health.rerun&format=json
     */
    public function rerun(): void
    {
        if (!Session::checkToken()) {
            $this->sendJson(false, 'Invalid security token.', []);
            return;
        }

        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJson(false, 'Access denied.', []);
            return;
        }

        try {
            $settings = $this->loadSettings();
            $service  = new HealthCheckService($settings, Factory::getDbo(), new JoomlaAppContext());
            $result   = $service->run();

            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'score'   => $result['score'],
                'checks'  => $result['checks'],
            ], JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}';
            $this->app->close();
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning('[AiBoost] Health rerun error: ' . $e->getMessage());
            $this->sendJson(false, 'Error running health checks.', []);
        }
    }

    /**
     * AJAX: dismiss or un-dismiss a health check.
     * URL: index.php?option=com_aiboost&task=health.dismiss&format=json
     * POST params: check_id (string), action ('dismiss'|'restore')
     */
    public function dismiss(): void
    {
        if (!Session::checkToken()) {
            $this->sendJson(false, 'Invalid security token.', []);
            return;
        }

        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJson(false, 'Access denied.', []);
            return;
        }

        try {
            $input   = $this->app->getInput();
            $checkId = preg_replace('/[^a-z0-9_]/', '', $input->getString('check_id', ''));
            $action  = $input->getString('action', 'dismiss') === 'restore' ? 'restore' : 'dismiss';

            if ($checkId === '') {
                $this->sendJson(false, 'Invalid check ID.', []);
                return;
            }

            $db       = Factory::getDbo();
            $query    = $db->getQuery(true)
                ->select($db->quoteName('settings_json'))
                ->from('#__aiboost_settings')
                ->where($db->quoteName('setting_key') . '=' . $db->quote('main'));
            $json     = (string) $db->setQuery($query)->loadResult();
            $settings = $json ? (array) (json_decode($json, true) ?? []) : [];

            $dismissed = json_decode((string) ($settings['dismissed_checks'] ?? '[]'), true);
            if (!is_array($dismissed)) {
                $dismissed = [];
            }

            if ($action === 'dismiss') {
                if (!in_array($checkId, $dismissed, true)) {
                    $dismissed[] = $checkId;
                }
            } else {
                $dismissed = array_values(array_filter($dismissed, fn($id) => $id !== $checkId));
            }

            $settings['dismissed_checks'] = json_encode(array_values($dismissed));

            $now         = Factory::getDate()->toSql();
            $settingsJson = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            $query2 = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from('#__aiboost_settings')
                ->where($db->quoteName('setting_key') . '=' . $db->quote('main'));
            $existingId = (int) $db->setQuery($query2)->loadResult();

            if ($existingId) {
                $update = $db->getQuery(true)
                    ->update('#__aiboost_settings')
                    ->set($db->quoteName('settings_json') . '=' . $db->quote($settingsJson))
                    ->set($db->quoteName('updated_at') . '=' . $db->quote($now))
                    ->where($db->quoteName('id') . '=' . $existingId);
                $db->setQuery($update)->execute();
            } else {
                $insert = $db->getQuery(true)
                    ->insert('#__aiboost_settings')
                    ->columns(['setting_key', 'settings_json', 'created_at', 'updated_at'])
                    ->values($db->quote('main') . ',' . $db->quote($settingsJson) . ',' . $db->quote($now) . ',' . $db->quote($now));
                $db->setQuery($insert)->execute();
            }

            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode(['success' => true, 'dismissed' => $dismissed]);
            $this->app->close();
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning('[AiBoost] Health dismiss error: ' . $e->getMessage());
            $this->sendJson(false, 'Error saving dismiss state.', []);
        }
    }

    /**
     * AJAX: server-side live scan.
     * Fetches one or more URLs (must live under the site root — SSRF protection),
     * returns raw HTML head + response headers for the Vue Health tab to parse.
     *
     * URL: index.php?option=com_aiboost&task=health.scan&format=json
     * POST params:
     *   urls[] = string (1..3 absolute URLs; default = site root)
     */
    public function scan(): void
    {
        if (!Session::checkToken()) {
            $this->sendJson(false, 'Invalid security token.', []);
            return;
        }

        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJson(false, 'Access denied.', []);
            return;
        }

        try {
            $input = $this->app->getInput();
            $raw   = $input->get('urls', [], 'array');
            $urls  = [];
            foreach ((array) $raw as $u) {
                $u = trim((string) $u);
                if ($u !== '') {
                    $urls[] = $u;
                }
            }
            if (empty($urls)) {
                $urls = [Uri::root()];
            }
            if (count($urls) > 3) {
                $urls = array_slice($urls, 0, 3);
            }

            $siteRoot = Uri::root();
            // SSRF hardening: follow_location is DISABLED so the HTTP client
            // never silently chases a 3xx Location: header off-origin. We
            // re-validate every hop ourselves via fetchWithSafeRedirects().
            $http  = HttpFactory::getHttp(['follow_location' => false, 'timeout' => 15]);
            $pages = [];

            foreach ($urls as $url) {
                if (!$this->isUnderSiteRoot($url, $siteRoot)) {
                    $pages[] = [
                        'url'         => $url,
                        'ok'          => false,
                        'error'       => 'URL is not under this site root (SSRF protection).',
                        'status'      => 0,
                        'headers'     => new \stdClass(),
                        'html'        => '',
                    ];
                    continue;
                }
                try {
                    $res = $this->fetchWithSafeRedirects($http, $url, $siteRoot, 3, true);
                    $body    = (string) ($res->body ?? '');
                    $code    = (int) ($res->code ?? 0);
                    $headers = [];
                    foreach ((array) ($res->headers ?? []) as $k => $v) {
                        $headers[strtolower((string) $k)] = is_array($v) ? implode(', ', $v) : (string) $v;
                    }
                    $pages[] = [
                        'url'     => $url,
                        'ok'      => $code >= 200 && $code < 400,
                        'status'  => $code,
                        'headers' => (object) $headers,
                        'html'    => $body,
                        'bytes'   => strlen($body),
                    ];
                } catch (\Throwable $e) {
                    $pages[] = [
                        'url'     => $url,
                        'ok'      => false,
                        'status'  => 0,
                        'error'   => $e->getMessage(),
                        'headers' => new \stdClass(),
                        'html'    => '',
                    ];
                }
            }

            // Probe known files at the site root path (handles subfolder installs)
            $u            = parse_url($siteRoot);
            $basePath     = isset($u['path']) ? rtrim($u['path'], '/') : '';
            $origin       = ($u['scheme'] ?? 'https') . '://' . ($u['host'] ?? '');
            if (!empty($u['port'])) {
                $origin .= ':' . $u['port'];
            }
            $probeBase = $origin . $basePath;
            $probes    = ['/llms.txt', '/llms-full.txt', '/robots.txt', '/sitemap.xml'];
            $reachable = [];
            foreach ($probes as $p) {
                try {
                    $r = $this->fetchWithSafeRedirects($http, $probeBase . $p, $siteRoot, 3, false);
                    $ct = '';
                    foreach ((array) ($r->headers ?? []) as $hk => $hv) {
                        if (strtolower((string) $hk) === 'content-type') {
                            $ct = is_array($hv) ? implode(', ', $hv) : (string) $hv;
                            break;
                        }
                    }
                    $reachable[$p] = [
                        'ok'          => ($r->code ?? 0) >= 200 && ($r->code ?? 0) < 400,
                        'status'      => (int) ($r->code ?? 0),
                        'contentType' => $ct,
                    ];
                } catch (\Throwable $e) {
                    $reachable[$p] = ['ok' => false, 'status' => 0, 'contentType' => '', 'error' => $e->getMessage()];
                }
            }

            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode([
                'success'   => true,
                'pages'     => $pages,
                'reachable' => $reachable,
                'siteRoot'  => $siteRoot,
            ], JSON_INVALID_UTF8_SUBSTITUTE);
            $this->app->close();
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning('[AiBoost] Health scan error: ' . $e->getMessage());
            $this->sendJson(false, 'Scan failed: ' . $e->getMessage(), []);
        }
    }

    /**
     * SSRF guard: URL must use http/https and resolve under the configured
     * site root (same scheme, host, port and base-path prefix). Uses parsed
     * components instead of string-prefix matching so trailing-slash edge
     * cases and case-insensitive hosts are normalised properly.
     */
    private function isUnderSiteRoot(string $url, string $siteRoot): bool
    {
        $p = parse_url($url);
        $r = parse_url($siteRoot);
        if (!$p || !$r) return false;
        $scheme = strtolower($p['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true)) return false;
        if ($scheme !== strtolower($r['scheme'] ?? '')) return false;
        if (strtolower($p['host'] ?? '') !== strtolower($r['host'] ?? '')) return false;
        $defaultPort = $scheme === 'https' ? 443 : 80;
        $pPort = (int) ($p['port'] ?? $defaultPort);
        $rPort = (int) ($r['port'] ?? $defaultPort);
        if ($pPort !== $rPort) return false;
        $rootPath = rtrim($r['path'] ?? '/', '/') . '/';
        $urlPath  = ($p['path'] ?? '/');
        // urlPath must start with rootPath (treat empty paths as '/')
        return strpos($urlPath . '/', $rootPath) === 0
            || $urlPath === rtrim($rootPath, '/')
            || $urlPath . '/' === $rootPath;
    }

    /**
     * Fetch with manual redirect handling — re-validates each hop against
     * the site root to prevent SSRF via open redirects. Returns the final
     * response; throws on too many redirects or off-origin redirect.
     *
     * @param object $http      Joomla HTTP transport
     * @param string $url       Initial URL (already validated by caller)
     * @param string $siteRoot  Allowed origin/base
     * @param int    $maxHops   Maximum redirect hops to follow
     * @param bool   $asHtml    Set Accept header to text/html when true
     */
    private function fetchWithSafeRedirects(object $http, string $url, string $siteRoot, int $maxHops, bool $asHtml): object
    {
        $current = $url;
        for ($hop = 0; $hop <= $maxHops; $hop++) {
            $opts = [
                'User-Agent' => 'AiBoost-Health/1.0 (+' . $siteRoot . ')',
                'Accept'     => $asHtml ? 'text/html,application/xhtml+xml' : '*/*',
            ];
            $res  = $http->get($current, $opts);
            $code = (int) ($res->code ?? 0);
            if ($code < 300 || $code >= 400) {
                return $res;
            }
            // Resolve Location header (case-insensitive)
            $loc = null;
            foreach ((array) ($res->headers ?? []) as $hk => $hv) {
                if (strtolower((string) $hk) === 'location') {
                    $loc = is_array($hv) ? (string) reset($hv) : (string) $hv;
                    break;
                }
            }
            if ($loc === null || $loc === '') {
                return $res; // 3xx with no Location — return as-is
            }
            // Resolve relative Location against the current URL
            $next = $this->resolveRelativeUrl($current, $loc);
            if (!$this->isUnderSiteRoot($next, $siteRoot)) {
                throw new \RuntimeException('Redirect to off-origin URL blocked: ' . $next);
            }
            $current = $next;
        }
        throw new \RuntimeException('Too many redirects (max ' . $maxHops . ').');
    }

    private function resolveRelativeUrl(string $base, string $rel): string
    {
        if (preg_match('#^https?://#i', $rel)) return $rel;
        $b = parse_url($base);
        if (!$b) return $rel;
        $scheme = $b['scheme'] ?? 'https';
        $host   = $b['host'] ?? '';
        $port   = isset($b['port']) ? ':' . $b['port'] : '';
        $origin = $scheme . '://' . $host . $port;
        if (strpos($rel, '//') === 0) return $scheme . ':' . $rel;
        if (strpos($rel, '/') === 0)  return $origin . $rel;
        $basePath = isset($b['path']) ? preg_replace('#/[^/]*$#', '/', $b['path']) : '/';
        return $origin . $basePath . $rel;
    }

    // ─────────────────────────────────────────────────────────────────────────

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
            \AiBoost\Lib\Logger::warning($e, ['where' => 'HealthController::loadSettings']);
            return [];
        }
    }

    private function sendJson(bool $success, string $message, array $extra = []): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra), JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}';
        $this->app->close();
    }
}
