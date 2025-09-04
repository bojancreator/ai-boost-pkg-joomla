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

namespace Weeblr\Wblib\Forsef\Wb;

use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Joomla\StringHelper\StringHelper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * A set of timesaving php functions
 *
 * @author  weeblr
 */

/**
 * Get a value by key from an array, defaulting to
 * a provided value if key doesn't exist.
 * Key can be an array of keys, which are then
 * traversed
 *
 * @param   array  $array
 * @param          $keys
 * @param   mixed  $default
 *
 * @return mixed
 */
function arrayGet($array, $keys, $default = null)
{
	if (empty($keys) && !\is_array($keys) && isset($array[$keys]))
	{
		// empty string can be a valid array index
		return $array[$keys];
	}

	if (empty($keys) && $keys !== 0)
	{
		return $array;
	}

	if (!\is_array($array))
	{
		return $default;
	}

	if (!\is_array($keys) && isset($array[$keys]))
	{
		return $array[$keys];
	}

	if (\is_array($keys))
	{
		$current = $array;
		foreach ($keys as $key)
		{
			if (!\is_array($current) || !\array_key_exists($key, $current))
			{
				return $default;
			}

			$current = $current[$key];
		}

		return $current;
	}

	return $default;
}

/**
 * Convert output of arrayGet to an integer.
 *
 * @param   array           $array
 * @param   array | string  $keys
 * @param   int             $default
 *
 * @return int
 */
function arrayGetInt($array, $keys, $default = 0)
{
	return (int) arrayGet($array, $keys, $default);
}

/**
 * Set a value by key from an array.
 * Key can be an array of keys, which are then
 * traversed
 *
 * @param   array  $array
 * @param   mixed  $keys
 * @param   mixed  $value
 *
 * @return mixed
 */
function arraySet($array, $keys, $value)
{
	if (!\is_array($array))
	{
		$array = array();
	}

	if (\is_scalar($keys))
	{
		// end recursion
		$array[$keys] = $value;

		return $array;
	}
	if (!\is_array($keys))
	{
		// objects?
		return $array;
	}

	// current iteration key
	$key = \array_shift($keys);

	if (!empty($keys))
	{
		if (!isset($array[$key]) || !\is_array($array[$key]))
		{
			$array[$key] = array();
		}
		$array[$key] = arraySet($array[$key], $keys, $value);
	}
	else
	{
		$array[$key] = $value;
	}

	return $array;
}

/**
 * Append item to array if not already there.
 *
 * @param $array
 * @param $value
 *
 * @return mixed
 */
function arrayAppendUnlessPresent($array, $value)
{
	if (!in_array($value, $array))
	{
		$array[] = $value;
	}

	return $array;
}

/**
 * Find if an array has a specific key.
 * Key can be an array of keys, which are then
 * traversed
 *
 * @param   array  $array
 * @param   mixed  $keys
 *
 * @return bool
 */
function arrayIsset($array, $keys)
{
	if (empty($keys) && $keys !== 0)
	{
		return false;
	}

	if (!\is_array($array))
	{
		return false;
	}

	if (!\is_array($keys) && isset($array[$keys]))
	{
		return true;
	}

	if (\is_array($keys))
	{
		$current = $array;
		foreach ($keys as $key)
		{
			if (!array_key_exists($key, $current))
			{
				return false;
			}

			$current = $current[$key];
		}

		return true;
	}

	return false;
}

/**
 * Find if an array has a specific key.
 * Key can be an array of keys, which are then
 * traversed.
 *
 * This is B/C way to fix a bug in arrayIsSet above which for some reason
 * is testing for isset($array[$keys]) which will return false if the key exists
 * but is set to null. Oddly, if $keys is an array, I was indeed using array_key_exists.
 * As it's not possible now to change the arrayIsSet behavior, arrayHasKey is
 * introduced instead.
 *
 * @param   array  $array
 * @param   mixed  $keys
 *
 * @return bool
 */
function arrayHasKey($array, $keys)
{
	if (empty($keys) && $keys !== 0)
	{
		return false;
	}

	if (!\is_array($array))
	{
		return false;
	}

	if (!\is_array($keys) && array_key_exists($keys, $array))
	{
		return true;
	}

	if (\is_array($keys))
	{
		$current = $array;
		foreach ($keys as $key)
		{
			if (!array_key_exists($key, $current))
			{
				return false;
			}

			$current = $current[$key];
		}

		return true;
	}

	return false;
}

