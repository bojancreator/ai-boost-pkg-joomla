<?php
/**
 * @package     AiBoost\Component\AiBoost\Administrator\Controller
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Component\AiBoost\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

class DashboardController extends BaseController
{
    /**
     * Toggle a single plugin enabled/disabled.
     *
     * POST params:
     *   extension_id  (int)  — #__extensions.extension_id
     *   state         (int)  — 1 = enable, 0 = disable
     *   <token>       (1)    — Joomla CSRF token
     *
     * Returns JSON: { success, message, newState }
     */
    public function togglePlugin(): void
    {
        if (!Session::checkToken('post')) {
            $this->sendJson(false, 'Invalid security token.');
            return;
        }

        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJson(false, 'Access denied.');
            return;
        }

        $input       = $this->app->getInput();
        $extensionId = (int) $input->post->getInt('extension_id', 0);
        $newState    = $input->post->getInt('state', 1) ? 1 : 0;

        if ($extensionId <= 0) {
            $this->sendJson(false, 'Invalid extension ID.');
            return;
        }

        try {
            $db = Factory::getDbo();

            // Verify it is actually one of our own plugins before touching it.
            $checkQuery = $db->getQuery(true)
                ->select(['extension_id', 'element'])
                ->from('#__extensions')
                ->where($db->quoteName('extension_id') . '=' . $extensionId)
                ->where($db->quoteName('type')         . '=' . $db->quote('plugin'))
                ->where($db->quoteName('folder')       . '=' . $db->quote('system'))
                ->where($db->quoteName('element')      . ' LIKE ' . $db->quote('aiboost_%'));

            $row = $db->setQuery($checkQuery)->loadObject();

            if (!$row) {
                $this->sendJson(false, 'Plugin not found or not an AI Boost plugin.');
                return;
            }

            $updateQuery = $db->getQuery(true)
                ->update('#__extensions')
                ->set($db->quoteName('enabled') . '=' . $newState)
                ->where($db->quoteName('extension_id') . '=' . $extensionId);

            $db->setQuery($updateQuery)->execute();

            $label = $newState ? 'enabled' : 'disabled';
            $this->sendJson(true, 'Plugin ' . htmlspecialchars($row->element) . ' ' . $label . '.', $newState);
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning('[AiBoost] DashboardController::togglePlugin error: ' . $e->getMessage());
            $this->sendJson(false, 'An error occurred. Check the server error log.');
        }
    }

    private function sendJson(bool $success, string $message, ?int $newState = null): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        $payload = ['success' => $success, 'message' => $message];
        if ($newState !== null) {
            $payload['newState'] = $newState;
        }
        echo json_encode($payload);
        $this->app->close();
    }
}
