<?php
/**
 * AI Boost — OpenGraph Pro Plugin
 *
 * Closed-source upgrade plugin for the 'og' SKU. This plugin physically
 * houses every Pro OpenGraph / Twitter Card feature:
 *
 *   - Per-article OG custom fields (aiboost_og_*) with Falang translation
 *   - Sitewide per-language translations of site_name / description / image
 *   - Article intro-image fallback
 *   - og:type=article + article:* meta
 *   - og:locale auto from active language
 *   - og:video (per-article custom field)
 *   - fb:app_id
 *   - twitter:site handle
 *   - twitter:card type override
 *
 * Removing this plugin from a Free install removes the entire code path —
 * no settings, no license-tier flag, no runtime patch can re-enable Pro
 * behaviour from the Free package.
 *
 * Wiring: listens on the `EVENT_FILTER_SOCIAL_PROPS` event fired by the
 * Free aiboost_social plugin and applies `OgTagProDecorator::decorate()`
 * to the structured props array. Listener priority 5 keeps Pro decoration
 * ahead of bridge mutations (Falang, etc.) which run on the post-render
 * `EVENT_FILTER_OG_TAGS`.
 *
 * @package     AiBoost\Plugin\System\AiBoostSocialPro
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostSocialPro\Extension;

defined('_JEXEC') or die;

use AiBoost\Lib\Integration\FilterResult;
use AiBoost\Lib\JoomlaAppContext;
use AiBoost\Lib\PluginRegistry;
use AiBoost\Lib\TranslationService;
use AiBoost\Plugin\System\AiBoostSocialPro\Service\OgTagProDecorator;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;

class AiBoostSocialPro extends CMSPlugin
{
    protected $autoloadLanguage = true;

    private bool $booted = false;

    /** Cached result of libReady() — null until first probed. */
    private ?bool $libReady = null;

    public function onAfterInitialise(): void
    {
        $this->boot();
        if (!$this->libReady()) {
            return;
        }
    }

    /**
     * Decorate the Free social props with Pro enrichment.
     *
     * Listener for `EVENT_FILTER_SOCIAL_PROPS`. The Free plugin always
     * fires this event after building its baseline props, so this hook
     * runs on every front-end page where the Social plugin is active.
     */
    public function onAiBoostFilterSocialProps(array $input, FilterResult $result): void
    {
        $this->boot();
        if (!$this->libReady()) {
            return;
        }

        // Activation gate — only run when the Pro 'og' license is verified
        // active. Mirrors AiBoostSchemaPro / AiBoostAeoPro; without it the Pro
        // OG/Twitter enrichment leaks on a Pro-inactive install (Task #537).
        if (!PluginRegistry::hasPro('og')) {
            return;
        }

        $current = $result->getOutput();
        $props   = $current['props'] ?? null;
        if (!is_array($props)) {
            return;
        }

        $settings = $current['settings'] ?? ($input['settings'] ?? []);
        if (!is_array($settings)) {
            $settings = [];
        }

        try {
            $ctx          = new JoomlaAppContext();
            $db           = Factory::getDbo();
            $defaultLang  = (string) Factory::getApplication()->get('language', 'en-GB');
            // D3 (Multilang Pro): translated OG is an overlay gated on the
            // Multilang licence. Without it the decorator runs on the 'og' bundle
            // Pro with no per-language overlay (og:type/og:locale/per-article all
            // still emit — see OgTagProDecorator null-guards).
            $translations = PluginRegistry::hasPro('int_falang')
                ? new TranslationService($db, $defaultLang)
                : null;
            $decorator    = new OgTagProDecorator($ctx, $db, $translations);
            $decorated    = $decorator->decorate($props, $settings);
        } catch (\Throwable $e) {
            // On any error, leave Free baseline untouched — never break the page.
            return;
        }

        $current['props'] = $decorated;
        $result->setOutput($current, $this->getName(), 'apply Pro OG/Twitter decoration');
    }

    /**
     * Contribute Pro-only marker field(s) to the manifest.
     *
     * @return array<int, array<string,mixed>>
     */
    public function onAiBoostRegisterFields(): array
    {
        $this->boot();
        if (!$this->libReady()) {
            return [];
        }
        return [];
    }

    /**
     * Defensive NULL/empty guard for the AI Boost OG custom fields (Task #548).
     *
     * The installer re-introduces a Media-type custom field (aiboost_og_image)
     * plus five text-like OG fields on Pro installs, without carrying over the
     * retired legacy `ogfix` fields plugin. Joomla 5–6 core guards null/empty
     * inside its own field renderers, but an older, non-standard, or third-party
     * field renderer could still pass a NULL value into json_decode() /
     * DOMCdataSection() and trip a PHP 8.1+ deprecation.
     *
     * This listener lives on a *system* plugin, which Joomla imports before the
     * `fields` group, so it normalises NULL/empty values on the field object
     * before any type-specific core field plugin reads them. It is a pure,
     * idempotent safety net: when the value is set normally nothing changes, and
     * an empty Media field simply renders nothing.
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
     *                  scripts/test-og-field-guard.php, which instantiates the
     *                  class without Joomla. Do not add boot()/libReady() here.
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
     * Whether the shared AiBoost\Lib library is fully loadable.
     *
     * boot() only checks that lib/autoload.php exists — not enough: a partial
     * base-package uninstall can leave autoload.php on disk while individual
     * lib/src class files are gone, and the first lib reference then fatals
     * on every page. Probing two core lib classes detects that state so every
     * event handler can no-op instead. This is a tripwire, not an exhaustive
     * integrity check. The try/catch matters: under JDEBUG Joomla's debug
     * class loader THROWS on a missing class file instead of returning false.
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
