<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Fields.Ogfix
 * @author      JoomlaBoost Team
 * @copyright   Copyright (C) 2025 JoomlaBoost. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Joomla\Plugin\Fields\Ogfix\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Component\Fields\Administrator\Plugin\FieldsPlugin;

/**
 * OG Fix Fields Plugin
 *
 * Prevents PHP 8.1+ json_decode(null) deprecation warnings in Media fields
 * by sanitizing NULL values BEFORE they reach the core Media field plugin.
 *
 * @since 0.1.102
 */
final class Ogfix extends FieldsPlugin
{
    /**
     * Intercept custom fields before they are processed by type-specific plugins.
     *
     * This event fires during field preparation. By sanitizing NULL values here,
     * we prevent deprecation warnings in both frontend and backend.
     *
     * @param   string    $context  The context of the content (e.g., 'com_content.article')
     * @param   \stdClass $item     The item object containing the fields
     * @param   \stdClass $field    The field object being prepared (passed by reference)
     *
     * @return  void
     *
     * @since   0.1.104
     */
    public function onCustomFieldsPrepareField($context, $item, $field): void
    {
        // 1. Target only Article Custom Fields
        if ($context !== 'com_content.article') {
            return;
        }

        // 2. Target ALL three custom OG fields
        $targetFields = [
            'custom_og_image',
            'custom_og_title',
            'custom_og_description',
        ];

        if (!in_array($field->name, $targetFields, true)) {
            return;
        }

        // 3. THE FIX: Check for NULL or empty string and inject proper defaults
        // This prevents BOTH json_decode(null) AND DOMCdataSection(null) errors

        // Determine default value based on field type
        $defaultValue = '';
        if ($field->type === 'media') {
            $defaultValue = '{"imagefile":""}'; // Valid JSON for Media field
        } else {
            $defaultValue = ''; // Empty string for text/textarea CDATA
        }

        // Sanitize value property (main field value)
        if (($field->value ?? null) === null || $field->value === '') {
            $field->value = $defaultValue;
        }

        // Also sanitize rawvalue if it exists (backend form population)
        if (property_exists($field, 'rawvalue')) {
            if (($field->rawvalue ?? null) === null || $field->rawvalue === '') {
                $field->rawvalue = $defaultValue;
            }
        }
    }
}
