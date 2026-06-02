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
class Product extends Base
{
	/**
	 * @var string Schema type.
	 */
	protected $type = Data\Sd::PRODUCT;

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
				case 'name':
				case 'description':
				case 'offerPrice':
				case 'offerPriceCurrency':
				case 'sku':
				case 'offerUrl':
					$fields[$lcItemKey] = [
						'type'      => $this->sdFieldType($lcItemKey),
						'valueType' => $this->sdIsAutomaticField($rule, $itemKey),
						'value'     => $this->helper->getCustomValueFromRule($rule, $key, true /* $raw */)
					];
					break;
				case 'productGlobalIdType':
					$valueType = $this->sdIsAutomaticField($rule, $itemKey);
					if (Data\Sd::FIELD_CUSTOM === $valueType)
					{
						$value = $this->helper->productGlobalIdFromRule(
							$rule,
							$valueType
						);

						if (!empty($value))
						{
							$fields[Wb\arrayGet($value, 'productGlobalIdType')] =
								[
									'type'      => $this->sdFieldType($lcItemKey),
									'valueType' => $valueType,
									'value'     => Wb\arrayGet($value, 'productGlobalId')
								];
						}
					}

					break;
				case 'offerItemCondition':
				case 'offerAvailability':
					$value = $this->helper->getCustomValueFromRule($rule, $key, true /* $raw */);
					if (is_array($value))
					{
						$value = array_shift($value);
					}
					// special case for offerItemCondition: custom condition, entered manually in rule options
					$valueType = $this->sdIsAutomaticField($rule, $itemKey);
					if (
						'offerItemCondition' === $lcItemKey
						&&
						Data\Sd::FIELD_CUSTOM === $valueType
						&&
						'custom' === $value
						&&
						Wb\arrayIsFalsy($rule, 'offerItemConditionCfId')
					)
					{
						// user set a specific, custom condition value
						$value = Wb\ArrayGet(
							$rule,
							'actionSdOfferItemConditionCustom',
							''
						);
					}
					if (!empty($value))
					{
						$fields[$lcItemKey] = [
							'type'      => $this->sdFieldType($lcItemKey),
							'valueType' => $valueType,
							'value'     => $value
						];
					}
					break;

				case 'brand':
					$fields[$lcItemKey] = [
						'valueType' => $this->sdIsAutomaticField($rule, $itemKey),
						'value'     => $this->helper->getCustomValueFromRule($rule, $key, true /* $raw */)
					];
					if (Data\Sd::FIELD_CUSTOM === Wb\arrayGet($fields, [$lcItemKey, 'valueType'], Data\Sd::FIELD_AUTO))
					{
						$fields[$lcItemKey]['value'] = [
							'@type' => Data\Sd::ORGANIZATION,
							'name'  => $fields[$lcItemKey]['value']
						];
					}
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

				case 'offerDatePriceValidUntilAuto':
					$fieldKey                   = 'offerPriceValidUntil';
					$fields[$fieldKey]          = [
						'type'      => $this->sdFieldType($fieldKey),
						'valueType' => $this->sdIsAutomaticField($rule, 'offerDatePriceValidUntil')
					];
					$fields[$fieldKey]['value'] = $this->helper->dateFromRule('PriceValidUntil', $rule, Wb\arrayGet($fields, [$fieldKey, 'valueType'], Data\Sd::FIELD_AUTO), 'actionSdOfferDate', 'actionSdOfferTime');
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

				// Not implemented
				case 'reviewAuto':
					$fieldKey          = 'review';
					$fields[$fieldKey] = [
						'type'      => $this->sdFieldType($fieldKey),
						'valueType' => $this->sdIsAutomaticField($rule, $fieldKey)
					];
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
	 * @return $this|Product
	 * @throws \Exception
	 */
	protected function addMissingFields()
	{
		parent::addMissingFields();

		if (empty($this->sdData['mainEntityOfPage']))
		{
			$url                              = Wb\arrayGet(
				$this->sdData,
				'url',
				$this->requestInfo->get('page_url')
			);
			$this->sdData['mainEntityOfPage'] = [
				'@type' => 'WebPage',
				'url'   => $url
			];
		}

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
		if (isset($this->sdData['offerPrice']))
		{
			$offer = [
				'@type'         => Data\Sd::OFFER,
				'availability'  => Wb\arrayGet($this->sdData, 'offerAvailability', Data\Sd::OFFERS_IN_STOCK),
				'price'         => Wb\arrayGet($this->sdData, 'offerPrice', 0),
				'priceCurrency' => Wb\arrayGet($this->sdData, 'offerPriceCurrency', 'USD'),
				'url'           => Wb\arrayGet($this->sdData, 'offerUrl')
			];

			if (\is_array($offer['priceCurrency']))
			{
				$offer['priceCurrency'] = $offer['priceCurrency'][0];
			}

			$itemCondition = Wb\arrayGet($this->sdData, 'offerItemCondition');
			if (!empty($itemCondition))
			{
				$offer['itemCondition'] = $itemCondition;
			}

			$priceValidUntil = Wb\arrayGet($this->sdData, 'offerPriceValidUntil');
			if (!empty($priceValidUntil))
			{
				$offer['priceValidUntil'] = $this->formatIndividualProperty(
					'validUntil',
					Wb\arrayGet($this->sdData, 'offerPriceValidUntil')
				);
			}

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
					'offerAvailability',
					'offerPrice',
					'offerPriceCurrency',
					'offerPriceValidUntil',
					'offerUrl',
					'offerItemCondition'
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
