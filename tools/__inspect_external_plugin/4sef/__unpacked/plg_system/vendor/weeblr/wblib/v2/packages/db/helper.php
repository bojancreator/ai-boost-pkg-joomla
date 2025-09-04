<?php
/**
 * Project:                 4SEF
 *
 * @package                 4SEF
 * @copyright               Copyright Weeblr llc - 2022 -2025
 * @author                  Yannick Gaultier - Weeblr llc
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 2.6.2.644
 *
 * 2025-06-02
 */

namespace Weeblr\Wblib\Forsef\Db;

use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\Joomla\StringHelper\StringHelper;
use Weeblr\Wblib\Forsef\Platform;

defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

class Helper extends Base\Base
{
	const STRING = 1;
	const INTEGER = 2;

	/**
	 * Max index: 191. MD5 = 32, Separator =1, Safety = 1, Cutoff = 191-32-1-1 = 157.
	 *
	 * @var int Character count at which we hash the remainder of URL.
	 */
	protected $cutoffLength = 157;

	/**
	 * @var Db|null Instance of the underlying database object
	 */
	protected $db = null;

	/**
	 * Manager constructor.
	 *
	 * Builds and store the database object
	 *
	 * @param   array  $options
	 */
	public function __construct($options = [])
	{
		parent::__construct($options);

		$db       = empty($options['db'])
			? $this->factory->getThe(Platform\Joomla\Dbconnection::class)
			: $options['db'];

		$this->db = new Db(
			$db
		);
	}

	/**
	 * @return int Getter for storage safe cutoff length
	 */
	public function getCutoffLength()
	{
		return $this->cutoffLength;
	}

	/**
	 * Getter for underlying database object.
	 *
	 * @return Db|null
	 */
	public function db()
	{
		return $this->db;
	}

	/**
	 * Retrieves field information about a given table.
	 *
	 * @param   string   $table     The name of the database table.
	 * @param   boolean  $typeOnly  True to only return field types.
	 *
	 * @return  array  An array of fields for the database table.
	 *
	 * @since   1.0
	 * @throws  \RuntimeException
	 */
	public function getTableColumns($table, $typeOnly = true)
	{
		$result = [];

		// Set the query to get the table fields statement.
		$fields = $this->setQueryAnd('SHOW FULL COLUMNS FROM ' . $this->quoteName($table))->loadObjectList();

		// If we only want the type as the value add just that to the list.
		if ($typeOnly)
		{
			foreach ($fields as $field)
			{
				$result[$field->Field] = preg_replace('/[(0-9)]/', '', $field->Type);
			}
		}
		// If we want the whole field data object add that to the list.
		else
		{
			foreach ($fields as $field)
			{
				$result[$field->Field] = $field;
			}
		}

		return $result;
	}

	/**
	 * Escape a value, using database connection
	 *
	 * @param   string | array  $text
	 * @param   bool            $extra
	 *
	 * @return array|string
	 */
	public function escape($text, $extra = false)
	{
		return $this->db->escape($text, $extra);
	}

	/**
	 * Get the null date value for the database driver.
	 */
	public function getNullDate()
	{
		return $this->db->getNullDate();
	}

	public function getTableList()
	{
		return $this->db->getTableList();
	}

	public function getPrefix()
	{
		return $this->db->getPrefix();
	}

	public function dropTable($table, $ifExists = true)
	{
		return $this->db->dropTable($table, $ifExists);
	}

