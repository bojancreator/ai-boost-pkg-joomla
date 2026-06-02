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

use Weeblr\Wblib\Forseo\Wb;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Base class for Structured Data objects.
 *
 * @package Weeblr\Forseo\Data\Sd
 */
class Course extends Base
{
	/**
	 * @var string Schema type.
	 */
	protected $type = Data\Sd::COURSE;

	/**
	 * Builds a data array suited to building Structured data from the
	 * rule definition used in the client.
	 *
	 * @param string $actualRuleType
	 * @param array  $rule
	 * @return $this
	 * @throws \Exception
	 */
	public function addSdSpecFromRule($actualRuleType, $rule)
	{
		parent::addSdSpecFromRule($actualRuleType, $rule);

		if (empty($actualRuleType))
		{
			return $this;
		}

		// Some SD types may have "sub-types"
		$ruleType = $actualRuleType;

		$fields = [
			'inLanguage' => [
				'type'      => Data\Sd::TEXT,
				'valueType' => Data\Sd::FIELD_CUSTOM,
				'value'     => $this->requestInfo->get('page_language')
			]
		];

		foreach ($rule as $key => $value)
		{
			if (!Wb\startsWith($key, 'actionSd'))
			{
				continue;
			}
			$itemKey   = Wb\lTrim($key, 'actionSd');
			$lcItemKey = Wb\lcFirst($itemKey);
			switch ($lcItemKey)
			{
				case 'url':
				case 'name':
				case 'description':
					$fields[$lcItemKey] = [
						'type'      => $this->sdFieldType($lcItemKey),
						'valueType' => $this->sdIsAutomaticField($rule, $itemKey),
						'value'     => $this->helper->getCustomValueFromRule($rule, $key, true /* $raw */)
					];
					break;

				case 'location':
				case 'instructorName':
				case 'instructorDescription':
				case 'courseMode':
				case 'workload':
				case 'repeatCount':
				case 'repeatFrequency':
				case 'duration':
				case 'dateStarted':
				case 'dateEnded':
				case 'offerPrice':
				case 'courseOfferCategory':
					$fields[$lcItemKey] = [
						'valueType' => Data\Sd::FIELD_CUSTOM,
						'value'     => $this->helper->getCustomValueFromRule($rule, $key, true /* $raw */)
					];
					break;
				case 'offerPriceCurrency':
					$currency           = Wb\arrayEnsure(
						$this->helper->getCustomValueFromRule($rule, $key, true /* $raw */)
					);
					$fields[$lcItemKey] = [
						'valueType' => Data\Sd::FIELD_CUSTOM,
						'value'     => Wb\arrayGet($currency, 0, null)
					];
					break;
				case 'provider':
					$fields[$lcItemKey] = [
						'valueType' => $this->sdIsAutomaticField($rule, $itemKey),
						'value'     => [
							'type'  => Data\Sd::ORGANIZATION,
							'name' => $this->helper->getCustomValueFromRule($rule, $key, true /* $raw */)
						]
					];
					// auto mode, default is the detault PERSON
					// custom mode, type is TEXT
					$fields[$lcItemKey]['type'] = Data\Sd::ORGANIZATION;
					break;
				case 'imageAuto':
					$fieldKey          = 'image';
					$fields[$fieldKey] = [
						'type'      => $this->sdFieldType($fieldKey),
						'valueType' => $this->sdIsAutomaticField($rule, $fieldKey)
					];

					$imageFromRule = $this->helper->imageFromRule(
						$rule,
						Wb\arrayGet($fields, [$fieldKey, 'valueType'], Data\Sd::FIELD_AUTO)
					);
					if (!empty($imageFromRule))
					{
						$fields[$fieldKey]['value'] = $imageFromRule;
					}

					break;
				case 'aggregateRatingAuto':
					$fieldKey          = 'aggregateRating';
					$fields[$fieldKey] = [
						'type'      => $this->sdFieldType($fieldKey),
						'valueType' => $this->sdIsAutomaticField($rule, $fieldKey),
					];

					$aggregateRatingFromRule = $this->helper->aggregateRatingFromRule(
						$rule,
						Wb\arrayGet($fields, [$fieldKey, 'valueType'], Data\Sd::FIELD_AUTO)
					);
					if (!empty($aggregateRatingFromRule))
					{
						$fields[$fieldKey]['value'] = $aggregateRatingFromRule;
					}

					break;
			}
		}

		// gather everyone
		$this->spec = [
			'type'                     => $ruleType,
			'actualType'               => $actualRuleType,
			'useItemAuthor'            => true,
			'useDefaultImageIfMissing' => true,
			'fields'                   => $fields
		];

		return $this;
	}

	/**
	 * Fill-in fallback values that may not have provided by plugin.
	 *
	 * @return $this
	 * @throws \Exception
	 */
	protected function addMissingFields()
	{
		parent::addMissingFields();

		$fields = Wb\arrayGet($this->spec, 'fields', []);
		foreach ($fields as $fieldName => $fieldDef)
		{
			if (empty($this->sdData[$fieldName]))
			{
				switch ($fieldName)
				{
					case 'url':
						$this->sdData[$fieldName] = $this->requestInfo->get('page_url');
						break;
					case 'name':
						$this->sdData[$fieldName] = $this->requestInfo->getPageTitle();
						break;
					case 'description':
						$this->sdData[$fieldName] = $this->requestInfo->getMetaDescription();
						break;
					case 'provider':
						$this->addIdentity('provider', 'defaultPublisher');
						break;
				}
			}
		}

		return $this;
	}

