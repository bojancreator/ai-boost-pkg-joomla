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
				->getA(Admin\Status::class)
				->status($options),
			'count' => 1,
			'total' => 1
		];
	}
}
