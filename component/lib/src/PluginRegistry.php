<?php
/**
 * AI Boost — Plugin Registry
 *
 * Single request-cached scan of #__extensions for every AI Boost plugin
 * (core, Pro upgrade, integration). Provides the source of truth that the
 * Manifest Registry and the Vue SPA use to know which SKUs / integrations
 * are physically installed and enabled.
 *
 * SKU map (PRO upgrades):
 *   plg_system_aiboost_schema_pro   → sku 'schema'
 *   plg_system_aiboost_aeo_pro      → sku 'aeo'
 *   plg_system_aiboost_social_pro   → sku 'og'
 *   plg_system_aiboost_hreflang_pro → sku 'hreflang'
 *   plg_system_aiboost_code_pro     → sku 'code'
 *
 * Integrations:
 *   plg_system_aiboost_int_falang     → 'falang'
 *   plg_system_aiboost_int_yootheme   → 'yootheme'
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib;

defined('_JEXEC') or defined('ABSPATH') or die;

use AiBoost\Lib\Cms\AdapterRegistry;
use AiBoost\Lib\Integration\IntegrationRegistry;

final class PluginRegistry
{
    /** @var array<string,array<string,mixed>>|null */
    private static ?array $cache = null;

    /** @var array<string,mixed>|null  Cached license_simulation map. */
    private static ?array $simulation = null;

    /** @var array<string,mixed>|null  Cached license_state map (real verifications, per SKU). */
    private static ?array $licenseStates = null;

    /** Valid simulator states per SKU. */
    public const SIM_STATES = ['active', 'expired', 'disabled', 'not_licensed'];

    /** All Pro/bundle SKUs that the simulator UI can toggle. Integration SKUs
     *  are appended dynamically by simSkus() from IntegrationRegistry. */
    public const SIM_SKUS_STATIC = [
        'schema', 'og', 'hreflang', 'code', 'aeo', 'bundle',
    ];

    /**
     * Back-compat: a few call sites still read PluginRegistry::SIM_SKUS as
     * a hardcoded list. Keep the two bridges that shipped before the dynamic
     * registry so the simulator's UI never regresses.
     */
    public const SIM_SKUS = [
        'schema', 'og', 'hreflang', 'code', 'aeo', 'bundle',
        'int_falang', 'int_yootheme',
    ];

    private const PRO_SKUS = [
        'aiboost_schema_pro'   => 'schema',
        'aiboost_aeo_pro'      => 'aeo',
        'aiboost_social_pro'   => 'og',
        'aiboost_hreflang_pro' => 'hreflang',
        'aiboost_code_pro'     => 'code',
    ];

    /**
     * Fallback whitelist used only when IntegrationRegistry::all() returns
     * an empty set (i.e. no bridge plugin is installed and listening to
     * onAiBoostRegisterIntegration). Keeps the two bridges that shipped
     * before the dynamic registry visible in the Integrations dashboard.
     */
    private const INTEGRATIONS_FALLBACK = [
        'aiboost_int_falang'   => 'falang',
        'aiboost_int_yootheme' => 'yootheme',
    ];

    /**
     * Resolve plugin-element → integration-key map dynamically from the
     * IntegrationRegistry, falling back to the static whitelist when no
     * bridge has registered yet (fresh install, CLI bootstrap, etc).
     *
     * @return array<string,string>
     */
    private static function integrations(): array
    {
        $dynamic = [];
        try {
            foreach (IntegrationRegistry::all() as $key => $desc) {
                $element = $desc->pluginElement !== '' ? $desc->pluginElement : ('aiboost_int_' . $key);
                $dynamic[$element] = $key;
            }
        } catch (\Throwable) {
            $dynamic = [];
        }
        // Merge fallback so planned tiles like yootheme still surface
        // before its bridge ZIP ships.
        return $dynamic + self::INTEGRATIONS_FALLBACK;
    }

    /**
     * Public list of simulator-supported SKUs (Pro + every registered bridge).
     *
     * @return list<string>
     */
    public static function simSkus(): array
    {
        $skus = self::SIM_SKUS_STATIC;
        foreach (self::integrations() as $key) {
            $skus[] = 'int_' . $key;
        }
        return array_values(array_unique($skus));
    }

    /**
     * Return per-SKU and per-integration capabilities.
     *
     * Structure:
     *   [
     *     'pro_schema'   => ['installed' => bool, 'enabled' => bool, 'version' => string],
     *     'pro_aeo'      => [...],
     *     ...
     *     'int_falang'   => ['installed' => bool, 'enabled' => bool, 'detected_third_party' => bool, ...],
     *     'int_yootheme' => [...],
     *     'core'         => ['version' => string],
     *   ]
     *
     * @return array<string, array<string,mixed>>
     */
    public static function capabilities(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $rows = self::scan();
        $caps = ['core' => ['version' => self::coreVersion()]];

        // Precedence (highest first):
        //   1. Simulator override (JDEBUG only) — never honored in production
        //   2. Real per-SKU license_state (from verifyLicense AJAX flow)
        //   3. Raw plugin scan from #__extensions (Pro plugin installed + row enabled)
        $simEnforced = defined('JDEBUG') && JDEBUG === true;
        $sim         = $simEnforced ? self::loadSimulation() : null;
        $licStates   = self::loadLicenseStates();
        $bundleReal  = self::resolveRealStatus($licStates['bundle'] ?? null);

        foreach (self::PRO_SKUS as $element => $sku) {
            $row        = $rows[$element] ?? null;
            $installed  = $row !== null;
            $rowEnabled = $row !== null && (int) ($row['enabled'] ?? 0) === 1;

            $simState = self::simStateFor($sim, $sku);
            if ($simState !== null) {
                // Layer 1 — simulator overrides everything
                $caps['pro_' . $sku] = [
                    'installed'     => $simState !== 'not_licensed',
                    'enabled'       => $simState === 'active',
                    'version'       => self::manifestVersion($row),
                    'element'       => $element,
                    'license_state' => $simState,
                    'simulated'     => true,
                ];
                continue;
            }

            // Layer 2 — real per-SKU verification (or bundle covers it).
            // Bundle 'active' UNCONDITIONALLY overrides per-SKU state so that
            // hasPro()/licenseStatus() see a consistent 'active' for every SKU.
            $perSku       = self::resolveRealStatus($licStates[$sku] ?? null);
            $licenseValid = $bundleReal === 'active' || $perSku === 'active';

            if ($bundleReal === 'active') {
                $licenseState = 'active';
            } elseif ($perSku !== null) {
                $licenseState = $perSku;
            } else {
                $licenseState = null;
            }

            // Layer 3 — physical plugin row state combines with license gate
            $enabled = $installed && $rowEnabled && $licenseValid;
            if ($licenseState === null) {
                $licenseState = $installed ? 'disabled' : 'not_licensed';
            }
            // If license is active but Joomla row is disabled, surface 'disabled'
            if ($licenseValid && $installed && !$rowEnabled) {
                $licenseState = 'disabled';
            }

            $caps['pro_' . $sku] = [
                'installed'     => $installed,
                'enabled'       => $enabled,
                'version'       => self::manifestVersion($row),
                'element'       => $element,
                'license_state' => $licenseState,
                'simulated'     => false,
            ];
        }

        // 'bundle' is virtual — combine simulator + real verification.
        $bundleSim = self::simStateFor($sim, 'bundle');
        if ($bundleSim !== null) {
            $caps['pro_bundle'] = [
                'installed'     => $bundleSim !== null && $bundleSim !== 'not_licensed',
                'enabled'       => $bundleSim === 'active',
                'version'       => '',
                'element'       => '',
                'license_state' => $bundleSim,
                'simulated'     => true,
            ];
        } else {
            $caps['pro_bundle'] = [
                'installed'     => $bundleReal !== null && $bundleReal !== 'not_licensed',
                'enabled'       => $bundleReal === 'active',
                'version'       => '',
                'element'       => '',
                'license_state' => $bundleReal ?? 'not_licensed',
                'simulated'     => false,
            ];
        }

        foreach (self::integrations() as $element => $key) {
            $row       = $rows[$element] ?? null;
            $thirdP    = self::detectThirdParty($key);
            $installed = $row !== null;
            $enabled   = $row !== null && (int) ($row['enabled'] ?? 0) === 1;

            $simState = self::simStateFor($sim, 'int_' . $key);
            if ($simState !== null) {
                $installed = $simState !== 'not_licensed';
                $enabled   = $simState === 'active';
            }

            $caps['int_' . $key] = [
                'installed'            => $installed,
                'enabled'              => $enabled,
                'version'              => self::manifestVersion($row),
                'detected_third_party' => $thirdP,
                'element'              => $element,
                'license_state'        => $simState ?? ($enabled ? 'active' : ($installed ? 'disabled' : 'not_licensed')),
                'simulated'            => $simState !== null,
            ];
        }

        return self::$cache = $caps;
    }

    /**
     * Return the simulated license state for a SKU, or null if the simulator
     * is not active for it. Reads directly from #__aiboost_settings so callers
     * can short-circuit any real licensing logic.
     */
    public static function simulatedStatus(string $sku): ?string
    {
        if (!(defined('JDEBUG') && JDEBUG === true)) {
            return null;
        }
        return self::simStateFor(self::loadSimulation(), $sku);
    }

    /**
     * Return the resolved license state for a SKU as a string:
     *   'active' | 'expired' | 'disabled' | 'not_licensed'
     * Simulator state (when present) wins over the real plugin state.
     */
    public static function licenseStatus(string $sku): string
    {
        $caps = self::capabilities();
        $key  = str_starts_with($sku, 'int_') ? $sku : 'pro_' . $sku;
        $cap  = $caps[$key] ?? null;
        return (string) ($cap['license_state'] ?? 'not_licensed');
    }

    /**
     * True when the SKU is currently usable (real install enabled OR
     * simulator state === 'active'). Wraps isProSku() for clarity.
     */
    public static function hasPro(string $sku): bool
    {
        $simState = self::simulatedStatus($sku);
        if ($simState !== null) {
            return $simState === 'active';
        }

        // Task #565 — per-SKU SKUs are retired; Pro is a single bundle unlocked
        // by perpetual activation. Delegate to the canonical bundle-level gate
        // so every Pro emitter, the admin UI and the settings-save endpoint
        // derive Pro from the SAME signal (`pro_activated`). $sku is accepted
        // for back-compat with existing call sites but no longer differentiates.
        // Settings are read once per request and cached in a function static.
        static $isPro = null;
        if ($isPro === null) {
            $isPro = false;
            try {
                $db    = AdapterRegistry::database()->getConnection();
                $query = $db->getQuery(true)
                    ->select($db->quoteName('settings_json'))
                    ->from($db->quoteName('#__aiboost_settings'))
                    ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'));
                $json     = $db->setQuery($query)->loadResult();
                $settings = $json ? (json_decode((string) $json, true) ?? []) : [];
                $isPro    = self::isProActive($settings);
            } catch (\Throwable $e) {
                $isPro = false;
            }
        }
        return $isPro;
    }

    /**
     * Bundle-level "is this install Pro right now" check, resolved from a
     * settings array the caller already holds (no extra DB read).
     *
     * This is THE single canonical gate for Pro behaviour that is NOT
     * per-SKU. The admin bootstrap `isPro` (HtmlView::buildBootstrap), the
     * settings-save endpoint (SettingsController::isProSetting) and the
     * sitemap runtime (AiBoostSitemap::isPro) all delegate to it, so the UI,
     * server enforcement and frontend rendering can never drift apart.
     *
     * Task #565 — PERPETUAL ACTIVATION. A Pro install behaves exactly like
     * Free until a licence key is verified active **once**; from then on the
     * permanent `pro_activated` flag keeps Pro unlocked **forever**, even after
     * the licence expires. Expiry only stops automatic updates + support
     * (enforced by the update server), it never relocks features. This reverses
     * the v0.54.2 "Pro without a currently-verified key looks like Free" rule:
     * the new rule is "Pro without an *ever-activated* key looks like Free".
     *
     * Precedence:
     *   1. `dev_force_free_tier === '1'` → false  (QA: render/enforce as Free)
     *   2. `pro_activated === '1'`       → true   (a key verified active once)
     *   3. `dev_license_preview === '1'` → true   (QA: force Pro)
     *   4. otherwise → false
     *
     * NEVER gate on `license_tier`, on the current `license_state` status, or
     * on the heartbeat — those drift on expiry and were the source of the
     * recurring relock bugs this model removes.
     *
     * @param array<string,mixed> $settings The decoded #__aiboost_settings 'main' blob.
     */
    public static function isProActive(array $settings): bool
    {
        // 1. QA: force the install to behave as Free (screenshots / parity).
        if ((string) ($settings['dev_force_free_tier'] ?? '0') === '1') {
            return false;
        }
        // 2. Perpetual activation flag — set once a key verifies active, never cleared.
        if ((string) ($settings['pro_activated'] ?? '0') === '1') {
            return true;
        }
        // 3. QA: force Pro on a Free / never-activated install.
        if ((string) ($settings['dev_license_preview'] ?? '0') === '1') {
            return true;
        }
        return false;
    }

    /**
     * Domain override stored by the simulator, used to fake a JUri::root()
     * mismatch for the multi-site warning. Empty string when unset.
     */
    public static function simulatedDomainOverride(): string
    {
        if (!(defined('JDEBUG') && JDEBUG === true)) {
            return '';
        }
        $sim = self::loadSimulation();
        return (string) ($sim['_domain_override'] ?? '');
    }

    /**
     * True when ANY simulator override is active for ANY SKU/integration.
     * Used by the Health check to nag if the simulator is on outside debug.
     */
    public static function isSimulationActive(): bool
    {
        $sim = self::loadSimulation();
        foreach (self::SIM_SKUS as $sku) {
            if (self::simStateFor($sim, $sku) !== null) {
                return true;
            }
        }
        return !empty($sim['_domain_override']);
    }

    /**
     * Persist the simulation map to #__aiboost_settings under license_simulation.
     * Caller is responsible for permission + token + JDEBUG gating.
     *
     * @param array<string,mixed> $map  SKU => state, plus optional '_domain_override'.
     */
    public static function saveSimulation(array $map): void
    {
        try {
            $clean = [];
            foreach (self::SIM_SKUS as $sku) {
                $state = (string) ($map[$sku] ?? '');
                if (in_array($state, self::SIM_STATES, true)) {
                    $clean[$sku] = $state;
                }
            }
            $domain = trim((string) ($map['_domain_override'] ?? ''));
            if ($domain !== '') {
                $clean['_domain_override'] = $domain;
            }

            $db    = AdapterRegistry::database()->getConnection();
            $query = $db->getQuery(true)
                ->select($db->quoteName('settings_json'))
                ->from('#__aiboost_settings')
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'));
            $json  = (string) $db->setQuery($query)->loadResult();
            $data  = json_decode($json, true) ?: [];
            $data['license_simulation'] = $clean;
            $now   = AdapterRegistry::clock()->nowSql();
            $newJson = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            $check = $db->getQuery(true)
                ->select('id')->from('#__aiboost_settings')
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'));
            $id    = (int) $db->setQuery($check)->loadResult();
            if ($id > 0) {
                $upd = $db->getQuery(true)->update('#__aiboost_settings')
                    ->set($db->quoteName('settings_json') . ' = ' . $db->quote($newJson))
                    ->set($db->quoteName('updated_at') . ' = ' . $db->quote($now))
                    ->where($db->quoteName('id') . ' = ' . $id);
            } else {
                $upd = $db->getQuery(true)->insert('#__aiboost_settings')
                    ->columns(['setting_key', 'settings_json', 'created_at', 'updated_at'])
                    ->values($db->quote('main') . ',' . $db->quote($newJson) . ',' . $db->quote($now) . ',' . $db->quote($now));
            }
            $db->setQuery($upd)->execute();
        } catch (\Throwable $e) {
            error_log('[AI Boost PluginRegistry] saveSimulation failed: ' . $e->getMessage());
        }

        self::reset();
    }

    /**
     * @param array<string,mixed>|null $sim
     */
    private static function simStateFor(?array $sim, string $sku): ?string
    {
        if (!$sim) {
            return null;
        }
        $state = $sim[$sku] ?? null;
        if (!is_string($state) || !in_array($state, self::SIM_STATES, true)) {
            return null;
        }
        return $state;
    }

    /**
     * Load license_simulation map from settings, request-cached.
     *
     * @return array<string,mixed>
     */
    public static function loadSimulation(): array
    {
        if (self::$simulation !== null) {
            return self::$simulation;
        }
        $out = [];
        try {
            $db    = AdapterRegistry::database()->getConnection();
            $query = $db->getQuery(true)
                ->select($db->quoteName('settings_json'))
                ->from('#__aiboost_settings')
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'));
            $json  = (string) $db->setQuery($query)->loadResult();
            $data  = json_decode($json, true) ?: [];
            $sim   = $data['license_simulation'] ?? [];
            if (is_array($sim)) {
                $out = $sim;
            }
        } catch (\Throwable $e) {
            error_log('[AI Boost PluginRegistry] loadSimulation failed: ' . $e->getMessage());
        }
        return self::$simulation = $out;
    }

    /**
     * Returns true if at least one Pro plugin is installed AND enabled.
     */
    public static function hasAnyPro(): bool
    {
        foreach (self::capabilities() as $key => $cap) {
            if (str_starts_with($key, 'pro_')
                && !empty($cap['installed']) && !empty($cap['enabled'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check a specific Pro SKU.
     */
    public static function isProSku(string $sku): bool
    {
        $cap = self::capabilities()['pro_' . $sku] ?? null;
        return $cap !== null && !empty($cap['installed']) && !empty($cap['enabled']);
    }

    /**
     * Check if a Pro plugin is INSTALLED but the Joomla system plugin row
     * is disabled. Used by Health to surface `critical_pro_plugin_disabled`.
     *
     * @return array<int,string>  List of SKUs in this state.
     */
    public static function installedButDisabledPro(): array
    {
        $out = [];
        foreach (self::capabilities() as $key => $cap) {
            if (str_starts_with($key, 'pro_')
                && !empty($cap['installed']) && empty($cap['enabled'])) {
                $out[] = substr($key, 4);
            }
        }
        return $out;
    }

    /**
     * Reset cache (used by unit tests or after extension install/uninstall).
     */
    public static function reset(): void
    {
        self::$cache         = null;
        self::$simulation    = null;
        self::$licenseStates = null;
        // Task #486 — keep the integration registry cache in lockstep with
        // PluginRegistry so a bridge install/uninstall during the same
        // request does not surface stale "installed=false" rows.
        try {
            IntegrationRegistry::reset();
        } catch (\Throwable) {
            // Class may not be loaded in legacy boot paths.
        }
    }

    // ── Real per-SKU license_state (set by verifyLicense AJAX flow) ─────

    /**
     * Load the persisted per-SKU license_state map from #__aiboost_settings.
     * Shape:
     *   [
     *     'schema'   => ['key'=>..., 'status'=>..., 'expires_at'=>..., 'verified_at'=>..., 'activations_remaining'=>..., 'mock'=>bool],
     *     'og'       => [...],
     *     'hreflang' => [...],
     *     'code'     => [...],
     *     'aeo'      => [...],
     *     'bundle'   => [...],
     *   ]
     *
     * @return array<string, array<string,mixed>>
     */
    public static function loadLicenseStates(): array
    {
        if (self::$licenseStates !== null) {
            return self::$licenseStates;
        }
        $out = [];
        try {
            $db    = AdapterRegistry::database()->getConnection();
            $query = $db->getQuery(true)
                ->select($db->quoteName('settings_json'))
                ->from('#__aiboost_settings')
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'));
            $json  = (string) $db->setQuery($query)->loadResult();
            $data  = json_decode($json, true) ?: [];
            $states = $data['license_state'] ?? [];
            if (is_array($states)) {
                foreach ($states as $sku => $state) {
                    if (is_array($state)) {
                        $out[(string) $sku] = $state;
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[AI Boost PluginRegistry] loadLicenseStates failed: ' . $e->getMessage());
        }
        return self::$licenseStates = $out;
    }

    /**
     * Persist one SKU's license_state record into #__aiboost_settings.
     * Called from SettingsController::verifyLicense after a successful (or
     * failed) call to the AI Boost license validation API.
     *
     * Also materialises a back-compat `license_tier` field — set to 'pro'
     * if ANY per-SKU state is active OR bundle is active, otherwise 'free'.
     * Existing per-plugin isPro($settings) checks keep working unchanged.
     *
     * @param string                $sku     One of: schema, og, hreflang, code, aeo, bundle
     * @param array<string,mixed>   $state   Full state record (see loadLicenseStates() shape)
     */
    public static function saveLicenseState(string $sku, array $state): void
    {
        try {
            $db    = AdapterRegistry::database()->getConnection();
            $query = $db->getQuery(true)
                ->select($db->quoteName('settings_json'))
                ->from('#__aiboost_settings')
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'));
            $json  = (string) $db->setQuery($query)->loadResult();
            $data  = json_decode($json, true) ?: [];

            $states = isset($data['license_state']) && is_array($data['license_state'])
                ? $data['license_state']
                : [];
            $states[$sku] = $state;
            $data['license_state'] = $states;

            // Back-compat — materialise old single license_tier
            $anyActive = false;
            foreach ($states as $s) {
                if (is_array($s) && self::resolveRealStatus($s) === 'active') {
                    $anyActive = true;
                    break;
                }
            }
            $data['license_tier'] = $anyActive ? 'pro' : 'free';

            // Task #565 — perpetual activation. The first time any SKU resolves
            // to an active verified licence we set a permanent `pro_activated`
            // flag. This is the single source of truth for "is this a paid Pro
            // install". It is NEVER cleared by expiry, deactivation or the
            // heartbeat — once activated, Pro stays unlocked forever (an expired
            // licence only stops updates + support, enforced by the update
            // server, not by disabling code here).
            if ($anyActive && (string) ($data['pro_activated'] ?? '0') !== '1') {
                $data['pro_activated'] = '1';
                if (empty($data['pro_activated_at'])) {
                    $data['pro_activated_at'] = gmdate('c');
                }
                $data['pro_activated_version'] = class_exists('AiBoost\\Version')
                    ? \AiBoost\Version::VERSION
                    : '';
            }

            $now     = AdapterRegistry::clock()->nowSql();
            $newJson = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            $check = $db->getQuery(true)
                ->select('id')->from('#__aiboost_settings')
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'));
            $id    = (int) $db->setQuery($check)->loadResult();
            if ($id > 0) {
                $upd = $db->getQuery(true)->update('#__aiboost_settings')
                    ->set($db->quoteName('settings_json') . ' = ' . $db->quote($newJson))
                    ->set($db->quoteName('updated_at') . ' = ' . $db->quote($now))
                    ->where($db->quoteName('id') . ' = ' . $id);
            } else {
                $upd = $db->getQuery(true)->insert('#__aiboost_settings')
                    ->columns(['setting_key', 'settings_json', 'created_at', 'updated_at'])
                    ->values($db->quote('main') . ',' . $db->quote($newJson) . ',' . $db->quote($now) . ',' . $db->quote($now));
            }
            $db->setQuery($upd)->execute();
        } catch (\Throwable $e) {
            error_log('[AI Boost PluginRegistry] saveLicenseState failed: ' . $e->getMessage());
        }
        self::reset();
    }

    /**
     * Resolve a raw license_state record into one of:
     *   'active' | 'expired' | 'disabled' | null (= not configured)
     *
     * @param array<string,mixed>|null $state
     */
    public static function resolveRealStatus(?array $state): ?string
    {
        if (!$state) {
            return null;
        }
        $key = isset($state['key']) ? trim((string) $state['key']) : '';
        if ($key === '') {
            return null;
        }
        $status = strtolower((string) ($state['status'] ?? ''));
        if ($status === 'active') {
            // Honor expiry if present
            $exp = (string) ($state['expires_at'] ?? '');
            if ($exp !== '') {
                $expTs = strtotime($exp);
                if ($expTs !== false && $expTs < time()) {
                    return 'expired';
                }
            }
            return 'active';
        }
        if (in_array($status, ['expired', 'limit_reached', 'deactivated', 'invalid'], true)) {
            return 'expired';
        }
        return null;
    }

    // ── Internals ────────────────────────────────────────────────────────

    /**
     * @return array<string, array<string,mixed>>
     */
    private static function scan(): array
    {
        $out = [];
        try {
            $db    = AdapterRegistry::database()->getConnection();
            $known = array_merge(array_keys(self::PRO_SKUS), array_keys(self::integrations()));
            $quoted = array_map([$db, 'quote'], $known);
            $query = $db->getQuery(true)
                ->select(['element', 'enabled', 'manifest_cache'])
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
                ->where($db->quoteName('element') . ' IN (' . implode(',', $quoted) . ')');
            $db->setQuery($query);
            foreach ((array) $db->loadAssocList() as $row) {
                $out[(string) $row['element']] = $row;
            }
        } catch (\Throwable $e) {
            error_log('[AI Boost PluginRegistry] scan failed: ' . $e->getMessage());
        }
        return $out;
    }

    /**
     * @param array<string,mixed>|null $row
     */
    private static function manifestVersion(?array $row): string
    {
        if ($row === null) {
            return '';
        }
        $cache = json_decode((string) ($row['manifest_cache'] ?? '{}'), true) ?: [];
        return (string) ($cache['version'] ?? '');
    }

    private static function coreVersion(): string
    {
        if (class_exists('AiBoost\\Version')) {
            return (string) \AiBoost\Version::VERSION;
        }
        return '';
    }

    /**
     * Detect that a third-party extension (e.g. Falang, YOOtheme Pro) is on
     * the site without our integration plugin being installed.
     */
    private static function detectThirdParty(string $integration): bool
    {
        if (!class_exists('AiBoost\\Lib\\BridgeDetector')) {
            return false;
        }
        switch ($integration) {
            case 'falang':
                return \AiBoost\Lib\BridgeDetector::tableExists('#__falang_content')
                    || \AiBoost\Lib\BridgeDetector::isInstalled('falang')
                    || \AiBoost\Lib\BridgeDetector::isInstalled('com_falang');
            case 'yootheme':
                return \AiBoost\Lib\BridgeDetector::isInstalled('com_yootheme')
                    || \AiBoost\Lib\BridgeDetector::isInstalled('yootheme');
            default:
                return false;
        }
    }
}
