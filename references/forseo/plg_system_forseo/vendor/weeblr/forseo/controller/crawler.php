<?php
/**
 * Project: 4SEO
 *
 * @package                 4SEO
 * @copyright               Copyright Weeblr llc - 2020 - 2026
 * @author                  Yannick Gaultier - Weeblr llc
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 6.10.1.2660
 *
 * 2026-01-30
 */

namespace Weeblr\Forseo\Controller;

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\Mvc;
use Weeblr\Wblib\Forseo\Api;

use Weeblr\Forseo\Data;
use Weeblr\Forseo\Model;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Render a story page
 */
class Crawler extends Base\Base
{
	/**
	 * Triggers a crawl run from a cron command.
	 *
	 * NB: All cron-triggered jobs should always return and never die() or exit() as there
	 * may be other jobs attached to the same cron event, waiting to run. All errors should
	 * be caught, logged and then control returned to caller.
	 *
	 * @param array       $response [[]data, int count, int total]
	 * @param Api\Request $request
	 * @param string      $type     image | http
	 *
	 * @return array|\Exception
	 */
	public function fromCron($response, $request, $type)
	{
		try
		{
			$this->factory
				->getA(Model\Crawler::class)
				->run(
					false, // $force
					$type
				);
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return $e;
		}

		if (is_array($response))
		{
			$response['count'] += 1;
			$response['total'] += 1;
		}

		return $response;
	}
}