	/**
	 * Prepare, set and execute a select query, returning a single result
	 *
	 * usage:
	 *
	 * $result = Helper::selectResult( '#__sh404sef_alias', 'alias', array( 'nonsef' =>
	 * 'index.php?option=com_content&view=article&id=12')); will select the 'alias' column where nonsef column is
	 * index.php?option=com_content&view=article&id=12 Alternate where condition syntax:
	 * $result = Helper::selectResult( '#__sh404sef_alias', 'alias', 'amount > 0 and amount < ?', array( '100'));
	 * If where condition is a string, it will be used literally, with question marks replaced by parameters as
	 * passed in the next method param. These params are escaped, but the base where condition is not
	 *
	 * @param   String    $table       The table name
	 * @param   string[]  $aColList    array of strings of columns to be fetched
	 * @param   string    $mWhere      ( ie 'columnName' => 'columnValue') : a where clause is created like so : WHERE
	 *                                 `columnName` = 'columnValue'. columnValue is escaped before being used
	 * @param   array     $aWhereData  Used only if $aWhere is a string. In such case, '?' place holders will be replaced by
	 *                                 this array values, escaped
	 * @param   array     $orderBy     , a list of columns to order the results
	 * @param   Integer   $offset      , first line of result set to select
	 * @param   Integer   $lines       , max number of lines to select
	 *
	 * @return mixed single value read from db
	 * @throw none (underlying database layer does throw errors)
	 */
	public function selectResult($table, $aColList = array('*'), $mWhere = '', $aWhereData = array(), $orderBy = array(), $offset = 0,
	                             $lines = 0)
	{
		return $this->setSelectQuery($table, $aColList, $mWhere, $aWhereData, $orderBy, $offset, $lines)
			->loadResult();
	}

	/**
	 * Prepare, set and execute a select query, returning a an array of results
	 *
	 * usage:
	 *
	 * $result = Helper::selectResult( '#__sh404sef_alias', 'alias', array( 'nonsef' =>
	 * 'index.php?option=com_content&view=article&id=12')); will select the 'alias' column where nonsef column is
	 * index.php?option=com_content&view=article&id=12 Alternate where condition syntax:
	 * $result = Helper::selectResult( '#__sh404sef_alias', 'alias', 'amount > 0 and amount < ?', array( '100'));
	 * If where condition is a string, it will be used literally, with question marks replaced by parameters as
	 * passed in the next method param. These params are escaped, but the base where condition is not
	 *
	 * @param   String    $table       The table name
	 * @param   string[]  $aColList    array of strings of columns to be fetched
	 * @param   string    $mWhere      ( ie 'columnName' => 'columnValue') : a where clause is created like so : WHERE
	 *                                 `columnName` = 'columnValue'. columnValue is escaped before being used
	 * @param   array     $aWhereData  Used only if $aWhere is a string. In such case, '?' place holders will be replaced by
	 *                                 this array values, escaped
	 * @param   array     $orderBy     , a list of columns to order the results
	 * @param   Integer   $offset      , first line of result set to select
	 * @param   Integer   $lines       , max number of lines to select
	 *
	 * @return mixed single value read from db
	 * @throw none (underlying database layer does throw errors)
	 */
	public function selectColumn($table, $aColList = array('*'), $mWhere = '', $aWhereData = array(), $orderBy = array(), $offset = 0,
	                             $lines = 0)
	{
		return $this->setSelectQuery($table, $aColList, $mWhere, $aWhereData, $orderBy, $offset, $lines)
			->loadColumn();
	}

	/**
	 * Prepare, set and execute a select query, returning a single associative array
	 *
	 * usage:
	 *
	 * $result = Helper::selectAssoc( '#__sh404sef_alias', array('alias', 'id'), array( 'nonsef' =>
	 * 'index.php?option=com_content&view=article&id=12')); will return an array with 2 keys, alias and id, where
	 * nonsef column is index.php?option=com_content&view=article&id=12
	 *
	 * $result = Helper::selectAssoc( '#__sh404sef_alias', array('alias', 'id'), 'amount > 0 and amount < ?',
	 * array( '100')); If where condition is a string, it will be used literally, with question marks replaced by
	 * parameters as passed in the next method param. These params are escaped, but the base where condition is not
	 *
	 * @param   String    $table       The table name
	 * @param   string[]  $aColList    array of strings of columns to be fetched
	 * @param   string    $mWhere      ( ie 'columnName' => 'columnValue') : a where clause is created like so : WHERE
	 *                                 `columnName` = 'columnValue'. columnValue is escaped before being used
	 * @param   array     $aWhereData  Used only if $aWhere is a string. In such case, '?' place holders will be replaced by
	 *                                 this array values, escaped
	 * @param   array     $orderBy     , a list of columns to order the results
	 * @param   Integer   $offset      , first line of result set to select
	 * @param   Integer   $lines       , max number of lines to select
	 *
	 * @return mixed single value read from db
	 * @throw none (underlying database layer does throw errors)
	 */
	public function selectAssoc($table, $aColList = array('*'), $mWhere = '', $aWhereData = array(), $orderBy = array(), $offset = 0,
	                            $lines = 0)
	{
		return $this->setSelectQuery($table, $aColList, $mWhere, $aWhereData, $orderBy, $offset, $lines)
			->loadAssoc();
	}

