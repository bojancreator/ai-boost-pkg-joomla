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
        'custom_og_image',
        'custom_og_title',
        'custom_og_description',
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

        if ($type === 'install') {
            // Fresh installation
            $app->enqueueMessage(
                '✅ JoomlaBoost plugin successfully installed!',
                'success'
            );

            // Auto-create custom fields if they don't exist
            if (!$this->checkCustomFieldsExist()) {
                $app->enqueueMessage(
                    '🔧 Creating custom fields for per-article OpenGraph overrides...',
                    'info'
                );
                
                $result = $this->createCustomFields();
                
                if ($result['success']) {
                    $app->enqueueMessage($result['message'], 'success');
                } else {
                    $app->enqueueMessage($result['message'], 'warning');
                }
            } else {
                $app->enqueueMessage(
                    '✅ Custom fields already exist - ready to use!',
                    'info'
                );
            }
        } elseif ($type === 'update') {
            // Update from previous version
            $app->enqueueMessage(
                '✅ JoomlaBoost plugin successfully updated to version ' . $adapter->getManifest()->version . '!',
                'success'
            );
            
            // Also check/create fields on update
            if (!$this->checkCustomFieldsExist()) {
                $app->enqueueMessage(
                    '🔧 Creating custom fields for per-article OpenGraph overrides...',
                    'info'
                );
                
                $result = $this->createCustomFields();
                
                if ($result['success']) {
                    $app->enqueueMessage($result['message'], 'success');
                } else {
                    $app->enqueueMessage($result['message'], 'warning');
                }
            }
        }

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
            // Load CustomFieldsService
            require_once __DIR__ . '/src/Services/CustomFieldsService.php';
            
            $customFieldsService = new \CustomFieldsService();
            return $customFieldsService->setupCustomFields();
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create custom fields: ' . $e->getMessage()
            ];
        }
    }
};
