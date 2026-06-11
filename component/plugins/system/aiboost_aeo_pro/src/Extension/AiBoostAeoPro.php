<?php
/**
 * AI Boost — AEO Pro Plugin
 *
 * Closed-source upgrade plugin for the 'aeo' SKU. This plugin physically
 * houses every Pro AEO / AI Signals feature:
 *
 *   - /llms-full.txt route + /llms-{sef}.txt per-language route
 *   - /{indexnow_api_key}.txt key verification file
 *   - Markdown page conversion (Pro)
 *   - X-Robots-Tag header + AI meta tags + Markdown <link>
 *   - IndexNow auto-submit on article publish
 *   - Per-language /llms.txt rebuild via TranslationService
 *   - Per-bot AI/SEO crawler rules in robots.txt (26 bots)
 *
 * Removing this plugin from a Free install removes the entire code path —
 * no settings, no license-tier flag, no runtime patch can re-enable Pro
 * behaviour from the Free package.
 *
 * Wiring:
 *   - onAfterInitialise           — Pro virtual file routes
 *   - onBeforeCompileHead         — X-Robots, AI meta, Markdown discovery
 *   - onAfterRender               — Markdown body swap
 *   - onContentAfterSave / State  — IndexNow auto-submit
 *   - onAiBoostFilterLlmsTxt      — rebuild /llms.txt with translations
 *                                    and append "Full Index" reference
 *   - onAiBoostFilterRobotsRules  — append 26-bot rules to robots.txt
 *
 * Activation requires `PluginRegistry::hasPro('aeo') === true`.
 *
 * @package     AiBoost\Plugin\System\AiBoostAeoPro
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostAeoPro\Extension;

defined('_JEXEC') or die;

use AiBoost\Lib\Integration\FilterResult;
use AiBoost\Lib\JoomlaAppContext;
use AiBoost\Lib\PluginRegistry;
use AiBoost\Lib\TranslationService;
use AiBoost\Plugin\System\AiBoostAeoPro\Service\IndexNowService;
use AiBoost\Plugin\System\AiBoostAeoPro\Service\LlmsTxtProGenerator;
use AiBoost\Plugin\System\AiBoostAeoPro\Service\RobotsBotRules;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;

class AiBoostAeoPro extends CMSPlugin
{
    protected $autoloadLanguage = true;

    private bool $booted = false;

    /**
     * onAfterInitialise — Pro virtual file routes + Markdown detection.
     *
     * Handles /llms-full.txt, /llms-{sef}.txt, /{indexnow_key}.txt, and
     * Markdown page requests. The Free /llms.txt and /robots.txt routes
     * are owned by aiboost_aeo.
     */
    public function onAfterInitialise(): void
    {
        $this->boot();
        if (!PluginRegistry::hasPro('aeo')) {
            return;
        }

        $uri  = $_SERVER['REQUEST_URI'] ?? '';
        $path = ltrim((string) parse_url($uri, PHP_URL_PATH), '/');

        $settings = $this->getAiBoostSettings();

        $indexNowOn = (int) ($settings['indexnow_enabled'] ?? 0);
        $apiKey     = trim((string) ($settings['indexnow_api_key'] ?? ''));

        // Markdown serving moved to the Free aiboost_aeo plugin (Korak 3.2 #3).
        if ($path === '') {
            return;
        }

        $isLlmsFull = ($path === 'llms-full.txt');
        // The /{key}.txt verification file is only served when IndexNow is on.
        $isKeyFile  = ($indexNowOn && $apiKey !== '' && $path === $apiKey . '.txt');

        // /llms-{sef}.txt — per-language llms.txt
        $langSef    = null;
        $isLlmsLang = false;
        if (!$isLlmsFull && !$isKeyFile && preg_match('/^llms-([a-z]{2,5})\.txt$/i', $path, $m)) {
            $langSef    = strtolower($m[1]);
            $isLlmsLang = true;
        }

        if (!$isLlmsFull && !$isKeyFile && !$isLlmsLang) {
            return;
        }

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
            if ($langCode === '' || $langCode === $defaultLang) {
                return;
            }
            $ctx = new JoomlaAppContext();
            $gen = new LlmsTxtProGenerator(
                $settings, $ctx, $db, new TranslationService($db, $defaultLang), $langCode
            );
            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: public, max-age=86400');
            echo $gen->generate();
            Factory::getApplication()->close();
            return;
        }

        if ($isLlmsFull && (int) ($settings['llms_full_txt_enabled'] ?? 0)) {
            $ctx = new JoomlaAppContext();
            $db  = Factory::getDbo();
            $gen = new LlmsTxtProGenerator(
                $settings, $ctx, $db, new TranslationService($db, $defaultLang)
            );
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
    }

    /**
     * onBeforeCompileHead — inject AI visibility signals (Pro).
     */
    public function onBeforeCompileHead(): void
    {
        $this->boot();
        if (!PluginRegistry::hasPro('aeo')) {
            return;
        }

        $app = Factory::getApplication();
        if (!$app->isClient('site')) {
            return;
        }

        $settings = $this->getAiBoostSettings();

        if ((int) ($settings['enable_x_robots_header'] ?? 0)) {
            header('X-Robots-Tag: index, follow');
        }

        // AI Signals + the Markdown discovery <link> moved to the Free
        // aiboost_aeo plugin (Korak 3.2 #3). This Pro hook now only sets the
        // X-Robots-Tag header above.
    }

    /**
     * onContentAfterSave — auto-submit to IndexNow when an article is
     * saved as published (Pro).
     */
    public function onContentAfterSave(string $context, object $article, bool $isNew): void
    {
        $this->boot();
        if ($context !== 'com_content.article') {
            return;
        }
        if ((int) ($article->state ?? 0) !== 1) {
            return;
        }
        if (!PluginRegistry::hasPro('aeo')) {
            return;
        }
        $settings = $this->getAiBoostSettings();
        if (!(int) ($settings['indexnow_enabled'] ?? 0)) {
            return;
        }
        if (!(int) ($settings['indexnow_auto_submit'] ?? 0)) {
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
     * onContentChangeState — auto-submit to IndexNow when state changes
     * to published (Pro).
     *
     * @param array<int|string,int|string> $pks
     */
    public function onContentChangeState(string $context, array $pks, int $value): void
    {
        $this->boot();
        if ($context !== 'com_content.article' || $value !== 1) {
            return;
        }
        if (!PluginRegistry::hasPro('aeo')) {
            return;
        }
        $settings = $this->getAiBoostSettings();
        if (!(int) ($settings['indexnow_enabled'] ?? 0)) {
            return;
        }
        if (!(int) ($settings['indexnow_auto_submit'] ?? 0)) {
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
     * Rebuild /llms.txt with per-language translations + "Full Index" ref.
     *
     * Listener for `EVENT_FILTER_LLMS_TXT`. The Free plugin fires this
     * after building its baseline /llms.txt body.
     */
    public function onAiBoostFilterLlmsTxt(array $input, FilterResult $result): void
    {
        $this->boot();
        if (!PluginRegistry::hasPro('aeo')) {
            return;
        }

        $current  = $result->getOutput();
        $kind     = (string) ($current['kind'] ?? ($input['kind'] ?? ''));
        if ($kind !== 'llms.txt') {
            return;
        }

        $settings = $current['settings'] ?? ($input['settings'] ?? []);
        if (!is_array($settings)) {
            return;
        }

        try {
            $ctx          = new JoomlaAppContext();
            $db           = Factory::getDbo();
            $defaultLang  = (string) Factory::getApplication()->get('language', 'en-GB');
            $translations = new TranslationService($db, $defaultLang);
            $gen          = new LlmsTxtProGenerator($settings, $ctx, $db, $translations);
            $text         = $gen->generate();
        } catch (\Throwable $e) {
            return;
        }

        $current['text'] = $text;
        $result->setOutput($current, $this->getName(), 'rebuild /llms.txt with Pro translations + Full Index ref');
    }

    /**
     * Append 26-bot AI/SEO crawler rules to robots.txt (Pro).
     *
     * Listener for `EVENT_FILTER_ROBOTS_RULES`. Runs after the Free
     * baseline section is built.
     */
    public function onAiBoostFilterRobotsRules(array $input, FilterResult $result): void
    {
        $this->boot();
        if (!PluginRegistry::hasPro('aeo')) {
            return;
        }

        $current = $result->getOutput();
        $rules   = (string) ($current['rules'] ?? '');
        if ($rules === '') {
            return;
        }

        $settings = $current['settings'] ?? ($input['settings'] ?? []);
        if (!is_array($settings)) {
            $settings = [];
        }

        try {
            $decorated = (new RobotsBotRules())->decorate($rules, $settings);
        } catch (\Throwable $e) {
            return;
        }

        $current['rules'] = $decorated;
        $result->setOutput($current, $this->getName(), 'append 26-bot AI/SEO crawler rules');
    }

    /**
     * Contribute Pro-only marker field(s) to the manifest.
     *
     * @return array<int, array<string,mixed>>
     */
    public function onAiBoostRegisterFields(): array
    {
        $this->boot();
        return [];
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        $loader = JPATH_ADMINISTRATOR . '/components/com_aiboost/lib/autoload.php';
        if (file_exists($loader)) {
            require_once $loader;
        }
    }

    /**
     * Load all AI Boost settings (cached per request).
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
}
