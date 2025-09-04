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

class Newsfeeds extends Base
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
		$lang   = $uriToBuild->getVar('lang');

		$menuItemTitle = $this->menuHelper
			->getMenuTitle(
				'com_contact',
				$Itemid
			);

		if (empty($sefSegments))
		{
			$sefSegments[] = empty($menuItemTitle)
				? 'Newsfeed' // not an error, keeping same as sh404SEF, Newsfeed vs Newsfeeds
				: $menuItemTitle;
		}

		switch ($view)
		{
			case 'newsfeed':
				if (!empty($catid))
				{
					try
					{
						$slugsArray = $slugsHelper->getCategorySlugArray(
							'com_newsfeeds',
							$catid,
							Data\Config::CAT_ALL_NESTED,
							false, // useAlias
							false, // insertId
							'',
							$uriToBuild->getVar('lang')
						);

						$sefSegments = array_merge(
							$sefSegments,
							$slugsArray
						);
					}
					catch (\Exception $e)
					{
						$this->logger->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
					}
				}

				if (!empty($id))
				{
					try
					{
						$newsfeedName = $this->factory
							->getThe('db')
							->selectResult(
								'#__newsfeeds',
								'name',
								[
									'id' => $id
								]
							);
					}
					catch (\Exception $e)
					{
						$this->logger->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
					}

					if (!empty($newsfeedName))
					{
						$sefSegments[] = $newsfeedName;
					}
				}
				else
				{
					$sefSegments[] = '/';
				}
				break;
			case 'category':
				if (!empty($id))
				{
					try
					{
						$slugsArray = $slugsHelper->getCategorySlugArray(
							'com_newsfeeds',
							$id,
							Data\Config::CAT_ALL_NESTED,
							false, // useAlias
							false, // insertId
							'',
							$uriToBuild->getVar('lang')
						);

						if (!empty($slugsArray))
						{
							$sefSegments = array_merge(
								$sefSegments,
								$slugsArray,
								[
									'/'
								]
							);
						}
					}
					catch (\Exception $e)
					{
						$this->logger->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
					}
				}

				break;
			case 'new':
				$sefSegments[] = $this->t($lang, 'CREATE_NEW_NEWSFEED');
				break;
			default:
				$sefSegments[] = '/';
				break;
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

		switch ($view)
		{
			case 'category':
				return empty($uriToBuild->getVar('id'));
		}

		return false;
	}
}

