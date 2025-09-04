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
use Joomla\CMS\Language\Text;

use Weeblr\Forsef\Data;
use Weeblr\Forsef\Helper;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Factory;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Weblinks extends Base
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
		$task   = $uriToBuild->getVar('task');
		$id     = $uriToBuild->getVar('id');
		$Itemid = $uriToBuild->getVar('Itemid');
		$lang   = $uriToBuild->getVar('lang');

		$menuItemTitle = $this->menuHelper
			->getMenuTitle(
				'com_contact',
				$Itemid
			);

		$uncategorizedPath = Data\Config::UNCAT_SLUG_ITEM_TITLE === $this->extensionsConfig->getInt('weblinksSlugForUncategorizedWeblinks')
			? ''
			: $menuItemTitle;

		// jumping to link target
		if ('weblink.go' === $task)
		{
			if (!empty($id))
			{
				try
				{
					$weblinkDetails = $this->factory
						->getThe('db')
						->selectObject(
							'#__weblinks',
							['id', 'alias', 'catid'],
							['id' => $id]
						);

					if (!empty($weblinkDetails->catid))
					{
						$slugsArray = $slugsHelper->getCategorySlugArray(
							'com_weblinks',
							$weblinkDetails->catid,
							(int)$this->extensionsConfig->get('weblinksIncludeWeblinksCat'),
							$this->extensionsConfig->get('weblinksUseWeblinksCatAlias'),
							false,              // insertId
							$uncategorizedPath, // using uncategorizedPath does not make much sense, kept for B/C
							$uriToBuild->getVar('lang')
						);

						$sefSegments = array_merge(
							$sefSegments,
							$slugsArray,
							[
								'/'
							]
						);

					}

					$sefSegments = array_merge(
						$sefSegments,
						[
							$weblinkDetails->alias
						]
					);
				}
				catch (Exception $e)
				{
					$this->logger->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
				}
			}
		}

		if ('weblink.go' !== $task)
		{
			switch ($view)
			{
				case 'category':
					// fetch cat name
					if (!empty($id))
					{
						$slugsArray = $slugsHelper->getCategorySlugArray(
							'com_weblinks',
							$id,
							(int)$this->extensionsConfig->get('weblinksIncludeWeblinksCatCategories'),
							$this->extensionsConfig->get('useWeblinksCatAlias'),       // Warning: sh404SEF uses com_contact option for this (contactUseContactCatAlias), possible B/C break
							false,                                                     // insertId
							$uncategorizedPath,                                        // using uncategorizedPath does not make much sense, kept for B/C
							$uriToBuild->getVar('lang')
						);

						$sefSegments = array_merge(
							$sefSegments,
							$slugsArray,
							[
								'/'
							]
						);
					}
					else if (!empty($menuItemTitle))
					{
						$sefSegments[] = $menuItemTitle;
					}

					break;
				case 'categories':
					// fetch cat name
					if (!empty($id))
					{
						$slugsArray = $slugsHelper->getCategorySlugArray(
							'com_weblinks',
							$id,
							(int)$this->extensionsConfig->get('weblinksIncludeWeblinksCatCategories'),
							$this->extensionsConfig->get('useWeblinksCatAlias'),       // surprise! sh404SEF uses com_contact option for this? kept for B/C
							false,                                                     // insertId
							$menuItemTitle,
							$uriToBuild->getVar('lang')
						);

						$sefSegments = array_merge(
							$sefSegments,
							$slugsArray,
							[
								'/'
							]
						);
					}
					else if (!empty($menuItemTitle))
					{
						$sefSegments[] = $menuItemTitle;
					}
					break;
				case 'form':
					$sefSegments[] = $this->t($lang, 'CREATE_NEW_LINK');
					break;
			}
		}

		return $sefSegments;
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
		$id   = $uriToBuild->getVar('id');
		$task = $uriToBuild->getVar('task');

		if (
			'weblink.go' === $task
			&&
			empty($id)
		)
		{
			return true;
		}

		if (
			'weblink.go' === $task
			&&
			!in_array(
				$view,
				[
					'category',
					'categories',
					'form'
				]
			)
		)
		{
			return true;
		}

		if (
			'weblink.go' !== $task
			&&
			empty($id)
			&&
			empty(
			$this->menuHelper->getMenuTitle(
				'com_contact',
				$uriToBuild->getVar('Itemid')
			)
			)
		)
		{
			return true;
		}

		if (
			'weblink.go' !== $task
			&&
			'form' === $view
			&&
			empty($uriToBuild->getVar('w_id'))
		)
		{
			return true;
		}

		return false;
	}
}

