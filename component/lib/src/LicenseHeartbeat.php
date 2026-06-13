<?php
/**
 * AI Boost — License Heartbeat (Task #440, Anti-Piracy B)
 *
 * Every 7 days, in the admin only, the plugin POSTs `{license_key, domain,
 * plugin_version, install_id}` to api.aiboostnow.com/api/license/heartbeat
 * and stores the verdict in #__aiboost_settings under `license_heartbeat`.
 *
 * Task #565 — PERPETUAL ACTIVATION. The heartbeat is now purely informational:
 * it refreshes the displayed licence status (active / expired) that drives the
 * "renew for updates + support" notice on the Licenses tab. It NEVER gates or
 * relocks Pro features — once `pro_activated` is set, Pro stays on forever
 * regardless of any verdict here. The 14-day grace period and hard-disable
 * behaviour have been removed.
 *
 * Verdicts (display only):
 *   - 'ok'              → licence valid
 *   - 'soft_warning'    → licence non-active or expired (renewal notice only)
 *   - 'domain_mismatch' → key already bound to another (domain, install_id)
 *
 * Network failures NEVER block the request — short timeout + try/catch.
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib;

use AiBoost\Lib\Cms\AdapterRegistry;

defined('_JEXEC') or defined('ABSPATH') or die;

final class LicenseHeartbeat
{
    public const HEARTBEAT_INTERVAL_DAYS = 7;
    public const ENDPOINT                = 'https://api.aiboostnow.com/api/license/heartbeat';
    public const TIMEOUT_SECONDS         = 3;

    /**
     * True when a heartbeat is due — i.e. it has been more than
     * HEARTBEAT_INTERVAL_DAYS since `license_heartbeat.last_checked_at`,
     * or has never run.
     *
     * @param array<string,mixed> $settings
     */
    public static function shouldRun(array $settings): bool
    {
        if (empty($settings['install_id'])) {
            return false;
        }
        if (!self::firstActiveLicenseKey($settings)) {
            return false;
        }
        $hb   = isset($settings['license_heartbeat']) && is_array($settings['license_heartbeat'])
            ? $settings['license_heartbeat']
            : [];
        $last = isset($hb['last_checked_at']) ? (int) strtotime((string) $hb['last_checked_at']) : 0;
        if ($last <= 0) {
            return true;
        }
        return (time() - $last) > (self::HEARTBEAT_INTERVAL_DAYS * 86400);
    }

    /**
     * Fire-and-forget heartbeat. Returns the parsed verdict array, or an
     * empty array on network failure (caller treats this as "no change").
     *
     * @param array<string,mixed> $settings
     * @return array<string,mixed>
     */
    public static function execute(array $settings): array
    {
        $key = self::firstActiveLicenseKey($settings);
        if (!$key) {
            return [];
        }
        $payload = [
            'license_key'    => $key,
            'domain'         => AdapterRegistry::application()->getHost(),
            'plugin_version' => \AiBoost\Version::VERSION,
            'install_id'     => (string) ($settings['install_id'] ?? ''),
        ];

        try {
            $http = AdapterRegistry::http()->getClient();
            $response = $http->post(
                self::ENDPOINT,
                json_encode($payload, JSON_UNESCAPED_SLASHES),
                ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
                self::TIMEOUT_SECONDS
            );
            if ($response === null || (int) $response->code < 200 || (int) $response->code >= 300) {
                return [];
            }
            $body = json_decode((string) $response->body, true);
            if (!is_array($body) || empty($body['verdict'])) {
                return [];
            }
            self::persistVerdict($settings, $body);
            return $body;
        } catch (\Throwable $e) {
            if (!empty($settings['debug_mode'])) {
                error_log('[AI Boost: heartbeat] failed — ' . $e->getMessage());
            }
            return [];
        }
    }

    /**
     * Days until the next automatic heartbeat fires. Negative when overdue.
     *
     * @param array<string,mixed> $settings
     */
    public static function daysUntilNextCheck(array $settings): int
    {
        $hb   = $settings['license_heartbeat'] ?? null;
        $last = is_array($hb) && !empty($hb['last_checked_at'])
            ? (int) strtotime((string) $hb['last_checked_at'])
            : 0;
        if ($last <= 0) {
            return 0;
        }
        $elapsed = (int) floor((time() - $last) / 86400);
        return self::HEARTBEAT_INTERVAL_DAYS - $elapsed;
    }

    /**
     * True when either the last verdict was `domain_mismatch` OR the server
     * flagged a collision via `domain_collision:true` on the most recent
     * heartbeat. The server sets `domain_collision:true` on the bound
     * install's heartbeat for up to 30 days after a conflicting install
     * tried the same key — so both sides see the warning, not just the
     * intruder.
     *
     * @param array<string,mixed> $settings
     */
    public static function hasDomainCollision(array $settings): bool
    {
        $hb = $settings['license_heartbeat'] ?? null;
        if (!is_array($hb)) {
            return false;
        }
        if ((string) ($hb['last_verdict'] ?? '') === 'domain_mismatch') {
            return true;
        }
        return !empty($hb['domain_collision']);
    }

    /**
     * Pick the strongest license key to heartbeat with.
     *
     * Selection order (so we never accidentally hard-disable a paying user
     * because the heartbeat picked a stale/expired key):
     *   1. Bundle key with status == 'active'        (covers all SKUs)
     *   2. Any per-SKU key with status == 'active'   (deterministic order)
     *   3. Bundle key regardless of status           (so renewal warnings still surface)
     *   4. First non-empty per-SKU key
     *
     * @param array<string,mixed> $settings
     */
    private static function firstActiveLicenseKey(array $settings): ?string
    {
        $states = $settings['license_state'] ?? null;
        if (!is_array($states)) {
            return null;
        }

        // Deterministic SKU iteration order — bundle first.
        $order = ['bundle', 'schema', 'og', 'hreflang', 'code', 'aeo'];

        // Pass 1: bundle, active.
        $bundle = is_array($states['bundle'] ?? null) ? $states['bundle'] : null;
        if ($bundle && !empty($bundle['key']) && (string) ($bundle['status'] ?? '') === 'active') {
            return (string) $bundle['key'];
        }
        // Pass 2: any SKU with status active.
        foreach ($order as $sku) {
            $row = is_array($states[$sku] ?? null) ? $states[$sku] : null;
            if ($row && !empty($row['key']) && (string) ($row['status'] ?? '') === 'active') {
                return (string) $row['key'];
            }
        }
        // Pass 3: bundle key regardless of status.
        if ($bundle && !empty($bundle['key'])) {
            return (string) $bundle['key'];
        }
        // Pass 4: first non-empty per-SKU key.
        foreach ($order as $sku) {
            $row = is_array($states[$sku] ?? null) ? $states[$sku] : null;
            if ($row && !empty($row['key'])) {
                return (string) $row['key'];
            }
        }
        // Pass 5: unknown SKU keys not in the canonical list — but NEVER an
        // integration licence (int_*). Plan 2a integration keys belong to their
        // own product/licence channel; heartbeating one against the CORE licence
        // endpoint would surface an integration's status as the core status and
        // bind the wrong key to this install. Core-only catch-all.
        foreach ($states as $sku => $row) {
            if (str_starts_with((string) $sku, 'int_')) {
                continue;
            }
            if (is_array($row) && !empty($row['key'])) {
                return (string) $row['key'];
            }
        }
        return null;
    }

    /**
     * Persist the verdict back into #__aiboost_settings.license_heartbeat.
     * Task #565 — display only. Stores the latest verdict, status and expiry
     * so the Licenses tab can show "Active / Expired — renew for updates +
     * support". No grace timer is kept any more (it gated nothing).
     *
     * @param array<string,mixed> $settings  Current settings.
     * @param array<string,mixed> $verdict   Server response.
     */
    private static function persistVerdict(array $settings, array $verdict): void
    {
        try {
            $db    = AdapterRegistry::database()->getConnection();
            $query = $db->getQuery(true)
                ->select($db->quoteName('settings_json'))
                ->from($db->quoteName('#__aiboost_settings'))
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'));
            $row   = $db->setQuery($query)->loadResult();
            $data  = $row ? (json_decode((string) $row, true) ?? []) : [];

            $prev = isset($data['license_heartbeat']) && is_array($data['license_heartbeat'])
                ? $data['license_heartbeat']
                : [];

            $now        = gmdate('c');
            $verdictStr = (string) ($verdict['verdict'] ?? 'ok');

            $hb = [
                'last_verdict'     => $verdictStr,
                'last_checked_at'  => $now,
                'install_id'       => (string) ($settings['install_id'] ?? ($prev['install_id'] ?? '')),
                'message'          => (string) ($verdict['message'] ?? ''),
                'expires_at'       => $verdict['expires_at'] ?? null,
                'status'           => $verdict['status'] ?? null,
                'domain_collision' => !empty($verdict['domain_collision']),
            ];

            $data['license_heartbeat'] = $hb;

            $newJson = json_encode($data, JSON_UNESCAPED_SLASHES);
            if ($row === null) {
                $db->setQuery(
                    $db->getQuery(true)
                        ->insert($db->quoteName('#__aiboost_settings'))
                        ->columns([$db->quoteName('setting_key'), $db->quoteName('settings_json')])
                        ->values($db->quote('main') . ', ' . $db->quote($newJson))
                )->execute();
            } else {
                $db->setQuery(
                    $db->getQuery(true)
                        ->update($db->quoteName('#__aiboost_settings'))
                        ->set($db->quoteName('settings_json') . ' = ' . $db->quote($newJson))
                        ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'))
                )->execute();
            }
        } catch (\Throwable $e) {
            if (!empty($settings['debug_mode'])) {
                error_log('[AI Boost: heartbeat persist] failed — ' . $e->getMessage());
            }
        }
    }
}
