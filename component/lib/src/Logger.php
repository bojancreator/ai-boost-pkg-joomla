<?php
/**
 * AI Boost — Central Logger (Task #511)
 *
 * Static API for logging structured events into the
 * #__aiboost_error_log table, with parallel Joomla JLog output and
 * an error_log() last-resort fallback so a logging failure never
 * breaks the request.
 *
 * Severity levels (ordered):
 *   debug (0) → info (1) → warning (2) → error (3)
 *
 * Severity floor is read from settings `error_log_min_severity`
 * (default: warning). Calls below the floor are dropped.
 * Calls are also dropped entirely when `error_log_enabled` is OFF.
 *
 * Retention: at most 1000 rows OR 30 days, whichever comes first.
 * Trim is performed once per request (throttled via static flag).
 *
 * Bootstrapping: methods lazy-init the database handle via
 * Joomla\CMS\Factory::getDbo() if init($db) was never called, so
 * any caller — plugins, services, controllers — can use Logger
 * without explicit setup.
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log as JoomlaLog;
use Joomla\Database\DatabaseInterface;

final class Logger
{
    public const SEVERITY_DEBUG   = 'debug';
    public const SEVERITY_INFO    = 'info';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_ERROR   = 'error';

    private const TABLE        = '#__aiboost_error_log';
    private const RETENTION_DAYS = 30;
    private const RETENTION_ROWS = 1000;

    private const SEVERITY_RANK = [
        self::SEVERITY_DEBUG   => 0,
        self::SEVERITY_INFO    => 1,
        self::SEVERITY_WARNING => 2,
        self::SEVERITY_ERROR   => 3,
    ];

    private static ?DatabaseInterface $db = null;
    private static ?string $requestId    = null;
    private static bool $trimmedThisRequest = false;
    private static bool $jlogRegistered = false;
    private static ?array $settingsCache = null;
    /** Re-entrancy guard: PluginSettings/DB errors fired during settings load
     *  must not loop back through Logger::write() (Task #511 review fix). */
    private static bool $inWrite = false;

    /** Optional explicit init. Safe to skip — lazy init kicks in on first call. */
    public static function init(?DatabaseInterface $db): void
    {
        self::$db = $db;
    }

    public static function debug(string|\Throwable $msg, array $context = [], ?string $source = null): void
    {
        self::write(self::SEVERITY_DEBUG, $msg, $context, $source);
    }

    public static function info(string|\Throwable $msg, array $context = [], ?string $source = null): void
    {
        self::write(self::SEVERITY_INFO, $msg, $context, $source);
    }

    public static function warning(string|\Throwable $msg, array $context = [], ?string $source = null): void
    {
        self::write(self::SEVERITY_WARNING, $msg, $context, $source);
    }

    public static function error(string|\Throwable $msg, array $context = [], ?string $source = null): void
    {
        self::write(self::SEVERITY_ERROR, $msg, $context, $source);
    }

    /**
     * Reset all internal state. Test helper — not for production use.
     */
    public static function reset(): void
    {
        self::$db                  = null;
        self::$requestId           = null;
        self::$trimmedThisRequest  = false;
        self::$jlogRegistered      = false;
        self::$settingsCache       = null;
    }

    // ─────────────────────────────────────────────────────────────────────
    // INTERNAL
    // ─────────────────────────────────────────────────────────────────────

    private static function write(string $severity, string|\Throwable $msg, array $context, ?string $source): void
    {
        // Re-entrancy guard — if a Logger call is triggered from inside the
        // logger pipeline itself (e.g. PluginSettings::loadAll fails while
        // Logger::loadSettings() is reading settings), drop to native
        // error_log and return immediately. Prevents infinite recursion.
        if (self::$inWrite) {
            $m = $msg instanceof \Throwable ? $msg->getMessage() : (string) $msg;
            @error_log('[AiBoost Logger][re-entry][' . strtoupper($severity) . '] ' . $m);
            return;
        }
        self::$inWrite = true;
        try {
            $settings = self::$settingsCache !== null ? self::$settingsCache : self::loadSettings();

            // Logger globally disabled → silent no-op.
            if (($settings['error_log_enabled'] ?? '1') !== '1') {
                return;
            }

            // Severity floor.
            $min  = (string) ($settings['error_log_min_severity'] ?? self::SEVERITY_WARNING);
            $minR = self::SEVERITY_RANK[$min] ?? self::SEVERITY_RANK[self::SEVERITY_WARNING];
            if ((self::SEVERITY_RANK[$severity] ?? 0) < $minR) {
                return;
            }

            // Normalise message + context.
            if ($msg instanceof \Throwable) {
                $context = array_merge([
                    'exception' => get_class($msg),
                    'file'      => $msg->getFile() . ':' . $msg->getLine(),
                    'trace'     => self::trimTrace($msg->getTraceAsString()),
                ], $context);
                $message = $msg->getMessage();
            } else {
                $message = $msg;
            }

            $source    = $source !== null && $source !== '' ? $source : self::detectSource();
            $requestId = self::getRequestId();

            // 1. DB write (primary).
            $dbOk = self::writeDb($severity, $source, $message, $context, $requestId);

            // 2. Joomla JLog (always — keeps existing Joomla log tooling working).
            self::writeJlog($severity, $source, $message);

            // 3. error_log() — last resort if DB layer failed.
            if (!$dbOk) {
                @error_log(sprintf('[AiBoost][%s][%s] %s', strtoupper($severity), $source, $message));
            }
        } catch (\Throwable $e) {
            // Logger must never break the request.
            @error_log('[AiBoost Logger] internal failure: ' . $e->getMessage());
        } finally {
            self::$inWrite = false;
        }
    }

    private static function writeDb(string $severity, string $source, string $message, array $context, string $requestId): bool
    {
        $db = self::db();
        if ($db === null) {
            return false;
        }
        try {
            $now    = gmdate('Y-m-d H:i:s');
            $ctxStr = '';
            if (!empty($context)) {
                $encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                if (is_string($encoded)) {
                    $ctxStr = strlen($encoded) > 8000 ? substr($encoded, 0, 8000) : $encoded;
                }
            }
            $messageTrimmed = strlen($message) > 1000 ? substr($message, 0, 1000) : $message;

            $query = $db->getQuery(true)
                ->insert($db->quoteName(self::TABLE))
                ->columns([
                    $db->quoteName('created_at'),
                    $db->quoteName('severity'),
                    $db->quoteName('source'),
                    $db->quoteName('message'),
                    $db->quoteName('context_json'),
                    $db->quoteName('request_id'),
                ])
                ->values(
                    $db->quote($now) . ', ' .
                    $db->quote($severity) . ', ' .
                    $db->quote(substr($source, 0, 100)) . ', ' .
                    $db->quote($messageTrimmed) . ', ' .
                    $db->quote($ctxStr) . ', ' .
                    $db->quote($requestId)
                );
            $db->setQuery($query)->execute();

            self::trimIfNeeded($db);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function trimIfNeeded(DatabaseInterface $db): void
    {
        if (self::$trimmedThisRequest) {
            return;
        }
        self::$trimmedThisRequest = true;
        try {
            // Drop rows older than retention window.
            $cutoff = gmdate('Y-m-d H:i:s', time() - (self::RETENTION_DAYS * 86400));
            $db->setQuery(
                'DELETE FROM ' . $db->quoteName(self::TABLE)
                . ' WHERE ' . $db->quoteName('created_at') . ' < ' . $db->quote($cutoff)
            )->execute();

            // Cap to RETENTION_ROWS most recent.
            $count = (int) $db->setQuery('SELECT COUNT(*) FROM ' . $db->quoteName(self::TABLE))->loadResult();
            if ($count > self::RETENTION_ROWS) {
                $minKeepId = (int) $db->setQuery(
                    'SELECT id FROM ' . $db->quoteName(self::TABLE)
                    . ' ORDER BY id DESC LIMIT 1 OFFSET ' . (self::RETENTION_ROWS - 1)
                )->loadResult();
                if ($minKeepId > 0) {
                    $db->setQuery(
                        'DELETE FROM ' . $db->quoteName(self::TABLE)
                        . ' WHERE id < ' . $minKeepId
                    )->execute();
                }
            }
        } catch (\Throwable $e) {
            // non-fatal
        }
    }

    private static function writeJlog(string $severity, string $source, string $message): void
    {
        try {
            if (!self::$jlogRegistered) {
                JoomlaLog::addLogger(
                    ['text_file' => 'com_aiboost.log.php'],
                    JoomlaLog::ALL,
                    ['com_aiboost']
                );
                self::$jlogRegistered = true;
            }
            $priority = match ($severity) {
                self::SEVERITY_ERROR   => JoomlaLog::ERROR,
                self::SEVERITY_WARNING => JoomlaLog::WARNING,
                self::SEVERITY_INFO    => JoomlaLog::INFO,
                default                => JoomlaLog::DEBUG,
            };
            JoomlaLog::add('[' . $source . '] ' . $message, $priority, 'com_aiboost');
        } catch (\Throwable $e) {
            // non-fatal
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function loadSettings(): array
    {
        if (self::$settingsCache !== null) {
            return self::$settingsCache;
        }
        try {
            // Prefer the shared PluginSettings cache when it's already populated.
            $all = PluginSettings::all();
            if (!empty($all)) {
                self::$settingsCache = $all;
                return $all;
            }
        } catch (\Throwable $e) {
            // fall through to direct read
        }

        $db = self::db();
        if ($db !== null) {
            try {
                $query = $db->getQuery(true)
                    ->select($db->quoteName('settings_json'))
                    ->from('#__aiboost_settings')
                    ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'));
                $json = $db->setQuery($query)->loadResult();
                if (is_string($json) && $json !== '') {
                    $decoded = json_decode($json, true);
                    if (is_array($decoded)) {
                        self::$settingsCache = $decoded;
                        return $decoded;
                    }
                }
            } catch (\Throwable $e) {
                // settings table may not exist yet on first install
            }
        }
        self::$settingsCache = [];
        return self::$settingsCache;
    }

    private static function db(): ?DatabaseInterface
    {
        if (self::$db !== null) {
            return self::$db;
        }
        try {
            if (class_exists(Factory::class)) {
                self::$db = Factory::getContainer()->get(DatabaseInterface::class);
            }
        } catch (\Throwable $e) {
            try {
                self::$db = Factory::getDbo();
            } catch (\Throwable $e2) {
                self::$db = null;
            }
        }
        return self::$db;
    }

    private static function getRequestId(): string
    {
        if (self::$requestId !== null) {
            return self::$requestId;
        }
        try {
            self::$requestId = bin2hex(random_bytes(8));
        } catch (\Throwable $e) {
            self::$requestId = substr(md5(uniqid('aib_', true)), 0, 16);
        }
        return self::$requestId;
    }

    private static function detectSource(): string
    {
        // Walk a few frames to find the first non-Logger caller.
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8);
        foreach ($bt as $frame) {
            $cls = $frame['class'] ?? '';
            if ($cls === self::class) {
                continue;
            }
            if ($cls !== '') {
                $short = substr($cls, strrpos($cls, '\\') + 1);
                return $short . ($frame['function'] ? '::' . $frame['function'] : '');
            }
            if (!empty($frame['function'])) {
                return $frame['function'];
            }
        }
        return 'aiboost';
    }

    private static function trimTrace(string $trace): string
    {
        return strlen($trace) > 4000 ? substr($trace, 0, 4000) . "\n…[trimmed]" : $trace;
    }
}
