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

use Weeblr\Wblib\Forsef\Api;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Languages extends Api\Controller
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
		$languages = $this->platform->getFrontendLanguages();
		$count     = count($languages);

		return [
			'data'  => $languages,
			'count' => $count,
			'total' => $count,
		];
	}
}
