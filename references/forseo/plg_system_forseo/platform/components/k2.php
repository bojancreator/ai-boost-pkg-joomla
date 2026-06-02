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

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Db;
use Weeblr\Wblib\Forseo\Html;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Support for Hikashop.
 *
 * Keep this in mind: https://code.weeblr.com/weeblr/forseo/-/issues/278
 *
 */
class K2 extends Base
{
	/**
	 * @var string Component name - with leading com_ removed, eg content for com_content
	 */
	protected $component = 'k2';

	/**
	 * @var \stdClass Cache for items being rendered.
	 */
	protected $contentData = null;

	/**
	 * @var string[] Views we can store.
	 */
	protected $includedViews = [
		'itemlist',
		'item'
	];

	/**
	 * @var null|array List of layouts names that should be stored. None if null. All if empty.
	 */
	protected $includedLayouts = [];

	/**
	 * @var null|array List of layouts names that should NOT be stored. No effect if null or empty.
	 */
	protected $excludedLayouts = [];

	/**
	 * @var null|array List of variables/values that should cause a page to NOT be stored.
	 */
	protected $excludedInputVars = [
		'task' => ['add', 'edit']
	];

	/**
	 * @var array List of schema types supported by this plugin.
	 */
	protected $supportedSdTypes = [
		Data\Sd::ARTICLE,
		Data\Sd::NEWS_ARTICLE,
		Data\Sd::BLOG_POSTING,
		Data\Sd::VIDEO_OBJECT,
		Data\Sd::COURSE,
		Data\Sd::EVENT,
		Data\Sd::PRODUCT,
		Data\Sd::RECIPE,
		Data\Sd::FAQ_PAGE
	];

	/**
	 * @var bool Whether the plugin wants to filter raw, SEF URL. Time consuming, avoid if not needed.
	 */
	protected $filterShouldCollectUrlsFoundOnPage = false;

	/**
	 * @var Db\Helper Convenience Helper instance.
	 */
	protected $dbHelper;

	/**
	 * Register event handler
	 *
	 * @param array $options Can inject custom factory and platform.
	 */
	public function __construct($options = [])
	{
		parent::__construct($options);

		$this->dbHelper = $this->factory->getThe('db');
	}

	/**
	 * Filters page data collected at the onAfterRoute event.
	 *
	 * @param Data\Page $pageData
	 *
	 * @return Data\Page
	 * @throws \Exception
	 */
	protected function filterAfterRoutePageData($pageData)
	{
		$pageData = parent::filterAfterRoutePageData($pageData);

		$inputVars = $pageData->get('input_vars', []);
		$pageData->set('layout', Wb\arrayGet($inputVars, 'layout', ''));

		if (class_exists('\K2HelperUtilities'))
		{
			$k2params = \K2HelperUtilities::getParams('com_k2');
			$k2params->set('facebookMetatags', 0);
			$k2params->set('twitterMetatags', 0);
		}

		return $pageData;
	}

	/**
	 * Implement construction of com_contact item unique id.
	 *
	 * @param null|array     $id
	 * @param null|Data\Page $pageData
	 *
	 * @return       array
	 * @throws \Exception
	 */
	protected function filterPageBuildContentId($id, $pageData)
	{
		$id = $this->defaultPageBuildContentId($id, $pageData);

		// clean up id of item title
		$itemId = Wb\arrayGet($id, 'id');
		if (!empty($itemId))
		{
			$id['id'] = $this->helper->cleanIdsWithColons($itemId);
		}

		return $id;
	}

	/**
	 * Hook to store the finalized content data of current page.
	 *
	 * @param array $contentData
	 * @return mixed
	 */
	public function actionStorePreparedContent($contentData)
	{
		$context = Wb\arrayGet($contentData, 'context', '');

		if (!in_array($context, ['com_k2.item', 'com_k2.itemlist']))
		{
			return;
		}

		$this->contentData = $contentData;
	}