	/**
	 * Build the offer records and remove useless individual items.
	 *
	 * @return bool
	 */
	protected function makeCompliant()
	{
		// CourseInstance
		$courseInstance = [
			'@type'      => Data\Sd::COURSE_INSTANCE,
			'courseMode' => Wb\arrayGet($this->sdData, 'courseMode', Data\Sd::COURSE_MODE_ONLINE),
		];

		if (Data\Sd::COURSE_MODE_ONLINE !== $courseInstance['courseMode'])
		{
			$location = Wb\arrayGet($this->sdData, 'location', '');
			if (!empty($location))
			{
				$courseInstance['location'] = $location;
			}
		}

		$instructorName = Wb\arrayGet($this->sdData, 'instructorName', '');
		if (!empty($instructorName))
		{
			$courseInstance['instructor'] = [
				'@type' => Data\Sd::PERSON,
				'name'  => $instructorName
			];
			$instructorDescription        = Wb\arrayGet($this->sdData, 'instructorDescription', '');
			if (!empty($instructorDescription))
			{
				$courseInstance['instructor']['description'] = $instructorDescription;
			}
		}

		$workload = Wb\arrayGet($this->sdData, 'workload', '');
		if (!empty($workload))
		{
			// Bad design, should have been courseWorkload from the start
			$courseInstance['courseWorkload'] = $workload;
		}

		// schedule extraction
		$repeatCount     = Wb\arrayGet($this->sdData, 'repeatCount', 0);
		$repeatFrequency = Wb\arrayGet($this->sdData, 'repeatFrequency', '');
		if (
			!empty($repeatCount)
			&&
			!empty($repeatFrequency)
		) {
			$courseSchedule = [
				'@type'           => Data\Sd::SCHEDULE,
				'repeatCount'     => $repeatCount,
				'repeatFrequency' => $repeatFrequency
			];

			$duration = Wb\arrayGet($this->sdData, 'duration', '');
			if (!empty($duration))
			{
				$courseSchedule['duration'] = $duration;
			}
			$startDate = Wb\arrayGet($this->sdData, 'dateStarted', '');
			if (!empty($startDate))
			{
				$courseSchedule['startDate'] = $startDate;
			}
			$endDate = Wb\arrayGet($this->sdData, 'dateEnded', '');
			if (!empty($endDate))
			{
				$courseSchedule['endDate'] = $endDate;
			}
		}
		if (!empty($courseSchedule))
		{
			// only one course instance supported
			$courseInstance['courseSchedule'] = $courseSchedule;
		}

		$this->sdData['hasCourseInstance'] = $courseInstance;

		// price
		$priceCategory = Wb\arrayGet($this->sdData, 'courseOfferCategory', Data\Sd::COURSE_OFFER_CATEGORY_FREE);
		$currency      = Wb\arrayGet($this->sdData, 'offerPriceCurrency', 'USD');
		if (
			!empty($this->sdData['offerPrice'])
			&&
			Data\Sd::COURSE_OFFER_CATEGORY_FREE !== $priceCategory
		) {
			$offer = [
				'@type'         => Data\Sd::OFFER,
				'price'         => Wb\arrayGet($this->sdData, 'offerPrice', 0),
				'priceCurrency' => $currency,
				'category'      => $priceCategory
			];

			$this->sdData['offers'] = [$offer];
		}
		else
		{
			$this->sdData['offers'] = [];
		}

		$this->sdData = array_diff_key(
			$this->sdData,
			array_flip(
				[
					'inLanguage',
					'offerPrice',
					'offerPriceCurrency',
					'offerCategory',
					'courseMode',
					'courseOfferCategory',
					'dateStarted',
					'dateEnded',
					'duration',
					'instructorName',
					'instructorDescription',
					'location',
					'repeatCount',
					'repeatFrequency',
					'workload'
				]
			)
		);

		return parent::makeCompliant();
	}

	/**
	 * Validate if an image can be used in an Article schema record.
	 *
	 * Notes:
	 * - Google rich snippets specs states that image should be present on page
	 * and have a minimal width of 696px (non-AMP) or 1200px (AMP) for Articles type items.
	 * - The requirement for more than 300,000 pixels is only stated as "for best results"
	 * so we do not enforce it.
	 *
	 * - This plugin is not handling AMP structured data as wbAMP already does.
	 * - even if image is invalid, not on page, or anything else, we're still going to
	 * include a json-ld record on the page because:
	 * a/ search engines may still use some of the data
	 * b/ this may change in the future
	 * c/ this will prompt user to add an image if they see the error.
	 *
	 * Considering that many types (ie VideoObject) accepts images smaller than Article, we'll just accept
	 * all images regarding of types.
	 *
	 * @param array $pageImage
	 * @return bool
	 */
	protected function isValidImage($pageImage)
	{
		return true;
	}
}
