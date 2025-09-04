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

namespace Weeblr\Wblib\Forsef\Platform;

//
/* Security check to ensure this file is being included by a parent file.*/
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 *
 * Holds data for a database instance description
 *
 * @author weeblr
 *
 */
interface Platformdbconnectioninterface
{
	public function getQuery();

	public function getPrefix();

	public function quote($data, $escape = true);

	public function quoteName($data);

	public function quoteTable($data);

	public function escape($data, $extra = false);

	public function getNullDate();

	public function getTableList();

	public function setQuery($query, $offset = 0, $limit = 0);

	public function loadAssoc();

	public function loadAssocList($key = null, $column = null);

	public function loadColumn($offset = 0);

	public function loadObject();

	public function loadObjectList($key = '');

	public function loadResult();

	public function loadRow();

	public function loadRowList($key = null);

	public function getInsertId();

	public function transactionStart();

	public function transactionCommit();

	public function transactionRollback();

	public function execute();

	public function getTableColumns($table, $typeOnly = true);

	public function dropTable($table, $ifExists = true);
}
