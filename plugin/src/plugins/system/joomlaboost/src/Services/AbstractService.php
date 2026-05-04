<?php

/**
 * Abstract base service for JoomlaBoost
 *
 * @package     JoomlaBoost
 * @subpackage  Plugin.System.Services
 * @since       4.0
 * @author      JoomlaBoost Team
 * @copyright   (C) 2025 JoomlaBoost. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Services;

use JoomlaBoost\Plugin\System\JoomlaBoost\Enums\EnvironmentType;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

/**
 * Abstract base service for JoomlaBoost
 *
 * Provides domain detection and universal functionality
 */
abstract class AbstractService implements ServiceInterface
{
    protected CMSApplication $app;
    protected Registry $params;

    private ?string $currentDomain = null;
    private ?string $baseUrl = null;
    protected ?ServiceContainer $serviceContainer = null;

    public function __construct(CMSApplication $app, Registry $params)
    {
        $this->app = $app;
        $this->params = $params;
    }

    /**
     * Set service container for dependency injection
     */
    public function setServiceContainer(ServiceContainer $container): void
    {
        $this->serviceContainer = $container;
    }

    /**
     * Get service container
     */
    protected function getServiceContainer(): ?ServiceContainer
    {
        return $this->serviceContainer;
    }

    /**
     * Get dependent service
     *
     * @template T of ServiceInterface
     * @param string $serviceKey
     * @return T|null
     */
    protected function getService(string $serviceKey): ?ServiceInterface
    {
        return $this->serviceContainer?->get($serviceKey);
    }

    /**
     * Check if this service is enabled
     */
    public function isEnabled(): bool
    {
        $serviceKey = $this->getServiceKey();
        return (bool) $this->params->get($serviceKey, 1);  // Default 1 not true - Registry stores strings!
    }

    /**
     * Get the current domain (auto-detected or manual)
     */
    public function getCurrentDomain(): string
    {
        if ($this->currentDomain === null) {
            $this->detectDomain();
        }

        return $this->currentDomain;
    }

    /**
     * Get the base URL with protocol
     */
    public function getBaseUrl(): string
    {
        if ($this->baseUrl === null) {
            $this->detectDomain();
        }

        return $this->baseUrl;
    }

    /**
     * Get environment type based on current domain
     */
    public function getEnvironmentType(): EnvironmentType
    {
        $domain = $this->getCurrentDomain();
        return EnvironmentType::detectFromDomain($domain);
    }

    /**
     * Check if current environment is production
     */
    public function isProduction(): bool
    {
        return $this->getEnvironmentType()->isProduction();
    }

    /**
     * Check if search engines should be allowed in current environment
     */
    public function allowSearchEngines(): bool
    {
        return $this->getEnvironmentType()->allowSearchEngines();
    }

    /**
     * Detect current domain and base URL
     */
    private function detectDomain(): void
    {
        try {
            // Check if auto-detection is enabled
            $autoDetect = (bool) $this->params->get('auto_domain_detection', true);

            if (!$autoDetect) {
                // Use manual domain if specified
                $manualDomain = trim((string) $this->params->get('manual_domain', ''));
                if ($manualDomain !== '') {
                    $parsed = parse_url($manualDomain);
                    $this->currentDomain = $parsed['host'] ?? $manualDomain;
                    $this->baseUrl = $manualDomain;
                    return;
                }
            }

            // Auto-detect from current request
            $uri = Uri::getInstance();
            $this->currentDomain = $uri->getHost();

            // Build base URL
            $scheme = $uri->getScheme();
            $port = $uri->getPort();

            $this->baseUrl = $scheme . '://' . $this->currentDomain;

            // Add port if not standard and port is actually specified
            if (
                $port &&
                (($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443))
            ) {
                $this->baseUrl .= ':' . $port;
            }
        } catch (\Throwable $e) {
            // Fallback to localhost if detection fails
            $this->currentDomain = 'localhost';
            $this->baseUrl = 'http://localhost';
        }
    }

    /**
     * Check if we're on staging environment
     */
    protected function isStaging(): bool
    {
        $domain = $this->getCurrentDomain();
        return str_contains(strtolower($domain), 'staging')
            || str_contains(strtolower($domain), 'stage')
            || str_contains(strtolower($domain), 'test')
            || str_contains(strtolower($domain), 'dev');
    }

    /**
     * Get debug mode status
     */
    protected function isDebugMode(): bool
    {
        return (bool) $this->params->get('debug_mode', false);
    }

