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

    /**
     * Wish-list / planned tiles + always-detect "compatible" extensions.
     * REAL integration bridges (those shipped as `plg_system_aiboost_int_*`)
     * are discovered dynamically via IntegrationRegistry and overlay this
     * map — keeping the dashboard live without a core release per bridge.
     *
     * `status_type`:
     *   'addon'      — Dedicated AI Boost support plugin in the works for this extension
     *   'compatible' — Works out of the box, AI Boost detects and adapts (true "support active")
     *   'planned'    — On the wish-list, ranked by user votes
     */
    private const INTEGRATIONS = [
        'falang' => [
            'name'        => 'Falang Pro',
            'vendor'      => 'Falang',
            'category'    => 'Multilingual',
            'description' => 'Multilingual content management for Joomla. AI Boost integrates Schema.org, OG meta, and sitemap hreflang for all Falang-translated content.',
            'type'        => 'component',
            'element'     => 'com_falang',
            'folder'      => '',
            'status_type' => 'addon',
            'addon_url'   => 'https://aiboostnow.com/integrations/falang',
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
        'hikashop' => [
            'name'        => 'HikaShop',
            'vendor'      => 'Hikari Software',
            'category'    => 'E-Commerce',
            'description' => 'E-commerce for Joomla. AI Boost for HikaShop adds Product + Offer schema and OpenGraph meta to product pages and categories.',
            'type'        => 'component',
            'element'     => 'com_hikashop',
            'folder'      => '',
            'status_type' => 'addon',
            'addon_url'   => 'https://aiboostnow.com/integrations/hikashop',
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
            'addon_url'   => 'https://aiboostnow.com/integrations/virtuemart',
            'learn_url'   => 'https://virtuemart.net/',
            'icon'        => 'icon-shopping-cart',
        ],
        'k2' => [
            'name'        => 'K2',
            'vendor'      => 'JoomlaWorks',
            'category'    => 'Content',
            'description' => 'Extended content component for Joomla. AI Boost detects K2 article pages and generates Article schema, OG meta, and sitemap entries.',
            'type'        => 'component',
            'element'     => 'com_k2',
            'folder'      => '',
            'status_type' => 'addon',
            'addon_url'   => 'https://aiboostnow.com/integrations/k2',
            'learn_url'   => 'https://getk2.org/',
            'icon'        => 'icon-file-alt',
        ],
        'easyblog' => [
            'name'        => 'EasyBlog',
            'vendor'      => 'StackIdeas',
            'category'    => 'Content',
            'description' => 'Feature-rich blogging extension for Joomla. AI Boost for EasyBlog adds Article and BlogPosting schema to every post and author page.',
            'type'        => 'component',
            'element'     => 'com_easyblog',
            'folder'      => '',
            'status_type' => 'addon',
            'addon_url'   => 'https://aiboostnow.com/integrations/easyblog',
            'learn_url'   => 'https://stackideas.com/easyblog',
            'icon'        => 'icon-pencil',
        ],
        'admintools' => [
            'name'        => 'Admin Tools',
            'vendor'      => 'Akeeba',
            'category'    => 'Security',
            'description' => 'Security hardening for Joomla. AI Boost detects Admin Tools and avoids conflicts in robots.txt management and redirect rules.',
            'type'        => 'plugin',
            'element'     => 'admintools',
            'folder'      => 'system',
            'status_type' => 'compatible',
            'addon_url'   => '',
            'learn_url'   => 'https://aiboostnow.com/docs/integrations/admintools',
            'icon'        => 'icon-shield',
        ],
        // ── Planned: vote-driven wish-list (always shows as "Coming soon") ──
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
        'rsformpro' => [
            'name'        => 'RSForm! Pro',
            'vendor'      => 'RSJoomla!',
            'category'    => 'Forms',
            'description' => 'Popular form builder. Planned: ContactPoint schema linking and Meta Pixel Lead event hooks for form submissions.',
            'type'        => 'component',
            'element'     => 'com_rsform',
            'folder'      => '',
            'status_type' => 'planned',
            'addon_url'   => '',
            'learn_url'   => 'https://www.rsjoomla.com/joomla-extensions/joomla-form.html',
            'icon'        => 'icon-list',
        ],
        'chronoforms' => [
            'name'        => 'ChronoForms',
            'vendor'      => 'ChronoEngine',
            'category'    => 'Forms',
            'description' => 'Form builder for Joomla. Planned: schema for contact forms and conversion event tracking through GA4/Meta Pixel.',
            'type'        => 'component',
            'element'     => 'com_chronoforms7',
            'folder'      => '',
            'status_type' => 'planned',
            'addon_url'   => '',
            'learn_url'   => 'https://www.chronoengine.com/',
            'icon'        => 'icon-list',
        ],
        'akeebabackup' => [
            'name'        => 'Akeeba Backup',
            'vendor'      => 'Akeeba',
            'category'    => 'Backup',
            'description' => 'The Joomla backup standard. Planned: pre-publish sitemap snapshots and health-check awareness of backup status.',
            'type'        => 'component',
            'element'     => 'com_akeeba',
            'folder'      => '',
            'status_type' => 'planned',
            'addon_url'   => '',
            'learn_url'   => 'https://www.akeeba.com/products/akeeba-backup.html',
            'icon'        => 'icon-database',
        ],
        'jce' => [
            'name'        => 'JCE Editor',
            'vendor'      => 'WIDGet Factory',
            'category'    => 'Editor',
            'description' => 'Powerful WYSIWYG editor for Joomla. Planned: automatic alt-text suggestions and image schema injection during article editing.',
            'type'        => 'plugin',
            'element'     => 'jce',
            'folder'      => 'editors',
            'status_type' => 'planned',
            'addon_url'   => '',
            'learn_url'   => 'https://www.joomlacontenteditor.net/',
            'icon'        => 'icon-pencil-alt',
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
        $result = [];

        // 1. Static catalogue (planned + compatible tiles).
        foreach (self::INTEGRATIONS as $key => $info) {
            $installed = $this->isExtensionEnabled($info['type'], $info['element'], $info['folder']);
            $result[$key] = array_merge($info, [
                'key'       => $key,
                'installed' => $installed,
                'status'    => $this->resolveStatus($info['status_type'], $installed),
            ]);
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
                $statusType = $installed ? 'compatible' : 'addon';
                $result[$key] = array_merge($legacy, [
                    'key'         => $key,
                    'installed'   => $installed,
                    'status'      => $this->resolveStatus($statusType, $installed),
                    'status_type' => $statusType,
                    'sdk_version' => $descriptor->sdkVersion,
                    'version'     => $descriptor->version,
                    'dynamic'     => true,
                ]);
            }
        } catch (\Throwable) {
            // Fall back to static catalogue only.
        }

        return $result;
    }

    /**
     * Returns one of four UI states:
     *   support_active — extension is installed AND AI Boost actively integrates with it
     *   detected       — extension is installed, dedicated AI Boost support plugin still coming
     *   coming_soon    — planned / addon-available integration that is not installed yet
     *   not_detected   — compatible integration that is simply not installed
     *
     * Legacy values ('installed', 'addon_available') are kept as fallbacks for any
     * external consumers that hard-code them.
     */
    private function resolveStatus(string $statusType, bool $installed): string
    {
        if ($statusType === 'planned') {
            return 'coming_soon';
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
}