/**
 * Get a value by key from an array and check if it is empty.
 *
 * @param   array  $array
 * @param          $keys
 *
 * @return bool
 */
function arrayIsEmpty($array, $keys)
{
	return arrayIsFalsy($array, $keys);
}

/**
 * Get a value by key from an array and check if it is empty.
 *
 * @param   array  $array
 * @param          $keys
 *
 * @return bool
 */
function arrayIsFalsy($array, $keys)
{
	$value = arrayGet($array, $keys, null);

	return empty($value);
}

/**
 * Get a value by key from an array and check if it is truthy
 *
 * @param   array  $array
 * @param          $keys
 *
 * @return bool
 */
function arrayIsTruthy($array, $keys)
{
	return !arrayIsFalsy($array, $keys);
}

/**
 * Get a value by key from an array and check if it is equal to a given value
 *
 * @param   array  $array
 * @param          $keys
 * @param   mixed  $value
 * @param   bool   $strict
 *
 * @return bool
 */
function arrayIsEqual($array, $keys, $value, $strict = false)
{
	$actualValue = arrayGet($array, $keys, null);

	return $strict ? $actualValue === $value : $actualValue == $value;
}

/**
 * Append a string to an array member
 * If not existing, the array member is created
 *
 * @param   array   $array
 * @param   mixed   $key
 * @param   string  $value
 *
 * @param   string  $glue
 *
 * @throws Exception
 */
function arrayKeyAppend(&$array, $key, $value, $glue = '')
{
	if (empty($key))
	{
		return;
	}
	if (!\is_array($array) && !empty($array))
	{
		throwException(new \InvalidArgumentException('Trying to initialize an array key, while not an array and not empty'));
	}
	else if (!\is_array($array))
	{
		throwException(new \InvalidArgumentException('Trying to initialize an array key, while not an array'));
	}

	$array[$key] = empty($array[$key]) ? $value : $array[$key] . $glue . $value;
}

/**
 * Set value of an array member. Create the item if it does not exist.
 *
 * Replaces:
 * $array['key'] = is_array($array['key'])
 * ? []
 * : $array['key']
 * $array['key'] = $value
 *
 * @param   array  $array
 * @param   mixed  $key
 * @param   mixed  $value
 *
 * @throws Exception
 */
function arrayKeySet(&$array, $key, $value)
{
	if (empty($key))
	{
		return;
	}

	if (!\is_array($array))
	{
		throwException(new \InvalidArgumentException('Trying to set an array key to a non array'));
	}

	if (!isset($array[$key]) || !\is_array($array[$key]))
	{
		$array[$key] = [];
	}

	$array[$key] = $value;
}

/**
 * Push a value into an array member. Create the item as an array if it does not exist.
 *
 * Replaces:
 * $array['key'] = is_array($array['key'])
 * ? []
 * : $array['key']
 * $array['key'][] = $value
 *
 * @param   array  $array
 * @param   mixed  $key
 * @param   mixed  $value
 *
 * @throws Exception
 */
function arrayKeyPush(&$array, $key, $value)
{
	if (empty($key))
	{
		return;
	}

	if (!\is_array($array))
	{
		throwException(new \InvalidArgumentException('Trying to set an array key to a non array'));
	}

	if (!isset($array[$key]) || !\is_array($array[$key]))
	{
		$array[$key] = [];
	}

	$array[$key][] = $value;
}

/**
 * Set initial value of an array member
 * only if it doesn't exist already
 * Replaces:
 * $array['key'] = isset($array['key'] ? $array['key'] : "some value";
 *
 * @param   array  $array
 * @param   mixed  $key
 * @param   mixed  $default
 *
 * @throws Exception
 */
function arrayKeyInit(&$array, $key, $default)
{
	if (is_null($key) || isset($array[$key]))
	{
		return;
	}
	if (!\is_array($array) && !empty($array))
	{
		throwException(new \InvalidArgumentException('Trying to initialize an array key, while not an array and not empty'));
	}
	else if (!\is_array($array))
	{
		$array = [];
	}

	$array[$key] = $default;
}

/**
 * Merges an array with one of the values of an associative array
 * initializing it if that key doesn't exists already
 *
 * Replaces:
 * $array['key'] = isset($array['key'] ? \array_merge($array['key'], $newArray) : $newArray;
 *
 * Note: if the key exists, but doesn't contain an array, its value is cast to an array
 * Note: if the passed "$array" is actually not an array, it's left untouched
 *
 * @param   array  $array
 * @param   mixed  $key
 * @param   array  $toMerge
 */
