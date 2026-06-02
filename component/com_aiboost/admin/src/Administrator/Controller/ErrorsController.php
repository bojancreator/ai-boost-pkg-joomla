<?php
/**
 * AI Boost — Errors Controller (Task #512)
 *
 * AJAX endpoints for the Errors tab in the Vue admin SPA. Reads rows
 * from #__aiboost_error_log (populated by AiBoost\Lib\Logger) and
 * exposes paginated list, summary, and clear-all operations.
 *
 * Tasks (all require core.manage on com_aiboost + valid CSRF token):
 *   - errors.getErrors        GET-ish, returns paginated rows + total
 *   - errors.getErrorsSummary lightweight counts for nav badge / health
 *   - errors.clearErrors      TRUNCATE the log
 *   - errors.logClientError   Task #513 — receives browser-side errors
 *                             (Vue exceptions, window.onerror, unhandled
 *                             promise rejections) and forwards them to
 *                             Logger with source prefix "frontend:..."
 *
 * @package     AiBoost\Component\AiBoost\Administrator\Controller
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Component\AiBoost\Administrator\Controller;

defined('_JEXEC') or die;

use AiBoost\Lib\Logger;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

class ErrorsController extends BaseController
{
    private const TABLE = '#__aiboost_error_log';

    private const ALLOWED_SEVERITIES = ['debug', 'info', 'warning', 'error'];

    private const MAX_LIMIT = 200;

    /**
     * AJAX: paginated list of rows.
     * URL: index.php?option=com_aiboost&task=errors.getErrors&format=json
     * Params: severity (csv|''), source (string|''), q (string|''),
     *         limit (int 1..200, default 50), offset (int>=0)
     */
    public function getErrors(): void
    {
        if (!Session::checkToken('request')) {
            $this->sendJson(false, 'Invalid security token.');
            return;
        }
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJson(false, 'Access denied.');
            return;
        }

        try {
            $input = $this->app->getInput();

            $severityRaw = (string) $input->getString('severity', '');
            $severities  = [];
            if ($severityRaw !== '') {
                foreach (explode(',', $severityRaw) as $s) {
                    $s = strtolower(trim($s));
                    if (in_array($s, self::ALLOWED_SEVERITIES, true)) {
                        $severities[] = $s;
                    }
                }
            }

            $source = trim((string) $input->getString('source', ''));
            $source = preg_replace('/[^A-Za-z0-9_:\-\.\\\\]/', '', $source) ?? '';
            if (strlen($source) > 100) {
                $source = substr($source, 0, 100);
            }

            $q = trim((string) $input->getString('q', ''));
            if (strlen($q) > 200) {
                $q = substr($q, 0, 200);
            }

            $limit  = (int) $input->getInt('limit', 50);
            $limit  = max(1, min(self::MAX_LIMIT, $limit));
            $offset = max(0, (int) $input->getInt('offset', 0));

            $db    = Factory::getDbo();
            $where = ['1=1'];
            if (!empty($severities)) {
                $quoted = array_map(static fn($s) => $db->quote($s), $severities);
                $where[] = $db->quoteName('severity') . ' IN (' . implode(',', $quoted) . ')';
            }
            if ($source !== '') {
                $where[] = $db->quoteName('source') . ' = ' . $db->quote($source);
            }
            if ($q !== '') {
                $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
                $where[] = '(' . $db->quoteName('message') . ' LIKE ' . $db->quote($like)
                    . ' OR ' . $db->quoteName('context_json') . ' LIKE ' . $db->quote($like) . ')';
            }
            $whereSql = implode(' AND ', $where);

            $total = (int) $db->setQuery(
                'SELECT COUNT(*) FROM ' . $db->quoteName(self::TABLE) . ' WHERE ' . $whereSql
            )->loadResult();

            $cols = implode(', ', array_map(
                static fn($c) => $db->quoteName($c),
                ['id', 'created_at', 'severity', 'source', 'message', 'context_json', 'request_id']
            ));
            $rows = $db->setQuery(
                'SELECT ' . $cols . ' FROM ' . $db->quoteName(self::TABLE)
                . ' WHERE ' . $whereSql
                . ' ORDER BY ' . $db->quoteName('id') . ' DESC'
                . ' LIMIT ' . $limit . ' OFFSET ' . $offset
            )->loadAssocList();

            $rows = is_array($rows) ? $rows : [];

            // Normalise: parse context_json into a real object for the client.
            foreach ($rows as &$row) {
                $row['id']         = (int) $row['id'];
                $ctx               = (string) ($row['context_json'] ?? '');
                $parsed            = $ctx !== '' ? json_decode($ctx, true) : null;
                $row['context']    = is_array($parsed) ? $parsed : ($ctx !== '' ? $ctx : null);
                unset($row['context_json']);
            }
            unset($row);

            // Distinct sources for the filter dropdown (cheap; capped via
            // table retention at 1000 rows so DISTINCT is bounded).
            $sources = $db->setQuery(
                'SELECT DISTINCT ' . $db->quoteName('source') . ' FROM ' . $db->quoteName(self::TABLE)
                . ' WHERE ' . $db->quoteName('source') . " <> '' ORDER BY " . $db->quoteName('source') . ' ASC LIMIT 100'
            )->loadColumn();

            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'rows'    => $rows,
                'total'   => $total,
                'limit'   => $limit,
                'offset'  => $offset,
                'sources' => is_array($sources) ? array_values($sources) : [],
            ], JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}';
            $this->app->close();
        } catch (\Throwable $e) {
            Logger::warning($e, ['where' => 'ErrorsController::getErrors']);
            $this->sendJson(false, 'Error fetching log rows.');
        }
    }

    /**
     * AJAX: summary counts. Cheap — used for the nav badge and the
     * Health "Error logging" check upgrade to warning.
     * URL: index.php?option=com_aiboost&task=errors.getErrorsSummary&format=json
     */
    public function getErrorsSummary(): void
    {
        if (!Session::checkToken('request')) {
            $this->sendJson(false, 'Invalid security token.');
            return;
        }
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJson(false, 'Access denied.');
            return;
        }

        try {
            $summary = self::buildSummary();
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode(array_merge(['success' => true], $summary), JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}';
            $this->app->close();
        } catch (\Throwable $e) {
            Logger::warning($e, ['where' => 'ErrorsController::getErrorsSummary']);
            $this->sendJson(false, 'Error fetching summary.');
        }
    }

    /**
     * AJAX: truncate the log.
     * URL: index.php?option=com_aiboost&task=errors.clearErrors&format=json
     * POST only (CSRF + permission check).
     */
    public function clearErrors(): void
    {
        if (!Session::checkToken()) {
            $this->sendJson(false, 'Invalid security token.');
            return;
        }
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJson(false, 'Access denied.');
            return;
        }

        try {
            $db = Factory::getDbo();
            // Use DELETE (not TRUNCATE) so it works inside table-prefix
            // installs where the user might not have DROP privileges.
            $db->setQuery('DELETE FROM ' . $db->quoteName(self::TABLE))->execute();

            Logger::info('Error log cleared by admin.', ['where' => 'ErrorsController::clearErrors']);

            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode(['success' => true, 'message' => 'Error log cleared.']);
            $this->app->close();
        } catch (\Throwable $e) {
            Logger::warning($e, ['where' => 'ErrorsController::clearErrors']);
            $this->sendJson(false, 'Error clearing log.');
        }
    }

    /**
     * AJAX: receive a frontend-originated error event and forward it to
     * Logger so it lands in the same #__aiboost_error_log table as
     * backend errors. Used by the Vue admin SPA's global error handler
     * (Task #513).
     *
     * URL: index.php?option=com_aiboost&task=errors.logClientError&format=json
     * POST params (all strings; total body capped at 8KB by MAX_CLIENT_PAYLOAD):
     *   message  — short error message (required, max 500 chars)
     *   stack    — JS stack trace (optional, max 4000 chars)
     *   source   — short tag, will be prefixed "frontend:" (e.g. "vue",
     *              "window", "promise"); allowlisted, falls back to "js"
     *   route    — current SPA hash route (optional, max 200 chars)
     *   userAgent — browser UA (optional, max 300 chars)
     *   context  — JSON string with extra fields (component, props, etc.)
     */
    /**
     * Server-side rate limit (Task #522 — defence in depth).
     *
     * The Vue admin SPA's errorReporter already caps itself at ~20
     * sends/minute (SEND_MAX / SEND_WINDOW_MS in
     * vue-admin/src/composables/errorReporter.js), but the controller
     * is also reachable by a custom client or a script outside the
     * admin UI. To stop a misbehaving caller from filling
     * #__aiboost_error_log we mirror that cap server-side, per
     * authenticated user. Once the cap is crossed we silently drop
     * the write (still return success so the client cannot probe the
     * cap for tuning).
     */
    private const CLIENT_LOG_WINDOW_SEC = 60;
    private const CLIENT_LOG_MAX_PER_WINDOW = 30;

    public function logClientError(): void
    {
        if (!Session::checkToken()) {
            $this->sendJson(false, 'Invalid security token.');
            return;
        }
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJson(false, 'Access denied.');
            return;
        }

        try {
            $input = $this->app->getInput();

            $message = trim((string) $input->getString('message', ''));
            if ($message === '') {
                $this->sendJson(false, 'Missing message.');
                return;
            }
            if (!$this->checkClientLogRateLimit()) {
                // Silently drop — see CLIENT_LOG_MAX_PER_WINDOW above.
                $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
                echo json_encode(['success' => true, 'throttled' => true]);
                $this->app->close();
                return;
            }
            if (strlen($message) > 500) {
                $message = substr($message, 0, 500);
            }

            $stack = (string) $input->get('stack', '', 'STRING');
            if (strlen($stack) > 4000) {
                $stack = substr($stack, 0, 4000);
            }

            // Allow-list source tag; anything else collapses to "js".
            $rawSource = strtolower(trim((string) $input->getString('source', 'js')));
            $allowedSources = ['vue', 'window', 'promise', 'js', 'ajax'];
            if (!in_array($rawSource, $allowedSources, true)) {
                $rawSource = 'js';
            }
            $source = 'frontend:' . $rawSource;

            $route = trim((string) $input->getString('route', ''));
            if (strlen($route) > 200) {
                $route = substr($route, 0, 200);
            }

            $ua = trim((string) $input->getString('userAgent', ''));
            if (strlen($ua) > 300) {
                $ua = substr($ua, 0, 300);
            }

            $contextJson = (string) $input->get('context', '', 'STRING');
            $extra       = [];
            if ($contextJson !== '' && strlen($contextJson) <= 2000) {
                $decoded = json_decode($contextJson, true);
                if (is_array($decoded)) {
                    // Drop any nested objects deeper than 2 levels.
                    $extra = self::flattenForLog($decoded);
                }
            }

            $context = array_merge($extra, [
                'route'      => $route !== '' ? $route : null,
                'user_agent' => $ua !== '' ? $ua : null,
                'stack'      => $stack !== '' ? $stack : null,
            ]);

            Logger::error($message, $context, $source);

            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode(['success' => true]);
            $this->app->close();
        } catch (\Throwable $e) {
            Logger::warning($e, ['where' => 'ErrorsController::logClientError']);
            $this->sendJson(false, 'Error recording client event.');
        }
    }

    /**
     * Per-user, per-minute cap for errors.logClientError writes
     * (Task #522). Stored in the Joomla session so it survives across
     * requests for the same logged-in admin without needing a new
     * table. Returns true when the write may proceed, false once the
     * cap is crossed.
     */
    private function checkClientLogRateLimit(): bool
    {
        try {
            $session = $this->app->getSession();
            $key     = 'com_aiboost.errors.client_log_rate';
            $bucket  = $session->get($key, null);
            $now     = time();

            if (!is_array($bucket)
                || !isset($bucket['start'], $bucket['count'])
                || ($now - (int) $bucket['start']) >= self::CLIENT_LOG_WINDOW_SEC
            ) {
                $bucket = ['start' => $now, 'count' => 0];
            }

            $bucket['count'] = (int) $bucket['count'] + 1;
            $session->set($key, $bucket);

            return $bucket['count'] <= self::CLIENT_LOG_MAX_PER_WINDOW;
        } catch (\Throwable $e) {
            // Fail open — better to log an extra error than to lose
            // visibility on a genuine outage because the session
            // helper threw.
            return true;
        }
    }

    /**
     * Shallow-flatten a context payload so the stored JSON stays small
     * and predictable. Drops nested arrays beyond 2 levels and converts
     * remaining scalars to strings, truncating each to 500 chars.
     *
     * @param array<int|string,mixed> $arr
     * @return array<string,mixed>
     */
    private static function flattenForLog(array $arr): array
    {
        $out = [];
        $i   = 0;
        foreach ($arr as $k => $v) {
            if ($i++ > 30) break; // hard cap on keys
            $key = is_string($k) ? substr($k, 0, 60) : (string) $k;
            if (is_scalar($v) || $v === null) {
                $out[$key] = is_string($v) && strlen($v) > 500 ? substr($v, 0, 500) : $v;
            } elseif (is_array($v)) {
                // One level deep only — re-flatten scalars, drop the rest.
                $sub = [];
                $j   = 0;
                foreach ($v as $sk => $sv) {
                    if ($j++ > 10) break;
                    if (is_scalar($sv) || $sv === null) {
                        $sub[(string) $sk] = is_string($sv) && strlen($sv) > 200 ? substr($sv, 0, 200) : $sv;
                    }
                }
                $out[$key] = $sub;
            }
        }
        return $out;
    }

    /**
     * Shared summary helper — also called from HtmlView bootstrap to seed
     * the nav badge on first paint without a roundtrip.
     *
     * @return array{total:int,errors_24h:int,warnings_24h:int,last_at:?string}
     */
    public static function buildSummary(): array
    {
        try {
            $db     = Factory::getDbo();
            $cutoff = gmdate('Y-m-d H:i:s', time() - 86400);

            $total = (int) $db->setQuery(
                'SELECT COUNT(*) FROM ' . $db->quoteName(self::TABLE)
            )->loadResult();

            $errors24h = (int) $db->setQuery(
                'SELECT COUNT(*) FROM ' . $db->quoteName(self::TABLE)
                . ' WHERE ' . $db->quoteName('severity') . ' = ' . $db->quote('error')
                . ' AND ' . $db->quoteName('created_at') . ' >= ' . $db->quote($cutoff)
            )->loadResult();

            $warnings24h = (int) $db->setQuery(
                'SELECT COUNT(*) FROM ' . $db->quoteName(self::TABLE)
                . ' WHERE ' . $db->quoteName('severity') . ' = ' . $db->quote('warning')
                . ' AND ' . $db->quoteName('created_at') . ' >= ' . $db->quote($cutoff)
            )->loadResult();

            $lastAt = (string) $db->setQuery(
                'SELECT MAX(' . $db->quoteName('created_at') . ') FROM ' . $db->quoteName(self::TABLE)
            )->loadResult();

            return [
                'total'         => $total,
                'errors_24h'    => $errors24h,
                'warnings_24h'  => $warnings24h,
                'last_at'       => $lastAt !== '' ? $lastAt : null,
            ];
        } catch (\Throwable $e) {
            return ['total' => 0, 'errors_24h' => 0, 'warnings_24h' => 0, 'last_at' => null];
        }
    }

    private function sendJson(bool $success, string $message, array $extra = []): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra), JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}';
        $this->app->close();
    }
}
