<?php
/**
 * AI Boost — Redirects View (list + 404 log)
 *
 * @package     AiBoost\Component\AiBoost\Administrator\View\Redirects
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Component\AiBoost\Administrator\View\Redirects;

defined('_JEXEC') or die;

use AiBoost\Version;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    public string  $version     = '';
    public string  $token       = '';
    public array   $redirects   = [];
    public array   $log404      = [];
    public int     $total404    = 0;
    public string  $prefillFrom = '';
    public string  $activeTab   = 'redirects';

    public function display($tpl = null): void
    {
        $this->version     = Version::VERSION;
        $this->token       = Session::getFormToken();
        $this->redirects   = $this->loadRedirects();
        $this->log404      = $this->load404Log(50);
        $this->total404    = $this->count404();
        $this->prefillFrom = urldecode(trim((string) Factory::getApplication()->getInput()->getString('from_url', '')));
        $this->activeTab   = Factory::getApplication()->getInput()->getString('tab', 'redirects');
        // If from_url is provided, default to rules tab (not 404 tab)
        if ($this->prefillFrom && $this->activeTab === 'redirects') {
            $this->activeTab = 'redirects';
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    private function addToolbar(): void
    {
        ToolbarHelper::title('AI Boost — Redirects <small>v' . Version::VERSION . '</small>', 'bolt');
    }

    private function loadRedirects(): array
    {
        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'from_url', 'to_url', 'redirect_type', 'hits', 'enabled', 'note', 'created_at', 'updated_at']))
                ->from($db->quoteName('#__aiboost_redirects'))
                ->order($db->quoteName('id') . ' DESC')
                ->setLimit(500);
            $db->setQuery($query);
            return $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function load404Log(int $limit = 50): array
    {
        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'request_url', 'referrer', 'hits', 'first_seen', 'last_seen']))
                ->from($db->quoteName('#__aiboost_404_log'))
                ->order($db->quoteName('hits') . ' DESC, ' . $db->quoteName('last_seen') . ' DESC')
                ->setLimit($limit);
            $db->setQuery($query);
            return $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function count404(): int
    {
        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__aiboost_404_log'));
            $db->setQuery($query);
            return (int) $db->loadResult();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
