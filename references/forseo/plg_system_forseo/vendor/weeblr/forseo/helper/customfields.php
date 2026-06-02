<?php
/**
 * Project: 4SEO
 *
 * @package          4SEO
 * @copyright        Copyright Weeblr llc - 2020 - 2026
 * @author           Yannick Gaultier - Weeblr llc
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          6.10.1.2660
 * @date        2026-01-30
 */

namespace Weeblr\Forseo\Helper;

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Customfields extends Base\Base
{
	public const SUPPORTED_CF_TYPES = [
		'calendar',
		'checkboxes',
		'color',
		'editor',
		'integer',
		'list',
		'imagelist',
		'media',
		'radio',
		//'repeatable',
		//'sql',
		'text',
		'textarea',
		'url',
		'user',
		'usergrouplist',
	];

	public const RAWVALUE_TYPES = [
		'imagelist',
		'media',
		'url'
	];

	/**
	 * @var array Storage for custom fields values for the request
	 */
	private static $valuesCache = [];

	/**
	 * Facade for the features item in the app config.
	 *
	 * @param Field $field
	 *
	 * @return bool
	 */
	public function pass($field, $operator, $value1, $value2 = null)
	{
		return $this->checkPassStatus(
			$field,
			$operator,
			$this->prepareValue($field, $operator, $value1),
			$this->prepareValue($field, $operator, $value2)
		);
	}

	/**
	 * Some field types may require pre-processing to normalize values.
	 *
	 * @param Field  $field
	 * @param string $operator
	 * @param mixed  $value
	 * @return mixed
	 */
	private function prepareValue($field, $operator, $value)
	{
		return StringHelper::trim($value);
	}

	/**
	 * @param Field  $field
	 * @param string $operator
	 * @param mixed  $value1
	 * @param mixed  $value2
	 * @return bool
	 */
	private function checkPassStatus($field, $operator, $value1, $value2 = null)
	{
		$fieldValue = $this->prepareFieldValue(
			$field->type,
			$field->value,
			$field->rawvalue
		);

		switch ($operator)
		{
			case '=':
				return $fieldValue === $value1;
			case '!=':
				return $fieldValue !== $value1;
			case '>':
				return $fieldValue > $value1;
			case '>=':
				return $fieldValue >= $value1;
			case '<':
				return $fieldValue < $value1;
			case '<=':
				return $fieldValue <= $value1;
			case '<=>':
				return $fieldValue >= $value1 && $fieldValue <= $value2;
			case '<==>':
				return !empty($value1) && Wb\contains($fieldValue, $value1);
			case '<!==>':
				return !empty($value1) && !Wb\contains($fieldValue, $value1);
			case '==>':
				return !empty($value1) && Wb\startsWith($fieldValue, $value1);
			case '<==':
				return !empty($value1) && Wb\endsWith($fieldValue, $value1);
		}

		$this->factory->getThe('forseo.logger')
					  ->error('Unknown operator in custom fields rule ' . print_r($operator, true) . ', field id: ' . $field->id);

		return false;
	}

	/**
	 * Some field types may require pre-processing the field value.
	 *
	 * @param string $fieldType
	 * @param mixed  $value
	 * @param mixed  $rawValue
	 * @param JField $field
	 * @return mixed
	 */
	private function prepareFieldValue($fieldType, $value, $rawValue, $field = null)
	{
		if ('calendar' === $fieldType)
		{
			$showTime   = $field->fieldparams->get('showtime');
			$dateBits   = empty($rawValue)
				? []
				: explode(' ', $rawValue);
			$fieldValue = $showTime || empty($dateBits)
				? $rawValue
				: array_shift($dateBits);
		}

		if (
			!in_array($fieldType, self::RAWVALUE_TYPES)
			&&
			'calendar' !== $fieldType
		) {
			$fieldValue = is_array($value)
				? implode(', ', $value)
				: $value;
		}

		if (in_array($fieldType, self::RAWVALUE_TYPES))
		{
			$fieldValue = is_array($rawValue)
				? implode(', ', $rawValue)
				: $rawValue;
		}

		return $fieldValue;
	}

	/**
	 * Gets the value of a custom field by its id, either for a passed
	 * content array or from the one gathered during the current request.
	 *
	 * @param int    $customFieldId
	 * @param string $valueType auto | value | rawvalue
	 * @param array  $contentData
	 * @return null
	 */
	public function getFieldValueById($customFieldId, $valueType = 'auto', $contentData = null)
	{
		$customFieldId = is_array($customFieldId)
			? $customFieldId[0]
			: $customFieldId;

		if (empty($customFieldId))
		{
			return null;
		}

		if (
			empty($contentData)
			&&
			$this->fieldHasCachedValueById($customFieldId . $valueType)
		) {
			// we cache CF values for the current request, use that
			// if available
			return self::$valuesCache[$customFieldId . $valueType];
		}

		$field = $this->platform->getCustomFieldById($customFieldId);
		if (empty($field))
		{
			return null;
		}

		/**
		 * Filter the value of a custom field for the current page request.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\customfields
		 * @var forseo_cf_get_value_by_id
		 *
		 * @param mixed  $customFieldValue
		 * @param int    $customFieldId Id of custom field in platform table
		 * @param string $fieldContext  The context of the content being passed to the plugin.
		 * @param array  $contentData   The main page content array, to which the custom fields has been attached.
		 *
		 * @return mixed
		 *
		 * @since   2.1.1
		 *
		 */
		$value = $this->factory->getThe('hook')->filter(
			'forseo_cf_get_value_by_id',
			null,
			$customFieldId,
			$field->context,
			$contentData
		);

		if ('auto' === $valueType)
		{
			$value = $this->prepareFieldValue(
				$field->type,
				Wb\arrayGet($value, 'value'),
				Wb\arrayGet($value, 'rawvalue'),
				$field
			);
		}

		if ('rawvalue' === $valueType)
		{
			$value = Wb\arrayGet($value, 'rawvalue');
		}

		if ('value' === $valueType)
		{
			$value = Wb\arrayGet($value, 'value');
		}

		if (empty($contentData))
		{
			self::$valuesCache[$customFieldId . $valueType] = $value;
		}

		return $value;
	}

	/**
	 * Check if we have a cached value for a specific custom fields id
	 * for the current request main object.
	 *
	 * @param int $customFieldId
	 * @return bool
	 */
	private function fieldHasCachedValueById($customFieldId)
	{
		return array_key_exists(
			$customFieldId,
			self::$valuesCache
		);
	}
}
