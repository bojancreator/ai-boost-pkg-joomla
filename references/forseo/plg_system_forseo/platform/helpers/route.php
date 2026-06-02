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

namespace Weeblr\Forseo\Platform\Helpers;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Routing-related helpers
 */
class Route extends Base\Base
{
	/**
	 * @var \JMenu|\Joomla\CMS\Menu\AbstractMenu Local copy of menu object.
	 */
	private $menu = null;

	/**
	 * @var CMSApplication|CMSApplicationInterface Local copy of
	 *     Joomla application.
	 */
	private $app = null;

	/**
	 * Route constructor. Stores a ref to platform app and menu object.
	 *
	 * @throws \Exception
	 */
	public function __construct()
	{
		parent::__construct();
		$this->app = Factory::getApplication();
	}

	/**
	 * Tries to get the value of a query variable from a menu item.
	 * If variable not specified, the entire menu query is returned.
	 *
	 * @param   int     $menuItemId
	 * @param   string  $varName
	 *
	 * @param   string  $default
	 *
	 * @return null|mixed
	 */
	public function getVarFromMenuItem($menuItemId, $varName = '', $default = '')
	{
		if (empty($menuItemId))
		{
			return $default;
		}

		/**
		 * WARNING: it is not possible to do app->getMenu() from the
		 * onAfterInitialize event. It has to be at or after onAfterRoute or else
		 * in some rare cases this will cause the remember me plugin to somewhat fail.
		 *
		 * See https://github.com/joomla/joomla-cms/issues/11541
		 * This will be fixed in a future Joomla release.
		 */
		$this->menu = empty($this->menu)
			? $this->app->getMenu()
			: $this->menu;

		$menuItem = $this->menu->getItem($menuItemId);
		if (empty($menuItem))
		{
			return $default;
		}

		$query = $menuItem->query;
		if ($menuItem->type === 'alias')
		{
			$newItem = $this->menu->getItem(
				$menuItem->getParams()->get('aliasoptions')
			);

			if ($newItem)
			{
				$query = array_merge(
					$query,
					$newItem->query
				);
			}
		}

		return empty($varName)
			? $query
			: Wb\arrayGet(
				$query,
				$varName,
				$default
			);
	}
}