function arrayKeyMerge(&$array, $key, $toMerge)
{
	$array = empty($array) ? [] : $array;
	if (\is_array($array))
	{
		$array[$key] = empty($array[$key]) ? (array) $toMerge : \array_merge((array) $array[$key], (array) $toMerge);
	}
}

/**
 * Add a number to the current value of an array key.
 * If not existing, the array member is created an initialized with the $default value.
 *
 * @param   array      $array
 * @param   mixed      $key
 * @param   int|float  $value
 * @param   int|float  $default
 *
 * @throws Exception
 */
function arrayKeyAdd(&$array, $key, $value, $default = 0)
{
	if (empty($key))
	{
		return;
	}
	if (!\is_array($array) && !empty($array))
	{
		throwException(new \InvalidArgumentException('Trying to add to an array key, while not an array and not empty'));
	}
	else if (!\is_array($array))
	{
		throwException(new \InvalidArgumentException('Trying to add to an array key, while not an array'));
	}

	if (array_key_exists($key, $array))
	{
		$array[$key] += $value;
	}
	else
	{
		$array[$key] = $default + $value;
	}
}

/**
 * Substract a number to the current value of an array key.
 * If not existing, the array member is created an initialized with the $default value.
 *
 * @param   array      $array
 * @param   mixed      $key
 * @param   int|float  $value
 * @param   int|float  $default
 *
 * @throws Exception
 */
function arrayKeySub(&$array, $key, $value, $default = 0)
{
	if (empty($key))
	{
		return;
	}
	if (!\is_array($array) && !empty($array))
	{
		throwException(new \InvalidArgumentException('Trying to substract to an array key, while not an array and not empty'));
	}
	else if (!\is_array($array))
	{
		throwException(new \InvalidArgumentException('Trying to substract to an array key, while not an array'));
	}

	if (array_key_exists($key, $array))
	{
		$array[$key] -= $value;
	}
	else
	{
		$array[$key] = $default - $value;
	}
}

/**
 * Filter an array to remove empty values. Resulting array is re-indexed.
 *
 * @param   array  $data
 * @param   array  $keyList
 *
 * @return array
 */
function arrayFilterEmpty($data)
{
	return is_array($data)
		? array_values(array_filter($data))
		: $data;
}

/**
 * Filter an associative array, returning only keys listed
 * in the second parameter
 *
 * @param   array  $data
 * @param   array  $keyList
 *
 * @return array
 */
function arrayFilterByKey($data, $keyList)
{
	// return untouched if invalid params
	if (!\is_array($data) || !\is_array($keyList))
	{
		return $data;
	}

	$filtered = array();
	foreach ($data as $key => $value)
	{
		if (in_array($key, $keyList))
		{
			$filtered[$key] = $value;
		}
	}

	return $filtered;
}

/**
 * Merges  arrays, checking that they are indeed arrays
 *
 * @param [array, array, ...]
 *
 * @return array
 */
function arrayMerge(...$args)
{
	$merged = [];
	foreach ($args as $array)
	{
		$merged = \array_merge($merged, (array) $array);
	}

	return $merged;
}

/**
 * Ensure a variable is an array.
 *
 * @param   mixed
 *
 * @return array
 */
function arrayEnsure($thing)
{
	return \is_array($thing) ? $thing : array($thing);
}

/**
 * Return passed value if not empty, default otherwise
 *
 * @param   mixed  $value
 * @param   mixed  $default
 *
 * @return mixed
 */
function initEmpty($value, $default)
{
	return empty($value) ? $default : $value;
}

function wbDump($value, $name = '', $asString = false, $newLine = '<br />', $codeWrapper = '<pre>%s</pre>')
{
	$back = debug_backtrace(false);
	if (count($back) > 1)
	{
		$caller = arrayGet($back[1], 'class', '-') . ' | ' . arrayGet($back[1], 'function', '-') . ' | ' . arrayGet($back[0], 'line', '-');
	}
	else
	{
		$caller = '';
	}
	$output = '';
	$name   = empty($name) ? 'Var dump' : $name;
	$output .= $newLine . '<b>' . $name . ': </b><small>(' . $caller . ')</small>' . $newLine;
	$output .= sprintf($codeWrapper, is_null($value) ? 'null' : print_r($value, true));
	$output .= $newLine;

	echo $asString ? null : $output;

	return $output;
}