	/**
	 * Prepare, set and execute a select query, returning a an array of associative arrays
	 *
	 * usage:
	 *
	 * $result = Helper::selectAssoc( '#__sh404sef_alias', array('alias', 'id'), array( 'nonsef' =>
	 * 'index.php?option=com_content&view=article&id=12')); will return an array of arrays with 2 keys, alias and id,
	 * where nonsef column is index.php?option=com_content&view=article&id=12
	 *
	 * $result = Helper::selectAssoc( '#__sh404sef_alias', array('alias', 'id'), 'amount > 0 and amount < ?',
	 * array( '100')); If where condition is a string, it will be used literally, with question marks replaced by
	 * parameters as passed in the next method param. These params are escaped, but the base where condition is not
	 *
	 * @param   String    $table       The table name
	 * @param   string[]  $aColList    array of strings of columns to be fetched
	 * @param   string    $mWhere      ( ie 'columnName' => 'columnValue') : a where clause is created like so : WHERE
	 *                                 `columnName` = 'columnValue'. columnValue is escaped before being used
	 * @param   array     $aWhereData  Used only if $aWhere is a string. In such case, '?' place holders will be replaced by
	 *                                 this array values, escaped
	 * @param   array     $orderBy     , a list of columns to order the results
	 * @param   Integer   $offset      , first line of result set to select
	 * @param   Integer   $lines       , max number of lines to select
	 * @param   string    $key         a column name to index the returned array with
	 *
	 * @return mixed single value read from db
	 * @throw none (underlying database layer does throw errors)
	 */
	public function selectAssocList($table, $aColList = array('*'), $mWhere = '', $aWhereData = array(), $orderBy = array(), $offset = 0,
	                                $lines = 0, $key = '')
	{
		return $this->setSelectQuery($table, $aColList, $mWhere, $aWhereData, $orderBy, $offset, $lines)
			->loadAssocList($key);
	}

	/**
	 * Prepare, set and execute a select query, returning a single object
	 *
	 * @param   String    $table       The table name
	 * @param   string[]  $aColList    array of strings of columns to be fetched
	 * @param   string    $mWhere      ( ie 'columnName' => 'columnValue') : a where clause is created like so : WHERE
	 *                                 `columnName` = 'columnValue'. columnValue is escaped before being used
	 * @param   array     $aWhereData  Used only if $aWhere is a string. In such case, '?' place holders will be replaced by
	 *                                 this array values, escaped
	 * @param   array     $orderBy     , a list of columns to order the results
	 * @param   Integer   $offset      , first line of result set to select
	 * @param   Integer   $lines       , max number of lines to select
	 *
	 * @return mixed single value read from db
	 * @throw none (underlying database layer does throw errors)
	 */
	public function selectObject($table, $aColList = array('*'), $mWhere = '', $aWhereData = array(), $orderBy = array(), $offset = 0,
	                             $lines = 0)
	{
		return $this->setSelectQuery($table, $aColList, $mWhere, $aWhereData, $orderBy, $offset, $lines)
			->loadObject();
	}

