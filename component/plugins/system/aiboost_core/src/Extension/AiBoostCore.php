<?php
/**
 * AI Boost — Core Plugin (namespace style)
 * Handles: canonical URL, title templates, redirect manager, 404 monitoring.
 *
 * @package     AiBoost\Plugin\System\AiBoostCore
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostCore\Extension;

defined('_JEXEC') or die;

use AiBoost\Lib\BodyBlockBuilder;
use AiBoost\Lib\Cms\AdapterRegistry;
use AiBoost\Lib\ConflictPolicy;
use AiBoost\Lib\HeadBlockBuilder;
use AiBoost\Version;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;

class AiBoostCore extends CMSPlugin
{
    protected $autoloadLanguage = true;

    /** Cached result of libReady() — null until first probed. */
    private ?bool $libReady = null;

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

    /**
     * Handle redirects early (301/302) before Joomla routes the request.
     */
    public function onAfterInitialise(): void
    {
        $app = Factory::getApplication();

        // Task #471 — wire up CMS adapter layer with the live CMSApplication
        // so lib services (Manifest/Registry triggerEvent, HeadBlockBuilder
        // body manipulation, etc.) route through the adapter interfaces
        // instead of reaching for Factory::*/JPATH_* directly. Idempotent.
        // libReady() guards the partial-lib state (base package removed or
        // half-removed); redirects + 404 logging below are lib-free and keep
        // working without it.
        if ($this->libReady()) {
            \AiBoost\Lib\Cms\AdapterBootstrap::registerJoomla($app);
        }

        // Task #440 — phone-home heartbeat (admin only, 7-day throttle).
        // Runs BEFORE the site-only guard. Fire-and-forget, never blocks.
        if ($app->isClient('administrator')) {
            try {
                $adminSettings = $this->getAiBoostSettings();
                if (!empty($adminSettings)
                    && \AiBoost\Lib\LicenseHeartbeat::shouldRun($adminSettings)
                ) {
                    \AiBoost\Lib\LicenseHeartbeat::execute($adminSettings);
                }

                // Task #567 — perpetual-activation safety net. A lapsed past
                // purchaser whose local licence markers were cleared slips past
                // the migration backfill; ask the update server (by install_id)
                // whether this install was ever bound to a real purchase and
                // re-activate Pro perpetually if so. Admin only, throttled,
                // fire-and-forget.
                if (!empty($adminSettings)
                    && \AiBoost\Lib\LicenseReconcile::shouldRun($adminSettings)
                ) {
                    \AiBoost\Lib\LicenseReconcile::execute($adminSettings);
                }
            } catch (\Throwable $e) {
                $debug = $adminSettings['debug_mode'] ?? false;
                if (!empty($debug)) {
                    error_log('[AI Boost: aiboost_core] heartbeat skipped — ' . $e->getMessage());
                }
            }
            return;
        }

        if (!$app->isClient('site')) {
            return;
        }
        $settings = $this->getAiBoostSettings();
        if (empty($settings)) {
            return;
        }
        if (!empty($settings['staging_mode'])) {
            error_log('[AI Boost: aiboost_core] STAGING MODE ON — canonical, title/description templates, and redirects are suppressed. Disable staging_mode in Debug tab to see output.');
            return;
        }
        if (!empty($settings['redirect_enabled'])) {
            $this->handleRedirects($settings);
        }
    }

    /**
     * Inject admin-side CSS overrides for com_content article edit so AI Boost
     * custom fields remain legible under dark templates / YooTheme overrides.
     * (v0.12.9 — fixes Bojan's "polja se ne vide u tamnoj temi" feedback.)
     */
    public function onBeforeRender(): void
    {
        $app = Factory::getApplication();
        if (!$app->isClient('administrator')) {
            return;
        }
        $input = $app->getInput();
        if ($input->get('option') !== 'com_content') {
            return;
        }
        $view = $input->get('view');
        if (!in_array($view, ['article', 'articles'], true)) {
            return;
        }
        $document = $app->getDocument();
        if (!$document || $document->getType() !== 'html') {
            return;
        }

        // High-specificity overrides so YooTheme / Widgetkit admin styles
        // can't make our labels and inputs invisible on dark backgrounds.
        // v0.12.10 — broadened selectors: matches Joomla 5/6 article edit AND
        // YOOtheme custom field renderers (which drop .control-group wrapper).
        $css = <<<CSS
html[data-bs-theme=dark] form#item-form label,
html[data-bs-theme=dark] form#item-form .control-label,
html[data-bs-theme=dark] form#item-form .form-label,
html[data-bs-theme=dark] form#item-form legend,
html[data-bs-theme=dark] form#item-form .tab-pane label,
html[data-bs-theme=dark] form#item-form .tab-content label,
html[data-bs-theme=dark] form#item-form fieldset label {
    color: #f1f3f5 !important;
}
html[data-bs-theme=dark] form#item-form input[type=text],
html[data-bs-theme=dark] form#item-form input[type=url],
html[data-bs-theme=dark] form#item-form input[type=email],
html[data-bs-theme=dark] form#item-form input[type=number],
html[data-bs-theme=dark] form#item-form input[type=search],
html[data-bs-theme=dark] form#item-form input:not([type]),
html[data-bs-theme=dark] form#item-form textarea,
html[data-bs-theme=dark] form#item-form select,
html[data-bs-theme=dark] form#item-form .form-control,
html[data-bs-theme=dark] form#item-form .form-select {
    background-color: #1e2125 !important;
    color: #f1f3f5 !important;
    border-color: #495057 !important;
}
html[data-bs-theme=dark] form#item-form .form-text,
html[data-bs-theme=dark] form#item-form .text-muted,
html[data-bs-theme=dark] form#item-form small,
html[data-bs-theme=dark] form#item-form .help-block {
    color: #adb5bd !important;
}
/* Tab panes background — some YOOtheme builds inject white panel bg */
html[data-bs-theme=dark] form#item-form .tab-content,
html[data-bs-theme=dark] form#item-form .tab-pane,
html[data-bs-theme=dark] form#item-form .card,
html[data-bs-theme=dark] form#item-form .card-body {
    background-color: transparent !important;
    color: #f1f3f5 !important;
}
CSS;
        $document->addStyleDeclaration($css);
    }

    /**
     * Inject canonical URL, apply title template.
     */
    public function onBeforeCompileHead(): void
    {
        if (!$this->libReady()) {
            return;
        }

        $app = Factory::getApplication();
        if (!$app->isClient('site')) {
            return;
        }
        $document = $app->getDocument();
        if (!$document || $document->getType() !== 'html') {
            return;
        }

        $settings = $this->getAiBoostSettings();
        if (empty($settings)) {
            return;
        }

        // Set hide-comments flag FIRST — before any early-return paths (#384).
        $hide = !empty($settings['hide_comments']);
        HeadBlockBuilder::setHideComments($hide);
        BodyBlockBuilder::setHideComments($hide);

        // Per-feature conflict policy — also before early returns, so the
        // finalize-time dedup (Deliverable B) honours it regardless of which path
        // this request takes. Each head section maps to one output feature; the
        // AEO meta section folds under 'schema' (no dedicated competitor). Body
        // analytics (GTM/Pixel noscript) tracks the analytics feature.
        HeadBlockBuilder::setSectionMode(HeadBlockBuilder::SECTION_SCHEMA, ConflictPolicy::legacyModeFor(ConflictPolicy::FEATURE_SCHEMA, $settings));
        HeadBlockBuilder::setSectionMode(HeadBlockBuilder::SECTION_SOCIAL, ConflictPolicy::legacyModeFor(ConflictPolicy::FEATURE_OG, $settings));
        HeadBlockBuilder::setSectionMode(HeadBlockBuilder::SECTION_AEO, ConflictPolicy::legacyModeFor(ConflictPolicy::FEATURE_SCHEMA, $settings));
        HeadBlockBuilder::setSectionMode(HeadBlockBuilder::SECTION_ANALYTICS, ConflictPolicy::legacyModeFor(ConflictPolicy::FEATURE_ANALYTICS, $settings));
        BodyBlockBuilder::setConflictMode(ConflictPolicy::legacyModeFor(ConflictPolicy::FEATURE_ANALYTICS, $settings));

        if (!empty($settings['staging_mode'])) {
            return;
        }

        if (!empty($settings['debug_mode'])) {
            error_log('[AI Boost: aiboost_core] onBeforeCompileHead — canonical + title template');
        }

        // Canonical URL — addHeadLink() writes to the document's link stream
        // (not addCustomTag), so it can't live inside the consolidated AI Boost
        // head block. Task #380: register the name with HeadBlockBuilder so the
        // outer block header lists it under "Also emitted via Joomla head".
        if (!empty($settings['enable_canonical'])
            && ConflictPolicy::shouldApplyExclusive(ConflictPolicy::FEATURE_CANONICAL, $settings)) {
            $canonical = $this->resolveCanonicalViaResolver($settings);
            if ($canonical) {
                $document->addHeadLink(htmlspecialchars($canonical), 'canonical');
                HeadBlockBuilder::noteNative('canonical');
            }
        }

        // Title + meta-description templates REWRITE the document's <title> /
        // meta description in place, so "defer" means: don't apply ours, leave
        // whatever Joomla / another SEO extension produced. Skipped only on an
        // explicit per-feature defer (Conflict Manager) — never silently on the
        // global cooperative default, so existing sites keep their templates.
        if (ConflictPolicy::shouldApplyExclusive(ConflictPolicy::FEATURE_TITLES, $settings)) {
            // setTitle() rewrites <title> in place — register with the builder
            // so it shows up in the consolidated header summary.
            $titleApplied = $this->applyTitleTemplate($document, $settings);
            if ($titleApplied) {
                HeadBlockBuilder::noteNative('title template');
            }

            // Meta Description Template — same caveat as title (setMetaData rewrites).
            $metaApplied = $this->applyMetaDescTemplate($document, $settings);
            if ($metaApplied) {
                HeadBlockBuilder::noteNative('meta description template');
            }
        }
    }

    /**
     * Idempotent finalize — see HeadBlockBuilder::finalize().
     */
    public function onAfterRender(): void
    {
        if (!$this->libReady()) {
            return;
        }

        $app = Factory::getApplication();

        // Optional performance probe (Task: perf/memory baseline). Gated on the
        // plugin's own debug_mode setting or Joomla's global Debug, so it is
        // silent for ordinary visitors. getAiBoostSettings() is request-cached,
        // so this read is free on the hot path. finalize() is the single
        // idempotent point where ALL accumulated head/body output is rendered
        // and spliced — bracketing it captures AI Boost's consolidation cost,
        // and memory_get_peak_usage() gives the request's peak for sizing the
        // documented PHP memory_limit floor.
        $perf = !empty($this->getAiBoostSettings()['debug_mode'])
            || (\defined('JDEBUG') && JDEBUG === true);
        $t0   = $perf ? microtime(true) : 0.0;

        HeadBlockBuilder::finalize($app, Version::VERSION);
        BodyBlockBuilder::finalize($app);

        if ($perf) {
            $finalizeMs = (microtime(true) - $t0) * 1000;
            $peakMb     = memory_get_peak_usage(true) / 1048576;
            $reqMs      = isset($_SERVER['REQUEST_TIME_FLOAT'])
                ? (microtime(true) - (float) $_SERVER['REQUEST_TIME_FLOAT']) * 1000
                : 0.0;
            $line = sprintf(
                'finalize=%.2fms peak=%.1fMB request=%.0fms v=%s',
                $finalizeMs,
                $peakMb,
                $reqMs,
                Version::VERSION
            );
            if ($app->isClient('site')) {
                try {
                    $app->setHeader('X-AiBoost-Perf', $line, true);
                } catch (\Throwable) {
                    // Headers already sent on some SAPIs — log only.
                }
            }
            error_log('[AI Boost perf] ' . $line);
        }
    }

    /*
     * Licence-gated auto-updates use Joomla's STANDARD Download Key mechanism
     * (manifest <dlid> + #__update_sites.extra_query), NOT a custom event. Joomla
     * itself appends the extra_query (dlid=<key>) to BOTH the update-XML fetch and
     * the package download, on every supported version (J5–J6+). The previous
     * onInstallerBeforeFetchManifest token-substitution hook was removed: that
     * event does NOT fire during the update-site fetch (verified live 2026-06-19 —
     * Joomla sent the literal {LICENSE_KEY}). The key is written into extra_query on
     * licence activation by SettingsController::verifyLicense() → fillUpdateDownloadKey().
     */

    /**
     * Log 404 errors via Joomla's error event.
     */
    public function onError(\Throwable $error): void
    {
        if ((int) $error->getCode() !== 404) {
            return;
        }
        $app = Factory::getApplication();
        if (!$app->isClient('site')) {
            return;
        }
        $settings = $this->getAiBoostSettings();
        if (empty($settings['redirect_404_log_enabled']) || !empty($settings['staging_mode'])) {
            return;
        }
        $this->log404Request();
    }

    /**
     * T1·S5 — canonical now lives in the shared PageResolver. Read the resolved
     * canonical from PageContext (the single source), threading in the raw
     * `canonical_url_map` setting so the resolver applies the URL map. The
     * resolver reproduces the legacy logic byte-for-byte (URL-map hit OR the bare
     * scheme://host/path). Falls back to the legacy private method below if the
     * Page classes are absent (partial uninstall) or resolve() throws — so the
     * absent-resolver path stays byte-identical to pre-S5.
     *
     * @param array<string,mixed> $settings
     */
    private function resolveCanonicalViaResolver(array $settings): string
    {
        if (class_exists('AiBoost\\Lib\\Page\\PageResolver')) {
            try {
                $map = $settings['canonical_url_map'] ?? null;
                return AdapterRegistry::pageResolver()
                    ->resolve(is_string($map) ? $map : null)
                    ->canonical;
            } catch (\Throwable $e) {
                // fall through to the legacy resolver
            }
        }

        return $this->resolveCanonical($settings);
    }

    private function resolveCanonical(array $settings): string
    {
        $mapJson = trim((string) ($settings['canonical_url_map'] ?? ''));
        if ($mapJson) {
            $map = json_decode($mapJson, true);
            if (is_array($map)) {
                $currentPath = ltrim(Uri::getInstance()->getPath(), '/');
                foreach ($map as $pattern => $target) {
                    $pattern = ltrim((string) $pattern, '/');
                    if ($currentPath === $pattern || strpos($currentPath, $pattern) === 0) {
                        return (string) $target;
                    }
                }
            }
        }
        $uri = Uri::getInstance();
        return $uri->getScheme() . '://' . $uri->getHost() . $uri->getPath();
    }

    /**
     * Detect the current page type for template selection.
     *
     * Returns one of: 'home' | 'article' | 'category' | 'search' | 'tag' | 'default'
     */
    private function detectPageType(): string
    {
        $app   = Factory::getApplication();
        $input = $app->getInput();
        $option = $input->get('option', '');
        $view   = $input->get('view', '');

        // Homepage
        $path = ltrim(Uri::getInstance()->getPath(), '/');
        if ($path === '' || $path === 'index.php') {
            return 'home';
        }
        if ($option === 'com_content' && $view === 'featured') {
            return 'home';
        }

        // Article
        if ($option === 'com_content' && $view === 'article') {
            return 'article';
        }

        // Category
        if ($option === 'com_content' && in_array($view, ['category', 'categories'], true)) {
            return 'category';
        }

        // Search
        if (in_array($option, ['com_search', 'com_finder'], true)) {
            return 'search';
        }

        // Tag
        if ($option === 'com_tags') {
            return 'tag';
        }

        return 'default';
    }

    /**
     * Resolve {category} token for article pages.
     */
    private function resolveCategoryToken(): string
    {
        try {
            $app   = Factory::getApplication();
            $input = $app->getInput();
            if ($input->get('option') === 'com_content' && $input->get('view') === 'article') {
                $articleId = (int) $input->get('id', 0);
                if ($articleId) {
                    $db  = Factory::getDbo();
                    $q   = $db->getQuery(true)
                        ->select('cat.title')
                        ->from($db->quoteName('#__content', 'c'))
                        ->join('LEFT', $db->quoteName('#__categories', 'cat') . ' ON cat.id = c.catid')
                        ->where($db->quoteName('c.id') . ' = ' . $articleId);
                    $db->setQuery($q);
                    return (string) ($db->loadResult() ?? '');
                }
            }
        } catch (\Throwable $e) {
        }
        return '';
    }

    /**
     * Apply title template — per-content-type with global fallback.
     *
     * Per-type settings: title_template_home, title_template_article,
     *                    title_template_category, title_template_search,
     *                    title_template_tag, title_template_default
     * Global fallback:   title_template (legacy)
     *
     * Tokens: {page_title}, {site_name}, {separator}, {category}, {year}
     *
     * Max-length guard: titles are truncated to 60 chars if title_template_maxlen is set.
     */
    private function applyTitleTemplate($document, array $settings): bool
    {
        $app       = Factory::getApplication();
        $pageTitle = $document->getTitle();
        $siteName  = $app->get('sitename', '');
        $separator = trim((string) ($settings['title_separator'] ?? ' | '));
        $year      = date('Y');

        $pageType = $this->detectPageType();

        // Per-type template lookup
        $typeKey  = 'title_template_' . $pageType;
        $template = trim((string) ($settings[$typeKey] ?? ''));

        // Fall back to global template
        if (!$template) {
            $template = trim((string) ($settings['title_template'] ?? ''));
        }

        if (!$template || strpos($template, '{') === false) {
            return false;
        }

        $category = $this->resolveCategoryToken();

        $newTitle = str_replace(
            ['{page_title}', '{site_name}', '{separator}', '{category}', '{year}'],
            [$pageTitle, $siteName, $separator, $category, $year],
            $template
        );

        // Clean up double separators when category is empty
        if ($category === '') {
            $sep      = preg_quote($separator, '/');
            $newTitle = preg_replace('/(' . $sep . '\s*){2,}/', $separator, $newTitle);
            $newTitle = trim($newTitle, trim($separator));
        }

        $newTitle = trim($newTitle);

        // Max-length guard
        $maxLen = (int) ($settings['title_template_maxlen'] ?? 0);
        if ($maxLen > 0 && mb_strlen($newTitle) > $maxLen) {
            $newTitle = mb_substr($newTitle, 0, $maxLen - 1) . '…';
        }

        if ($newTitle) {
            $document->setTitle($newTitle);
            return true;
        }
        return false;
    }

    /**
     * Apply meta description template.
     *
     * Tokens: {site_name}, {separator}, {year}, {description}
     * (description = existing Joomla meta description)
     *
     * Per-type settings: meta_desc_template_article, meta_desc_template_default
     * Global fallback:   meta_desc_template
     */
    private function applyMetaDescTemplate($document, array $settings): bool
    {
        $app      = Factory::getApplication();
        $pageType = $this->detectPageType();

        $typeKey  = 'meta_desc_template_' . $pageType;
        $template = trim((string) ($settings[$typeKey] ?? ''));

        if (!$template) {
            $template = trim((string) ($settings['meta_desc_template'] ?? ''));
        }

        if (!$template || strpos($template, '{') === false) {
            return false;
        }

        $siteName   = $app->get('sitename', '');
        $separator  = trim((string) ($settings['title_separator'] ?? ' | '));
        $year       = date('Y');
        $existingDesc = trim($document->getMetaData('description') ?? '');

        $newDesc = str_replace(
            ['{site_name}', '{separator}', '{year}', '{description}'],
            [$siteName, $separator, $year, $existingDesc],
            $template
        );
        $newDesc = trim($newDesc);

        // Max-length guard (160 chars is the SEO guideline)
        $maxLen = (int) ($settings['meta_desc_maxlen'] ?? 160);
        if ($maxLen > 0 && mb_strlen($newDesc) > $maxLen) {
            $newDesc = mb_substr($newDesc, 0, $maxLen - 1) . '…';
        }

        if ($newDesc) {
            $document->setMetaData('description', $newDesc);
            return true;
        }
        return false;
    }

    /**
     * Check #__aiboost_redirects for a matching rule and issue HTTP redirect.
     * Supports exact path match and wildcard (*) patterns.
     */
    private function handleRedirects(array $settings): void
    {
        try {
            $db     = Factory::getDbo();
            $prefix = $db->getPrefix();
            $tables = $db->setQuery('SHOW TABLES LIKE ' . $db->quote($prefix . 'aiboost_redirects'))->loadColumn();
            if (empty($tables)) {
                return;
            }

            $uri         = Uri::getInstance();
            $currentPath = '/' . ltrim($uri->getPath(), '/');
            $currentFull = $uri->getScheme() . '://' . $uri->getHost() . $currentPath;
            if ($uri->getQuery()) {
                $currentFull .= '?' . $uri->getQuery();
            }

            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'from_url', 'to_url', 'redirect_type']))
                ->from($db->quoteName('#__aiboost_redirects'))
                ->where($db->quoteName('enabled') . ' = 1')
                ->order($db->quoteName('id') . ' ASC');
            $db->setQuery($query);
            $rules = $db->loadObjectList();

            if (empty($rules)) {
                return;
            }

            foreach ($rules as $rule) {
                $from = trim((string) ($rule->from_url ?? ''));
                $to   = trim((string) ($rule->to_url   ?? ''));
                if (!$from || !$to) {
                    continue;
                }

                $matched = ($from === $currentPath || $from === $currentFull);

                // Wildcard support: /old/* → /new/
                if (!$matched && strpos($from, '*') !== false) {
                    $pattern = '#^' . str_replace('\*', '.*', preg_quote($from, '#')) . '$#';
                    if (preg_match($pattern, $currentPath)) {
                        $matched = true;
                    }
                }

                if ($matched) {
                    // Increment hit counter (best-effort)
                    try {
                        $db->setQuery(
                            'UPDATE ' . $db->quoteName('#__aiboost_redirects') .
                            ' SET hits = hits + 1' .
                            ' WHERE ' . $db->quoteName('id') . ' = ' . (int) $rule->id
                        )->execute();
                    } catch (\Throwable $e) {}

                    $code = in_array((int) $rule->redirect_type, [301, 302, 303, 307, 308], true)
                        ? (int) $rule->redirect_type : 301;

                    header('Location: ' . $to, true, $code);
                    exit;
                }
            }
        } catch (\Throwable $e) {
            if (!empty($settings['debug_mode'])) {
                error_log('[AI Boost: aiboost_core] Redirect check error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Log a 404 request to #__aiboost_404_log (upsert — hits++ for duplicates).
     */
    private function log404Request(): void
    {
        try {
            $db     = Factory::getDbo();
            $prefix = $db->getPrefix();
            $tables = $db->setQuery('SHOW TABLES LIKE ' . $db->quote($prefix . 'aiboost_404_log'))->loadColumn();
            if (empty($tables)) {
                return;
            }

            $requestUrl = substr($_SERVER['REQUEST_URI']    ?? '', 0, 2000);
            $referrer   = substr($_SERVER['HTTP_REFERER']   ?? '', 0, 2000);
            $userAgent  = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
            $now        = Factory::getDate()->toSql();

            $existId = (int) $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('id'))
                    ->from($db->quoteName('#__aiboost_404_log'))
                    ->where($db->quoteName('request_url') . ' = ' . $db->quote($requestUrl))
            )->loadResult();

            if ($existId) {
                $db->setQuery(
                    'UPDATE ' . $db->quoteName('#__aiboost_404_log') .
                    ' SET hits = hits + 1, last_seen = ' . $db->quote($now) .
                    ' WHERE id = ' . $existId
                )->execute();
            } else {
                $db->setQuery(
                    $db->getQuery(true)
                        ->insert($db->quoteName('#__aiboost_404_log'))
                        ->columns($db->quoteName(['request_url', 'referrer', 'user_agent', 'hits', 'first_seen', 'last_seen']))
                        ->values(
                            $db->quote($requestUrl) . ',' .
                            $db->quote($referrer) . ',' .
                            $db->quote($userAgent) . ',' .
                            '1,' .
                            $db->quote($now) . ',' .
                            $db->quote($now)
                        )
                )->execute();
            }
        } catch (\Throwable $e) {
            // 404 logging must never crash the page
        }
    }

    /**
     * Whether the shared AiBoost\Lib library is fully loadable.
     *
     * The plugin entry file only checks that lib/autoload.php exists — not
     * enough: a partial base-package uninstall can leave autoload.php on disk
     * while individual lib/src class files are gone, and the first lib
     * reference then fatals on every page. Probing two core lib classes
     * detects that state so every lib-touching event handler can no-op
     * instead. This is a tripwire, not an exhaustive integrity check. The
     * try/catch matters: under JDEBUG Joomla's debug class loader THROWS on
     * a missing class file instead of returning false.
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
