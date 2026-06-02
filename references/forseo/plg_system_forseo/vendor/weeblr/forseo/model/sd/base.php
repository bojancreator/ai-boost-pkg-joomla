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

namespace Weeblr\Forseo\Model\Sd;

use Weeblr\Forseo\Data;
use Weeblr\Forseo\Helper;

use Weeblr\Forseo\Model\Config;
use Weeblr\Wblib\Forseo\Base as wblBase;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Base class for Structured Data objects.
 *
 * @package Weeblr\Forseo\Data\Sd
 */
class Base extends wblBase\Base
{
	/**
	 * @var string Schema type.
	 */
	protected $type = '';

	/**
	 * @var string The base string used to build internal schema ids, typically the site root URL.
	 */
	protected $baseId = '';

	/**
	 * @var array The Structured data being built.
	 */
	protected $sdData = [];

	/**
	 * @var string Raw custom schema data entered by user.
	 */
	protected $sdCustom = '';

	/**
	 * @var array Structured data identities actually used when building the SD, need to be injected into the SD json output.
	 */
	private $identitiesUsed = [];

	/**
	 * @var array Records of identities that may have been created specifically for this item.
	 */
	protected $identitiesCreated = [];

	/**
	 * @var Data\Rule Instance of the rule that triggered instantiating this object.
	 */
	protected $spec;

	/**
	 * @var array List of fields that should be automatically generated (ie not overriden by user)
	 */
	protected $autoFields = [];

	/**
	 * @var array Generated data for "auto" fields
	 */
	protected $autoFieldsData = [];

	/**
	 * @var Config Instance of sd config.
	 */
	protected $config;

	/**
	 * @var Data\Requestinfo Convenience instance of the current request details.
	 */
	protected $requestInfo;

	/**
	 * @var Data\Page Instance of current computed data about the page.
	 */
	protected $pageData;

	/**
	 * @var Helper\Sd Instance of structured data helper.
	 */
	protected $helper;

	/**
	 * Stores factory instance.
	 *
	 * @param array $options Can inject custom factory and platform.
	 */
	public function __construct($options = [])
	{
		parent::__construct($options);

		$this->helper = $this->factory->getA(
			Helper\Sd::class,
			$options
		);

		$this->config = $this->factory->getThis('forseo.config', 'sd');

		$this->pageData    = Wb\arrayGet($options, 'pageData');
		$this->requestInfo = Wb\arrayGet($options, 'requestInfo');
		$this->spec        = Wb\arrayGet($options, 'spec');
		$this->baseId      = Wb\arrayGet($options, 'baseId');
	}

	/**
	 * Builds a data array suited to building Structured data from the
	 * rule definition used in the client.
	 *
	 * @param string $actualRuleType
	 * @param array  $rule
	 * @return $this
	 */
	public function addSdSpecFromRule($actualRuleType, $rule)
	{
		$rawValue = $this->helper->getCustomValueFromRule($rule, 'actionSdCustom');
		if (empty($rawValue))
		{
			$rawValue = [];
		}

		if (is_array($rawValue))
		{
			$this->sdCustom = $rawValue;
		}

		if (!is_array($rawValue))
		{
			$value = json_decode(
				$rawValue,
				true
			);

			$this->sdCustom = empty($value)
				? []
				: $value;
		}

		return $this;
	}

	/**
	 * Reads the SD fields definition constant to find the SD type
	 * for a given field.
	 *
	 * @param string $lcItemKey
	 * @return string
	 */
	protected function sdFieldType($lcItemKey)
	{
		return Wb\arrayGet(
			Data\Sd::SD_FIELDS_DEF,
			['fields', $lcItemKey, 'type']
		);
	}

	/**
	 * Whether a specific field has been set to automatic or custom by user
	 * in the provided rule.
	 *
	 * @param array  $rule
	 * @param string $itemKey
	 * @return bool
	 */
	protected function sdIsAutomaticField($rule, $itemKey)
	{
		return Wb\arrayGet($rule, 'actionSd' . ucfirst($itemKey) . 'Auto')
			? Data\Sd::FIELD_AUTO
			: Data\Sd::FIELD_CUSTOM;
	}

