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

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Api;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;
use Weeblr\Forseo\Model;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Referrers extends Api\Controller
{
	/**
	 * Fetch referrers for a given link.
	 *
	 * @param   Weeblr\Wblib\APi\Request  $request
	 * @param   array                     $options
	 *
	 * @return void
	 */
	public function links($request, $options)
	{
		return $this->getReferrers(
			'links',
			$options
		);
	}

	/**
	 * Fetch referrers for a given Error.
	 *
	 * @param   Weeblr\Wblib\APi\Request  $request
	 * @param   array                     $options
	 *
	 * @return void
	 */
	public function errors($request, $options)
	{
		return $this->getReferrers(
			'errors',
			$options
		);
	}

	/**
	 * Fetch referrers for a given link.
	 *
	 * @param   string  $type
	 * @param   array   $options
	 *
	 * @return void
	 */
	private function getReferrers($type, $options)
	{
		try
		{
			$id = (int) Wb\arrayGet($options, 'id');
			if (empty($id))
			{
				return new \Exception('No link id provided to fetch referrers for.', System\Http::RETURN_BAD_REQUEST);
			}

			return $this->factory
				->getA(
					Model\Referrers::class
				)->getReferrers(
					$type,
					$options
				);

		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return new \Exception('Internal error. See error log file.', System\Http::RETURN_INTERNAL_ERROR);
		}
	}
}
