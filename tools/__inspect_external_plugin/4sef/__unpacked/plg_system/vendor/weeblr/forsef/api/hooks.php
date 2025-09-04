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

namespace Weeblr\Forsef\Api;

use Weeblr\Wblib\Forsef\Base;

use Weeblr\Forsef\Model\Admin;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Hooks related to the API execution: code that runs in response to an
 * API request.
 *
 * @package Weeblr\Forsef\Api
 */
class Hooks extends Base\Base
{
	public function add()
	{
		$hook = $this->factory->getThe('hook');

		/********************************************************************************************************
		 * Url customization
		 *******************************************************************************************************/

		$hook->add(
			'forsef_url_customized',
			function ($data, $originalBasePath, $customizedSefs, $originalSef, $extraPathLeadingSlash, $customizeDuplicates) {
				$this->factory
					->getA(Admin\Redirect::class)
					->onUrlCustomized(
						$data,
						$originalBasePath,
						$customizedSefs,
						$originalSef,
						$extraPathLeadingSlash,
						$customizeDuplicates
					);
			}
		);
	}
}
