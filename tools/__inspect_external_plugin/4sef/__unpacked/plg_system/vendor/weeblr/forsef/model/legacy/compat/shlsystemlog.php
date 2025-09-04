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
use Weeblr\Wblib\Forsef\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class ShlSystem_Log
{
	/**
	 * Log a message with level Error
	 *
	 * @param string message
	 * @param mixed various params to be sprintfed into the msg
	 *
	 * @return boolean true if success
	 */
	public static function error($prefix)
	{
		self::getLogger()->error(
			self::prepareArguments(
				func_get_args()
			)
		);
	}

	/**
	 * Log a message with level Debug
	 *
	 * @param string message
	 * @param mixed various params to be sprintfed into the msg
	 *
	 * @return boolean true if success
	 */
	public static function debug($prefix)
	{
		self::getLogger()->debug(
			self::prepareArguments(
				func_get_args()
			)
		);
	}

	/**
	 * Log a message with level Info
	 *
	 * @param string message
	 * @param mixed various params to be sprintfed into the msg
	 *
	 * @return boolean true if success
	 */
	public static function info($prefix)
	{
		self::getLogger()->info(
			self::prepareArguments(
				func_get_args()
			)
		);
	}

	/**
	 * Log a message with level Custom
	 *
	 * @param string message
	 * @param mixed various params to be sprintfed into the msg
	 *
	 * @return boolean true if success
	 */
	public static function custom($prefix, $level, $category)
	{
		self::getLogger()->info(
			self::prepareArguments(
				func_get_args()
			)
		);
	}

	/**
	 * Retrieve the app logger.
	 *
	 * @return System\Logger
	 */
	private static function getLogger()
	{
		return Factory::get()->getThe('forsef.logger');
	}

	/**
	 * Prepare a loggable message from a legacy logging call.
	 *
	 * @param array $args
	 * @return string
	 */
	private function prepareArguments($args)
	{
		// 1st arg is sh404SEF string, not needed.
		array_shift($args);

		if (count($args) > 1)
		{
			$message = call_user_func_array('sprintf', $args);
		}
		else
		{
			$message = $args[0];  // no variable parts, just use first element as a string
		}

		return $message;
	}
}
