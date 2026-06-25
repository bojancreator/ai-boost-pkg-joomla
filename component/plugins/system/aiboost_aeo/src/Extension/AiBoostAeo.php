<?php
/**
 * AI Boost — AEO / AI Signals Plugin (Free)
 *
 * Free-tier orchestrator. Serves /llms.txt and /robots.txt, emits AI Signals,
 * serves Markdown pages, and finalises the shared head + body blocks. The
 * remaining Pro-only features live in aiboost_aeo_pro:
 *
 *   - /llms-full.txt + /llms-{sef}.txt routing → AiBoostAeoPro::onAfterInitialise
 *   - IndexNow key file + auto-submit         → AiBoostAeoPro
 *   - X-Robots-Tag header                      → AiBoostAeoPro::onBeforeCompileHead
 *   - Per-bot crawler rules                   → AiBoostAeoPro listener for
 *                                                EVENT_FILTER_ROBOTS_RULES
 *   - Per-language llms.txt translations +    → AiBoostAeoPro listener for
 *     "Full Index" reference                    EVENT_FILTER_LLMS_TXT
 *
 * Markdown page serving + AI Signals are Free features (Korak 3.2 #3).
 *
 * @package     AiBoost\Plugin\System\AiBoostAeo
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostAeo\Extension;

defined('_JEXEC') or die;

use AiBoost\Lib\BodyBlockBuilder;
use AiBoost\Lib\DocumentInspector;
use AiBoost\Lib\HeadBlockBuilder;
use AiBoost\Lib\Integration\FilterDispatcher;
use AiBoost\Lib\Integration\Sdk;
use AiBoost\Lib\JoomlaAppContext;
use AiBoost\Lib\MarkdownConverterService;
use AiBoost\Lib\PluginRegistry;
use AiBoost\Lib\TranslationService;
use AiBoost\Plugin\System\AiBoostAeo\Service\IndexNowService;
use AiBoost\Plugin\System\AiBoostAeo\Service\LlmsTxtGenerator;
use AiBoost\Plugin\System\AiBoostAeo\Service\LlmsTxtProGenerator;
use AiBoost\Version;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;

class AiBoostAeo extends CMSPlugin
{
    protected $autoloadLanguage = true;

    /** Set in onAfterInitialise when the request should be served as Markdown. */
    private bool $isMarkdownRequest = false;

    /** Cached result of libReady() — null until first probed. */
    private ?bool $libReady = null;

    /**
     * onAfterInitialise — Free-tier virtual file routing + Markdown detection.
     *
     * Serves /llms.txt; every other path is a candidate for Markdown page
     * serving. robots.txt is a PHYSICAL file on disk, never served virtually.
     * Pro routes (/llms-full.txt, /llms-{sef}.txt, /{indexnow_key}.txt) are
     * handled by aiboost_aeo_pro on the same event.
     */
    public function onAfterInitialise(): void
    {
        if (!$this->libReady()) {
            return;
        }

        $uri  = $_SERVER['REQUEST_URI'] ?? '';
        $path = ltrim((string) parse_url($uri, PHP_URL_PATH), '/');

        $settings = $this->getAiBoostSettings();

        // Pro virtual-file routes (relocated from aiboost_aeo_pro):
        // /llms-full.txt, /llms-{sef}.txt, /{indexnow_key}.txt. LlmsTxtProGenerator
        // ships ONLY in the Pro build (FREE_EXCLUDE) → class_exists() is false on
        // Free. Runs BEFORE the Markdown fall-through so a Pro virtual path is
        // never misread as a Markdown candidate.
        if ($path !== '' && class_exists(LlmsTxtProGenerator::class) && PluginRegistry::isProActive($settings)) {
            try {
                $indexNowOn = (int) ($settings['indexnow_enabled'] ?? 0);
                $apiKey     = trim((string) ($settings['indexnow_api_key'] ?? ''));
                $isLlmsFull = ($path === 'llms-full.txt');
                $isKeyFile  = ($indexNowOn && $apiKey !== '' && $path === $apiKey . '.txt');
                $langSef    = null;
                $isLlmsLang = false;
                if (!$isLlmsFull && !$isKeyFile && preg_match('/^llms-([a-z]{2,5})\.txt$/i', $path, $m)) {
                    $langSef    = strtolower($m[1]);
                    $isLlmsLang = true;
                }

                if ($isLlmsFull || $isKeyFile || $isLlmsLang) {
                    $defaultLang = (string) Factory::getApplication()->get('language', 'en-GB');

                    if ($isLlmsLang && (int) ($settings['llmstxt_enabled'] ?? 1)) {
                        $db = Factory::getDbo();
                        try {
                            $q = $db->getQuery(true)
                                ->select($db->quoteName('lang_code'))
                                ->from($db->quoteName('#__languages'))
                                ->where($db->quoteName('sef') . ' = ' . $db->quote($langSef))
                                ->where($db->quoteName('published') . ' = 1');
                            $langCode = (string) ($db->setQuery($q)->loadResult() ?? '');
                        } catch (\Throwable $e) {
                            $langCode = '';
                        }
                        if ($langCode !== '' && $langCode !== $defaultLang) {
                            $ctx = new JoomlaAppContext();
                            $gen = new LlmsTxtProGenerator($settings, $ctx, $db, new TranslationService($db, $defaultLang), $langCode);
                            header('Content-Type: text/plain; charset=utf-8');
                            header('Cache-Control: public, max-age=86400');
                            echo $gen->generate();
                            Factory::getApplication()->close();
                            return;
                        }
                    }

                    if ($isLlmsFull && (int) ($settings['llms_full_txt_enabled'] ?? 0)) {
                        $ctx = new JoomlaAppContext();
                        $db  = Factory::getDbo();
                        $gen = new LlmsTxtProGenerator($settings, $ctx, $db, new TranslationService($db, $defaultLang));
                        header('Content-Type: text/plain; charset=utf-8');
                        header('Cache-Control: public, max-age=3600');
                        echo $gen->generateFull();
                        Factory::getApplication()->close();
                        return;
                    }

                    if ($isKeyFile) {
                        header('Content-Type: text/plain; charset=utf-8');
                        header('Cache-Control: public, max-age=86400');
                        echo $apiKey;
                        Factory::getApplication()->close();
                        return;
                    }

                    // A matched Pro virtual path whose feature is OFF must 404
                    // cleanly — never fall through to the Markdown detector below.
                    return;
                }
            } catch (\Throwable $e) {
                // Never break routing — fall through to Free behaviour.
            }
        }

        if ($path !== 'llms.txt') {
            // Any non-llms.txt path (including the root URL) may be a Markdown
            // request — detect .md suffix / ?markdown=1 / Accept: text/markdown.
            $this->detectMarkdownRequest($path, $settings);
            return;
        }

        $ctx = new JoomlaAppContext();
        $db  = Factory::getDbo();

        if ((int) ($settings['llmstxt_enabled'] ?? 1)) {
            $text = (new LlmsTxtGenerator($settings, $ctx, $db))->generate();

            // Pro: rebuild with per-language translations + the "Full Index"
            // reference (relocated from aiboost_aeo_pro). LlmsTxtProGenerator
            // ships only in the Pro build (FREE_EXCLUDE). Applied BEFORE the
            // dispatch below so third-party EVENT_FILTER_LLMS_TXT bridges still
            // get the last word over the Pro output.
            if (class_exists(LlmsTxtProGenerator::class) && PluginRegistry::isProActive($settings)) {
                try {
                    $defaultLang = (string) Factory::getApplication()->get('language', 'en-GB');
                    $text = (new LlmsTxtProGenerator($settings, $ctx, $db, new TranslationService($db, $defaultLang)))->generate();
                } catch (\Throwable $e) {
                    // keep the Free baseline $text
                }
            }

            // Bridge hook — third-party extensions may decorate /llms.txt.
            if (class_exists(FilterDispatcher::class)) {
                $filtered = FilterDispatcher::dispatch(
                    Sdk::EVENT_FILTER_LLMS_TXT,
                    [
                        'text'     => $text,
                        'settings' => $settings,
                        'kind'     => 'llms.txt',
                        'langCode' => '',
                    ]
                );
                if (isset($filtered['text']) && is_string($filtered['text']) && $filtered['text'] !== '') {
                    $text = $filtered['text'];
                }
            }

            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: public, max-age=86400');
            echo $text;
            Factory::getApplication()->close();
            return;
        }
    }

    /**
     * onBeforeCompileHead — share `hide_comments`, emit AI Signals + the
     * Markdown discovery <link> (all Free, Korak 3.2). X-Robots-Tag stays Pro.
     */
    public function onBeforeCompileHead(): void
    {
        if (!$this->libReady()) {
            return;
        }

        $settings = $this->getAiBoostSettings();
        $hide     = !empty($settings['hide_comments']);
        HeadBlockBuilder::setHideComments($hide);
        BodyBlockBuilder::setHideComments($hide);

        $app = Factory::getApplication();
        if (!$app->isClient('site')) {
            return;
        }

        // Pro: X-Robots-Tag header (relocated from aiboost_aeo_pro). isProActive
        // is the single source of truth (emits only a header, no relocated class).
        if (PluginRegistry::isProActive($settings) && (int) ($settings['enable_x_robots_header'] ?? 0)) {
            header('X-Robots-Tag: index, follow');
        }

        // AI Signals (Free) — lightweight hints that tell AI engines the page
        // is AI-optimised and point them at /llms.txt.
        if ((int) ($settings['aeo_ai_meta_enabled'] ?? 0)) {
            $doc = $app->getDocument();
            if (DocumentInspector::shouldSkip($doc, DocumentInspector::SIG_AI_META_VERIFIED, $settings)) {
                HeadBlockBuilder::noteSkip(
                    HeadBlockBuilder::SECTION_AEO,
                    'AI meta tags already emitted by another extension'
                );
            } else {
                $baseUrl = rtrim(Uri::root(), '/');
                $llmsUrl = htmlspecialchars($baseUrl . '/llms.txt', ENT_QUOTES);
                HeadBlockBuilder::pushSection(
                    HeadBlockBuilder::SECTION_AEO,
                    '<meta name="ai-content-verified" content="true">' . "\n"
                    . '<meta name="ai-content-optimized" content="true">' . "\n"
                    . '<meta name="llms-txt" content="' . $llmsUrl . '">'
                );
            }
        }

        // Markdown discovery <link> (Free) — lets AI agents auto-discover the
        // Markdown alternate of the current page.
        if ((int) ($settings['markdown_pages_enabled'] ?? 0)) {
            try {
                $current  = Uri::getInstance();
                $href     = $current->toString(['scheme', 'host', 'port', 'path']);
                $sep      = str_contains($href, '?') ? '&' : '?';
                $hrefAttr = htmlspecialchars($href . $sep . 'markdown=1', ENT_QUOTES);
                HeadBlockBuilder::pushSection(
                    HeadBlockBuilder::SECTION_AEO,
                    '<link rel="alternate" type="text/markdown" href="' . $hrefAttr . '">'
                );
            } catch (\Throwable $e) {
                // Document may not be HTML; silently skip.
            }
        }
    }

    /**
     * onContentAfterSave — Pro: auto-submit to IndexNow when an article is
     * saved as published (relocated from aiboost_aeo_pro). IndexNowService ships
     * only in the Pro build (FREE_EXCLUDE) → class_exists() is false on Free.
     */
    public function onContentAfterSave(string $context, object $article, bool $isNew): void
    {
        if (!$this->libReady()) {
            return;
        }
        if ($context !== 'com_content.article') {
            return;
        }
        if ((int) ($article->state ?? 0) !== 1) {
            return;
        }
        $settings = $this->getAiBoostSettings();
        if (!class_exists(IndexNowService::class) || !PluginRegistry::isProActive($settings)) {
            return;
        }
        if (!(int) ($settings['indexnow_enabled'] ?? 0) || !(int) ($settings['indexnow_auto_submit'] ?? 0)) {
            return;
        }
        $apiKey = trim((string) ($settings['indexnow_api_key'] ?? ''));
        if ($apiKey === '') {
            return;
        }
        $siteRoot = rtrim(Uri::root(), '/');
        $url      = $this->buildArticleUrl((int) ($article->id ?? 0));
        if ($url !== '') {
            (new IndexNowService($apiKey, $siteRoot))->submit($url);
        }
    }

    /**
     * onContentChangeState — Pro: auto-submit to IndexNow when an article's
     * state changes to published (relocated from aiboost_aeo_pro).
     *
     * @param array<int|string,int|string> $pks
     */
    public function onContentChangeState(string $context, array $pks, int $value): void
    {
        if (!$this->libReady()) {
            return;
        }
        if ($context !== 'com_content.article' || $value !== 1) {
            return;
        }
        $settings = $this->getAiBoostSettings();
        if (!class_exists(IndexNowService::class) || !PluginRegistry::isProActive($settings)) {
            return;
        }
        if (!(int) ($settings['indexnow_enabled'] ?? 0) || !(int) ($settings['indexnow_auto_submit'] ?? 0)) {
            return;
        }
        $apiKey = trim((string) ($settings['indexnow_api_key'] ?? ''));
        if ($apiKey === '') {
            return;
        }
        $siteRoot = rtrim(Uri::root(), '/');
        $svc      = new IndexNowService($apiKey, $siteRoot);
        foreach ($pks as $pk) {
            $url = $this->buildArticleUrl((int) $pk);
            if ($url !== '') {
                $svc->submit($url);
            }
        }
    }

    /**
     * Build the canonical article URL for an IndexNow submission (relocated
     * from aiboost_aeo_pro).
     */
    private function buildArticleUrl(int $id): string
    {
        if ($id <= 0) {
            return '';
        }
        try {
            $uri = Uri::getInstance();
            return $uri->getScheme() . '://' . $uri->getHost()
                . '/index.php?option=com_content&view=article&id=' . $id;
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * onAfterRender — finalise shared blocks, then (when this is a Markdown
     * request) replace the rendered HTML with clean Markdown.
     */
    public function onAfterRender(): void
    {
        if (!$this->libReady()) {
            return;
        }

        $app = Factory::getApplication();
        HeadBlockBuilder::finalize($app, Version::VERSION);
        BodyBlockBuilder::finalize($app);

        if ($this->isMarkdownRequest && $app->isClient('site')) {
            try {
                $html = (string) $app->getBody();
                if ($html !== '') {
                    $markdown = (new MarkdownConverterService())->convert($html);
                    $app->setHeader('Content-Type', 'text/markdown; charset=utf-8', true);
                    $app->setHeader('Cache-Control', 'public, max-age=3600', true);
                    try { $app->setHeader('X-Content-Type-Options', 'nosniff', true); } catch (\Throwable $e) {}
                    $app->setBody($markdown);
                }
            } catch (\Throwable $e) {
                // On any conversion error, let the original HTML through.
            }
        }
    }

    /**
     * Markdown request detection — sets $isMarkdownRequest and rewrites URLs
     * ending in .md so Joomla's router can resolve them. (Free, Korak 3.2 #3.)
     *
     * @param array<string,mixed> $settings
     */
    private function detectMarkdownRequest(string $path, array $settings): void
    {
        if (!(int) ($settings['markdown_pages_enabled'] ?? 0)) {
            return;
        }

        $accept   = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        $acceptMd = stripos($accept, 'text/markdown') !== false;
        $queryMd  = isset($_GET['markdown']) && (string) $_GET['markdown'] === '1';
        $suffixMd = str_ends_with(strtolower($path), '.md');

        if (!$acceptMd && !$queryMd && !$suffixMd) {
            return;
        }

        try {
            if (!Factory::getApplication()->isClient('site')) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }

        $this->isMarkdownRequest = true;

        if ($suffixMd) {
            $newPath = '/' . substr($path, 0, -3);
            $qs      = (string) ($_SERVER['QUERY_STRING'] ?? '');
            $newUri  = $newPath . ($qs !== '' ? '?' . $qs : '');

            $_SERVER['REQUEST_URI'] = $newUri;
            try {
                Factory::getApplication()->input->server->set('REQUEST_URI', $newUri);
            } catch (\Throwable $e) {}

            try {
                $ref  = new \ReflectionClass(Uri::class);
                $prop = $ref->getProperty('instances');
                // Note: ReflectionProperty::setAccessible() is a no-op since PHP 8.1
                // (reflection grants access automatically) and is *deprecated* in PHP
                // 8.5 — calling it would emit an E_DEPRECATED notice. Omitted on purpose.
                $prop->setValue(null, []);
            } catch (\Throwable $e) {}
        }
    }

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
