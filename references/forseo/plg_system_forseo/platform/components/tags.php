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
use Weeblr\Wblib\Forseo\Wb;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 */
class Tags extends Base
{
	/**
	 * @var string Component name - with leading com_ removed, eg content for com_content
	 */
	protected $component = 'tags';

	/**
	 * @var string[] com_tags views we can store.
	 */
	protected $includedViews = [
		'tag',
		'tags'
	];

	/**
	 * @var bool Whether the plugin wants to filter raw, SEF URL. Time consuming, avoid if not needed.
	 */
	protected $filterShouldCollectUrlsFoundOnPage = false;

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

		if ($pageData->isFalsy('ignore'))
		{
			$inputVars = $pageData->get('input_vars', []);
			// update the item_id based on non-sef variables
			$pageData->set(
				'item_id',
				Wb\arrayGet(
					$inputVars,
					'id'
				)
			);
		}

		return $pageData;
	}

	/**
	 * Implement construction of com_tags item unique id.
	 *
	 * @param null|array     $id
	 * @param null|Data\Page $pageData
	 *
	 * @return       array
	 * @throws \Exception
	 */
	protected function filterPageBuildContentId($id, $pageData)
	{
		// do not override contentId computed by others.
		$id = $this->defaultPageBuildContentId($id, $pageData);

		// clean up id of item title
		$itemId = Wb\arrayGet($id, 'id');
		if (!empty($itemId))
		{
			if (
				is_array($itemId)
				&&
				count($itemId) === 1
				&&
				isset($itemId[0])
			) {
				// &$id=123 <==> &$id=[0]=123 if only one item in array and index is zero
				$itemId = $itemId[0];
			}

			$id['id'] = $this->helper->cleanIdsWithColons(
				$itemId,
				true // $forceIntegers
			);
		}

		// tags can be filtered by content type (Contact, Content, etc)
		$inputVars = $pageData->get('input_vars', []);
		$types     = Wb\arrayGet(
			$inputVars,
			'types'
		);

		if (!is_null($types))
		{
			$id['types'] = $this->helper->compactValuesList(
				$this->helper->cleanIdsWithColons(
					$types
				)
			);
		}

		$this->factory->getThe('forseo.logger')->debug('tags filterPageBuildContentId: %s, %s', print_r($id, true), print_r($pageData->get(), true));

		return $id;
	}
}
