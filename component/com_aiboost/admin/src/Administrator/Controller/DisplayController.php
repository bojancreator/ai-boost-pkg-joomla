<?php
/**
 * @package     AiBoost\Component\AiBoost\Administrator\Controller
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Component\AiBoost\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;

class DisplayController extends BaseController
{
    protected $default_view = 'app';

    public function display($cachable = false, $urlparams = [])
    {
        $view = $this->input->get('view', $this->default_view);

        $allowedViews = [
            'app',
            'dashboard', 'settings', 'import', 'health', 'redirects',
            'urlchecker', 'help', 'integrations', 'analyzer',
        ];
        if (!in_array($view, $allowedViews, true)) {
            $view = $this->default_view;
        }

        $this->input->set('view', $view);
        parent::display($cachable, $urlparams);
        return $this;
    }
}
