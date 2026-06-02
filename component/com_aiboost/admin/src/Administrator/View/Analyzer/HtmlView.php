<?php
/**
 * AI Boost — Analyzer View
 * Passes base URL and CSRF token to the Vue AnalyzerPage.
 *
 * @package     AiBoost\Component\AiBoost\Administrator\View\Analyzer
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Component\AiBoost\Administrator\View\Analyzer;

defined('_JEXEC') or die;

use AiBoost\Version;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;

class HtmlView extends BaseHtmlView
{
    public string $version = '';
    public string $baseUrl = '';
    public string $token   = '';

    public function display($tpl = null): void
    {
        $this->version = Version::VERSION;
        $this->token   = Session::getFormToken();
        // Uri::root() returns the frontend root URL (e.g. https://example.com/).
        // Uri::base() would return the admin URL — not suitable as analyzer target.
        $this->baseUrl = rtrim(Uri::root(), '/');
        $this->addToolbar();
        parent::display($tpl);
    }

    private function addToolbar(): void
    {
        ToolbarHelper::title('AI Boost <small>v' . Version::VERSION . '</small>', 'bolt');
    }
}