	/**
	 * Prepare, set and execute a select query, returning a an object list
	 *
	 * @param   String    $table       The table name
	 * @param   string[]  $aColList    array of strings of columns to be fetched
	 * @param   string    $mWhere      ( ie 'columnName' => 'columnValue') : a where clause is created like so : WHERE
	 *                                 `columnName` = 'columnValue'. columnValue is escaped before being used
	 * @param   array     $aWhereData  Used only if $aWhere is a string. In such case, '?' place holders will be replaced by
	 *                                 this array values, escaped
	 * @param   array     $orderBy     , a list of columns to order the results
	 * @param   Integer   $offset      , first line of result set to select
	 * @param   Integer   $lines       , max number of lines to select
	 * @param   string    $key         a column name to index the returned array with
	 *
	 * @return array
	 * @throw none (underlying database layer does throw errors)
	 */
	public function selectObjectList($table, $aColList = array('*'), $mWhere = '', $aWhereData = array(), $orderBy = array(), $offset = 0,
	                                 $lines = 0, $key = '')
	{
		// have db driver create the sql query
		return $this->setSelectQuery($table, $aColList, $mWhere, $aWhereData, $orderBy, $offset, $lines)
			->loadObjectList($key);
	}

	/**
	 * Prepare, set and execute a count query
	 *
	 * @param   String  $table       The table name
	 * @param   String  $column      optional column to be counted (defaults to *)
	 * @param   string  $mWhere      ( ie 'columnName' => 'columnValue') : a where clause is created like so : WHERE
	 *                               `columnName` = 'columnValue'. columnValue is escaped before being used
	 * @param   array   $aWhereData  Used only if $aWhere is a string. In such case, '?' place holders will be replaced by
	 *                               this array values, escaped
	 * @param   bool    $unique
	 *
	 * @return int
	 */
	public function count($table, $column = '*', $mWhere = '', $aWhereData = array(), $unique = false)
	{
		// have db driver create the sql query
		$read = $this->db->setCountQuery($table, $column, $mWhere, $aWhereData, $unique)
			->loadResult();

		return empty($read) ? 0 : $read;
	}

	/**
	 * Check if at least one record exists that matches the given condition.
	 *
	 * @param   string        $table       The table name.
	 * @param   string|array  $mWhere      Condition(s) for the query. Can be a string or an associative array.
	 * @param   array         $aWhereData  Data to replace placeholders in the $mWhere string.
	 *
	 * @return bool True if at least one record exists, false otherwise.
	 * @throws \Exception
	 */
	public function exists($table, $mWhere = '', $aWhereData = array())
	{
		// Build the WHERE clause if conditions are provided
		$whereClause = '';
		if (!empty($mWhere))
		{
			$whereClause = $this->buildWhereClause($mWhere, $aWhereData);
		}

		// Construct the EXISTS query
		$query = "SELECT EXISTS(SELECT 1 FROM " . $this->quoteName($table) . $whereClause . ")";

		// Execute the query
		$result = $this->setQueryAnd($query)->loadResult();

		// Return true if the result is 1, indicating that at least one record exists
		return !empty($result) && $result === 1;
	}

	/**
	 * Prepare, set and execute a delete query
	 *
	 * @param   String  $table       The table name
	 * @param   string  $mWhere      ( ie 'columnName' => 'columnValue') : a where clause is created like so : WHERE
	 *                               `columnName` = 'columnValue'. columnValue is escaped before being used
	 * @param   array   $aWhereData  Used only if $aWhere is a string. In such case, '?' place holders will be replaced by
	 *                               this array values, escaped
	 *
	 * @return $this
	 */
	public function delete($table, $mWhere = '', $aWhereData = array())
	{
		$this->db->setDeleteQuery($table, $mWhere, $aWhereData)->execute();

		return $this;
	}

	/**
	 * Prepare, set and execute a delete query based on a
	 * list of column value
	 *
	 * @param   String  $table         The table name
	 * @param   String  $mwhereColumn  name of column to compare to list of values
	 * @param   Array   $aWhereData    List of column values that should be deleted
	 * @param   Integer if self::INTEGER, list will be 'intvaled', else quoted
	 *
	 * @return $this
	 */
	public function deleteIn($table, $mwhereColumn, $aWhereData, $type = self::STRING)
	{
		if (empty($mwhereColumn) || empty($aWhereData))
		{
			return $this;
		}

		// build a list of ids to read
		$wheres = $type == self::INTEGER ? $this->arrayToIntvalList($aWhereData) : $this->arrayToQuotedList($aWhereData);

		// perform deletion
		return $this->delete($table, $this->db->quoteName($mwhereColumn) . ' in (' . $wheres . ')');
	}

