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
use AiBoost\Lib\DocumentInspector;
use AiBoost\Lib\HeadBlockBuilder;
use AiBoost\Lib\Integration\FilterDispatcher;
use AiBoost\Lib\Integration\Sdk;
use AiBoost\Lib\JoomlaAppContext;
use AiBoost\Plugin\System\AiBoostSocial\Service\OgTagBuilder;
use AiBoost\Version;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;

class AiBoostSocial extends CMSPlugin
{
    protected $autoloadLanguage = true;

    /**
     * onBeforeCompileHead — build and inject OG + Twitter Card meta tags.
     */
    public function onBeforeCompileHead(): void
    {
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
        $builder = new OgTagBuilder($settings, $ctx, $db);
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
     * Idempotent finalize — see HeadBlockBuilder::finalize().
     */
    public function onAfterRender(): void
    {
        $app = Factory::getApplication();
        HeadBlockBuilder::finalize($app, Version::VERSION);
        BodyBlockBuilder::finalize($app);
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
