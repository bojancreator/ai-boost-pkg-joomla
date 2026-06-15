<?php
/**
 * AI Boost — Plugin Registry
 *
 * Single request-cached scan of #__extensions for every AI Boost plugin
 * (core, legacy add-on, integration). Provides the source of truth that the
 * Manifest Registry and the Vue SPA use to know which SKUs / integrations
 * are physically installed and enabled.
 *
 * Legacy add-on SKU map:
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

        try {
            $intSettings = self::loadMainSettings();
        } catch (\Throwable) {
            $intSettings = [];
        }

        $caps += self::proCapabilities($rows, $sim, $licStates, $bundleReal);
        $caps['pro_bundle'] = self::buildBundleCapability($sim, $bundleReal);
        $caps += self::integrationCapabilities($rows, $sim, $intSettings);

        return self::$cache = $caps;
    }

    /**
     * @param array<string, array<string,mixed>> $rows
     * @param array<string,mixed>|null $sim
     * @param array<string, array<string,mixed>> $licStates
     * @return array<string, array<string,mixed>>
     */
    private static function proCapabilities(array $rows, ?array $sim, array $licStates, ?string $bundleReal): array
    {
        $caps = [];
        foreach (self::PRO_SKUS as $element => $sku) {
            $caps['pro_' . $sku] = self::buildProCapability($rows[$element] ?? null, $element, $sku, $sim, $licStates, $bundleReal);
        }
        return $caps;
    }

    /**
     * @param array<string, array<string,mixed>> $rows
     * @param array<string,mixed>|null $sim
     * @param array<string,mixed> $settings The decoded #__aiboost_settings 'main' blob.
     * @return array<string, array<string,mixed>>
     */
    private static function integrationCapabilities(array $rows, ?array $sim, array $settings): array
    {
        $caps = [];
        foreach (self::integrations() as $element => $key) {
            $caps['int_' . $key] = self::buildIntegrationCapability($rows[$element] ?? null, $element, $key, $sim, $settings);
        }
        return $caps;
    }

    /**
     * Master Integrations-page toggle state for a bridge. Key is
     * `integration_<key>_enabled`; fail-open to enabled so a fresh install
     * (key never saved) and any bridge without a static master key behave as ON.
     *
     * @param array<string,mixed> $settings
     */
    private static function integrationAdminEnabled(string $key, array $settings): bool
    {
        return (string) ($settings['integration_' . $key . '_enabled'] ?? '1') !== '0';
    }

    /**
     * @param array<string,mixed>|null $row
     * @param array<string,mixed>|null $sim
     * @param array<string, array<string,mixed>> $licStates
     * @return array<string,mixed>
     */
    private static function buildProCapability(?array $row, string $element, string $sku, ?array $sim, array $licStates, ?string $bundleReal): array
    {
        $simState = self::simStateFor($sim, $sku);
        if ($simState !== null) {
            return self::simulatedCapability($row, $element, $simState);
        }

        $installed  = $row !== null;
        $rowEnabled = self::extensionRowEnabled($row);
        $perSku = self::resolveRealStatus($licStates[$sku] ?? null);
        $licenseValid = self::licenseStatusActive($bundleReal) || self::licenseStatusActive($perSku);
        $licenseState = self::resolvedSkuLicenseState($installed, $rowEnabled, $licenseValid, $bundleReal, $perSku);

        return [
            'installed'     => $installed,
            'enabled'       => $installed && $rowEnabled && $licenseValid,
            'version'       => self::manifestVersion($row),
            'element'       => $element,
            'license_state' => $licenseState,
            'simulated'     => false,
        ];
    }

    /**
     * @param array<string,mixed>|null $row
     * @return array<string,mixed>
     */
    private static function simulatedCapability(?array $row, string $element, string $simState): array
    {
        return [
            'installed'     => $simState !== 'not_licensed',
            'enabled'       => $simState === 'active',
            'version'       => self::manifestVersion($row),
            'element'       => $element,
            'license_state' => $simState,
            'simulated'     => true,
        ];
    }

    private static function resolvedSkuLicenseState(bool $installed, bool $rowEnabled, bool $licenseValid, ?string $bundleReal, ?string $perSku): string
    {
        $state = self::baseSkuLicenseState($installed, $bundleReal, $perSku);
        return self::licensedRowDisabled($installed, $rowEnabled, $licenseValid) ? 'disabled' : $state;
    }

    private static function baseSkuLicenseState(bool $installed, ?string $bundleReal, ?string $perSku): string
    {
        if ($bundleReal === 'active') {
            return 'active';
        }
        return $perSku ?? ($installed ? 'disabled' : 'not_licensed');
    }

    private static function licensedRowDisabled(bool $installed, bool $rowEnabled, bool $licenseValid): bool
    {
        return $licenseValid && $installed && !$rowEnabled;
    }

    /**
     * @param array<string,mixed>|null $row
     */
    private static function extensionRowEnabled(?array $row): bool
    {
        return $row !== null && (int) ($row['enabled'] ?? 0) === 1;
    }

    private static function licenseStatusActive(?string $status): bool
    {
        return $status === 'active';
    }

    /**
     * @param array<string,mixed>|null $sim
     * @return array<string,mixed>
     */
    private static function buildBundleCapability(?array $sim, ?string $bundleReal): array
    {
        $bundleSim = self::simStateFor($sim, 'bundle');
        if ($bundleSim !== null) {
            return [
                'installed'     => $bundleSim !== 'not_licensed',
                'enabled'       => $bundleSim === 'active',
                'version'       => '',
                'element'       => '',
                'license_state' => $bundleSim,
                'simulated'     => true,
            ];
        }

        return [
            'installed'     => $bundleReal !== null && $bundleReal !== 'not_licensed',
            'enabled'       => $bundleReal === 'active',
            'version'       => '',
            'element'       => '',
            'license_state' => $bundleReal ?? 'not_licensed',
            'simulated'     => false,
        ];
    }

    /**
     * @param array<string,mixed>|null $row
     * @param array<string,mixed>|null $sim
     * @return array<string,mixed>
     */
    private static function buildIntegrationCapability(?array $row, string $element, string $key, ?array $sim, array $settings): array
    {
        $adminEnabled = self::integrationAdminEnabled($key, $settings);

        $simState = self::simStateFor($sim, 'int_' . $key);
        if ($simState !== null) {
            return self::integrationCapability($row, $element, $key, $simState !== 'not_licensed', $simState === 'active', $simState, true, $adminEnabled);
        }

        $installed = $row !== null;
        return self::integrationCapability($row, $element, $key, $installed, self::extensionRowEnabled($row), null, false, $adminEnabled);
    }

    /**
     * @param array<string,mixed>|null $row
     * @return array<string,mixed>
     */
    private static function integrationCapability(?array $row, string $element, string $key, bool $installed, bool $enabled, ?string $simState, bool $simulated, bool $adminEnabled = true): array
    {
        return [
            'installed'            => $installed,
            'enabled'              => $enabled,
            'admin_enabled'        => $adminEnabled,
            'version'              => self::manifestVersion($row),
            'detected_third_party' => self::detectThirdParty($key),
            'element'              => $element,
            'license_state'        => $simState ?? self::realIntegrationLicenseState($installed, $enabled),
            'simulated'            => $simulated,
        ];
    }

    private static function realIntegrationLicenseState(bool $installed, bool $enabled): string
    {
        return $enabled ? 'active' : ($installed ? 'disabled' : 'not_licensed');
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

        // Plan 2a — per-integration licensing. Integration SKUs (`int_*`) are
        // unlocked by their OWN Lemon Squeezy key, recorded in
        // license_state['int_*'], strictly INDEPENDENT of the core bundle.
        // Buying YOOtheme Pro must never unlock Multilang or the core bundle,
        // and vice-versa. The reverse half of that rule lives in
        // coreLicenseActive(), which ignores int_* keys so an integration
        // activation can never set the perpetual `pro_activated` flag.
        if (str_starts_with($sku, 'int_')) {
            return self::hasIntegrationPro($sku);
        }

        // Core/bundle SKUs — Task #565: per-SKU core SKUs are retired; core Pro
        // is a single bundle unlocked by perpetual activation. Delegate to the
        // canonical bundle-level gate so every Pro emitter, the admin UI and the
        // settings-save endpoint derive core Pro from the SAME signal
        // (`pro_activated`). $sku is accepted for back-compat with existing call
        // sites but no longer differentiates among core SKUs.
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
     * Plan 2a — resolve whether a single integration SKU (`int_*`) is licensed
     * Pro, INDEPENDENTLY of the core bundle. Precedence mirrors isProActive():
     *   1. dev_force_free_tier === '1' → false  (QA: force-Free)
     *   2. dev_license_preview === '1' → true   (QA: force-Pro, unlocks all int_*)
     *   3. otherwise → the integration's OWN license_state resolves to 'active'
     *      (real key verified active and not expired).
     *
     * Never consults `pro_activated` or the core bundle — an unactivated core
     * install can still hold a paid integration licence, and a core-bundle buyer
     * does not get the integrations for free (product-id pinning enforces that on
     * verify()). Result is cached per-SKU for the request.
     */
    private static function hasIntegrationPro(string $sku): bool
    {
        static $cache = [];
        if (array_key_exists($sku, $cache)) {
            return $cache[$sku];
        }
        $result = false;
        try {
            $settings = self::loadMainSettings();
            if (self::settingEnabled($settings, 'dev_force_free_tier')) {
                $result = false;
            } elseif (self::settingEnabled($settings, 'dev_license_preview')) {
                $result = true;
            } else {
                $result = self::resolveRealStatus(self::loadLicenseStates()[$sku] ?? null) === 'active';
            }
        } catch (\Throwable $e) {
            $result = false;
        }
        return $cache[$sku] = $result;
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
        if (self::settingEnabled($settings, 'dev_force_free_tier')) {
            return false;
        }
        // 2. Perpetual activation flag — set once a key verifies active, never cleared.
        // 3. QA: force Pro on a Free / never-activated install.
        return self::settingEnabled($settings, 'pro_activated') || self::settingEnabled($settings, 'dev_license_preview');
    }

    /**
     * "Is this a Pro INSTALL?" — drives the admin-UI unlock (ProGate / the
     * Licenses surface), NOT the runtime emitters (those use isProActive()).
     *
     * True when ANY of:
     *   1. the combined Pro package set the `pro_installed` install marker —
     *      the signal that survives the single-plugin collapse, where the Pro
     *      package shares the `pkg_aiboost` element with Free so the package
     *      element alone can no longer distinguish the edition;
     *   2. Pro is activated / dev-previewed (isProActive);
     *   3. a legacy split layout is physically present — the old
     *      `pkg_aiboost_pro` package row or any `aiboost_*_pro` plugin row.
     *
     * Must stay TRUE for a paying customer who has installed Pro but not yet
     * entered a key, so the Licenses page + Pro controls remain reachable.
     * False only on a genuine Free install.
     */
    public static function isProInstall(): bool
    {
        $settings = self::loadMainSettings();

        // 1. install-edition marker (survives the collapse) + 2. activation.
        if (self::settingEnabled($settings, 'pro_installed') || self::isProActive($settings)) {
            return true;
        }

        // 3. legacy split layout physically present in #__extensions.
        try {
            $db    = AdapterRegistry::database()->getConnection();
            $count = (int) $db->setQuery(
                $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__extensions'))
                    ->where(
                        '(' . $db->quoteName('element') . ' = ' . $db->quote('pkg_aiboost_pro')
                        . ' OR ' . $db->quoteName('element') . ' LIKE ' . $db->quote('aiboost_%\\_pro') . ' ESCAPE ' . $db->quote('\\')
                        . ')'
                    )
            )->loadResult();
            return $count > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param array<string,mixed> $settings
     */
    private static function settingEnabled(array $settings, string $key): bool
    {
        return (string) ($settings[$key] ?? '0') === '1';
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
            $data = self::loadMainSettings();
            $data['license_simulation'] = self::cleanSimulationMap($map);
            self::persistMainSettings($data);
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
            $sim = self::loadMainSettings()['license_simulation'] ?? [];
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
            $out = self::normalizeLicenseStates(self::loadMainSettings()['license_state'] ?? []);
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
            $data = self::loadMainSettings();
            $states = is_array($data['license_state'] ?? null) ? $data['license_state'] : [];
            $states[$sku] = $state;
            $data['license_state'] = $states;

            $coreActive = self::coreLicenseActive($states);
            $data['license_tier'] = $coreActive ? 'pro' : 'free';
            self::markPerpetualActivation($data, $coreActive);
            self::persistMainSettings($data);
        } catch (\Throwable $e) {
            error_log('[AI Boost PluginRegistry] saveLicenseState failed: ' . $e->getMessage());
        }
        self::reset();
    }

    /**
     * @param array<string,mixed> $map
     * @return array<string,mixed>
     */
    private static function cleanSimulationMap(array $map): array
    {
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
        return $clean;
    }

    /**
     * @param mixed $states
     * @return array<string, array<string,mixed>>
     */
    private static function normalizeLicenseStates($states): array
    {
        $out = [];
        if (!is_array($states)) {
            return $out;
        }
        foreach ($states as $sku => $state) {
            if (is_array($state)) {
                $out[(string) $sku] = $state;
            }
        }
        return $out;
    }

    /**
     * Plan 2a — does the install hold an active CORE/bundle licence?
     *
     * ANTI-LEAK (the single most important rule of per-integration licensing):
     * integration (`int_*`) licence keys are deliberately ignored here. They are
     * licensed independently via hasPro('int_*'), so activating one must NEVER
     * set the perpetual `pro_activated` flag (which would unlock the WHOLE core
     * bundle for free) nor flip the back-compat `license_tier` to 'pro'. Only a
     * genuine core SKU (schema/og/hreflang/code/aeo/bundle) verifying active may
     * do that. Public + pure so the anti-leak regression test can assert it
     * directly without a DB seam.
     *
     * @param array<string,mixed> $states  license_state map (sku => record)
     */
    public static function coreLicenseActive(array $states): bool
    {
        foreach ($states as $sku => $state) {
            if (str_starts_with((string) $sku, 'int_')) {
                continue;
            }
            if (is_array($state) && self::resolveRealStatus($state) === 'active') {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function markPerpetualActivation(array &$data, bool $anyActive): void
    {
        if (!$anyActive || (string) ($data['pro_activated'] ?? '0') === '1') {
            return;
        }
        $data['pro_activated'] = '1';
        if (empty($data['pro_activated_at'])) {
            $data['pro_activated_at'] = gmdate('c');
        }
        $data['pro_activated_version'] = class_exists('AiBoost\\Version') ? \AiBoost\Version::VERSION : '';
    }

    /**
     * @return array<string,mixed>
     */
    private static function loadMainSettings(): array
    {
        $db = AdapterRegistry::database()->getConnection();
        $query = $db->getQuery(true)
            ->select($db->quoteName('settings_json'))
            ->from('#__aiboost_settings')
            ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'));
        $json = (string) $db->setQuery($query)->loadResult();
        return json_decode($json, true) ?: [];
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function persistMainSettings(array $data): void
    {
        $db = AdapterRegistry::database()->getConnection();
        $now = AdapterRegistry::clock()->nowSql();
        $newJson = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $id = self::mainSettingsId($db);
        $query = $id > 0
            ? self::updateMainSettingsQuery($db, $id, $newJson, $now)
            : self::insertMainSettingsQuery($db, $newJson, $now);
        $db->setQuery($query)->execute();
    }

    private static function mainSettingsId($db): int
    {
        $query = $db->getQuery(true)
            ->select('id')->from('#__aiboost_settings')
            ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'));
        return (int) $db->setQuery($query)->loadResult();
    }

    private static function updateMainSettingsQuery($db, int $id, string $json, string $now)
    {
        return $db->getQuery(true)->update('#__aiboost_settings')
            ->set($db->quoteName('settings_json') . ' = ' . $db->quote($json))
            ->set($db->quoteName('updated_at') . ' = ' . $db->quote($now))
            ->where($db->quoteName('id') . ' = ' . $id);
    }

    private static function insertMainSettingsQuery($db, string $json, string $now)
    {
        return $db->getQuery(true)->insert('#__aiboost_settings')
            ->columns(['setting_key', 'settings_json', 'created_at', 'updated_at'])
            ->values($db->quote('main') . ',' . $db->quote($json) . ',' . $db->quote($now) . ',' . $db->quote($now));
    }

    /**
     * Resolve a raw license_state record into one of:
     *   'active' | 'expired' | 'disabled' | null (= not configured)
     *
     * @param array<string,mixed>|null $state
     */
    public static function resolveRealStatus(?array $state): ?string
    {
        if (!self::hasLicenseKey($state)) {
            return null;
        }
        $status = strtolower((string) ($state['status'] ?? ''));
        return $status === 'active' ? self::activeStatusWithExpiry($state) : self::inactiveLicenseStatus($status);
    }

    /**
     * @param array<string,mixed>|null $state
     */
    private static function hasLicenseKey(?array $state): bool
    {
        return $state !== null && trim((string) ($state['key'] ?? '')) !== '';
    }

    /**
     * @param array<string,mixed> $state
     */
    private static function activeStatusWithExpiry(array $state): string
    {
        return self::licenseExpired((string) ($state['expires_at'] ?? '')) ? 'expired' : 'active';
    }

    private static function licenseExpired(string $expiresAt): bool
    {
        $expiresAt = trim($expiresAt);
        if ($expiresAt === '') {
            return false;
        }
        $expiresTs = strtotime($expiresAt);
        return $expiresTs !== false && $expiresTs < time();
    }

    private static function inactiveLicenseStatus(string $status): ?string
    {
        return in_array($status, ['expired', 'limit_reached', 'deactivated', 'invalid'], true) ? 'expired' : null;
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
