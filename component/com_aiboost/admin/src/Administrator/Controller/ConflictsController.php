<?php
/**
 * AI Boost — Conflicts Controller
 *
 * AJAX endpoint behind the Conflict Manager page. Returns every detected
 * conflict — the cheap DB-only named-competitor scan (ConflictDetector) PLUS the
 * live HTTP generic scan (DuplicateTagScanner, which catches a template's or
 * Tassos-style third-party schema/OG) — together with the current per-feature
 * policy and the first-run flag. Unlike Health, it never suppresses the list in
 * `conflict_mode=off`: the Manager is precisely where the user inspects conflicts.
 *
 * It never disables another vendor's extension — each conflict carries only
 * deep-links to Joomla's Plugins/Extensions manager (the user acts).
 *
 * @package     AiBoost\Component\AiBoost\Administrator\Controller
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Component\AiBoost\Administrator\Controller;

defined('_JEXEC') or die;

use AiBoost\Lib\ConflictDetector;
use AiBoost\Lib\ConflictPolicy;
use AiBoost\Lib\DuplicateTagScanner;
use AiBoost\Lib\JoomlaAppContext;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

class ConflictsController extends BaseController
{
    /**
     * AJAX: scan for conflicts and return JSON.
     * URL: index.php?option=com_aiboost&task=conflicts.scan&format=json
     *
     * @return void
     */
    public function scan(): void
    {
        if (!Session::checkToken()) {
            $this->sendJson(false, 'Invalid security token.');
            return;
        }

        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJson(false, 'Access denied.');
            return;
        }

        try {
            $settings  = $this->loadSettings();
            $dismissed = json_decode((string) ($settings['dismissed_checks'] ?? '[]'), true);
            $dismissed = is_array($dismissed) ? $dismissed : [];

            $conflicts = [];

            // Named competitors — DB-only, always reliable.
            try {
                foreach ((new ConflictDetector(Factory::getDbo(), $settings, $dismissed))->scan() as $c) {
                    $conflicts[] = $c;
                }
            } catch (\Throwable $e) {
                \AiBoost\Lib\Logger::warning($e, ['where' => 'ConflictsController::scan/named']);
            }

            // Generic third-party emitters (template schema, Tassos, etc.) via a
            // live HTTP self-fetch. Best-effort: a blocked/failed fetch (e.g. a
            // self-signed cert) must not break the endpoint — the named scan still
            // returns.
            try {
                foreach ((new DuplicateTagScanner(new JoomlaAppContext(), $dismissed))->scan() as $c) {
                    $conflicts[] = $c;
                }
            } catch (\Throwable $e) {
                \AiBoost\Lib\Logger::warning($e, ['where' => 'ConflictsController::scan/generic']);
            }

            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode([
                'success'   => true,
                'conflicts' => array_values($conflicts),
                'policy'    => $this->currentPolicy($settings),
                'setupDone' => (string) ($settings['conflict_setup_done'] ?? '0') === '1',
            ], JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}';
            $this->app->close();
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning('[AiBoost] Conflicts scan error: ' . $e->getMessage());
            $this->sendJson(false, 'Error scanning for conflicts.');
        }
    }

    /**
     * AJAX: persist the conflict policy chosen in the wizard / Conflict Manager.
     * URL: index.php?option=com_aiboost&task=conflicts.savePolicy&format=json
     *
     * Deliberate read-modify-write of ONLY the conflict keys (the
     * integrations.saveToggle pattern) — NOT settings.save, whose full-blob
     * rewrite would drop everything the Conflict Manager does not post (and on a
     * fresh page the SPA settings mirror is not even loaded yet). conflict_setup_done
     * can only ever be SET (to '1') here, never cleared.
     *
     * @return void
     */
    public function savePolicy(): void
    {
        if (!Session::checkToken()) {
            $this->sendJson(false, 'Invalid security token.');
            return;
        }

        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJson(false, 'Access denied.');
            return;
        }

        try {
            $input   = $this->app->getInput();
            $updates = [];

            $mode = strtolower(trim((string) $input->get('conflict_mode', '', 'string')));
            if (in_array($mode, ['cooperative', 'aggressive', 'off'], true)) {
                $updates['conflict_mode'] = $mode;
            }
            foreach (ConflictPolicy::FEATURES as $feature) {
                $value = strtolower(trim((string) $input->get('conflict_' . $feature, '', 'string')));
                if (in_array($value, ['inherit', 'takeover', 'defer'], true)) {
                    $updates['conflict_' . $feature] = $value;
                }
            }
            // The wizard marks itself answered. Never cleared from the client.
            if ((string) $input->get('conflict_setup_done', '', 'string') === '1') {
                $updates['conflict_setup_done'] = '1';
            }

            if ($updates === []) {
                $this->sendJson(false, 'No valid conflict settings provided.');
                return;
            }

            $settings = $this->loadSettings();
            foreach ($updates as $key => $value) {
                $settings[$key] = $value;
            }
            $this->writeSettings($settings);

            $this->sendJson(true, 'Conflict settings saved.', [
                'policy'    => $this->currentPolicy($settings),
                'setupDone' => (string) ($settings['conflict_setup_done'] ?? '0') === '1',
            ]);
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning('[AiBoost] Conflicts savePolicy error: ' . $e->getMessage());
            $this->sendJson(false, 'Could not save conflict settings. Check the server error log.');
        }
    }

    /**
     * Write the settings blob back (read-modify-write helper). Mirrors
     * IntegrationsController::saveToggle: update the single `main` row, or insert
     * it if a fresh install has none yet.
     *
     * @param array<string,mixed> $settings
     */
    private function writeSettings(array $settings): void
    {
        $db   = Factory::getDbo();
        $now  = Factory::getDate()->toSql();
        $json = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';

        $idQuery = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__aiboost_settings'))
            ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'));
        $existingId = (int) $db->setQuery($idQuery)->loadResult();

        if ($existingId) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__aiboost_settings'))
                ->set($db->quoteName('settings_json') . ' = ' . $db->quote($json))
                ->set($db->quoteName('updated_at') . ' = ' . $db->quote($now))
                ->where($db->quoteName('id') . ' = ' . $existingId);
        } else {
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__aiboost_settings'))
                ->columns([$db->quoteName('setting_key'), $db->quoteName('settings_json'), $db->quoteName('created_at'), $db->quoteName('updated_at')])
                ->values($db->quote('main') . ',' . $db->quote($json) . ',' . $db->quote($now) . ',' . $db->quote($now));
        }
        $db->setQuery($query)->execute();
    }

    /**
     * Current conflict policy: the global mode + each per-feature override, with
     * manifest defaults applied so a fresh install returns a complete shape.
     *
     * @param  array<string,mixed> $settings
     * @return array<string,string>
     */
    private function currentPolicy(array $settings): array
    {
        $policy = ['conflict_mode' => (string) ($settings['conflict_mode'] ?? 'cooperative')];
        foreach (ConflictPolicy::FEATURES as $feature) {
            $policy['conflict_' . $feature] = (string) ($settings['conflict_' . $feature] ?? 'inherit');
        }
        return $policy;
    }

    /** @return array<string,mixed> */
    private function loadSettings(): array
    {
        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName('settings_json'))
                ->from('#__aiboost_settings')
                ->where($db->quoteName('setting_key') . '=' . $db->quote('main'));
            $json  = (string) $db->setQuery($query)->loadResult();
            if ($json === '') {
                return [];
            }
            $decoded = json_decode($json, true);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning($e, ['where' => 'ConflictsController::loadSettings']);
            return [];
        }
    }

    /** @param array<string,mixed> $extra */
    private function sendJson(bool $success, string $message, array $extra = []): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra), JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}';
        $this->app->close();
    }
}
