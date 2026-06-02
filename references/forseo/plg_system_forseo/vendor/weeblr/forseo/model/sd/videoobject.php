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
class Videoobject extends Base
{
	/**
	 * @var string Schema type.
	 */
	protected $type = Data\Sd::VIDEO_OBJECT;

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

		$fields = [];
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
				case 'thumbnailUrl':
				case 'contentUrl':
					$fields[$lcItemKey] = [
						'type'      => $this->sdFieldType($lcItemKey),
						'valueType' => $this->sdIsAutomaticField($rule, $lcItemKey),
						'value'     => $this->helper->getCustomValueFromRule($rule, $key, true /* $raw */)
					];
					break;
				case 'publisher':
					$fields[$lcItemKey] = [
						'valueType' => $this->sdIsAutomaticField($rule, $itemKey),
						'value'     => $this->helper->getCustomValueFromRule($rule, $key, true /* $raw */)
					];
					// auto mode, default is the default Organization
					// custom mode, type is TEXT
					$fields[$lcItemKey]['type'] = Data\Sd::FIELD_CUSTOM === $fields[$lcItemKey]['valueType']
						? $this->sdFieldType($lcItemKey)
						: Data\Sd::ORGANIZATION;
					break;
				case 'dateUploaded':
					$keyName                   = 'uploadDate';
					$fields[$keyName]          = [
						'type'      => $this->sdFieldType($lcItemKey),
						'valueType' => $this->sdIsAutomaticField($rule, $itemKey)
					];
					$fields[$keyName]['value'] = $this->helper->dateFromRule('Uploaded', $rule, $fields[$keyName]['valueType']);
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
	 * @return $this|Videoobject
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
					case 'thumbnailUrl':
						$pageImage = $this->getPageImage();
						if (empty($pageImage))
						{
							// default to organization logo
							$pageImage = $this->config->get('organizationLogo');
						}

						$this->sdData[$fieldName] = Wb\arrayGet($pageImage, 'url', '');
						break;
					case 'name':
						$this->sdData[$fieldName] = $this->requestInfo->getPageTitle();
						break;
				}
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
