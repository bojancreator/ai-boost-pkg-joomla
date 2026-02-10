<?php

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class plgSystemJoomlaboostInstallerScript
{
    /**
     * Run on plugin installation
     */
    public function install($adapter)
    {
        $this->createSettingsTable();
        return true;
    }

    /**
     * Run on plugin update
     */
    public function update($adapter)
    {
        // Ensure settings table exists (for upgrades from older versions)
        $this->createSettingsTable();
        return true;
    }

    /**
     * Run on plugin uninstallation
     */
    public function uninstall($adapter)
    {
        // Check if user wants to preserve settings
        // Note: We can't reliably read plugin params here during uninstall
        // So we'll keep the table by default - user can manually drop it
        // Future enhancement: Add admin UI to clear settings

        // Optionally: Always keep settings table for persistence
        // User can manually drop table if needed: DROP TABLE IF EXISTS `#__joomlaboost_settings`;

        return true;
    }

    /**
     * Create settings persistence table
     */
    private function createSettingsTable()
    {
        $db = Factory::getDbo();

        // Check if table already exists
        $tables = $db->getTableList();
        $tablePrefix = $db->getPrefix();
        $tableName = $tablePrefix . 'joomlaboost_settings';

        if (in_array($tableName, $tables)) {
            return true; // Table already exists
        }

        // Create table
        $query = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `setting_key` VARCHAR(100) NOT NULL DEFAULT 'main',
            `settings_json` MEDIUMTEXT NOT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_setting_key` (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        try {
            $db->setQuery($query);
            $db->execute();
            return true;
        } catch (Exception $e) {
            // Log error but don't fail installation
            Factory::getApplication()->enqueueMessage(
                'JoomlaBoost: Could not create settings table: ' . $e->getMessage(),
                'warning'
            );
            return false;
        }
    }

    public function postflight($type, $adapter)
    {
        // Auto-restore settings on install (if persisted settings exist)
        if ($type === 'install') {
            $this->restorePersistedSettings();
        }

        // Show modern installation message
        if ($type === 'install' || $type === 'update') {
            echo '
            <div style="padding: 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 12px; font-family: system-ui, -apple-system, sans-serif; margin: 20px 0; box-shadow: 0 10px 40px rgba(0,0,0,0.2);">
                <h2 style="margin: 0 0 20px 0; font-size: 28px; font-weight: 700;">🚀 JoomlaBoost Successfully ' . ($type === 'install' ? 'Installed' : 'Updated') . '!</h2>
                
                <div style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: 8px; margin-bottom: 20px; backdrop-filter: blur(10px);">
                    <p style="margin: 0 0 10px 0; font-size: 16px; line-height: 1.6;">Universal SEO & Performance plugin that automatically optimizes your Joomla site for search engines and social media.</p>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
                    <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px;">
                        <div style="font-size: 24px; margin-bottom: 8px;">✨</div>
                        <div style="font-weight: 600; margin-bottom: 5px;">Smart SEO</div>
                        <div style="font-size: 13px; opacity: 0.9;">Schema.org, OpenGraph, Meta Tags</div>
                    </div>
                    <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px;">
                        <div style="font-size: 24px; margin-bottom: 8px;">📊</div>
                        <div style="font-weight: 600; margin-bottom: 5px;">Analytics</div>
                        <div style="font-size: 13px; opacity: 0.9;">GA4, GTM, Meta Pixel</div>
                    </div>
                    <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px;">
                        <div style="font-size: 24px; margin-bottom: 8px;">⚡</div>
                        <div style="font-weight: 600; margin-bottom: 5px;">Performance</div>
                        <div style="font-size: 13px; opacity: 0.9;">Caching & Optimization</div>
                    </div>
                    <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px;">
                        <div style="font-size: 24px; margin-bottom: 8px;">🌐</div>
                        <div style="font-weight: 600; margin-bottom: 5px;">Multi-Environment</div>
                        <div style="font-size: 13px; opacity: 0.9;">Production, Staging, Dev</div>
                    </div>
                </div>
                
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.2);">
                    <p style="margin: 0 0 15px 0; font-size: 14px; opacity: 0.95;">📋 <strong>Next Steps:</strong></p>
                    <ol style="margin: 0; padding-left: 20px; font-size: 14px; line-height: 1.8;">
                        <li>Enable the plugin in <strong>System → Plugins</strong></li>
                        <li>Configure your settings in plugin options</li>
                        <li>Add Google Analytics, GTM, or Meta Pixel IDs (optional)</li>
                        <li>Check your site source code for SEO tags</li>
                    </ol>
                </div>
                
                <div style="margin-top: 20px; font-size: 12px; opacity: 0.8; text-align: center;">
                    <strong>Version 0.5.4</strong> | Joomla 4.0+ / 5.0+ / 6.0+ | PHP 8.1+ | Built with ❤️ by JoomlaBoost Team
                </div>
            </div>
            ';
        }

        // Silent logging without user-facing messages
        $log = JPATH_ROOT . '/joomlaboost_install.log';
        file_put_contents($log, "\n" . str_repeat('=', 60) . "\n", FILE_APPEND);
        file_put_contents($log, date('Y-m-d H:i:s') . " - v0.5.4 Installation Complete\n", FILE_APPEND);
        file_put_contents($log, str_repeat('=', 60) . "\n", FILE_APPEND);

        return true;
    }

    /**
     * Restore persisted settings on install
     */
    private function restorePersistedSettings(): void
    {
        try {
            $db = Factory::getDbo();
            $tablePrefix = $db->getPrefix();
            $tableName = $tablePrefix . 'joomlaboost_settings';

            // Check if settings table exists
            $tables = $db->getTableList();
            if (!in_array($tableName, $tables)) {
                return; // No settings to restore
            }

            // Check if settings exist
            $query = $db->getQuery(true)
                ->select($db->quoteName('settings_json'))
                ->from($tableName)
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'));

            $db->setQuery($query);
            $settingsJson = $db->loadResult();

            if (empty($settingsJson)) {
                return; // No settings found
            }

            // Decode settings
            $settings = json_decode($settingsJson, true);
            if (!is_array($settings) || empty($settings)) {
                return;
            }

            // Get plugin ID
            $query = $db->getQuery(true)
                ->select('extension_id')
                ->from('#__extensions')
                ->where($db->quoteName('element') . ' = ' . $db->quote('joomlaboost'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('system'));

            $db->setQuery($query);
            $extensionId = $db->loadResult();

            if (!$extensionId) {
                return;
            }

            // Restore settings to plugin params
            $query = $db->getQuery(true)
                ->update('#__extensions')
                ->set($db->quoteName('params') . ' = ' . $db->quote(json_encode($settings)))
                ->where($db->quoteName('extension_id') . ' = ' . (int) $extensionId);

            $db->setQuery($query);
            $db->execute();

            // Show success message
            Factory::getApplication()->enqueueMessage(
                '✅ JoomlaBoost: Your previous settings have been restored!',
                'success'
            );
        } catch (Exception $e) {
            // Silent fail - don't break installation
        }
    }
}
