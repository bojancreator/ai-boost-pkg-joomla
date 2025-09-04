<?php
/**
 * Project: 4SEF
 *
 * @package                 4SEF
 * @copyright               Copyright Weeblr llc - 2022 -2025
 * @author                  Yannick Gaultier - Weeblr llc
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 2.6.2.644
 *
 * @date        2025-06-02
 */

namespace Weeblr\Forsef\Controller;

use Weeblr\Forsef\Data;
use Weeblr\Forsef\Helper;

use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Wb;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Apply any stored redirect.
 */
class Redirect extends Base\Base
{
	/**
	 * Search for, and execute, redirects that apply to the current request.
	 *
	 * @return void
	 */
	public function execute()
	{
		try
		{
			$requestInfo = $this->factory->getThe('forsef.requestInfo');

			// extra_path is everything that was added to base path: pagination + suffix
			// Appended segment is everything that was added dynamically to base path: feed format
			// extra_path and appended_segment are mutually exclusive
			$appendedPath = $requestInfo->get('page_extra_path', '') . $requestInfo->get('page_appended_segment', '');
			$requestPath  = empty($appendedPath)
				? $requestInfo->get('page_path')
				: $requestInfo->get('page_base_path');

			$applicableRedirect = $this->factory
				->getA(Data\Redirect::class)
				->loadPerColumn(
					'source',
					$requestPath
				);

			if (!$applicableRedirect->exists())
			{
				return;
			}

			// stitch back pagination/suffix if one was found
			$target = empty($appendedPath)
				? $applicableRedirect->get('target')
				: Wb\trimJoin(
					$applicableRedirect->get('target'),
					$appendedPath
				);

			$target = System\Route::absolutify(
				$target,
				true
			);

			if ($this->platform->canRedirect(
				$target,
				$requestInfo->get('page_url')
			))
			{
				// re-append query...
				$query  = $requestInfo->get('page_query');
				$target = empty($query)
					? $target
					: $target . '?' . $query;

				// ...before redirecting
				$this->platform->redirectTo($target);
			}
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forsef.logger')->error(__METHOD__ . ': %s::%d %s %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
	}
}
