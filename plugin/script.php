<?php

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class plgSystemJoomlaboostInstallerScript
{
    /**
     * Get plugin version from XML manifest (dynamic)
     * @return string Plugin version (e.g., "0.2.14")
     */
    private function getPluginVersion(): string
    {
        static $version = null;

        if ($version === null) {
            // Try multiple paths (works in both build/ and staging deployment)
            $paths = [
                __DIR__ . '/joomlaboost.xml',
                JPATH_PLUGINS . '/system/joomlaboost/joomlaboost.xml',
                dirname(__DIR__) . '/joomlaboost.xml'
            ];

            foreach ($paths as $xmlPath) {
                if (file_exists($xmlPath)) {
                    $xmlContent = file_get_contents($xmlPath);
                    if (preg_match('/<version>([^<]+)<\/version>/', $xmlContent, $matches)) {
                        $version = $matches[1];
                        break;
                    }
                }
            }

            if ($version === null) {
                $version = 'unknown';
            }
        }

        return $version;
    }

    public function postflight($type, $adapter)
    {
        $log = JPATH_ROOT . '/joomlaboost_install.log';

        $version = $this->getPluginVersion();

        file_put_contents($log, "\n" . str_repeat('=', 60) . "\n", FILE_APPEND);
        file_put_contents($log, date('Y-m-d H:i:s') . " - JoomlaBoost v{$version} POSTFLIGHT START\n", FILE_APPEND);

        // Check if custom fields exist
        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__fields'))
                ->where($db->quoteName('name') . ' IN (' .
                    $db->quote('custom_og_image') . ',' .
                    $db->quote('custom_og_title') . ',' .
                    $db->quote('custom_og_description') . ')');

            $db->setQuery($query);
            $count = (int) $db->loadResult();

            file_put_contents($log, date('Y-m-d H:i:s') . " - Found $count/3 custom fields\n", FILE_APPEND);

            if ($count === 3) {
                file_put_contents($log, date('Y-m-d H:i:s') . " - ✅ All fields exist!\n", FILE_APPEND);
            } else {
                file_put_contents($log, date('Y-m-d H:i:s') . " - ⚠️ Fields missing - creating...\n", FILE_APPEND);

                // Create field group using direct SQL
                $groupId = $this->createFieldGroup($db, $log);

                if ($groupId) {
                    file_put_contents($log, date('Y-m-d H:i:s') . " - ✅ Field group created (ID: $groupId)\n", FILE_APPEND);

                    // Create custom_og_image field
                    $fieldId1 = $this->createField($db, $log, $groupId, 'custom_og_image', 'media', 'Custom OG Image');
                    if ($fieldId1) {
                        file_put_contents($log, date('Y-m-d H:i:s') . " - ✅ Field 'custom_og_image' created (ID: $fieldId1)\n", FILE_APPEND);
                    } else {
                        file_put_contents($log, date('Y-m-d H:i:s') . " - ❌ Failed to create 'custom_og_image'\n", FILE_APPEND);
                    }

                    // Create custom_og_title field
                    $fieldId2 = $this->createField($db, $log, $groupId, 'custom_og_title', 'text', 'Custom OG Title');
                    if ($fieldId2) {
                        file_put_contents($log, date('Y-m-d H:i:s') . " - ✅ Field 'custom_og_title' created (ID: $fieldId2)\n", FILE_APPEND);
                    } else {
                        file_put_contents($log, date('Y-m-d H:i:s') . " - ❌ Failed to create 'custom_og_title'\n", FILE_APPEND);
                    }

                    // Create custom_og_description field
                    $fieldId3 = $this->createField($db, $log, $groupId, 'custom_og_description', 'textarea', 'Custom OG Description');
                    if ($fieldId3) {
                        file_put_contents($log, date('Y-m-d H:i:s') . " - ✅ Field 'custom_og_description' created (ID: $fieldId3)\n", FILE_APPEND);
                    } else {
                        file_put_contents($log, date('Y-m-d H:i:s') . " - ❌ Failed to create 'custom_og_description'\n", FILE_APPEND);
                    }
                } else {
                    file_put_contents($log, date('Y-m-d H:i:s') . " - ❌ Failed to create field group\n", FILE_APPEND);
                }
            }

            // Update existing fields to prevent frontend rendering (PHP 8.1+ deprecation fix)
            $this->updateFieldsDisplayParam($db, $log);

            // Fix NULL values in database (prevents json_decode(null) in backend)
            $this->fixNullFieldValues($db, $log);

            // Create database trigger for automatic population of new articles
            $this->createFieldValuesTrigger($db, $log);
        } catch (\Exception $e) {
            file_put_contents($log, date('Y-m-d H:i:s') . " - ❌ ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
            file_put_contents($log, date('Y-m-d H:i:s') . " - File: " . $e->getFile() . ":" . $e->getLine() . "\n", FILE_APPEND);
        }

        file_put_contents($log, date('Y-m-d H:i:s') . " - JoomlaBoost v{$version} POSTFLIGHT END\n", FILE_APPEND);
        file_put_contents($log, str_repeat('=', 60) . "\n", FILE_APPEND);

        return true;
    }

    private function createFieldGroup($db, $log)
    {
        try {
            // Check if group already exists
            $query = $db->getQuery(true)
                ->select('id')
                ->from($db->quoteName('#__fields_groups'))
                ->where($db->quoteName('title') . ' = ' . $db->quote('JoomlaBoost SEO'));

            $db->setQuery($query);
            $existingId = $db->loadResult();

            if ($existingId) {
                file_put_contents($log, date('Y-m-d H:i:s') . " - Field group already exists (ID: $existingId)\n", FILE_APPEND);
                return $existingId;
            }

            file_put_contents($log, date('Y-m-d H:i:s') . " - Creating new field group...\n", FILE_APPEND);

            // Direct INSERT instead of JTable
            $now = date('Y-m-d H:i:s');

            $columns = [
                'title',
                'context',
                'state',
                'access',
                'language',
                'description',
                'params',
                'created',
                'created_by',
                'modified',
                'modified_by'
            ];

            $values = [
                $db->quote('JoomlaBoost SEO'),
                $db->quote('com_content.article'),
                1,
                1,
                $db->quote('*'),
                $db->quote('Custom OpenGraph fields for per-article overrides'),
                $db->quote('{}'),
                $db->quote($now),
                0,
                $db->quote($now),
                0
            ];

            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__fields_groups'))
                ->columns($db->quoteName($columns))
                ->values(implode(',', $values));

            $db->setQuery($query);
            $db->execute();

            $groupId = $db->insertid();
            file_put_contents($log, date('Y-m-d H:i:s') . " - INSERT successful, ID: $groupId\n", FILE_APPEND);

            return $groupId;
        } catch (\Exception $e) {
            file_put_contents($log, date('Y-m-d H:i:s') . " - createFieldGroup ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }

    private function createField($db, $log, $groupId, $name, $type, $label)
    {
        try {
            // Check if field already exists
            $query = $db->getQuery(true)
                ->select('id')
                ->from($db->quoteName('#__fields'))
                ->where($db->quoteName('name') . ' = ' . $db->quote($name));

            $db->setQuery($query);
            $existingId = $db->loadResult();

            if ($existingId) {
                file_put_contents($log, date('Y-m-d H:i:s') . " - Field '$name' already exists (ID: $existingId)\n", FILE_APPEND);
                return $existingId;
            }

            file_put_contents($log, date('Y-m-d H:i:s') . " - Creating field '$name'...\n", FILE_APPEND);

            $columns = [
                'context',
                'group_id',
                'title',
                'name',
                'label',
                'type',
                'default_value',
                'state',
                'access',
                'language',
                'description',
                'created_time',
                'modified_time',
                'params',
                'fieldparams'
            ];

            $now = date('Y-m-d H:i:s');

            // CRITICAL: These fields must NOT auto-render to prevent PHP 8.1+ json_decode(null) error
            // display=0 hides from frontend, render_class empty prevents automatic rendering
            $params = json_encode([
                'display' => '0',
                'render_class' => '',
                'class' => '',
                'showlabel' => '0',
                'disabled' => '0',
                'readonly' => '0'
            ]);

            // Fieldparams: Set proper default based on field type
            // This prevents json_decode(null) deprecation in PHP 8.1+
            $fieldparams = '{}';
            $defaultValue = '';

            if ($type === 'media') {
                // Media field expects JSON value, provide empty JSON structure
                $fieldparams = json_encode([
                    'image_class' => '',
                    'image_link' => '',
                    'imagefile' => ''  // Empty image file path as default
                ]);
                // CRITICAL: Default Value for media must be valid JSON!
                $defaultValue = '{"imagefile":""}';
            } elseif ($type === 'text' || $type === 'textarea') {
                // Text fields can have simple default value
                $fieldparams = json_encode([
                    'filter' => '',
                    'maxlength' => '',
                    'placeholder' => ''
                ]);
                // Default is empty string for text fields
                $defaultValue = '';
            }

            $values = [
                $db->quote('com_content.article'),
                (int) $groupId,
                $db->quote($label),
                $db->quote($name),
                $db->quote($label),
                $db->quote($type),
                $db->quote($defaultValue),  // CRITICAL: Default value shown in editor for new articles
                1,  // state: 1=published (needed for backend editing and visibility)
                3,  // access: 3=Special (prevents frontend loading for Guest users, avoids PHP 8.1+ json_decode(null) deprecation)
                $db->quote('*'),
                $db->quote(''),
                $db->quote($now),
                $db->quote($now),
                $db->quote($params),
                $db->quote($fieldparams)
            ];

            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__fields'))
                ->columns($db->quoteName($columns))
                ->values(implode(',', $values));

            $db->setQuery($query);
            $db->execute();

            return $db->insertid();
        } catch (\Exception $e) {
            file_put_contents($log, date('Y-m-d H:i:s') . " - createField ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }

    /**
     * Update existing fields and insert empty values for articles without custom fields
     * Prevents PHP 8.1+ json_decode(null) deprecation
     */
    private function updateFieldsDisplayParam($db, $log)
    {
        try {
            file_put_contents($log, date('Y-m-d H:i:s') . " - Fixing custom fields for PHP 8.1+...\n", FILE_APPEND);

            $fieldNames = ['custom_og_image', 'custom_og_title', 'custom_og_description'];
            $fieldIds = [];
            $updated = 0;

            // Step 1: Update params, fieldparams AND default_value for existing fields (preserving data!)
            foreach ($fieldNames as $fieldName) {
                $query = $db->getQuery(true)
                    ->select('id, type, params, fieldparams, default_value')
                    ->from($db->quoteName('#__fields'))
                    ->where($db->quoteName('name') . ' = ' . $db->quote($fieldName))
                    ->where($db->quoteName('context') . ' = ' . $db->quote('com_content.article'));

                $db->setQuery($query);
                $field = $db->loadObject();

                if (!$field) {
                    continue;
                }

                $fieldIds[$fieldName] = $field->id;

                // Update params to prevent auto-rendering
                $params = json_encode([
                    'display' => '0',
                    'render_class' => '',
                    'class' => '',
                    'showlabel' => '0',
                    'disabled' => '0',
                    'readonly' => '0'
                ]);

                // Update fieldparams based on field type
                $fieldparams = '{}';
                $defaultValue = '';

                if ($field->type === 'media') {
                    $fieldparams = json_encode([
                        'image_class' => '',
                        'image_link' => '',
                        'imagefile' => ''
                    ]);
                    // CRITICAL: Default value for media must be valid JSON
                    $defaultValue = '{"imagefile":""}';
                } elseif ($field->type === 'text' || $field->type === 'textarea') {
                    $fieldparams = json_encode([
                        'filter' => '',
                        'maxlength' => '',
                        'placeholder' => ''
                    ]);
                    // Empty string for text fields
                    $defaultValue = '';
                }

                // Update field to prevent frontend loading (Access Level = Special)
                $updateQuery = $db->getQuery(true)
                    ->update($db->quoteName('#__fields'))
                    ->set($db->quoteName('params') . ' = ' . $db->quote($params))
                    ->set($db->quoteName('fieldparams') . ' = ' . $db->quote($fieldparams))
                    ->set($db->quoteName('state') . ' = 1')  // Keep published for admin editing
                    ->set($db->quoteName('access') . ' = 3')  // Special - prevents Guest loading
                    ->where('id = ' . (int) $field->id);

                // CRITICAL: Only update default_value if it's currently empty/null
                // This preserves any manually set default values on plugin reinstall!
                if (empty($field->default_value)) {
                    $updateQuery->set($db->quoteName('default_value') . ' = ' . $db->quote($defaultValue));
                }

                $db->setQuery($updateQuery);
                $db->execute();
                $updated++;
            }

            file_put_contents($log, date('Y-m-d H:i:s') . " - Updated $updated field params\n", FILE_APPEND);

            // Step 2: Insert empty values for articles without field values (prevents NULL)
            if (empty($fieldIds)) {
                return;
            }

            $query = $db->getQuery(true)
                ->select('id')
                ->from($db->quoteName('#__content'))
                ->where($db->quoteName('state') . ' >= 0');

            $db->setQuery($query);
            $articleIds = $db->loadColumn();

            $inserted = 0;
            $updated = 0;
            foreach ($articleIds as $articleId) {
                foreach ($fieldIds as $fieldName => $fieldId) {
                    // Check if value exists and get current value
                    $checkQuery = $db->getQuery(true)
                        ->select('value')
                        ->from($db->quoteName('#__fields_values'))
                        ->where($db->quoteName('field_id') . ' = ' . (int) $fieldId)
                        ->where($db->quoteName('item_id') . ' = ' . (int) $articleId);

                    $db->setQuery($checkQuery);
                    $currentValue = $db->loadResult();

                    // Determine default value based on field type
                    $defaultValue = '';
                    if ($fieldName === 'custom_og_image') {
                        // For media fields, use JSON structure to prevent json_decode(null) in PHP 8.1+
                        $defaultValue = json_encode(['imagefile' => '']);
                    }

                    // Handle NULL or empty values
                    if ($currentValue === false) {
                        // Record doesn't exist - INSERT
                        $insertQuery = $db->getQuery(true)
                            ->insert($db->quoteName('#__fields_values'))
                            ->columns($db->quoteName(['field_id', 'item_id', 'value']))
                            ->values((int) $fieldId . ',' . (int) $articleId . ',' . $db->quote($defaultValue));

                        $db->setQuery($insertQuery);
                        try {
                            $db->execute();
                            $inserted++;
                        } catch (\Exception $e) {
                            // Ignore duplicate key errors
                            if (strpos($e->getMessage(), 'Duplicate') === false) {
                                file_put_contents($log, date('Y-m-d H:i:s') . " - Insert error: " . $e->getMessage() . "\n", FILE_APPEND);
                            }
                        }
                    } elseif ($currentValue === null || ($fieldName === 'custom_og_image' && empty($currentValue))) {
                        // Record exists but value is NULL or empty (for media fields) - UPDATE
                        $updateQuery = $db->getQuery(true)
                            ->update($db->quoteName('#__fields_values'))
                            ->set($db->quoteName('value') . ' = ' . $db->quote($defaultValue))
                            ->where($db->quoteName('field_id') . ' = ' . (int) $fieldId)
                            ->where($db->quoteName('item_id') . ' = ' . (int) $articleId);

                        $db->setQuery($updateQuery);
                        try {
                            $db->execute();
                            $updated++;
                        } catch (\Exception $e) {
                            file_put_contents($log, date('Y-m-d H:i:s') . " - Update error: " . $e->getMessage() . "\n", FILE_APPEND);
                        }
                    }
                }
            }

            file_put_contents($log, date('Y-m-d H:i:s') . " - Inserted $inserted, Updated $updated field values\n", FILE_APPEND);
        } catch (\Exception $e) {
            file_put_contents($log, date('Y-m-d H:i:s') . " - updateFieldsDisplayParam ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }

    /**
     * Fix NULL values in #__fields_values for ALL custom OG fields
     * Prevents DOMCdataSection(null) and json_decode(null) deprecation in PHP 8.1+
     *
     * @param DatabaseInterface $db Database object
     * @param string $log Log file path
     * @return void
     */
    private function fixNullFieldValues($db, $log)
    {
        try {
            file_put_contents($log, date('Y-m-d H:i:s') . " - Fixing NULL field values for ALL custom OG fields...\n", FILE_APPEND);

            $fieldsToFix = [
                'custom_og_image' => '{"imagefile":""}',  // JSON for media field
                'custom_og_title' => '',                   // Empty string for text field
                'custom_og_description' => ''              // Empty string for textarea field
            ];

            $totalUpdated = 0;
            $totalInserted = 0;

            foreach ($fieldsToFix as $fieldName => $defaultValue) {
                // Step 1: UPDATE existing NULL/empty values
                $updateSql = "UPDATE " . $db->quoteName('#__fields_values') . " v
                    JOIN " . $db->quoteName('#__fields') . " f ON f.id = v.field_id
                    SET v.value = " . $db->quote($defaultValue) . "
                    WHERE f.name = " . $db->quote($fieldName) . "
                    AND (v.value IS NULL OR v.value = '')";

                $db->setQuery($updateSql);
                $db->execute();
                $affectedUpdate = $db->getAffectedRows();
                $totalUpdated += $affectedUpdate;

                file_put_contents($log, date('Y-m-d H:i:s') . " - Updated $affectedUpdate NULL values for $fieldName\n", FILE_APPEND);

                // Step 2: INSERT missing values for articles without field entry
                $insertSql = "INSERT INTO " . $db->quoteName('#__fields_values') . " (field_id, item_id, value)
                    SELECT f.id, c.id, " . $db->quote($defaultValue) . "
                    FROM " . $db->quoteName('#__content') . " c
                    JOIN " . $db->quoteName('#__fields') . " f ON f.name = " . $db->quote($fieldName) . "
                    LEFT JOIN " . $db->quoteName('#__fields_values') . " v ON v.field_id = f.id AND v.item_id = c.id
                    WHERE v.item_id IS NULL";

                $db->setQuery($insertSql);
                $db->execute();
                $affectedInsert = $db->getAffectedRows();
                $totalInserted += $affectedInsert;

                file_put_contents($log, date('Y-m-d H:i:s') . " - Inserted $affectedInsert missing values for $fieldName\n", FILE_APPEND);
            }

            file_put_contents($log, date('Y-m-d H:i:s') . " - ✅ NULL fix complete: $totalUpdated updated, $totalInserted inserted across all fields\n", FILE_APPEND);
        } catch (\Exception $e) {
            file_put_contents($log, date('Y-m-d H:i:s') . " - fixNullFieldValues ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }

    /**
     * Create database trigger to automatically populate custom OG fields with default values
     * This ensures NEW articles always have non-NULL values, preventing PHP 8.1+ deprecation errors
     *
     * @param DatabaseInterface $db Database object
     * @param string $log Log file path
     * @return void
     */
    private function createFieldValuesTrigger($db, $log)
    {
        try {
            file_put_contents($log, date('Y-m-d H:i:s') . " - Creating database trigger for auto-population...\n", FILE_APPEND);

            // Drop trigger if exists (for reinstall scenarios)
            $dropTrigger = "DROP TRIGGER IF EXISTS " . $db->quoteName('trg_joomlaboost_autopop_fields');
            $db->setQuery($dropTrigger);
            $db->execute();

            // Get field IDs for our custom fields
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'name', 'type']))
                ->from($db->quoteName('#__fields'))
                ->where($db->quoteName('name') . ' IN (' .
                    $db->quote('custom_og_image') . ',' .
                    $db->quote('custom_og_title') . ',' .
                    $db->quote('custom_og_description') .
                    ')');

            $db->setQuery($query);
            $fields = $db->loadObjectList();

            if (empty($fields)) {
                file_put_contents($log, date('Y-m-d H:i:s') . " - No custom fields found, skipping trigger creation\n", FILE_APPEND);
                return;
            }

            // Build INSERT statements for trigger
            $insertStatements = [];
            foreach ($fields as $field) {
                $defaultValue = ($field->type === 'media') ? '{"imagefile":""}' : '';

                $insertStatements[] =
                    "INSERT INTO " . $db->quoteName('#__fields_values') . " " .
                    "(" . $db->quoteName('field_id') . ", " .
                    $db->quoteName('item_id') . ", " .
                    $db->quoteName('value') . ") " .
                    "VALUES (" . (int)$field->id . ", NEW.id, " . $db->quote($defaultValue) . ") " .
                    "ON DUPLICATE KEY UPDATE " .
                    $db->quoteName('value') . " = IF(" .
                    $db->quoteName('value') . " IS NULL OR " .
                    $db->quoteName('value') . " = '', " .
                    $db->quote($defaultValue) . ", " .
                    $db->quoteName('value') . ")";
            }

            // Create trigger that fires AFTER INSERT on #__content table
            $createTrigger = "
                CREATE TRIGGER " . $db->quoteName('trg_joomlaboost_autopop_fields') . "
                AFTER INSERT ON " . $db->quoteName('#__content') . "
                FOR EACH ROW
                BEGIN
                    " . implode(";\n                    ", $insertStatements) . ";
                END
            ";

            $db->setQuery($createTrigger);
            $db->execute();

            file_put_contents($log, date('Y-m-d H:i:s') . " - ✅ Database trigger created successfully\n", FILE_APPEND);
            file_put_contents($log, date('Y-m-d H:i:s') . " - Trigger will auto-populate " . count($fields) . " custom fields for new articles\n", FILE_APPEND);
        } catch (\Exception $e) {
            file_put_contents($log, date('Y-m-d H:i:s') . " - createFieldValuesTrigger ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
            file_put_contents($log, date('Y-m-d H:i:s') . " - Note: Trigger is optional, plugin will still work via onContentAfterSave\n", FILE_APPEND);
        }
    }
}