	/**
	 * Filter automatically detected images from content data object.
	 *
	 * @param array     $extractedImages
	 * @param string    $context       An option string representing the context, the content type.
	 * @param string    $content       Rendered content.
	 * @param Object    $contentObject Data object holding the content data.
	 * @param Data\Page $pageData      Collected request information.
	 * @param Data\Meta $pageMeta      Collected meta data about the request.
	 *
	 * @return array
	 *
	 */
	protected function filterExtractPageImagesFromContentData($extractedImages, $context, $content, $contentObject, $pageData, $pageMeta)
	{
		if ('com_k2.item' == $context)
		{
			$appConfig    = $this->factory->getThis('forseo.config', 'app');
			$imageSpec    = $appConfig->get('imageDetectionRequireSizeSd');
			$ogpImageSpec = $appConfig->get('imageDetectionRequireSizeOgp');

			$extractedImages['page_image']         = $this->detectComK2Images($contentObject, $imageSpec);
			$extractedImages['page_sharing_image'] = $this->detectComK2Images($contentObject, $ogpImageSpec);
		}

		return $extractedImages;
	}

	/**
	 * Detect whether a K2 item has a suitable image set
	 *
	 * NB: we default to Large instead of Medium
	 *
	 * @param Object $contentObject
	 * @param array  $imageSpec
	 *
	 * @return array|void
	 */
	private function detectComK2Images($contentObject, $imageSpec)
	{
		if (!empty($contentObject))
		{
			$imageSize = empty($contentObject->params) || !is_object($contentObject->params)
				? 'Large'
				: $contentObject->params->get('facebookImage', 'Large');

			// K2 defaults to "Medium", while those images are too small
			switch ($imageSize)
			{
				case 'XLarge':
					$imageSize = 'XLarge';
					break;
				default:
					$imageSize = 'Large';
					break;
			}

			$imageName = 'image' . $imageSize;
			$imageUrl  = empty($contentObject->{$imageName})
				? ''
				: $contentObject->{$imageName};
			$image     = $this->factory->getA(Helper\Meta::class)
									   ->validateImageFromContent(
										   $imageUrl,
										   $imageSpec
									   );
			// return right away if a valid image is found
			// User has set those images, they should be representative
			if (!empty($image))
			{
				return $image;
			}
		}

		return '';
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
	 * NB: Hikashop already lists products on category pages, we only provide for product views.
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
		$view = strtolower($pageData->get('view'));
		$task = Wb\arrayGet($pageData->get('input_vars'), 'task');
		if (
			!in_array($view, ['item'])
			||
			in_array($task, ['edit', 'add']))
		{
			return false;
		}

		return $canRunRule;
	}

	/**
	 * Build automatically computed structured data for a com_k2 article.
	 * K2 builds SD by default so not going to try customize that now.
	 * In the future, we may bring back some of this to allow creating hybrid SD rules
	 * where some data is custom and some is coming from this plugin.
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
		return $autoFieldsData;
	}

	/**
	 * Implement default construction of a content hash that may be computed
	 * from a raw content array as provided by the platform.
	 *
	 * @param string         $hash
	 * @param array          $contentData
	 * @param null|Data\Page $pageData
	 *
	 * @return string
	 * @throws \Exception
	 */
	protected function filterPageBuildContentHash($hash, $contentData, $pageData)
	{
		$context = Wb\arrayGet($contentData, 'context', '');

		if ('com_k2.item' !== $context)
		{
			return $hash;
		}

		$content = Wb\arrayGet($contentData, 'content');
		if (
			empty($content)
			||
			// Some extensions (GSD) create invalid records, missing some parts.
			!isset($content->catid)
			||
			!isset($content->author)
			||
			!isset($content->title)
		) {
			return $hash;
		}

		$id = $content->catid
			  . $content->text
			  . $content->imageLarge
			  . $content->imageMedium
			  . $content->imageSmall
			  . $content->imageXLarge
			  . $content->imageXSmall
			  . $content->language
			  . $content->title;

		return md5(json_encode($id));
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
		$view = strtolower($pageData->get('view'));
		$task = Wb\arrayGet($pageData->get('input_vars'), 'task');
		if (
			!in_array($view, ['item'])
			||
			in_array($task, ['edit', 'add']))
		{
			return $lastMod;
		}

		if (
			empty($this->contentData)
			||
			empty($this->contentData['content'])
		) {
			return $lastMod;
		}

		$lastMod = $this->contentData['content']->modified;
		$lastMod = empty($lastMod)
				   ||
				   '0000-00-00 00:00:00' == $lastMod
			? $this->contentData['content']->created
			: $lastMod;

		if (!empty($lastMod))
		{
			$lastMod = System\Date::siteToUTC($lastMod);
		}

		return $lastMod;
	}
}