	/**
	 * Prepare, set and execute and insert query
	 *
	 * @param   String  $table  The table name
	 * @param   Array   $aData  array of values pairs ( ie 'columnName' => 'columnValue')
	 *
	 * @return $this
	 */
	public function insert($table, $aData)
	{
		$this->db->setInsertQuery($table, $aData)
			->execute();

		return $this;
	}

	/**
	 * Prepare, set and execute an update query
	 *
	 * @param   String  $table       The table name
	 * @param   Array   $aData       array of values pairs ( ie 'columnName' => 'columnValue')
	 * @param   string  $mWhere      ( ie 'columnName' => 'columnValue') : a where clause is created like so : WHERE
	 *                               `columnName` = 'columnValue'. columnValue is escaped before being used
	 * @param   array   $aWhereData  Used only if $aWhere is a string. In such case, '?' place holders will be replaced by
	 *                               this array values, escaped
	 *
	 * @return $this
	 */
	public function update($table, $aData, $mWhere = '', $aWhereData = array())
	{
		$this->db->setUpdateQuery($table, $aData, $mWhere, $aWhereData)
			->execute();

		return $this;
	}

	/**
	 * Prepare, set and execute an update query on a list
	 * of items
	 *
	 * @param   String  $table         The table name
	 * @param   Array   $aData         array of values pairs ( ie 'columnName' => 'columnValue')
	 * @param   String  $mwhereColumn  name of column to compare to list of values
	 * @param   Array   $aWhereData    List of column values that should be updated
	 * @param   Integer if self::INTEGER, list will be 'intvaled', else quoted
	 *
	 * @return object the db object
	 */
	public function updateIn($table, $aData, $mwhereColumn, $aWhereData, $type = self::STRING)
	{
		if (empty($mwhereColumn) || empty($aWhereData))
		{
			return $this;
		}

		// build a list of ids to read
		$wheres = $type == self::INTEGER ? $this->arrayToIntvalList($aWhereData) : $this->arrayToQuotedList($aWhereData);

		// perform deletion
		return $this->update($table, $aData, $this->db->quoteName($mwhereColumn) . ' in (' . $wheres . ')');
	}

	/**
	 * Prepare, set and execute an insert or update query
	 *
	 * @param   String  $table       The table name
	 * @param   Array   $aData       An array of field to be inserted in the db ('columnName' => 'columnValue')
	 * @param   string  $mWhere      ( ie 'columnName' => 'columnValue') : a where clause is created like so : WHERE
	 *                               `columnName` = 'columnValue'. columnValue is escaped before being used
	 * @param   array   $aWhereData  Used only if $aWhere is a string. In such case, '?' place holders will be replaced by
	 *                               this array values, escaped
	 *
	 * @return $this
	 */
	public function insertUpdate($table, $aData, $mWhere = '', $aWhereData = array())
	{
		$this->db->setInsertUpdateQuery($table, $aData, $mWhere, $aWhereData)->execute();

		return $this;
	}

	/**
	 * Prepare, set and execute a custom database query
	 *
	 * @param   String  $query   A litteral sql query
	 * @param   string  $opType  optional forced operation type for this operation
	 *
	 * @return $this
	 */
	public function query($query, $opType = '')
	{
		$this->setQuery($query, $opType);
		$this->db->execute();

		return $this;
	}

	/**
	 * Truncate db tables. Use with care.
	 *
	 * @param   string | array  $tables
	 *
	 * @return $this
	 */
	public function truncate($tables)
	{
		$tables = is_array($tables)
			? $tables
			: [$tables];
		foreach ($tables as $table)
		{
			$this->setQuery('truncate ' . $this->quoteName($table));
			$this->db->execute();
		}

		return $this;
	}

	/**
	 * Set a custom database query, so that
	 * another method can be chained to execute it
	 *
	 * @param   String  $query  A litteral sql query
	 *
	 * @return $this
	 */
	public function setQuery($query)
	{
		$this->db->setQuery($query);

		return $this;
	}

