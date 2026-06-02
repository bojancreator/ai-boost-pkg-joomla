<?php
/**
 * AI Boost — ProGate Trait
 *
 * Adds Pro feature gating to any AI Boost plugin. Include this trait in a
 * CMSPlugin subclass that stores params in $this->params (Joomla standard).
 *
 * Usage:
 *   class AiBoostAeo extends CMSPlugin
 *   {
 *       use \AiBoost\Lib\ProGate;
 *
 *       public function onBeforeCompileHead(): void
 *       {
 *           if (!$this->isProEnabled()) {
 *               return; // skip Pro-only feature
 *           }
 *           // ... Pro logic ...
 *       }
 *   }
 *
 * @package     AiBoost\Lib
 * @version     0.7.0
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib;

defined('_JEXEC') or die;

if (!trait_exists('AiBoost\\Lib\\ProGate', false)) :

trait ProGate
{
    /**
     * Return true if this plugin's stored license tier is 'pro'.
     *
     * Reads `license_tier` from $this->params (Joomla plugin params).
     * Default (no key entered) is 'free'.
     */
    public function isProEnabled(): bool
    {
        return trim((string) $this->params->get('license_tier', 'free')) === 'pro';
    }

    /**
     * Validate a raw license key via Lemon Squeezy API and store the resolved
     * tier back into the plugin's params row in the database.
     *
     * Call this from an onExtensionAfterSave event handler or a dedicated
     * AJAX endpoint so the tier is persisted immediately after the user saves
     * their key in the plugin settings panel.
     *
     * @param  string $licenseKey  Raw key value from the params field.
     * @return string              Resolved tier: 'pro' or 'free'.
     */
    public function validateAndStoreLicense(string $licenseKey): string
    {
        // Dynamically load the LicenseValidator class if the shared lib autoloader
        // is not already registered. Component-based plugins load it via autoload.php;
        // standalone plugins include the class directly.
        if (!class_exists(LicenseValidator::class)) {
            $sharedLib = JPATH_ADMINISTRATOR . '/components/com_aiboost/lib/src/LicenseValidator.php';
            if (file_exists($sharedLib)) {
                require_once $sharedLib;
            } else {
                return 'free';
            }
        }

        $tier = LicenseValidator::validate($licenseKey);
        $this->storeLicenseTier($tier);
        return $tier;
    }

    /**
     * Persist the resolved tier to the plugin's `params` column in the
     * `#__extensions` database table.
     *
     * Uses $this->db which is injected by Joomla DI into CMSPlugin.
     *
     * @param  string $tier 'pro' or 'free'
     */
    private function storeLicenseTier(string $tier): void
    {
        try {
            $db = $this->db;

            // Read current params JSON
            $query = $db->getQuery(true)
                ->select($db->quoteName('params'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('element') . ' = ' . $db->quote($this->_name))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('system'));
            $db->setQuery($query, 0, 1);
            $paramsJson = (string) ($db->loadResult() ?? '{}');

            $params = json_decode($paramsJson, true) ?: [];
            $params['license_tier']   = $tier;
            $params['license_status'] = $tier === 'pro' ? 'Professional' : 'Free';

            $update = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('params') . ' = ' . $db->quote(json_encode($params)))
                ->where($db->quoteName('element') . ' = ' . $db->quote($this->_name))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('system'));
            $db->setQuery($update);
            $db->execute();
        } catch (\Throwable $e) {
            error_log('[AI Boost ProGate] Failed to store license tier: ' . $e->getMessage());
        }
    }

    /**
     * Ensure the ConflictManager class is available.
     * Loads it from the shared component lib if not already in memory.
     *
     * @return bool True if ConflictManager can be used.
     */
    public function ensureConflictManager(): bool
    {
        if (class_exists(\AiBoost\Lib\ConflictManager::class, false)) {
            return true;
        }
        $file = JPATH_ADMINISTRATOR . '/components/com_aiboost/lib/src/ConflictManager.php';
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
        return false;
    }

    /**
     * Joomla save event — validates and stores the license tier whenever this
     * plugin's configuration is saved in the Joomla admin panel.
     *
     * Fires automatically for any plugin that uses this trait, provided the
     * plugin is registered as a Joomla event subscriber (standard for CMSPlugin).
     *
     * @param  string $context  Context string; must be 'com_plugins.plugin'.
     * @param  mixed  $table    The saved #__extensions table row.
     * @param  bool   $isNew    True on first-ever save.
     */
    public function onExtensionAfterSave(string $context, $table, bool $isNew): void
    {
        if ($context !== 'com_plugins.plugin') {
            return;
        }

        // Filter to this plugin's own row only
        $element = $table->element ?? '';
        $folder  = $table->folder  ?? '';
        if ($folder !== 'system' || $element !== $this->_name) {
            return;
        }

        // Parse the newly-saved params JSON
        $params = json_decode((string) ($table->params ?? '{}'), true) ?: [];
        if (!array_key_exists('license_key', $params)) {
            return; // Not a license-aware plugin row
        }

        // Ensure LicenseValidator is available (component lib)
        $lv = JPATH_ADMINISTRATOR . '/components/com_aiboost/lib/src/LicenseValidator.php';
        if (!class_exists(LicenseValidator::class, false) && file_exists($lv)) {
            require_once $lv;
        }

        $key = trim((string) ($params['license_key'] ?? ''));
        $this->validateAndStoreLicense($key);
    }
}

endif;
