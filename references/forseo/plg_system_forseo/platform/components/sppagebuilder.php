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

namespace Weeblr\Forseo\Platform\Components;

use Weeblr\Forseo\Data;
use Weeblr\Forseo\Helper;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;

use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Html;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 */
class Sppagebuilder extends Base
{
	/**
	 * @var string Component name - with leading com_ removed, eg content for com_content
	 */
	protected $component = 'sppagebuilder';

	/**
	 * @var string[] Views we can store.
	 */
	protected $includedViews = [];

	/**
	 * @var array List of schema types supported by this plugin.
	 */
	protected $supportedSdTypes = [
		Data\Sd::ARTICLE,
		Data\Sd::NEWS_ARTICLE,
		Data\Sd::BLOG_POSTING
	];

	/**
	 * @var Model\Config
	 */
	private $config;

	/**
	 * @var bool Flag to avoid extracting data from SPPB content data twice
	 */
	private $isContentDataParsed = false;

	/**
	 * Register event handler
	 *
	 * @param array $options Can inject custom factory and platform.
	 */
	public function __construct($options = [])
	{
		parent::__construct($options);

		$this->config = $this->factory
			->getThis('forseo.config', 'extensions');
	}

	/**
	 * Extract images from SPPB page data.
	 *
	 * @return void
	 * @throws \Exception
	 */
	private function loadContentData()
	{
		if (empty($this->contentData))
		{
			// Some SPPB versions / Website setups don't fire onContentPrepare
			// so we must load the data ourselves
			$model = BaseDatabaseModel::getInstance('Page', 'SppagebuilderModel');
			if (!empty($model))
			{
				$contentData       = $model->getItem();
				$this->contentData = is_array($contentData)
					? $contentData['content'] // SPPB v5
					: $contentData; // SPPB v3
			}
		}

		if (
			!empty($this->contentData)
			&&
			is_array($this->contentData)
			&&
			!empty($this->contentData['content'])
		) {
			// more recent versions of SPPB trigger onContentPrepare, so
			// the content data is already loaded at that event, and
			// we must normalize the format
			$this->contentData = $this->contentData['content'];
		}

		if (
			!empty($this->contentData)
			&&
			!$this->isContentDataParsed
		) {
			$this->isContentDataParsed = true;

			// extract image
			$itemParamsJson = $this->contentData->content ?? null;
			if (!empty($itemParamsJson))
			{
				$itemParams = json_decode(
					$itemParamsJson,
					true
				);
			}
			if (!empty($itemParams))
			{
				$requestInfo = $this->factory
					->getThe('forseo.requestInfo');
				$imageHelper = $this->factory->getA(
					Html\Image::class,
					[
						'cacheLocalImages'  => true,
						'cacheRemoteImages' => true
					]
				);
				$images      = [];

				foreach ($itemParams as $itemParam)
				{
					$itemColumns = Wb\arrayGet($itemParam, 'columns', []);
					foreach ($itemColumns as $itemColumn)
					{
						$itemAddons = Wb\arrayGet($itemColumn, 'addons', []);
						foreach ($itemAddons as $itemAddon)
						{
							$itemAddonName = Wb\arrayGet($itemAddon, 'name');
							if ('text_block' === $itemAddonName)
							{
								$images = Html\Extract::extractImages(
									Wb\arrayGet(
										$itemAddon,
										['settings', 'text']
									),
									[
										'wrapContentInHtmlDoc' => false,
										'currentUrl'           => System\Route::makeRootRelative(
											$requestInfo->get('page_url')
										),
										'onlyInternal'         => false,
										'stripAnchors'         => true,
										'skipRelative'         => false,
										'removeLeadingSlash'   => false,
										'queryVarsToStrip'     => [
											FORSEO_CRAWLER_CDN_BUST_VAR
										],
										'filter'               => '',
										'filterParams'         => null,
										'excludeUrls'          => [
											'/forseo/v1/cron'
										],
										'thorough'             => false,
										'rawUrlDecode'         => true,
										'dataAttrToReadFrom'   => ['data-src']
									]
								);
							}

							if ('image' === $itemAddonName)
							{
								$imageUrl = Wb\arrayGet(
									$itemAddon,
									['settings', 'image', 'src']
								);

								if (!empty($imageUrl))
								{
									$imageSize = $imageHelper->getImageSize($imageUrl);
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
							}

							if (!empty($images))
							{
								$image = array_shift($images);
								if (!empty($image))
								{
									$requestInfo->set(
										'page_image',
										$image
									);

									$requestInfo->set(
										'page_sharing_image',
										$image
									);
								}

								return;
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Decides whether a given SD rule can apply to the current page.
	 * By default is null.
	 * If a plugin can support, it sets it to true.
	 * If a plugin says this SD type cannot exist on this page, it sets it to false.
	 * Else leave as is.
	 *
	 * In the end, returned value must be true (ie at least one plugin can support and no other
	 * contradict) for the rule to run.
	 *
	 * NB: At this stage, it has already been checked that:
	 *
	 * - the current request is for this plugin extension
	 * - the current plugin lists the SD rule type in its $supportedSdTypes property.
	 *
	 * @param bool             $canRunRule
	 * @param array            $spec
	 * @param Data\Requestinfo $requestInfo
	 * @param Data\Page        $pageData
	 * @return bool
	 * @throws \Exception
	 */
	public function filterSdCanRunRule($canRunRule, $spec, $requestInfo, $pageData)
	{
		if ($this->config->isFalsy('sppagebuilderEnableStructuredData'))
		{
			return false;
		}

		if ('page' !== strtolower($pageData->get('view')))
		{
			return false;
		}

		return $canRunRule;
	}

	/**
	 * Build automatically computed structured data for a com_content article.
	 *
	 * @param array            $autoFieldsData
	 * @param array            $autoFields
	 * @param array            $spec
	 * @param Data\Requestinfo $requestInfo
	 * @param Data\Page        $pageData
	 * @param string           $baseId
	 * @return array
	 * @throws \Exception
	 */
	protected function filterSdData($autoFieldsData, $autoFields, $spec, $requestInfo, $pageData, $baseId)
	{
		if ($this->config->isFalsy('sppagebuilderEnableStructuredData'))
		{
			return $autoFieldsData;
		}

		if (!$this->shouldRunFilter($pageData))
		{
			return $autoFieldsData;
		}

		$this->loadContentData();

		if (empty($this->contentData))
		{
			return $autoFieldsData;
		}

		$createdOn  = $this->contentData->created_on;
		$modifiedOn = $this->contentData->modified;
		if (
			empty($createdOn)
			||
			Wb\startsWith($createdOn, '1970-')
		) {
			$createdOn = $modifiedOn;
		}

		if (array_key_exists('datePublished', $autoFields))
		{
			$autoFieldsData['sdData']['datePublished'] = $createdOn;
		}

		if (array_key_exists('dateCreated', $autoFields))
		{
			$autoFieldsData['sdData']['dateCreated'] = $createdOn;
		}

		if (array_key_exists('dateModified', $autoFields))
		{
			$autoFieldsData['sdData']['dateModified'] = $modifiedOn;
		}

		if (
			array_key_exists('author', $autoFields)
			&&
			isset($this->contentData->author_name)
		) {
			$authorUserName = $this->contentData->author_name;
			$authorId       = $this->factory
				->getA(Helper\Sd::class)
				->toId(
					$authorUserName
					. '_'
					. System\Auth::shortHash(
						$authorUserName
					)
				);

			$autoFieldsData['identitiesUsed']    = [
				'author' => $authorId
			];
			$autoFieldsData['identitiesCreated'] = [
				$authorId => [
					'@type' => Data\Sd::PERSON, // Person | Organization
					'name'  => $authorUserName
				]
			];

			$authorField = [
				'@id' => $baseId . '#' . $authorId
			];

			$autoFieldsData['sdData']['author'] = $authorField;
		}

		return $autoFieldsData;
	}

	/**
	 * Implement default construction of finding out modified_at date time.
	 *
	 * Use MYSQL format (Y-m-d H:i:s), assumes UTC.
	 *
	 * Null if unable to determine.
	 *
	 * @param null|string $lastMod
	 * @param Data\Page   $pageData
	 *
	 * @return null | string
	 * @throws \Exception
	 */
	protected function filterPageModifiedAt($lastMod, $pageData)
	{
		$this->loadContentData();

		return !empty($this->contentData)
			   &&
			   !empty($contentData->modified)
			? $this->contentData->modified
			: null;
	}
}
