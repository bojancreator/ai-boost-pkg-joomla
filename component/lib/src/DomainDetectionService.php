<?php
/**
 * AI Boost Shared Library — Domain Detection Service
 *
 * Resolves the canonical domain/URL for the current site.
 * Respects the `manual_domain` setting when set, otherwise derives the
 * origin from the CMS live-site configuration via AppContextInterface.
 *
 * AppContextInterface is injected so this service makes no Factory:: / Uri:: calls.
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Lib;

defined('_JEXEC') or die;

class DomainDetectionService
{
    public function __construct(private readonly AppContextInterface $ctx) {}

    /**
     * Resolve the site's canonical base URL (scheme + host, no trailing slash).
     *
     * Priority order:
     *   1. `manual_domain` setting (if non-empty and valid URL).
     *   2. CMS configuration `live_site` value.
     *   3. AppContextInterface::getBaseUrl() — derived from the current request.
     *
     * @param array<string, mixed> $settings Plugin/component settings array.
     * @return string e.g. 'https://example.com'
     */
    public function getBaseUrl(array $settings = []): string
    {
        // 1. Explicit override from settings
        $manual = trim((string) ($settings['manual_domain'] ?? ''));
        if ($manual !== '' && filter_var($manual, FILTER_VALIDATE_URL)) {
            return rtrim($manual, '/');
        }

        // 2. CMS global config live_site
        try {
            $liveSite = trim($this->ctx->getConfigValue('live_site', ''));
            if ($liveSite !== '' && filter_var($liveSite, FILTER_VALIDATE_URL)) {
                return rtrim($liveSite, '/');
            }
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning('[AiBoost] DomainDetectionService: could not read live_site config: ' . $e->getMessage());
        }

        // 3. Derive from AppContextInterface base URL
        try {
            $base = rtrim($this->ctx->getBaseUrl(), '/');
            if ($base !== '') {
                return $base;
            }
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning('[AiBoost] DomainDetectionService: could not derive base URL: ' . $e->getMessage());
        }

        return '';
    }

    /**
     * Return only the host part (e.g. 'example.com') without scheme or path.
     *
     * @param array<string, mixed> $settings
     */
    public function getHost(array $settings = []): string
    {
        $url = $this->getBaseUrl($settings);
        if ($url === '') {
            return '';
        }

        $parsed = parse_url($url, PHP_URL_HOST);
        return is_string($parsed) ? $parsed : '';
    }
}