	/**
	 * Set a custom database query, and return the db object
	 * so that another method can be chained to execute it.
	 *
	 * @param   String  $query  A litteral sql query
	 *
	 * @return Db\Dbconnection
	 */
	public function setQueryAnd($query)
	{
		$this->db->setQuery($query);

		return $this->db;
	}

	/**
	 * Trigger previously set queries execution in the database.
	 *
	 * @return mixed
	 */
	public function execute()
	{
		return $this->db->execute();
	}

	/**
	 *
	 * Prepare a query for running, quoting or name quoting some
	 * of its constituents
	 * ?? will be replaced with name quoted data from the $nameQuoted parameter
	 * ? will be replaced with quoted data from the $quoted parameter
	 *
	 * Example:
	 *   $query = 'select ?? from ?? where ?? <> ?'
	 *   with
	 *     $nameQuoted = array( 'id', '#__table', 'counter')
	 *     $quoted = array( 'test')
	 *
	 * will result in running
	 *
	 *   select `id` from `#__table` where `counter` <> 'test'
	 *
	 *
	 * @param   string  $query
	 * @param   array   $nameQuoted
	 * @param   array   $quoted
	 * @param   string  $namePlaceHolder
	 * @param   string  $dataPlaceHolder
	 *
	 * @return $this
	 */
	public function quoteQuery($query, $nameQuoted = array(), $quoted = array(), $namePlaceHolder = '??', $dataPlaceHolder = '?')
	{
		// save query for error message
		$newQuery = $this->db->quoteQuery($query, $nameQuoted, $quoted, $namePlaceHolder, $dataPlaceHolder);
		$this->db->setQuery($newQuery);

		return $this;
	}

	/**
	 *
	 * Runs a query, after quoting or name quoting some
	 * of its constituents
	 * ?? will be replaced with name quoted data from the $nameQuoted parameter
	 * ? will be replaced with quoted data from the $quoted parameter
	 *
	 * Example:
	 *   $query = 'select ?? from ?? where ?? <> ?'
	 *   with
	 *     $nameQuoted = array( 'id', '#__table', 'counter')
	 *     $quoted = array( 'test')
	 *
	 * will result in running
	 *
	 *   select `id` from `#__table` where `counter` <> 'test'
	 *
	 *
	 * @param   string  $query
	 * @param   array   $nameQuoted
	 * @param   array   $quoted
	 * @param   string  $namePlaceHolder
	 * @param   string  $dataPlaceHolder
	 *
	 * @return $this
	 */
	public function runQuotedQuery($query, $nameQuoted = array(), $quoted = array(), $namePlaceHolder = '??', $dataPlaceHolder = '?')
	{
		// save query for error message
		$newQuery = $this->db->quoteQuery($query, $nameQuoted, $quoted, $namePlaceHolder, $dataPlaceHolder);

		return $this->query($newQuery);
	}

	/**
	 * Returns the last insert id.
	 *
	 * @return int
	 */
	public function getInsertId()
	{
		return $this->db->insertId();
	}

	/**
	 *
	 * Asks DB to quote a string
	 *
	 * @param   string  $string
	 *
	 * @return string
	 */
	public function quote($string)
	{
		return $this->db->quote($string);
	}

	public function q($string)
	{
		return $this->quote($string);
	}

	/**
	 *
	 * Asks db to name quote a string
	 *
	 * @param   string  $string
	 *
	 * @return string
	 */
	public function quoteName($string)
	{
		return $this->db->quoteName($string);
	}

	public function qn($string)
	{
		return $this->quoteName($string);
	}


	/**
	 *
	 * Asks db to name quote table name a string
	 *
	 * @param $tableName
	 *
	 * @return string
	 */
	public function quoteTable($tableName)
	{
		return $this->db->quoteTable($tableName);
	}

