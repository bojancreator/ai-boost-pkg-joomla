<?php
/**
 * Project:                 4SEF
 *
 * @author                  Yannick Gaultier - Weeblr llc
 * @copyright               Copyright Weeblr llc - 2022 -2025
 * @package                 4SEF
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 2.6.2.644
 *
 * 2025-06-02
 *
 */

namespace Weeblr\Wblib\Forsef\Api;

defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * Parse and manage the named parameters of an API request.
 *
 */
class Input
{
	/**
	 * @var array Associative array of api request named parameters.
	 */
	private $parameters = array();

	/**
	 * Stores parameters, based on a list of names and values. There may be less values than names.
	 * Data can be passed either as:
	 *
	 * - two arrays, one for the keys, one for the values
	 * - one associative array
	 *
	 * @param   array  $names   Ordered list of names parameters.
	 * @param   array  $values  Ordered list of parameters values (optional)
	 */
	public function __construct($names, $values = null)
	{
		// input type 1: keys and values are in different arrays
		if (!is_null($values))
		{
			for ($index = 0; $index < count($values); $index++)
			{
				$this->parameters[$names[$index]] = $values[$index];
			}
		}
		else
		{
			$this->parameters = $names;
		}
	}

	/**
	 * Get a named parameter value.
	 *
	 * @param   string  $key      Parameter name.
	 * @param   mixed   $default  Default value if parameter has not been set.
	 *
	 * @return mixed
	 */
	public function get($key, $default = null)
	{
		if (isset($this->parameters[$key]))
		{
			return $this->parameters[$key];
		}

		return $default;
	}

	/**
	 * Retrieve all set parameters into an associative array.
	 *
	 * @return array
	 */
	public function getArray()
	{
		return $this->parameters;
	}

	/**
	 * Get a named parameter value as an integer.
	 *
	 * @param   string  $key      Parameter name.
	 * @param   mixed   $default  Default value if parameter has not been set.
	 *
	 * @return int
	 */
	public function getInt($key, $default = 0)
	{
		$pattern = '/[-+]?[0-9]+/';

		preg_match($pattern, (string) $this->get($key, $default), $matches);
		$result = isset($matches[0]) ? (int) $matches[0] : 0;

		return $result;
	}

	/**
	 * Get a named parameter value only allowing alphanumeric characters.
	 *
	 * @param   string  $key      Parameter name.
	 * @param   mixed   $default  Default value if parameter has not been set.
	 *
	 * @return string
	 */
	public function getAlnum($key, $default = '')
	{
		$value = $this->get($key, $default);
		$value = (string) preg_replace(
			'/[^A-Z0-9]/iu',
			'',
			$value
		);

		return $value;
	}

	/**
	 * Get a specific value passed as a query variable, as a safe string:
	 *   A-Za-z0-9-_
	 *
	 * @param   string  $key
	 * @param   mixed   $default
	 *
	 * @return bool
	 */
	public function getSafeString($key, $default = '')
	{
		$value = preg_replace(
			'/[^A-Za-z0-9\-_]*/',
			'',
			$this->get(
				$key,
				$default
			)
		);

		return $value;
	}

	/**
	 * Get a named parameter value as a boolean.
	 * if unset, or 'false' and '0', evaluates as false. Otherwise true.
	 *
	 * @param   string  $key      Parameter name.
	 * @param   mixed   $default  Default value if parameter has not been set.
	 *
	 * @return bool
	 */
	public function getBool($key, $default = false)
	{
		$value = $this->get($key, $default);
		if (empty($value))
		{
			return false;
		}

		switch (strtolower($value))
		{
			case 'false':
			case '0':
				$value = false;
				break;
			default:
				$value = true;
				break;
		}

		return $value;
	}
}
