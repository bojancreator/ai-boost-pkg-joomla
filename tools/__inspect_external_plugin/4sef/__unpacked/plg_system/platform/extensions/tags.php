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

use Weeblr\Forsef\Helper;
use Weeblr\Wblib\Forsef\Wb;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Tags extends Base
{
	/**
	 * Builds the SEF URL for a non-sef using a plugin per extension, if available.
	 *
	 * @param Uri\Uri $uriToBuild
	 * @param Uri\Uri $platformUri
	 * @param Uri\Uri $originalUri
	 *
	 * @return array|null
	 * @throws \Exception
	 */
	public function build($uriToBuild, $platformUri, $originalUri)
	{
		$platformSef = $this->factory
			->getA(Helper\Url::class)
			->getSefFromUri($platformUri);
		$platformSef = Wb\lTrim(
			$platformSef,
			[
				'index.php/',
				'index.php'
			]
		);

		// hack: the platform URL already has a language prefix. But we must
		// removed it as it's supposed to be added later in the process
		if ($this->platform->isMultilingual())
		{
			$languages = $this->platform->getInstalledLanguages();
			foreach ($languages as $key => $language)
			{
				$platformSef = Wb\lTrim(
					$platformSef,
					[
						$language->sef . '/'
					]
				);
			}

		}

		return explode('/', $platformSef);
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
		$nonSefWithoutFeedVars = $this->nonSefHelper->stripFeedVars(
			parent::buildNormalizedNonSef(
				$vars
			)
		);

		return array_diff_key(
			$nonSefWithoutFeedVars,
			array_flip(
				[
					'limit',
					'limitstart'
				]
			)
		);
	}
}
