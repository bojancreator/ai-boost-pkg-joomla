<?php
/**
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib;

defined('_JEXEC') or die;

use Joomla\Registry\Registry;

abstract class AbstractService implements ServiceInterface
{
    protected ?AppContextInterface $ctx;
    protected Registry $params;

    private ?string $currentDomain = null;
    private ?string $baseUrl = null;

    public function __construct(?AppContextInterface $ctx, Registry $params)
    {
        $this->ctx    = $ctx;
        $this->params = $params;
    }

    public function isEnabled(): bool
    {
        return (bool) $this->params->get($this->getServiceKey(), 1);
    }

    protected function getLicenseTier(): string
    {
        return strtolower(trim((string) $this->params->get('license_tier', '')));
    }

    protected function isProTier(): bool
    {
        if ((bool) $this->params->get('dev_force_free_tier', false)) {
            return false;
        }
        if ((bool) $this->params->get('dev_license_preview', false)) {
            return true;
        }

        $key = trim((string) $this->params->get('license_key', ''));
        if ($key === '') {
            return false;
        }

        $validFormat = (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $key
        );
        if (!$validFormat) {
            return false;
        }

        $tier = $this->getLicenseTier();
        return in_array($tier, ['basic', 'professional', 'starter', 'developer', 'agency'], true);
    }

    public function getCurrentDomain(): string
    {
        if ($this->currentDomain === null) {
            $this->detectDomain();
        }
        return $this->currentDomain;
    }

    public function getBaseUrl(): string
    {
        if ($this->baseUrl === null) {
            $this->detectDomain();
        }
        return $this->baseUrl;
    }

    private function detectDomain(): void
    {
        try {
            $autoDetect = (bool) $this->params->get('auto_domain_detection', true);
            if (!$autoDetect) {
                $manualDomain = trim((string) $this->params->get('manual_domain', ''));
                if ($manualDomain !== '') {
                    $parsed = parse_url($manualDomain);
                    $this->currentDomain = $parsed['host'] ?? $manualDomain;
                    $this->baseUrl       = $manualDomain;
                    return;
                }
            }

            if ($this->ctx !== null) {
                $base   = $this->ctx->getBaseUrl();
                $parsed = parse_url($base);
                $scheme = $parsed['scheme'] ?? 'http';
                $host   = $parsed['host']   ?? 'localhost';
                $port   = isset($parsed['port']) ? (int) $parsed['port'] : null;

                $this->currentDomain = $host;
                $this->baseUrl       = $scheme . '://' . $host;
                if ($port && (($scheme === 'http' && $port !== 80) || ($scheme === 'https' && $port !== 443))) {
                    $this->baseUrl .= ':' . $port;
                }
            } else {
                $this->currentDomain = 'localhost';
                $this->baseUrl       = 'http://localhost';
            }
        } catch (\Throwable $e) {
            $this->currentDomain = 'localhost';
            $this->baseUrl       = 'http://localhost';
        }
    }

    protected function isDebugMode(): bool
    {
        return (bool) $this->params->get('debug_mode', false);
    }

    protected function logDebug(string $message): void
    {
        if ($this->isDebugMode()) {
            error_log('[AiBoost] ' . $message);
        }
    }

    protected function getCurrentLangCode(): string
    {
        try {
            if ($this->ctx !== null) {
                return strtolower(substr($this->ctx->getActiveLanguage(), 0, 2));
            }
            return 'en';
        } catch (\Throwable $e) {
            return 'en';
        }
    }

    abstract protected function getServiceKey(): string;
}
