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
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Base\Base;

/** ensure this file is being included by a parent file */
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * Base class for rendering a display layout
 *
 * @since       0.2.1
 */
class LayoutBase extends Base
{
	/**
	 * Storage for display data
	 *
	 * @var null
	 */
	protected $__data = null;

	/**
	 * Method to render the layout.
	 *
	 * @param   object  $__data  Object which properties are used inside the layout file to build displayed output
	 *
	 * @return  string  The necessary HTML to display the layout
	 *
	 * @since   0.2.1
	 */
	public function render($__data)
	{
		$this->__data = $__data;

		return '';
	}

	/**
	 * Same as @see WblMvcLayout_Base::get, but returned output has [ and ] removed, to serve as HTML id
	 *
	 * @param   string  $key
	 * @param   string  $default
	 *
	 * @return string
	 */
	public function getAsId($key, $default = '')
	{
		return System\Strings::asHtmlId(
			$this->getAsAttr(
				$key,
				$default
			)
		);
	}

	/**
	 * Display the time elapsed between today in a fuzzy way (ie 2 minutes ago, in 30 seconds)
	 *
	 * @param   srtring            $key
	 * @param   string | DateTime  $default
	 * @param   string | DateTime  $refDate  Optional reference date, default to 'now'
	 *
	 * @return string
	 */
	public function getAsMoments($key, $default = '', $refDate = 'now', $options = array())
	{
		return System\Date::getAsMoments(
			$this->getAsDateTime(
				$key,
				$default
			),
			$refDate
		);
	}

	/**
	 * Same as @see WblMvcLayout_Base::get, but returned output is used to build a DateTime object
	 *
	 * @param   string  $key
	 * @param   mixed   $default
	 *
	 * @return DateTime
	 */
	public function getAsDateTime($key, $default = null)
	{
		return System\Date::toDateTimeObject(
			$this->get(
				$key,
				$default
			)
		);
	}

	/**
	 * Get the full display data variable
	 *
	 * @return mixed
	 */
	protected function getDisplayData()
	{
		return $this->__data;
	}

	/**
	 * Check if some display data has been set to render, either globally
	 * or for a specific display data key
	 *
	 * @param   string  $key
	 * @param   string  $subkey  If $key item exists, and is an array, we can check for a subkey in that array
	 *
	 * @return bool
	 */
	protected function hasDisplayData($key = '', $subkey = null)
	{
		$hasDisplayData = empty($key) ? !empty($this->__data) : !empty($this->__data[$key]);
		if (!empty($subkey))
		{
			$hasDisplayData = !empty($this->__data[$key]) && is_array($this->__data[$key]) && !empty($this->__data[$key][$subkey]);
		}

		return $hasDisplayData;
	}

	/**
	 * Count items in a particular keyed data element
	 *
	 * @param   string  $key
	 *
	 * @return int
	 */
	protected function count($key = '')
	{
		$data = $this->get($key);

		return count($data);
	}

	/**
	 * Get a display data variable, identified by its key in the
	 * data set passed to the render() method. Assumes this data is
	 * an associative array. If not, or if key not set, returns $default value
	 *
	 * @param   string  $key
	 * @param   mixed   $default
	 *
	 * @return mixed
	 */
	protected function get($key, $default = null)
	{
		return isset($this->__data[$key]) ? $this->__data[$key] : $default;
	}

	/**
	 * Escape a string before display. Wrapper around htmlspecialchars
	 *
	 * @param   string  $string
	 * @param   int     $flags
	 * @param   string  $encoding
	 *
	 * @return string
	 */
	protected function escape($string, $flags = ENT_COMPAT, $encoding = 'UTF-8')
	{
		if (empty($string))
		{
			return $string;
		}

		return htmlspecialchars(
			$string,
			$flags,
			$encoding
		);
	}

	/**
	 * Same as @see WblMvcLayout_Base::get, but returned output is escaped with esc_html
	 * through the @see WblMvcLayout_Base::escape method
	 *
	 * @param   string  $key
	 * @param   mixed   $default
	 *
	 * @return string
	 */
	protected function getEscaped($key, $default = null)
	{
		return $this->escape(
			isset($this->__data[$key]) ? $this->__data[$key] : $default
		);
	}

	/**
	 * Same as @see WblMvcLayout_Base::get, but returned output cast to an array
	 *
	 * @param   string  $key
	 * @param   array   $default
	 *
	 * @return array
	 */
	protected function getAsArray($key, $default = array())
	{
		$value = $this->get(
			$key,
			$default
		);

		return is_array($value) ? $value : (array) $value;
	}

	/**
	 * Get a specific entry in an associative array
	 *
	 * @param   string  $key         Key of the associative array to lookup
	 * @param   string  $keyInArray  Key of the desired item in the associative array
	 * @param   mixed   $default     Returned if not an array, or item in array not found or empty
	 *
	 * @return mixed
	 */
	protected function getInArray($key, $keyInArray, $default = null)
	{
		$value = $default;
		if ($this->hasDisplayData($key))
		{
			$array = $this->getAsArray($key);
			$value = Wb\arrayGet($array, $keyInArray, $default);
		}

		return $value;
	}