	/**
	 * Build a where clause
	 *
	 * @param   string  $mWhere      ( ie 'columnName' => 'columnValue') : a where clause is created like so : WHERE
	 *                               `columnName` = 'columnValue'. columnValue is escaped before being used
	 * @param   array   $aWhereData  Used only if $aWhere is a string. In such case, '?' place holders will be replaced by
	 *                               this array values, escaped
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function buildWhereClause($mWhere = '', $aWhereData = array())
	{
		return $this->db->buildWhereClause($mWhere, $aWhereData);
	}

	/**
	 * Builds an ORDER BY sql statement
	 *
	 * $orderBy = 'title';
	 * $orderBy = array( 'extension' => '', 'title' => 'desc');
	 * $orderBy = array( 'extension', 'title');
	 *
	 * @param   Array  $orderBy  a list of key => values, where key is a column name, and value is either '', 'asc' or
	 *                           'desc'
	 *
	 * @return string
	 */
	public function buildOrderByClause($orderBy)
	{
		return $this->db->buildOrderByClause($orderBy);
	}

	/**
	 * Builds a LIMIT sql statement
	 *
	 * @param   Integer  $offset  , the line in result set to start with
	 * @param   Integer  $lines   , the max number of lines in result set to return
	 *
	 * @return string
	 */
	public function buildLimitClause($offset, $lines)
	{
		return $this->db->buildLimitClause($offset, $lines);
	}

	/**
	 * Quote an array of value and turn it into a list
	 * of separated, name quoted elements
	 *
	 * @param   array   $data
	 * @param   string  $glue
	 *
	 * @return string
	 */
	public function arrayToNameQuotedList($data, $glue = ',')
	{
		return $this->_arrayToQuotedList($data, $nameQuote = true, $glue);
	}

	/**
	 * Quote an array of value and turn it into a list
	 * of separated, quoted elements
	 *
	 * @param   array   $data
	 * @param   string  $glue
	 *
	 * @return string
	 */
	public function arrayToQuotedList($data, $glue = ',')
	{
		return $this->_arrayToQuotedList($data, $nameQuote = false, $glue);
	}

	/**
	 * Quote an array of value and turn it into a list
	 * of separated, quoted elements
	 *
	 * @param   array    $data
	 * @param   boolean  $nameQuote  if true, data is namedQuoted, otherwise Quoted
	 * @param   string   $glue
	 *
	 * @return string
	 */
	private function _arrayToQuotedList($data, $nameQuote = false, $glue = ',')
	{
		$list = '';
		if (empty($data) || !is_array($data))
		{
			return $list;
		}

		$values = array();
		foreach ($data as $value)
		{
			$values[] = $nameQuote ? $this->db->quoteName($value) : $this->db->quote($value);
		}

		$list = implode($glue, $values);

		return $list;
	}

	/**
	 * Intval an array of value and turn it into a list
	 * of separated, quoted elements
	 *
	 * @param   array   $data
	 * @param   string  $glue
	 *
	 * @return string
	 */
	public function arrayToIntvalList($data, $glue = ',')
	{
		$list = '';
		if (empty($data) || !is_array($data))
		{
			return $list;
		}

		$values = array();
		foreach ($data as $value)
		{
			$values[] = (int) $value;
		}

		$list = implode($glue, $values);

		return $list;
	}

	protected function setSelectQuery($table, $aColList = array('*'), $mWhere = '', $aWhereData = array(), $orderBy = array(), $offset = 0,
	                                  $lines = 0)
	{
		return $this->db->setSelectQuery($table, $aColList, $mWhere, $aWhereData, $orderBy, $offset, $lines);
	}

	/**
	 * Computes a possibly shortened version of a long string to be stored
	 * in a database field and indexed.
	 *
	 * @param   string  $value  Original, full length content
	 *
	 * @return string
	 */
	public function storageSafe($value, $cutoffLength = null)
	{
		if (empty($value))
		{
			return $value;
		}

		$cutoffLength = empty($cutoffLength)
			? $this->cutoffLength
			: $cutoffLength;

		$valueLength = StringHelper::strlen($value);
		if ($valueLength <= $cutoffLength)
		{
			// short enough, nothing to do
			return $value;
		}

		// split at cutoff point
		$main      = StringHelper::substr($value, 0, $cutoffLength);
		$remainder = StringHelper::substr($value, $cutoffLength);

		return $main . '_' . strtolower(md5($remainder));
	}
}
