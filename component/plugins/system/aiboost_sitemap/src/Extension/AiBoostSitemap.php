<?php
/**
 * AI Boost — XML Sitemap Plugin
 *
 * Serves dynamic XML sitemaps on every request — no static file needed.
 *
 * Free tier:
 *   /sitemap.xml  — articles, menu items, categories
 *
 * Pro tier:
 *   Image sitemap entries (<image:image>)
 *   Hreflang alternates (<xhtml:link rel="alternate">)
 *   Sitemap index + chunks (/sitemap-{n}.xml) when URL count > limit
 *   News sitemap (/sitemap-news.xml)
 *   Auto-ping Google + Bing when an article is published
 *
 * Extension-layer responsibilities (CMS-specific, allowed here):
 *   - Factory::getDbo()         — DB connection resolved and injected into services
 *   - Factory::getApplication() — used for default language + app close
 *   - Uri::root() / Uri::base() — used for URL building in this class
 *
 * @package     AiBoost\Plugin\System\AiBoostSitemap
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostSitemap\Extension;

defined('_JEXEC') or die;

use AiBoost\Lib\ConflictPolicy;
use AiBoost\Plugin\System\AiBoostSitemap\Service\HreflangSitemapExtension;
use AiBoost\Plugin\System\AiBoostSitemap\Service\ImageSitemapExtension;
use AiBoost\Plugin\System\AiBoostSitemap\Service\NewsSitemapGenerator;
use AiBoost\Plugin\System\AiBoostSitemap\Service\SearchEnginePingService;
use AiBoost\Plugin\System\AiBoostSitemap\Service\SitemapGenerator;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;

class AiBoostSitemap extends CMSPlugin
{
    protected $autoloadLanguage = true;

    // ─────────────────────────────────────────────────────────────────────────
    // URI path patterns we intercept
    // ─────────────────────────────────────────────────────────────────────────

    private const PATH_SITEMAP       = 'sitemap.xml';
    private const PATH_SITEMAP_INDEX = 'sitemap-index.xml';
    private const PATH_SITEMAP_NEWS  = 'sitemap-news.xml';
    private const CHUNK_PATTERN      = '/^sitemap-(\d+)\.xml$/';

    /** Cached result of libReady() — null until first probed. */
    private ?bool $libReady = null;

    /**
     * Variadic forwarding keeps us compatible with every CMSPlugin constructor
     * signature across Joomla 4/5/6.
     *
     * The body suppresses PHP error *display* as early as the plugin-import
     * phase — but only for sitemap requests. This is essential because other
     * system plugins (notably Falang's falangdriver, which is incompatible with
     * PHP 8.5) can emit deprecation/notice HTML from their own onAfterInitialise
     * handler, which may run *before* ours. Once that output is flushed it can
     * no longer be removed from a later event, so the buffer cleanup in
     * beginCleanResponse()/sendXml() cannot catch it. Disabling display_errors
     * here — scoped strictly to sitemap URLs we fully own and terminate — keeps
     * the XML document pristine without affecting error reporting anywhere else.
     */
    public function __construct(...$args)
    {
        parent::__construct(...$args);

        if ($this->isSitemapRequest()) {
            @ini_set('display_errors', '0');
        }
    }

    /**
     * Loose, routing-independent check for a sitemap URL, safe to call during
     * construction (before Joomla has booted the router). Matches the same set
     * of paths as onAfterInitialise(), ignoring any sub-directory base path.
     */
    private function isSitemapRequest(): bool
    {
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $file = basename($path);

        return $file === self::PATH_SITEMAP
            || $file === self::PATH_SITEMAP_INDEX
            || $file === self::PATH_SITEMAP_NEWS
            || (bool) preg_match(self::CHUNK_PATTERN, $file);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Joomla events
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * onAfterInitialise — serve sitemap XML before Joomla normal routing takes over.
     */
    public function onAfterInitialise(): void
    {
        $settings = $this->getAiBoostSettings();

        if (!(int)($settings['enable_sitemap'] ?? 1)) {
            return;
        }

        // Defer to an existing sitemap extension (OSMap / Xmap / …) only on an
        // explicit per-feature defer (Conflict Manager) — never silently on the
        // global cooperative default. When deferred we don't claim our
        // /sitemap.xml route, leaving the other extension to serve it.
        if (!ConflictPolicy::shouldApplyExclusive(ConflictPolicy::FEATURE_SITEMAP, $settings)) {
            return;
        }

        $uri  = $_SERVER['REQUEST_URI'] ?? '';
        $path = ltrim((string) parse_url($uri, PHP_URL_PATH), '/');

        $basePath = ltrim((string) Uri::base(true), '/');
        if ($basePath !== '' && str_starts_with($path, $basePath . '/')) {
            $path = substr($path, strlen($basePath) + 1);
        }

        $isChunk = (bool) preg_match(self::CHUNK_PATTERN, $path, $m);

        if (!\in_array($path, [self::PATH_SITEMAP, self::PATH_SITEMAP_INDEX, self::PATH_SITEMAP_NEWS], true)
            && !$isChunk) {
            return;
        }

        // From here on we are committed to emitting an XML document, so harden
        // the response against stray output before we build any URLs.
        $this->beginCleanResponse();

        if ($path === self::PATH_SITEMAP) {
            $this->handleSitemapXml();
            return;
        }

        if ($path === self::PATH_SITEMAP_INDEX) {
            $this->handleSitemapIndex();
            return;
        }

        if ($path === self::PATH_SITEMAP_NEWS) {
            $this->handleNewsSitemap();
            return;
        }

        if ($isChunk) {
            $this->handleSitemapChunk((int) $m[1]);
            return;
        }
    }

    /**
     * Prepare a pristine response buffer for sitemap output.
     *
     * Building SEF URLs via Route::_() runs the Joomla router, which invokes
     * third-party route drivers (notably Falang's falangdriver). On PHP 8.5
     * those drivers can emit deprecation notices (e.g. ReflectionProperty::
     * setAccessible()) that would otherwise be printed straight into the
     * response and corrupt the XML — breaking parsers and the admin Live
     * Preview ("Unexpected token '<'"). We disable error display for this
     * request and open a dedicated output buffer; sendXml()/send404() then
     * discard whatever landed in it before writing the clean document.
     */
    private function beginCleanResponse(): void
    {
        @ini_set('display_errors', '0');
        ob_start();
    }

    /**
     * onExtensionAfterSave — trigger ping when admin saves settings.
     */
    public function onExtensionAfterSave(string $context, $table, bool $isNew): void
    {
        if ($context !== 'com_plugins.plugin') {
            return;
        }
        $element = $table->element ?? '';
        $folder  = $table->folder  ?? '';
        if ($folder !== 'system' || $element !== $this->_name) {
            return;
        }

        $settings   = $this->getAiBoostSettings();
        $sitemapUrl = rtrim((string) Uri::root(), '/') . '/sitemap.xml';

        $svc = new SearchEnginePingService();
        if ((int)($settings['ping_google'] ?? 1)) {
            $svc->pingGoogle($sitemapUrl);
        }
        if ((int)($settings['ping_bing'] ?? 1)) {
            $svc->pingBing($sitemapUrl);
        }
    }

    /**
     * onContentAfterSave — auto-ping search engines when an article is saved as published (Pro).
     */
    public function onContentAfterSave(string $context, object $article, bool $isNew): void
    {
        if ($context !== 'com_content.article') {
            return;
        }
        if ((int) ($article->state ?? 0) !== 1) {
            return;
        }
        $this->maybePing();
    }

    /**
     * onContentChangeState — auto-ping when article state changes to published (Pro).
     */
    public function onContentChangeState(string $context, array $pks, int $value): void
    {
        if ($context !== 'com_content.article' || $value !== 1) {
            return;
        }
        $this->maybePing();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Sitemap handlers
    // ─────────────────────────────────────────────────────────────────────────

    private function handleSitemapXml(): void
    {
        try {
            $settings  = $this->getAiBoostSettings();
            $isPro     = $this->isPro($settings);
            $generator = $this->makeGenerator($isPro, $settings);
            $entries   = $generator->generate();

            // Task #486 — let integration bridges (Falang, K2, ZOO …) filter
            // the URL set before serialization. Bridges can add multilingual
            // alternates, drop hidden items, or rewrite loc/lastmod.
            if (class_exists(\AiBoost\Lib\Integration\FilterDispatcher::class)) {
                $filtered = \AiBoost\Lib\Integration\FilterDispatcher::dispatch(
                    \AiBoost\Lib\Integration\Sdk::EVENT_FILTER_SITEMAP_URL_SET,
                    ['entries' => $entries, 'isPro' => $isPro]
                );
                if (isset($filtered['entries']) && is_array($filtered['entries'])) {
                    $entries = $filtered['entries'];
                }
            }

            $limit  = $isPro ? max(100, (int)($settings['sitemap_limit'] ?? 1000)) : 1000;
            $useIdx = $isPro && (int)($settings['enable_sitemap_index'] ?? 0) && count($entries) > $limit;

            if ($useIdx) {
                header('HTTP/1.1 301 Moved Permanently');
                header('Location: ' . $this->getBaseUrl() . '/sitemap-index.xml');
                Factory::getApplication()->close();
                return;
            }

            $xml = $this->buildUrlset($entries, $isPro, $settings);
            $this->sendXml($xml);
        } catch (\Throwable $e) {
            error_log('[AI Boost Sitemap] handleSitemapXml error: ' . $e->getMessage());
        }
    }

    private function handleSitemapIndex(): void
    {
        $settings = $this->getAiBoostSettings();
        if (!$this->isPro($settings)) {
            $this->send404();
            return;
        }

        try {
            $isPro     = true;
            $generator = $this->makeGenerator($isPro, $settings);
            $entries   = $generator->generate();

            $limit   = max(100, (int)($settings['sitemap_limit'] ?? 1000));
            $chunks  = (int) ceil(count($entries) / $limit);
            $baseUrl = $this->getBaseUrl();

            $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

            for ($i = 1; $i <= max(1, $chunks); $i++) {
                $xml .= "  <sitemap>\n";
                $xml .= '    <loc>' . htmlspecialchars($baseUrl . '/sitemap-' . $i . '.xml') . "</loc>\n";
                $xml .= '    <lastmod>' . date('Y-m-d') . "</lastmod>\n";
                $xml .= "  </sitemap>\n";
            }

            if ((int)($settings['enable_news_sitemap'] ?? 0)) {
                $xml .= "  <sitemap>\n";
                $xml .= '    <loc>' . htmlspecialchars($baseUrl . '/sitemap-news.xml') . "</loc>\n";
                $xml .= '    <lastmod>' . date('Y-m-d') . "</lastmod>\n";
                $xml .= "  </sitemap>\n";
            }

            $xml .= '</sitemapindex>';

            $this->sendXml($xml);
        } catch (\Throwable $e) {
            error_log('[AI Boost Sitemap] handleSitemapIndex error: ' . $e->getMessage());
        }
    }

    private function handleSitemapChunk(int $page): void
    {
        $settings = $this->getAiBoostSettings();
        if (!$this->isPro($settings)) {
            $this->send404();
            return;
        }

        try {
            $isPro     = true;
            $generator = $this->makeGenerator($isPro, $settings);
            $entries   = $generator->generate();

            $limit  = max(100, (int)($settings['sitemap_limit'] ?? 1000));
            $offset = ($page - 1) * $limit;
            $chunk  = array_slice($entries, $offset, $limit);

            if (empty($chunk)) {
                $this->send404();
                return;
            }

            $xml = $this->buildUrlset($chunk, $isPro, $settings);
            $this->sendXml($xml);
        } catch (\Throwable $e) {
            error_log('[AI Boost Sitemap] handleSitemapChunk error: ' . $e->getMessage());
        }
    }

    private function handleNewsSitemap(): void
    {
        $settings = $this->getAiBoostSettings();
        if (!$this->isPro($settings) || !(int)($settings['enable_news_sitemap'] ?? 0)) {
            $this->send404();
            return;
        }

        try {
            $db  = Factory::getDbo();
            $gen = new NewsSitemapGenerator(
                $this->getBaseUrl(),
                (int)($settings['news_category_id'] ?? 0),
                (string)($settings['news_publication_name'] ?? ''),
                $db,
            );

            $xml = $gen->generate();
            $this->sendXml($xml);
        } catch (\Throwable $e) {
            error_log('[AI Boost Sitemap] handleNewsSitemap error: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // XML builders
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build a <urlset> document from an array of sitemap entries.
     *
     * @param  array<int,array<string,mixed>> $entries
     * @param  array<string,mixed>            $settings
     */
    private function buildUrlset(array $entries, bool $isPro, array $settings): string
    {
        $withImages   = $isPro && (int)($settings['enable_image_sitemap'] ?? 0);
        // D1 (Multilang Pro): ALL sitemap hreflang — native #__associations AND
        // Falang — moves behind the Multilang licence. Both HreflangSitemapExtension
        // strategies are built only when $withHreflang is true, so this single
        // gate re-tiers every sitemap alternate to int_falang.
        $withHreflang = $isPro
            && (int) ($settings['enable_hreflang'] ?? 0)
            && \AiBoost\Lib\PluginRegistry::hasPro('int_falang');

        $ns = 'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        if ($withImages) {
            $ns .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';
        }
        if ($withHreflang) {
            $ns .= ' xmlns:xhtml="http://www.w3.org/1999/xhtml"';
        }

        $baseUrl = $this->getBaseUrl();
        $imgExt  = $withImages   ? new ImageSitemapExtension($baseUrl) : null;
        $hrefExt = null;

        if ($withHreflang) {
            try {
                Factory::getApplication()->triggerEvent('onAiBoostBeforeSitemapBuild');
            } catch (\Throwable) { /* integration bridge hook is best-effort */ }

            $db          = Factory::getDbo();
            $defaultLang = (string) Factory::getApplication()->get('language', 'en-GB');
            $hrefExt     = new HreflangSitemapExtension($baseUrl, $db, $defaultLang);
        }

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset ' . $ns . '>' . "\n";

        foreach ($entries as $entry) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'        . htmlspecialchars($entry['loc'])                   . "</loc>\n";
            $xml .= '    <lastmod>'    . htmlspecialchars($entry['lastmod'])               . "</lastmod>\n";
            $xml .= '    <changefreq>' . htmlspecialchars($entry['changefreq'])            . "</changefreq>\n";
            $xml .= '    <priority>'   . htmlspecialchars((string) $entry['priority'])    . "</priority>\n";

            if ($imgExt !== null && !empty($entry['intro_image'])) {
                $xml .= $imgExt->render(
                    (string) $entry['intro_image'],
                    (string) $entry['title']
                );
            }

            if ($hrefExt !== null && !empty($entry['id'])) {
                if ($entry['type'] === 'article') {
                    $xml .= $hrefExt->renderForArticle(
                        (int) $entry['id'],
                        (string) $entry['language'],
                        (string) ($entry['loc'] ?? '')
                    );
                } elseif ($entry['type'] === 'menu') {
                    $xml .= $hrefExt->renderForMenu((int) $entry['id'], (string) ($entry['loc'] ?? ''));
                }
            }

            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param array<string,mixed> $settings
     */
    private function makeGenerator(bool $isPro, array $settings): SitemapGenerator
    {
        $excludeMenuIds = $this->parseIdList((string)($settings['exclude_menu_ids'] ?? ''));
        $excludeCatIds  = $this->parseIdList((string)($settings['exclude_category_ids'] ?? ''));

        $db = Factory::getDbo();

        return new SitemapGenerator(
            baseUrl:            $this->getBaseUrl(),
            db:                 $db,
            includeArticles:    (bool)(int)($settings['include_articles']   ?? 1),
            includeMenuItems:   (bool)(int)($settings['include_menu_items'] ?? 1),
            includeCategories:  (bool)(int)($settings['include_categories'] ?? 0),
            defaultChangefreq:  (string)($settings['default_changefreq']    ?? 'weekly'),
            defaultPriority:    (string)($settings['default_priority']      ?? '0.8'),
            priorityHomepage:   $isPro ? (string)($settings['priority_homepage']   ?? '1.0') : '1.0',
            priorityArticles:   $isPro ? (string)($settings['priority_articles']   ?? '0.8') : '0.8',
            priorityCategories: $isPro ? (string)($settings['priority_categories'] ?? '0.6') : '0.6',
            priorityTags:       $isPro ? (string)($settings['priority_tags']       ?? '0.4') : '0.4',
            includeTags:        $isPro && (bool)(int)($settings['include_tags']    ?? 0),
            excludeMenuIds:     $isPro ? $excludeMenuIds : [],
            excludeCatIds:      $isPro ? $excludeCatIds  : [],
            guestViewLevels:    $this->resolveGuestViewLevels(),
        );
    }

    /**
     * Resolve the view access levels an anonymous (guest) visitor is allowed
     * to see. CMS-specific, so it belongs in the extension layer and is
     * injected into the service. Falls back to the Public level (1) if
     * resolution fails, so restricted content is never accidentally exposed.
     *
     * @return int[]
     */
    private function resolveGuestViewLevels(): array
    {
        try {
            $levels = array_values(array_filter(array_map('intval', Access::getAuthorisedViewLevels(0))));
            if (!empty($levels)) {
                return $levels;
            }
        } catch (\Throwable $e) {
            // Fall through to the safe default below.
        }

        return [1];
    }

    private function parseIdList(string $raw): array
    {
        $raw   = str_replace(["\n", "\r"], ',', $raw);
        $parts = array_map('trim', explode(',', $raw));
        return array_values(array_filter(array_map('intval', $parts)));
    }

    private function maybePing(): void
    {
        $settings = $this->getAiBoostSettings();
        if (!$this->isPro($settings)) {
            return;
        }
        if (!(int)($settings['ping_on_publish'] ?? 0)) {
            return;
        }

        $sitemapUrl = $this->getBaseUrl() . '/sitemap.xml';

        $svc = new SearchEnginePingService();
        if ((int)($settings['ping_google'] ?? 1)) {
            $svc->pingGoogle($sitemapUrl);
        }
        if ((int)($settings['ping_bing'] ?? 1)) {
            $svc->pingBing($sitemapUrl);
        }
    }

    private function getBaseUrl(): string
    {
        return rtrim((string) Uri::root(), '/');
    }

    private function sendXml(string $xml): void
    {
        $this->discardStrayOutput();
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        header('X-Robots-Tag: noindex');
        echo $xml;
        Factory::getApplication()->close();
    }

    private function send404(): void
    {
        $this->discardStrayOutput();
        header('HTTP/1.1 404 Not Found');
        header('Content-Type: text/plain; charset=utf-8');
        echo '404 Not Found';
        Factory::getApplication()->close();
    }

    /**
     * Drop anything already buffered before we emit the sitemap.
     *
     * On multilingual installs a third-party extension or bridge (e.g. Falang)
     * can emit a PHP notice/warning during the request lifecycle. Without this,
     * that HTML (`<br /> <b>…`) is prepended to the response and corrupts the
     * XML body, breaking parsers and the admin Live Preview. Discarding the
     * buffer guarantees the document starts with the XML declaration.
     */
    private function discardStrayOutput(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Settings helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Load all AI Boost settings from #__aiboost_settings (cached per request).
     *
     * @return array<string,mixed>
     */
    private function getAiBoostSettings(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName('settings_json'))
                ->from($db->quoteName('#__aiboost_settings'))
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'));
            $db->setQuery($query);
            $json  = $db->loadResult();
            $cache = $json ? (json_decode($json, true) ?? []) : [];
        } catch (\Throwable $e) {
            $cache = [];
        }
        return $cache;
    }

    private function isPro(array $settings): bool
    {
        // Canonical perpetual-activation gate (matches admin bootstrap + hasPro()).
        // Previously gated on `license_tier` alone (DRIFT): that emitted Pro
        // sitemap artifacts in the lapsed-license window. isProActive() resolves
        // purely from the perpetual `pro_activated` flag.
        // libReady() (not a bare class_exists) so a partially removed lib —
        // or JDEBUG's throwing class loader — can never fatal this check.
        if ($this->libReady()) {
            return \AiBoost\Lib\PluginRegistry::isProActive($settings);
        }
        // Fail-closed fallback if the lib is somehow unavailable.
        return false;
    }

    /**
     * Whether the shared AiBoost\Lib library is fully loadable.
     *
     * The plugin entry file only checks that lib/autoload.php exists — not
     * enough: a partial base-package uninstall can leave autoload.php on disk
     * while individual lib/src class files are gone, and the first lib
     * reference then fatals on every page. Probing two core lib classes
     * detects that state so every lib-touching code path can no-op instead.
     * This is a tripwire, not an exhaustive integrity check. The try/catch
     * matters: under JDEBUG Joomla's debug class loader THROWS on a missing
     * class file instead of returning false.
     */
    private function libReady(): bool
    {
        if ($this->libReady !== null) {
            return $this->libReady;
        }
        try {
            $this->libReady = class_exists('AiBoost\\Lib\\PluginRegistry')
                && class_exists('AiBoost\\Lib\\Logger');
        } catch (\Throwable $e) {
            $this->libReady = false;
        }
        return $this->libReady;
    }
}
