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

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Usersgroups extends Api\Controller
{
	/**
	 * Builds up an array of data for use in API response.
	 *
	 * @param array $options
	 *
	 * @return array|\Throwable
	 */
	public function get($request, $options)
	{

		$groups = $this->platform->getUsersGroups();
		$count  = count($groups);

		return [
			'data'  => $groups,
			'count' => $count,
			'total' => $count,
		];
	}
}
