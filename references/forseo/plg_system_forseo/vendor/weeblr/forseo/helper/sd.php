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

use Weeblr\Forseo\Data;

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Html;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Sd extends Base\Base
{
	public const AGGREGATE_RATINGS_PROPS_NAMES = [
		'ratingValue' => 'actionSdRatingValue',
		'reviewCount' => 'actionSdRatingCount',
		'bestRating'  => 'actionSdBestRating',
		'worstRating' => 'actionSdWorstRating'
	];

	public const AGGREGATE_RATINGS_DEFAULTS = [
		'ratingValue' => 1,
		'reviewCount' => 1,
		'bestRating'  => 1,
		'worstRating' => 5
	];

	/**
	 * @var System\Config Structured data configuration instance.
	 */
	private $config = null;

	/**
	 * @var System\Logger Convenience logger instance.
	 */
	private $logger = null;

	/**
	 * @var string Base id for identities.
	 */
	private $baseId = '';

	/**
	 * @var Helper\Customfields Instance of structured data helper.
	 */
	private $customFieldsHelper;

	/**
	 * @var Html\Image Instance of wbLib image helper.
	 */
	private $imageHelper;

	/**
	 * Stores factory instance.
	 *
	 * @param array $options Can inject custom factory and platform.
	 */
	public function __construct($options = [])
	{
		parent::__construct();

		$this->config             = $this->factory->getThis('forseo.config', 'sd');
		$this->logger             = $this->factory->getThe('forseo.logger');
		$this->customFieldsHelper = $this->factory->getA(
			Customfields::class,
			$options
		);
		$this->imageHelper        = $this->factory->getA(
			Html\Image::class,
			[
				'cacheLocalImages' => true,
				'cacheRemoteImages' => true
			]
		);

		$this->baseId = Wb\arrayGet($options, 'baseId');
	}


	/**
	 * Read a user custom value for a field. Lookup any custom field set as a custom value.
	 * If no custom field, tries to json_decode raw custom value, which
	 * allows users to enter raw custom SD data for standard fields.
	 * If not actual json, use the raw value.
	 *
	 * @param array  $rule
	 * @param string $key
	 * @param bool   $raw       If true, return raw value. If false, attempt to json_decode it
	 * @param bool   $stripTags Strip Tags - if not raw value requested
	 * @return mixed
	 */
	public function getCustomValueFromRule($rule, $key, $raw = false, $stripTags = true)
	{
		// Any custom field specified for this key?
		$cfId = Wb\arrayGet($rule, $key . 'CfId');
		if (
			!empty($cfId)
			&&
			\is_array($cfId)
		) {
			$cfId = $cfId[0];
		}

		if (!empty($cfId))
		{
			$cfValue = $this->customFieldsHelper->getFieldValueById($cfId);
			$value   = is_array($cfValue)
				? implode(', ', $cfValue)
				: $cfValue;
		}

		if (empty($cfId))
		{
			$rawValue = Wb\arrayGet($rule, $key, '');
			if ($raw)
			{
				$value = $rawValue;
			}
			if (!$raw)
			{
				$json = json_decode($rawValue, true);

				$value = empty($json)
					? null
					: $json;
			}
		}

		return $stripTags && is_string($value)
			? strip_tags($value)
			: $value;
	}

	/**
	 * Extract author name and URL from a rule options and build a
	 * PERSON structured data array with that.
	 *
	 * @param array $rule
	 * @param bool  $valueType
	 *
	 * @return array
	 */
	public function authorFromRule($rule, $valueType)
	{
		$sd = [];
		if (Data\Sd::FIELD_CUSTOM !== $valueType)
		{
			return $sd;
		}

		// build author using name and URL
		$name = $this->getCustomValueFromRule($rule, 'actionSdAuthor', true /* raw */);
		$url  = $this->getCustomValueFromRule($rule, 'actionSdAuthorUrl', true /* raw */);
		if (!empty($name))
		{
			$sd['name'] = $name;
		}
		if (!empty($url))
		{
			$sd['url'] = $url;
		}
		if (!empty($sd))
		{
			$sd['@type'] = Data\Sd::PERSON;
		}

		return $sd;
	}

	/**
	 * Extract date and time from a rule options and build a
	 * date time string with that.
	 *
	 * @param string $dateType Date/time property suffix
	 * @param array  $rule
	 * @param bool   $valueType
	 * @param string $datePrefix
	 * @param string $timePrefix
	 *
	 * @return string
	 */
	public function dateFromRule($dateType, $rule, $valueType, $datePrefix = 'actionSdDate', $timePrefix = 'actionSdTime')
	{
		$sd = '';
		if (Data\Sd::FIELD_CUSTOM !== $valueType)
		{
			return $sd;
		}

		$cfId = Wb\arrayGet($rule, $datePrefix . $dateType . 'CfId');
		if (!empty($cfId))
		{
			return $this->customFieldsHelper->getFieldValueById($cfId, 'auto');
		}

		return Wb\join(
			' ',
			Wb\arrayGet($rule, $datePrefix . $dateType),
			Wb\arrayGet($rule, $timePrefix . $dateType)
		);
	}

	/**
	 * Extract image from a rule options, finding about its size when a custom field.
	 *
	 * @param array  $rule
	 * @param bool   $valueType
	 * @param string $dataType auto | rawvalue | value Which value we want back from the custom field.
	 *
	 * @return array
	 */
	public function imageFromRule($rule, $valueType, $dataType = 'auto')
	{
		$sd = [];
		if (Data\Sd::FIELD_CUSTOM !== $valueType)
		{
			return $sd;
		}

		$cfId = Wb\arrayGet($rule, 'actionSdImageUrlCfId');
		if (!empty($cfId))
		{
			$imageRecords = $this->customFieldsHelper->getFieldValueById(
				$cfId,
				$dataType
			);

			$imageRecords = Wb\arrayEnsure($imageRecords);
			if (empty($imageRecords))
			{
				return [];
			}

			$field   = $this->platform->getCustomFieldById($cfId);
			$baseDir = 'imagelist' === $field->type
				? Wb\slashTrimJoin(
					'images',
					$field->fieldparams->get('directory', '')
				)
				: '';

			$imagesSdObjects = $this->buildImageObjectList(
				$imageRecords,
				$baseDir
			);

			return count($imagesSdObjects) === 1
				? array_shift($imagesSdObjects)
				: $imagesSdObjects;
		}

		return [
			'@type'  => Data\Sd::IMAGE_OBJECT,
			'url'    => Wb\arrayGet($rule, 'actionSdImageUrl'),
			'alt'    => Wb\arrayGet($rule, 'actionSdImageAlt'),
			'width'  => Wb\arrayGet($rule, 'actionSdImageWidth'),
			'height' => Wb\arrayGet($rule, 'actionSdImageHeight'),
		];
	}

	/**
	 * Builds an array of ImageObject structured data records based on a list
	 * of images URLs, possibly prepended by a file system base directory.
	 *
	 * @param array  $imageUrls
	 * @param string $baseDir
	 * @return array
	 */
	private function buildImageObjectList($imageUrls, $baseDir = '')
	{
		$images = [];
		foreach ($imageUrls as $imageUrl)
		{
			if (
				!System\Route::isFullyQualified($imageUrl)
				&&
				!empty($baseDir)
			) {
				// prepend base dir from custom fields options for local images
				$imageUrl = Wb\slashTrimJoin(
					$baseDir,
					$imageUrl
				);
			}

			$imageSize = $this->imageHelper->getImageSize($imageUrl);
			$images[]  = [
				'@type'  => Data\Sd::IMAGE_OBJECT,
				'url'    => System\Route::absolutify(
					$imageUrl,
					true
				),
				'width'  => Wb\arrayGet($imageSize, 'width'),
				'height' => Wb\arrayGet($imageSize, 'height'),
			];
		}

		return $images;
	}

	/**
	 * Build an aggregate rating from a custom value in a rule, possibly using
	 * custom fields.
	 *
	 * @param array $rule
	 * @param bool  $valueType
	 *
	 * @return array
	 */
	public function aggregateRatingFromRule($rule, $valueType)
	{
		$sd = [];
		if (Data\Sd::FIELD_CUSTOM !== $valueType)
		{
			return $sd;
		}

		$sd['@type'] = Data\Sd::AGGREGATE_RATING;

		foreach (self::AGGREGATE_RATINGS_PROPS_NAMES as $sdKeyName => $propName)
		{
			$cfId = Wb\arrayGet($rule, $propName . 'CfId');
			if (!empty($cfId))
			{
				$sd[$sdKeyName] = $this->customFieldsHelper->getFieldValueById($cfId);
			}

			$sd[$sdKeyName] = !isset($sd[$sdKeyName])
							  ||
							  is_null($sd[$sdKeyName])
				? Wb\arrayGet(
					$rule,
					$propName,
					self::AGGREGATE_RATINGS_DEFAULTS[$sdKeyName]
				)
				: $sd[$sdKeyName];
		}

		return $sd;
	}

	/**
	 * Build a product global identifier record from a rule, possibly using
	 * custom fields.
	 *
	 * @param array $rule
	 * @param bool  $valueType
	 *
	 * @return array
	 */
	public function productGlobalIdFromRule($rule, $valueType)
	{
		$sd = [];
		if (Data\Sd::FIELD_CUSTOM !== $valueType)
		{
			return $sd;
		}

		// build author using name and URL
		$globalIdType = $this->getCustomValueFromRule($rule, 'actionSdProductGlobalIdType', true /* raw */);
		if (empty($globalIdType))
		{
			return $sd;
		}

		$globalId = $this->getCustomValueFromRule($rule, 'actionSdProductGlobalId', true /* raw */);
		if (empty($globalId))
		{
			return $sd;
		}

		return [
			'productGlobalIdType' => $globalIdType,
			'productGlobalId'     => $globalId
		];
	}

	/**
	 * Transform a string to be used as an schema id.
	 *
	 * @param string $name
	 * @return string
	 */
	public function toId($name)
	{
		$name = preg_replace('/\p{P}/u', '_', $name);
		$name = str_replace(' ', '_', $name);
		$name = StringHelper::strtolower($name);
		return $name;
	}
}