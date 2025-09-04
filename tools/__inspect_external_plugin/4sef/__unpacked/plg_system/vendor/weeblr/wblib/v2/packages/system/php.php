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

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

class Php
{
	/**
	 * Fetch a private or protected property from an object.
	 *
	 * @param   string   $className
	 * @param   string   $propertyName
	 * @param   object   $instance
	 * @param   boolean  $static
	 *
	 * @return mixed property value, or null
	 */
	public function getProtectedProperty($className, $propertyName, $instance, $static = false)
	{
		static $_classesCache = array();
		static $_propertiesCache = array();

		try
		{
			if (empty($_propertiesCache[$className . $propertyName]))
			{
				if (empty($_classesCache[$className]))
				{
					$_classesCache[$className] = new \ReflectionClass($className);
				}
				$_propertiesCache[$className . $propertyName] = $_classesCache[$className]->getProperty($propertyName);
				$_propertiesCache[$className . $propertyName]->setAccessible(true);
			}
			$propertyValue = $static ? $_propertiesCache[$className . $propertyName]->getStaticValue($instance)
				: $_propertiesCache[$className . $propertyName]->getValue($instance);
		}
		catch (\Throwable $e)
		{
			Log::libraryError('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
			$propertyValue = null;
		}
		catch (\Exception $e)
		{
			Log::libraryError('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
			$propertyValue = null;
		}

		return $propertyValue;
	}

	/**
	 * A function to diff_assoc 2 arrays by comparing their values properly
	 * instead of their string representation which throw PHP notices as soon
	 * as one array is multidimensional.
	 *
	 * Gathered from various PHP doc comments and stackoverflow responses.
	 *
	 * @param   array  $array1
	 * @param   array  $array2
	 *
	 * @return array
	 */
	public function arrayDiffAssocRecursive($array1, $array2)
	{
		$difference = [];
		foreach ($array1 as $key => $value)
		{
			if (is_array($value))
			{
				if (!isset($array2[$key]) || !is_array($array2[$key]))
				{
					$difference[$key] = $value;
				}
				else
				{
					$new_diff = $this->arrayDiffAssocRecursive($value, $array2[$key]);
					if (!empty($new_diff))
					{
						$difference[$key] = $new_diff;
					}
				}
			}
			else if (!array_key_exists($key, $array2) || $array2[$key] !== $value)
			{
				$difference[$key] = $value;
			}
		}

		return $difference;
	}
}