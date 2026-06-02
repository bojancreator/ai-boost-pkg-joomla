<?php
/**
 * AI Boost — Redirects Controller
 *
 * @package     AiBoost\Component\AiBoost\Administrator\Controller
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Component\AiBoost\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

class RedirectsController extends BaseController
{
    public function display($cachable = false, $urlparams = []): static
    {
        $this->app->getInput()->set('view', 'redirects');
        parent::display($cachable, $urlparams);
        return $this;
    }

    /**
     * Add a single redirect rule.
     * POST: from_url, to_url, redirect_type, note
     */
    public function add(): void
    {
        if (!Session::checkToken()) {
            $this->sendJsonResponse(false, 'Invalid security token.');
            return;
        }
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJsonResponse(false, 'Access denied.');
            return;
        }

        $input    = $this->app->getInput();
        $fromUrl  = trim($input->getString('from_url', ''));
        $toUrl    = trim($input->getString('to_url', ''));
        $type     = (int) $input->getInt('redirect_type', 301);
        $note     = trim($input->getString('note', ''));

        if (!$fromUrl || !$toUrl) {
            $this->sendJsonResponse(false, 'from_url and to_url are required.');
            return;
        }

        if (!in_array($type, [301, 302, 303, 307, 308], true)) {
            $type = 301;
        }

        try {
            $db  = Factory::getDbo();
            $now = Factory::getDate()->toSql();
            $db->setQuery(
                $db->getQuery(true)
                    ->insert($db->quoteName('#__aiboost_redirects'))
                    ->columns($db->quoteName(['from_url', 'to_url', 'redirect_type', 'note', 'hits', 'enabled', 'created_at', 'updated_at']))
                    ->values(
                        $db->quote($fromUrl) . ',' .
                        $db->quote($toUrl) . ',' .
                        $type . ',' .
                        $db->quote($note) . ',' .
                        '0, 1,' .
                        $db->quote($now) . ',' .
                        $db->quote($now)
                    )
            )->execute();
            $newId = (int) $db->insertid();
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Redirect rule added.']);
            $this->app->close();
        } catch (\Throwable $e) {
            $this->sendJsonResponse(false, 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Toggle enabled/disabled state of a redirect rule.
     * POST: id, enabled (0|1)
     */
    public function toggle(): void
    {
        if (!Session::checkToken()) {
            $this->sendJsonResponse(false, 'Invalid security token.');
            return;
        }
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJsonResponse(false, 'Access denied.');
            return;
        }

        $input   = $this->app->getInput();
        $id      = (int) $input->getInt('id', 0);
        $enabled = (int) $input->getInt('enabled', 1);

        if (!$id) {
            $this->sendJsonResponse(false, 'Invalid ID.');
            return;
        }

        try {
            $db  = Factory::getDbo();
            $now = Factory::getDate()->toSql();
            $db->setQuery(
                'UPDATE ' . $db->quoteName('#__aiboost_redirects') .
                ' SET enabled = ' . ($enabled ? 1 : 0) . ', updated_at = ' . $db->quote($now) .
                ' WHERE id = ' . $id
            )->execute();
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode(['success' => true]);
            $this->app->close();
        } catch (\Throwable $e) {
            $this->sendJsonResponse(false, 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Delete a redirect rule.
     * POST: id
     */
    public function delete(): void
    {
        if (!Session::checkToken()) {
            $this->sendJsonResponse(false, 'Invalid security token.');
            return;
        }
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJsonResponse(false, 'Access denied.');
            return;
        }

        $id = (int) $this->app->getInput()->getInt('id', 0);
        if (!$id) {
            $this->sendJsonResponse(false, 'Invalid ID.');
            return;
        }

        try {
            $db = Factory::getDbo();
            $db->setQuery(
                'DELETE FROM ' . $db->quoteName('#__aiboost_redirects') . ' WHERE id = ' . $id
            )->execute();
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode(['success' => true]);
            $this->app->close();
        } catch (\Throwable $e) {
            $this->sendJsonResponse(false, 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Delete all logged 404 entries.
     */
    public function clear404(): void
    {
        if (!Session::checkToken()) {
            $this->sendJsonResponse(false, 'Invalid security token.');
            return;
        }
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJsonResponse(false, 'Access denied.');
            return;
        }

        try {
            $db = Factory::getDbo();
            $db->setQuery('TRUNCATE TABLE ' . $db->quoteName('#__aiboost_404_log'))->execute();
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode(['success' => true, 'message' => '404 log cleared.']);
            $this->app->close();
        } catch (\Throwable $e) {
            $this->sendJsonResponse(false, 'Error: ' . $e->getMessage());
        }
    }

    /**
     * Import redirect rules from CSV text (one rule per line: from_url,to_url,type).
     */
    public function importCsv(): void
    {
        if (!Session::checkToken()) {
            $this->sendJsonResponse(false, 'Invalid security token.');
            return;
        }
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJsonResponse(false, 'Access denied.');
            return;
        }

        $csv = trim($this->app->getInput()->getString('csv', ''));
        if (!$csv) {
            $this->sendJsonResponse(false, 'No CSV data provided.');
            return;
        }

        $db      = Factory::getDbo();
        $now     = Factory::getDate()->toSql();
        $lines   = array_filter(array_map('trim', explode("\n", $csv)));
        $imported = 0;
        $skipped  = 0;

        foreach ($lines as $line) {
            if (str_starts_with($line, '#') || str_starts_with($line, 'from_url')) {
                continue; // skip comments and header line
            }
            $parts = str_getcsv($line);
            $from  = trim($parts[0] ?? '');
            $to    = trim($parts[1] ?? '');
            $type  = (int) ($parts[2] ?? 301);
            if (!in_array($type, [301, 302, 303, 307, 308], true)) {
                $type = 301;
            }
            if (!$from || !$to) {
                $skipped++;
                continue;
            }
            try {
                $db->setQuery(
                    $db->getQuery(true)
                        ->insert($db->quoteName('#__aiboost_redirects'))
                        ->columns($db->quoteName(['from_url', 'to_url', 'redirect_type', 'hits', 'enabled', 'created_at', 'updated_at']))
                        ->values(
                            $db->quote($from) . ',' . $db->quote($to) . ',' . $type .
                            ',0,1,' . $db->quote($now) . ',' . $db->quote($now)
                        )
                )->execute();
                $imported++;
            } catch (\Throwable $e) {
                $skipped++;
            }
        }

        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo json_encode(['success' => true, 'imported' => $imported, 'skipped' => $skipped]);
        $this->app->close();
    }

    /**
     * Return current rules + recent 404 log as JSON, for Vue admin.
     * GET/POST — read-only, but still requires admin auth.
     */
    public function listJson(): void
    {
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_aiboost')) {
            $this->sendJsonResponse(false, 'Access denied.');
            return;
        }

        try {
            $db = Factory::getDbo();

            $redirects = $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(['id', 'from_url', 'to_url', 'redirect_type', 'hits', 'enabled', 'note', 'created_at', 'updated_at']))
                    ->from($db->quoteName('#__aiboost_redirects'))
                    ->order($db->quoteName('id') . ' DESC')
                    ->setLimit(500)
            )->loadAssocList() ?: [];

            $log404 = $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(['id', 'request_url', 'referrer', 'hits', 'first_seen', 'last_seen']))
                    ->from($db->quoteName('#__aiboost_404_log'))
                    ->order($db->quoteName('hits') . ' DESC, ' . $db->quoteName('last_seen') . ' DESC')
                    ->setLimit(100)
            )->loadAssocList() ?: [];

            $total404 = (int) $db->setQuery(
                $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__aiboost_404_log'))
            )->loadResult();

            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode([
                'success'   => true,
                'redirects' => $redirects,
                'log404'    => $log404,
                'total404'  => $total404,
            ]);
            $this->app->close();
        } catch (\Throwable $e) {
            $this->sendJsonResponse(false, 'Error: ' . $e->getMessage());
        }
    }

    private function sendJsonResponse(bool $success, string $message): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        echo json_encode(['success' => $success, 'message' => $message]);
        $this->app->close();
    }
}
