<?php
/**
 * Project:                 4SEF
 *
 * @author                  Yannick Gaultier - Weeblr llc
 * @copyright               Copyright Weeblr llc - 2022 -2025
 * @package                 4SEF
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 @build_version_full_build@
 *
 * 2025-06-02
 */

use Weeblr\Wblib\Forsef\Wb;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * A few specific helpers
 */
class WblWordpress_Compat
{
	/**
	 * @since 4.5.0
	 *
	 * @param $args
	 *
	 * @return mixed
	 */
	public static function get_terms($args)
	{
		if (self::isGTE('4.5.0'))
		{
			return get_terms($args);
		}
		else
		{
			$taxonomies = Wb\arrayGet($args, 'taxonomy', array());
			unset($args['taxonomy']);

			return get_terms($taxonomies, $args);
		}
	}

	/**
	 *
	 * @param   string  $fallback
	 *
	 * @return string
	 */
	public static function wp_get_document_title()
	{
		if (self::isGTE('4.4.0'))
		{
			return wp_get_document_title();
		}

		return wp_title($sep = '»', $display = false, $seplocation = 'right');
	}

	/**
	 * Returns true if runnin WP version is
	 *    Greater Than or Equal
	 * the passed version
	 *
	 * @param   string  $version
	 *
	 * @return bool
	 */
	public static function isGTE($version)
	{
		global $wp_version;

		return version_compare($wp_version, $version, '>=');
	}
}