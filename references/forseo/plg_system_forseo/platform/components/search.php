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

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 */
class Search extends Base
{
	/**
	 * @var string Component name - with leading com_ removed, eg content for com_content
	 */
	protected $component = 'search';

	/**
	 * @var null|array List of view names that should be stored. None if null..
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
	 * @param   array  $links
	 * @param   array  $originalLinks
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
					'/search{*}',
					'{?}{?}/search{*}',
					'{?}{?}/search{*}',
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
	 * Implement construction of com_search item unique id.
	 *
	 * @param null|array     $id
	 * @param null|Data\Page $pageData
	 *
	 * @return       array
	 * @throws \Exception
	 */
	protected function filterPageBuildContentId($id, $pageData)
	{
		return $this->defaultPageBuildContentId($id, $pageData);
	}
}
