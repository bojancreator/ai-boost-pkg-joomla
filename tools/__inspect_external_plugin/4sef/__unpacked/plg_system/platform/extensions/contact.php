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

use Weeblr\Forsef\Helper;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Factory;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Contact extends Base
{
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
		$sefSegments = parent::build($uriToBuild, $platformUri, $originalUri);

		$slugsHelper = $this->factory->getA(Helper\Slugs::class);

		$view   = $uriToBuild->getVar('view');
		$catid  = $uriToBuild->getVar('catid');
		$id     = $uriToBuild->getVar('id');
		$Itemid = $uriToBuild->getVar('Itemid');

		$menuItemTitle = $this->menuHelper
			->getMenuTitle(
				'com_contact',
				$Itemid
			);

		switch ($view)
		{
			case 'featured':
				if (!empty($menuItemTitle))
				{
					$sefSegments[] = $menuItemTitle;
					$sefSegments[] = '/';
				}
				break;
			case 'category' :

				// fetch cat name
				if (!empty($id))
				{
					try
					{
						$slugsArray = $slugsHelper->getCategorySlugArray(
							'com_contact',
							$id,
							(int)$this->extensionsConfig->get('contactIncludeContactCatCategories'),
							$this->extensionsConfig->get('contactUseContactCatAlias'),
							false, // insertId
							$menuItemTitle,
							$uriToBuild->getVar('lang')
						);
					}
					catch (\Exception $e)
					{
						$this->logger->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
					}
					$slugsArray[] = '/';
				}
				else
				{
					if (!empty($menuItemTitle))
					{
						$slugsArray[] = $menuItemTitle;
					}
				}
				if (!empty($slugsArray))
				{
					$sefSegments = array_merge(
						$sefSegments,
						$slugsArray
					);
				}
				break;
			case 'categories':
				// get category(ies) path
				if (!empty($id))
				{
					try
					{
						$slugsArray = $slugsHelper->getCategorySlugArray(
							'com_contact',
							$id,
							(int)$this->extensionsConfig->get('contactIncludeContactCatCategories'),
							$this->extensionsConfig->get('contactUseContactCatAlias'),
							false, // insertId
							$menuItemTitle,
							$uriToBuild->getVar('lang')
						);
					}
					catch (\Exception $e)
					{
						$this->logger->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
					}

					// insert a suffix to distinguish from normal category listing
					if ($this->extensionsConfig->isTruthy('contactContentCategoriesSuffix'))
					{
						$slugsArray[] = $this->extensionsConfig->get('contactContentCategoriesSuffix');
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

				if (!empty($slugsArray))
				{
					$sefSegments = array_merge(
						$sefSegments,
						$slugsArray
					);
				}
				break;
			case 'contact' :

				// insert category, as per settings
				if (empty($catid) && !empty($id))
				{
					try
					{
						$catid = $this->factory
							->getThe('db')
							->selectResult(
								'#__contact_details',
								'catid',
								[
									'id' => $id
								]
							);
					}
					catch (\Exception $e)
					{
						$this->logger->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
					}
				}

				if (!empty($catid))
				{
					try
					{
						$slugsArray = $slugsHelper->getCategorySlugArray(
							'com_contact',
							$catid,
							(int)$this->extensionsConfig->get('contactIncludeContactCat'),
							$this->extensionsConfig->get('contactUseContactCatAlias'),
							false, // insertId
							$menuItemTitle,
							$uriToBuild->getVar('lang')
						);
					}
					catch (\Exception $e)
					{
						$this->logger->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
					}

					if (!empty($slugsArray))
					{
						$sefSegments = array_merge(
							$sefSegments,
							$slugsArray
						);
					}
				}

				// fetch contact name
				if (!empty($id))
				{
					try
					{
						$contactDetails = $this->factory
							->getThe('db')
							->selectObject(
								'#__contact_details',
								[
									'name',
									'alias',
									'id'
								],
								[
									'id' => $id
								]
							);
					}
					catch (\Exception $e)
					{
						$this->logger->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
					}

					if (!empty($contactDetails))
					{
						$sefSegments[] = $this->extensionsConfig->get('contactUseContactAlias')
							? $contactDetails->alias
							: $contactDetails->name;

						// We know these 3 will always be used
						$platformUri->delVar('view');
						$platformUri->delVar('id');
						$platformUri->delVar('catid');

						// and in some cases, Itemid will prevent us from recognizing the page
						$platformUri->delVar('Itemid');
					}
					else
					{
						$sefSegments[] = $id;
					}
				}
				break;
		}

		return $sefSegments;
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
		return $this->nonSefHelper->stripFeedVars(
			parent::buildNormalizedNonSef(
				$vars
			)
		);
	}

	/**
	 * Check if passed URI is for an extension configured to be left non-sef.
	 *
	 * @param Uri\Uri $uriToBuild
	 * @return bool
	 */
	public function shouldLeaveNonSef($uriToBuild)
	{
		$view   = $uriToBuild->getVar('view');
		$id     = $uriToBuild->getVar('id');
		$Itemid = $uriToBuild->getVar('Itemid');

		$menuItemTitle = $this->menuHelper
			->getMenuTitle(
				'com_contact',
				$Itemid
			);

		switch ($view)
		{
			case 'featured':
				if (empty($menuItemTitle))
				{
					return true;
				}
				break;
			case 'category' :
			case 'categories':
				if (empty($id) && empty($menuItemTitle))
				{
					return true;
				}
				break;
			case 'contact' :
				break;
			default:
				return true;
		}

		return false;
	}
}

