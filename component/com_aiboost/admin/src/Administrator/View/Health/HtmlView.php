<?php
/**
 * AI Boost — Health View
 *
 * @package     AiBoost\Component\AiBoost\Administrator\View\Health
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Component\AiBoost\Administrator\View\Health;

defined('_JEXEC') or die;

use AiBoost\Lib\HealthCheckService;
use AiBoost\Lib\JoomlaAppContext;
use AiBoost\Version;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    public int    $score     = 100;
    public array  $checks    = [];
    public array  $dismissed = [];
    public string $token     = '';
    public string $version   = '';

    public function display($tpl = null): void
    {
        $this->version   = Version::VERSION;
        $this->token     = Session::getFormToken();

        $settings        = $this->loadSettings();
        $service         = new HealthCheckService($settings, Factory::getDbo(), new JoomlaAppContext());
        $result          = $service->run();

        $this->score     = $result['score'];
        $this->checks    = $result['checks'];
        $this->dismissed = $result['dismissed'];

        $this->addToolbar();

        parent::display($tpl);
    }

    private function addToolbar(): void
    {
        ToolbarHelper::title('AI Boost <small>v' . Version::VERSION . '</small>', 'bolt');
    }

    // JS is loaded in the template via HTMLHelper::_('script', ...)
    // — consistent with dashboard and import templates.

    private function loadSettings(): array
    {
        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName('settings_json'))
                ->from('#__aiboost_settings')
                ->where($db->quoteName('setting_key') . '=' . $db->quote('main'));
            $json  = (string) $db->setQuery($query)->loadResult();
            if (empty($json)) {
                return [];
            }
            $decoded = json_decode($json, true);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
