<?php

/**
 * Hreflang Service for JoomlaBoost
 *
 * @package     JoomlaBoost
 * @subpackage  Plugin.System.Services
 * @since       Joomla 4.0, PHP 8.1+
 * @author      JoomlaBoost Team
 * @copyright   (C) 2025 JoomlaBoost. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Services;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Uri\Uri;

/**
 * Hreflang Service
 *
 * Generates <link rel="alternate" hreflang="..."> tags for multilingual pages.
 *
 * Priority:
 * 1. Falang (single Joomla install + Falang translation layer) — primary for vividblue.me
 * 2. Native Joomla multilingual (Language Associations) — fallback
 *
 * Guard: if Joomla Language Filter plugin already injected hreflang tags into
 * the document, this service skips injection to avoid duplicates.
 */
class HreflangService extends AbstractService
{
    protected function getServiceKey(): string
    {
        return 'enable_hreflang';
    }

    /**
     * Inject hreflang <link> tags into the HTML document.
     *
     * Skips silently if:
     * - Service is disabled
     * - Site is not multilingual (only 1 language)
     * - Hreflang tags already exist in document head (Language Filter guard)
     */
    public function injectIntoDocument(HtmlDocument $document): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        try {
            $langService = new LanguageService($this->app, $this->params);

            if (!$langService->isMultilingual()) {
                return;
            }

            // Guard: don't duplicate if Joomla Language Filter already added hreflang tags
            if ($this->hasExistingHreflangTags($document)) {
                $this->logDebug('HreflangService: skipping — Language Filter already injected hreflang tags');
                return;
            }

            $tags = $this->generateTags($langService);

            foreach ($tags as $tag) {
                $document->addCustomTag(
                    '<link rel="alternate" hreflang="'
                        . htmlspecialchars($tag['hreflang'], ENT_QUOTES, 'UTF-8')
                        . '" href="'
                        . htmlspecialchars($tag['href'], ENT_QUOTES, 'UTF-8')
                        . '">'
                );
            }

            $this->logDebug('HreflangService: injected ' . count($tags) . ' hreflang tags');
        } catch (\Throwable $e) {
            $this->logDebug('HreflangService: failed — ' . $e->getMessage());
        }
    }

    /**
     * Generate hreflang tag data for all active languages + x-default.
     *
     * @return array<int, array{hreflang: string, href: string}>
     */
    public function generateTags(LanguageService $langService): array
    {
        $tags       = [];
        $languages  = $langService->getActiveLanguages();
        $baseUrl    = rtrim((string) Uri::root(), '/');
        $defaultCode = $langService->getDefaultLanguageCode();

        foreach ($languages as $lang) {
            $hreflang = $langService->getHreflangCode($lang->lang_code);
            $href     = $this->buildHref($langService, $lang, $baseUrl);

            if (empty($href)) {
                continue;
            }

            $tags[] = ['hreflang' => $hreflang, 'href' => $href];

            // x-default points to the default language URL
            if ($lang->lang_code === $defaultCode) {
                $tags[] = ['hreflang' => 'x-default', 'href' => $href];
            }
        }

        return $tags;
    }

    /**
     * Build the href URL for a language.
     *
     * Uses Falang if active (primary), otherwise native Joomla SEF URL.
     */
    private function buildHref(LanguageService $langService, object $lang, string $baseUrl): string
    {
        try {
            $currentUri = Uri::getInstance();

            // For Falang: swap language SEF prefix in current URL path
            if ($langService->isFalangActive()) {
                return $this->buildFalangHref($lang, $baseUrl, $currentUri);
            }

            // Native Joomla multilingual: use LanguageService URL builder
            $input       = $this->app->getInput();
            $option      = $input->getCmd('option', '');
            $view        = $input->getCmd('view', '');
            $id          = $input->getInt('id', 0);

            if ($option && $view && $id) {
                $internalLink = "index.php?option={$option}&view={$view}&id={$id}";
                return $langService->buildUrlForLanguage($internalLink, $lang->lang_code, $baseUrl);
            }

            // Fallback: swap SEF prefix
            return $this->buildFalangHref($lang, $baseUrl, $currentUri);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Build href by swapping the language SEF prefix in the current URL.
     *
     * Example: /en/spa-center → /me/spa-center
     *
     * Works for Falang (same slug, different prefix) and simple prefix-based setups.
     */
    private function buildFalangHref(object $lang, string $baseUrl, Uri $currentUri): string
    {
        $path = $currentUri->getPath();

        // Remove base URL path prefix if Joomla is installed in a subdirectory
        $joomlaBase = parse_url((string) Uri::root(), PHP_URL_PATH) ?? '/';
        $joomlaBase = rtrim((string) $joomlaBase, '/');

        $relativePath = $path;
        if ($joomlaBase !== '' && str_starts_with($path, $joomlaBase)) {
            $relativePath = substr($path, strlen($joomlaBase));
        }

        // Normalise: ensure leading slash
        $relativePath = '/' . ltrim($relativePath, '/');

        // Split path into segments and replace first non-empty segment (the lang prefix)
        $segments = explode('/', $relativePath);

        // Find and replace the language prefix segment
        foreach ($segments as $i => $segment) {
            if ($segment === '') {
                continue;
            }

            // Replace this segment with the target language SEF prefix
            $segments[$i] = $lang->sef;
            break;
        }

        $newPath = implode('/', $segments);

        return $baseUrl . $joomlaBase . $newPath;
    }

    /**
     * Check if the document already has hreflang link tags (from Language Filter).
     */
    private function hasExistingHreflangTags(HtmlDocument $document): bool
    {
        try {
            $headData = $document->getHeadData();

            if (!isset($headData['custom']) || !is_array($headData['custom'])) {
                return false;
            }

            foreach ($headData['custom'] as $tag) {
                if (is_string($tag) && str_contains($tag, 'hreflang')) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
