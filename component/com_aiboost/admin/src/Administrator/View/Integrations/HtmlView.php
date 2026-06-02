<?php
/**
 * AI Boost — Integrations View
 *
 * @package     AiBoost\Component\AiBoost\Administrator\View\Integrations
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Component\AiBoost\Administrator\View\Integrations;

defined('_JEXEC') or die;

use AiBoost\Lib\IntegrationDetectorService;
use AiBoost\Version;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    public string $version      = '';
    public array  $integrations = [];

    public function display($tpl = null): void
    {
        $this->version      = Version::VERSION;
        $this->integrations = $this->detectIntegrations();
        $this->addToolbar();
        parent::display($tpl);
    }

    private function detectIntegrations(): array
    {
        try {
            $service = new IntegrationDetectorService(Factory::getDbo());
            return $service->detect();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function addToolbar(): void
    {
        ToolbarHelper::title('AI Boost <small>v' . Version::VERSION . '</small>', 'bolt');
    }
}
