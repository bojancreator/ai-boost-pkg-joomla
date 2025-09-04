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
 *
 */

namespace Weeblr\Wblib\Forsef\System;

use Weeblr\Wblib\Forsef\Factory;

/* Security check to ensure this file is being included by a parent file. */
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * Simple hook system
 *
 */
class Hook
{
	/**
	 * A few priority values, higher priorities are
	 * executed first.
	 */
	const PRIORITY_NORMAL = 100;
	const PRIORITY_HIGHEST = 20;
	const PRIORITY_HIGHER = 50;
	const PRIORITY_HIGH = 80;
	const PRIORITY_LOW = 200;
	const PRIORITY_LOWEST = 300;

	/**
	 * Look for, and include_once if found, a functions file that can
	 * contains user provided code.
	 *
	 * Default search path is the one obtained from the platform.
	 *
	 * If a path is provided and/or a file name are provided, they are used instead, as in:
	 * {provided_full_path}/{provided_filed_name}
	 *
	 * @param   string  $fileName  Optional file name to include instead of wblib_functions.php.
	 * @param   string  $path      Root path to search for the functions file.
	 *
	 * @return bool
	 */
	public function load($fileName = 'wblib_functions.php', $path = '')
	{
		if (empty($path))
		{
			$path = Factory::get()->getThe('platform')->getHooksPath();
		}
		$path     = rtrim($path, '/\\');
		$fullPath = $path . '/' . $fileName;
		if (file_exists($fullPath))
		{
			// inject factory to allow access to libary and app.
			$factory = Factory::get();
			$hooks   = $factory->getThe('hook');

			include_once $fullPath;

			return true;
		}

		return false;
	}

	/**
	 * Add a hook, identified by an id (wblib.some_name),
	 * a callback and a priority
	 *
	 * @param   string    $id        Unique identifier for the hook
	 * @param   Callable  $callback  Callback that was passed to add method
	 * @param   int       $priority  Lower priorities are executed first. Default to 100.
	 *
	 * @return bool True if hook was added
	 **/
	public function add($id, $callback, $priority = 100)
	{
		return Factory::get()->getThe('platform')->addHook($id, $callback, $priority);
	}

	/**
	 * Remove a callback for a given hook
	 *
	 * @param   string    $id        Dot-joined unique identifier for the hook
	 * @param   Callable  $callback  Callback that was passed to add method
	 * @param   int|null  $priority  Optional param to restrict removal to a given priority level
	 *
	 * @return bool True if hook was removed
	 **/
	public function remove($id, $callback, $priority = null)
	{
		return Factory::get()->getThe('platform')->removeHook($id, $callback, $priority);

		return $removed;
	}

	/**
	 * Execute all callbacks registered for a hook id
	 * in order of priority
	 * Params can be modified by the callback, if so defined
	 * Execution can return values
	 *
	 * @return mixed|null
	 */
	public function run(...$args)
	{
		$this->execute(false, $args);
	}

	/**
	 * Execute all callbacks registered for a hook id
	 * in order of priority, only on first call.
	 * Params can be modified by the callback, if so defined
	 * Execution can return values
	 *
	 * @return mixed|null
	 */
	public function runOnce(...$args)
	{
		static $ran = [];

		$id = Wb\arrayGet($args, 0);
		if (in_array($id, $ran))
		{
			return;
		}

		$ran[] = $id;
		$this->execute(false, $args);
	}

	/**
	 * Execute all callbacks registered for a hook id
	 * in order of priority
	 * A value must be returned, which will normally be assigned
	 * by caller to replace current value
	 *
	 * @return mixed
	 */
	public function filter(...$args)
	{
		return $this->execute(true, $args);
	}

	/**
	 * Execute all callbacks registered for a hook id
	 * in order of priority
	 * A value must be returned, which will normally be assigned
	 * by caller to replace current value
	 *
	 * @param          $filter
	 * @param   array  $params
	 *
	 * @return mixed
	 */
	private function execute($filter, $params)
	{
		static $platform;

		if (is_null($platform))
		{
			$platform = Factory::get()->getThe('platform');
		}

		if ($filter)
		{
			return $platform->executeHook($filter, $params);
		}
		else
		{
			$platform->executeHook($filter, $params);
		}
	}

	/**
	 * Whether a given hook has callbacks registered.
	 *
	 * @param   string  $id
	 *
	 * @return bool
	 */
	public function hasHook($id)
	{
		return Factory::get()->getThe('platform')->hasHook($id);
	}
}
