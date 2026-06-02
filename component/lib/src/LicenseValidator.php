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
    private const API_URL    = 'https://api.lemonsqueezy.com/v1/licenses/validate';
    private const TIMEOUT    = 8;

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
