<?php

/**
 * @package     JoomlaBoost
 * @subpackage  System
 * @copyright   Copyright (C) 2024 4X4 Serbia Crew. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

/**
 * Installation script for JoomlaBoost plugin
 *
 * Handles installation, update, and uninstallation hooks.
 * Auto-detects custom fields and offers to create them if missing.
 *
 * @since 0.1.65
 */
return new class () implements InstallerScriptInterface {
    /**
     * Minimum Joomla version required
     */
    private const MIN_JOOMLA_VERSION = '4.0.0';

    /**
     * Custom field names we manage
     */
    private const FIELD_NAMES = [
        'custom-og-image',
        'custom-og-title',
        'custom-og-description',
    ];

    /**
     * Runs before installation or update
     *
     * @param string $type Type of operation (install, update, discover_install)
     * @param InstallerAdapter $adapter Installer adapter
     * @return bool True to continue, false to abort
     */
    public function preflight(string $type, InstallerAdapter $adapter): bool
    {
        // Check Joomla version
        if (version_compare(JVERSION, self::MIN_JOOMLA_VERSION, '<')) {
            Factory::getApplication()->enqueueMessage(
                sprintf(
                    'JoomlaBoost requires Joomla %s or higher. You are running %s.',
                    self::MIN_JOOMLA_VERSION,
                    JVERSION
                ),
                'error'
            );
            return false;
        }

        return true;
    }

    /**
     * Runs after installation or update
     *
     * @param string $type Type of operation (install, update, discover_install)
     * @param InstallerAdapter $adapter Installer adapter
     * @return bool
     */
    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        $app = Factory::getApplication();
        
        // Create log file for debugging
        $logFile = JPATH_ROOT . '/joomlaboost_install.log';
        $log = function($message) use ($logFile) {
            file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $message . "\n", FILE_APPEND);
        };
        
        $log("=== POSTFLIGHT START (type: $type) ===");

        if ($type === 'install') {
            $log("Processing fresh installation...");
            
            // Fresh installation
            $app->enqueueMessage(
                '✅ JoomlaBoost plugin successfully installed!',
                'success'
            );

            // Auto-create custom fields if they don't exist
            try {
                $log("Checking if custom fields exist...");
                $fieldsExist = $this->checkCustomFieldsExist();
                $log("Fields exist check result: " . ($fieldsExist ? 'YES' : 'NO'));
                
                $app->enqueueMessage(
                    '🔍 Custom fields check: ' . ($fieldsExist ? 'EXIST' : 'MISSING'),
                    'info'
                );
                
                if (!$fieldsExist) {
                    $log("Fields are missing, attempting to create...");
                    
                    $app->enqueueMessage(
                        '🔧 Creating custom fields for per-article OpenGraph overrides...',
                        'info'
                    );
                    
                    $result = $this->createCustomFields();
                    $log("Create fields result: " . json_encode($result));
                    
                    $app->enqueueMessage(
                        '📋 Result: ' . ($result['success'] ? 'SUCCESS' : 'FAILED') . ' - ' . $result['message'],
                        $result['success'] ? 'success' : 'error'
                    );
                } else {
                    $log("Fields already exist, skipping creation.");
                    $app->enqueueMessage(
                        '✅ Custom fields already exist - ready to use!',
                        'success'
                    );
                }
            } catch (\Exception $e) {
                $errorMsg = 'Exception: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine();
                $log("ERROR: " . $errorMsg);
                
                $app->enqueueMessage(
                    '❌ Custom fields setup exception: ' . $errorMsg,
                    'error'
                );
            }
        } elseif ($type === 'update') {
            $log("Processing update...");
            
            // Update from previous version
            $app->enqueueMessage(
                '✅ JoomlaBoost plugin successfully updated to version ' . $adapter->getManifest()->version . '!',
                'success'
            );
            
            // Also check/create fields on update
            $log("Checking fields on update...");
            if (!$this->checkCustomFieldsExist()) {
                $log("Fields missing on update, creating...");
                
                $app->enqueueMessage(
                    '🔧 Creating custom fields for per-article OpenGraph overrides...',
                    'info'
                );
                
                $result = $this->createCustomFields();
                $log("Update create result: " . json_encode($result));
                
                if ($result['success']) {
                    $app->enqueueMessage($result['message'], 'success');
                } else {
                    $app->enqueueMessage($result['message'], 'warning');
                }
            } else {
                $log("Fields already exist on update.");
            }
        }
        
        $log("=== POSTFLIGHT END ===\n");
        return true;
    }

    /**
     * Runs on installation
     *
     * @param InstallerAdapter $adapter Installer adapter
     * @return bool
     */
    public function install(InstallerAdapter $adapter): bool
    {
        return true;
    }

    /**
     * Runs on update
     *
     * @param InstallerAdapter $adapter Installer adapter
     * @return bool
     */
    public function update(InstallerAdapter $adapter): bool
    {
        return true;
    }

    /**
     * Runs on uninstallation
     *
     * @param InstallerAdapter $adapter Installer adapter
     * @return bool
     */
    public function uninstall(InstallerAdapter $adapter): bool
    {
        $app = Factory::getApplication();

        // Note: We don't automatically delete custom fields on uninstall
        // because they might contain user data. Admin can delete manually if needed.
        
        if ($this->checkCustomFieldsExist()) {
            $app->enqueueMessage(
                '⚠️ Custom fields created by JoomlaBoost (custom_og_image, custom_og_title, custom_og_description) ' .
                'were NOT automatically deleted to preserve your data. You can delete them manually from ' .
                'Content → Fields if no longer needed.',
                'notice'
            );
        }

        $app->enqueueMessage(
            'JoomlaBoost plugin uninstalled.',
            'info'
        );

        return true;
    }

    /**
     * Check if custom fields exist
     *
     * @return bool
     */
    private function checkCustomFieldsExist(): bool
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__fields'))
                ->where($db->quoteName('name') . ' IN (' . implode(',', array_map([$db, 'quote'], self::FIELD_NAMES)) . ')')
                ->where($db->quoteName('context') . ' = ' . $db->quote('com_content.article'));

            $db->setQuery($query);
            $count = (int) $db->loadResult();

            return $count === \count(self::FIELD_NAMES);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create custom fields using CustomFieldsService
     *
     * @return array Result array with 'success' and 'message' keys
     */
    private function createCustomFields(): array
    {
        try {
            // Load CustomFieldsService with full namespace
            require_once __DIR__ . '/src/Services/CustomFieldsService.php';
            
            $serviceClass = '\\JoomlaBoost\\Plugin\\System\\JoomlaBoost\\Services\\CustomFieldsService';
            
            if (!class_exists($serviceClass)) {
                return [
                    'success' => false,
                    'message' => 'CustomFieldsService class not found at: ' . $serviceClass
                ];
            }
            
            $customFieldsService = new $serviceClass();
            $result = $customFieldsService->setupCustomFields();
            
            return $result;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception in createCustomFields: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine()
            ];
        }
    }
};
