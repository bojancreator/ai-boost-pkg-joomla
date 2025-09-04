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

namespace Weeblr\Wblib\Forsef\Base;

use Weeblr\Wblib\Forsef\Joomla\StringHelper\StringHelper;
use Weeblr\Wblib\Forsef\System;

/** ensure this file is being included by a parent file */
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * Database persistence for data objects.
 * Data is stored in the state it will be stored to the database.
 *
 */
class Dataobject extends Base
{
	/**
	 * @var array Holds data read/written to db. Also a specification of the columns.
	 */
	protected $data = [];

	/**
	 * @var array List of defaults values for the item properties.
	 */
	protected $defaults = [];

	/**
	 * @var array List of types that should be enforced if present for properties.
	 */
	protected $dataTypes = [];

	/**
	 * @var array List of max length per column.
	 */
	protected $autotrimSpec = [];

	/**
	 * Init of data holding array with defaults.
	 *
	 * @param   array  $options  Can inject custom factory and platform.
	 */
	public function __construct($options = [])
	{
		parent::__construct($options);

		$this->data = $this->defaults;
	}

	/**
	 * Load this instance with a pre-existing data set. Differs from the set Method
	 * in that the incoming data array is first checked and possibly cleaned of invalid data before attempting to
	 * use the data. Used to pass data around.
	 *
	 * @param [] $data
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function withData($data)
	{
		$filteredData = empty($data)
			? []
			: array_intersect_key(
				$data,
				$this->data
			);

		if (!empty($filteredData))
		{
			$this->data = array_merge(
				$this->data,
				$filteredData
			);
		}

		$this->validate();

		return $this;
	}

	/**
	 * Getter for the data types, can be used externally to enforce types.
	 *
	 * @return array
	 */
	public function getDataTypes()
	{
		return $this->dataTypes;
	}

	/**
	 * Set data for this object. Override to pre-process
	 * data for storage.
	 *
	 * @param   null| string  $keyOrData
	 * @param   array         $data
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function set($keyOrData = null, $data = null)
	{
		if (!empty($keyOrData) && !is_array($keyOrData))
		{
			// single key
			$this->setKey($keyOrData, $data);

			return $this;
		}
		if (empty($keyOrData))
		{
			$keyOrData = $data;
		}

		if (!is_array($keyOrData))
		{
			throw new \Exception('Trying to set invalid key or data ' . print_r($keyOrData, true) . ' to ' . __CLASS__ . ' data object', 500);
		}

		foreach ($keyOrData as $key => $value)
		{
			$this->setKey($key, $value);
		}

		return $this;
	}

	/**
	 * Get the current data hold by the object, or the content of a single
	 * key if one is provided.
	 *
	 * Override to postprocess data out of storage.
	 *
	 * @param   null| string  $key
	 * @param   mixed         $default
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function get($key = null, $default = null)
	{
		if (empty($key))
		{
			$data = [];
			foreach ($this->data as $key => $value)
			{
				$data[$key] = $this->getKey($key);
			}

			return $this->afterGet(
				$data
			);
		}
		else if (array_key_exists($key, $this->data))
		{
			$value = $this->getKey($key);

			return is_null($value)
				? $default
				: $value;
		}
		else
		{
			throw new \Exception('Trying to get invalid key ' . print_r($key, true) . ' from ' . __CLASS__ . ' data object', 500);
		}
	}

	/**
	 * Filter the returned data after a get.
	 *
	 * @param   mixed  $data
	 * @param   null   $key
	 *
	 * @return mixed
	 */
	protected function afterGet($data, $key = null)
	{
		return $data;
	}

	/**
	 * Validates the entire data store. Use prior to storing for instance.
	 *
	 * @return $this
	 * @throws \Exception
	 */
	protected function validate()
	{
		foreach ($this->data as $key => $value)
		{
			$this->validateKey($key);
		}

		return $this;
	}

	/**
	 * Set an individual key of this object.
	 *
	 * @param   string  $key
	 * @param   mixed   $value
	 *
	 * @return $this
	 * @throws \Exception
	 */
	protected function setKey($key, $value)
	{
		$previousValue = array_key_exists(
			$key,
			$this->data
		)
			? $this->get($key)
			: null;

		$this->data[$key] = $this->validateKey($key)
			->encodeValue(
				$key,
				$this->autotrim(
					$key,
					$value
				)
			);

		$this->afterSetKey(
			$key,
			$value,
			$previousValue
		);

		return $this;
	}

