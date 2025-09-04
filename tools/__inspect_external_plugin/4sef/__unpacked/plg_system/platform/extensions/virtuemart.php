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

namespace Weeblr\Forsef\Platform\Extensions;

use Joomla\CMS\Uri;

use Weeblr\Forsef\Data;
use Weeblr\Forsef\Platform\Extensions\Helpers;

use Weeblr\Wblib\Forsef\Wb;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Virtuemart extends Base
{
	/**
	 * @var Helpers\Virtuemart Private instance of a URL helper.
	 */
	private $virtuemartHelper;

	/**
	 * Stores factory instance and other stuff.
	 *
	 * @param string $option  Extension this applies to, in com_xxx format.
	 * @param array  $options Can inject custom factory and platform.
	 */
	public function __construct($option, $options = [])
	{
		parent::__construct($option, $options);

		$this->loadVirtuemartRouter()
			 ->loadVirtuemartHelper();
	}

	public function postProcessParsing($vars, &$uri)
	{
		$view         = Wb\arrayGet($vars, 'view');
		$requestStart = (int)$uri->getVar('start');

		if (
			!empty($requestStart)
			&&
			Wb\arrayIsFalsy($vars, 'start')
		)
		{
			$vars['limitstart'] = $requestStart;
			$uri->delVar('limitstart');
		}

		if (
			Wb\arrayIsTruthy($vars, 'limitstart')
			&&
			Wb\arrayIsFalsy($vars, 'start')
		)
		{
			$uri->setVar('start', $vars['limitstart']);
			$vars['start'] = $vars['limitstart'];
			$uri->delVar('limitstart');
		}

		// VM reads limitstart from session when missing in non-sef, instead of just
		// defaulting to zero. Try to fix that.
		if (
			Wb\arrayIsFalsy($vars, 'limitstart')
			&&
			Wb\arrayIsFalsy($vars, 'start')
			&&
			in_array(
				$view,
				[
					'category',
					'categories'
				]
			))
		{
			$uri->setVar('limitstart', 0);
		}

		// Joomla 4: need to feed parsed vars into \vmRequest or they are not seen
		// Just like when we switched to Joomla 3!
		if (
			!empty($vars)
			&&
			$this->platform->majorVersion() > 3
		)
		{
			if (!class_exists('\vRequest'))
			{
				$fileName = JPATH_ROOT . '/administrator/components/com_virtuemart/helpers/vrequest.php';
				if (file_exists($fileName))
				{
					include_once $fileName;
				}
			}
			if (!class_exists('\VmConfig'))
			{
				$fileName = JPATH_ROOT . '/administrator/components/com_virtuemart/helpers/config.php';
				if (file_exists($fileName))
				{
					include_once $fileName;
				}
			}
			if (
				class_exists('\vRequest')
				&&
				class_exists('\VmConfig')
			)
			{
				foreach ($vars as $key => $value)
				{
					\vRequest::setVar($key, $value);
				}
				// turns out using vRequest::setVar() is not enough, we need to call
				// setRouterVars() as well. Not sure since when...
				// Yes, it hurts a bit
				$_GET = array_merge(
					$_GET,
					$vars
				);
				\VmConfig::loadConfig();
				\vRequest::setRouterVars();
			}
		}

		return $vars;
	}

	/**
	 * Builds the SEF URL for a non-sef.
	 *
	 * @param Uri\Uri $uriToBuild
	 * @param Uri\Uri $platformUri
	 * @param Uri\Uri $originalUri
	 *
	 * @return \array
	 * @throws \Exception
	 */
	public function build($uriToBuild, $platformUri, $originalUri)
	{
		$sefSegments = [];

		$view   = $uriToBuild->getVar('view');
		$task   = $uriToBuild->getVar('task');
		$layout = $uriToBuild->getVar('layout');
		$id     = $uriToBuild->getVar('id');
		$Itemid = $uriToBuild->getVar('Itemid', 0);
		$catId  = $uriToBuild->getVar('virtuemart_category_id');

		$originalVars = $originalUri->getQuery(
			true // asArray
		);

		// query can be modified by preprocess
		$this->virtuemartHelper->preprocess($originalVars);

		$shopName   = $this->getShopName($Itemid);
		$isHomepage = $this->platform->isAnyHomepageFromVars($originalVars);

		$menu       = $this->platform->getMenu('site');
		$menuItemid = empty($originalVars['Itemid'])
			? $this->virtuemartHelper->menu('virtuemart')
			: $originalVars['Itemid'];
		$menuItem   = $menu->getItem($menuItemid);

		if (
			$this->extensionsConfig->isFalsy('virtuemartUseMenuItems')
			&&
			'productdetails' === $view
		)
		{
			$productId = $uriToBuild->getVar('virtuemart_product_id');
			if (empty($catId))
			{
				// product category was not passed in the request, find it from the product itself
				$catId = $this->getVmProductCategory($productId);
			}

			if (empty($catId))
			{
				// use parent if none found
				$catId = $this->virtuemartHelper->getParentProductcategory($productId);
			}

			if (!empty($catId))
			{
				$categoryNames = $this->virtuemartHelper->getCategoryNames($catId);
				if (!empty($categoryNames))
				{
					$catNamesArray = explode('/', $categoryNames);
					switch ($this->extensionsConfig->getInt('virtuemartWhichProductDetailsCat'))
					{
						case Data\Config::CAT_ALL_NESTED:
							break;
						case Data\Config::CAT_NONE:
							$catNamesArray = [];
							break;
						case Data\Config::CAT_FIRST:
							$catNamesArray = [
								array_shift($catNamesArray)
							];
							break;
						case Data\Config::CAT_LAST:
							$catNamesArray = [
								array_pop($catNamesArray)
							];
							break;
						case Data\Config::CAT_FIRST_TWO:
							while (count($catNamesArray) > 2)
							{
								array_pop($catNamesArray);
							}
							break;
						case Data\Config::CAT_LAST_TWO:
							while (count($catNamesArray) > 2)
							{
								array_shift($catNamesArray);
							}
							break;
						default:
							throw new \Exception(
								'Invalid configuration option (' . print_r($id) . ') passed to ' . __METHOD__ . ' in ' . __CLASS__,
								500
							);;
							break;
					}

					$sefSegments = array_merge(
						$sefSegments,
						$catNamesArray
					);
				}
				else
				{
					$sefSegments[] = $catId;
				}
			}

			$sefSegments[] = $this->virtuemartHelper->getProductName($productId);

			if (!empty($task))
			{
				$sefSegments[] = $this->virtuemartHelper->lang($task);
			}
			if (!empty($layout))
			{
				$sefSegments[] = $this->virtuemartHelper->lang($layout);
			}

			// add shop menu item, if asked to
			if ($this->extensionsConfig->get('virtuemartInsertShopName'))
			{
				array_unshift(
					$sefSegments,
					$shopName
				);
			}
		}

		if (
			$this->extensionsConfig->isTruthy('virtuemartUseMenuItems')
			||
			'productdetails' !== $view
		)
		{
			if (empty($sefSegments))
			{
				if (
					!empty($catId)
					&&
					empty($view)
				)
				{
					$view = 'category';
					$uriToBuild->setVar('view', $view);
					$originalVars['view'] = $view;
				}

				// check for legacy shop root url, else normal routing
				if (
					'virtuemart' === $uriToBuild->getVar('view', '')
					&&
					!$isHomepage)
				{
					// if VM is homepage, then empty URL is fine else use menu item alias as slug
					$sefSegments[] = $shopName;
				}
				else
				{
					$hasCategoryId = (
										 'category' === $view
										 ||
										 'productdetails' === $view
									 )
									 &&
									 !empty($catId);

					$isProductView = 'productdetails' === $view
									 &&
									 !empty($uriToBuild->getVar('virtuemart_product_id'));

					$isCategoryView = 'category' === $view
									  &&
									  !empty($catId);

					$isCartView = 'cart' === $view;

					$isUserView = 'user' === $view;

					// have Virtuemart own router.php build url.
					// NB: the $originalVars array will be modified by router.php, with used variables removed.
					if (\function_exists('\VirtuemartBuildRoute'))
					{
						$sefSegments = \VirtuemartBuildRoute(
							$originalVars
						);
					}
					else
					{
						$sefSegments = $this->virtuemartHelper->buildRoute($originalVars);
					}

					// VM can return a non-empty array, with only an empty string in it
					$sefSegments = \array_filter($sefSegments);

					if ($isProductView)
					{
						//if only option and Itemid left, VM wants Joomla router to prepend menu item. Let's do that
						$shouldInsertMenuItem = empty($sefSegments)
												&&
												2 === count($originalVars)
												&&
												!empty($originalVars['Itemid'])
												&&
												!empty($originalVars['option']);

						$askAQuestionTask = !empty($task)
											&&
											'askquestion' === $task
											&&
											3 === count($originalVars)
											&&
											!empty($originalVars['Itemid'])
											&&
											!empty($originalVars['option'])
											&&
											!empty($originalVars['tmpl']);

						if ($shouldInsertMenuItem || $askAQuestionTask)
						{
							$validMenuItem = $menu->getItem($originalVars['Itemid']);
							if (!empty($validMenuItem))
							{
								$validItemid = $originalVars['Itemid'];
							}
						}

						if (!empty($validItemid))
						{
							// we now use the calculated Itemid, either the original one
							// or the one that was swapped in by Virtuemart router.php
							$uriToBuild->setVar(
								'Itemid',
								$validItemid
							);

							$prodRoute = empty($validMenuItem) || $this->menuHelper->isHomepageMenuItem($validMenuItem)
								? ''
								: $validMenuItem->route;

							if (!empty($prodRoute))
							{
								array_unshift(
									$sefSegments,
									$prodRoute
								);
							}

							$hasCategoryId = false;
						}
					}

					// VM router set the Itemid for category links!!!!
					// instead of doing the routing
					if ($hasCategoryId)
					{
						//if only option and Itemid left, VM wants Joomla router to prepend menu item. Let's do that
						if (
							2 === count($originalVars)
							&&
							!empty($originalVars['Itemid'])
							&&
							!empty($originalVars['option'])
						)
						{
							$validMenuItem = $menu->getItem($originalVars['Itemid']);
							if (!empty($validMenuItem))
							{
								$validItemid = $originalVars['Itemid'];
							}
						}

						if ($isCategoryView)
						{
							unset($originalVars['categorylayout']);
							$validMenuItem = $menu->getItem($originalVars['Itemid']);
							if (!empty($validMenuItem))
							{
								$validItemid = $originalVars['Itemid'];
							}
						}

						if (!empty($validItemid))
						{
							// we now use the calculated Itemid, either the original one
							// or the one that was swapped in by Virtuemart router.php
							$uriToBuild->setVar(
								'Itemid',
								$validItemid
							);

							// then stick the category route
							// adjust to change to getCategoryRoute, somewhere around VM 3.0.x: requires $manufacturer Id as param #2
							$categoryRoute = $this->virtuemartHelper->getCategoryRoute(
								$catId,
								$uriToBuild->getVar('virtuemart_manufacturer_id', 0)
							);

							if (empty($categoryRoute) || empty($categoryRoute->route))
							{
								$categoryItemid = empty($categoryRoute->itemId)
									? $categoryRoute->Itemid
									: $categoryRoute->itemId;

								// adjust to change to getCategoryRoute, somewhere around VM 3.0.x: ->itemId has become ->Itemid
								if (!empty($categoryItemid))
								{
									$menuItem = $menu->getItem($categoryItemid);
									$catRoute = empty($menuItem) || $this->menuHelper->isHomepageMenuItem($menuItem)
										? ''
										: $menuItem->route;
								}

								if (!empty($catRoute))
								{
									array_unshift(
										$sefSegments,
										$catRoute
									);
								}
							}
						}

						$platformUri->delVar('virtuemart_category_id');
					}

					$limitstart      = Wb\arrayGetInt($originalVars, 'limitstart', 0);
					$lastSegment     = \end($sefSegments);
					$hasVmPagination = !empty($lastSegment)
									   &&
									   Wb\contains($lastSegment, 'results');
					if (
						$limitstart > 0
						&&
						!$hasVmPagination
					)
					{
						$uriToBuild->delVar('limitstart');
					}

					if (
						$limitstart > 0
						&&
						$hasVmPagination
					)
					{
						$platformUri->delVar('start');
					}

					if ($isCartView)
					{
						//if only option and Itemid left, VM wants Joomla router to prepend menu item. Let's do that
						if (
							empty($sefSegments)
							&&
							!empty($originalVars['Itemid'])
							&&
							!empty($originalVars['option']
							)
						)
						{
							if (
								!empty($menuItem)
								&&
								!$this->menuHelper->isHomepageMenuItem($menuItem))
							{
								if (!empty($menuItem->route))
								{
									array_unshift(
										$sefSegments,
										$menuItem->route
									);
								}
							}
						}
					}

					if ($isUserView)
					{
						//if only option and Itemid left, VM wants Joomla router to prepend menu item. Let's do that
						if (
							empty($title)
							&&
							2 === count($originalVars)
							&&
							!empty($originalVars['Itemid'])
							&&
							!empty($originalVars['option'])
						)
						{
							if (
								!empty($menuItem)
								&&
								!$this->menuHelper->isHomepageMenuItem($menuItem)
							)
							{
								$userRoute = $menuItem->title;
								if (!empty($userRoute))
								{
									array_unshift(
										$sefSegments,
										$userRoute
									);
								}
							}
						}
					}

					// add shop menu item, if asked to
					if ($this->extensionsConfig->isTruthy('virtuemartInsertShopName'))
					{
						array_unshift(
							$sefSegments,
							$shopName
						);
					}
				}
			}
		}

		if (
			empty($sefSegments)
			&&
			!empty($menuItem)
			&&
			!$isHomepage
		)
		{
			// fallback to menu item if no SEF and not home page.
			array_unshift(
				$sefSegments,
				$menuItem->route
			);
		}

		$format = $uriToBuild->getVar('format');
		if ('html' === $format)
		{
			$platformUri->delVar('format');
		}

		$clearCart = $uriToBuild->getVar('clearCart');
		if (empty($clearCart))
		{
			$platformUri->delVar('clearCart');
		}

		return array_merge(
			parent::build(
				$uriToBuild,
				$platformUri,
				$originalUri
			),
			$sefSegments
		);
	}

	/**
	 * Retrieve the category ID of a product based on its id. The first category is retrieved if
	 * product belongs to mulitple ones.
	 *
	 * @param int $productId
	 * @return int
	 */
	private function getVmProductCategory($productId)
	{
		static $categoryIds = [];

		if (empty($categoryIds[$productId]))
		{
			try
			{
				$category = $this->factory->getThe('db')->selectObject(
					'#__virtuemart_product_categories',
					'*',
					[
						'virtuemart_product_id' => $productId
					],
					[], // $aWhereData
					[
						'ordering' => 'asc'
					]
				);

				if (
					!empty($category)
					&&
					!empty($category->virtuemart_category_id)
				)
				{
					// we have a category, lets fetch its alias
					$categoryIds[$productId] = $category->virtuemart_category_id;
				}
				else
				{
					$categoryIds[$productId] = 0;
				}
			}
			catch (\Exception $e)
			{
				$categoryIds[$productId] = 0;
			}
		}

		return $categoryIds[$productId];
	}

	/**
	 * Finds the shop name based on the a provided Itemid or the menu item
	 * provided by the VM helper.
	 *
	 * @param int $Itemid
	 * @return mixed|string
	 */
	private function getShopName($Itemid)
	{
		static $shopNames = array();

		if (empty($shopNames[$Itemid]))
		{
			$vmItemid = $this->virtuemartHelper->menu('virtuemart');
			$Itemid   = empty($vmItemid)
				? $Itemid
				: $vmItemid;

			$menuItem = $this->platform->getMenu('site')->getItem($Itemid);
			if (!empty($menuItem))
			{
				$shopNames[$Itemid] = $menuItem->route;
			}
			else
			{
				$shopNames[$Itemid] = 'vm';
			}
		}

		return $shopNames[$Itemid];
	}

	/**
	 * Loads Virtuemart router.php file if no already loaded elsewhere.
	 *
	 * @return $this
	 */
	private function loadVirtuemartRouter()
	{
		$functionName = '\VirtuemartBuildRoute';
		if (!function_exists($functionName))
		{
			include_once(JPATH_ROOT . '/components/com_virtuemart/router.php');
		}

		return $this;
	}

	/**
	 * Loads Virtuemart helper and store for convenience
	 *
	 * @return $this
	 */
	private function loadVirtuemartHelper()
	{
		$this->virtuemartHelper = Helpers\Virtuemart::getInstance(
			$originalVars
		);

		return $this;
	}

	/**
	 * Participate in building a normalized non-sef URL based on an incoming URI. Query vars values are URL-encoded.
	 * Stripping slugs, sorting vars and possibly other things are taken care globally. Only plugin-specific
	 * vars processing should happen here. For instance, stripping pagination variables if the plugin
	 * handles pagination dynamically.
	 *
	 * @param array $vars
	 * @return array
	 */
	public function buildNormalizedNonSef($vars)
	{
		$toStrip = [];

		return array_diff_key(
			$this->nonSefHelper->stripFeedVars(
				parent::buildNormalizedNonSef(
					$vars
				)
			),
			array_flip(
				$toStrip
			)
		);
	}

	/**
	 * Extract the limit pagination value from a URI, trying to get the default value
	 * if none set and if possible.
	 *
	 * @TODO: implement detecting default limit.
	 *
	 * @param Uri\Uri $uri
	 * @return int
	 */
	protected function getPaginationLimit($uri)
	{
		if ($uri->hasVar('limit'))
		{
			return (int)$uri->getVar('limit');
		}

//		$query            = $uri->getQuery(true);
//		$virtuemartHelper = empty($this->virtuemartHelper)
//			? \vmrouterHelper::getInstance($query)
//			: $this->virtuemartHelper;

		$limit = $this->virtuemartHelper->limit();

		return empty($limit)
			? 0
			: $limit;
	}

	/**
	 * Build and append a pagination string to the URL to be built.
	 *
	 * @param Data\Urlpair $urlPair
	 * @param Uri\Uri      $uriToBuild
	 * @param Uri\Uri      $platformUri
	 * @return string
	 */
	public function buildPagination(&$urlPair, &$uriToBuild, &$platformUri)
	{
		$paginationString = '';
		if (
			!$uriToBuild->hasVar('start')
			&&
			!$uriToBuild->hasVar('limitstart')
		)
		{
			return $paginationString;
		}

//		$query            = $uriToBuild->getQuery(true);
//		$virtuemartHelper = empty($this->virtuemartHelper)
//			? \vmrouterHelper::getInstance($query)
//			: $this->virtuemartHelper;

		if (!$this->virtuemartHelper->isLegacy())
		{
			// VM 4+ builds its own pagination as part of the SEF URL.
			// VM 3 did not
			return $paginationString;
		}

		$limitVar     = $this->getPaginationLimit($uriToBuild);
		$defaultLimit = $this->virtuemartHelper->limit();
		if ($limitVar !== $defaultLimit)
		{
			// on J3, VM does build a pagination string if limit is set
			// to a value different from the current page limit (ie limit selector)
			return $paginationString;
		}

		$limitstartVar = $uriToBuild->getVar(
			'start',
			$uriToBuild->getVar('limitstart')
		);

		$limitVar = $this->getPaginationLimit($uriToBuild);

		return $this->buildPaginationString(
			$uriToBuild,
			$limitstartVar,
			$limitVar,
			$uriToBuild->getVar('lang')
		);
	}

	/**
	 * Build a pagination string based on number of pages, start item and language.
	 *
	 * @param Uri\Uri $uri
	 * @param integer $limitstartVar
	 * @param integer $limitVar
	 * @param string  $languageTag
	 * @return string
	 */
	public function buildPaginationString($uri, $limitstartVar, $limitVar, $languageTag)
	{
		$paginationString = '';

//		$query            = $uri->getQuery(true);
//		$virtuemartHelper = empty($this->virtuemartHelper)
//			? \vmrouterHelper::getInstance($query)
//			: $this->virtuemartHelper;

		if ($limitstartVar > 0)
		{
			//For the urls leading to the paginated pages
			// using general limit if $limit is not set
			if ($limitVar === null)
			{
				$limitVar = $this->virtuemartHelper->limit();
			}
			$paginationString = $this->virtuemartHelper->lang('results') . ',' . ($limitstartVar + 1) . '-' . ($limitstartVar + $limitVar);
		}
		else if ($limitVar !== null && $limitVar != $this->virtuemartHelper->limit())
		{
			//for the urls of the list where the user sets the pagination size/limit
			$paginationString = $this->virtuemartHelper->lang('results') . ',1-' . $limitVar;
		}
		else if (!empty($uri->getVar('search')) || !empty($uri->getVar('keyword')))
		{
			$paginationString = $this->virtuemartHelper->lang('results') . ',1-' . $this->virtuemartHelper->limit();
		}

		return $this->sefHelper->conformSegment(
			$paginationString
		);
	}

	/**
	 * Whether the limit value should be included in the URL. Factors are:
	 * - configuration option
	 * - for some extensions, if limit is different from the default limit, it should be included
	 *
	 * There may be a per extension configuration option as well.
	 *
	 * @param Uri\Uri $uri
	 * @param integer $limitstartVar
	 * @param integer $limitVar
	 * @return bool
	 */
	protected function shouldAppendPaginationLimit($uri, $limitstartVar, $limitVar)
	{
		$shouldAppend    = false;
		$platformDefault = $this->getDefaultListLimit($uri);
		if ((int)$limitVar !== (int)$platformDefault)
		{
			$shouldAppend = true;
		}
		if ($this->paginationConfig->get('alwaysAppendItemsPerPage', false))
		{
			$shouldAppend = true;
		}

		return $shouldAppend;
	}

	/**
	 * Figure out the default list limit value based on the nons-ef being built.
	 *
	 * @param Uri\Uri $uri
	 * @return mixed
	 */
	protected function getDefaultListlimit($uri)
	{
		// defaults to platform limit
		return $this->platform->getConfig()->get('list_limit');
	}

	/**
	 * Check if passed URI is for an extension configured to be left non-sef.
	 *
	 * @param Uri\Uri $uriToBuild
	 * @return bool
	 */
	public function shouldLeaveNonSef($uriToBuild)
	{
		$view = $uriToBuild->getVar('view');

		if (
			'category' === $view
			&&
			!empty($uriToBuild->getVar('keyword'))
		)
		{
			return true;
		}

		if (
			$this->extensionsConfig->isFalsy('virtuemartUseMenuItems')
			&&
			'productdetails' === $view
			&&
			empty($uriToBuild->getVar('virtuemart_product_id'))
		)
		{
			return true;
		}

		if (
			!empty($view)
			&&
			'productdetails' === $view
			&&
			empty($uriToBuild->getVar('virtuemart_product_id'))
		)
		{
			return true;
		}

		return false;
	}
}