    /**
     * Log debug message if debug mode is enabled
     *
     * @param array<string, mixed> $context
     */
    protected function logDebug(string $message, array $context = []): void
    {
        if ($this->isDebugMode()) {
            try {
                $logMessage = '[JoomlaBoost] ' . $message;
                if (!empty($context)) {
                    $logMessage .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                // Use error_log instead of JLog to avoid console.log output in HTML
                error_log($logMessage);
            } catch (\Throwable $e) {
                // Ignore logging errors
            }
        }
    }

    // ── Multilingual Parameter Resolution ─────────────────────────────────

    /**
     * Get localized parameter value with smart fallback.
     *
     * Resolution chain:
     *   1. {field}_{currentLang}   (e.g. org_name_sr)
     *   2. {field}_{defaultLang}   (e.g. org_name_en — Joomla's configured default)
     *   3. {field}                 (generic / legacy — backward compat with v0.5.x)
     *   4. DB translations table  (#__joomlaboost_translations)
     *
     * @param string $fieldName  Base field name (e.g. 'org_name', 'og_site_name')
     * @param mixed  $default    Fallback if nothing found
     * @return mixed
     */
    protected function getLocalizedParam(string $fieldName, mixed $default = ''): mixed
    {
        $langCode    = $this->getCurrentLangCode();
        $defaultLang = $this->getDefaultLangCode();

        // 1. Current language param (e.g. org_name_sr)
        $value = $this->params->get("{$fieldName}_{$langCode}", '');
        if (!empty($value)) {
            $this->logDebug("Multilang: using {$fieldName}_{$langCode}");
            return $value;
        }

        // 2. Default language param (e.g. org_name_en)
        if ($langCode !== $defaultLang) {
            $value = $this->params->get("{$fieldName}_{$defaultLang}", '');
            if (!empty($value)) {
                $this->logDebug("Multilang: fallback {$fieldName}_{$langCode} → {$fieldName}_{$defaultLang}");
                return $value;
            }
        }

        // 3. Generic / legacy param (e.g. org_name — backward compat)
        $value = $this->params->get($fieldName, '');
        if (!empty($value)) {
            $this->logDebug("Multilang: using legacy param {$fieldName}");
            return $value;
        }

        // 4. Database-backed translations (last resort)
        try {
            $translationService = new TranslationService($this->app, $this->params);
            $value = $translationService->get($fieldName);
            if (!empty($value)) {
                $this->logDebug("Multilang: DB translation for {$fieldName} ({$langCode})");
                return $value;
            }
        } catch (\Throwable $e) {
            $this->logDebug("Multilang: TranslationService error — {$e->getMessage()}");
        }

        return $default;
    }

    /**
     * Get current frontend language as 2-letter ISO code.
     *
     * @return string  e.g. 'en', 'sr', 'me'
     */
    protected function getCurrentLangCode(): string
    {
        try {
            $lang = Factory::getLanguage();
            return strtolower(substr($lang->getTag(), 0, 2));
        } catch (\Throwable $e) {
            return 'en';
        }
    }

    /**
     * Get Joomla's configured default site language as 2-letter ISO code.
     *
     * Reads from #__extensions (element = '*', client_id = 0) — the same
     * source used by LanguageService::getDefaultLanguageCode(), but returns
     * the short 2-letter code for parameter matching.
     *
     * @return string  e.g. 'en', 'sr', 'me'
     */
    protected function getDefaultLangCode(): string
    {
        static $code = null;
        if ($code !== null) {
            return $code;
        }

        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('element')
                ->from('#__extensions')
                ->where('type = ' . $db->quote('language'))
                ->where('client_id = 0')
                ->order('enabled DESC')
                ->setLimit(1);

            $db->setQuery($query);
            $tag = $db->loadResult(); // e.g. 'en-GB'

            if (!empty($tag)) {
                $code = strtolower(substr((string) $tag, 0, 2));
                return $code;
            }
        } catch (\Throwable $e) {
            // fall through
        }

        // Fallback: application config
        try {
            $tag  = Factory::getApplication()->get('language', 'en-GB');
            $code = strtolower(substr((string) $tag, 0, 2));
            return $code;
        } catch (\Throwable $e) {
            $code = 'en';
            return $code;
        }
    }

    /**
     * Get the service key for configuration lookup
     *
     * @return string The parameter key for this service
     */
    abstract protected function getServiceKey(): string;
}
