<?php
/**
 * AI Boost — OpenGraph & Twitter Cards Plugin (Free)
 *
 * Orchestrator — delegates tag generation to OgTagBuilder and injects the
 * resulting meta tags into the <head> via HeadBlockBuilder.
 *
 * Free tier (this file):
 *   - Global og:title, og:description, og:url, og:type=website, og:image
 *   - Twitter Card tags (twitter:card, twitter:title, twitter:description, twitter:image)
 *   - Uses Joomla document title + meta description as fallback values
 *
 * Pro tier — implemented entirely by the closed-source aiboost_social_pro
 * plugin which listens on `EVENT_FILTER_SOCIAL_PROPS` and decorates the
 * structured props array (per-article OG custom fields with Falang
 * translation, og:type=article + article:* meta, fb:app_id, og:locale,
 * twitter:site handle). Removing the Pro plugin disables those features
 * regardless of settings or runtime license-tier patches.
 *
 * @package     AiBoost\Plugin\System\AiBoostSocial
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostSocial\Extension;

defined('_JEXEC') or die;

use AiBoost\Lib\BodyBlockBuilder;
use AiBoost\Lib\Cms\AdapterRegistry;
use AiBoost\Lib\DocumentInspector;
use AiBoost\Lib\HeadBlockBuilder;
use AiBoost\Lib\Integration\FilterDispatcher;
use AiBoost\Lib\Integration\Sdk;
use AiBoost\Lib\JoomlaAppContext;
use AiBoost\Lib\Page\PageContext;
use AiBoost\Lib\PluginRegistry;
use AiBoost\Lib\TranslationService;
use AiBoost\Plugin\System\AiBoostSocial\Service\OgTagBuilder;
use AiBoost\Plugin\System\AiBoostSocial\Service\OgTagProDecorator;
use AiBoost\Version;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;

class AiBoostSocial extends CMSPlugin
{
    protected $autoloadLanguage = true;

    /** Cached result of libReady() — null until first probed. */
    private ?bool $libReady = null;

    /**
     * onBeforeCompileHead — build and inject OG + Twitter Card meta tags.
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

        $doc = $app->getDocument();
        if (!$doc || $doc->getType() !== 'html') {
            return;
        }

        $settings = $this->getAiBoostSettings();

        // Set hide-comments flag FIRST — before any early-return paths (#384).
        $hide = !empty($settings['hide_comments']);
        HeadBlockBuilder::setHideComments($hide);
        BodyBlockBuilder::setHideComments($hide);

        // Master switches: `enable_opengraph` governs og:* tags, while Twitter
        // Cards are governed separately by `enable_twitter_cards`. When BOTH are
        // off there is nothing to inject. The og render-gate itself lives in
        // OgTagBuilder::renderProps (keyed on the `enable_og` prop).
        $ogEnabled = (int) ($settings['enable_opengraph'] ?? 1);
        $twEnabled = (int) ($settings['enable_twitter_cards'] ?? 1);
        if (!$ogEnabled && !$twEnabled) {
            return;
        }

        // Cooperative conflict-resolution: skip when another extension already
        // emits og:title (Joomla core OG, 4SEO, Sh404SEF, EFSEO, …). See #362.
        // Only relevant while OG output is enabled.
        if ($ogEnabled && DocumentInspector::shouldSkip($doc, DocumentInspector::SIG_OG_TITLE, $settings)) {
            if (!empty($settings['debug_mode'])) {
                error_log('[AI Boost: aiboost_social] Cooperative mode — og:title already present, skipping injection');
            }
            HeadBlockBuilder::noteSkip(
                HeadBlockBuilder::SECTION_SOCIAL,
                'og:title already emitted by another extension'
            );
            return;
        }

        $ctx     = new JoomlaAppContext();
        $db      = Factory::getDbo();

        // T1·S3: resolve the per-request PageContext once and feed it to the Free
        // builder (seeds the props context block) and the Pro decorator (article
        // gate). Behaviour-preserving — the resolver's raw primitives are identical
        // to what $ctx yields; guarded so any resolver failure falls back to null
        // (→ builders use $ctx / props['context'], unchanged).
        $pageContext = $this->resolvePageContext();

        $builder = new OgTagBuilder($settings, $ctx, $db, $pageContext);
        $props   = $builder->buildProps();

        // Pro decorator hook — closed-source aiboost_social_pro listens here
        // and rebuilds the props for article views (custom fields, og:type=article,
        // article:*), sitewide og:locale / fb:app_id / twitter:site, and
        // per-language translation of site_name / og_description_override /
        // default_og_image. When the Pro plugin is absent the props pass through
        // unchanged and the Free baseline is what gets rendered.
        if (class_exists(FilterDispatcher::class)) {
            $filtered = FilterDispatcher::dispatch(
                Sdk::EVENT_FILTER_SOCIAL_PROPS,
                [
                    'props'    => $props,
                    'settings' => $settings,
                ]
            );
            if (isset($filtered['props']) && is_array($filtered['props'])) {
                $props = $filtered['props'];
            }
        }

        // Pro decoration (relocated from the former aiboost_social_pro decorator).
        // OgTagProDecorator ships ONLY in the Pro build (build FREE_EXCLUDE) so
        // class_exists() is false on Free and the base props pass through. Runs
        // here — before renderProps() and before the EVENT_FILTER_OG_TAGS bridge
        // pass below — so Falang/bridge ordering is preserved exactly.
        if (class_exists(OgTagProDecorator::class) && PluginRegistry::isProActive($settings)) {
            try {
                $defaultLang  = (string) Factory::getApplication()->get('language', 'en-GB');
                $translations = PluginRegistry::hasPro('int_falang')
                    ? new TranslationService($db, $defaultLang)
                    : null;
                $props = (new OgTagProDecorator($ctx, $db, $translations, $pageContext))->decorate($props, $settings);
            } catch (\Throwable $e) {
                // On any error, leave the Free baseline untouched — never break the page.
            }
        }

        $tags = OgTagBuilder::renderProps($props);

        // Task #486 — bridges (Falang etc.) can still mutate the rendered tags.
        if (class_exists(FilterDispatcher::class)) {
            $filtered = FilterDispatcher::dispatch(
                Sdk::EVENT_FILTER_OG_TAGS,
                ['tags' => $tags]
            );
            if (isset($filtered['tags']) && is_array($filtered['tags'])) {
                $tags = $filtered['tags'];
            }
        }

        if (empty($tags)) {
            return;
        }

        if (!empty($settings['debug_mode'])) {
            error_log('[AI Boost: aiboost_social] onBeforeCompileHead — pushing ' . count($tags) . ' OpenGraph / Twitter Card tags');
        }

        HeadBlockBuilder::pushSection(
            HeadBlockBuilder::SECTION_SOCIAL,
            implode("\n", $tags)
        );
    }

    /**
     * Defensive NULL/empty guard for the AI Boost OG custom fields (Task #548).
     *
     * Pure, idempotent, lib-free and Joomla-free: normalises NULL/empty values
     * on the OG custom-field object before any type-specific core field plugin
     * reads them, avoiding PHP 8.1+ deprecations. A no-op on Free (the OG fields
     * are a Pro install artifact) and when values are already set. Relocated
     * here from the former aiboost_social_pro decorator during the
     * Pro-replaces-Free collapse so it survives the decorator sweep.
     *
     * @param   string     $context  The content context (e.g. 'com_content.article')
     * @param   \stdClass  $item     The item carrying the fields
     * @param   \stdClass  $field    The field object being prepared (mutated in place)
     *
     * @return  void
     *
     * @libReady-exempt deliberately lib-free AND Joomla-free (pure in-place
     *                  normalisation, no $this state) so it keeps working in
     *                  every partial-install state; exercised standalone by
     *                  scripts/test-og-field-guard.php. Do not add boot()/libReady().
     */
    public function onCustomFieldsPrepareField($context, $item, $field): void
    {
        if ($context !== 'com_content.article') {
            return;
        }

        if (!\is_object($field) || !isset($field->name)) {
            return;
        }

        static $ogFields = [
            'aiboost_og_title',
            'aiboost_og_description',
            'aiboost_og_image',
            'aiboost_og_type',
            'aiboost_og_video',
            'aiboost_twitter_card',
        ];

        if (!\in_array($field->name, $ogFields, true)) {
            return;
        }

        // Media fields decode JSON; everything else is wrapped in a CDATA node.
        // Both PHP 8.1+ deprecations are avoided by replacing NULL/'' with a
        // safe, non-null default. Empty valid JSON keeps the Media renderer happy.
        $default = (string) ($field->type ?? '') === 'media' ? '{"imagefile":""}' : '';

        if (($field->value ?? null) === null || $field->value === '') {
            $field->value = $default;
        }

        if (property_exists($field, 'rawvalue')
            && (($field->rawvalue ?? null) === null || $field->rawvalue === '')) {
            $field->rawvalue = $default;
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
        HeadBlockBuilder::finalize($app, Version::VERSION);
        BodyBlockBuilder::finalize($app);
    }

    /**
     * T1·S3 — resolve the per-request PageContext via the wired resolver.
     *
     * Returns null (so OgTagBuilder/OgTagProDecorator transparently fall back to
     * their $ctx / props['context'] reads — unchanged behaviour) when the Page
     * classes are absent or the resolver throws. The resolver's primitives
     * (option/view/rawId) are by construction identical to JoomlaAppContext's, so
     * routing the read through one place is behaviour-preserving.
     */
    private function resolvePageContext(): ?PageContext
    {
        if (!class_exists('AiBoost\\Lib\\Page\\PageResolver')) {
            return null;
        }
        try {
            return AdapterRegistry::pageResolver()->resolve();
        } catch (\Throwable $e) {
            return null;
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

    // ── Settings helpers ──────────────────────────────────────────────────────

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
}
