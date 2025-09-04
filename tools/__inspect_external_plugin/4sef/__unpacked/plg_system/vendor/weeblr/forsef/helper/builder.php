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

use Weeblr\Forsef\Data;
use Weeblr\Forsef\Model;

use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\Db;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Joomla\StringHelper\StringHelper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Builder extends Base\Base
{
	/**
	 * @var Model\Config
	 */
	protected $routingConfig;

	/**
	 * @var Model\Config
	 */
	protected $extensionsConfig;

	/**
	 * @var Db\Helper
	 */
	protected $db;

	/**
	 * Store configs for convenience.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->db               = $this->factory->getThe('db');
		$this->routingConfig    = $this->factory->getThis('forsef.config', 'routing');
		$this->extensionsConfig = $this->factory->getThis('forsef.config', 'extensions');
	}

	public function getContentSlugs($view, $id, $layout, $options = [])
	{
		$Itemid = Wb\arrayGet($options, 'Itemid', 0);
		$shLang = Wb\arrayGet($options, 'shLang', null);

		$slugsArray  = [];
		$prefixArray = [];

		$id = empty($id) ? 0 : (int)$id;

		$requestedLanguage = empty($shLang) ? '*' : $shLang;

		try
		{
			$slugsHelper   = $this->factory->getA(Slugs::class);
			$menuItemTitle = $this->factory->getA(Menu::class)
										   ->getMenuTitle(
											   null,
											   $Itemid,
											   '',
											   $shLang
										   );

			$menuItemTitle = '/' === $menuItemTitle
				? ''
				: $menuItemTitle;

			$uncategorizedPath = Data\Config::UNCAT_SLUG_ITEM_TITLE === $this->extensionsConfig->getInt('contentSlugForUncategorizedContent')
				? ''
				: $menuItemTitle;

			switch ($view)
			{
				case 'category':
					if (empty($layout) || 'default' == $layout)
					{
						if (!empty($Itemid))
						{
							$menuItem = $this->platform
								->getMenu('site')
								->getItem($Itemid);
						}

						if (
							!empty($menuItem)
							&&
							$menuItem->component == 'com_content'
							&&
							isset(
								$menuItem->query['view'],
								$menuItem->query['id']
							)
							&&
							$menuItem->query['view'] == 'category'
							&&
							$menuItem->query['id'] == $id)
						{
							// J4
							$layout = empty($menuItem->query['layout'])
								? ''
								: $menuItem->query['layout'];
						}

						// Legacy code for reference. Use case not clearly established.
//						if (empty($layout))
//						{
//							$categories = Categories::getInstance(
//								'content',
//								[
//									'access'    => false,
//									'published' => 0
//								]
//							);
//
//							$category = $categories->get($id);
//							if (empty($category))
//							{
//								$layout = 'default';
//							}
//							else
//							{
//								$cparams          = $category->getParams();
//								$category->params = $this->platform->isFrontend()
//									? clone $this->platform->getAppParams()
//									: $this->backendFallbackGetParams('com_content');
//								$category->params->merge($cparams);
//								$paramsLayout = $category->params->get('category_layout');
//								if (Wb\contains($paramsLayout, ':'))
//								{
//									$temp   = explode(':', $paramsLayout);
//									$layout = $temp[1];
//								}
//								else
//								{
//									$layout = $paramsLayout;
//								}
//							}
//						}
					}

					if ($layout != 'blog')
					{
						if ($this->extensionsConfig->isTruthy('contentInsertContentTableName'))
						{
							$prefix = $this->extensionsConfig->isFalsy('contentContentTableName')
								? $menuItemTitle
								: $this->extensionsConfig->get('contentContentTableName', '');
							if (!empty($prefix))
							{
								$prefixArray[] = $prefix;
							}
						}

						if (!empty($id))
						{ // we have a category id
							$slugsArray = $slugsHelper->getCategorySlugArray(
								'com_content',
								$id,
								(int)$this->extensionsConfig->get('contentIncludeContentCatCategories'),
								$this->extensionsConfig->get('contentUseCategoryAlias'),
								false, // $insertId
								$uncategorizedPath,
								$requestedLanguage
							);
						}
						else
						{  // no category id, use menu item title
							if (
								$this->extensionsConfig->isFalsy('contentInsertContentTableName')
								||
								$this->extensionsConfig->isFalsy('contentContentTableName')
							)
							{
								if (!empty($menuItemTitle))
								{
									$slugsArray[] = $menuItemTitle;
								}
							}
						}
					}

					if ($layout == 'blog')
					{  // blog category
						if ($this->extensionsConfig->isTruthy('contentInsertContentBlogName'))
						{
							$prefix = $this->extensionsConfig->isFalsy('contentContentBlogName')
								? $menuItemTitle
								: $this->extensionsConfig->get('contentContentBlogName');
							if (!empty($prefix))
							{
								$prefixArray[] = $prefix;
							}
						}
						if (!empty($id))
						{
							$slugsArray = $slugsHelper->getCategorySlugArray(
								'com_content',
								$id,
								(int)$this->extensionsConfig->get('contentIncludeContentCatCategories'),
								$this->extensionsConfig->get('contentUseCategoryAlias'),
								$insertId = false,
								$uncategorizedPath,
								$requestedLanguage
							);
						}
						else
						{ // this should not happen, probably a malformed url
							if (
								$this->extensionsConfig->isFalsy('contentInsertContentBlogName')
								||
								$this->extensionsConfig->isFalsy('contentContentBlogName')
							)
							{
								if (!empty($menuItemTitle))
								{
									$slugsArray[] = $menuItemTitle;
								}
							}
						}
					}

					if (!empty($prefixArray))
					{
						$slugsArray = array_merge(
							$prefixArray,
							$slugsArray
						);
					}

					$slugsArray[] = '/';
					break;

				case 'categories':
					// now get category(ies) path
					if (!empty($id))
					{
						$slugsArray = $slugsHelper->getCategorySlugArray(
							'com_content',
							$id,
							(int)$this->extensionsConfig->get('contentIncludeContentCatCategories'),
							$this->extensionsConfig->get('contentUseCategoryAlias'),
							$insertId = false,
							$uncategorizedPath,
							$requestedLanguage
						);
						// insert a suffix to distinguish from normal category listing
						if ($this->extensionsConfig->isTruthy('contentContentCategoriesSuffix'))
						{
							$slugsArray[] = $this->extensionsConfig->get('contentContentCategoriesSuffix');
						}

						// end with a directory sign
						$slugsArray[] = '/';
					}
					else
					{
						if (!empty($menuItemTitle))
						{
							$slugsArray[] = $menuItemTitle;
						}
					}
					break;

				case 'featured' :
					if (!empty($menuItemTitle))
					{
						$slugsArray[] = $menuItemTitle;
					}
					break;

				case 'article':
					$article  = $slugsHelper->getArticle($id);
					$language = $requestedLanguage;
					if (empty($article[$requestedLanguage]))
					{
						$language = '*';
					}
					// still no luck, use whatever is available
					if (empty($article[$language]))
					{
						$languages = array_keys($article);
						$language  = array_shift($languages);
					}
					// get category(ies)
					// special case for the "uncategorised" category
					$unCat = $this->factory->getA(Content::class)
										   ->getUncategorizedCat('com_content');
					if (
						!empty($unCat)
						&&
						$article[$language]->catid == $unCat->id
					)
					{
						$slugsArray = empty($uncategorizedPath)
							? []
							: [$uncategorizedPath];
					}
					else
					{
						$slugsArray = $slugsHelper->getCategorySlugArray(
							'com_content',
							$article[$language]->catid,
							(int)$this->extensionsConfig->get('contentIncludeContentCat'),
							$this->extensionsConfig->get('contentUseCategoryAlias'),
							$insertId = false,
							$uncategorizedPath,
							$requestedLanguage
						);
					}
					// get article slug, optionnally including article id inurl
					$insertIdCatList = Data\Config::CONTENT_INSERT_ARTICLE_ID_NONE !== $this->extensionsConfig->get('contentContentTitleInsertArticleId')
						? $this->extensionsConfig->get('contentInsertContentArticleIdCatList', [])
						: [];
					$articleSlug     = $slugsHelper->getArticleSlug(
						$id,
						$this->extensionsConfig->get('contentUseTitleAlias'),
						$this->extensionsConfig->get('contentContentTitleInsertArticleId'),
						$insertIdCatList,
						$requestedLanguage
					);
					$slugsArray[]    = $articleSlug;
					break;

				default :
					break;
			}
		}
		catch (\Exception $e)
		{
			$this->factory->getThe('forsef.logger')->error(__METHOD__ . ', %s::%d %s %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}

		return $slugsArray;
	}

	/**
	 * Legacy code for reference. Use case not clearly established.
	 *
	 * Get the application parameters
	 *
	 * @param string $option The component option
	 *
	 * @return  Registry  The parameters object
	 *
	 * @since   3.2
	 */
//	private function backendFallbackGetParams($option)
//	{
//		static $params = [];
//
//		$hash = '__default';
//
//		if (!empty($option))
//		{
//			$hash = $option;
//		}
//
//		if (!isset($params[$hash]))
//		{
//			// get configuration
//			$platformConfig = $this->platform->getConfig();
//
//			// Get new instance of component global parameters
//			$params[$hash] = clone ComponentHelper::getParams($option);
//
//			// Get menu parameters
//			$menus = $this->platform->getMenu('site');
//			$menu  = $menus->getActive();
//
//			$title = $platformConfig->get('sitename');
//
//			$lang_code = $this->platform->getCurrentLanguageTag();
//			$languages = $this->platform->getFrontendLanguages();
//			foreach ($languages as $language)
//			{
//				if ($language->lang_code === $lang_code && !empty($language->metadesc))
//				{
//					$description = $language->metadesc;
//					break;
//				}
//			}
//			$description = empty($description)
//				? $platformConfig->get('MetaDesc')
//				: $description;
//
//			$rights = $platformConfig->get('MetaRights');
//			$robots = $platformConfig->get('robots');
//
//			// Retrieve com_menu global settings
//			$temp = clone ComponentHelper::getParams('com_menus');
//
//			// Lets cascade the parameters if we have menu item parameters
//			if (is_object($menu))
//			{
//				// Get show_page_heading from com_menu global settings
//				$params[$hash]->def(
//					'show_page_heading',
//					$temp->get('show_page_heading')
//				);
//
//				$params[$hash]->merge($menu->params);
//				$title = $menu->title;
//			}
//			else
//			{
//				// Merge com_menu global settings
//				$params[$hash]->merge($temp);
//
//				// If supplied, use page title
//				$title = $temp->get('page_title', $title);
//			}
//
//			$params[$hash]->def('page_title', $title);
//			$params[$hash]->def('page_description', $description);
//			$params[$hash]->def('page_rights', $rights);
//			$params[$hash]->def('robots', $robots);
//		}
//
//		return $params[$hash];
//	}
}
