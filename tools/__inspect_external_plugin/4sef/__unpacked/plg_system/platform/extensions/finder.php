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

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Finder extends Base
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

		$Itemid = $uriToBuild->getVar('Itemid');

		$menuItemTitle = $this->menuHelper
			->getMenuTitle(
				'com_contact',
				$Itemid
			);

		if (
			!empty($menuItemTitle)
			&&
			'/' !== $menuItemTitle
		)
		{
			$sefSegments[] = $menuItemTitle;
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
		return array_diff_key(
			$this->nonSefHelper->stripFeedVars(
				parent::buildNormalizedNonSef($vars)
			),
			array_flip(
				[
					'q',
					'task',
					't',
					'w1',
					'w2',
					'd1',
					'd2'
				]
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
		$format = $uriToBuild->getVar('format');
		if (in_array(
			$format,
			[
				'opensearch',
				'json'
			]
		))
		{
			return true;
		}

		return false;
	}
}
