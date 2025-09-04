<?php
/**
 * Project:                 4SEF
 *
 * @author           Yannick Gaultier - Weeblr llc
 * @copyright        Copyright Weeblr llc - 2022 -2025
 * @package          4SEF
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          @build_version_full_build@
 * @date                2025-06-02
 */

namespace Weeblr\Wblib\Forsef\System;

use Weeblr\Wblib\Forsef\Wb;

// no direct access
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

class Memo
{
	private static $cache = [];

	/**
	 * Get a possibly memoized value, returning a default value if not found.
	 *
	 * @param   string  $key      An array of nested keys to get to the desired config item
	 * @param   mixed   $default  Optional default value if config not set
	 *
	 * @return mixed
	 */
	public function get($key, $default = null)
	{
		return Wb\arrayGet(
			self::$cache,
			md5($key),
			$default
		);
	}

	/**
	 * Sets a value under a specific key.
	 *
	 * @param   string  $key
	 * @param   mixed   $value
	 *
	 * @return Memo
	 */
	public function set($key, $value)
	{
		self::$cache[md5($key)] = $value;

		return $this;
	}

	/**
	 * Check if some data has been memoized under a specific key.
	 *
	 * @param   string  $key
	 *
	 * @return bool
	 */
	public function has($key)
	{
		return Wb\arrayIsSet(
			self::$cache,
			$key
		);
	}
}
