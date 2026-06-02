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

use Weeblr\Wblib\Forseo\Api;

use Weeblr\Forseo\Model;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Status extends Api\Controller
{
	/**
	 * Use model to gather status information.
	 *
	 * @param Api\Request $request
	 * @param array       $options
	 *
	 * @return array
	 */
	public function get($request, $options)
	{
		return [
			'data'  => $this->factory
				->getA(Model\Status::class)
				->status($options),
			'count' => 1,
			'total' => 1
		];
	}
}
