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
 * Overrides any incomplete tags inserted by Joomla Language Filter.
 */
class HreflangService extends AbstractService
{
    protected function getServiceKey(): string
    {
        return 'enable_hreflang';
    }

    /**
     * Inject hreflang <link> tags via raw HTML buffer manipulation.
     *
     * Called from onAfterRender — guaranteed to run AFTER Language Filter / Falang.
     * Strategy:
     *   1. Strip ALL existing <link rel="alternate" hreflang="..."> tags from HTML.
     *   2. Build our clean set (one per active language + x-default).
     *   3. Insert before </head>.
     *
     * @param string          $body        Raw HTML output buffer
     * @param LanguageService $langService Already-initialised LanguageService
     * @return string                      Modified buffer (or original if nothing done)
     */
    public function injectIntoBuffer(string $body, LanguageService $langService): string
    {
        $headClose = stripos($body, '</head>');
        if ($headClose === false) {
            return $body; // Not a full HTML page
        }

        // 1. Strip ALL existing hreflang link tags (including the broken Joomla x-default)
        $body = preg_replace(
            '/<link[^>]+\bhreflang\b[^>]*\/?>\s*/i',
            '',
            $body
        ) ?? $body;

        // Re-locate </head> after removal (line count may have changed)
        $headClose = stripos($body, '</head>');
        if ($headClose === false) {
            return $body;
        }

        // 2. Generate our clean hreflang tags
        $tags    = $this->generateTags($langService);
        if (empty($tags)) {
            return $body;
        }

        $linkHtml = "\n";
        foreach ($tags as $tag) {
            $linkHtml .= sprintf(
                '<link rel="alternate" hreflang="%s" href="%s">' . "\n",
                htmlspecialchars($tag['hreflang'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($tag['href'], ENT_QUOTES, 'UTF-8')
            );
        }

        // 3. Insert before </head>
        $body = substr($body, 0, $headClose) . $linkHtml . substr($body, $headClose);

        $this->logDebug('HreflangService: injected ' . count($tags) . ' hreflang tags via buffer');

        return $body;
    }

    /**
     * Inject hreflang <link> tags into the HTML document (legacy — onBeforeCompileHead).
     * Kept for backward compat but no longer called (Language Filter runs after us).
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

            // Generate full set of hreflang tags
            $tags = $this->generateTags($langService);

            if (empty($tags)) {
                return;
            }

            // Remove any existing hreflang tags added by Joomla Language Filter
            // so we don't have duplicates.
            $headData = $document->getHeadData();
            if (isset($headData['links']) && is_array($headData['links'])) {
                foreach ($headData['links'] as $url => $attribs) {
                    if (isset($attribs['hreflang'])) {
                        unset($headData['links'][$url]);
                    }
                }
                $document->setHeadData($headData);
            }

            // Inject our comprehensive set
            foreach ($tags as $tag) {
                $document->addHeadLink(
                    htmlspecialchars($tag['href'], ENT_QUOTES, 'UTF-8'),
                    'alternate',
                    'rel',
                    ['hreflang' => htmlspecialchars($tag['hreflang'], ENT_QUOTES, 'UTF-8')]
                );
            }

            $this->logDebug('HreflangService: injected ' . count($tags) . ' hreflang tags');
        } catch (\Throwable $e) {
            $this->logDebug('HreflangService: failed — ' . $e->getMessage());
        }
    }

    /**
     * Generate hreflang tag data.
     *
     * @return array<int, array{hreflang: string, href: string}>
     */
    private function generateTags(LanguageService $langService): array
    {
        $tags = [];
        $languages    = $langService->getActiveLanguages();
        $baseUrl      = rtrim((string) Uri::root(), '/');
        $defaultCode  = $langService->getDefaultLanguageCode();

        // Build a set of known SEF prefixes so buildHref can detect the lang segment
        $knownSefs = [];
        foreach ($languages as $lang) {
            $knownSefs[] = strtolower((string) $lang->sef);
        }

        foreach ($languages as $lang) {
            $hreflang = $langService->getHreflangCode($lang->lang_code);
            $href     = $this->buildHref($lang, $baseUrl, $knownSefs);

            if (empty($href)) {
                continue;
            }

            $tags[] = ['hreflang' => $hreflang, 'href' => $href];
        }

        // Add x-default pointing to the default language URL
        foreach ($languages as $lang) {
            if ($lang->lang_code === $defaultCode) {
                $href = $this->buildHref($lang, $baseUrl, $knownSefs);
                if (!empty($href)) {
                    $tags[] = ['hreflang' => 'x-default', 'href' => $href];
                }
                break;
            }
        }

        return $tags;
    }

    /**
     * Build the href URL for a given language.
     *
     * Takes the current request path, detects the existing language SEF prefix
     * (e.g. "en" or "me") and replaces it with the target language's SEF code.
     * If no known prefix is found the target prefix is prepended instead.
     *
     * @param object   $lang       Language object with ->sef property
     * @param string   $baseUrl    Site base URL without trailing slash
     * @param string[] $knownSefs  All active language SEF codes (lowercase)
     */
    private function buildHref(object $lang, string $baseUrl, array $knownSefs): string
    {
        try {
            $currentUri = Uri::getInstance();
            $path       = $currentUri->getPath();

            // Preserve trailing slash for canonical consistency
            $hasTrailingSlash = str_ends_with($path, '/');

            // Strip subdirectory prefix when Joomla is not installed at root
            $joomlaBase = parse_url((string) Uri::root(), PHP_URL_PATH) ?? '/';
            $joomlaBase = rtrim((string) $joomlaBase, '/');

            $relativePath = $path;
            if ($joomlaBase !== '' && str_starts_with($path, $joomlaBase)) {
                $relativePath = substr($path, strlen($joomlaBase));
            }

            // Build clean list of non-empty path segments
            $segments = array_values(array_filter(explode('/', $relativePath)));

            // If the first segment is a known language prefix, replace it;
            // otherwise prepend the target language prefix.
            if (!empty($segments) && in_array(strtolower($segments[0]), $knownSefs, true)) {
                $segments[0] = $lang->sef;
            } else {
                array_unshift($segments, $lang->sef);
            }

            $newPath = '/' . implode('/', $segments);

            if ($hasTrailingSlash) {
                $newPath .= '/';
            }

            return $baseUrl . $joomlaBase . $newPath;
        } catch (\Throwable $e) {
            return '';
        }
    }
}
