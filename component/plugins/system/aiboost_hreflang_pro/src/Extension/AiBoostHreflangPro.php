<?php
/**
 * AI Boost — Hreflang Pro Plugin
 *
 * Skeleton Pro plugin for the 'hreflang' SKU. Today this plugin only does two
 * things:
 *
 *   1. Boots the shared lib autoloader (so PluginRegistry recognises it
 *      as installed when the Joomla extension row is enabled).
 *   2. Registers a tiny 'Pro is active' info banner field via the
 *      onAiBoostRegisterFields event so the SPA can confirm wiring.
 *
 * Pro logic from component/plugins/system/aiboost_hreflang/ will be
 * relocated here as part of the physical-extraction follow-up. Until
 * that lands, the free plugin gates its Pro branches on
 * PluginRegistry::hasPro('hreflang') (which already requires this plugin
 * + a verified license).
 *
 * @package     AiBoost\Plugin\System\AiBoostHreflangPro
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostHreflangPro\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;

class AiBoostHreflangPro extends CMSPlugin
{
    protected $autoloadLanguage = true;

    private bool $booted = false;

    public function onAfterInitialise(): void
    {
        $this->boot();
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
     * Contribute Pro-only marker field(s) to the manifest. The free
     * package already declares Pro fields with tier='pro' + sku='hreflang'
     * that are locked until PluginRegistry reports this plugin installed
     * AND a license is verified active. This hook is reserved for fields
     * that ONLY ship with the Pro plugin (closed-source feature flags).
     *
     * @return array<int, array<string,mixed>>
     */
    public function onAiBoostRegisterFields(): array
    {
        $this->boot();
        return [];
    }
}
