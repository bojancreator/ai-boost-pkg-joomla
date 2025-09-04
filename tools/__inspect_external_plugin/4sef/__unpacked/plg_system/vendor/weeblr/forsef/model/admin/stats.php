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

namespace Weeblr\Forsef\Model\Admin;

use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Stats extends Base\Base
{
	/**
	 * Delete all stats collected so far.
	 *
	 * @param array $keys
	 * @param array $options
	 *
	 * @return array|\Exception
	 */
	public function delete($keys, $options = [])
	{
		try
		{
			// reset all URLs hits
			$this->factory
				->getThe('db')
				->update(
					'#__forsef_urls',
					[
						'hits' => 0
					]
				)->truncate(
					'#__forsef_stats_dailies'
				);

			// reset per day starts
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forsef.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return new \Exception($e->getMessage(), System\Http::RETURN_INTERNAL_ERROR);
		}

		return [
			'data'  => null,
			'count' => 0,
			'total' => 0,
		];
	}
}