	/**
	 * Hook to perform additional options after setting a value.
	 *
	 * @param   string  $key
	 * @param   mixed   $newValue
	 * @param   mixed   $previousValue
	 *
	 * @return Dataobject
	 */
	protected function afterSetKey($key, $newValue, $previousValue)
	{
		return $this;
	}

	/**
	 * Optionally trim a value before it's stored in the data object.
	 *
	 * @param   string  $key
	 * @param   mixed   $value
	 *
	 * @return mixed
	 */
	protected function autotrim($key, $value)
	{
		if (
			is_string($value)
			&&
			array_key_exists(
				$key,
				$this->autotrimSpec
			))
		{
			$value = StringHelper::substr(
				$value,
				0,
				$this->autotrimSpec[$key]
			);
		}

		return $value;
	}


	/**
	 * Optionally encode a value before it's stored in the data object.
	 *
	 * @param   string  $key
	 * @param   mixed   $value
	 *
	 * @return mixed
	 */
	public function encodeValue($key, $value)
	{
		return $value;
	}

	/**
	 * Get an individual key of this object.
	 *
	 * @param   string  $key
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	protected function getKey($key)
	{
		return $this->validateKey($key)
			->decodeValue(
				$key,
				$this->data[$key]
			);
	}

	/**
	 * Optionally decode a value before it's returned from the data object. Will type-cast
	 * based on the dataTypes property.
	 *
	 * @param   string  $key
	 * @param   mixed   $value
	 *
	 * @return mixed
	 */
	public function decodeValue($key, $value)
	{
		if (array_key_exists($key, $this->dataTypes))
		{
			$value = System\Convert::enforceType($value, $this->dataTypes[$key]);
		}

		return $value;
	}

	/**
	 * Validate whether data for a given key is ok and can be used by the object. Possible
	 * processing is allowed to fix/update things here.
	 *
	 * @param   string  $key
	 *
	 * @return $this
	 * @throws \Exception
	 */
	protected function validateKey($key)
	{
		if (empty($key))
		{
			throw new \Exception('Trying to set/get empty key on ' . __CLASS__ . ' data object', 500);
		}

		// if replacing a keyed item, that key must be one of the data set
		if (!array_key_exists(
			$key,
			$this->data
		))
		{
			throw new \Exception('Trying to set/get unknown key ' . print_r($key, true) . ' / ' . print_r($this->data, true)
				. ' on ' . __CLASS__ . ' data object ' . print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), true), 500);
		}

		return $this;
	}

	/**
	 * Store a UTC datetime into the designated data field.
	 *
	 * @param   string  $key
	 * @param   bool    $update  If true, a new timestamp is created, else the request timestamp is used.
	 *
	 * @return Dataobject
	 */
	public function timestamp($key, $update = false)
	{
		$this->data[$key] = System\Date::getUTCNow('Y-m-d H:i:s', $update);

		return $this;
	}

	/**
	 * Store current UTC date into the designated data field.
	 *
	 * @param   string  $key
	 * @param   bool    $update  If true, a new timestamp is created, else the request timestamp is used.
	 *
	 * @return Dataobject
	 */
	public function datestamp($key, $update = false)
	{
		$this->data[$key] = System\Date::getUTCNow('Y-m-d', $update);

		return $this;
	}

	/**
	 * Increment a counter, by default by 1.
	 *
	 * @param   string  $key
	 * @param   int     $increment
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function increment($key, $increment = 1)
	{
		$this->data[$key] = $this->data[$key] ?? 0;

		if (!is_numeric($this->data[$key]))
		{
			throw new \Exception('wbLib: trying to increment/decrement a non-existing or non-numeric value.');
		}
		$this->data[$key] += $increment;

		return $this;
	}

	/**
	 * Decrement a counter, by default by 1.
	 *
	 * @param   string  $key
	 * @param   int     $decrement
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function decrement($key, $decrement = 1)
	{
		$this->increment($key, -$decrement);

		return $this;
	}

	/**
	 * Get the array of default values.
	 *
	 * @return array
	 */
	public function defaults()
	{
		return $this->defaults;
	}

	/**
	 * Whether value for a given key evaluates to truthy.
	 *
	 * @param   string  $key
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function isTruthy($key)
	{
		$value = $this->get($key);

		return !empty($value);
	}

	/**
	 * Whether value for a given key evaluates to falsy.
	 *
	 * @param   string  $key
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function isFalsy($key)
	{
		return !$this->isTruthy($key);
	}
}
