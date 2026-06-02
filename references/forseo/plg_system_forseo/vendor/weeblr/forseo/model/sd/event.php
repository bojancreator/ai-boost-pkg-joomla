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
class Event extends Base
{
	/**
	 * @var string Schema type.
	 */
	protected $type = Data\Sd::EVENT;

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
				case 'eventAttendanceMode':
				case 'eventStatus':
				case 'name':
				case 'description':
				case 'offerPrice':
				case 'offerPriceCurrency':
				case 'offerUrl':
				case 'location':
				case 'locationName':
				case 'locationAddress':
					$fields[$lcItemKey] = [
						'type'      => $this->sdFieldType($lcItemKey),
						'valueType' => $this->sdIsAutomaticField($rule, $itemKey),
						'value'     => $this->helper->getCustomValueFromRule($rule, $key, true /* $raw */)
					];
					break;
				case 'offerAvailability':
					$value = $this->helper->getCustomValueFromRule($rule, $key, true /* $raw */);
					if (is_array($value))
					{
						$value = array_shift($value);
					}
					if (!empty($value))
					{
						// if unknown, do not create field
						$fields[$lcItemKey] = [
							'type'      => $this->sdFieldType($lcItemKey),
							'valueType' => $this->sdIsAutomaticField($rule, $itemKey),
							'value'     => $value
						];
					}
					break;

				case 'performer':
					$performer     = $this->helper->getCustomValueFromRule($rule, $key, true /* $raw */);
					$performerType = $this->helper->getCustomValueFromRule($rule, 'actionSdPerformerType', true /* $raw */);

					$fields[$lcItemKey] = [
						'valueType' => $this->sdIsAutomaticField($rule, $itemKey),
					];

					// auto mode, default is the detault PERSON
					// custom mode, type is TEXT
					$fields[$lcItemKey]['type'] = Data\Sd::FIELD_CUSTOM === Wb\arrayGet($fields, [$lcItemKey, 'valueType'], Data\Sd::FIELD_AUTO)
						? $this->sdFieldType($lcItemKey)
						: Data\Sd::PERSON;

					$fields[$lcItemKey]['value'] = [
						'@type' => empty($performerType)
							? Data\Sd::PERSON
							: $performerType,
						'name'  => $performer,
					];
					break;

				case 'organizer':
					$fields[$lcItemKey]          = [
						'valueType' => $this->sdIsAutomaticField($rule, $itemKey),
						'value'     => $this->helper->getCustomValueFromRule($rule, $key, true /* $raw */)
					];
					$fields[$lcItemKey]['value'] = [
						'@type' => Data\Sd::ORGANIZATION,
						'name'  => $fields[$lcItemKey]['value'],
					];
					break;
				case 'imageAuto':
					$fieldKey          = 'image';
					$fields[$fieldKey] = [
						'type'      => $this->sdFieldType($fieldKey),
						'valueType' => $this->sdIsAutomaticField($rule, $fieldKey)
					];

					$imageFromRule = $this->helper->imageFromRule(
						$rule,
						Wb\arrayGet($fields, [$fieldKey, 'valueType'], Data\Sd::FIELD_AUTO),
						'rawvalue'
					);
					if (!empty($imageFromRule))
					{
						$fields[$fieldKey]['value'] = $imageFromRule;
					}

					break;

				case 'dateStartedAuto':
					$fieldKey                     = 'startDate';
					$fields[$fieldKey]            = [
						'type'      => $this->sdFieldType($fieldKey),
						'valueType' => $this->sdIsAutomaticField($rule, 'dateStarted')
					];
					$fields['startDate']['value'] = $this->helper->dateFromRule('Started', $rule, Wb\arrayGet($fields, [$fieldKey, 'valueType'], Data\Sd::FIELD_AUTO));
					break;

				case 'dateEndedAuto':
					$fieldKey                   = 'endDate';
					$fields[$fieldKey]          = [
						'type'      => $this->sdFieldType($fieldKey),
						'valueType' => $this->sdIsAutomaticField($rule, 'dateEnded')
					];
					$fields[$fieldKey]['value'] = $this->helper->dateFromRule('Ended', $rule, Wb\arrayGet($fields, [$fieldKey, 'valueType'], Data\Sd::FIELD_AUTO));
					break;

