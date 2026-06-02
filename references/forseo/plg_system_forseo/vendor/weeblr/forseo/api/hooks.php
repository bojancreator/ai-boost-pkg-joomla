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

namespace Weeblr\Forseo\Api;

use Weeblr\Wblib\Forseo\Base;

use Weeblr\Forseo\Data;
use Weeblr\Forseo\Model;
use Weeblr\Forseo\Controller;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Hooks related to the API execution: code that runs in response to an
 * API request.
 *
 * @package Weeblr\Forseo\Api
 */
class Hooks extends Base\Base
{
	public function add()
	{
		// uSE $hook to add any API event handler
		// $hook = $this->factory
		//   ->getThe('hook');
	}
}
