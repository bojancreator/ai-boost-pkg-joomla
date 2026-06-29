<?php

/**
 * AI Boost — PageResolver
 *
 * The single, CMS-neutral resolver that answers "where am I?" for the current
 * request and returns a memoised PageContext. It reads the request ONLY through
 * AppContextInterface and the Cms DatabaseAdapter — never through Factory/Uri/
 * direct `#__` calls — which is exactly what makes it portable to WordPress.
 *
 * T1 slice S0 (docs/analysis/T1-resolver-design.md §2): the resolver exists and
 * is wired into AdapterRegistry, but NO plugin/service consumes it yet, so the
 * rendered site is byte-for-byte unchanged. Consumers migrate onto it one
 * behaviour-preserving slice at a time (S2+); the one intended behaviour change
 * (unifying homepage detection) is a separate, signed-off slice (S7).
 *
 * @package     AiBoost\Lib\Page
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Page;

use AiBoost\Lib\AppContextInterface;
use AiBoost\Lib\Cms\DatabaseAdapter;

defined('_JEXEC') or defined('ABSPATH') or die;

final class PageResolver implements PageResolverInterface
{
    private ?PageContext $resolved = null;

    public function __construct(
        private readonly AppContextInterface $ctx,
        private readonly IndexabilityPolicy $indexability,
        private readonly DatabaseAdapter $db,
    ) {
    }

    public function resolve(?string $canonicalUrlMap = null): PageContext
    {
        $base = $this->resolved ??= $this->build();

        // T1·S5 — canonical URL map. Only the canonical consumer (aiboost_core)
        // threads in the raw `canonical_url_map` setting; every other consumer
        // passes nothing and gets the bare scheme://host/path canonical the base
        // context already carries (byte-unchanged from before S5). A map hit
        // returns a NEW context carrying the mapped canonical without mutating the
        // memoised base, so the answer is identical regardless of which consumer
        // resolves first — no dependence on plugin ordering.
        if ($canonicalUrlMap === null) {
            return $base;
        }
        $mapped = $this->applyCanonicalUrlMap($canonicalUrlMap, $base->canonical);

        return $mapped === $base->canonical ? $base : $base->withCanonical($mapped);
    }

    private function build(): PageContext
    {
        $option = $this->ctx->getCurrentOption();
        $view   = $this->ctx->getCurrentView();
        $rawId  = $this->ctx->getCurrentId();
        $home   = $this->ctx->isHomepage();

        [$type, $entityKind, $entityId] = $this->classify($option, $view, $rawId, $home);

        $language       = $this->ctx->getActiveLanguage();
        $globalDefault  = $this->ctx->getDefaultLanguage();
        $siteDefault    = $this->resolveSiteDefaultLanguage($globalDefault);
        $canonical      = $this->bareCurrentUrl();

        // Per-request indexability. The per-page noindex capability is OFF by
        // default (decision D2), so this is [true, ''] for every rendered page —
        // i.e. no behaviour change. The setting that can flip it arrives at S8.
        [$indexable, $noindexReason] = $this->indexability->forRenderedPage($type, false);

        return new PageContext(
            type:                  $type,
            entityKind:            $entityKind,
            entityId:              $entityId,
            option:                $option,
            view:                  $view,
            rawId:                 $rawId,
            isHomepage:            $home,
            language:              $language,
            siteDefaultLanguage:   $siteDefault,
            globalDefaultLanguage: $globalDefault,
            canonical:             $canonical,
            indexable:             $indexable,
            noindexReason:         $noindexReason,
        );
    }

    /**
     * The bare canonical URL for the current request: scheme://host/path, with no
     * query string or fragment. CMS-neutral reproduction of the legacy
     * AiBoostCore::resolveCanonical() fallback (`$uri->getScheme().'://'.
     * $uri->getHost().$uri->getPath()`) — derived by parsing the AppContext's
     * current URL so the resolver never touches Joomla's Uri directly.
     */
    private function bareCurrentUrl(): string
    {
        $url    = $this->ctx->getCurrentUrl();
        $scheme = (string) parse_url($url, PHP_URL_SCHEME);
        $host   = (string) parse_url($url, PHP_URL_HOST);
        $path   = (string) parse_url($url, PHP_URL_PATH);

        return $scheme . '://' . $host . $path;
    }

    /**
     * Apply the canonical URL map verbatim from the legacy
     * AiBoostCore::resolveCanonical(): decode the JSON map and prefix-match the
     * current request path (`ltrim(path,'/')`) against each `pattern`; the first
     * exact-or-prefix hit returns its mapped `target`. No map / invalid JSON /
     * no hit → the supplied bare-URL fallback. Path is read CMS-neutrally from the
     * AppContext URL (== the old `Uri::getInstance()->getPath()`).
     */
    private function applyCanonicalUrlMap(string $canonicalUrlMap, string $fallback): string
    {
        $mapJson = trim($canonicalUrlMap);
        if ($mapJson !== '') {
            $map = json_decode($mapJson, true);
            if (is_array($map)) {
                $currentPath = ltrim((string) parse_url($this->ctx->getCurrentUrl(), PHP_URL_PATH), '/');
                foreach ($map as $pattern => $target) {
                    $pattern = ltrim((string) $pattern, '/');
                    if ($currentPath === $pattern || strpos($currentPath, $pattern) === 0) {
                        return (string) $target;
                    }
                }
            }
        }

        return $fallback;
    }

    /**
     * Map the raw request primitives to a page type + CMS-neutral entity.
     *
     * @return array{0:PageType,1:string,2:int}  [type, entityKind, entityId]
     */
    private function classify(string $option, string $view, int $rawId, bool $isHomepage): array
    {
        // The ONE homepage truth wins first (active menu item home=1). A Featured-
        // or Single-Article-home page classifies as HOMEPAGE, not FEATURED/ARTICLE.
        if ($isHomepage) {
            return [PageType::HOMEPAGE, 'site', 0];
        }

        if ($option === 'com_content') {
            if ($view === 'article' && $rawId > 0) {
                return [PageType::ARTICLE, 'article', $rawId];
            }
            if ($view === 'category' || $view === 'categories') {
                return [PageType::CATEGORY, 'category', $rawId];
            }
            if ($view === 'featured') {
                return [PageType::FEATURED, '', 0];
            }
            return [PageType::COMPONENT_OTHER, '', 0];
        }

        if ($option === 'com_contact' && $view === 'contact') {
            return [PageType::CONTACT, 'contact', $rawId];
        }

        if ($option === 'com_tags') {
            return [PageType::TAG, 'tag', $rawId];
        }

        if ($option === 'com_finder' || $option === 'com_search' || $view === 'search') {
            return [PageType::SEARCH, '', 0];
        }

        if ($option !== '') {
            return [PageType::COMPONENT_OTHER, '', 0];
        }

        return [PageType::UNKNOWN, '', 0];
    }

    /**
     * The site default CONTENT language (Language Manager → Installed → Site →
     * Default), stored in the com_languages component params key `site`. This is
     * the SEO/x-default default and is DISTINCT from the global config default
     * (see memory joomla-site-default-vs-global-language). Read through the CMS
     * database adapter so it stays portable; falls back to the global default on
     * any error or when not set.
     */
    private function resolveSiteDefaultLanguage(string $fallback): string
    {
        try {
            $db    = $this->db->getConnection();
            $query = $db->getQuery(true)
                ->select($db->quoteName('params'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('com_languages'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
            $db->setQuery($query, 0, 1);
            $params = (string) ($db->loadResult() ?? '');
            if ($params === '') {
                return $fallback;
            }
            $decoded = json_decode($params, true);
            $site    = is_array($decoded) ? trim((string) ($decoded['site'] ?? '')) : '';

            return $site !== '' ? $site : $fallback;
        } catch (\Throwable $e) {
            return $fallback;
        }
    }
}
