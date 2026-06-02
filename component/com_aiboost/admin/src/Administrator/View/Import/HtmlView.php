<?php
/**
 * @package     AiBoost\Component\AiBoost\Administrator\View\Import
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Component\AiBoost\Administrator\View\Import;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    public string $token = '';

    public function display($tpl = null): void
    {
        // ImportPage.vue (task #337) renders the full UI client-side, so the
        // legacy inline upload script is no longer injected here.
        $this->token = Session::getFormToken();
        $this->addToolbar();
        parent::display($tpl);
    }

    private function addToolbar(): void
    {
        ToolbarHelper::title('AI Boost &mdash; Import / Export', 'upload');
    }
}
