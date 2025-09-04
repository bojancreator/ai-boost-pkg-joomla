<?php
/**
 * Project: 4SEF
 *
 * @package          4SEF
 * @copyright        Copyright Weeblr llc - 2022 -2025
 * @author           Yannick Gaultier - Weeblr llc
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          2.6.2.644
 * @date        2025-06-02
 */

use Weeblr\Wblib\Forsef\Factory;
use Weeblr\Wblib\Forsef\Db;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Getvarslist
{
	/**
	 * @var array Stores used GET variables
	 */
	private static $getVars = [];

	/**
	 * @var array Stores non-used vars, used later to rebuild the non-sef
	 */
	private static $rebuildNonSef = [];

	public static function init($vars)
	{
		self::$getVars = $vars;
	}

	public static function get($vars)
	{
		return self::$getVars;
	}

	public static function add($varName, $varValue)
	{
		if (empty($varName))
		{
			return;
		}

		self::$getVars[$varName] = $varValue;

		// check and remove from self::$rebuildNonSef, in case this param was previously added to the list, using shRemoveFromGETVarsList
		if (!empty(self::$rebuildNonSef))
		{
			$indexesFound = array();
			if (is_array($varValue))
			{
				foreach ($varValue as $value)
				{
					foreach (self::$rebuildNonSef as $index => $item)
					{
						if ($item == '&' . $varName . '[]=' . $value)
						{
							$indexesFound[] = $index;
							break;
						}
					}
				}
			}
			else
			{
				foreach (self::$rebuildNonSef as $index => $item)
				{
					if ($item == '&' . $varName . '=' . $varValue)
					{
						$indexesFound[] = $index;
						break;
					}
				}
			}

			foreach ($indexesFound as $indexFound)
			{
				unset(self::$rebuildNonSef[$indexFound]);
			}
		}
	}

	public static function remove($varName)
	{
		if (!empty($varName))
		{
			if (isset(self::$getVars[$varName]))
			{
				$storedValue = self::$getVars[$varName];
				if (is_array($storedValue))
				{
					// array handling, fix provided by VinhCV
					foreach ($storedValue as $value)
					{
						self::$rebuildNonSef[] = '&' . $varName . '[]=' . $value;
					}
				}
				else
				{
					self::$rebuildNonSef[] = '&' . $varName . '=' . $storedValue;
				} // build up a non-sef string with the GET vars used to
				// build the SEF string. This string will be the one stored in db instead of
				// the full, original one
				unset(self::$getVars[$varName]);
			}
		}
	}

//	we manage the rebuilt nonsef as a string, no reason for that. We must use directly the $vars array
//shRebuildNonSefString is only used in shFinalizePlugin, to rebuild the non-sef which in turns is used in getLocation.
//
//getLocation is used to convert the array of "title" into the final sef. Includes transliteration, truncating to max length,adding pagination,
//storing to memory cache, generating pageiD
	public static function rebuildNonSef($string)
	{
		// V 1.2.4.m moved to main component from plugins
		// rebuild a non-sef string, removing all GET vars that were not turned into SEF
		// as we do not want to store them in DB

		global $rebuildNonSef;
		$sefConfig = Sh404sefFactory::getConfig();
		if (!$sefConfig->shAppendRemainingGETVars || empty(self::$rebuildNonSef))
		{
			return $string;
		}
		$shNewString = '';
		if (!empty(self::$rebuildNonSef))
		{
			foreach (self::$rebuildNonSef as $param)
			{
				// need to sort, and still place option in first pos.
				if (strpos($param, 'sh404SEF_title=') !== false)
				{
					$param = str_replace('sh404SEF_title=', 'title=', $param);
				}
				$shNewString .= $param;
			}
			$ret = Sh404sefHelperUrl::sortUrl('index.php?' . JString::ltrim($shNewString, '&'));
		}
		return $ret;
	}
}