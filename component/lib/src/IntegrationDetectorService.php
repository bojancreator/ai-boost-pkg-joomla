<?php
/**
 * AI Boost — IntegrationDetectorService
 * Detects compatible third-party Joomla extensions by querying #__extensions.
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib;

defined('_JEXEC') or die;

use AiBoost\Lib\Integration\IntegrationRegistry;
use Joomla\Database\DatabaseInterface;

class IntegrationDetectorService
{
    private DatabaseInterface $db;

    /** Cached "site has 2+ published languages" probe — null until first checked. */
    private ?bool $multilingual = null;

    /**
     * Wish-list / planned tiles + always-detect "compatible" extensions.
     * REAL integration bridges (those shipped as `plg_system_aiboost_int_*`)
     * are discovered dynamically via IntegrationRegistry and overlay this
     * map — keeping the dashboard live without a core release per bridge.
     *
        * `status_type`:
        *   'addon'      — Dedicated AI Boost support plugin in the works for this extension
     *   'compatible' — Works out of the box, AI Boost detects and adapts (true "support active")
        *   'planned'    — Roadmap placeholder shown without user actions until scoped
     */
    private const INTEGRATIONS = [
        'falang' => [
            'name'        => 'Multilingual',
            'vendor'      => 'Native Joomla & Falang',
            'category'    => 'Multilingual',
            'description' => 'Per-language hreflang, Schema.org and OpenGraph for multilingual Joomla sites — works with native Joomla language associations and, when present, Falang.',
            'type'        => 'component',
            'element'     => 'com_falang',
            'folder'      => '',
            'status_type' => 'compatible',
            'addon_url'   => '',
            'learn_url'   => 'https://www.falang.net/',
            'icon'        => 'icon-language',
        ],
        'yootheme' => [
            'name'        => 'YOOtheme Pro',
            'vendor'      => 'YOOtheme',
            'category'    => 'Page Builder',
            'description' => 'Premium Joomla theme and page builder. AI Boost detects YOOtheme SEO settings and avoids duplicate meta tags automatically.',
            'type'        => 'plugin',
            'element'     => 'yootheme',
            'folder'      => 'system',
            'status_type' => 'compatible',
            'addon_url'   => '',
            'learn_url'   => 'https://yootheme.com/marketplace/yootheme-pro',
            'icon'        => 'icon-puzzle',
        ],
        'admintools' => [
            'name'        => 'Admin Tools',
            'vendor'      => 'Akeeba',
            'category'    => 'Security',
            'description' => 'Security hardening for Joomla. Detection only — AI Boost never changes Admin Tools. When both are set to manage robots.txt, AI Boost raises a Health warning so you keep robots.txt editing in one tool.',
            'type'        => 'plugin',
            'element'     => 'admintools',
            'folder'      => 'system',
            'status_type' => 'compatible',
            'addon_url'   => '',
            'learn_url'   => 'https://aiboostnow.com/docs/integrations/admintools',
            'icon'        => 'icon-shield',
        ],
        'hikashop' => [
            'name'        => 'HikaShop',
            'vendor'      => 'Hikari Software',
            'category'    => 'E-Commerce',
            'description' => 'E-commerce for Joomla. AI Boost for HikaShop adds Product + Offer schema and OpenGraph meta to product pages and categories.',
            'type'        => 'component',
            'element'     => 'com_hikashop',
            'folder'      => '',
            'status_type' => 'addon',
            'addon_url'   => '',
            'learn_url'   => 'https://www.hikashop.com/',
            'icon'        => 'icon-shopping-cart',
        ],
        'virtuemart' => [
            'name'        => 'VirtueMart',
            'vendor'      => 'VirtueMart Team',
            'category'    => 'E-Commerce',
            'description' => 'The classic Joomla e-commerce component. AI Boost for VirtueMart adds Product schema, rich snippets, and OG meta to product listings.',
            'type'        => 'component',
            'element'     => 'com_virtuemart',
            'folder'      => '',
            'status_type' => 'addon',
            'addon_url'   => '',
            'learn_url'   => 'https://virtuemart.net/',
            'icon'        => 'icon-shopping-cart',
        ],
        'forseo' => [
            'name'        => '4SEO Pro',
            'vendor'      => 'Weeblr',
            'category'    => 'SEO Suite',
            'description' => 'All-in-one Joomla SEO suite with metadata, sitemap, structured data, social sharing, redirects, canonical URLs, and analytics features. Compatibility scope is planned.',
            'type'        => 'component',
            'element'     => 'com_4seo',
            'folder'      => '',
            'status_type' => 'planned',
            'addon_url'   => '',
            'learn_url'   => 'https://weeblr.com/joomla-seo/4seo',
            'icon'        => 'icon-search',
        ],
        'easyblog' => [
            'name'        => 'EasyBlog',
            'vendor'      => 'StackIdeas',
            'category'    => 'Content',
            'description' => 'Feature-rich blogging extension for Joomla. Future scope may cover Article and BlogPosting schema for posts and author pages.',
            'type'        => 'component',
            'element'     => 'com_easyblog',
            'folder'      => '',
            'status_type' => 'planned',
            'addon_url'   => '',
            'learn_url'   => 'https://stackideas.com/easyblog',
            'icon'        => 'icon-pencil',
        ],
        // ── Planned: inactive roadmap placeholders ──
        'joomshopping' => [
            'name'        => 'JoomShopping',
            'vendor'      => 'WebDesigner',
            'category'    => 'E-Commerce',
            'description' => 'Free Joomla e-commerce component. Planned: Product/Offer schema and OG meta for product and category pages.',
            'type'        => 'component',
            'element'     => 'com_jshopping',
            'folder'      => '',
            'status_type' => 'planned',
            'addon_url'   => '',
            'learn_url'   => 'https://www.joomshopping.pro/',
            'icon'        => 'icon-shopping-cart',
        ],
        'j2store' => [
            'name'        => 'J2Store',
            'vendor'      => 'J2Store',
            'category'    => 'E-Commerce',
            'description' => 'Joomla e-commerce extension. Future scope may cover product metadata, structured data, and sitemap compatibility checks.',
            'type'        => 'component',
            'element'     => 'com_j2store',
            'folder'      => '',
            'status_type' => 'planned',
            'addon_url'   => '',
            'learn_url'   => 'https://www.j2store.org/',
            'icon'        => 'icon-shopping-cart',
        ],
    ];

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Detect all integrations and return enriched list with `installed` status.
     *
     * @return array<string, array{key: string, name: string, vendor: string, category: string,
     *                              description: string, installed: bool, status: string,
     *                              status_type: string, addon_url: string, learn_url: string, icon: string}>
     */
    public function detect(): array
    {
        $result     = [];
        $toggleKeys = $this->masterToggleKeys();
        $settings   = $this->loadSettings();

        // 1. Static catalogue (planned + compatible tiles).
        foreach (self::INTEGRATIONS as $key => $info) {
            $installed = $this->isExtensionEnabled($info['type'], $info['element'], $info['folder']);
            $installed = $this->applyMultilangAvailability($key, $installed);
            $result[$key] = $this->withMasterToggle(array_merge($info, [
                'key'       => $key,
                'installed' => $installed,
                'status'    => $this->resolveStatus($info['status_type'], $installed),
            ]), $key, $toggleKeys, $settings);
        }

        // 2. Dynamically-registered bridges via IntegrationRegistry (Task #486).
        //    A registered descriptor wins over a static planned tile of the
        //    same key so the dashboard immediately reflects "support active"
        //    once the bridge ZIP is installed.
        try {
            foreach (IntegrationRegistry::all() as $key => $descriptor) {
                $legacy    = $descriptor->toLegacyArray();
                $installed = $this->isExtensionEnabled(
                    $legacy['type'],
                    $legacy['element'],
                    $legacy['folder']
                );
                $installed  = $this->applyMultilangAvailability($key, $installed);
                $statusType = $installed ? 'compatible' : 'addon';
                $result[$key] = $this->withMasterToggle(array_merge($legacy, [
                    'key'         => $key,
                    'installed'   => $installed,
                    'status'      => $this->resolveStatus($statusType, $installed),
                    'status_type' => $statusType,
                    'sdk_version' => $descriptor->sdkVersion,
                    'version'     => $descriptor->version,
                    'dynamic'     => true,
                ]), $key, $toggleKeys, $settings);
            }
        } catch (\Throwable) {
            // Fall back to static catalogue only.
        }

        return $result;
    }

    /**
     * Integration keys that expose a master on/off switch on the Integrations
     * page (manifest key `integration_<key>_enabled`). Falang + YOOtheme ship
     * a static toggle; any registered bridge is toggle-able too.
     *
     * @return array<string,bool>
     */
    private function masterToggleKeys(): array
    {
        $keys = ['falang' => true, 'yootheme' => true];
        try {
            foreach (IntegrationRegistry::keys() as $k) {
                $keys[(string) $k] = true;
            }
        } catch (\Throwable) {
            // Registry unavailable — static set is enough.
        }
        return $keys;
    }

    /**
     * Annotate a tile with master-switch state. When an integration's host is
     * detected but the admin switched it OFF, the status becomes 'paused' so
     * the dashboard can show a distinct "⏸ Paused" badge.
     *
     * @param array<string,mixed> $tile
     * @param array<string,bool>  $toggleKeys
     * @param array<string,mixed> $settings
     * @return array<string,mixed>
     */
    private function withMasterToggle(array $tile, string $key, array $toggleKeys, array $settings): array
    {
        if (empty($toggleKeys[$key])) {
            $tile['has_master_toggle'] = false;
            return $tile;
        }

        // The integration is only truly enabled when BOTH the master setting is on
        // AND our bridge plugin is actually published in Joomla. This keeps the card
        // honest when the plugin is unpublished directly via Joomla → Plugins (the
        // setting alone would otherwise still read as enabled).
        $settingOn = (string) ($settings['integration_' . $key . '_enabled'] ?? '1') !== '0';
        $enabled   = $settingOn && $this->isBridgePluginEnabled($key);
        $tile['has_master_toggle'] = true;
        $tile['master_enabled']    = $enabled;

        if (!$enabled && !empty($tile['installed'])) {
            $tile['status'] = 'paused';
        }

        return $tile;
    }

    /** True when our own bridge plugin plg_system_aiboost_int_<key> is published. */
    private function isBridgePluginEnabled(string $key): bool
    {
        return $this->isExtensionEnabled('plugin', 'aiboost_int_' . $key, 'system');
    }

    /**
     * True when multilingual output is actually active: the Multilingual bridge
     * plugin is published, its master switch is on, and the site has 2+ published
     * languages. Drives the SPA's per-field Translation UI gate so the dropdowns
     * mirror the Integrations card exactly.
     */
    public function isMultilangActive(): bool
    {
        $settings  = $this->loadSettings();
        $settingOn = (string) ($settings['integration_falang_enabled'] ?? '1') !== '0';

        return $settingOn
            && $this->isBridgePluginEnabled('falang')
            && $this->isSiteMultilingual();
    }

    /**
     * Load the #__aiboost_settings 'main' blob via the injected connection.
     *
     * @return array<string,mixed>
     */
    private function loadSettings(): array
    {
        try {
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName('settings_json'))
                ->from($this->db->quoteName('#__aiboost_settings'))
                ->where($this->db->quoteName('setting_key') . '=' . $this->db->quote('main'));
            $json = $this->db->setQuery($query)->loadResult();
            $data = $json ? json_decode((string) $json, true) : [];
            return is_array($data) ? $data : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Returns one of four UI states:
     *   support_active — extension is installed AND AI Boost actively integrates with it
     *   detected       — extension is installed, dedicated AI Boost support plugin still coming
    *   coming_soon    — addon-available integration that is not installed yet
    *   roadmap        — future integration placeholder without actions
     *   not_detected   — compatible integration that is simply not installed
     *
     * Legacy values ('installed', 'addon_available') are kept as fallbacks for any
     * external consumers that hard-code them.
     */
    private function resolveStatus(string $statusType, bool $installed): string
    {
        if ($statusType === 'planned') {
            return 'roadmap';
        }
        if ($installed) {
            return $statusType === 'compatible' ? 'support_active' : 'detected';
        }
        return $statusType === 'addon' ? 'coming_soon' : 'not_detected';
    }

    private function isExtensionEnabled(string $type, string $element, string $folder): bool
    {
        try {
            $query = $this->db->getQuery(true)
                ->select('COUNT(*)')
                ->from('#__extensions')
                ->where($this->db->quoteName('type')    . '=' . $this->db->quote($type))
                ->where($this->db->quoteName('element') . '=' . $this->db->quote($element))
                ->where($this->db->quoteName('enabled') . '=1');

            if ($folder !== '') {
                $query->where($this->db->quoteName('folder') . '=' . $this->db->quote($folder));
            }

            return (int) $this->db->setQuery($query)->loadResult() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Multilang (Workstream C) is a pure-Pro feature for ANY multilingual Joomla
     * site — native language associations or Falang. So the "falang" tile must
     * read as available (not "Not installed") whenever the site has 2+ published
     * languages, even when the Falang host extension is absent. Other
     * integrations are unaffected. Returns the possibly-promoted installed flag.
     */
    private function applyMultilangAvailability(string $key, bool $installed): bool
    {
        if ($key === 'falang' && !$installed && $this->isSiteMultilingual()) {
            return true;
        }
        return $installed;
    }

    /** True when the site publishes 2+ content languages (the signal the
     *  Multilang bridge itself uses to decide whether hreflang is meaningful). */
    private function isSiteMultilingual(): bool
    {
        if ($this->multilingual !== null) {
            return $this->multilingual;
        }
        try {
            $query = $this->db->getQuery(true)
                ->select('COUNT(*)')
                ->from($this->db->quoteName('#__languages'))
                ->where($this->db->quoteName('published') . ' = 1');
            return $this->multilingual = (int) $this->db->setQuery($query)->loadResult() >= 2;
        } catch (\Throwable) {
            return $this->multilingual = false;
        }
    }
}
