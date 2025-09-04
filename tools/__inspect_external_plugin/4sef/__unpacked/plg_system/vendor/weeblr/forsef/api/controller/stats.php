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

namespace Weeblr\Forsef\Api\Controller;

use Weeblr\Forsef\Model\Admin;

use Weeblr\Wblib\Forsef\Api;
use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\System;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Stats extends Api\Controller
{
	/**
	 * Delete ore or more pages.
	 *
	 * @param Weeblr\Wblib\Api\Request $request
	 * @param array                    $options
	 *
	 * @return \Exception|array
	 */
	public function delete($request, $options)
	{
		try
		{
			$deleted = $this->factory
				->getA(Admin\Stats::class)
				->delete(
					$request->getBody(),
					$options
				);

			return $deleted instanceof \Throwable
				? $deleted
				: array_merge(
					$deleted,
					['status' => System\Http::RETURN_NO_CONTENT]
				);
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forsef.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return new \Exception('Internal error. See error log file.', System\Http::RETURN_INTERNAL_ERROR);
		}
	}
}
