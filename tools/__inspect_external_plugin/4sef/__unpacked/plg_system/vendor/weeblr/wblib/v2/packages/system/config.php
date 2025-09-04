<?php
/**
 * Project:                 4SEF
 *
 * @package                 4SEF
 * @copyright               Copyright Weeblr llc - 2022 -2025
 * @author                  Yannick Gaultier - Weeblr llc
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 @build_version_full_build@
 *
 * 2025-06-02
 */

namespace Weeblr\Wblib\Forsef\System;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\Joomla\StringHelper\StringHelper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * Class Config
 *
 * Proxy for the platform-suppplied configuration object
 *
 * @package Weeblr\Wblib\Forsef\System
 *
 */
class Config extends Base\Base
{
	/**
	 * @var string This configuration unique ID.
	 */
	protected $scope = 'default';

	/**
	 * @var mixed
	 */
	protected $config = [];

	public function __construct($scope)
	{
		parent::__construct();
		if (!is_string($scope) || StringHelper::strlen($scope) >= 40)
		{
			$msg = 'wbLib: invalid configuration scope (' . print_r($scope, true) . '). Please report to administrator. ';
			Log::libraryError('%s::%d %s', __METHOD__, __LINE__, $msg);
			throw new \Exception($msg);
		}
		$this->scope = $scope;
	}

	/**
	 * Setter for raw config. Use with care.
	 *
	 * @param   array  $config
	 */
	public function withConfig($config)
	{
		$this->config = $config;
	}

	/**
	 * Get a specific configuration item through nested keys.
	 *
	 * @param   string|array  $keys     An array of nested keys, or a single key, to get to the desired config item
	 * @param   mixed         $default  Optional default value if config not set
	 *
	 * @return mixed
	 */
	public function get($keys, $default = null)
	{
		return Wb\arrayGet($this->config, $keys, $default);
	}

	/**
	 * Get a specific configuration item through nested keys, cast as an int.
	 *
	 * @param   string|array  $keys     An array of nested keys, or a single key, to get to the desired config item
	 * @param   mixed         $default  Optional default value if config not set
	 *
	 * @return mixed
	 */
	public function getInt($keys, $default = null)
	{
		return (int) Wb\arrayGet($this->config, $keys, $default);
	}

	/**
	 * Sets a value through nested keys.
	 *
	 * @param   string|array  $keys   An array of nested keys, or a single key, to get to the desired config item
	 * @param   mixed         $value  The value to set
	 *
	 * @return $this
	 */
	public function set($keys, $value)
	{
		$previousValue = $this->get($keys);
		$this->config  = Wb\arraySet($this->config, $keys, $value);

		return $this->afterSet($keys, $value, $previousValue);
	}

	/**
	 * Can trigger an action after a key is set.
	 *
	 * @param   array|string  $keys
	 * @param   mixed         $newValue
	 * @param   mixed         $previousValue
	 *
	 * @return $this
	 */
	protected function afterSet($keys, $newValue, $previousValue)
	{
		return $this;
	}

	/**
	 * Check if there exists a specific configuration key definition
	 *
	 * @param   string|array  $keys
	 *
	 * @return
	 */
	public function hasKey($keys)
	{
		return Wb\arrayHasKey($this->config, $keys);
	}

	/**
	 * Check if a given config option is truthy.
	 *
	 * @param   string|array  $keys
	 *
	 * @return bool
	 */
	public function isTruthy($keys)
	{
		$value = $this->get($keys, false);

		return !empty($value);
	}

	/**
	 * Check if a given config option is falsy
	 * Can fetch a subkey in an array as well
	 *
	 * @param   string|array  $keys
	 *
	 * @return bool
	 */
	public function isFalsy($keys)
	{
		$value = $this->get($keys, false);

		return empty($value);
	}
}