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

defined('_JEXEC') or die;

if (!class_exists('AiBoost\\Lib\\LicenseValidator', false)) :

final class LicenseValidator
{
    private const API_URL      = 'https://api.lemonsqueezy.com/v1/licenses/validate';
    private const API_ACTIVATE = 'https://api.lemonsqueezy.com/v1/licenses/activate';
    private const TIMEOUT      = 8;

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
     * Register the site's base URL for stable instance-ID generation.
     * Call once during plugin bootstrap (e.g. in services/provider.php).
     */
    public static function setBaseUrl(string $url): void
    {
        self::$siteBaseUrl = rtrim($url, '/');
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
     */
    private static function callApi(string $licenseKey, string $instanceId): string
    {
        $payload = http_build_query([
            'license_key' => $licenseKey,
            'instance_id' => $instanceId ?: self::getSiteInstanceId(),
        ]);

        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n" .
                             "Accept: application/json\r\n",
                'content' => $payload,
                'timeout' => self::TIMEOUT,
                'ignore_errors' => true,
            ],
        ]);

        try {
            $response = @file_get_contents(self::API_URL, false, $context);
        } catch (\Throwable $e) {
            error_log('[AI Boost LicenseValidator] API request failed: ' . $e->getMessage());
            return 'free';
        }

        if ($response === false || $response === '') {
            return 'free';
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
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
     * returns valid===true AND license_key.status==='active'. Any network error,
     * empty response or malformed payload yields a non-active status, so Pro is
     * never unlocked without a confirmed live license.
     *
     * @param  string $licenseKey   Raw key from the Licenses tab.
     * @param  string $instanceName Human label for the activation (site root URL).
     * @param  string $instanceId   Previously stored LS instance id, if any.
     * @return array<string,mixed>
     */
    public static function verify(string $licenseKey, string $instanceName, string $instanceId = ''): array
    {
        $licenseKey = trim($licenseKey);
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

        // Prefer validating an already-activated instance (does not consume an
        // activation); fall back to activating a fresh instance for this site.
        $resp = null;
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

        if ($resp === null) {
            $state['message'] = 'Could not reach the licensing server. Check the connection and try again.';
            return $state;
        }

        $lk       = is_array($resp['license_key'] ?? null) ? $resp['license_key'] : [];
        $lsStatus = strtolower((string) ($lk['status'] ?? ''));
        $valid    = !empty($resp['valid']);

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
     * decoded JSON array, or null on any transport/parse failure.
     *
     * @param  array<string,string> $params
     * @return array<string,mixed>|null
     */
    private static function request(string $url, array $params): ?array
    {
        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/x-www-form-urlencoded\r\n" .
                                   "Accept: application/json\r\n",
                'content'       => http_build_query($params),
                'timeout'       => self::TIMEOUT,
                'ignore_errors' => true,
            ],
        ]);

        try {
            $response = @file_get_contents($url, false, $context);
        } catch (\Throwable $e) {
            error_log('[AI Boost LicenseValidator] API request failed: ' . $e->getMessage());
            return null;
        }

        if ($response === false || $response === '') {
            return null;
        }

        $data = json_decode($response, true);
        return is_array($data) ? $data : null;
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
     * Clear the in-memory cache and reset the stored base URL.
     * Intended for use in tests.
     */
    public static function resetCache(): void
    {
        self::$cache       = [];
        self::$siteBaseUrl = '';
    }
}

endif;
