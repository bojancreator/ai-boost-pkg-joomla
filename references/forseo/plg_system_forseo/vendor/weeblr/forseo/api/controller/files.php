<?php
/**
 * Project: 4SEO
 *
 * @package          4SEO
 * @copyright        Copyright Weeblr llc - 2020 - 2026
 * @author           Yannick Gaultier - Weeblr llc
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          6.10.1.2660
 * @date        2026-01-30
 */

namespace Weeblr\Forseo\Api\Controller;

use Weeblr\Forseo\Model;
use Weeblr\Wblib\Forseo\Api;
use Weeblr\Wblib\Forseo\System;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Files extends Api\Controller
{
	/**
	 * Builds up an array of data for use in API response.
	 *
	 * @param Weeblr\Wblib\APi\Request $request
	 * @param array                    $options
	 *
	 * @return void
	 */
	public function files($request, $options)
	{
		try
		{
			return $this->factory
				->getA(
					Model\Files::class
				)->files($options);
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return new \Exception('Internal error. See error log file.', System\Http::RETURN_INTERNAL_ERROR);
		}
	}
}
