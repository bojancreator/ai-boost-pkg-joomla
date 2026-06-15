<?php
/**
 * AI Boost — OpenGraph Pro Plugin (dormant decorator)
 *
 * As of the "Pro replaces Free" collapse, the Pro OpenGraph/Twitter enrichment
 * (OgTagProDecorator + CustomFieldReader) AND the defensive OG-field null-guard
 * (onCustomFieldsPrepareField) were relocated INTO the free `aiboost_social`
 * plugin. This element is retained only so an existing split-package install
 * keeps a valid extension row until it is swept on the combined Pro install; it
 * intentionally does nothing. The libReady() guard is kept so the plugin can
 * never fatal on a partial base-package uninstall (incident 2026-06-11).
 *
 * @package     AiBoost\Plugin\System\AiBoostSocialPro
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostSocialPro\Extension;

defined('_JEXEC') or die;

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
        // No-op: Pro OG/Twitter decoration + the OG-field guard now run inside
        // the free aiboost_social plugin (relocated during the collapse).
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
