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

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\System as WblSystem;
use Weeblr\Wblib\Forsef\Api;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Platform extends Api\Controller
{
	/**
	 * Update a single URL pair.
	 *
	 * @param $request
	 * @param $options
	 *
	 * @return array|\Exception
	 */
	public function patch($request, $options)
	{
		try
		{
			$data = $request->getBody();
			$data = Wb\arrayEnsure($data);
			foreach ($data as $action => $parameter)
			{
				if ('offline' === $action)
				{
					$this->platform->persistOfflineMode($parameter);
				}
			}

			return [
				'data' => $data,
			];
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forsef.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return new \Exception('Internal error. See error log file.', WblSystem\Http::RETURN_INTERNAL_ERROR);
		}
	}
}