	/**
	 * Getter for the built data. Can be called multiple times
	 * but only after having called build();
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function get()
	{
		$built = $this->build();

		return $built
			? [
				'sdData'            => $this->sdData,
				'identitiesUsed'    => $this->identitiesUsed,
				'identitiesCreated' => $this->identitiesCreated
			]
			: [];
	}

	/**
	 * Builds data array for a given Schema type.
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function build()
	{
		if (false === $this->canRun())
		{
			return false;
		}

		$this->sdData = array_merge(
			[
				'@type' => $this->type,
			],
			$this->sdData
		);

		$this->loadAutoFields()
			 ->buildFields()
			 ->buildCustomCode()
			 ->addMissingFields()
			 ->makeCompliant();

		return $this->hasRequiredFields();
	}

	/**
	 * Whether enough structured data has been provided for required fields.
	 *
	 * @return bool
	 */
	protected function hasRequiredFields()
	{
		$requiredFieldsDef = Data\Sd::REQUIRED_FIELDS_PER_TYPE[$this->type];
		if (is_callable($requiredFieldsDef))
		{
			return $requiredFieldsDef(
				$this->sdData
			);
		}
		else
		{
			$fieldsProvidedFor = array_intersect_key(
				array_flip(Data\Sd::REQUIRED_FIELDS_PER_TYPE[$this->type]),
				$this->sdData
			);

			return count($fieldsProvidedFor) > 0
				   &&
				   count($fieldsProvidedFor) === count(Data\Sd::REQUIRED_FIELDS_PER_TYPE[$this->type]);
		}
	}

	/**
	 * Review the built data and make it compliant to the specific
	 * item type. For instance, drop some fields if they don't have proper values
	 * or if they should only be provided when another, missing, value is also present.
	 */
	protected function makeCompliant()
	{
		ksort($this->sdData);

		return true;
	}

	/**
	 * Add the any field that has not been provided by extension-specific plugin
	 * when we have a fallback for it.
	 *
	 * @return $this
	 * @throws \Exception
	 */
	protected function addMissingFields()
	{
		$fields = Wb\arrayGet($this->spec, 'fields', []);
		foreach ($fields as $fieldName => $fieldDef)
		{
			// Fields that are missing entirely
			if (empty($this->sdData[$fieldName]))
			{
				switch ($fieldName)
				{
					case 'description':
						$this->sdData[$fieldName] = $this->requestInfo->getMetaDescription();
						break;
					case 'author':
						$this->addIdentity('author', 'defaultAuthor');
						break;
					case 'publisher':
						$this->addIdentity('publisher', 'defaultPublisher');
						break;
					case 'address':
						$this->addIdentity('address', 'defaultAddress');
						break;
					case 'image':
						$pageImage = $this->getPageImage();
						if (!empty($pageImage))
						{
							$this->sdData[$fieldName] = $pageImage;
						}
						if (empty($pageImage) && !empty(Wb\arrayGet($this->spec, 'useDefaultImageIfMissing')))
						{
							$this->addIdentity(
								'image',
								'defaultLogo'
							);
						}
						break;
				}
			}

			// Fields which may be missing some properties
			if (!empty($this->sdData[$fieldName]))
			{
				switch ($fieldName)
				{
					case 'publisher':
						if (!is_array($this->sdData[$fieldName]))
						{
							// user entered a custom value, reformat
							$this->sdData[$fieldName] = [
								'@type' => Data\Sd::ORGANIZATION,
								'name'  => $this->sdData[$fieldName]
							];
						}

						// if not default publisher and no logo, use the default one.
						$id = Wb\arrayGet($this->sdData, ['publisher', '@id'], '');
						if (
							!Wb\endsWith($id, '#defaultPublisher')
							&&
							Wb\arrayIsEmpty($this->sdData, ['publisher', 'logo'])
						) {
							$this->addIdentity(
								['publisher', 'logo'],
								'defaultLogo'
							);
						}
						break;
				}
			}
		}

		return $this;
	}

	protected function buildCustomCode()
	{
		if (!empty($this->sdCustom))
		{
			$this->sdData = array_merge(
				$this->sdData,
				Wb\arrayEnsure($this->sdCustom)
			);
		}

		return $this;
	}

	/**
	 * Build all structured data fields based on their type:
	 * - automatically computed by plugin
	 * - custom, provided by user for this page
	 * - raw custom code entered by user in admin for this page
	 *
	 * @return $this
	 */
	protected function buildFields()
	{
		// Foreach field, as defined in the rule
		// If set to custom, just use that (apply truncating, formatting, etc)
		// If not custom, run a filter to get automatic value from plugins
		// For some fields, if plugins did not return a value, set defaults
		// (or set defaults in value passed to filter?)

		$fields = Wb\arrayGet($this->spec, 'fields', []);
		foreach ($fields as $fieldName => $fieldDefs)
		{
			if (isset($fieldDefs[0]))
			{
				// an array of defs
				$renderedFields = [];
				foreach ($fieldDefs as $index => $subFieldDef)
				{
					$renderedField = $this->buildField($fieldName, $subFieldDef, $index);
					if (!empty($renderedField))
					{
						$renderedFields[] = $renderedField;
					}
				}
				$this->sdData[$fieldName] = $renderedFields;
			}
			else
			{
				// a single def
				$renderedField = $this->buildField($fieldName, $fieldDefs);
				if (!is_null($renderedField))
				{
					$this->sdData[$fieldName] = $renderedField;
				}
			}
		}

		return $this;
	}

