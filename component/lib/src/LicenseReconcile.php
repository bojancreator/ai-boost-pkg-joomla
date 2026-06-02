<?php
/**
 * AI Boost — License Reconcile (Task #567, perpetual-activation safety net)
 *
 * The perpetual-activation backfill (`pkg_script::migrateActivateProPerpetual`)
 * flips the permanent `pro_activated` flag for any install that, at migration
 * time, still resolves to Pro from LOCAL state — an active `license_state` row,
 * a legacy paid `license_tier`, or a populated `pro_skus` map. A genuine past
 * purchaser whose licence has since lapsed AND whose local licence markers were
 * overwritten or cleared (settings re-import, manual edit, partial restore)
 * slips through that backfill and is treated as Free — forced to re-enter their
 * key for code they already paid for.
 *
 * This class closes that gap with a server-side reconciliation that needs NO
 * local licence key. Every install already carries a stable per-site
 * `install_id` (UUIDv4, written by pkg_script) and, when it ever verified a
 * licence, the update server bound that exact `install_id` to the purchased
 * licence (see api `/license/heartbeat` first-bind). So an install that has no
 * local Pro evidence can ask the server: "was THIS install_id ever bound to a
 * real purchase?". Only the server can answer (the binding lives there, keyed
 * on a UUID an attacker cannot guess), which is what keeps this free of false
 * positives — we never activate an install the server has no purchase record
 * for.
 *
 * On an `eligible` verdict we set the same perpetual `pro_activated` flag the
 * migration sets (never cleared; an expired licence only pauses updates +
 * support, enforced by the update server, not by relocking code here) and, when
 * the server returns the key, restore a minimal `license_state` row so the
 * Licenses tab and heartbeat keep working.
 *
 * Admin-only, throttled (RETRY_INTERVAL_DAYS), capped (MAX_ATTEMPTS) and
 * fire-and-forget: network failures NEVER block the request and never burn an
 * attempt.
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib;

use AiBoost\Lib\Cms\AdapterRegistry;

defined('_JEXEC') or defined('ABSPATH') or die;

final class LicenseReconcile
{
    public const ENDPOINT            = 'https://api.aiboostnow.com/api/license/reconcile';
    public const TIMEOUT_SECONDS     = 3;

    /** Re-attempt a still-ineligible install at most this often. */
    public const RETRY_INTERVAL_DAYS = 30;

    /** Stop pinging the server after this many ineligible verdicts. */
    public const MAX_ATTEMPTS        = 6;

    /**
     * True when this install is a candidate for reconciliation:
     *   - it is NOT already perpetually activated,
     *   - it has NO currently-active local licence (the normal verify /
     *     migration paths already cover those),
     *   - it carries a stable install_id to identify itself to the server,
     *   - and we have not already reconciled it, exhausted MAX_ATTEMPTS, or
     *     pinged within the RETRY_INTERVAL_DAYS window.
     *
     * @param array<string,mixed> $settings
     */
    public static function shouldRun(array $settings): bool
    {
        // Already Pro forever — nothing to recover.
        if ((string) ($settings['pro_activated'] ?? '0') === '1') {
            return false;
        }
        // No identity to reconcile with.
        if (empty($settings['install_id'])) {
            return false;
        }
        // A live, active local licence is handled by the normal verify flow;
        // it would have set pro_activated already. Don't reconcile those.
        if (self::hasActiveLocalLicense($settings)) {
            return false;
        }

        $rc       = isset($settings['license_reconcile']) && is_array($settings['license_reconcile'])
            ? $settings['license_reconcile']
            : [];
        // Once eligible, the perpetual flag is set and we never run again.
        if ((string) ($rc['result'] ?? '') === 'eligible') {
            return false;
        }
        if ((int) ($rc['attempts'] ?? 0) >= self::MAX_ATTEMPTS) {
            return false;
        }
        $last = isset($rc['last_checked_at']) ? (int) strtotime((string) $rc['last_checked_at']) : 0;
        if ($last > 0 && (time() - $last) < (self::RETRY_INTERVAL_DAYS * 86400)) {
            return false;
        }
        return true;
    }

    /**
     * Ask the update server whether this install_id was ever bound to a real
     * purchase. On an `eligible` verdict, perpetually activate Pro (and restore
     * the licence key/state when the server returns it). Returns the parsed
     * verdict array, or an empty array on network failure (no attempt burned).
     *
     * @param array<string,mixed> $settings
     * @return array<string,mixed>
     */
    public static function execute(array $settings): array
    {
        $installId = (string) ($settings['install_id'] ?? '');
        if ($installId === '') {
            return [];
        }
        $payload = [
            'install_id'     => $installId,
            'domain'         => AdapterRegistry::application()->getHost(),
            'plugin_version' => \AiBoost\Version::VERSION,
        ];

        try {
            $http     = AdapterRegistry::http()->getClient();
            $response = $http->post(
                self::ENDPOINT,
                json_encode($payload, JSON_UNESCAPED_SLASHES),
                ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
                self::TIMEOUT_SECONDS
            );
            // Network / server error — treat as "no change", don't burn an attempt.
            if ($response === null || (int) $response->code < 200 || (int) $response->code >= 300) {
                return [];
            }
            $body = json_decode((string) $response->body, true);
            if (!is_array($body) || !array_key_exists('eligible', $body)) {
                return [];
            }

            if (!empty($body['eligible'])) {
                self::activateFromVerdict($settings, $body);
            } else {
                self::recordIneligible($settings);
            }
            return $body;
        } catch (\Throwable $e) {
            if (!empty($settings['debug_mode'])) {
                error_log('[AI Boost: reconcile] failed — ' . $e->getMessage());
            }
            return [];
        }
    }

    /**
     * True when any local license_state row resolves to a currently-active
     * licence. Mirrors PluginRegistry::resolveRealStatus semantics without
     * loading the request-cached registry (this runs very early).
     *
     * @param array<string,mixed> $settings
     */
    private static function hasActiveLocalLicense(array $settings): bool
    {
        $states = $settings['license_state'] ?? null;
        if (!is_array($states)) {
            return false;
        }
        foreach ($states as $row) {
            if (is_array($row) && PluginRegistry::resolveRealStatus($row) === 'active') {
                return true;
            }
        }
        return false;
    }

    /**
     * Perpetually activate Pro from an `eligible` server verdict and stamp the
     * reconcile audit block. Writes #__aiboost_settings.main in a single update.
     * The `pro_activated` flag is set regardless of the recovered licence's
     * status — the server has already confirmed a genuine prior purchase, and
     * (per Task #565) expiry must never relock paid code.
     *
     * @param array<string,mixed> $settings  Current settings (caller's copy).
     * @param array<string,mixed> $verdict   Server response.
     */
    private static function activateFromVerdict(array $settings, array $verdict): void
    {
        try {
            $db    = AdapterRegistry::database()->getConnection();
            $query = $db->getQuery(true)
                ->select($db->quoteName('settings_json'))
                ->from($db->quoteName('#__aiboost_settings'))
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'));
            $row   = $db->setQuery($query)->loadResult();
            $data  = $row ? (json_decode((string) $row, true) ?? []) : [];
            if (!is_array($data)) {
                $data = [];
            }

            // Perpetual activation — the single source of truth for "paid Pro".
            // Idempotent: never overwrite an existing activation timestamp.
            $data['pro_activated'] = '1';
            if (empty($data['pro_activated_at'])) {
                $data['pro_activated_at'] = gmdate('c');
            }
            if (empty($data['pro_activated_version'])) {
                $data['pro_activated_version'] = class_exists('AiBoost\\Version')
                    ? \AiBoost\Version::VERSION
                    : 'reconciled';
            }

            // Restore a minimal license_state row when the server handed the
            // key back, so the Licenses tab + heartbeat have something to show
            // and renew against. Stored under the recovered SKU (default bundle).
            $key = trim((string) ($verdict['license_key'] ?? ''));
            if ($key !== '') {
                $sku    = strtolower(trim((string) ($verdict['sku'] ?? 'bundle')));
                $sku    = $sku !== '' ? $sku : 'bundle';
                $states = isset($data['license_state']) && is_array($data['license_state'])
                    ? $data['license_state']
                    : [];
                $states[$sku] = [
                    'key'        => $key,
                    'status'     => (string) ($verdict['status'] ?? 'expired'),
                    'expires_at' => $verdict['expires_at'] ?? null,
                    'source'     => 'reconcile',
                    'checked_at' => gmdate('c'),
                ];
                $data['license_state'] = $states;
            }

            $data['license_reconcile'] = [
                'result'          => 'eligible',
                'last_checked_at' => gmdate('c'),
                'attempts'        => (int) ($data['license_reconcile']['attempts'] ?? 0) + 1,
            ];

            self::persist($db, $row, $data);
            PluginRegistry::reset();
        } catch (\Throwable $e) {
            if (!empty($settings['debug_mode'])) {
                error_log('[AI Boost: reconcile activate] failed — ' . $e->getMessage());
            }
        }
    }

    /**
     * Stamp an ineligible attempt (increment the counter + timestamp) so the
     * throttle in shouldRun() backs off and eventually stops.
     *
     * @param array<string,mixed> $settings
     */
    private static function recordIneligible(array $settings): void
    {
        try {
            $db    = AdapterRegistry::database()->getConnection();
            $query = $db->getQuery(true)
                ->select($db->quoteName('settings_json'))
                ->from($db->quoteName('#__aiboost_settings'))
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'));
            $row   = $db->setQuery($query)->loadResult();
            $data  = $row ? (json_decode((string) $row, true) ?? []) : [];
            if (!is_array($data)) {
                $data = [];
            }
            // Never downgrade an already-eligible install.
            if ((string) ($data['pro_activated'] ?? '0') === '1') {
                return;
            }
            $prev = isset($data['license_reconcile']) && is_array($data['license_reconcile'])
                ? $data['license_reconcile']
                : [];
            $data['license_reconcile'] = [
                'result'          => 'ineligible',
                'last_checked_at' => gmdate('c'),
                'attempts'        => (int) ($prev['attempts'] ?? 0) + 1,
            ];
            self::persist($db, $row, $data);
        } catch (\Throwable $e) {
            if (!empty($settings['debug_mode'])) {
                error_log('[AI Boost: reconcile record] failed — ' . $e->getMessage());
            }
        }
    }

    /**
     * Write the settings blob back, inserting the 'main' row when absent.
     *
     * @param mixed                $existingRow  Raw settings_json (null if no row).
     * @param array<string,mixed>  $data         Decoded settings to persist.
     */
    private static function persist($db, $existingRow, array $data): void
    {
        $newJson = json_encode($data, JSON_UNESCAPED_SLASHES);
        if ($existingRow === null) {
            $db->setQuery(
                $db->getQuery(true)
                    ->insert($db->quoteName('#__aiboost_settings'))
                    ->columns([$db->quoteName('setting_key'), $db->quoteName('settings_json')])
                    ->values($db->quote('main') . ', ' . $db->quote($newJson))
            )->execute();
            return;
        }
        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__aiboost_settings'))
                ->set($db->quoteName('settings_json') . ' = ' . $db->quote($newJson))
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'))
        )->execute();
    }
}
