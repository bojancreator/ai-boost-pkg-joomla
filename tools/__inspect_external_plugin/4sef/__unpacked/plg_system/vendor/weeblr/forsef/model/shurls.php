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

namespace Weeblr\Forsef\Model;

use Joomla\CMS\Router\Route;

use Weeblr\Forsef\Data;

use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Shurls extends Base\Base
{
	/**
	 * Execute any shURL that would apply to the provided path.
	 *
	 * @throws \Exception
	 */
	public function execute()
	{
		$requestInfo = $this->factory->getThe('forsef.requestInfo');

		// search in shURLs table
		$targetNonSef = $this->getShurlTarget($requestInfo);

		/**
		 * sh404SEF stored shurls against the non-sef, like many things but 4SEF stores everything against the SEF.
		 * So should not we switch to directly using the SEF? implies a conversion at import time but that's fine really.
		 *
		 * In any case, if we just use the renamed sh404SEF db tables, target URL there is non-SEF.
		 */
		if (empty($targetNonSef))
		{
			return;
		}

		$targetSef = $this->platform->route(
			$targetNonSef,
			false,
			Route::TLS_IGNORE,
			true
		);

		// execute redirect
		if ($this->platform->canRedirect(
			$targetSef,
			$requestInfo->get('page_url')
		))
		{
			$this->platform->redirectTo(
				$targetSef
			);
		}
	}

	/**
	 * Lookup a provided path in the shURL table and retrieve the matching non-sef
	 * redirect target URL, if any?
	 *
	 * @param Data\RequestInfo $requestinfo Normalized (without domain, rewrite prefix or leading slash) requested path/
	 *
	 * @return string|null
	 */
	private function getShurlTarget($requestInfo)
	{
		try
		{
			$shurl = $this->factory
				->getA(Data\Sh404sef\Shurl::class)
				->loadPerShurl($requestInfo->get('page_path'));
			if (empty($shurl))
			{
				return null;
			}

			$targetNonSef = $shurl->get('newurl', '');
			if (empty($targetNonSef))
			{
				return null;
			}

			$targetNonSef = System\Route::appendQuery(
				$targetNonSef,
				$requestInfo->get('page_query')
			);

			return $this->platform
				->stripLangVarIfUseless(
					$targetNonSef,
					false
				);
		}
		catch (\Throwable $e)
		{
			// catch if sh404SEF legacy shurl table does not exist
		}

		return null;
	}
}