	protected function buildField($fieldName, $fieldDef, $index = null)
	{
		$fieldValue = null;

		$value     = Wb\arrayGet($fieldDef, 'value');
		$valueType = Wb\arrayGet($fieldDef, 'valueType', Data\Sd::FIELD_AUTO);

		$autoDataSelector = is_null($index)
			? ['sdData', $fieldName]
			: ['sdData', $fieldName, $index];

		if (
			Data\Sd::FIELD_AUTO === $valueType
			&&
			Wb\arrayIsSet($this->autoFieldsData, $autoDataSelector)
		) {
			$fieldValue = $this->formatProperty(
				$fieldName,
				Wb\arrayGet($this->autoFieldsData, $autoDataSelector),
				$fieldDef
			);
		}

		if (Data\Sd::FIELD_CUSTOM === $valueType)
		{
			$fieldValue = $this->formatProperty(
				$fieldName,
				$value,
				$fieldDef
			);
		}

		return $fieldValue;
	}

	/**
	 * Initial processing of schema definition and calling
	 * upon plugins to build the automatic data.
	 * This initial, "auto" data will be then processed by a
	 * descendant class into proper schema data.
	 *
	 * @return $this
	 * @throws \Exception
	 */
	protected function loadAutoFields()
	{
		$fields               = Wb\arrayGet($this->spec, 'fields', []);
		$this->autoFields     = [];
		$this->autoFieldsData = [
			'sdData'            => [],
			'identitiesUsed'    => [],
			'identitiesCreated' => []
		];
		foreach ($fields as $fieldName => $fieldDef)
		{
			$valueType = Wb\arrayGet($fieldDef, 'valueType', Data\Sd::FIELD_AUTO);
			if (Data\Sd::FIELD_AUTO === $valueType)
			{
				$this->autoFields[$fieldName] = $fieldDef;
			}
		}

		$this->buildAutoFields();

		// merge into main data records
		$identitiesCreated = Wb\arrayGet($this->autoFieldsData, 'identitiesCreated', []);
		foreach ($identitiesCreated as $identityId => $definition)
		{
			$this->storeNewIdentity($identityId, $definition);
		}

		$identitiesUsed = Wb\arrayGet($this->autoFieldsData, 'identitiesUsed', []);
		foreach ($identitiesUsed as $identityProperty => $identityId)
		{
			$this->addIdentity($identityProperty, $identityId);
		}

		return $this;
	}

	/**
	 * Build fields automatically extracted from content or database for current SD type.
	 * Essentially runs a hook but can be overriden by descendant for specific behavior.
	 * Built fields should always be filtered even if built by descendants.
	 */
	protected function buildAutoFields()
	{
		if (!empty($this->autoFields))
		{
			/**
			 * Filter the automatically built values of a given type of structured data.
			 *
			 * @api     forseo
			 * @package 4SEO\filter\sd
			 * @var forseo_sd_auto_data
			 * @since   1.3.0
			 *
			 * @param array            $autoFieldsData
			 * @param array            $autoFields
			 * @param array            $spec
			 * @param Data\Requestinfo $requestInfo
			 * @param Data\Page        $pageData
			 *
			 * @return array
			 *
			 */
			$this->autoFieldsData = $this->factory
				->getThe('hook')
				->filter(
					'forseo_sd_auto_data',
					$this->autoFieldsData,
					$this->autoFields,
					$this->spec,
					$this->requestInfo,
					$this->pageData,
					$this->baseId
				);
		}
	}

	/**
	 * Whether the current SD type can be rendered for the current page.
	 *
	 * For instance, an Article cannot be rendered on a category page.
	 *
	 * @return bool|null
	 */
	protected function canRun()
	{
		/**
		 * Whether the assigned rule can be run on this page.
		 * By default is null.
		 * If a plugin can support, it sets it to true.
		 * If a plugin says this SD type cannot exist on this page, it sets it to false.
		 * Else leave as is.
		 *
		 * In the end, returned value must be true (ie at least one plugin can support and no other
		 * contradict) for the rule to run.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\sd
		 * @var forseo_sd_can_run_rule
		 * @since   1.0.0
		 *
		 * @param bool             $canRun
		 * @param array            $spec
		 * @param Data\Requestinfo $requestInfo
		 * @param Data\Page        $pageData
		 *
		 * @return bool|null
		 *
		 */
		return $this->factory
			->getThe('hook')
			->filter(
				'forseo_sd_can_run_rule',
				null,
				$this->spec,
				$this->requestInfo,
				$this->pageData
			);
	}