	/**
	 * Get a specific entry in an associative array and escape it.
	 *
	 * @param   string  $key         Key of the associative array to lookup
	 * @param   string  $keyInArray  Key of the desired item in the associative array
	 * @param   mixed   $default     Returned if not an array, or item in array not found or empty
	 *
	 * @return mixed
	 */
	protected function getInArrayEscaped($key, $keyInArray, $default = null)
	{
		return $this->escape(
			$this->getInArray(
				$key,
				$keyInArray,
				$default
			)
		);
	}

	/**
	 * Get a specific property of an object
	 *
	 * @param   string  $key
	 * @param   string  $keyInObject
	 * @param   mixed   $default
	 *
	 * @return mixed
	 */
	protected function getInObject($key, $keyInObject, $default = null)
	{
		$value = $default;
		if ($this->hasDisplayData($key))
		{
			$object = $this->getAsArray($key);
			$value  = empty($object[$keyInObject]) ? $default : $object[$keyInObject];
		}

		return $value;
	}

	/**
	 * Get a specific property of an object and escape it.
	 *
	 * @param   string  $key
	 * @param   string  $keyInObject
	 * @param   mixed   $default
	 *
	 * @return mixed
	 */
	protected function getInObjectEscaped($key, $keyInObject, $default = null)
	{
		return $this->escape(
			$this->getInObject(
				$key,
				$keyInObject,
				$default
			)
		);
	}

	/**
	 * Same as @see WblMvcLayout_Base::get, but returned output cast to an integer
	 *
	 * @param   string  $key
	 * @param   int     $default
	 *
	 * @return int
	 */
	protected function getAsInt($key, $default = 0)
	{
		return (int) $this->get(
			$key,
			$default
		);
	}

	/**
	 * Same as @see WblMvcLayout_Base::get, but returned output cast to an integer
	 * and then formatted as a string:
	 *
	 * Format into K and M for large number
	 * 0 -> 9999 : literal
	 * 10 000 -> 999999 : 10K -> 999,9K (max one decimal)
	 * > 1000 000 : 1M -> 1,9M (max 1 decimals)
	 *
	 * @param   string  $key
	 * @param   int     $default
	 *
	 * @return string
	 */
	protected function getAsFormattedInt($key, $default = 0)
	{
		return System\Strings::formatIntForTitle(
			$this->get(
				$key,
				$default
			)
		);
	}

	/**
	 * Same as @see WblMvcLayout_Base::get, but returned output is escaped with esc_url
	 *
	 * @param   string  $key
	 * @param   string  $default
	 *
	 * @return string
	 */
	protected function getAsUrl($key, $default = '')
	{
		$url = $this->get(
			$key,
			$default
		);
		$url = str_replace(' ', '%20', $url);

		return $this->escape(
			$url
		);
	}

	/**
	 * Same as @see WblMvcLayout_Base::getAsUrl, but returned url is made absolute
	 *
	 * @param   string  $key
	 * @param   string  $default
	 *
	 * @return string
	 */
	protected function getAsAbsoluteUrl($key, $default = '', $forceDomain = false)
	{
		$url = System\Route::absolutify(
			$this->get(
				$key,
				$default
			),
			$forceDomain
		);

		$url = str_replace(' ', '%20', $url);

		return $this->escape(
			$url
		);
	}

	/**
	 * Same as @see WblMvcLayout_Base::get, but returned output is escaped with esc_js
	 *
	 * @param   string  $key
	 * @param   string  $default
	 *
	 * @return string
	 */
	protected function getAsJs($key, $default = '')
	{
		return $this->escape(
			$this->get(
				$key,
				$default
			)
		);
	}

	/**
	 * Same as @see WblMvcLayout_Base::get, but returned output is json encoded
	 *
	 * @param   string  $key
	 * @param   string  $default
	 *
	 * @return string
	 */
	protected function getAsJson($key, $default = '')
	{
		return \json_encode(
			$this->get(
				$key,
				$default
			)
		);
	}

	/**
	 * Same as @see WblMvcLayout_Base::get, but returned output is escaped with esc_attr
	 *
	 * @param   string  $key
	 * @param   string  $default
	 *
	 * @return string
	 */
	protected function getAsAttr($key, $default = '')
	{
		return $this->escape(
			$this->get(
				$key,
				$default
			),
			ENT_QUOTES
		);
	}

	/**
	 * Whether the provided key resolves to something falsy. True if the key does not exist at all.
	 *
	 * @param   string  $key
	 *
	 * @return string
	 */
	protected function isFalsy($key)
	{
		$value = $this->get($key);

		return empty($value);
	}

	/**
	 * Whether the provided key resolves to something truthy.
	 *
	 * @param   string  $key
	 *
	 * @return string
	 */
	protected function isTruthy($key)
	{
		$value = $this->get($key);

		return !empty($value);
	}

}
