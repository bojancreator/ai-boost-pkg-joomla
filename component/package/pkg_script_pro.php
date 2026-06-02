<?php
/**
 * AI Boost — Pro Upgrade Package Installer Script
 *
 * Installs / upgrades the closed-source Pro plugins:
 *   - aiboost_schema_pro
 *   - aiboost_aeo_pro
 *   - aiboost_social_pro   (SKU: og)
 *   - aiboost_hreflang_pro
 *   - aiboost_code_pro
 *
 * Requirements:
 *   - AI Boost (free) package must already be installed.
 *   - Each plugin requires its license key to be entered + verified on
 *     Components → AI Boost → Licenses before any feature unlocks.
 *
 * @package     AiBoost
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     Commercial — see https://aiboostnow.com/eula
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class Pkg_Aiboost_ProInstallerScript
{
    public const VERSION    = '0.41.0';
    public const MIN_PHP    = '8.1.0';
    public const MIN_JOOMLA = '5.0.0';

    /**
     * Task #511 — Route installer log events through the central Logger
     * when the AiBoost\Lib autoloader is registered; fall back to
     * native error_log() during very first install.
     */
    private static function logEvent(string $severity, string $msg): void
    {
        if (class_exists('AiBoost\\Lib\\Logger', false)) {
            try {
                \AiBoost\Lib\Logger::$severity($msg, ['source' => 'pkg_script_pro']);
                return;
            } catch (\Throwable $e) {
                // fall through to error_log
            }
        }
        @error_log('[AiBoost Pro][' . strtoupper($severity) . '] ' . $msg);
    }

    public function preflight(string $type, object $parent): bool
    {
        $app = Factory::getApplication();

        if (version_compare(PHP_VERSION, self::MIN_PHP, '<')) {
            $app->enqueueMessage(
                sprintf('AI Boost Pro requires PHP %s or higher. You are running PHP %s.', self::MIN_PHP, PHP_VERSION),
                'error'
            );
            return false;
        }
        if (version_compare(JVERSION, self::MIN_JOOMLA, '<')) {
            $app->enqueueMessage(
                sprintf('AI Boost Pro requires Joomla %s or higher. You are running Joomla %s.', self::MIN_JOOMLA, JVERSION),
                'error'
            );
            return false;
        }

        // The free package MUST already be installed (com_aiboost = the host
        // component that owns the shared lib autoloader and Licenses UI).
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('extension_id')
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_aiboost'));
        $exists = (int) $db->setQuery($query)->loadResult();
        if ($exists === 0) {
            $app->enqueueMessage(
                'AI Boost (free) package is not installed. Install pkg_aiboost first, '
                . 'then install this Pro Upgrade package.',
                'error'
            );
            return false;
        }

        return true;
    }

    public function postflight(string $type, object $parent): bool
    {
        $app = Factory::getApplication();

        // Joomla also invokes postflight() with $type='uninstall' AFTER uninstall()
        // has already run removeHealthModule(). Without this guard the install-time
        // side effects below (publishHealthModule / relabel / enablePlugins) would
        // re-create the very module the uninstall just removed, leaving an orphan
        // widget behind. Only act on a genuine install/update.
        if (!in_array($type, ['install', 'update', 'discover_install'], true)) {
            return true;
        }

        // Auto-enable the five Pro system plugins right after install/upgrade.
        // Without this a freshly-installed Pro package leaves every
        // aiboost_*_pro plugin DISABLED in #__extensions, so a paying customer
        // sees no Pro output until they manually publish each plugin one by one.
        // Each Pro plugin self-gates on a verified-active license
        // (PluginRegistry::hasPro), so enabling them unconditionally is safe —
        // no Pro behaviour leaks until the license is active.
        $this->enableProPlugins();

        // Publish the admin Health module (a Pro-only surface that ships in this
        // Pro package). Ensures exactly ONE instance, published in the 'cpanel'
        // position at the top of the control panel, and removes any duplicate
        // instances left by older base builds that used to bundle the module.
        $this->publishHealthModule();

        // Task #455 — Pro just landed → flip the Components menu label to
        // "AI Boost Pro". The base package's applyEditionMenuLabel() also
        // does this on its next install/update, but doing it here makes the
        // label flip immediately when the Pro Upgrade is installed on top
        // of an existing Free install (no need to reinstall the base pkg).
        $this->relabelComponentMenu('COM_AIBOOST_MENU_PRO');

        $app->enqueueMessage(
            'AI Boost Pro Upgrade installed. Open Components → AI Boost → Licenses '
            . 'and verify each license key to unlock Pro features.',
            'message'
        );
        return true;
    }

    /**
     * Enable the five Pro system plugins after install/upgrade.
     *
     * Mirrors Pkg_AiboostInstallerScript::enablePlugins() in pkg_script.php,
     * but targets the Pro plugin element names. Deterministic exact-name
     * updates (no LIKE) so we never touch an unrelated extension. Safe to run
     * on every install/upgrade — UPDATE is idempotent and the plugins gate
     * their own behaviour on an active license.
     */
    private function enableProPlugins(): void
    {
        $plugins = [
            'aiboost_schema_pro',
            'aiboost_aeo_pro',
            'aiboost_social_pro',
            'aiboost_hreflang_pro',
            'aiboost_code_pro',
        ];

        try {
            $db = Factory::getDbo();
            foreach ($plugins as $element) {
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('enabled') . ' = 1')
                    ->where($db->quoteName('type')    . ' = ' . $db->quote('plugin'))
                    ->where($db->quoteName('folder')  . ' = ' . $db->quote('system'))
                    ->where($db->quoteName('element') . ' = ' . $db->quote($element));
                $db->setQuery($query)->execute();
            }
        } catch (\Throwable $e) {
            self::logEvent('warning', '[AiBoost Pro] enableProPlugins failed: ' . $e->getMessage());
            Factory::getApplication()->enqueueMessage(
                'Warning: Could not auto-enable Pro plugins. Please enable them manually in Extensions → Plugins.',
                'warning'
            );
        }
    }

    /**
     * Task #455 — Pro Upgrade package removed → revert the Components menu
     * label to "AI Boost Free". Without this hook the label would stay
     * "AI Boost Pro" until the next base package install/update.
     *
     * Task #461 audit note — the Pro package ships only Pro plugin
     * sub-packages (aiboost_*_pro). It has no DB tables of its own, so
     * the only uninstall side-effect needed here is the menu relabel.
     * Pro plugin rows in `#__extensions` and per-plugin params are
     * removed automatically by Joomla as it uninstalls each child
     * extension declared in `pkg_aiboost_pro.xml`. Shared data (settings,
     * translations) intentionally survives a Pro-only uninstall so the
     * site can keep running on Free without losing configuration.
     */
    public function uninstall(object $parent): bool
    {
        $this->relabelComponentMenu('COM_AIBOOST_MENU_FREE');
        // Remove the admin Health module instances. The module extension (files
        // + #__extensions row) is removed by Joomla via its pkg_aiboost_pro.xml
        // membership; this clears the #__modules / #__modules_menu rows Joomla
        // leaves behind so no orphan widget stays on the control panel.
        $this->removeHealthModule();
        return true;
    }

    /**
     * Create / publish the admin Health module and guarantee exactly ONE
     * instance, published in the 'cpanel' position at the top of the control
     * panel (ordering = 1) and assigned to all menus.
     *
     * Older base builds shipped + published the module, which left staging with
     * duplicate instances. This keeps the lowest-id instance (creating one when
     * none exist), forces it to cpanel/ordering=1/published, and deletes every
     * other admin instance. Idempotent — safe on every install/upgrade.
     */
    private function publishHealthModule(): void
    {
        try {
            $db = Factory::getDbo();

            $ids = array_map('intval', $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('id'))
                    ->from($db->quoteName('#__modules'))
                    ->where($db->quoteName('module') . ' = ' . $db->quote('mod_aiboost_health'))
                    ->where($db->quoteName('client_id') . ' = 1')
                    ->order($db->quoteName('id') . ' ASC')
            )->loadColumn());

            $keepId = $ids[0] ?? 0;

            if ($keepId === 0) {
                $module = (object) [
                    'id'        => null,
                    'asset_id'  => 0,
                    'title'     => 'AI Boost Health',
                    'note'      => '',
                    'content'   => '',
                    'ordering'  => 1,
                    'position'  => 'cpanel',
                    'published' => 1,
                    'module'    => 'mod_aiboost_health',
                    'access'    => 1,
                    'showtitle' => 1,
                    'params'    => '{}',
                    'client_id' => 1,
                    'language'  => '*',
                ];
                $db->insertObject('#__modules', $module, 'id');
                $keepId = (int) $module->id;
                $db->insertObject('#__modules_menu', (object) [
                    'moduleid' => $keepId,
                    'menuid'   => 0,
                ]);
            } else {
                // Force the surviving instance to the desired published state.
                $db->setQuery(
                    $db->getQuery(true)
                        ->update($db->quoteName('#__modules'))
                        ->set($db->quoteName('position') . ' = ' . $db->quote('cpanel'))
                        ->set($db->quoteName('ordering') . ' = 1')
                        ->set($db->quoteName('published') . ' = 1')
                        ->where($db->quoteName('id') . ' = ' . $keepId)
                )->execute();

                // Ensure it is assigned to all menus (menuid = 0).
                $hasAll = (int) $db->setQuery(
                    $db->getQuery(true)
                        ->select('COUNT(*)')
                        ->from($db->quoteName('#__modules_menu'))
                        ->where($db->quoteName('moduleid') . ' = ' . $keepId)
                        ->where($db->quoteName('menuid') . ' = 0')
                )->loadResult();
                if ($hasAll === 0) {
                    $db->setQuery(
                        'DELETE FROM ' . $db->quoteName('#__modules_menu')
                        . ' WHERE ' . $db->quoteName('moduleid') . ' = ' . $keepId
                    )->execute();
                    $db->insertObject('#__modules_menu', (object) [
                        'moduleid' => $keepId,
                        'menuid'   => 0,
                    ]);
                }
            }

            // Remove every other (duplicate) admin instance.
            $dupes = array_values(array_filter($ids, static fn ($id) => $id !== $keepId));
            if ($dupes) {
                $dupeList = implode(',', $dupes);
                $db->setQuery(
                    'DELETE FROM ' . $db->quoteName('#__modules_menu')
                    . ' WHERE ' . $db->quoteName('moduleid') . ' IN (' . $dupeList . ')'
                )->execute();
                $db->setQuery(
                    'DELETE FROM ' . $db->quoteName('#__modules')
                    . ' WHERE ' . $db->quoteName('id') . ' IN (' . $dupeList . ')'
                )->execute();
            }
        } catch (\Throwable $e) {
            self::logEvent('warning', '[AiBoost Pro] publishHealthModule failed: ' . $e->getMessage());
        }
    }

    /**
     * Delete all admin Health module instances on Pro uninstall so no orphan
     * widget remains. The module extension itself is removed by Joomla via its
     * pkg_aiboost_pro.xml membership.
     */
    private function removeHealthModule(): void
    {
        try {
            $db  = Factory::getDbo();
            $ids = array_map('intval', $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('id'))
                    ->from($db->quoteName('#__modules'))
                    ->where($db->quoteName('module') . ' = ' . $db->quote('mod_aiboost_health'))
                    ->where($db->quoteName('client_id') . ' = 1')
            )->loadColumn());
            if (!$ids) {
                return;
            }
            $idList = implode(',', $ids);
            $db->setQuery(
                'DELETE FROM ' . $db->quoteName('#__modules_menu')
                . ' WHERE ' . $db->quoteName('moduleid') . ' IN (' . $idList . ')'
            )->execute();
            $db->setQuery(
                'DELETE FROM ' . $db->quoteName('#__modules')
                . ' WHERE ' . $db->quoteName('id') . ' IN (' . $idList . ')'
            )->execute();
        } catch (\Throwable $e) {
            self::logEvent('warning', '[AiBoost Pro] removeHealthModule failed: ' . $e->getMessage());
        }
    }

    /**
     * Shared helper that mirrors the predicate used by
     * Pkg_AiboostInstallerScript::applyEditionMenuLabel() in pkg_script.php.
     * Updates only the top-level admin Components → AI Boost menu row whose
     * title is one of our known language keys — admin-customized labels are
     * preserved untouched.
     */
    private function relabelComponentMenu(string $titleKey): void
    {
        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__menu'))
                ->set($db->quoteName('title') . ' = ' . $db->quote($titleKey))
                ->where($db->quoteName('client_id') . ' = 1')
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where($db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_aiboost%'))
                ->where($db->quoteName('title') . ' IN ('
                    . $db->quote('COM_AIBOOST_MENU') . ','
                    . $db->quote('COM_AIBOOST_MENU_FREE') . ','
                    . $db->quote('COM_AIBOOST_MENU_PRO') . ')');
            $db->setQuery($query)->execute();
        } catch (\Throwable $e) {
            self::logEvent('warning', '[AiBoost Pro] relabelComponentMenu failed: ' . $e->getMessage());
        }
    }
}