function wbLog($message, $includeBacktrace = null, $newLine = '<br />', $codeWrapper = '<pre>%s</pre>')
{
	if (!defined('WBLIB_LOG_TO_SCREEN') || WBLIB_LOG_TO_SCREEN === false)
	{
		return;
	}

	// include backtrace if globally set, or based on call argument
	if (defined('WBLIB_LOG_TO_SCREEN_INCLUDE_BACKTRACE') && WBLIB_LOG_TO_SCREEN_INCLUDE_BACKTRACE !== false && $includeBacktrace !== false)
	{
		$includeBacktrace = true;
	}

	if ($includeBacktrace)
	{
		$back    = \debug_backtrace(false);
		$message .= $newLine . \sprintf($codeWrapper, print_r($back, true));
	}

	echo $message . $newLine;
}

function throwException(\Throwable $exception, $log = true)
{
	// logging globally disabled
	if (defined('WBLIB_LOG_EXCEPTIONS') && WBLIB_LOG_EXCEPTIONS !== true)
	{
		$log = false;
	}

	if ($log)
	{
		// build log message ourselves rather than relying on (string) $exception
		// the latter uses php built-in display of stack trace, which cuts off
		// long values
		$logMsg = 'Exception ' . \get_class($exception) . ' with message "' . $exception->getMessage() . '" in ' . $exception->getFile() . ':'
			. $exception->getLine();
		$logMsg .= "\nStack trace:\n";
		$logMsg .= $exception->getTraceAsString();

		System\Log::libraryError($logMsg);
	}

	throw $exception;
}

function contains($haystack, $needles, $caseSensitive = true)
{
	if (empty($haystack))
	{
		return false;
	}

	if (!$caseSensitive)
	{
		$haystack = StringHelper::strtolower($haystack);
	}

	if (\is_string($needles))
	{
		$needles = [$needles];
	}

	if (\is_array($needles))
	{
		foreach ($needles as $needle)
		{
			if (!$caseSensitive)
			{
				$needle = StringHelper::strtolower($needle);
			}
			if (!empty($needle) && StringHelper::strpos($haystack, $needle) !== false)
			{
				return true;
			}
		}
	}

	return false;
}

function startsWith($haystack, $needles)
{
	if (empty($haystack))
	{
		return false;
	}

	if (is_string($needles))
	{
		return !empty($needles) && 0 === StringHelper::strpos($haystack, $needles);
	}
	else if (\is_array($needles))
	{
		foreach ($needles as $needle)
		{
			if (!empty($needle) && 0 === StringHelper::strpos($haystack, $needle))
			{
				return true;
			}
		}
	}

	return false;
}

function endsWith($haystack, $needles)
{
	if (empty($haystack))
	{
		return false;
	}

	if (is_string($needles))
	{
		return !empty($needles) && StringHelper::substr($haystack, -StringHelper::strlen($needles)) == $needles;
	}
	else if (\is_array($needles))
	{
		foreach ($needles as $needle)
		{
			if (!empty($needle) && StringHelper::substr($haystack, -StringHelper::strlen($needle)) == $needle)
			{
				return true;
			}
		}
	}

	return false;
}

function rTrim($string, $toTrims)
{
	if (!is_array($toTrims))
	{
		$toTrims = [$toTrims];
	}

	foreach ($toTrims as $toTrim)
	{
		if (endsWith($string, $toTrim))
		{
			$string = StringHelper::substr($string, 0, -StringHelper::strlen($toTrim));
		}
	}

	return $string;
}

function lTrim($string, $toTrims)
{
	if (!is_array($toTrims))
	{
		$toTrims = [$toTrims];
	}

	foreach ($toTrims as $toTrim)
	{
		if (startsWith($string, $toTrim))
		{
			$string = StringHelper::substr($string, StringHelper::strlen($toTrim));
		}
	}

	return $string;
}

/**
 * Lower case the first character of a string, preserving case in the remaining part.
 *
 * @param $string
 *
 * @return mixed
 */
function lcFirst($string)
{
	return strtolower(substr($string, 0, 1)) . substr($string, 1);
}

/**
 * Join hopefully strings with a glue string
 * Warning: empty strings are removed prior to joining
 *
 * @param   string  $glue  the string to use to glue things
 * @param   mixed variable numbers or aguments te be joined
 *
 * @return mixed
 */
function join($glue, ...$args)
{
	return \join($glue, array_filter($args));
}

