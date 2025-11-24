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
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * Custom Fields Auto-Creation Service
 *
 * Manages automatic creation of Joomla Custom Fields for per-article OpenGraph overrides.
 * Creates field group and three fields: custom_og_image, custom_og_title, custom_og_description.
 *
 * @since 0.1.65
 */
class CustomFieldsService
{
    /**
     * Field names we manage
     */
    private const FIELD_NAMES = [
        'custom_og_image',
        'custom_og_title',
        'custom_og_description',
    ];

    /**
     * Field group name
     */
    private const GROUP_NAME = 'joomlaboost-seo';

    /**
     * Database instance
     *
     * @var DatabaseInterface
     */
    private DatabaseInterface $db;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = Factory::getContainer()->get(DatabaseInterface::class);
    }

    /**
     * Check if all custom fields exist
     *
     * @return bool True if all three fields exist
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
     * Create custom field group for JoomlaBoost
     *
     * @return int|null Group ID or null on failure
     */
    public function createCustomFieldGroup(): ?int
    {
        try {
            // Check if group already exists
            $query = $this->db->getQuery(true)
                ->select('id')
                ->from($this->db->quoteName('#__fields_groups'))
                ->where($this->db->quoteName('context') . ' = ' . $this->db->quote('com_content.article'))
                ->where($this->db->quoteName('title') . ' = ' . $this->db->quote('JoomlaBoost SEO'));

            $this->db->setQuery($query);
            $existingId = $this->db->loadResult();

            if ($existingId) {
                return (int) $existingId;
            }

            // Create new group
            $columns = [
                'context',
                'title',
                'note',
                'description',
                'state',
                'access',
                'language',
                'created',
                'created_by',
                'ordering',
            ];

            $values = [
                $this->db->quote('com_content.article'),
                $this->db->quote('JoomlaBoost SEO'),
                $this->db->quote(''),
                $this->db->quote('OpenGraph meta tags overrides for social media sharing'),
                1, // Published
                1, // Public access
                $this->db->quote('*'), // All languages
                $this->db->quote(Factory::getDate()->toSql()),
                Factory::getUser()->id,
                0,
            ];

            $query = $this->db->getQuery(true)
                ->insert($this->db->quoteName('#__fields_groups'))
                ->columns($this->db->quoteName($columns))
                ->values(implode(',', $values));

            $this->db->setQuery($query);
            $this->db->execute();

            return (int) $this->db->insertid();
        } catch (\Exception $e) {
            // Log error but don't crash
            Factory::getApplication()->enqueueMessage(
                'JoomlaBoost: Failed to create custom field group: ' . $e->getMessage(),
                'warning'
            );
            return null;
        }
    }

    /**
     * Create all three custom fields
     *
     * @param int|null $groupId Field group ID (optional)
     * @return array Array of created field IDs
     */
    public function createCustomFields(?int $groupId = null): array
    {
        $createdIds = [];

        $fieldsConfig = [
            'custom_og_image' => [
                'title' => 'Custom OG Image',
                'label' => 'OpenGraph Image Override',
                'description' => 'Full URL or relative path to image (e.g., images/my-custom-og-image.jpg). Leave empty to use article intro image.',
                'type' => 'text',
                'default' => '',
            ],
            'custom_og_title' => [
                'title' => 'Custom OG Title',
                'label' => 'OpenGraph Title Override',
                'description' => 'Custom title for social media sharing. Leave empty to use article title.',
                'type' => 'text',
                'default' => '',
            ],
            'custom_og_description' => [
                'title' => 'Custom OG Description',
                'label' => 'OpenGraph Description Override',
                'description' => 'Custom description for social media sharing (150-300 characters recommended). Leave empty to use article intro text.',
                'type' => 'textarea',
                'default' => '',
            ],
        ];

        foreach ($fieldsConfig as $fieldName => $config) {
            $fieldId = $this->createSingleField($fieldName, $config, $groupId);
            if ($fieldId) {
                $createdIds[$fieldName] = $fieldId;
            }
        }

        return $createdIds;
    }

    /**
     * Create a single custom field
     *
     * @param string $fieldName Field name
     * @param array $config Field configuration
     * @param int|null $groupId Field group ID
     * @return int|null Field ID or null on failure
     */
    private function createSingleField(string $fieldName, array $config, ?int $groupId = null): ?int
    {
        try {
            // Check if field already exists
            $query = $this->db->getQuery(true)
                ->select('id')
                ->from($this->db->quoteName('#__fields'))
                ->where($this->db->quoteName('name') . ' = ' . $this->db->quote($fieldName))
                ->where($this->db->quoteName('context') . ' = ' . $this->db->quote('com_content.article'));

            $this->db->setQuery($query);
            $existingId = $this->db->loadResult();

            if ($existingId) {
                return (int) $existingId;
            }

            // Field parameters
            $params = [
                'hint' => '',
                'render_class' => '',
                'class' => '',
                'showlabel' => '1',
                'label_class' => '',
                'show_on' => '',
                'display' => '2', // Show on all
            ];

            if ($config['type'] === 'textarea') {
                $params['rows'] = 3;
                $params['cols'] = 50;
                $params['maxlength'] = 500;
            }

            $columns = [
                'context',
                'group_id',
                'title',
                'name',
                'label',
                'default_value',
                'type',
                'note',
                'description',
                'state',
                'required',
                'access',
                'language',
                'params',
                'created_time',
                'created_user_id',
                'ordering',
            ];

            $values = [
                $this->db->quote('com_content.article'),
                $groupId ?: 0,
                $this->db->quote($config['title']),
                $this->db->quote($fieldName),
                $this->db->quote($config['label']),
                $this->db->quote($config['default']),
                $this->db->quote($config['type']),
                $this->db->quote(''),
                $this->db->quote($config['description']),
                1, // Published
                0, // Not required
                1, // Public access
                $this->db->quote('*'), // All languages
                $this->db->quote(json_encode($params)),
                $this->db->quote(Factory::getDate()->toSql()),
                Factory::getUser()->id,
                0,
            ];

            $query = $this->db->getQuery(true)
                ->insert($this->db->quoteName('#__fields'))
                ->columns($this->db->quoteName($columns))
                ->values(implode(',', $values));

            $this->db->setQuery($query);
            $this->db->execute();

            return (int) $this->db->insertid();
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                'JoomlaBoost: Failed to create field "' . $fieldName . '": ' . $e->getMessage(),
                'warning'
            );
            return null;
        }
    }

    /**
     * Setup all custom fields (group + fields)
     *
     * @return array Result with success status and message
     */
    public function setupCustomFields(): array
    {
        try {
            // Check if already exist
            if ($this->checkCustomFieldsExist()) {
                return [
                    'success' => true,
                    'message' => 'Custom fields already exist.',
                    'action' => 'skipped',
                ];
            }

            // Create group
            $groupId = $this->createCustomFieldGroup();

            // Create fields
            $fieldIds = $this->createCustomFields($groupId);

            if (\count($fieldIds) === \count(self::FIELD_NAMES)) {
                return [
                    'success' => true,
                    'message' => 'Successfully created ' . \count($fieldIds) . ' custom fields in group "JoomlaBoost SEO".',
                    'action' => 'created',
                    'group_id' => $groupId,
                    'field_ids' => $fieldIds,
                ];
            }

            return [
                'success' => false,
                'message' => 'Only ' . \count($fieldIds) . ' of ' . \count(self::FIELD_NAMES) . ' fields were created.',
                'action' => 'partial',
                'field_ids' => $fieldIds,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'action' => 'error',
            ];
        }
    }
}
