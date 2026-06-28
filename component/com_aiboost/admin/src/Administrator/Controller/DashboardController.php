<?php
/**
 * @package     AiBoost\Component\AiBoost\Administrator\Controller
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Component\AiBoost\Administrator\Controller;

defined('_JEXEC') or die;

use AiBoost\Version;
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

    /**
     * Record the current version as "seen" so the post-update "What's New"
     * highlight clears. Internal flag (settings['last_seen_version']) written
     * directly, like dismissed_checks — survives ordinary settings saves.
     *
     * URL: index.php?option=com_aiboost&task=dashboard.markVersionSeen&format=json
     */
    public function markVersionSeen(): void
    {
        if (!Session::checkToken('post')) {
            $this->sendJson(false, 'Invalid security token.');
            return;
        }

        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJson(false, 'Access denied.');
            return;
        }

        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'settings_json']))
                ->from('#__aiboost_settings')
                ->where($db->quoteName('setting_key') . '=' . $db->quote('main'));
            $row = $db->setQuery($query)->loadObject();

            if (!$row) {
                // No settings row yet (first-run) — nothing to persist.
                $this->sendJson(true, 'No settings row.');
                return;
            }

            $settings = json_decode((string) $row->settings_json, true);
            $settings = is_array($settings) ? $settings : [];
            $settings['last_seen_version'] = Version::VERSION;

            $update = $db->getQuery(true)
                ->update('#__aiboost_settings')
                ->set($db->quoteName('settings_json') . '=' . $db->quote(
                    json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                ))
                ->where($db->quoteName('id') . '=' . (int) $row->id);
            $db->setQuery($update)->execute();

            $this->sendJson(true, 'Version marked seen.');
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning('[AiBoost] DashboardController::markVersionSeen error: ' . $e->getMessage());
            $this->sendJson(false, 'Could not save.');
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
