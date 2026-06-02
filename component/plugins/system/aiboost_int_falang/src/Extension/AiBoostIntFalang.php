<?php
/**
 * AI Boost — Falang Integration Plugin
 *
 * Reference implementation of an AI Boost integration plugin, refactored
 * on top of the public Integration SDK (Task #486):
 *
 *   1. Extends AbstractIntegrationPlugin so the lib autoloader, BridgeDetector
 *      check, and onAiBoostRegisterIntegration handler are inherited.
 *   2. describe() returns the canonical IntegrationDescriptor — the same
 *      shape every third-party bridge will ship.
 *   3. Contributes settings fields via onAiBoostRegisterFields (unchanged
 *      contract from 0.39.0; byte-identical field list).
 *   4. Bridges AI Boost runtime services with Falang via the existing
 *      BridgeDetector::register* APIs (unchanged behaviour).
 *
 * Coexistence rule: this plugin only ACTIVATES bridging when Falang is
 * present AND the integration plugin itself is enabled in Joomla. If
 * Falang exists on the site without this plugin, AI Boost runs in
 * "compatible mode" and simply does not add Falang-specific output —
 * NEVER modifies Falang tables or overrides Falang translations.
 *
 * @package     AiBoost\Plugin\System\AiBoostIntFalang
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostIntFalang\Extension;

defined('_JEXEC') or die;

use AiBoost\Lib\BridgeDetector;
use AiBoost\Lib\ConflictManager;
use AiBoost\Lib\Integration\AbstractIntegrationPlugin;
use AiBoost\Lib\Integration\IntegrationDescriptor;
use AiBoost\Lib\Integration\Sdk;
use Joomla\CMS\Factory;

class AiBoostIntFalang extends AbstractIntegrationPlugin
{
    private ?array $languages = null;

    protected function describe(): IntegrationDescriptor
    {
        return new IntegrationDescriptor(
            key:            'falang',
            pluginElement:  'aiboost_int_falang',
            label:          'Falang Pro',
            vendor:         'Falang',
            category:       'Multilingual',
            description:    'Multilingual content management for Joomla. AI Boost integrates Schema.org, OG meta, and sitemap hreflang for all Falang-translated content.',
            hostType:       'component',
            hostElement:    'com_falang',
            sdkVersion:     Sdk::SDK_VERSION,
            minCoreVersion: '0.58.0',
            version:        '0.58.0',
            learnUrl:       'https://www.falang.net/',
            addonUrl:       'https://aiboostnow.com/integrations/falang',
            icon:           'icon-language',
            claimsSlots:    [
                ConflictManager::SLOT_HREFLANG,
            ],
        );
    }

    // ── onAiBoostRegisterFields (manifest contributions) ───────────────────

    public function onAiBoostRegisterFields(): array
    {
        return [
            [
                'key'         => 'falang_hreflang_head',
                'tab'         => 'social',
                'section'     => 'hreflang',
                'label'       => 'Falang: hreflang in <head>',
                'type'        => 'toggle',
                'default'     => '1',
                'tier'        => 'free',
                'sku'         => 'core',
                'integration' => 'falang',
                'description' => 'Generate <link rel="alternate" hreflang> tags from Falang language list.',
            ],
            [
                'key'         => 'falang_hreflang_sitemap',
                'tab'         => 'sitemap',
                'section'     => 'hreflang',
                'label'       => 'Falang: hreflang in sitemap',
                'type'        => 'toggle',
                'default'     => '1',
                'tier'        => 'free',
                'sku'         => 'core',
                'integration' => 'falang',
            ],
            [
                'key'         => 'falang_schema_translate',
                'tab'         => 'schema',
                'section'     => 'translation',
                'label'       => 'Falang: translate Schema.org per language',
                'type'        => 'toggle',
                'default'     => '1',
                'tier'        => 'free',
                'sku'         => 'core',
                'integration' => 'falang',
            ],
            [
                'key'         => 'falang_og_translate',
                'tab'         => 'social',
                'section'     => 'og',
                'label'       => 'Falang: translate OpenGraph per language',
                'type'        => 'toggle',
                'default'     => '1',
                'tier'        => 'free',
                'sku'         => 'core',
                'integration' => 'falang',
            ],
            [
                'key'         => 'falang_primary_language',
                'tab'         => 'general',
                'section'     => 'multilingual',
                'label'       => 'Falang: primary language SEF',
                'type'        => 'text',
                'default'     => 'en',
                'tier'        => 'free',
                'sku'         => 'core',
                'integration' => 'falang',
                'description' => 'SEF code used as x-default in sitemap hreflang alternates.',
            ],
        ];
    }

    // ── Bridge: register translation data with BridgeDetector ──────────────

    public function onAiBoostBeforeSitemapBuild(): void
    {
        if (!$this->isDetected()) {
            return;
        }
        if (!(int) $this->params->get('falang_hreflang_sitemap', 1)) {
            return;
        }
        if (!class_exists(BridgeDetector::class)) {
            return;
        }

        $langs = $this->getLanguages();
        if (!empty($langs)) {
            BridgeDetector::registerSitemapLanguages($langs);
        }

        $primary = trim((string) $this->params->get('falang_primary_language', 'en'));
        if ($primary !== '') {
            BridgeDetector::registerPrimaryLanguageSef($primary);
        }

        $aliasMap = $this->loadFalangAliasMap();
        if (!empty($aliasMap)) {
            BridgeDetector::registerFalangAliasMap($aliasMap);
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /** @return array<int, array<string,string>> */
    private function getLanguages(): array
    {
        if ($this->languages !== null) {
            return $this->languages;
        }
        $out = [];
        try {
            $db = Factory::getDbo();
            $q  = $db->getQuery(true)
                ->select(['lang_id', 'lang_code', 'sef', 'title'])
                ->from($db->quoteName('#__languages'))
                ->where($db->quoteName('published') . ' = 1')
                ->order('ordering ASC');
            $db->setQuery($q);
            foreach ((array) $db->loadAssocList() as $row) {
                $out[] = [
                    'lang_id'   => (string) ($row['lang_id']   ?? ''),
                    'lang_code' => (string) ($row['lang_code'] ?? ''),
                    'sef'       => (string) ($row['sef']       ?? ''),
                    'title'     => (string) ($row['title']     ?? ''),
                ];
            }
        } catch (\Throwable) { /* silent */ }
        return $this->languages = $out;
    }

    /**
     * Build [orig_alias => [sef => translated_alias]] from #__falang_content
     * for menu, articles, and categories. Falang Pro schema:
     *   reference_table, reference_field='alias', reference_id, language_id, value
     *
     * @return array<string, array<string,string>>
     */
    private function loadFalangAliasMap(): array
    {
        $map = [];

        // Falang misconfiguration / partial install: the translation table may
        // be absent. Bail out cleanly instead of letting a failed query surface
        // a DB warning into the sitemap output.
        if (class_exists(BridgeDetector::class) && !BridgeDetector::tableExists('#__falang_content')) {
            return $map;
        }

        try {
            $db    = Factory::getDbo();
            $fetch = function (string $refTable, string $joinTable, string $joinAlias) use ($db, &$map): void {
                try {
                    $q = $db->getQuery(true)
                        ->select([
                            $db->quoteName($joinAlias . '.alias', 'orig'),
                            $db->quoteName('fc.value', 'translated'),
                            $db->quoteName('l.sef', 'sef'),
                        ])
                        ->from($db->quoteName('#__falang_content', 'fc'))
                        ->join('INNER', $db->quoteName($joinTable, $joinAlias)
                            . ' ON ' . $db->quoteName($joinAlias . '.id') . ' = ' . $db->quoteName('fc.reference_id'))
                        ->join('INNER', $db->quoteName('#__languages', 'l')
                            . ' ON ' . $db->quoteName('l.lang_id') . ' = ' . $db->quoteName('fc.language_id'))
                        ->where($db->quoteName('fc.reference_table') . ' = ' . $db->quote($refTable))
                        ->where($db->quoteName('fc.reference_field') . ' = ' . $db->quote('alias'))
                        ->where($db->quoteName('fc.published') . ' = 1')
                        ->where($db->quoteName('l.published') . ' = 1');
                    $db->setQuery($q);
                    foreach ($db->loadObjectList() as $row) {
                        $o = trim((string) ($row->orig ?? ''));
                        $t = trim((string) ($row->translated ?? ''));
                        $s = trim((string) ($row->sef ?? ''));
                        if ($o !== '' && $t !== '' && $s !== '') {
                            $map[$o][$s] = $t;
                        }
                    }
                } catch (\Throwable) { /* skip this entity type */ }
            };

            $fetch('menu', '#__menu', 'm');
            $fetch('#__content', '#__content', 'a');
            $fetch('#__categories', '#__categories', 'c');
        } catch (\Throwable) { /* silent */ }
        return $map;
    }
}
