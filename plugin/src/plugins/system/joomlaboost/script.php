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
        $this->createTranslationsTable();
        $this->createJoomlaBoostCustomFields();
        return true;
    }

    /**
     * Run on plugin update
     */
    public function update($adapter)
    {
        // Ensure settings table exists (for upgrades from older versions)
        $this->createSettingsTable();
        $this->createTranslationsTable();

        // Migrate existing legacy language fields to database
        $this->migrateLegacyTranslations();

        // Migrate legacy day-by-day hours params → compact JSON widget (v0.26.0)
        $this->migrateBusinessHoursParams();

        // Create custom fields if not yet present
        $this->createJoomlaBoostCustomFields();

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
     * Auto-create JoomlaBoost custom fields for per-article OG overrides.
     *
     * Creates (if not existing):
     *   - Field group : "JoomlaBoost" (context: com_content.article)
     *   - custom_og_image       : Media field  — per-article OG image
     *   - custom_og_title       : Text field   — per-article OG title
     *   - custom_og_description : Textarea     — per-article OG description
     *
     * Idempotent: safe to call on every install/update.
     */
    private function createJoomlaBoostCustomFields(): void
    {
        try {
            $db  = Factory::getDbo();
            $now = (new \DateTime())->format('Y-m-d H:i:s');

            // ── 1. Ensure field group exists ──────────────────────────────────
            $groupQuery = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__fields_groups'))
                ->where(
                    '(' . $db->quoteName('title') . ' = ' . $db->quote('JB OpenGraph') .
                    ' OR ' . $db->quoteName('title') . ' = ' . $db->quote('JoomlaBoost') . ')'
                )
                ->where($db->quoteName('context') . ' = ' . $db->quote('com_content.article'));

            $db->setQuery($groupQuery);
            $groupId = (int) $db->loadResult();

            if ($groupId) {
                // Rename old 'JoomlaBoost' group to 'JB OpenGraph' if needed
                $renameQuery = $db->getQuery(true)
                    ->update($db->quoteName('#__fields_groups'))
                    ->set($db->quoteName('title') . ' = ' . $db->quote('JB OpenGraph'))
                    ->where($db->quoteName('id') . ' = ' . $groupId)
                    ->where($db->quoteName('title') . ' = ' . $db->quote('JoomlaBoost'));
                $db->setQuery($renameQuery);
                $db->execute();
            } else {
                $group = (object) [
                    'title'       => 'JB OpenGraph',
                    'context'     => 'com_content.article',
                    'description' => '',
                    'note'        => 'Auto-created by JoomlaBoost plugin for per-article SEO overrides.',
                    'state'       => 1,
                    'access'      => 1,
                    'language'    => '*',
                    'params'      => '{}',
                    'ordering'    => 0,
                    'created'     => $now,
                    'created_by'  => 0,
                    'modified'    => $now,
                    'modified_by' => 0,
                ];
                $db->insertObject('#__fields_groups', $group);
                $groupId = (int) $db->insertid();
            }

            // ── 2. Field definitions ──────────────────────────────────────────
            $fieldsToCreate = [
                [
                    'name'        => 'custom_og_image',
                    'label'       => 'OG Image (JoomlaBoost)',
                    'type'        => 'media',
                    'description' => 'Per-article Open Graph image. Overrides plugin default. Recommended: JPEG/PNG, min 1200x630px.',
                    'note'        => '',
                ],
                [
                    'name'        => 'custom_og_title',
                    'label'       => 'OG Title (JoomlaBoost)',
                    'type'        => 'text',
                    'description' => 'Per-article Open Graph title. Overrides article title for social sharing.',
                    'note'        => '',
                ],
                [
                    'name'        => 'custom_og_description',
                    'label'       => 'OG Description (JoomlaBoost)',
                    'type'        => 'textarea',
                    'description' => 'Per-article Open Graph description. Overrides meta description for social sharing.',
                    'note'        => '',
                ],
            ];

            foreach ($fieldsToCreate as $index => $fieldDef) {
                // Check if field with this name already exists
                $existsQuery = $db->getQuery(true)
                    ->select($db->quoteName('id'))
                    ->from($db->quoteName('#__fields'))
                    ->where($db->quoteName('name') . ' = ' . $db->quote($fieldDef['name']))
                    ->where($db->quoteName('context') . ' = ' . $db->quote('com_content.article'));

                $db->setQuery($existsQuery);
                if ($db->loadResult()) {
                    continue; // Already exists — skip
                }

                $field = (object) [
                    'context'      => 'com_content.article',
                    'group_id'     => $groupId,
                    'title'        => $fieldDef['label'],
                    'name'         => $fieldDef['name'],
                    'label'        => $fieldDef['label'],
                    'default_value' => '',
                    'type'         => $fieldDef['type'],
                    'note'         => $fieldDef['note'],
                    'description'  => $fieldDef['description'],
                    'state'        => 1,
                    'access'       => 1,
                    'language'     => '*',
                    'params'       => '{"class":"","hint":"","show_on":"","display":"2","showlabel":"1","label_render_class":"","display_readonly":"2"}',
                    'fieldparams'  => '{}',
                    'required'     => 0,
                    'ordering'     => $index + 1,
                    'created_time' => $now,
                    'created_user_id' => 0,
                    'modified_time'   => $now,
                    'modified_by'     => 0,
                    'checked_out'     => 0,
                    'checked_out_time' => null,
                ];

                $db->insertObject('#__fields', $field);
            }
        } catch (\Throwable $e) {
            // Non-fatal — log and continue (don't break installation)
            try {
                Factory::getApplication()->enqueueMessage(
                    'JoomlaBoost: Could not auto-create custom fields: ' . $e->getMessage(),
                    'warning'
                );
            } catch (\Throwable $ignored) {
            }
        }
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

    /**
     * Create translations table (v0.6.0+)
     */
    private function createTranslationsTable()
    {
        $db = Factory::getDbo();

        // Check if table already exists
        $tables = $db->getTableList();
        $tablePrefix = $db->getPrefix();
        $tableName = $tablePrefix . 'joomlaboost_translations';

        if (in_array($tableName, $tables)) {
            return true; // Table already exists
        }

        // Create table
        $query = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `field_key` VARCHAR(100) NOT NULL COMMENT 'Field identifier',
            `lang_code` VARCHAR(10) NOT NULL COMMENT 'ISO language code',
            `field_value` TEXT NOT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_field_lang` (`field_key`, `lang_code`),
            KEY `idx_field_key` (`field_key`),
            KEY `idx_lang_code` (`lang_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
          COMMENT='Multi-language translations for JoomlaBoost Schema.org fields';";

        try {
            $db->setQuery($query);
            $db->execute();
            return true;
        } catch (Exception $e) {
            // Log error but don't fail installation
            Factory::getApplication()->enqueueMessage(
                'JoomlaBoost: Could not create translations table: ' . $e->getMessage(),
                'warning'
            );
            return false;
        }
    }

    /**
     * Migrate legacy day-by-day Business Hours params to compact JSON widget (v0.26.0).
     *
     * Reads schema_hours_{day}_{field} params and converts them to the new
     * schema_business_hours JSON format consumed by BusinessHoursField.
     * Safe to run multiple times — skips if schema_business_hours already set.
     */
    private function migrateBusinessHoursParams(): void
    {
        try {
            $db = Factory::getDbo();

            $query = $db->getQuery(true)
                ->select(['extension_id', 'params'])
                ->from('#__extensions')
                ->where($db->quoteName('element') . ' = ' . $db->quote('joomlaboost'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('system'));

            $db->setQuery($query);
            $row = $db->loadObject();

            if (!$row || !$row->extension_id) {
                return;
            }

            $params = json_decode($row->params ?? '{}', true);
            if (!is_array($params)) {
                return;
            }

            // Already migrated — skip
            if (!empty($params['schema_business_hours']) && $params['schema_business_hours'] !== '{}') {
                return;
            }

            $days = [
                'mon' => ['defaultOpen' => '09:00', 'defaultClose' => '17:00', 'defaultClosed' => false],
                'tue' => ['defaultOpen' => '09:00', 'defaultClose' => '17:00', 'defaultClosed' => false],
                'wed' => ['defaultOpen' => '09:00', 'defaultClose' => '17:00', 'defaultClosed' => false],
                'thu' => ['defaultOpen' => '09:00', 'defaultClose' => '17:00', 'defaultClosed' => false],
                'fri' => ['defaultOpen' => '09:00', 'defaultClose' => '17:00', 'defaultClosed' => false],
                'sat' => ['defaultOpen' => '09:00', 'defaultClose' => '13:00', 'defaultClosed' => true],
                'sun' => ['defaultOpen' => '10:00', 'defaultClose' => '14:00', 'defaultClosed' => true],
            ];

            $hasLegacyData = false;
            $schedule      = [];

            foreach ($days as $abbr => $def) {
                $closedKey = 'schema_hours_' . $abbr . '_closed';
                $openKey   = 'schema_hours_' . $abbr . '_open';
                $closeKey  = 'schema_hours_' . $abbr . '_close';
                $open2Key  = 'schema_hours_' . $abbr . '_open2';
                $close2Key = 'schema_hours_' . $abbr . '_close2';

                if (array_key_exists($closedKey, $params) || array_key_exists($openKey, $params)) {
                    $hasLegacyData = true;
                }

                $isClosed = (bool) ($params[$closedKey] ?? $def['defaultClosed']);
                $schedule[$abbr] = [
                    'open'   => (string) ($params[$openKey]   ?? $def['defaultOpen']),
                    'close'  => (string) ($params[$closeKey]  ?? $def['defaultClose']),
                    'open2'  => (string) ($params[$open2Key]  ?? ''),
                    'close2' => (string) ($params[$close2Key] ?? ''),
                    'closed' => $isClosed,
                ];
            }

            if (!$hasLegacyData) {
                return;
            }

            $params['schema_business_hours'] = json_encode($schedule, JSON_UNESCAPED_UNICODE);

            $updateQuery = $db->getQuery(true)
                ->update('#__extensions')
                ->set($db->quoteName('params') . ' = ' . $db->quote(json_encode($params)))
                ->where($db->quoteName('extension_id') . ' = ' . (int) $row->extension_id);

            $db->setQuery($updateQuery);
            $db->execute();

            Factory::getApplication()->enqueueMessage(
                '✅ AI Boost: Business hours migrated to compact widget format (v0.26.0).',
                'success'
            );
        } catch (\Throwable $e) {
            // Silent fail — don't break update
        }
    }

    /**
     * Migrate legacy v0.5.8 language fields to database
     */
    private function migrateLegacyTranslations()
    {
        try {
            $db = Factory::getDbo();

            // Get current plugin params
            $query = $db->getQuery(true)
                ->select($db->quoteName('params'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('joomlaboost'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('system'));

            $db->setQuery($query);
            $paramsJson = $db->loadResult();

            if (empty($paramsJson)) {
                return;
            }

            $params = json_decode($paramsJson, true);
            if (!is_array($params)) {
                return;
            }

            $now = Factory::getDate()->toSql();
            $migrated = 0;

            // Define field mappings: field_key => [language-specific param names]
            $migrations = [
                'org_name' => ['org_name_en', 'org_name_sr', 'org_name_ru'],
                'schema_description' => ['schema_description_en', 'schema_description_sr', 'schema_description_ru'],
                'manual_faqs' => ['manual_faqs_en', 'manual_faqs_sr', 'manual_faqs_ru']
            ];

            foreach ($migrations as $fieldKey => $langFields) {
                foreach ($langFields as $langField) {
                    if (!empty($params[$langField])) {
                        // Extract language code from field name (e.g., 'org_name_en' → 'en')
                        $langCode = substr($langField, -2);

                        // Insert into translations table
                        $query = $db->getQuery(true)
                            ->insert($db->quoteName('#__joomlaboost_translations'))
                            ->columns([
                                $db->quoteName('field_key'),
                                $db->quoteName('lang_code'),
                                $db->quoteName('field_value'),
                                $db->quoteName('created_at'),
                                $db->quoteName('updated_at')
                            ])
                            ->values(
                                $db->quote($fieldKey) . ', ' .
                                    $db->quote($langCode) . ', ' .
                                    $db->quote($params[$langField]) . ', ' .
                                    $db->quote($now) . ', ' .
                                    $db->quote($now)
                            );

                        $db->setQuery($query);
                        try {
                            $db->execute();
                            $migrated++;
                        } catch (Exception $e) {
                            // Skip duplicates or errors
                        }
                    }
                }
            }

            if ($migrated > 0) {
                Factory::getApplication()->enqueueMessage(
                    "✅ JoomlaBoost: Migrated {$migrated} language-specific fields to new database system!",
                    'success'
                );
            }
        } catch (Exception $e) {
            // Silent fail - don't break update
        }
    }

    public function postflight($type, $adapter)
    {
        // Auto-restore settings on install OR update (in case params were lost/reset)
        if ($type === 'install' || $type === 'update') {
            $this->restorePersistedSettings($type);
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
                    <strong>Version ' . $this->getPluginVersion() . '</strong> | Joomla 4.0+ / 5.0+ / 6.0+ | PHP 8.1+ | Built with ❤️ by JoomlaBoost Team
                </div>
            </div>
            ';
        }

        // =====================================================================
        // FORCE OPCACHE RECOMPILATION
        // =====================================================================
        // LiteSpeed PHP (and PHP-FPM with validate_timestamps=0) does NOT
        // automatically pick up changed PHP files from disk. We must explicitly
        // invalidate the OPcache entries so the next request uses the new code.
        $pluginDir = JPATH_PLUGINS . '/system/joomlaboost';
        $filesToInvalidate = [
            $pluginDir . '/joomlaboost.php',
            $pluginDir . '/script.php',
            $pluginDir . '/src/Services/RobotService.php',
            $pluginDir . '/src/Enums/EnvironmentType.php',
            $pluginDir . '/src/Services/AbstractService.php',
            $pluginDir . '/src/Services/LlmsTxtService.php',
        ];

        if (function_exists('opcache_invalidate')) {
            foreach ($filesToInvalidate as $file) {
                if (file_exists($file)) {
                    opcache_invalidate($file, true); // true = force immediate recompile
                }
            }
        }

        // Full reset as fallback (works on single-process LSPHP)
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        // =====================================================================
        // FORCE ROBOTS.TXT REGENERATION
        // =====================================================================
        // Delete the hash file so the next frontend request rewrites robots.txt
        // using the NEW code (AI crawlers from RobotService + EnvironmentType).
        $hashFile = JPATH_ROOT . '/.robots_hash';
        if (file_exists($hashFile)) {
            @unlink($hashFile);
        }

        return true;
    }

    /**
     * Restore persisted settings on install
     */
    private function restorePersistedSettings(string $type = 'install'): void
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

            // Decode persisted settings
            $persisted = json_decode($settingsJson, true);
            if (!is_array($persisted) || empty($persisted)) {
                return;
            }

            // Get plugin ID and current params
            $query = $db->getQuery(true)
                ->select(['extension_id', 'params'])
                ->from('#__extensions')
                ->where($db->quoteName('element') . ' = ' . $db->quote('joomlaboost'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('system'));

            $db->setQuery($query);
            $row = $db->loadObject();

            if (!$row || !$row->extension_id) {
                return;
            }

            if ($type === 'update') {
                // On UPDATE: merge — only fill in missing/empty keys from persisted backup
                $current = json_decode($row->params ?? '{}', true) ?: [];
                $merged  = $persisted;
                foreach ($current as $k => $v) {
                    if ($v !== null && $v !== '' && $v !== [] && $v !== '{}') {
                        $merged[$k] = $v; // current wins over persisted
                    }
                }
                $finalParams = json_encode($merged);
            } else {
                // On INSTALL: full restore from backup
                $finalParams = json_encode($persisted);
            }

            // Write back to extensions table
            $query = $db->getQuery(true)
                ->update('#__extensions')
                ->set($db->quoteName('params') . ' = ' . $db->quote($finalParams))
                ->where($db->quoteName('extension_id') . ' = ' . (int) $row->extension_id);

            $db->setQuery($query);
            $db->execute();

            Factory::getApplication()->enqueueMessage(
                '✅ JoomlaBoost: Your previous settings have been restored!',
                'success'
            );
        } catch (Exception $e) {
            // Silent fail - don't break installation
        }
    }

    /**
     * Get plugin version dynamically from XML manifest
     * This ensures the version shown on install screen always matches the built version.
     */
    private function getPluginVersion(): string
    {
        $xmlPath = __DIR__ . '/joomlaboost.xml';
        if (file_exists($xmlPath)) {
            $xml = simplexml_load_file($xmlPath);
            if ($xml && isset($xml->version)) {
                return (string) $xml->version;
            }
        }
        return 'unknown';
    }
}
