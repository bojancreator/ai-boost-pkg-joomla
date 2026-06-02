<?php
/**
 * AI Boost — URL Checker View
 *
 * @package     AiBoost\Component\AiBoost\Administrator\View\Urlchecker
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Component\AiBoost\Administrator\View\Urlchecker;

defined('_JEXEC') or die;

use AiBoost\Version;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    public string $version = '';
    public string $token   = '';
    public bool   $hasGsc  = false;

    public function display($tpl = null): void
    {
        $this->version = Version::VERSION;
        $this->token   = Session::getFormToken();
        $this->hasGsc  = $this->detectGsc();

        $this->addToolbar();
        parent::display($tpl);
    }

    private function addToolbar(): void
    {
        ToolbarHelper::title('AI Boost — URL Checker <small>v' . Version::VERSION . '</small>', 'bolt');
    }

    /**
     * Returns true if a Google Search Console API token is stored in settings.
     */
    private function detectGsc(): bool
    {
        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName('settings_json'))
                ->from('#__aiboost_settings')
                ->where($db->quoteName('setting_key') . '=' . $db->quote('main'));
            $json  = (string) $db->setQuery($query)->loadResult();
            if (empty($json)) {
                return false;
            }
            $settings = json_decode($json, true);
            if (!is_array($settings)) {
                return false;
            }
            $token   = trim((string) ($settings['gsc_api_token'] ?? ''));
            $siteUrl = trim((string) ($settings['gsc_site_url']  ?? ''));
            return $token !== '' && $siteUrl !== '';
        } catch (\Throwable $e) {
            return false;
        }
    }
}
