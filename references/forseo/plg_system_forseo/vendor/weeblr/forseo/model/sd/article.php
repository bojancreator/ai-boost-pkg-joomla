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
class Article extends Base
{
	/**
	 * @var string Schema type.
	 */
	protected $type = Data\Sd::ARTICLE;

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

		// Article type is the only one with 3 "sub-types", Article, BlogPosting and NewsArticle
		// They really are the same and are handled by the same "Article" plugin.
		$ruleType = (in_array($actualRuleType, [Data\Sd::BLOG_POSTING, Data\Sd::NEWS_ARTICLE]))
			? Data\Sd::ARTICLE
			: $actualRuleType;

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
				case 'url':
				case 'headline':
				case 'description':
					$fields[$lcItemKey] = [
						'type'      => $this->sdFieldType($lcItemKey),
						'valueType' => $this->sdIsAutomaticField($rule, $itemKey),
						'value'     => $this->helper->getCustomValueFromRule($rule, $key, true /* $raw */)
					];
					break;
				case 'publisher':
					$fields[$lcItemKey] = [
						'valueType' => $this->sdIsAutomaticField($rule, $itemKey),
						'value'     => $this->helper->getCustomValueFromRule($rule, $key, true /* $raw */)
					];
					// auto mode, default is ORGANIZATION
					// custom mode, type is TEXT
					$fields[$lcItemKey]['type'] = Data\Sd::FIELD_CUSTOM === Wb\arrayGet($fields, [$lcItemKey, 'valueType'], Data\Sd::FIELD_AUTO)
						? $this->sdFieldType($lcItemKey)
						: Data\Sd::ORGANIZATION;
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
				case 'datePublished':
					$fields[$lcItemKey]          = [
						'type'      => $this->sdFieldType($lcItemKey),
						'valueType' => $this->sdIsAutomaticField($rule, $itemKey)
					];
					$fields[$lcItemKey]['value'] = $this->helper->dateFromRule('Published', $rule, Wb\arrayGet($fields, [$lcItemKey, 'valueType'], Data\Sd::FIELD_AUTO));
					break;
				case 'dateModified':
					$fields[$lcItemKey]          = [
						'type'      => $this->sdFieldType($lcItemKey),
						'valueType' => $this->sdIsAutomaticField($rule, $itemKey),
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
	 * Fill-in fallback values that may not have provided by plugin.
	 *
	 * @return $this|Article
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

		if (empty($this->sdData['headline']))
		{
			$pageTitle = $this->requestInfo->get('page_title');
			if (!empty($pageTitle))
			{
				$this->sdData['headline'] = $pageTitle;
			}
		}
		return $this;
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
