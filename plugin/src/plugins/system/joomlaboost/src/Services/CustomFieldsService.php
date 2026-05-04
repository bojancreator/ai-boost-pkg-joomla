<?php

/**
 * @package     JoomlaBoost
 * @subpackage  Services
 * @copyright   Copyright (C) 2024 4X4 Serbia Crew. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Services;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Date\Date;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * Custom Fields Auto-Creation Service (using JTable API)
 *
 * @since 0.1.66
 */
class CustomFieldsService
{
    private const FIELD_NAMES = [
        'custom-og-image',
        'custom-og-title',
        'custom-og-description',
    ];

    private const GROUP_NAME = 'joomlaboost-seo';

    private DatabaseInterface $db;

    public function __construct()
    {
        $this->db = Factory::getContainer()->get(DatabaseInterface::class);

        // Add fields table path
        Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_fields/tables');
    }

    /**
     * Check if all custom fields exist
     */
    public function checkCustomFieldsExist(): bool
    {
        try {
            $query = $this->db->getQuery(true)
                ->select('COUNT(*)')
                ->from($this->db->quoteName('#__fields'))
                ->where($this->db->quoteName('name') . ' IN (' . implode(',', array_map([$this->db, 'quote'], self::FIELD_NAMES)) . ')')
                ->where($this->db->quoteName('context') . ' = ' . $this->db->quote('com_content.article'));

            $this->db->setQuery($query);
            $count = (int) $this->db->loadResult();

            return $count === \count(self::FIELD_NAMES);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Setup custom fields using JTable
     */
    public function setupCustomFields(): array
    {
        try {
            // 1. Create/get field group
            $groupId = $this->createCustomFieldGroup();
            if (!$groupId) {
                return [
                    'success' => false,
                    'message' => 'Failed to create custom field group',
                ];
            }

            // 2. Create individual fields
            $fieldIds = $this->createCustomFields($groupId);
            if (empty($fieldIds)) {
                return [
                    'success' => false,
                    'message' => 'Failed to create custom fields',
                ];
            }

            return [
                'success' => true,
                'message' => sprintf(
                    'Custom fields created successfully: group "%s", %d fields (%s)',
                    self::GROUP_NAME,
                    count($fieldIds),
                    implode(', ', self::FIELD_NAMES)
                ),
                'group_id' => $groupId,
                'field_ids' => $fieldIds,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Create field group using JTable
     */
    private function createCustomFieldGroup(): ?int
    {
        try {
            $groupTable = Table::getInstance('Group', 'FieldsTable');

            // Try to load existing
            $groupTable->load([
                'context' => 'com_content.article',
                'title'   => 'JoomlaBoost SEO',
            ]);

            if ($groupTable->get('id')) {
                return (int) $groupTable->get('id');
            }

            // Create new
            $now = Date::getInstance()->toSql();
            $userId = Factory::getUser()->get('id');

            $groupTable->bind([
                'context'     => 'com_content.article',
                'title'       => 'JoomlaBoost SEO',
                'note'        => 'Custom fields for per-article OpenGraph overrides',
                'description' => 'Override OpenGraph meta tags on a per-article basis',
                'state'       => 1,
                'access'      => 1,
                'language'    => '*',
                'params'      => ['display_readonly' => '1'],
                'created'     => $now,
                'created_by'  => $userId,
                'modified'    => $now,
                'modified_by' => $userId,
            ]);

            if (!$groupTable->store()) {
                return null;
            }

            return (int) $groupTable->get('id');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Create all custom fields
     */
    private function createCustomFields(?int $groupId): array
    {
        $fieldConfigs = [
            'custom-og-image' => [
                'title' => 'Custom OG Image',
                'type' => 'media',
                'description' => 'Override OpenGraph image for this article',
                'params' => ['display' => 0],
                'fieldparams' => ['hint' => 'Select image for Facebook/Twitter sharing'],
            ],
            'custom-og-title' => [
                'title' => 'Custom OG Title',
                'type' => 'text',
                'description' => 'Override OpenGraph title for this article',
                'params' => ['display' => 0],
                'fieldparams' => ['hint' => 'Max 60 characters recommended', 'maxlength' => 100],
            ],
            'custom-og-description' => [
                'title' => 'Custom OG Description',
                'type' => 'textarea',
                'description' => 'Override OpenGraph description for this article',
                'params' => ['display' => 0],
                'fieldparams' => ['hint' => 'Max 160 characters recommended', 'rows' => 3],
            ],
        ];

        $fieldIds = [];
        foreach ($fieldConfigs as $name => $config) {
            $fieldId = $this->createSingleField($name, $config, $groupId);
            if ($fieldId) {
                $fieldIds[$name] = $fieldId;
            }
        }

        return $fieldIds;
    }

    /**
     * Create single field using JTable
     */
    private function createSingleField(string $name, array $config, ?int $groupId): ?int
    {
        try {
            $fieldTable = Table::getInstance('Field', 'FieldsTable');

            // Try to load existing
            $fieldTable->load([
                'context' => 'com_content.article',
                'name'    => $name,
            ]);

            $now = Date::getInstance()->toSql();
            $userId = Factory::getUser()->get('id');

            if ($fieldTable->get('id')) {
                // Update existing
                $fieldTable->bind([
                    'group_id'      => $groupId,
                    'modified_time' => $now,
                    'modified_by'   => $userId,
                ]);
            } else {
                // Create new
                $fieldTable->bind([
                    'title'           => $config['title'],
                    'name'            => $name,
                    'label'           => $config['title'],
                    'type'            => $config['type'],
                    'state'           => 1,
                    'context'         => 'com_content.article',
                    'group_id'        => $groupId,
                    'params'          => $config['params'] ?? ['display' => 0],
                    'fieldparams'     => $config['fieldparams'] ?? ['hint' => ''],
                    'note'            => $config['description'],
                    'description'     => '',
                    'language'        => '*',
                    'created_time'    => $now,
                    'created_user_id' => $userId,
                    'modified_time'   => $now,
                    'modified_by'     => $userId,
                ]);
            }

            if (!$fieldTable->store()) {
                return null;
            }

            return (int) $fieldTable->get('id');
        } catch (\Exception $e) {
            return null;
        }
    }
}
