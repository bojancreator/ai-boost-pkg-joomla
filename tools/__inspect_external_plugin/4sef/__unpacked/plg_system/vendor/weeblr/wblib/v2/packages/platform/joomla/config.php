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

namespace Weeblr\Wblib\Forsef\Platform\Joomla;

use Weeblr\Wblib\Forsef\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

class Config implements System\ConfigInterface
{
	/**
	 * @var \JRegistry
	 */
	private $joomlaConfig = null;

	/**
	 * Config constructor. Stores Joomla config object
	 *
	 * @param   \JRegistry  $joomlaConfig
	 */
	public function __construct($joomlaConfig)
	{
		$this->joomlaConfig = $joomlaConfig;
	}

	/**
	 * Get a specific configuration key
	 *
	 * @param   string  $key      The config option name
	 * @param   mixed   $default  Optional default value if config not set
	 */
	public function get($key, $default = null)
	{
		return $this->joomlaConfig->get($key, $default);
	}

	/**
	 * Check if there exists a specific configuration key definition
	 *
	 * @param   string  $key
	 */
	public function hasConfigKey($key)
	{
		return $this->joomlaConfig->exists($key);
	}
}