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
use AiBoost\Plugin\System\AiBoostAeo\Service\LlmsTxtGenerator;
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

            // Pro decorator hook — aiboost_aeo_pro listens here and can
            // rebuild the response with per-language translations and
            // append the "Full Index" reference when llms-full is enabled.
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
                $prop->setAccessible(true);
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
