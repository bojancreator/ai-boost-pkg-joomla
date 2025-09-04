<?php
/**
 * Project:                 4SEF
 *
 * @author           Yannick Gaultier - Weeblr llc
 * @copyright        Copyright Weeblr llc - 2022 -2025
 * @package          4SEF
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          @build_version_full_build@
 * @date                2025-06-02
 */

namespace Weeblr\Wblib\Forsef\System;

use Weeblr\Wblib\Forsef\Wb;

// no direct access
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

class Convert
{
	const STRING = 0;
	const INT = 1;
	const ARRAY = 2;
	const ARRAY_STRING = 10;
	const ARRAY_INT = 11;
	const OBJECT = 20;
	const BOOLEAN = 21;
	const FLOAT = 22;

	public static function hexToDecimal($originalHex)
	{
		if (!extension_loaded('bcmath'))
		{
			Wb\throwException(new \RuntimeException(__METHOD__ . ': Using Convert::hexToDecimal without BCMATH extension', 500));
		}

		$dec         = hexdec(substr($originalHex, -4));
		$originalHex = substr($originalHex, 0, -4);
		$running     = 1;
		while (!empty($originalHex))
		{
			$hex         = hexdec(substr($originalHex, -4));
			$running     = bcmul($running, 65536);
			$dec1        = bcmul($running, $hex);
			$dec         = bcadd($dec1, $dec);
			$originalHex = substr($originalHex, 0, -4);
		}

		return $dec;
	}

	/**
	 * Internal method to get a JavaScript object notation string from an array
	 * Customizd to handle systems wich decimal separator is not a dot
	 * An alternative would be to set locale to C before handling each numeric value
	 * and restore afterwards.
	 *
	 * @param   array  $array  The array to convert to JavaScript object notation
	 *
	 * @return  string  JavaScript object notation representation of the array
	 *
	 * @since   3.0
	 */
	public static function arrayToJSObject($array = array())
	{
		static $decimalPoint = null;
		static $thousandsSep = null;

		if (is_null($decimalPoint) || is_null($thousandsSep))
		{
			$localeInfo   = localeconv();
			$decimalPoint = $localeInfo['decimal_point'];
			$thousandsSep = $localeInfo['thousands_sep'];
		}

		$elements = array();

		foreach ($array as $k => $v)
		{
			// Don't encode either of these types
			if (is_null($v) || is_resource($v))
			{
				continue;
			}

			// Safely encode as a Javascript string
			$key = \json_encode((string) $k);

			if (is_bool($v))
			{
				$elements[] = $key . ': ' . ($v ? 'true' : 'false');
			}
			elseif (is_numeric($v))
			{
				$value      = str_replace($thousandsSep, '', ($v + 0));
				$value      = str_replace($decimalPoint, '.', $value);
				$elements[] = $key . ': ' . $value;
			}
			elseif (is_string($v))
			{
				if (strpos($v, '\\') === 0)
				{
					// Items such as functions and JSON objects are prefixed with \, strip the prefix and don't encode them
					$elements[] = $key . ': ' . substr($v, 1);
				}
				else
				{
					// The safest way to insert a string
					$elements[] = $key . ': ' . \json_encode((string) $v);
				}
			}
			else
			{
				$elements[] = $key . ': ' . self::arrayToJSObject(is_object($v) ? get_object_vars($v) : $v);
			}
		}

		return '{' . implode(',', $elements) . '}';
	}

	/**
	 * Perform a type cast based on a definition.
	 *
	 * @param   mixed  $value
	 * @param   int    $type
	 *
	 * @return mixed
	 */
	public static function enforceType($value, $type)
	{
		switch ($type)
		{
			case self::STRING:
				$value = empty($value)
					? ''
					: (string) $value;
				break;
			case self::INT:
				$value = (int) $value;
				break;
			case self::FLOAT:
				$value = (float) $value;
				break;
			case self::BOOLEAN:
				$value = (bool) $value;
				break;
			case self::ARRAY:
				$value = Wb\arrayEnsure($value);
				break;
			case self::ARRAY_STRING:
				$value = array_map(
					function ($item) {
						return (string) $item;
					},
					Wb\arrayEnsure($value)
				);
				break;
			case self::ARRAY_INT:
				$value = array_map(
					'intval',
					Wb\arrayEnsure($value)
				);
				break;
			case self::OBJECT:
				$value = (object) $value;
				break;
		}

		return $value;
	}

	/**
	 * Format a numerical file size into a
	 * human readable format
	 *
	 * From http://uk2.php.net/manual/de/features.file-upload.php#88591
	 *
	 * @param   integer  $filesize   the numerical file size
	 * @param   integer  $precision  optional precision, default to 0
	 */
	public static function displayableFileSize($filesize, $precision = 0)
	{
		$units = array('B', 'KB', 'MB', 'GB', 'GB');

		$filesize = max($filesize, 0);
		$pow      = floor(($filesize ? log($filesize) : 0) / log(1024));
		$pow      = min($pow, count($units) - 1);

		$filesize /= pow(1024, $pow);

		return round($filesize, $precision) . ' ' . $units[$pow];
	}

}
