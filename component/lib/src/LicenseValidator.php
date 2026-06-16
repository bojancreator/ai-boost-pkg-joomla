<?php
/**
 * AI Boost — LicenseValidator
 *
 * Validates a Lemon Squeezy license key by calling the LS Licenses API
 * (POST /v1/licenses/validate). Returns the resolved tier string: 'pro' or 'free'.
 *
 * Results are cached per-request in a static property so multiple plugins
 * can call validate() for the same key without hitting the API twice.
 *
 * Usage:
 *   LicenseValidator::setBaseUrl('https://example.com'); // call once during bootstrap
 *   $tier = LicenseValidator::validate($licenseKey);
 *   // $tier === 'pro' or 'free'
 *
 * @package     AiBoost\Lib
 * @version     0.7.0
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib;

use AiBoost\Lib\Cms\AdapterRegistry;

defined('_JEXEC') or die;

if (!class_exists('AiBoost\\Lib\\LicenseValidator', false)) :

final class LicenseValidator
{
    private const API_URL      = 'https://api.lemonsqueezy.com/v1/licenses/validate';
    private const API_ACTIVATE = 'https://api.lemonsqueezy.com/v1/licenses/activate';
    private const TIMEOUT      = 8;

    /**
     * Lemon Squeezy store ID that genuine AI Boost licenses belong to.
     *
     * SECURITY — store pinning. The Lemon Squeezy license endpoints accept
     * keys issued by ANY store, so without this check a self-issued key from
     * a free LS account would activate Pro forever. verify() rejects every
     * response whose meta.store_id does not strictly equal this value, and
     * FAILS CLOSED (loud "not configured" error, no API call) while null.
     *
     * Configured 2026-06-16 to the aiboostnow.com Lemon Squeezy store. Store and
     * product IDs are stable across Lemon Squeezy test and live mode, so this same
     * value serves the demo and production; re-confirm against the dashboard before
     * the first public launch.
     *
     * @var int|null
     */
    public const EXPECTED_STORE_ID = 367944;

    /**
     * Plan 2a — per-integration product pinning. Maps each independently-sold
     * integration SKU to its Lemon Squeezy product ID.
     *
     * SECURITY — product pinning (fail closed). Store pinning alone cannot keep
     * the per-integration products apart: a YOOtheme-Pro key and a Multilang key
     * are issued by the SAME store, so without product pinning either key would
     * activate either integration. verify() rejects any response whose
     * meta.product_id does not strictly equal the pinned ID for the requested
     * SKU, and the controller refuses to even call out for an int_* SKU whose ID
     * is still null (fail-closed, exactly like EXPECTED_STORE_ID).
     *
     * Configured 2026-06-16 to the aiboostnow.com Lemon Squeezy products
     * (stable across test/live mode; re-confirm before the first public launch).
     *
     * @var array<string,int|null>
     */
    public const EXPECTED_PRODUCT_IDS = [
        'int_yootheme' => 1138446,
        'int_falang'   => 1138396,
    ];

    /**
     * Core product pinning — the Lemon Squeezy product IDs of the core Pro
     * tiers (PRO 3-site / PRO+ 10-site / Unlimited web sites). All three unlock
     * the SAME core bundle (`pro_activated`); the tier differs only commercially
     * (the plugin never counts sites), so any one of these IDs activates core.
     *
     * SECURITY — keep the cheap add-on products out of core. The integration
     * products (Multilang, YOOtheme) are issued by the SAME store, so store
     * pinning alone would let a €20 YOOtheme key activate the €65+ core bundle.
     * When this list is non-empty, verify() rejects any core key whose
     * meta.product_id is not one of these IDs (fail closed, exactly like the
     * per-integration product pin). Left EMPTY, core falls back to store-pin
     * only — the looser, pre-launch behaviour.
     *
     * Configured 2026-06-16 to the three core tiers (PRO 3-site / PRO+ 10-site /
     * Unlimited) of the aiboostnow.com Lemon Squeezy store (stable across
     * test/live mode; re-confirm before the first public launch).
     *
     * @var array<int>
     */
    public const EXPECTED_CORE_PRODUCT_IDS = [1126398, 1126399, 1126400];

    /**
     * Per-request cache: licenseKey => 'pro'|'free'
     * @var array<string, string>
     */
    private static array $cache = [];

    /**
     * Site base URL used to generate a stable instance identifier.
     * Set once via setBaseUrl() during plugin bootstrap.
     */
    private static string $siteBaseUrl = '';

    /**
     * Test-only override for EXPECTED_STORE_ID so both pinning branches can
     * be exercised before the production store exists. Only honoured while
     * $expectedStoreIdOverridden is true (a null override simulates the
     * unconfigured state regardless of the constant).
     */
    private static ?int $expectedStoreIdOverride = null;

    /** True while setExpectedStoreId() has installed an override. */
    private static bool $expectedStoreIdOverridden = false;

    /**
     * Test-only override map for EXPECTED_PRODUCT_IDS (sku => int|null) so the
     * product-pinning branches can be exercised before the production products
     * exist. Only honoured while $expectedProductIdsOverridden is true.
     *
     * @var array<string,int|null>
     */
    private static array $expectedProductIdsOverride = [];

    /** True while setExpectedProductId() has installed an override. */
    private static bool $expectedProductIdsOverridden = false;

    /**
     * Test-only override for EXPECTED_CORE_PRODUCT_IDS so the core product-set
     * pinning branch can be exercised before the production core products
     * exist. Only honoured while $expectedCoreProductIdsOverridden is true.
     *
     * @var array<int>
     */
    private static array $expectedCoreProductIdsOverride = [];

    /** True while setExpectedCoreProductIds() has installed an override. */
    private static bool $expectedCoreProductIdsOverridden = false;

    /**
     * Optional transport override so unit tests never hit the network.
     * Signature: fn (string $url, array<string,string> $params): ?string —
     * returns the raw response body, null for a network failure, or throws
     * \RuntimeException to simulate a host with no usable HTTP transport.
     *
     * @var callable|null
     */
    private static $transport = null;

    /**
     * Register the site's base URL for stable instance-ID generation.
     * Call once during plugin bootstrap (e.g. in services/provider.php).
     */
    public static function setBaseUrl(string $url): void
    {
        self::$siteBaseUrl = rtrim($url, '/');
    }

    /**
     * Override the pinned store ID (including null to simulate the
     * unconfigured state). Intended for use in tests — production code
     * must rely on the EXPECTED_STORE_ID constant only.
     */
    public static function setExpectedStoreId(?int $storeId): void
    {
        self::$expectedStoreIdOverride   = $storeId;
        self::$expectedStoreIdOverridden = true;
    }

    /**
     * Override the pinned product ID for one SKU (including null to simulate the
     * unconfigured state). Intended for use in tests — production code must rely
     * on the EXPECTED_PRODUCT_IDS constant only.
     */
    public static function setExpectedProductId(string $sku, ?int $productId): void
    {
        self::$expectedProductIdsOverride[$sku] = $productId;
        self::$expectedProductIdsOverridden     = true;
    }

    /**
     * Override the pinned core product IDs (the 3/10/unlimited tiers). Intended
     * for use in tests — production code must rely on the
     * EXPECTED_CORE_PRODUCT_IDS constant only.
     *
     * @param array<int> $productIds
     */
    public static function setExpectedCoreProductIds(array $productIds): void
    {
        self::$expectedCoreProductIdsOverride   = array_values(array_filter($productIds, 'is_int'));
        self::$expectedCoreProductIdsOverridden = true;
    }

    /**
     * Inject a transport callable (see self::$transport for the contract).
     * Intended for use in tests; pass null to restore the real HTTP client.
     */
    public static function setTransport(?callable $transport): void
    {
        self::$transport = $transport;
    }

    /**
     * Validate a license key and return the resolved tier.
     *
     * @param  string $licenseKey  Raw license key from plugin params.
     * @param  string $instanceId  Optional instance identifier (site domain).
     * @return string              'pro' if valid and active, 'free' otherwise.
     */
    public static function validate(string $licenseKey, string $instanceId = ''): string
    {
        $licenseKey = trim($licenseKey);

        if ($licenseKey === '') {
            return 'free';
        }

        // Return cached result for this key within the same request
        if (isset(self::$cache[$licenseKey])) {
            return self::$cache[$licenseKey];
        }

        $tier = self::callApi($licenseKey, $instanceId);
        self::$cache[$licenseKey] = $tier;
        return $tier;
    }

    /**
     * Make the API call to Lemon Squeezy and parse the response.
     * Fail-closed: any transport, network or parse failure resolves to 'free'.
     */
    private static function callApi(string $licenseKey, string $instanceId): string
    {
        try {
            $data = self::request(self::API_URL, [
                'license_key' => $licenseKey,
                'instance_id' => $instanceId ?: self::getSiteInstanceId(),
            ]);
        } catch (\RuntimeException $e) {
            error_log('[AI Boost LicenseValidator] ' . $e->getMessage());
            return 'free';
        }

        if ($data === null) {
            return 'free';
        }

        // Lemon Squeezy returns {"valid": true, "license_key": {"status": "active"}}
        if (
            !empty($data['valid']) &&
            isset($data['license_key']['status']) &&
            $data['license_key']['status'] === 'active'
        ) {
            return 'pro';
        }

        return 'free';
    }

    /**
     * Verify (and, on first use, activate) a Lemon Squeezy license key and
     * return a full state record in the shape PluginRegistry::saveLicenseState
     * expects: key, status, expires_at, activations_remaining, instance_id,
     * verified_at, mock=false, message.
     *
     * Flow: if a prior instance_id is known, /validate it; otherwise (or if that
     * instance is gone) /activate a new instance for this site. The result is
     * FAIL-CLOSED — `status` is only ever 'active' when Lemon Squeezy itself
     * returns valid===true AND license_key.status==='active' AND the response's
     * meta.store_id strictly matches EXPECTED_STORE_ID (store pinning) AND,
     * when a product pin is supplied, meta.product_id is one of the allowed
     * products. Any network error, missing HTTP transport, empty response,
     * malformed payload, foreign store, wrong product or unconfigured pin
     * yields a non-active status, so Pro is never unlocked without a confirmed
     * live license from our own store. meta.product_id is always captured into
     * the state for tier mapping.
     *
     * @param  string         $licenseKey   Raw key from the Licenses tab.
     * @param  string         $instanceName Human label for the activation (site root URL).
     * @param  string         $instanceId   Previously stored LS instance id, if any.
     * @param  int|array<int>|null $expectedProductId  Product pin: null/[] = store-pin
     *         only (core default); a single int = one pinned integration product;
     *         a list = any-of several pinned products (the core 3/10/unlimited tiers).
     * @return array<string,mixed>
     */
    public static function verify(string $licenseKey, string $instanceName, string $instanceId = '', int|array|null $expectedProductId = null): array
    {
        $licenseKey = trim($licenseKey);

        // Normalise the product pin to a list of allowed product IDs. An empty
        // list means store-pin only (core's pre-launch default); a non-empty
        // list means meta.product_id MUST be one of these (fail closed).
        $allowedProductIds = [];
        if (is_int($expectedProductId)) {
            $allowedProductIds = [$expectedProductId];
        } elseif (is_array($expectedProductId)) {
            $allowedProductIds = array_values(array_filter($expectedProductId, 'is_int'));
        }

        $state = [
            'key'                   => $licenseKey,
            'mock'                  => false,
            'verified_at'           => gmdate('c'),
            'expires_at'            => null,
            'activations_remaining' => null,
            'instance_id'           => $instanceId,
            'status'                => 'invalid',
            'message'               => '',
        ];

        if ($licenseKey === '') {
            $state['message'] = 'License key is required.';
            return $state;
        }

        // SECURITY — fail closed (and loudly) until store pinning is configured.
        // Without a pinned store ID any valid Lemon Squeezy key would unlock Pro.
        if (self::expectedStoreId() === null) {
            $state['message'] = 'License validation is not configured (store pinning missing). '
                . 'Set LicenseValidator::EXPECTED_STORE_ID to the production Lemon Squeezy store ID.';
            return $state;
        }

        // Prefer validating an already-activated instance (does not consume an
        // activation); fall back to activating a fresh instance for this site.
        $resp = null;
        try {
            if ($instanceId !== '') {
                $resp = self::request(self::API_URL, [
                    'license_key' => $licenseKey,
                    'instance_id' => $instanceId,
                ]);
            }
            if ($resp === null || empty($resp['valid'])) {
                $act = self::request(self::API_ACTIVATE, [
                    'license_key'   => $licenseKey,
                    'instance_name' => $instanceName !== '' ? $instanceName : 'aiboost-site',
                ]);
                if ($act !== null) {
                    $resp = $act;
                    if (isset($act['instance']['id'])) {
                        $state['instance_id'] = (string) $act['instance']['id'];
                    }
                }
            }
        } catch (\RuntimeException $e) {
            // Distinct from a plain connectivity failure so support can tell
            // host configuration problems from a bad key or a network blip.
            error_log('[AI Boost LicenseValidator] ' . $e->getMessage());
            $state['message'] = 'License check failed: this server cannot make outbound HTTP requests '
                . '(no usable HTTP transport — cURL missing and allow_url_fopen disabled). '
                . 'Ask your hosting provider to enable the cURL PHP extension, then try again.';
            return $state;
        }

        if ($resp === null) {
            $state['message'] = 'Could not reach the licensing server. Check the connection and try again.';
            return $state;
        }

        $lk       = is_array($resp['license_key'] ?? null) ? $resp['license_key'] : [];
        $lsStatus = strtolower((string) ($lk['status'] ?? ''));
        $valid    = !empty($resp['valid']);

        $meta      = is_array($resp['meta'] ?? null) ? $resp['meta'] : [];
        $storeId   = is_numeric($meta['store_id'] ?? null) ? (int) $meta['store_id'] : null;
        $productId = is_numeric($meta['product_id'] ?? null) ? (int) $meta['product_id'] : null;
        if ($productId !== null) {
            // Captured for the state record + product pinning (below).
            $state['product_id'] = $productId;
        }

        // SECURITY — store pinning (fail closed). Reject any response naming a
        // foreign store, and any would-be activation that does not prove its
        // provenance at all. Non-active outcomes without a store ID fall
        // through to the normal error mapping below.
        if (
            ($storeId !== null && $storeId !== self::expectedStoreId())
            || ($storeId === null && $valid && $lsStatus === 'active')
        ) {
            $state['message'] = 'This license key belongs to a different product or store. '
                . 'Use the AI Boost for Joomla key from your purchase email.';
            return $state;
        }

        // SECURITY — product pinning (fail closed). When the caller supplies an
        // allowed-product list, reject any active key whose product_id is not in
        // it, or that proves no product at all. This keeps the same-store
        // products apart in both directions: a YOOtheme key cannot activate
        // Multilang (single-product pin), and neither cheap add-on key can
        // activate the core bundle (core's 3/10/unlimited list). An empty list
        // (core's pre-launch default) skips this branch — store pinning only.
        if (
            $allowedProductIds !== []
            && (
                ($productId !== null && !in_array($productId, $allowedProductIds, true))
                || ($productId === null && $valid && $lsStatus === 'active')
            )
        ) {
            $state['message'] = 'This license key is for a different AI Boost product. '
                . 'Use the key from the matching purchase email for this product.';
            return $state;
        }

        if (!empty($lk['expires_at'])) {
            $state['expires_at'] = (string) $lk['expires_at'];
        }
        if (isset($lk['activation_limit']) && $lk['activation_limit'] !== null) {
            $used  = (int) ($lk['activation_usage'] ?? 0);
            $limit = (int) $lk['activation_limit'];
            $state['activations_remaining'] = max(0, $limit - $used);
        }

        if ($valid && $lsStatus === 'active') {
            $state['status']  = 'active';
            $state['message'] = 'License is active. Updates and support are available.';
        } elseif ($lsStatus === 'expired') {
            $state['status']  = 'expired';
            $state['message'] = 'This license has expired. Renew at aiboostnow.com/account to restore updates and support.';
        } elseif ($lsStatus === 'disabled') {
            $state['status']  = 'deactivated';
            $state['message'] = 'This license has been disabled. Contact support or purchase a new one at aiboostnow.com.';
        } else {
            $error = is_string($resp['error'] ?? null) ? trim((string) $resp['error']) : '';
            $state['status']  = (stripos($error, 'activation limit') !== false) ? 'limit_reached' : 'invalid';
            $state['message'] = $error !== '' ? $error : 'License key not recognised. Check the key from your purchase email.';
        }

        return $state;
    }

    /**
     * POST form params to a Lemon Squeezy license endpoint and return the
     * decoded JSON array, or null on any network/parse failure.
     *
     * @param  array<string,string> $params
     * @return array<string,mixed>|null
     * @throws \RuntimeException When no HTTP transport is available on this host.
     */
    private static function request(string $url, array $params): ?array
    {
        $body = self::$transport !== null
            ? (self::$transport)($url, $params)
            : self::httpPost($url, $params);

        if (!is_string($body) || $body === '') {
            return null;
        }

        $data = json_decode($body, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Real transport: POST via the CMS HTTP client (Joomla HttpFactory under
     * the hood) so activation also works on hardened hosts where
     * allow_url_fopen is disabled. Returns the raw response body for ANY
     * status code — Lemon Squeezy sends JSON error details on 4xx — or null
     * when the request itself failed.
     *
     * @param  array<string,string> $params
     * @throws \RuntimeException When no HTTP transport is available (lets the
     *                           caller distinguish host config from a bad key).
     */
    private static function httpPost(string $url, array $params): ?string
    {
        try {
            if (class_exists('AiBoost\\Lib\\Cms\\AdapterRegistry')) {
                $http = AdapterRegistry::http()->getClient();
            } elseif (class_exists('Joomla\\CMS\\Http\\HttpFactory')) {
                // Standalone plugin bundle without the Cms adapter layer.
                $http = \Joomla\CMS\Http\HttpFactory::getHttp();
            } else {
                throw new \RuntimeException('no HTTP client class found');
            }
        } catch (\Throwable $e) {
            // HttpFactory throws when neither the cURL nor the stream
            // transport is usable on this host.
            throw new \RuntimeException('HTTP transport unavailable: ' . $e->getMessage(), 0, $e);
        }

        try {
            $response = $http->post(
                $url,
                http_build_query($params),
                [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept'       => 'application/json',
                ],
                self::TIMEOUT
            );
        } catch (\Throwable $e) {
            error_log('[AI Boost LicenseValidator] API request failed: ' . $e->getMessage());
            return null;
        }

        $body = $response !== null ? (string) $response->body : '';
        return $body !== '' ? $body : null;
    }

    /**
     * Resolve the pinned store ID — the test override when installed,
     * otherwise the EXPECTED_STORE_ID release constant.
     */
    private static function expectedStoreId(): ?int
    {
        return self::$expectedStoreIdOverridden
            ? self::$expectedStoreIdOverride
            : self::EXPECTED_STORE_ID;
    }

    /**
     * Plan 2a — the pinned Lemon Squeezy product ID for an integration SKU, or
     * null when none is configured (which the controller treats as fail-closed
     * "integration licensing not configured"). Core SKUs are not product-pinned
     * and always return null.
     */
    public static function expectedProductId(string $sku): ?int
    {
        if (self::$expectedProductIdsOverridden && array_key_exists($sku, self::$expectedProductIdsOverride)) {
            return self::$expectedProductIdsOverride[$sku];
        }
        return self::EXPECTED_PRODUCT_IDS[$sku] ?? null;
    }

    /**
     * The pinned core product IDs (the 3/10/unlimited tiers) — the test override
     * when installed, otherwise the EXPECTED_CORE_PRODUCT_IDS release constant.
     * An empty list means core is store-pinned only (pre-launch behaviour).
     *
     * @return array<int>
     */
    public static function expectedCoreProductIds(): array
    {
        return self::$expectedCoreProductIdsOverridden
            ? self::$expectedCoreProductIdsOverride
            : self::EXPECTED_CORE_PRODUCT_IDS;
    }

    /**
     * Generate a stable instance identifier based on the site's root URL.
     * Used so LS can track activations per site.
     */
    private static function getSiteInstanceId(): string
    {
        if (self::$siteBaseUrl !== '') {
            return md5(self::$siteBaseUrl);
        }
        return md5($_SERVER['HTTP_HOST'] ?? 'unknown');
    }

    /**
     * Clear the in-memory cache and reset the stored base URL, transport
     * override and store-ID override. Intended for use in tests.
     */
    public static function resetCache(): void
    {
        self::$cache                     = [];
        self::$siteBaseUrl               = '';
        self::$transport                 = null;
        self::$expectedStoreIdOverride   = null;
        self::$expectedStoreIdOverridden = false;
        self::$expectedProductIdsOverride   = [];
        self::$expectedProductIdsOverridden = false;
        self::$expectedCoreProductIdsOverride   = [];
        self::$expectedCoreProductIdsOverridden = false;
    }
}

endif;
