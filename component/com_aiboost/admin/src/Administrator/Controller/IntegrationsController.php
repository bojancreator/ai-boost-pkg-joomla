<?php
/**
 * AI Boost — Integrations Controller
 *
 * @package     AiBoost\Component\AiBoost\Administrator\Controller
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Component\AiBoost\Administrator\Controller;

defined('_JEXEC') or die;

use AiBoost\Lib\Integration\IntegrationRegistry;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

class IntegrationsController extends BaseController
{
    // Display-only view — detection runs in IntegrationsHtmlView::display().

    /**
     * Bridges that ship a master switch even before their bridge plugin has
     * registered with IntegrationRegistry (fresh install / bridge ZIP not yet
     * installed). Keeps the switch usable from day one.
     */
    private const STATIC_TOGGLE_KEYS = ['falang', 'yootheme'];

    /**
     * Per-integration option keys the Integrations page may write. Whitelist —
     * any key not listed here is ignored, so the endpoint can never be coerced
     * into writing arbitrary settings. Mirrors the fields each bridge plugin
     * registers via onAiBoostRegisterFields().
     */
    private const INTEGRATION_OPTION_KEYS = [
        'falang' => [
            'falang_hreflang_head',
            'falang_hreflang_sitemap',
            'falang_hreflang_mode',
            'falang_schema_translate',
            'falang_og_translate',
            'falang_primary_language',
        ],
        'yootheme' => [
            'yootheme_meta_override',
            'yootheme_faq_enabled',
            'yootheme_gallery_enabled',
            'yootheme_schema_mapping',
            'yootheme_accordion_selector',
            'yootheme_sitemap_exclude_builder',
        ],
    ];

    /**
     * AJAX: flip a single integration master switch (`integration_<key>_enabled`).
     *
     * This is a deliberate read-modify-write of ONE key into the settings blob
     * (the `last_backup_at` pattern), NOT a settings.save — a settings.save
     * rebuilds the whole blob from the posted form and would drop everything
     * the Integrations page does not post.
     *
     * URL: index.php?option=com_aiboost&task=integrations.saveToggle
     */
    public function saveToggle(): void
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
            $input = $this->app->getInput();
            $key   = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $input->get('integration', '', 'string')));
            $value = (string) $input->get('enabled', '', 'string') === '1' ? '1' : '0';

            if ($key === '' || !in_array($key, $this->allowedToggleKeys(), true)) {
                $this->sendJson(false, 'Unknown integration.');
                return;
            }

            $settingKey = 'integration_' . $key . '_enabled';

            $db  = Factory::getDbo();
            $now = Factory::getDate()->toSql();

            // Load → modify ONE key → write back the whole blob.
            $query = $db->getQuery(true)
                ->select($db->quoteName('settings_json'))
                ->from($db->quoteName('#__aiboost_settings'))
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'));
            $raw      = (string) $db->setQuery($query)->loadResult();
            $settings = $raw !== '' ? (json_decode($raw, true) ?: []) : [];
            if (!is_array($settings)) {
                $settings = [];
            }

            $settings[$settingKey] = $value;
            $json = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $idQuery = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__aiboost_settings'))
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'));
            $existingId = (int) $db->setQuery($idQuery)->loadResult();

            if ($existingId) {
                $update = $db->getQuery(true)
                    ->update($db->quoteName('#__aiboost_settings'))
                    ->set($db->quoteName('settings_json') . ' = ' . $db->quote($json))
                    ->set($db->quoteName('updated_at') . ' = ' . $db->quote($now))
                    ->where($db->quoteName('id') . ' = ' . $existingId);
            } else {
                $update = $db->getQuery(true)
                    ->insert($db->quoteName('#__aiboost_settings'))
                    ->columns([$db->quoteName('setting_key'), $db->quoteName('settings_json'), $db->quoteName('created_at'), $db->quoteName('updated_at')])
                    ->values($db->quote('main') . ',' . $db->quote($json) . ',' . $db->quote($now) . ',' . $db->quote($now));
            }
            $db->setQuery($update)->execute();

            // The master switch must also publish/unpublish the actual Joomla
            // plugin row — otherwise it is a dead control (the integration plugin
            // keeps running, or stays disabled, regardless of the setting).
            // Scoped to our own 'aiboost_int_<key>' element so it can never flip
            // an unrelated extension. Best-effort: the setting write already
            // succeeded, so a failure here only logs.
            try {
                $extUpdate = $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('enabled') . ' = ' . ($value === '1' ? 1 : 0))
                    ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                    ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
                    ->where($db->quoteName('element') . ' = ' . $db->quote('aiboost_int_' . $key));
                $db->setQuery($extUpdate)->execute();
            } catch (\Throwable $e) {
                \AiBoost\Lib\Logger::warning('[AiBoost] integration plugin publish sync failed: ' . $e->getMessage());
            }

            // Drop request-cached views so a follow-up Health/Settings read in
            // this same request reflects the new switch state.
            $this->invalidateCaches();

            $this->sendJson(true, 'Integration updated.', [
                'integration' => $key,
                'enabled'     => $value === '1',
            ]);
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning('[AiBoost] integration saveToggle error: ' . $e->getMessage());
            $this->sendJson(false, 'Could not update the integration. Check the server error log.');
        }
    }

    /**
     * AJAX: save a single integration's option fields (e.g. falang_*, yootheme_*).
     *
     * Like saveToggle, this is a deliberate read-modify-write of ONLY the
     * whitelisted keys into the settings blob — NOT a settings.save, which would
     * rebuild the whole blob from the posted form and drop everything the
     * Integrations page does not post.
     *
     * POST: integration=<key>, options=<json {key:value}>
     * URL:  index.php?option=com_aiboost&task=integrations.saveOptions
     */
    public function saveOptions(): void
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
            $input = $this->app->getInput();
            $key   = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $input->get('integration', '', 'string')));

            if ($key === '' || !isset(self::INTEGRATION_OPTION_KEYS[$key])) {
                $this->sendJson(false, 'Unknown integration.');
                return;
            }
            $allowed = self::INTEGRATION_OPTION_KEYS[$key];

            $posted = json_decode((string) $input->get('options', '', 'raw'), true);
            if (!is_array($posted)) {
                $this->sendJson(false, 'No options received.');
                return;
            }

            $db  = Factory::getDbo();
            $now = Factory::getDate()->toSql();

            // Load → modify whitelisted keys → write back the whole blob.
            $query = $db->getQuery(true)
                ->select($db->quoteName('settings_json'))
                ->from($db->quoteName('#__aiboost_settings'))
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'));
            $raw      = (string) $db->setQuery($query)->loadResult();
            $settings = $raw !== '' ? (json_decode($raw, true) ?: []) : [];
            if (!is_array($settings)) {
                $settings = [];
            }

            $applied = 0;
            foreach ($allowed as $optKey) {
                if (array_key_exists($optKey, $posted)) {
                    $settings[$optKey] = (string) $posted[$optKey];
                    $applied++;
                }
            }

            $json = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $idQuery = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__aiboost_settings'))
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'));
            $existingId = (int) $db->setQuery($idQuery)->loadResult();

            if ($existingId) {
                $update = $db->getQuery(true)
                    ->update($db->quoteName('#__aiboost_settings'))
                    ->set($db->quoteName('settings_json') . ' = ' . $db->quote($json))
                    ->set($db->quoteName('updated_at') . ' = ' . $db->quote($now))
                    ->where($db->quoteName('id') . ' = ' . $existingId);
            } else {
                $update = $db->getQuery(true)
                    ->insert($db->quoteName('#__aiboost_settings'))
                    ->columns([$db->quoteName('setting_key'), $db->quoteName('settings_json'), $db->quoteName('created_at'), $db->quoteName('updated_at')])
                    ->values($db->quote('main') . ',' . $db->quote($json) . ',' . $db->quote($now) . ',' . $db->quote($now));
            }
            $db->setQuery($update)->execute();

            $this->invalidateCaches();

            $this->sendJson(true, 'Integration options saved.', [
                'integration' => $key,
                'applied'     => $applied,
            ]);
        } catch (\Throwable $e) {
            \AiBoost\Lib\Logger::warning('[AiBoost] integration saveOptions error: ' . $e->getMessage());
            $this->sendJson(false, 'Could not save the integration options. Check the server error log.');
        }
    }

    /** @return list<string> */
    private function allowedToggleKeys(): array
    {
        $keys = self::STATIC_TOGGLE_KEYS;
        try {
            foreach (IntegrationRegistry::keys() as $k) {
                $keys[] = (string) $k;
            }
        } catch (\Throwable) {
            // Registry unavailable (partial lib) — fall back to the static set.
        }
        return array_values(array_unique($keys));
    }

    private function invalidateCaches(): void
    {
        foreach ([
            'AiBoost\\Lib\\PluginRegistry',
            'AiBoost\\Lib\\Manifest\\Registry',
            'AiBoost\\Lib\\Integration\\IntegrationRegistry',
            'AiBoost\\Lib\\PluginSettings',
        ] as $class) {
            try {
                if (class_exists($class) && method_exists($class, 'reset')) {
                    $class::reset();
                }
            } catch (\Throwable) {
                // best-effort
            }
        }
    }

    /**
     * @param array<string,mixed> $extra
     */
    private function sendJson(bool $success, string $message, array $extra = []): void
    {
        try {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        } catch (\Throwable) {
            // no buffer to clean
        }
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra), JSON_UNESCAPED_SLASHES);
        $this->app->close();
    }
}
