<?php
/**
 * AI Boost — Schema.org Plugin
 *
 * Thin orchestrator that delegates schema generation to SchemaBuilder, then
 * fires `EVENT_FILTER_SCHEMA_BLOCKS` so optional add-ons can extend the block
 * list without coupling this plugin to a specific integration package.
 *
 * Core output:
 *   - Organization/Business JSON-LD (name, URL, logo, address, phone, email, social)
 *   - WebSite + SearchAction (homepage only)
 *   - BreadcrumbList (auto-built from Joomla pathway)
 *
 * @package     AiBoost\Plugin\System\AiBoostSchema
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostSchema\Extension;

defined('_JEXEC') or die;

use AiBoost\Lib\BodyBlockBuilder;
use AiBoost\Lib\DocumentInspector;
use AiBoost\Lib\HeadBlockBuilder;
use AiBoost\Lib\Integration\FilterDispatcher;
use AiBoost\Lib\Integration\Sdk;
use AiBoost\Lib\JoomlaAppContext;
use AiBoost\Plugin\System\AiBoostSchema\Service\SchemaBuilder;
use AiBoost\Version;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;

class AiBoostSchema extends CMSPlugin
{
    protected $autoloadLanguage = true;

    /**
    * onBeforeCompileHead — build JSON-LD blocks, let optional integrations
    * filter them via EVENT_FILTER_SCHEMA_BLOCKS, then inject.
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

        // Master Schema.org switch. Default '1' (and absent => enabled, so legacy
        // settings blobs are unaffected); when explicitly turned off, emit no
        // JSON-LD at all. Pro blocks flow through this plugin's filter dispatch,
        // so returning here also suppresses them.
        if (empty($settings['enable_schema'] ?? '1')) {
            if (!empty($settings['debug_mode'])) {
                error_log('[AI Boost: aiboost_schema] enable_schema is off — skipping all JSON-LD output');
            }
            HeadBlockBuilder::noteSkip(
                HeadBlockBuilder::SECTION_SCHEMA,
                'Schema.org output disabled in settings (enable_schema)'
            );
            return;
        }

        // Last-write-wins per request; all plugins read the same setting (#384).
        $hide = !empty($settings['hide_comments']);
        HeadBlockBuilder::setHideComments($hide);
        BodyBlockBuilder::setHideComments($hide);

        // Cooperative conflict-resolution: skip when another extension already
        // emits an Organization/LocalBusiness JSON-LD block (#362).
        if (DocumentInspector::shouldSkip($doc, DocumentInspector::SIG_SCHEMA_ORG, $settings)) {
            if (!empty($settings['debug_mode'])) {
                error_log('[AI Boost: aiboost_schema] Cooperative mode — Organization JSON-LD already present, skipping injection');
            }
            HeadBlockBuilder::noteSkip(
                HeadBlockBuilder::SECTION_SCHEMA,
                'Schema.org already emitted by another extension'
            );
            return;
        }

        $ctx     = new JoomlaAppContext();
        $db      = Factory::getDbo();
        $builder = new SchemaBuilder($settings, $ctx, $db);
        $schemas = $builder->buildAll();

        // Optional integration hook. Listeners may decorate existing blocks or
        // append extra JSON-LD blocks; without listeners the core blocks pass
        // through unchanged.
        if (class_exists(FilterDispatcher::class)) {
            $filtered = FilterDispatcher::dispatch(
                Sdk::EVENT_FILTER_SCHEMA_BLOCKS,
                [
                    'blocks'   => $schemas,
                    'settings' => $settings,
                    'option'   => $ctx->getCurrentOption(),
                    'view'     => $ctx->getCurrentView(),
                    'id'       => $ctx->getCurrentId(),
                ]
            );
            if (isset($filtered['blocks']) && is_array($filtered['blocks'])) {
                $schemas = $filtered['blocks'];
            }
        }

        if (!empty($settings['debug_mode'])) {
            error_log('[AI Boost: aiboost_schema] onBeforeCompileHead — pushing ' . count($schemas) . ' JSON-LD blocks');
        }

        $bodies = [];
        foreach ($schemas as $schema) {
            // JSON_HEX_TAG | JSON_HEX_AMP escape <, >, & to \u00XX so an
            // author-controlled value containing "</script>" (e.g. an article
            // title, meta description, or auto-detected FAQ answer) cannot break
            // out of this <script type="application/ld+json"> element — closes a
            // stored-XSS vector. Slashes stay unescaped for clean schema URLs.
            $json = json_encode(
                $schema,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
                    | JSON_HEX_TAG | JSON_HEX_AMP
            );
            if ($json !== false) {
                $bodies[] = '<script type="application/ld+json">' . "\n" . $json . "\n" . '</script>';
            }
        }
        if (!empty($bodies)) {
            HeadBlockBuilder::pushSection(
                HeadBlockBuilder::SECTION_SCHEMA,
                implode("\n", $bodies)
            );
        }
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
