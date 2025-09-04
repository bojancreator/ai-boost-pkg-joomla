<?php
/**
 * Project:                 4SEF
 *
 * @package                 4SEF
 * @copyright               Copyright Weeblr llc - 2022 -2025
 * @author                  Yannick Gaultier - Weeblr llc
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 2.6.2.644
 *
 * 2025-06-02
 */

namespace Weeblr\Wblib\Forsef\Api;

use Weeblr\Wblib\Forsef\Base;

defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * Standard controller used to respond to an api request. To be extended by api response producers.
 *
 * @package Weeblr\Wblib\Forsef\Api
 */
class Controller extends Base\Base
{
	/**
	 * Builds up an array of data for use in API response. Format:
	 *
	 * $data = array(
	 *  'data'  => array(
	 *      'enabled'     => true,
	 *      'seo_enabled' => true,
	 *      'site name'   => 'Site name set in PHP',
	 *      'ogp_id'      => 123456798
	 *  ),
	 *  'count' => 4,
	 *  'total' => 4
	 * );
	 *
	 * $data['data'] will be the payload returned.
	 * count and total are optionals, will be set to zero if missing.
	 *
	 * @param   Request  $request
	 * @param   array    $options
	 *
	 * @return array
	 */
	public function get($request, $options)
	{
		return [];
	}

	public function put($request, $options)
	{
		return [];
	}

	public function patch($request, $options)
	{
		return [];
	}

	public function delete($request, $options)
	{
		return [];
	}
}
