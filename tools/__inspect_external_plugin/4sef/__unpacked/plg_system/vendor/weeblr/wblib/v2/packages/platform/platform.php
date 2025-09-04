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

namespace Weeblr\Wblib\Forsef\Platform;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 */
abstract class Platform implements Platforminterface
{
	// platforms
	const JOOMLA = 'joomla';
	const WORDPRESS = 'wordpress';

	protected $_name = '';

	// store current platform name and implementation
	private static $platformName = null;

	/**
	 * @var \Weeblr\Wblib\Forsef\System\ConfigInterface
	 */
	protected $_config = null;

	/**
	 * Whether we are on Joomla
	 *
	 * @return bool
	 */
	public function isJoomla()
	{
		return self::JOOMLA == self::$platformName;
	}

	/**
	 * Whether we are on WordPress
	 *
	 * @return bool
	 */
	public function isWordpress()
	{
		return self::WORDPRESS == self::$platformName;
	}

	/**
	 * Getter for platform name
	 *
	 * @return string
	 */
	public function getName()
	{
		return self::$platformName;
	}

}