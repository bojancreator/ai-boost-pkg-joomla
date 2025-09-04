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

namespace Weeblr\Wblib\Forsef\Mvc;

use Weeblr\Wblib\Forsef\Wb;

/** ensure this file is being included by a parent file */
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

class LayoutHelper
{
	public static $defaultBasePath = '';

	public static function render($layoutFile, $__data = null, $basePath = '', $theme = '')
	{
		$basePath       = empty($basePath) ? self::$defaultBasePath : $basePath;
		$layoutFile     = Wb\dotJoin(
			$theme,
			$layoutFile
		);
		$layout         = new LayoutFile($layoutFile, $basePath);
		$renderedLayout = $layout->render($__data);

		return $renderedLayout;
	}

	/**
	 * Check if a layout file exist
	 *
	 * @param   string  $layoutFile
	 * @param   string  $basePath
	 *
	 * @return bool
	 */
	public static function layoutExists($layoutFile, $basePath = '')
	{
		$basePath = empty($basePath) ? self::$defaultBasePath : $basePath;
		$layout   = new LayoutFile($layoutFile, $basePath);

		return $layout->exists();
	}

	/**
	 * Iterate over a list of layout files, and returns the name
	 * of the first that exists
	 *
	 * @param   array   $layoutFiles
	 * @param   string  $basePath
	 *
	 * @return string
	 */
	public static function getExistingLayout($layoutFiles, $basePath = '')
	{
		if (empty($layoutFiles))
		{
			return '';
		}

		$layoutFiles = (array) $layoutFiles;
		foreach ($layoutFiles as $layoutFile)
		{
			if (self::layoutExists($layoutFile, $basePath))
			{
				return $layoutFile;
			}
		}

		return '';
	}
}
