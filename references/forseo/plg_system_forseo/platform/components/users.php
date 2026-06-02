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

use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Wb;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 */
class Users extends Base
{
	/**
	 * @var string Component name - with leading com_ removed, eg content for com_content
	 */
	protected $component = 'users';

	/**
	 * @var string[] component views we can store.
	 */
	protected $includedViews = null;

	/**
	 * @var bool Whether the plugin wants to filter raw, SEF URL. Time consuming, avoid if not needed.
	 */
	protected $filterShouldCollectUrlsFoundOnPage = true;

	/**
	 * Filters whether a specific URL collected from current page should be indeed
	 * stored to the collected URLs table.
	 *
	 * This preliminary test can only use the SEF URL because it's ran when the URL is found
	 * inside a page.
	 *
	 * @param array $links
	 * @param array $originalLinks
	 *
	 * @return array
	 */
	public function filterShouldCollectUrlsFoundOnPage($links, $originalLinks)
	{
		if (empty($links))
		{
			return $links;
		}

		$filteredLinks = array_filter(
			$links,
			function ($link)
			{

				$rules = [
					'/component/users/reset{*}',
					'/component/users/remind{*}',
					'{?}{?}/component/users/reset{*}',
					'{?}{?}/component/users/remind{*}',
				];

				foreach ($rules as $rule)
				{
					if (System\Route::matchUrlRule($rule, $link))
					{
						return false;
					}
				}


				return true;
			}
		);

		return empty($filteredLinks)
			? []
			: $filteredLinks;
	}

	/**
	 * Implement construction of com_users item unique id.
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
}
