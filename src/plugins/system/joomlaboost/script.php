<?php

/**
 * Installation Script for JoomlaBoost Plugin
 * Preserves configuration during uninstall/reinstall
 *
 * @package     JoomlaBoost
 * @subpackage  Plugin.System
 * @since       0.1.30
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Language\Text;

/**
 * Script file for JoomlaBoost plugin
 */
class PlgSystemJoomlaboostInstallerScript
{
    /**
     * Backup table for configuration preservation
     */
    private const BACKUP_TABLE = '#__joomlaboost_config_backup';

    /**
     * Called before installation/update
     * Forces cleanup of old plugin files to ensure fresh deployment
     *
     * @param   string            $type     Type of operation (install, update, discover_install)
     * @param   InstallerAdapter  $adapter  The adapter calling this method
     * @return  boolean  True on success
     */
    public function preflight(string $type, InstallerAdapter $adapter): bool
    {
        // On update, force delete old service files to ensure fresh copy
        if ($type === 'update' || $type === 'install') {
            try {
                $pluginPath = JPATH_PLUGINS . '/system/joomlaboost';

                // Delete critical service files that must be refreshed
                $criticalFiles = [
                    $pluginPath . '/src/Services/SchemaService.php',
                    $pluginPath . '/src/Services/OpenGraphService.php',
                    $pluginPath . '/joomlaboost.php'
                ];

                foreach ($criticalFiles as $file) {
                    if (file_exists($file)) {
                        @unlink($file);
                    }
                }

                // Clear OPcache immediately
                if (function_exists('opcache_reset')) {
                    opcache_reset();
                }
            } catch (\Exception $e) {
                // Don't block installation
            }
        }

        return true;
    }

    /**
     * Called before uninstallation
     * Saves plugin configuration to backup table
     *
     * @param   InstallerAdapter  $adapter  The adapter calling this method
     * @return  boolean  True on success
     */
    public function uninstall(InstallerAdapter $adapter): bool
    {
        try {
            $db = Factory::getDbo();

            // Get current plugin configuration
            $query = $db->getQuery(true)
                ->select($db->quoteName(['params', 'enabled']))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('joomlaboost'));

            $db->setQuery($query);
            $config = $db->loadObject();

            if ($config && !empty($config->params)) {
                // Create backup table if not exists
                $this->createBackupTable($db);

                // Save configuration with timestamp
                $backup = (object) [
                    'params' => $config->params,
                    'enabled' => $config->enabled,
                    'backup_date' => Factory::getDate()->toSql()
                ];

                // Delete old backups (keep only last 3)
                $query = $db->getQuery(true)
                    ->delete($db->quoteName(self::BACKUP_TABLE))
                    ->order($db->quoteName('id') . ' DESC');
                $db->setQuery($query, 3); // Skip first 3, delete rest

                try {
                    $db->execute();
                } catch (\Exception $e) {
                    // Table might not exist yet, ignore
                }

                // Insert new backup
                $db->insertObject(self::BACKUP_TABLE, $backup);

                Factory::getApplication()->enqueueMessage(
                    Text::_('PLG_SYSTEM_JOOMLABOOST_UNINSTALL_CONFIG_SAVED'),
                    'info'
                );
            }

            return true;
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                'JoomlaBoost: Error saving configuration: ' . $e->getMessage(),
                'warning'
            );
            return true; // Don't block uninstall
        }
    }

    /**
     * Called after installation/update
     * Restores configuration from backup if available
     * Clears all caches (Joomla, OPcache, Redis)
     *
     * @param   string            $type     Type of operation (install, update, discover_install)
     * @param   InstallerAdapter  $adapter  The adapter calling this method
     * @return  boolean  True on success
     */
    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        // Clear all caches after install/update
        $this->clearAllCaches();

        // Only restore on fresh install, not on update
        if ($type !== 'install') {
            return true;
        }

        try {
            $db = Factory::getDbo();

            // Check if backup table exists
            $tables = $db->getTableList();
            $prefix = $db->getPrefix();
            $backupTable = str_replace('#__', $prefix, self::BACKUP_TABLE);

            if (!in_array($backupTable, $tables)) {
                return true; // No backups available
            }

            // Get latest backup
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName(self::BACKUP_TABLE))
                ->order($db->quoteName('id') . ' DESC')
                ->setLimit(1);

            $db->setQuery($query);
            $backup = $db->loadObject();

            if ($backup && !empty($backup->params)) {
                // Restore configuration
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('params') . ' = ' . $db->quote($backup->params))
                    ->set($db->quoteName('enabled') . ' = ' . (int) $backup->enabled)
                    ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                    ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
                    ->where($db->quoteName('element') . ' = ' . $db->quote('joomlaboost'));

                $db->setQuery($query);
                $db->execute();

                Factory::getApplication()->enqueueMessage(
                    Text::sprintf(
                        'PLG_SYSTEM_JOOMLABOOST_INSTALL_CONFIG_RESTORED',
                        Factory::getDate($backup->backup_date)->format('Y-m-d H:i:s')
                    ),
                    'success'
                );
            }

            return true;
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                'JoomlaBoost: Error restoring configuration: ' . $e->getMessage(),
                'warning'
            );
            return true; // Don't block installation
        }
    }

    /**
     * Create backup table for configuration preservation
     *
     * @param   \Joomla\Database\DatabaseDriver  $db  Database driver
     * @return  void
     */
    private function createBackupTable($db): void
    {
        $query = "CREATE TABLE IF NOT EXISTS " . $db->quoteName(self::BACKUP_TABLE) . " (
            " . $db->quoteName('id') . " INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            " . $db->quoteName('params') . " TEXT NOT NULL,
            " . $db->quoteName('enabled') . " TINYINT(1) NOT NULL DEFAULT 0,
            " . $db->quoteName('backup_date') . " DATETIME NOT NULL,
            PRIMARY KEY (" . $db->quoteName('id') . ")
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci";

        $db->setQuery($query);
        $db->execute();
    }

    /**
     * Clear all caches (Joomla, OPcache, Redis)
     * Called after install/update to ensure new code is used
     *
     * @return  void
     */
    private function clearAllCaches(): void
    {
        try {
            $app = Factory::getApplication();

            // 1. Clear Joomla cache
            $cache = Factory::getCache();
            $cache->clean();

            // 2. Clear OPcache (PHP bytecode cache)
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }

            // 3. Clear Redis cache if configured
            $config = Factory::getConfig();
            $cacheHandler = $config->get('cache_handler', '');

            if ($cacheHandler === 'redis') {
                try {
                    $redis = new \Redis();
                    $redisHost = $config->get('redis_server_host', '127.0.0.1');
                    $redisPort = $config->get('redis_server_port', 6379);

                    if ($redis->connect($redisHost, $redisPort)) {
                        // Only flush Joomla cache keys, not entire Redis
                        $keys = $redis->keys('joomla:*');
                        if (!empty($keys)) {
                            $redis->del($keys);
                        }
                        $redis->close();
                    }
                } catch (\Exception $e) {
                    // Redis not available or not configured, ignore
                }
            }

            $app->enqueueMessage(
                'JoomlaBoost: All caches cleared (Joomla, OPcache, Redis)',
                'info'
            );
        } catch (\Exception $e) {
            // Don't block installation if cache clear fails
            Factory::getApplication()->enqueueMessage(
                'JoomlaBoost: Cache clear warning: ' . $e->getMessage(),
                'warning'
            );
        }
    }
}
