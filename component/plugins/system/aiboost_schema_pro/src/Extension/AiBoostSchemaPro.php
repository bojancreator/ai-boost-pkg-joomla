<?php
/**
 * AI Boost — Schema.org Legacy Decorator
 *
 * Compatibility decorator for installations that still have the historical
 * split schema package present. The active one-product package now emits the
 * core business schema directly from `aiboost_schema`; this decorator remains
 * to support older add-on deployments and extended JSON-LD blocks.
 *
 *   - 13 Site Type presets (LocalBusiness, Hotel, Restaurant, etc.)
 *   - openingHoursSpecification (BusinessHoursBuilder)
 *   - AggregateRating
 *   - Type-specific fields (priceRange, servesCuisine, starRating, …)
 *   - Per-language org translations (TranslationService + FalangBridge)
 *   - FAQPage, QAPage, Article, HowTo, Event blocks
 *   - Per-author Person block (custom-field driven)
 *
 * Wiring: listens on `EVENT_FILTER_SCHEMA_BLOCKS` fired by the core
 * aiboost_schema plugin and applies `SchemaProBuilder::decorateAll()` to the
 * structured blocks array when the legacy entitlement gate allows it.
 *
 * @package     AiBoost\Plugin\System\AiBoostSchemaPro
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostSchemaPro\Extension;

defined('_JEXEC') or die;

use AiBoost\Lib\Integration\FilterResult;
use AiBoost\Lib\JoomlaAppContext;
use AiBoost\Lib\PluginRegistry;
use AiBoost\Lib\TranslationService;
use AiBoost\Plugin\System\AiBoostSchemaPro\Features\BreadcrumbPro;
use AiBoost\Plugin\System\AiBoostSchemaPro\Service\SchemaProBuilder;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;

class AiBoostSchemaPro extends CMSPlugin
{
    protected $autoloadLanguage = true;

    private bool $booted = false;

    public function onAfterInitialise(): void
    {
        $this->boot();
    }

    /**
    * Decorate the core schema blocks and append extended blocks
    * (FAQPage, QAPage, Article, HowTo, Event).
     *
    * Listener for `EVENT_FILTER_SCHEMA_BLOCKS`. The core plugin fires this
    * event after building its blocks.
     */
    public function onAiBoostFilterSchemaBlocks(array $input, FilterResult $result): void
    {
        $this->boot();

        // Legacy activation gate; retained for older split-package installs.
        if (!PluginRegistry::hasPro('schema')) {
            return;
        }

        $current = $result->getOutput();
        $blocks  = $current['blocks'] ?? null;
        if (!is_array($blocks)) {
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
            $translations = new TranslationService($db, $defaultLang);
            $builder      = new SchemaProBuilder($settings, $ctx, $db, $translations);
            $decorated    = $builder->decorateAll($blocks);
        } catch (\Throwable $e) {
            // On any error, leave the core blocks untouched — never break the page.
            return;
        }

        $current['blocks'] = $decorated;
        $result->setOutput($current, $this->getName(), 'apply schema decoration');
    }

    /**
    * Contribute legacy marker field(s) to the manifest for split-package
    * compatibility.
     *
     * @return array<int, array<string,mixed>>
     */
    public function onAiBoostRegisterFields(): array
    {
        $this->boot();

        return [
            [
                'key'           => BreadcrumbPro::SETTING_KEY,
                'tab'           => 'schema',
                'section'       => 'breadcrumb',
                'label'         => 'Enhanced BreadcrumbList (Pro)',
                'type'          => 'toggle',
                'default'       => '0',
                'tier'          => 'pro',
                'sku'           => 'schema',
                'description'   => 'Emit a richer BreadcrumbList with per-item images and structured position metadata. The core plugin emits the basic BreadcrumbList.',
                'feature_class' => 'BreadcrumbPro',
                'health'        => [
                    'id'                => 'info_schema_breadcrumb_pro_active',
                    'category'          => 'Schema',
                    'message'           => 'Enhanced BreadcrumbList is active. Pages should emit a JSON-LD BreadcrumbList with image and position attributes on every item.',
                    'expected_artifact' => 'application/ld+json with @type=BreadcrumbList including itemListElement[].image',
                    'fix_actions'       => [
                        ['label' => 'Open Schema tab → Breadcrumb', 'target_tab' => 'schema', 'target_field' => 'schema_breadcrumb_pro'],
                    ],
                ],
                'i18n'          => [
                    'label_key'       => 'PLG_SYSTEM_AIBOOST_SCHEMA_BREADCRUMB_PRO_LABEL',
                    'description_key' => 'PLG_SYSTEM_AIBOOST_SCHEMA_BREADCRUMB_PRO_DESC',
                ],
            ],
        ];
    }

    /**
    * Per-request BreadcrumbPro stub trigger (codegen-managed apply()).
    * Extended decoration of the Breadcrumb block runs through
     * SchemaProBuilder above — this hook stays so the codegen `apply()`
     * stub keeps its declared lifecycle.
     */
    public function onBeforeCompileHead(): void
    {
        $this->boot();
        try {
            if (!PluginRegistry::hasPro('schema')) {
                return;
            }

            $db = Factory::getDbo();
            $raw = (string) $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('settings_json'))
                    ->from($db->quoteName('#__aiboost_settings'))
                    ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'))
            )->loadResult();
            $settings = $raw !== '' ? (json_decode($raw, true) ?: []) : [];

            if (BreadcrumbPro::isEnabled($settings)) {
                BreadcrumbPro::apply($settings);
            }
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning('[AiBoostSchemaPro] onBeforeCompileHead failed: ' . $e->getMessage());
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
}