	/**
	 * Builds up and ImageObject record for the current page image. As this is based off
	 * the page_image or page_sharing_image collected from the page, it can be shared
	 * by all plugins. However, this method is only called from the addMissingFields() method
	 * meaning plugins can provide their own image and that will not be overwritten.
	 * Note that image validation has been put in a different method so that each
	 * Schema type can have different spec for images.
	 *
	 * @return array[]|null
	 * @throws \Exception
	 */
	protected function getPageImage()
	{
		// @TODO: also provide a fallback image? image should be on page though
		$pageImage = $this->requestInfo->get('page_image');
		if (empty($pageImage))
		{
			$pageImage = $this->requestInfo->get('page_sharing_image');
		}
		if (empty($pageImage))
		{
			return null;
		}

		return $this->isValidImage($pageImage)
			? [
				[
					'@type'       => Data\Sd::IMAGE_OBJECT,
					'url'         => Wb\arrayGet($pageImage, 'url'),
					'caption'     => Wb\arrayGet($pageImage, 'caption'),
					'description' => Wb\arrayGet($pageImage, 'alt'),
					'width'       => Wb\arrayGet($pageImage, 'width'),
					'height'      => Wb\arrayGet($pageImage, 'height'),
				]
			]
			: null;
	}

	/**
	 * Validate if an image can be used in a schema record. Meant to
	 * be overriden by individual schema type handlers, as they may
	 * hev different requirements per type.
	 *
	 * @param array $pageImage
	 * @return bool
	 */
	protected function isValidImage($pageImage)
	{
		return true;
	}

	/**
	 * Inject a given identity.
	 *
	 * @param string $identityProperty Schema property name
	 * @param string $identityId       Internal id
	 * @return Base
	 */
	public function addIdentity($identityProperty, $identityId)
	{
		$prop         = Wb\arrayEnsure($identityProperty);
		$this->sdData = Wb\arraySet(
			$this->sdData,
			$prop,
			[
				'@id' => $this->baseId . '#' . $identityId
			]
		);

		return $this->storeUsedIdentity($identityId);
	}

	/**
	 * Store a new custom identity record.
	 *
	 * @param string $identityId
	 * @param array  $definition
	 * @return Base
	 */
	protected function storeNewIdentity($identityId, $definition)
	{
		$definition['@id'] = $this->baseId . '#' . $identityId;

		$this->identitiesCreated[$identityId] = $definition;

		return $this;
	}

	/**
	 * Store an identity used in this record.
	 *
	 * @param string $identity
	 * @return $this
	 */
	protected function storeUsedIdentity($identity)
	{
		$this->identitiesUsed = Wb\arrayAppendUnlessPresent(
			$this->identitiesUsed,
			$identity
		);

		return $this;
	}

	/**
	 * Format standard properties for inclusion in a structured data record.
	 *
	 * @param string $propName
	 * @param mixed  $propValue
	 * @param array  $fieldDef
	 * @return mixed
	 */
	protected function formatProperty($propName, $propValue, $fieldDef)
	{
		if (!is_array($propValue))
		{
			return $this->formatIndividualProperty($propName, $propValue, $fieldDef);
		}

		foreach ($propValue as $subPropName => $subProValue)
		{
			$propValue[$subPropName] = $this->formatProperty($subPropName, $subProValue, $fieldDef);
		}

		return $propValue;
	}

	/**
	 * Format standard properties for inclusion in a structured data record.
	 *
	 * @param string $propName
	 * @param mixed  $propValue
	 * @param array  $fieldDef
	 * @return mixed
	 */
	protected function formatIndividualProperty($propName, $propValue, $fieldDef = null)
	{
		try
		{
			switch ($propName)
			{
				case 'headline':
					return Wb\abridge($propValue, 110, 110);
				case 'dateCreated':
				case 'datePublished':
				case 'dateModified':
				case 'uploadDate':
				case 'validFrom':
				case 'validThrough':
				case 'validUntil':
					return empty($propValue)
						? ''
						: System\Date::toExtendedDateTime($propValue)->toW3c();
				case 'startDate':
				case 'endDate':
					if (empty($propValue))
					{
						return '';
					}

					$propValue = trim($propValue);
					$hasTime   = Wb\contains($propValue, ['T', ' ']);
					if (
						'NOW' !== strtoupper($propValue)
						&&
						!$hasTime
					) {
						return $propValue;
					}
					return System\Date::toExtendedDateTime($propValue)->toW3c();
				case 'prepTime':
				case 'cookTime':
				case 'totalTime':
					return empty($propValue)
						? ''
						: $this->minutesToDuration($propValue);
				case 'offerPrice':
				case 'sdPrice':
					return str_replace(',', '.', $propValue);
			}
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}

		return $propValue;
	}

	/**
	 * Convert a number of minute to a standard duration string.
	 *
	 * @param int $minutes
	 */
	protected function minutesToDuration($minutes)
	{
		$minutes = (int)$minutes;
		return empty($minutes)
			? ''
			: 'PT' . $minutes . 'M';
	}
}
