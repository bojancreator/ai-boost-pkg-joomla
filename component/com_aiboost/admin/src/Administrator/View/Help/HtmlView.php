<?php
/**
 * AI Boost — Help View
 *
 * @package     AiBoost\Component\AiBoost\Administrator\View\Help
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Component\AiBoost\Administrator\View\Help;

defined('_JEXEC') or die;

use AiBoost\Version;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    public string $version = '';

    public function display($tpl = null): void
    {
        $this->version = Version::VERSION;
        $this->addToolbar();
        parent::display($tpl);
    }

    private function addToolbar(): void
    {
        ToolbarHelper::title('AI Boost <small>v' . Version::VERSION . '</small>', 'bolt');
    }
}
