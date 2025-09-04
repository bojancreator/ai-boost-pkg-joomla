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

/** ensure this file is being included by a parent file */
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

use Weeblr\Wblib\Forsef\Mvc;

/**
 * A set of syntactic sugar for outputting content.
 *
 */

if (!function_exists('wblGetLayoutOutput'))
{
	/**
	 * Wrapper around the Layout helper.
	 *
	 * @param   string  $layoutFile
	 * @param   mixed   $__data
	 * @param   string  $basePath
	 * @param   string  $theme
	 *
	 * @return string
	 */
	function wblGetLayout($layoutFile, $__data = null, $basePath = '', $theme = '')
	{
		return Mvc\LayoutHelper::render(
			$layoutFile,
			$__data,
			$basePath,
			$theme
		);
	}
}

if (!function_exists('wblEchoLayoutOutput'))
{
	/**
	 * Wrapper around the Layout helper.
	 *
	 * @param   string  $layoutFile
	 * @param   mixed   $__data
	 * @param   string  $basePath
	 * @param   string  $theme
	 */
	function wblEchoLayout($layoutFile, $__data = null, $basePath = '', $theme = '')
	{
		echo Mvc\LayoutHelper::render(
			$layoutFile,
			$__data,
			$basePath,
			$theme
		);
	}
}
