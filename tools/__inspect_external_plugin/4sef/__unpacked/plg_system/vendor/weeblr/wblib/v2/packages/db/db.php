<?php
/**
 * Project:                 4SEF
 *
 * @package          4SEF
 * @copyright        Copyright Weeblr llc - 2022 -2025
 * @author           Yannick Gaultier - Weeblr llc
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          @build_version_full_build@
 * @date                2025-06-02
 */

namespace Weeblr\Wblib\Forsef\Db;

use Weeblr\Wblib\Forsef\Wb;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

class Db
{
	private $sql = '';
	private $db = null;

	/**
	 * Get a hold on the platform database object.
	 *
	 * @param   Platformdbconnectioninterface  $db
	 */
	public function __construct($db)
	{
		$this->db = $db;
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
	 * Quote the name of a table, replacing the prefix placeholder
	 * by its actual value
	 *
	 * @param   string  $tableName
	 * @param   string  $prefix
	 *
	 * @return string
	 */
	public function quoteTable($tableName, $prefix = '#__')
	{
		if(empty($tableName)) {
			throw new \Exception('Invalid table name');
		}
		return $this->db->quoteName(
			str_replace(
				$prefix,
				$this->db->getPrefix(),
				$tableName
			)
		);
	}

	/**
	 * Quote a column name (mysql)
	 *
	 * @param $name
	 *
	 * @return string
	 */
	public function quotename($name)
	{
		return $this->db->quoteName($name);
	}

	/**
	 * Quote and by default also escape
	 * a value (mysql)
	 *
	 * @param   string|array  $value
	 * @param   bool          $escape
	 *
	 * @return string
	 */
	public function quote($value, $escape = true)
	{
		return $this->db->quote($value, $escape);
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
		$result = $this->db->escape($text, $extra);

		if ($extra)
		{
			$result = addcslashes($result, '%_');
		}

		return $result;
	}

	/**
	 * Stores a (valid and complete) sql query, to
	 * be executed later
	 *
	 * @param $query
	 */
	public function setQuery($query)
	{
		$this->sql = $query;
		$this->db->setQuery($query);
	}

	/**
	 * Execute a query stored with setQuery()
	 */
	public function execute()
	{
		if (empty($this->sql))
		{
			Wb\throwException(new \InvalidArgumentException('wbLib: trying to execute an empty query'));
		}

		return $this->db->execute($this->sql);
	}

	/**
	 *
	 * Prepare a query, quoting or name quoting some
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
	 * will return
	 *
	 *   select `id` from `#__table` where `counter` <> 'test'
	 *
	 *
	 * @param   string  $query
	 * @param   array   $nameQuoted
	 * @param   array   $quoted
	 * @param   string  $namePlaceHolder
	 * @param   string  $dataPlaceHolder
	 */
	public function quoteQuery($query, $nameQuoted = array(), $quoted = array(), $namePlaceHolder = '??', $dataPlaceHolder = '?')
	{
		$newQuery = '';

		// name quoting
		if (!empty($nameQuoted))
		{
			// find placeholders
			$sqlBits = explode($namePlaceHolder, $query);
			$i       = 0;
			// replace each place holder by the matching value
			foreach ($nameQuoted as $data)
			{
				$newQuery .= $sqlBits[$i];
				$newQuery .= $this->quoteName($data);
				$i        += 1;
			}
			if (isset($sqlBits[$i]))
			{
				$newQuery .= $sqlBits[$i];
			}
		}

		if (strpos($newQuery, $namePlaceHolder) !== false)
		{
			Wb\throwException(new \RuntimeException(__METHOD__ . ':' . __LINE__ . ': ' . 'Invalid db query sent to queryQuote helper: ' . $query . '. Maybe missing some data.'));
		}

		// name quoting
		if (!empty($quoted))
		{
			// find placeholders
			$sqlBits  = explode($dataPlaceHolder, $newQuery);
			$newQuery = '';
			$i        = 0;
			// replace each place holder by the matching value
			foreach ($quoted as $data)
			{
				$newQuery .= $sqlBits[$i];
				$newQuery .= $this->_prepareData($data);
				$i        += 1;
			}
			if (isset($sqlBits[$i]))
			{
				$newQuery .= $sqlBits[$i];
			}
		}

		return $newQuery;
	}

	/**
	 * Prepare and set a query against the db object
	 *
	 * @param   String  $table       The table name
	 * @param   Array   $aData       An array of field to be inserted in the db ('columnName' => 'columnValue')
	 * @param   string  $mWhere      ( ie 'columnName' => 'columnValue') : a where clause is created like so : WHERE
	 *                               `columnName` = 'columnValue'. columnValue is escaped before being used
	 * @param   array   $aWhereData  Used only if $aWhere is a string. In such case, '?' place holders will be replaced by
	 *                               this array values, escaped
	 *
	 * @return Db
	 */
	public function setInsertUpdateQuery($table, $aData, $mWhere = '', $aWhereData = array())
	{
		if ($this->isRecord($table, $mWhere, $aWhereData))
		{
			// update it
			$this->setUpdateQuery($table, $aData, $mWhere, $aWhereData);
		}
		else
		{
			// or insert it
			$this->setInsertQuery($table, $aData);
		}

		return $this;
	}

	/**
	 * Prepare and set a SELECT query against the db
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
	 * @return Db
	 */
	public function setSelectQuery($table, $aColList = array('*'), $mWhere = '', $aWhereData = array(), $orderBy = array(), $offset = 0,
	                               $lines = 0)
	{
		// sanitize
		$aColList = empty($aColList) ? array('*') : $aColList;
		$aColList = is_string($aColList) ? array($aColList) : $aColList;

		// which columns to fetch ?
		$quotedColList = array();
		foreach ($aColList as $columnName)
		{
			$quotedColList[] = $columnName == '*' ? '*' : $this->quoteName($columnName);
		}
		$columns = implode(', ', $quotedColList);

		// where to look for
		$where = $this->buildWhereClause($mWhere, $aWhereData);

		// order by clause
		$orderByClause = $this->buildOrderByClause($orderBy);

		// lines limit clause
		$limitClause = $this->buildLimitClause($offset, $lines);

		// set up the query
		$this->setQuery('SELECT ' . $columns . ' FROM ' . $this->quoteTable($table) . $where . $orderByClause . $limitClause . ';');

		return $this;
	} // end of setSelectQuery

	/**
	 * Prepare and set a select/count query against the db
	 *
	 * @param   String  $table       The table name
	 * @param   String  $column      an optional column to be counter
	 * @param   string  $mWhere      ( ie 'columnName' => 'columnValue') : a where clause is created like so : WHERE
	 *                               `columnName` = 'columnValue'. columnValue is escaped before being used
	 * @param   array   $aWhereData  Used only if $aWhere is a string. In such case, '?' place holders will be replaced by
	 *                               this array values, escaped
	 * @param   bool    $unique
	 *
	 * @return Db
	 * @throws \Exception
	 */
	public function setCountQuery($table, $column = '*', $mWhere = '', $aWhereData = array(), $unique = false)
	{
		// sanitize
		$column = empty($column) || $column == '*' ? '*' : $this->quoteName($column);

		// where to look for
		$where = $this->buildWhereClause($mWhere, $aWhereData);

		// set up the query
		$this->setQuery('SELECT count(' . ($unique ? ' DISTINCT ' : '') . $column . ') FROM ' . $this->quoteTable($table) . $where . ';');

		return $this;
	} // end of setSelectQuery

	/**
	 * Prepare and set an UPDATE query against the db
	 *
	 * @param   String  $table       The table name
	 * @param   Array   $aData       array of values pairs ( ie 'columnName' => 'columnValue')
	 * @param   string  $mWhere      ( ie 'columnName' => 'columnValue') : a where clause is created like so : WHERE
	 *                               `columnName` = 'columnValue'. columnValue is escaped before being used
	 * @param   array   $aWhereData  Used only if $aWhere is a string. In such case, '?' place holders will be replaced by
	 *                               this array values, escaped
	 *
	 * @return bool|Db
	 */
	public function setUpdateQuery($table, $aData, $mWhere = '', $aWhereData = array())
	{
		// which columns to set ?
		$set = '';
		if (!empty($aData))
		{
			foreach ($aData as $columnName => $columnValue)
			{
				$set .= ', ' . $this->quoteName($columnName) . '=' . $this->_prepareData($columnValue);
			}
			// remove leading ', '
			$set = substr($set, 2);
		}

		// check result
		if (empty($set))
		{
			return false;
		}

		// where to look for
		$where = $this->buildWhereClause($mWhere, $aWhereData);

		// set up the query
		$this->setQuery('UPDATE ' . $this->quoteTable($table) . ' SET ' . $set . $where . ';');

		return $this;
	}

	/**
	 * Prepare and set an INSERT query against the db
	 *
	 * @param   String  $table  The table name
	 * @param   Array   $aData  array of values pairs ( ie 'columnName' => 'columnValue')
	 */
	public function setInsertQuery($table, $aData)
	{
		// which columns to set ?
		$columns = '';
		$values  = '';
		if (!empty($aData))
		{
			foreach ($aData as $columnName => $columnValue)
			{
				$columns .= ', ' . $this->quoteName($columnName);
				$values  .= ', ' . $this->_prepareData($columnValue);
			}
			// remove leading ', '
			$columns = substr($columns, 2);
			$values  = substr($values, 2);
		}

		// set up the query
		$this->setQuery('INSERT INTO ' . $this->quoteTable($table) . ' (' . $columns . ') VALUES (' . $values . ');');

		return $this;
	}

	/**
	 * Prepare and set a DELETE query against the db
	 *
	 * @param   String  $table       The table name
	 * @param   string  $mWhere      ( ie 'columnName' => 'columnValue') : a where clause is created like so : WHERE
	 *                               `columnName` = 'columnValue'. columnValue is escaped before being used
	 * @param   array   $aWhereData  Used only if $aWhere is a string. In such case, '?' place holders will be replaced by
	 *                               this array values, escaped
	 *
	 * @return Db
	 */
	public function setDeleteQuery($table, $mWhere = '', $aWhereData = array())
	{
		// where to look for
		$where = $this->buildWhereClause($mWhere, $aWhereData);

		// set up the query
		$this->setQuery('DELETE FROM ' . $this->quoteTable($table) . $where . ';');

		return $this;
	}

	/**
	 * Returns true if a record exists matching 'where' condition
	 *
	 * @param   String  $table       , the table to look into
	 * @param   string  $mWhere      ( ie 'columnName' => 'columnValue') : a where clause is created like so : WHERE
	 *                               `columnName` = 'columnValue'. columnValue is escaped before being used
	 * @param   array   $aWhereData  Used only if $aWhere is a string. In such case, '?' place holders will be replaced by
	 *                               this array values, escaped
	 *
	 * @return bool
	 */
	public function isRecord($table, $mWhere = '', $aWhereData = array())
	{
		// where to look for
		$where = $this->buildWhereClause($mWhere, $aWhereData);

		if (empty($where))
		{
			return false;
		}

		// set up the query and load result
		$this->setQuery('SELECT count(*) FROM ' . $this->quoteTable($table) . $where . ';');
		$result = $this->loadResult();

		return !empty($result);
	}

	/**
	 * Returns true if a record exists with a given Id
	 *
	 * @param   String   $table   , the table to look into
	 * @param   Integer  $id      , the id to look for
	 * @param   String   $idName  , default to 'id', the columns to look into, if not 'id'
	 */
	public function isRecordById($table, $id, $idName = 'id')
	{
		$id = (int) $id;

		if (empty($id))
		{
			return false;
		}

		// get db and look up record
		$this->setSelectQuery($table, array($idName), array($id));
		$result = $this->loadResult();

		return !empty($result);
	}

	/**
	 * Method to get the first row of the result set from the database query as an associative array
	 * of ['field_name' => 'row_value'].
	 *
	 * @return  mixed  The return value or null if the query failed.
	 *
	 * @throws  RuntimeException
	 */
	public function loadAssoc()
	{
		return $this->db->loadAssoc();
	}

	/**
	 * Method to get an array of the result set rows from the database query where each row is an associative array
	 * of ['field_name' => 'row_value'].  The array of rows can optionally be keyed by a field name, but defaults to
	 * a sequential numeric array.
	 *
	 * NOTE: Chosing to key the result array by a non-unique field name can result in unwanted
	 * behavior and should be avoided.
	 *
	 * @param   string  $key     The name of a field on which to key the result array.
	 * @param   string  $column  An optional column name. Instead of the whole row, only this column value will be in
	 *                           the result array.
	 *
	 * @return  mixed   The return value or null if the query failed.
	 *
	 * @throws  RuntimeException
	 */
	public function loadAssocList($key = null, $column = null)
	{
		return $this->db->loadAssocList($key, $column);
	}

	/**
	 * Method to get an array of values from the <var>$offset</var> field in each row of the result set from
	 * the database query.
	 *
	 * @param   integer  $offset  The row offset to use to build the result array.
	 *
	 * @return  mixed    The return value or null if the query failed.
	 *
	 * @throws  RuntimeException
	 */
	public function loadColumn($offset = 0)
	{
		return $this->db->loadColumn($offset);
	}

	/**
	 * Method to get the first row of the result set from the database query as an object.
	 *
	 * @param   string  $class  The class name to use for the returned row object.
	 *
	 * @return  mixed   The return value or null if the query failed.
	 *
	 * @throws  RuntimeException
	 */
	public function loadObject()
	{
		return $this->db->loadObject();
	}

	/**
	 * Method to get an array of the result set rows from the database query where each row is an object.  The array
	 * of objects can optionally be keyed by a field name, but defaults to a sequential numeric array.
	 *
	 * NOTE: Choosing to key the result array by a non-unique field name can result in unwanted
	 * behavior and should be avoided.
	 *
	 * @param   string  $key  The name of a field on which to key the result array.
	 *
	 * @return  mixed   The return value or null if the query failed.
	 *
	 * @throws  RuntimeException
	 */
	public function loadObjectList($key = '')
	{
		return $this->db->loadObjectList($key);
	}

	/**
	 * Method to get the first field of the first row of the result set from the database query.
	 *
	 * @return  mixed  The return value or null if the query failed.
	 *
	 * @throws  RuntimeException
	 */
	public function loadResult()
	{
		return $this->db->loadResult();
	}

	/**
	 * Method to get the first row of the result set from the database query as an array.  Columns are indexed
	 * numerically so the first column in the result set would be accessible via <var>$row[0]</var>, etc.
	 *
	 * @return  mixed  The return value or null if the query failed.
	 *
	 * @throws  RuntimeException
	 */
	public function loadRow()
	{
		return $this->db->loadRow();
	}

	/**
	 * Method to get an array of the result set rows from the database query where each row is an array.  The array
	 * of objects can optionally be keyed by a field offset, but defaults to a sequential numeric array.
	 *
	 * NOTE: Choosing to key the result array by a non-unique field can result in unwanted
	 * behavior and should be avoided.
	 *
	 * @param   string  $key  The name of a field on which to key the result array.
	 *
	 * @return  mixed   The return value or null if the query failed.
	 *
	 * @throws  RuntimeException
	 */
	public function loadRowList($key = null)
	{
		return $this->db->loadRowList($key);
	}

	/**
	 * Returns the last insert id.
	 *
	 * @return int
	 */
	public function insertId()
	{
		return $this->db->getInsertId();
	}

	/**
	 * Start a transation.
	 *
	 * @return mixed
	 */
	public function transactionStart()
	{
		return $this->db->transactionStart();
	}

	/**
	 * Commit queries in transaction.
	 *
	 * @return mixed
	 */
	public function transactionCommit()
	{
		return $this->db->transactionCommit();
	}

	/**
	 * Rollback queries from transaction.
	 *
	 * @return mixed
	 */
	public function transactionRollback()
	{
		return $this->db->transactionRollback();
	}

	/**
	 * Prepare data to be inserted in an sql statement
	 *
	 * @param   mixed  $data
	 */
	private function _prepareData($data)
	{
		// from Ron Baldwin <ron.baldwin#sourceprose.com>
		// Only quote string types
		$type = gettype($data);
		if ($type == 'string')
		{
			$ret = $this->quote($data);
		}
		else if ($type == 'double')
		{
			$ret = str_replace(',', '.', $data); // locales fix so 1.1 does not get converted to 1,1
		}
		else if ($type == 'boolean')
		{
			$ret = $data ? '1' : '0';
		}
		else if ($type == 'object')
		{
			if (method_exists($data, '__toString'))
			{
				$ret = $this->quote($data->__toString());
			}
			else
			{
				$ret = $this->quote((string) $data);
			}
		}
		else if ($data === null)
		{
			$ret = 'NULL';
		}
		else
		{
			$ret = $data;
		}

		return $ret;
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
		// where clause
		if (is_string($mWhere))
		{
			// litteral clause, find ? place holders
			if (!is_array($aWhereData))
			{
				$aWhereData = array($aWhereData);
			}
			$holderCount = substr_count($mWhere, '?');
			if ($holderCount > 0 && !empty($aWhereData) && $holderCount != count($aWhereData))
			{
				// the number of ? placehlders does not match the data array passed
				Wb\throwException(
					new \RuntimeException(
						__METHOD__ . ':' . __LINE__ . ': ' . 'Internal error: trying to build invalid db query where clause [ ' . \json_encode($mWhere) . ' ] [ '
						. \json_encode($aWhereData) . ' ]', 500
					)
				);
			}
			else
			{
				// we have ? placeholders and their number equals that of data passed
				$where = '';

				// find placeholders
				if (empty($aWhereData))
				{
					$where = $mWhere;
				}
				else
				{
					$sqlBits = explode('?', $mWhere);
					$i       = 0;
					// replace each place holder by the matching value
					foreach ($aWhereData as $data)
					{
						$where .= $sqlBits[$i];
						$where .= $this->_prepareData($data);
						$i     += 1;
					}
					if (isset($sqlBits[$i]))
					{
						$where .= $sqlBits[$i];
					}
				}
			}
		}
		elseif (is_array($mWhere))
		{
			// an array of columns/values, we must turn into a where clause
			// 2021-01-07: alternate syntax: $value can be an array. If so, it's similar to [$column, $op, $value]
			$where = '';
			foreach ($mWhere as $column => $value)
			{
				if (is_array($value))
				{
					if (count($value) !== 3)
					{
						Wb\throwException(
							new \RuntimeException(
								__METHOD__ . ':' . __LINE__ . ': ' . 'Internal error: trying to build invalid db query where clause [ ' . \json_encode($mWhere) . ' ] [ '
								. \json_encode($aWhereData) . ' ]', 500
							)
						);
					}
					$where .= ' AND ' . $this->quoteName($value[0]) . ' ' . $value[1] . ' ' . $this->_prepareData($value[2]);
				}
				else
				{
					$where .= ' AND ' . $this->quoteName($column) . '=' . $this->_prepareData($value);
				}
			}
			// remove initial AND
			$where = substr($where, 5);
		}
		else
		{
			$where = '';
		}

		return empty($where) ? '' : ' WHERE ' . $where;
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
		if (empty($orderBy))
		{
			return '';
		}

		$clause = '';

		// 1: $orderBy is a string
		if (is_string($orderBy))
		{
			$clause = $this->quoteName($orderBy);
		}
		else if (is_array($orderBy))
		{
			foreach ($orderBy as $key => $value)
			{
				if (is_int($key))
				{
					// 2 : $orderBy is an array of strings
					// use directly, always with no direction
					$clause .= ', ' . $this->quoteName((string) $value);
				}
				else
				{
					// 3 : $orderBy is an array of column names with direction
					$clause .= ', ' . $this->quoteName($key) . (empty($value) ? '' : $this->escape($value));
				}
			}
			$clause = empty($clause) ? '' : substr($clause, 2);
		}

		// put everything together
		$clause = empty($clause) ? '' : ' ORDER BY ' . $clause;

		return $clause;
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
		if (empty($offset) && empty($lines))
		{
			return '';
		}

		$clause = ' LIMIT ';
		if (!empty($offset))
		{
			$clause .= $this->_prepareData($offset);
		}
		if (!empty($lines))
		{
			$clause .= (empty($offset) ? '' : ', ') . $this->_prepareData($lines);
		}

		return $clause;
	}
}