/**
 * Join (hopefully) strings with dots
 * Warning: empty strings are removed prior to joining
 *
 * @param   string  $mixed  numbers or aguments te be joined
 *
 * @return mixed
 */
function dotJoin(...$args)
{
	return \join('.', \array_filter($args));
}

/**
 * Join (hopefully) strings while trimming them.
 * Warning: empty strings are removed prior to joining
 *
 * @param   mixed  $mixed  numbers or arguments te be joined
 *
 * @return mixed
 */
function trimJoin(...$args)
{
	return \join(
		'',
		\array_map(
			function ($item) {
				return \trim($item);
			},
			$args
		)
	);
}

/**
 * Join (hopefully) strings with slashes.
 * Warning: empty strings are removed prior to joining
 *
 * @param   mixed  $mixed  numbers or aguments te be joined
 *
 * @return mixed
 */
function slashJoin(...$args)
{
	return \join('/', \array_filter($args));
}

/**
 * Repalce backslashes with forward slashes.
 *
 * @param   string
 *
 * @return string
 */
function slashForward($string)
{
	return \str_replace('\\', '/', $string);
}

/**
 * Join (hopefully) strings with slashes
 *  - empty strings are removed
 *  - elements are trimmed of slashes prior to joining, except first and last
 *
 * @param   string  $mixed  either an array of strings or a series of strings as different arguments.
 *
 * @return mixed
 */
function slashTrimJoin(...$args)
{
	if (isset($args[0]) && \is_array($args[0]))
	{
		$args = $args[0];
	}
	$args = \array_values(
		\array_filter($args)
	);

	if (empty($args))
	{
		return '';
	}
	if (\count($args) == 1)
	{
		return $args[0];
	}

	$leadingSlash  = '';
	$trailingSlash = '';

	\array_walk(
		$args,
		function (&$element, $index, $maxIndex) use (&$leadingSlash, &$trailingSlash) {
			if ($index === 0)
			{
				if ('/' === $element)
				{
					$leadingSlash = '/';
				}

				$element = rtrim($element, '/');
			}
			else if ($index === $maxIndex)
			{
				if ('/' === $element)
				{
					$trailingSlash = '/';
				}
				$element = ltrim($element, '/');
			}
			else
			{
				$element = trim($element, '/');
			}
		},
		\count($args) - 1
	);

	return $leadingSlash . \join('/', array_filter($args)) . $trailingSlash;
}

/**
 * Replace dots with slashes in a string
 *
 * @param $string
 *
 * @return mixed
 */
function dot2Slash($string)
{
	return \str_replace('.', '/', $string);
}

/**
 * Returns the object passed.
 * Allow creating and using an object in one go
 * as in:
 *
 * with(new Someclass())->someMethod();
 *
 * @param $object
 *
 * @return mixed
 */
function with($object)
{
	return $object;
}

/**
 * @package     Joomla.Platform
 * @subpackage  HTML
 *
 * @copyright   Copyright (C) 2005 - 2012 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */
/**
 * HTML helper class for rendering manipulated strings.
 *
 * @package     Joomla.Platform
 * @subpackage  HTML
 * @since       11.1
 */
/**
 * Abridges text strings over the specified character limit. The
 * behavior will insert an ellipsis into the text replacing a section
 * of variable size to ensure the string does not exceed the defined
 * maximum length. This method is UTF-8 safe.
 *
 * For example, it transforms "Really long title" to "Really...title".
 *
 * Note that this method does not scan for HTML tags so will potentially break them.
 *
 * @param   string   $text    The text to abridge.
 * @param   integer  $length  The maximum length of the text (default is 50).
 * @param   integer  $intro   The maximum length of the intro text (default is 30).
 * @param   string   $bridge  the string to use to bridge
 *
 * @return  string   The abridged text.
 *
 * @since   11.1
 */
function abridge($text, $length = 50, $intro = 30, $bridge = '...')
{
	// Abridge the item text if it is too long.
	if (StringHelper::strlen($text) > $length)
	{
		// Determine the remaining text length.
		$remainder = $length - ($intro + StringHelper::strlen($bridge));

		// Extract the beginning and ending text sections.
		$beg = StringHelper::substr($text, 0, $intro);
		$end = StringHelper::substr($text, StringHelper::strlen($text) - $remainder);

		// Build the resulting string.
		$text = $beg . $bridge . $end;
	}

	return $text;
}

