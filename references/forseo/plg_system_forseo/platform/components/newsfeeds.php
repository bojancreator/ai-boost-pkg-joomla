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

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 */
class Newsfeeds extends Base
{
	/**
	 * @var string Component name - with leading com_ removed, eg content for com_content
	 */
	protected $component = 'newsfeeds';

	/**
	 * @var string[] views we can store: none for newsfeeds
	 */
	protected $includedViews = null;

	/**
	 * @var bool Whether the plugin wants to filter raw, SEF URL. Time consuming, avoid if not needed.
	 */
	protected $filterShouldCollectUrlsFoundOnPage = false;

	/**
	 * Implement construction of com_newsfeeds item unique id.
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
