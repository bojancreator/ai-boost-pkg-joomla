<?php
/**
 * AI Boost — AEO / AI Signals Pro Plugin (dormant decorator)
 *
 * As of the "Pro replaces Free" collapse, the Pro AEO logic (LlmsTxtProGenerator,
 * IndexNowService, RobotsBotRules + the /llms-full.txt, /llms-{sef}.txt,
 * /{key}.txt routes, the X-Robots header, and IndexNow auto-submit) was relocated
 * INTO the free `aiboost_aeo` plugin, which now runs it directly, gated on
 * PluginRegistry::isProActive(). This element is retained only so an existing
 * split-package install keeps a valid extension row until it is swept in the
 * final phase; it intentionally does nothing.
 *
 * IMPORTANT (double-fire guard): this class must keep EXACTLY ONE public event
 * handler — the no-op onAfterInitialise(). If any other public on* handler is
 * re-added here while the relocated logic also runs in aiboost_aeo, BOTH would
 * fire (e.g. duplicate IndexNow submissions). The libReady() guard is kept so the
 * plugin can never fatal on a partial base-package uninstall (incident 2026-06-11).
 *
 * @package     AiBoost\Plugin\System\AiBoostAeoPro
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostAeoPro\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;

class AiBoostAeoPro extends CMSPlugin
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
        // No-op: Pro AEO logic now runs inside the free aiboost_aeo plugin
        // (relocated during the Pro-replaces-Free collapse).
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
