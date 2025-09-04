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

use Joomla\CMS\Component\ComponentHelper;

use Weeblr\Forsef\Model;

use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Menu extends Base\Base
{
	/**
	 * @var Model\Routingconfig
	 */
	protected $routingConfig;

	/**
	 * @var Db\Helper
	 */
	protected $db;

	/**
	 * @var array Memoize articles defs
	 */
	private static $articles = [];

	/**
	 * @var array Memoize categories defs
	 */
	private static $categories = [];

	/**
	 * @var array Memoize "uncategorized" category details per extension
	 */
	private static $uncategorizedCat = [];

	/**
	 * Store a logger and config for convenience.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->db            = $this->factory->getThe('db');
		$this->routingConfig = $this->factory->getThis('forsef.config', 'routing');
	}

	/**
	 * Finds out if a specific menu item (either the menu item object
	 * or just the Itemid) is the site home page. On multilingual sites, also checks
	 * the menu item is of the default front end language.
	 *
	 * @param int | object $menuItemOrId
	 *
	 * @return bool
	 */
	public function isHomepageMenuItem($menuItemOrId, $inAnyLanguage = true)
	{
		static $defaultLanguage = null;

		if (empty($menuItemOrId))
		{
			return false;
		}

		if (is_null($defaultLanguage))
		{
			$defaultLanguageTag = $this->platform->getDefaultLanguageTag();
		}

		if (is_numeric($menuItemOrId))
		{
			$menuItemOrId = $this->platform->getMenu('site')->getItem($menuItemOrId);
		}

		if (empty($menuItemOrId->home))
		{
			return false;
		}

		if ($inAnyLanguage)
		{
			return true;
		}

		if ($this->platform->isMultilingual())
		{
			// language must be the default language
			return $menuItemOrId->language === $defaultLanguageTag;
		}

		return (
			$menuItemOrId->language === $defaultLanguageTag
			||
			$menuItemOrId->language === '*'
		);
	}

	/**
	 * Strip the menu alias from the start of a SEF URL, based on the provided menu item id.
	 *
	 * @param string $sef
	 * @param int    $menuItemId
	 * @return string
	 */
	public function stripMenuItem($sef, $menuItemId)
	{
		$menu     = $this->platform->getMenu('site');
		$menuItem = $menu->getItem($menuItemId);
		if (!empty($menuItem) && empty($menuItem->home))
		{
			$menuItemAlias = $menuItem->alias;
			if ($menuItemAlias !== $sef && Wb\startsWith($sef, $menuItemAlias))
			{
				$sef = Wb\lTrim(
					$sef,
					[
						$menuItemAlias,
						'/'
					]
				);
			}
		}

		return $sef;
	}

	/**
	 * Finds the best menu item match based on a request query variables set.
	 *
	 * @param string $option
	 * @param int    $id
	 * @param string $path
	 * @return array|mixed|string|string[]
	 */
	public function getMenuTitle($option, $id = null, $path = null)
	{
		$nameField = $this->routingConfig->get('useMenuAlias')
			? 'alias'
			: 'title';

		$menu = $this->platform->getMenu('site');

		$attr   = [];
		$values = [];
		if (!empty($path))
		{
			$attr[]   = 'link';
			$values[] = $path;
		}
		else if (!empty($id))
		{
			$attr[]   = 'id';
			$values[] = $id;
		}
		else if (!empty($option))
		{
			// need to find component id
			$component = ComponentHelper::getComponent(
				$option,
				true
			);
			if (!$component->enabled)
			{
				return ('/');
			}
			$attr[]   = 'component_id';
			$values[] = $component->id;
		}
		else
		{
			return '/';
		}

		// now ask J! to fetch menu item title
		$menuItem = $this->findMenuItem(
			$menu->getMenu('site'),
			$attr,
			$values,
			$firstOnly = true
		);

		if (!empty($menuItem))
		{
			$languages = $this->platform->getFrontendLanguages();
			foreach ($languages as $langId => $language)
			{
				// does it look like a home page, in any language?
				if (
					!empty($pageInfo->homeLinks[$language->lang_code])
					&&
					strpos($pageInfo->homeLinks[$language->lang_code], 'Itemid=' . $menuItem->id) !== false
				)
				{
					// is language filter set to remove lang code on default language?
					if (
						empty($langId)
						||
						(
							$language->sef == $this->platform->getLanguageUrlCode(
								$this->platform->getDefaultLanguageTag()
							)
							&&
							!$this->factory
								->getA(Language::class)
								->shouldInsertLangCodeInDefaultLanguage()
						)
					)
					{
						$slug = '';
					}
					else
					{
						$slug = $language->sef;
					}

					return $slug; // this is one of the homepages, return / or a lang code
				}
			}

			// non-homepage
			if (!empty($menuItem->{$nameField}))
			{
				return $menuItem->{$nameField};
			}
		}

		return Wb\lTrim($option, 'com_');
	}

	private function findMenuItem($menuItems, $attributes, $values, $firstonly = false)
	{
		$items      = [];
		$attributes = Wb\arrayEnsure($attributes);
		$values     = Wb\arrayEnsure($values);

		foreach ($menuItems as $item)
		{
			if (!is_object($item))
			{
				continue;
			}

			$test = true;
			for ($i = 0, $count = count($attributes); $i < $count; $i++)
			{
				if (is_array($values[$i]))
				{
					if (!in_array($item->{$attributes[$i]}, $values[$i]))
					{
						$test = false;
						break;
					}
				}
				else
				{
					if ($item->{$attributes[$i]} != $values[$i])
					{
						$test = false;
						break;
					}
				}
			}

			if ($test)
			{
				if ($firstonly)
				{
					return $item;
				}

				$items[] = $item;
			}
		}

		return $items;
	}
}
