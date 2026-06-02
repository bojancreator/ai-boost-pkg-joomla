<?php
/**
 * Project: 4SEO
 *
 * @package                 4SEO
 * @copyright               Copyright Weeblr llc - 2020 - 2026
 * @author                  Yannick Gaultier - Weeblr llc
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 6.10.1.2660
 *
 * @date        2026-01-30
 */

use Weeblr\Forseo\Factory as ForseoFactory;
use Weeblr\Forseo\Helper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Forseo
{
	/**
	 * Usage for 3rd-parties:
	 *
	 * if (Factory::getApplication()->isClient('site'))
	 * {
	 *   \Forseo::analyticsCookiesAccepted
	 *   (
	 *        [
	 *            'universalga',
	 *            'globalga',
	 *            'gtm',
	 *            'matomo',
	 *            'clarity',
	 *            'fbpixel'
	 *        ]
	 *    );
	 * }
	 *
	 * @param array $providers
	 * @return mixed
	 */
	public static function analyticsCookiesAccepted($providers = [])
	{
		return ForseoFactory::get()
							->getA(Helper\Analytics::class)
							->analyticsCookiesAccepted($providers);
	}

	public static function analyticsCookiesRejected($providers = [])
	{
		return ForseoFactory::get()
							->getA(Helper\Analytics::class)
							->analyticsCookiesRejected($providers);
	}

	/**
	 * Access hook system
	 *
	 * @return \Weeblr\Wblib\Forseo\System\Hook
	 *
	 * @since 4.6.0
	 */
	public static function getHook()
	{
		return ForseoFactory::get()->getThe('hook');
	}

}