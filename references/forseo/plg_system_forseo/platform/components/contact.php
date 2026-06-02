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

use Weeblr\Forseo\Helper;

use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Html;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 */
class Contact extends Base
{
	/**
	 * @var string Component name - with leading com_ removed, eg content for com_content
	 */
	protected $component = 'contact';

	/**
	 * @var string[] com_content views we can store.
	 */
	protected $includedViews = [
		'contact',
		'category',
		'categories',
		'featured'
	];

	/**
	 * @var null|array List of layouts names that should NOT be stored. No effect if null or empty.
	 */
	protected $excludedLayouts = [];

	/**
	 * @var bool Whether the plugin wants to filter raw, SEF URL. Time consuming, avoid if not needed.
	 */
	protected $filterShouldCollectUrlsFoundOnPage = false;

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

		if ('contact' === Wb\arrayGet($id, 'view'))
		{
			unset($id['catid']);
			// menu item options won't change a single article content, we can drop it
			unset($id['Itemid']);
		}

		return $id;
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
		if (
			'com_contact.contact' === $context
			&&
			!empty($contentObject)
			&&
			!empty($contentObject->image)
		) {
			$appConfig    = $this->factory->getThis('forseo.config', 'app');
			$imageSpec    = $appConfig->get('imageDetectionRequireSizeSd');
			$ogpImageSpec = $appConfig->get('imageDetectionRequireSizeOgp');
			$metaHelper   = $this->factory->getA(Helper\Meta::class);

			$extractedImages['page_image'] = $metaHelper->validateImageFromContent(
				$contentObject->image,
				$imageSpec
			);

			$extractedImages['page_sharing_image'] = $metaHelper->validateImageFromContent(
				$contentObject->image,
				$ogpImageSpec
			);
		}

		return $extractedImages;
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
		$inputVars = $pageData->get('input_vars', []);
		$view      = Wb\arrayGet($inputVars, 'view', '');
		if (!in_array($view, ['contact', 'category']))
		{
			return $lastMod;
		}

		$itemId = (int)Wb\arrayGet($inputVars, 'id');
		if (empty($itemId))
		{
			return $lastMod;
		}

		if ('contact' == $view)
		{
			$dbTable                  = '#__contact_details';
			$modificationColumn       = 'modified';
			$modificationBackupColumn = 'created';
		}
		else
		{
			$dbTable                  = '#__categories';
			$modificationColumn       = 'modified_time';
			$modificationBackupColumn = 'created_time';
		}

		$modData = $this->factory
			->getThe('db')
			->selectAssoc(
				$dbTable,
				[$modificationColumn, $modificationBackupColumn],
				[
					'id' => $itemId
				]
			);

		if (empty($modData))
		{
			return null;
		}

		$lastMod = Wb\arrayGet($modData, $modificationColumn, '');
		$lastMod = empty($lastMod) || '0000-00-00 00:00:00' == $lastMod
			? Wb\arrayGet($modData, $modificationBackupColumn, null)
			: $lastMod;

		return $lastMod;
	}

	/**
	 * Filters whether the content described by in $pageData is considered archived. Will have an impact on
	 * sitemap inclusion.
	 *
	 * @param bool      $isArchived True if content hyas support for archiving and is archived, false otherwise.
	 * @param Data\Page $pageData
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function filterPageIsArchived($isArchived, $pageData)
	{
		$column = 'published';
		$def    = [
			'contact'  => '#__contact_details',
			'category' => '#__categories'
		];

		$inputVars = $pageData->get('input_vars', []);
		$view      = Wb\arrayGet($inputVars, 'view', '');
		if (!array_key_exists($view, $def))
		{
			return $isArchived;
		}

		$itemId = (int)Wb\arrayGet($inputVars, 'id');
		if (empty($itemId))
		{
			return $isArchived;
		}

		$dbTable = Wb\arrayGet($def, $view);

		$stateData = $this->factory
			->getThe('db')
			->selectAssoc(
				$dbTable,
				[$column],
				[
					'id' => $itemId
				]
			);

		$state = Wb\arrayGet($stateData, $column, null);

		return !empty($state) && 2 == $state;
	}

	/**
	 * Check whether the current plugin can retrieve a custom field value
	 * associated with the provided context string.
	 *
	 * @param string $context
	 * @return bool
	 */
	protected function isValidCustomFieldContext($context)
	{
		return Wb\startsWith(
			$context,
			'com_contact.contact'
		);
	}
}
