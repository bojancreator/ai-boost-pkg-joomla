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

class ShlDbHelper
{
	/**
	 * @var Db\Helper Convienence instance of the main db helper;
	 */
	private static $helper;

	/**
	 * @return Returns the platform DB object.
	 */
	public static function getDb()
	{
		return self::getDbHelper()->db();
	}

	public static function selectResult($table, $aColList = ['*'], $mWhere = '', $aWhereData = [], $orderBy = [], $offset = 0,
										$lines = 0, $opType = '')
	{
		return self::getDbHelper()->selectResult(
			$table,
			$aColList,
			$mWhere,
			$aWhereData,
			$orderBy,
			$offset,
			$lines
		);
	}

	public static function selectObject($table, $aColList = ['*'], $mWhere = '', $aWhereData = [], $orderBy = [], $offset = 0,
										$lines = 0, $opType = '')
	{
		return self::getDbHelper()->selectObject(
			$table,
			$aColList,
			$mWhere,
			$aWhereData,
			$orderBy,
			$offset,
			$lines
		);
	}

	public static function selectObjectList($table, $aColList = ['*'], $mWhere = '', $aWhereData = [], $orderBy = [], $offset = 0,
											$lines = 0, $key = '', $opType = '')
	{
		return self::getDbHelper()->selectObjectList(
			$table,
			$aColList,
			$mWhere,
			$aWhereData,
			$orderBy,
			$offset,
			$lines,
			$key
		);
	}

	/**
	 * @return Db\Helper
	 */
	private static function getDbHelper()
	{
		if (is_null(self::$helper))
		{
			self::$helper = Factory::get()->getThe('db');
		}
		return self::$helper;
	}
}
