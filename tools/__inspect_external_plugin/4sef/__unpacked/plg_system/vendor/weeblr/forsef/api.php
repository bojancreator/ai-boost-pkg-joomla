<?php
/**
 * Project: 4SEF
 *
 * @package                 4SEF
 * @copyright               Copyright Weeblr llc - 2022 -2025
 * @author                  Yannick Gaultier - Weeblr llc
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 2.6.2.644
 *
 * @date        2025-06-02
 */

use Weeblr\Forsef\Factory as ForsefFactory;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Forsef
{
	/**
	 * Whether 4SEF is enabled on the site.
	 *
	 * @return bool
	 *
	 * @since 1.0.3
	 */
	static public function isEnabled()
	{
		return ForsefFactory::get()
							->getThis('forsef.config', 'routing')
							->isTruthy('enabled');
	}

	/**
	 * Access hook system
	 *
	 * @return \Weeblr\Wblib\Forsef\System\Hook
	 *
	 * @since 1.3.3
	 */
	static public function getHook()
	{
		return ForsefFactory::get()->getThe('hook');
	}
}
