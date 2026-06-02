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
use Weeblr\Wblib\Forseo\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Base class for Structured Data objects.
 *
 * @package Weeblr\Forseo\Data\Sd
 */
class Recipe extends Base
{
	/**
	 * @var string Schema type.
	 */
	protected $type = Data\Sd::RECIPE;

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
				case 'author':
					$fields[$lcItemKey] = [
						'type'      => $this->sdFieldType($lcItemKey),
						'valueType' => $this->sdIsAutomaticField($rule, $itemKey),
					];
					$authorFromRule     = $this->helper->authorFromRule(
						$rule,
						Wb\arrayGet($fields, [$lcItemKey, 'valueType'], Data\Sd::FIELD_AUTO)
					);
					if (!empty($authorFromRule))
					{
						$fields[$lcItemKey]['value'] = $authorFromRule;
					}
					break;
				case 'name':
				case 'recipeCategory':
				case 'recipeCuisine':
				case 'recipeYield':
				case 'keywords':
				case 'description':
				case 'prepTime':
				case 'cookTime':
				case 'totalTime':
				case 'calories':
				case 'contentUrl':
					$fields[$lcItemKey] = [
						'type'      => $this->sdFieldType($lcItemKey),
						'valueType' => $this->sdIsAutomaticField($rule, $itemKey),
						'value'     => $this->helper->getCustomValueFromRule($rule, $key, true /* $raw */)
					];
					break;
				case 'datePublished':
					$fields[$lcItemKey]          = [
						'type'      => $this->sdFieldType($lcItemKey),
						'valueType' => $this->sdIsAutomaticField($rule, $itemKey)
					];
					$fields[$lcItemKey]['value'] = $this->helper->dateFromRule('Published', $rule, Wb\arrayGet($fields, [$lcItemKey, 'valueType'], Data\Sd::FIELD_AUTO));
					break;
				case 'recipeInstructions':
				case 'recipeIngredient':
					$fields[$lcItemKey] = [
						'type'      => $this->sdFieldType($lcItemKey),
						'valueType' => $this->sdIsAutomaticField($rule, $itemKey),
						'value'     => $this->helper->getCustomValueFromRule($rule, $key, true /* $raw */)
					];
					if (Data\Sd::FIELD_CUSTOM === Wb\arrayGet($fields, [$lcItemKey, 'valueType'], Data\Sd::FIELD_AUTO))
					{
						// break down into lines
						$ingredientList              = System\Strings::stringToCleanedArray(
							$fields[$lcItemKey]['value'],
							"\n"
						);
						$fields[$lcItemKey]['value'] = empty($ingredientList)
							? []
							: $ingredientList;
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
	 * @return $this|Recipe
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
		if (
			(!empty($this->sdData['cookTime']) && empty($this->sdData['prepTime']))
			||
			(empty($this->sdData['cookTime']) && !empty($this->sdData['prepTime']))
		) {
			unset($this->sdData['cookTime']);
			unset($this->sdData['prepTime']);
		}

		if (!empty($this->sdData['cookTime']))
		{
			// we have prep and cook time, disregard total time
			unset($this->sdData['totalTime']);
		}

		if (!empty($this->sdData['calories']))
		{
			$this->sdData['nutrition'] = [
				'@type'    => Data\Sd::NUTRITION_INFORMATION,
				'calories' => $this->sdData['calories']
			];
		}

		if (!empty($this->sdData['contentUrl']))
		{
			$this->sdData['video'] = is_array($this->sdData['contentUrl'])
				? $this->sdData['contentUrl']
				: [
					'@type'        => Data\Sd::VIDEO_OBJECT,
					'name'         => $this->sdData['name'],
					'contentUrl'   => $this->sdData['contentUrl'],
					'thumbnailUrl' => Wb\arrayGet(
						$this->sdData['image'],
						'url',
						Wb\arrayGet($this->config->get('organizationLogo'), 'url', '')
					),
					'description'  => $this->sdData['description'],
					'uploadDate'   => $this->sdData['datePublished'],
				];
		}

		$this->sdData = array_diff_key(
			$this->sdData,
			array_flip([
				'calories',
				'contentUrl'
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

