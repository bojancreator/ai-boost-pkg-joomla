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
class Profilepage extends Base
{
	/**
	 * @var string Schema type.
	 */
	protected $type = Data\Sd::PROFILE_PAGE;

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
				case 'profileEntityType':
				case 'author':
				case 'profileAltName':
				case 'description':
				case 'socialProfiles':
					$fields[$lcItemKey] = [
						'type'      => $this->sdFieldType($lcItemKey),
						'valueType' => Data\Sd::FIELD_CUSTOM,
						'value'     => $this->helper->getCustomValueFromRule($rule, $key, true /* $raw */)
					];
					break;
				case 'imageUrl':
				case 'profileImage2Url':
				case 'profileImage3Url':
					$fieldKey          = Wb\rTrim($lcItemKey, 'Url');
					$fields[$fieldKey] = [
						'type'      => $this->sdFieldType($lcItemKey),
						'valueType' => Data\Sd::FIELD_CUSTOM,
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
				case 'datePublished':
					$fieldKey                   = 'dateCreated';
					$fields[$fieldKey]          = [
						'type'      => $this->sdFieldType($lcItemKey),
						'valueType' => Data\Sd::FIELD_CUSTOM,
					];
					$fields[$fieldKey]['value'] = $this->helper->dateFromRule('Published', $rule, Wb\arrayGet($fields, [$lcItemKey, 'valueType'], Data\Sd::FIELD_AUTO));
					break;
				case 'dateModified':
					$fields[$lcItemKey]          = [
						'type'      => $this->sdFieldType($lcItemKey),
						'valueType' => Data\Sd::FIELD_CUSTOM,
					];
					$fields[$lcItemKey]['value'] = $this->helper->dateFromRule('Modified', $rule, Wb\arrayGet($fields, [$lcItemKey, 'valueType'], Data\Sd::FIELD_AUTO));
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
	 * Build the offer records and remove useless individual items.
	 *
	 * @return bool
	 */
	protected function makeCompliant()
	{
		$author = Wb\arrayGet($this->sdData, 'author');
		if (!empty($author))
		{
			$this->sdData['mainEntity'] = [
				'@type' => $this->sdData['profileEntityType'],
				'name'  => $author
			];

			if (!empty($this->sdData['profileAltName']))
			{
				$this->sdData['mainEntity']['alternateName'] = $this->sdData['profileAltName'];
			}
			if (!empty($this->sdData['description']))
			{
				$this->sdData['mainEntity']['description'] = $this->sdData['description'];
			}

			$mainImage                           = Wb\arrayGet($this->sdData, ['image', 'url'], '');
			$profileImage2                       = Wb\arrayGet($this->sdData, ['profileImage2', 'url'], '');
			$profileImage3                       = Wb\arrayGet($this->sdData, ['profileImage3', 'url'], '');
			$this->sdData['mainEntity']['image'] = [];
			if (!empty($mainImage))
			{
				$this->sdData['mainEntity']['image'][] = $mainImage;
			}
			if (!empty($profileImage2))
			{
				$this->sdData['mainEntity']['image'][] = $profileImage2;
			}
			if (!empty($profileImage3))
			{
				$this->sdData['mainEntity']['image'][] = $profileImage3;
			}

			$socialProfiles = System\Strings::stringToCleanedArray(
				Wb\arrayGet($this->sdData, 'socialProfiles', ''),
				"\n"
			);

			if (!empty($socialProfiles))
			{
				$this->sdData['mainEntity']['sameAs'] = $socialProfiles;
			}
		}

		$this->sdData = array_diff_key(
			$this->sdData,
			array_flip(
				[
					'inLanguage',
					'profileEntityType',
					'author',
					'description',
					'profileAltName',
					'socialProfiles',
					'image',
					'profileImage2',
					'profileImage3',
					'datePublished'
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
