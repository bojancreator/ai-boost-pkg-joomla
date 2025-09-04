<?php
/**
 * Project: 4SEF
 *
 * @package          4SEF
 * @copyright        Copyright Weeblr llc - 2022 -2025
 * @author           Yannick Gaultier - Weeblr llc
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          2.6.2.644
 * @date        2025-06-02
 */

namespace Weeblr\Forsef\Helper;

use Joomla\Registry\Registry;
use Joomla\CMS\Component;

use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\Wb;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Platform extends Base\Base
{
	/**
	 * @var Registry
	 */
	static $contentGlobalParams;

	/**
	 * Figures out the default number of items per page for
	 *
	 * @param array $queryVars
	 * @param bool  $includeBlogLinks
	 * @return int
	 */
	public function getDefaultListLimit($queryVars, $includeBlogLinks = false)
	{
		// default value is general configuration list length param
		$defaultListLimit = $this->platform->getConfig()->get('list_limit', 10);

		// get elements of the url
		$menuItemId = Wb\arrayGet($queryVars, 'Itemid');
		$option     = Wb\arrayGet($queryVars, 'option');
		$layout     = Wb\arrayGet($queryVars, 'layout');
		if (empty($layout))
		{
			$layout = 'default';
		}
		$view = Wb\arrayGet($queryVars, 'view');

		// if there is a menu item, we can try read more params
		if (!empty($menuItemId))
		{

			$menu     = $this->platform->getMenu('site');
			$menuItem = $menu->getItem($menuItemId);
			if (empty($menuItem))
			{
				return $defaultListLimit;
			}

			$params = new Registry($menuItem->getParams());
			if ('alias' === $menuItem->type)
			{
				$menuItemId = $params->get('aliasoptions');
				$menuItem   = $menu->getItem($menuItemId);
				$params     = new Registry($menuItem->getParams());
			}

			// layout = blog and frontpage
			if (
				(
					$option == 'com_content'
					&&
					$layout == 'blog'
				)
				||
				(
					$option == 'com_content'
					&&
					$view == 'featured'
				)
			)
			{

				// Merge into default values for the component (set in Global confif)
				$params = $this->getContentGlobalParams()
							   ->merge($params);

				$num_leading_articles = $params->get('num_leading_articles');
				$num_intro_articles   = $params->get('num_intro_articles');
				// adjust limit and listLimit for page calculation as blog views include
				// # of links in the limit value, while it should not be included for
				// page number calculation
				$num_links = $includeBlogLinks ? $params->get('num_links') : 0;

				return $num_leading_articles + $num_intro_articles + $num_links;

			}

			// elements with a display_num parameter
			$displayNum       = (int)$params->get('display_num');
			$defaultListLimit = empty($displayNum)
				? $defaultListLimit
				: $displayNum;
		}

		return $defaultListLimit;
	}

	/**
	 * Memoize global content parameters.
	 *
	 * @return Registry
	 */
	private function getContentGlobalParams()
	{
		if (is_null(self::$contentGlobalParams))
		{
			self::$contentGlobalParams = clone Component\ComponentHelper::getParams('com_content');
		}

		return self::$contentGlobalParams;
	}
}