				case 'offerDateValidFromAuto':
					$fieldKey                   = 'offerValidFrom';
					$fields[$fieldKey]          = [
						'type'      => $this->sdFieldType($fieldKey),
						'valueType' => $this->sdIsAutomaticField($rule, 'offerDateValidFrom')
					];
					$fields[$fieldKey]['value'] = $this->helper->dateFromRule('ValidFrom', $rule, Wb\arrayGet($fields, [$fieldKey, 'valueType'], Data\Sd::FIELD_AUTO), 'actionSdOfferDate', 'actionSdOfferTime');
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
	 * @return $this|Event
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
					case 'name':
						$this->sdData[$fieldName] = $this->requestInfo->getPageTitle();
						break;
					case 'offerUrl':
						$this->sdData[$fieldName] = $this->requestInfo->get('page_url');
						break;
					case 'organizer':
						$this->sdData['organizer'] = [
							'@id'   => $this->baseId . '#defaultPublisher',
							'@type' => Data\Sd::ORGANIZATION
						];
						$this->storeUsedIdentity('defaultPublisher');
						break;
					case 'eventStatus':
						$this->sdData[$fieldName] = Data\Sd::EVENT_STATUS_SCHEDULED;
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
		if (!empty($this->sdData['offerPrice']))
		{
			$offer                  = [
				'@type'         => Data\Sd::OFFER,
				'availability'  => Wb\arrayGet($this->sdData, 'offerAvailability', Data\Sd::OFFERS_IN_STOCK),
				'price'         => Wb\arrayGet($this->sdData, 'offerPrice', 0),
				'priceCurrency' => Wb\arrayGet($this->sdData, 'offerPriceCurrency', 'USD'),
				'validFrom'     => $this->formatIndividualProperty('validFrom', Wb\arrayGet($this->sdData, 'offerValidFrom')),
				'url'           => Wb\arrayGet($this->sdData, 'offerUrl'),
			];
			$this->sdData['offers'] = [$offer];
		}
		else
		{
			$this->sdData['offers'] = [];
		}

		// location: B/C
		$locationFieldValue = $this->sdData['location'];
		$url                = '';
		if (Wb\startsWith($locationFieldValue, ['http://', 'https://']))
		{
			$url = $locationFieldValue;
		}

		// prior to 6.1.0, there was no address field and location could be
		// used to store an addres. To avoid B/C break, if there's some text here
		// but it's not a URL, we assume it was an address
		$address = empty($this->sdData['locationAddress'])
				   &&
				   empty($url)
			? $locationFieldValue
			: Wb\arrayGet($this->sdData, 'locationAddress', '');

		$attendanceMode = Wb\arrayGet($this->sdData, 'eventAttendanceMode');
		$locationArray  = [];
		if (
			Data\Sd::ONLINE_EVENT_ATTENDANCE_MODE === $attendanceMode
			||
			Data\Sd::MIXED_EVENT_ATTENDANCE_MODE === $attendanceMode
		) {
			$locationArray[] = [
				'@type' => Data\Sd::VIRTUAL_LOCATION,
				'url'   => $url
			];
		}

		if (
			Data\Sd::OFFLINE_EVENT_ATTENDANCE_MODE === $attendanceMode
			||
			Data\Sd::MIXED_EVENT_ATTENDANCE_MODE === $attendanceMode
		) {
			$physicalLocation = [
				'@type'   => Data\Sd::PLACE,
				'address' => $address
			];
			$locationName     = $this->sdData['locationName'];
			if (!empty($locationName))
			{
				$physicalLocation['name'] = $locationName;
			}
			$locationArray[] = $physicalLocation;
		}

		$locationArray = count($locationArray) <= 1
			? $locationArray[0]
			: $locationArray;

		$this->sdData['location'] = $locationArray;

		// performer should not be empty
		$performer = Wb\arrayGet(
			$this->sdData,
			[
				'performer',
				'name'
			]
		);
		if (empty($performer))
		{
			unset($this->sdData['performer']);
		}

		$this->sdData = array_diff_key(
			$this->sdData,
			array_flip([
				'inLanguage',
				'locationName',
				'locationAddress',
				'offerAvailability',
				'offerPrice',
				'offerPriceCurrency',
				'offerValidFrom',
				'offerUrl',
				'performerType'
			])
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
