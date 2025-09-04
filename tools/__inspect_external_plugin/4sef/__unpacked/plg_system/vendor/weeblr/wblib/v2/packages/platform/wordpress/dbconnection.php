<?php
/**
 * 4SEF
 *
 * @author       Yannick Gaultier - Weeblr llc
 * @copyright    Copyright Weeblr llc - 2022 -2025
 * @package      4SEF
 * @license      GNU General Public License version 3; see LICENSE.md
 * @version      2.6.2.644
 *
 * 2025-06-02
 */

namespace Weeblr\Wblib\Forsef\Platform\Wordpress;

use Weeblr\Wblib\Forsef\Wb;

/* Security check to ensure this file is being included by a parent file.*/
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 *
 * Interface to WordPress database driver
 *
 * @author weeblr
 *
 */
class Dbconnection implements Platformdbconnectioninterface
{
	protected $db = null;
	private $sql = '';

	/**
	 * Get a hold on WP db object
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	public function getQuery()
	{
		return $this->sql;
	}

	public function getPrefix()
	{
		return $this->db->getPrefix();
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
		return $this->quoteName(str_replace($prefix, $this->db->prefix, $tableName));
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
		return '`' . $name . '`';
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
		return '\'' . ($escape ? $this->escape($value) : $value) . '\'';
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
		$result = $this->db->_escape($text);

		if ($extra)
		{
			$result = addcslashes($result, '%_');
		}

		return $result;
	}

	public function getNullDate()
	{
		return '0000-00-00 00:00:00';
	}

	/**
	 * Stores a (valid and complete) sql query, to
	 * be executed later
	 *
	 * @param $query
	 */
	public function setQuery($query, $offset = 0, $limit = 0)
	{
		$this->sql = $query;
	}

	/**
	 * Method to get the first row of the result set from the database query as an associative array
	 * of ['field_name' => 'row_value'].
	 *
	 * @return  mixed  The return value or null if the query failed.
	 *
	 * @throws  \RuntimeException
	 */
	public function loadAssoc()
	{
		$read = $this->db->get_row($this->sql, ARRAY_A);
		$this->checkError();

		return $read;
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
	 * @throws \Exception
	 */
	public function loadAssocList($key = null, $column = null)
	{
		$reads = $this->db->get_results($this->sql, ARRAY_A);
		$this->checkError();

		$output = array();
		if (!empty($reads))
		{
			if (!empty($key) && !isset($reads[0][$key]))
			{
				Wb\throwException(new \RuntimeException('wbLib: trying to index row list with non-existing key.'));
			}
			foreach ($reads as $assocRow)
			{
				if (empty($key))
				{
					$output[] = $assocRow;
				}
				else
				{
					$output[$assocRow[$key]] = $assocRow;
				}
			}
		}

		return $output;
	}

	/**
	 * Method to get an array of values from the <var>$offset</var> field in each row of the result set from
	 * the database query.
	 *
	 * @param   integer  $offset  The row offset to use to build the result array.
	 *
	 * @return  mixed    The return value or null if the query failed.
	 *
	 * @throws \Exception
	 */
	public function loadColumn($offset = 0)
	{
		Wb\throwException(new \RuntimeException('wbLib: not implemented'));
		//return $read;
	}

	/**
	 * Method to get the first row of the result set from the database query as an object.
	 *
	 * @param   string  $class  The class name to use for the returned row object.
	 *
	 * @return  mixed   The return value or null if the query failed.
	 *
	 * @throws  \RuntimeException
	 */
	public function loadObject()
	{
		$read = $this->db->get_row($this->sql);
		$this->checkError();

		return $read;
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
	 * @throws  \RuntimeException
	 */
	public function loadObjectList($key = '')
	{
		$errorState = $this->db->hide_errors();
		$reads      = $this->db->get_results($this->sql);
		$this->db->show_errors($errorState);
		$this->checkError();

		$output = array();
		if (!empty($reads))
		{
			if (!empty($key) && !isset($reads[0]->{$key}))
			{
				Wb\throwException(new \RuntimeException('wbLib: trying to index object list with non-existing key.'));
			}
			foreach ($reads as $objectRow)
			{
				if (empty($key))
				{
					$output[] = $objectRow;
				}
				else
				{
					$output[$objectRow->{$key}] = $objectRow;
				}
			}
		}

		return $output;
	}

	/**
	 * Method to get the first field of the first row of the result set from the database query.
	 *
	 * @return  mixed  The return value or null if the query failed.
	 *
	 * @throws  \RuntimeException
	 */
	public function loadResult()
	{
		return $this->db->getResult();
	}

	/**
	 * Method to get the first row of the result set from the database query as an array.  Columns are indexed
	 * numerically so the first column in the result set would be accessible via <var>$row[0]</var>, etc.
	 *
	 * @return  mixed  The return value or null if the query failed.
	 *
	 * @throws  \RuntimeException
	 */
	public function loadRow()
	{
		$read = $this->db->get_row($this->sql, ARRAY_N);
		$this->checkError();

		return $read;
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
	 * @throws  \RuntimeException
	 */
	public function loadRowList($key = null)
	{
		$reads = $this->db->get_results($this->sql, ARRAY_A);
		$this->checkError();
		$output = array();
		if (!empty($reads))
		{
			if (!empty($key) && !isset($reads[0][$key]))
			{
				Wb\throwException(new \RuntimeException('wbLib: trying to index row list with non-existing key.'));
			}
			foreach ($reads as $assocRow)
			{
				if (empty($key))
				{
					$output[] = array_values($assocRow);
				}
				else
				{
					$output[$assocRow[$key]] = array_values($assocRow);
				}
			}
		}

		return $output;
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

		$result = $this->db->query($this->sql);

		$this->checkError();

		return $result;
	}

	public function getInsertId()
	{
		return $this->db->insert_id;
	}

	/**
	 * Check if last db op generated an error. Called after each db op
	 *
	 * @throws \RuntimeException
	 */
	private function checkError()
	{
		if (!empty($this->db->last_error))
		{
			Wb\throwException(new \RuntimeException('wbLib: db error - ' . $this->db->last_error));
		}
	}
}
