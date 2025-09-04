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

namespace Weeblr\Wblib\Forsef\Db;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\System;

/** ensure this file is being included by a parent file */
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * Database persistence for data objects.
 * Data is stored in the state it will be stored to the database.
 *
 */
class Dataobject extends Base\Dataobject
{
	/**
	 * @var string[] List of columns and their id which can be searched.
	 */
	protected $searchableColumns = [];

	/**
	 * @var string[] List of columns and their id which can be ordered by.
	 */
	protected $orderableColumns = [];

	/**
	 * @var string[] List of data fields representing long varchar content that still must be indexed.
	 * Keys are full length column names, values are corresponding indexable column name.
	 */
	protected $storageSafeColumns = [];

	/**
	 * @var array List of data key that should be ignored when storing to the DB.
	 */
	protected $dbIgnore = [];

	/**
	 * @var string Database table associated with this instance.
	 */
	protected $table = '';

	/**
	 * @var string Database table key name. Only single key supported.
	 */
	protected $keyName = 'id';

	/**
	 * @var Weeblr\Wblib\Db\Dbhelper Database helper instance.
	 */
	protected $db = null;

	/**
	 * @var Helper Convenience instance of the Db helper.
	 */
	protected $helper = null;

	/**
	 * Associate this instance to a database table.
	 *
	 * // @param string $table Legacy, replaced with $options
	 * @param array $options Can inject custom factory and platform.
	 *
	 * @throws \Exception
	 */
	public function __construct($options = null)
	{
		// Legacy signature handling
		$optionsArray = is_array($options)
			? $options
			: [];
		if (
			is_string($options)
			&&
			!empty($options)
		)
		{
			$optionsArray['table'] = $options;
		}

		parent::__construct($options);

		$this->table = Wb\arrayIsEmpty($optionsArray, 'table')
			? $this->table
			: Wb\arrayGet($optionsArray, 'table');

		$this->db = Wb\arrayGet($optionsArray, 'db', $this->db);
		if (empty($this->db))
		{
			$this->db = $this->factory->getThe('db');
		}
		$this->helper = $this->factory->getA(Helper::class);

		if (
			empty($this->table)
			||
			empty($this->data)
			||
			empty($this->keyName)
		)
		{
			throw new \Exception(get_class($this) . ': invalid database or data specification.', 500);
		}
	}

	/**
	 * Validate whether data for a given key is ok and can be used by the object. Possible
	 * processing is allowed to fix/update things here.
	 *
	 * @param string $key
	 *
	 * @return $this
	 * @throws \Exception
	 */
	protected function validateKey($key)
	{
		if (empty($key))
		{
			throw new \Exception('Trying to set/get empty key on ' . __CLASS__ . ' data object', 500);
		}

		// if replacing a keyed item, that key must be one of the data set
		if (
			!array_key_exists(
				$key,
				$this->data
			)
			&&
			!in_array(
				$key,
				$this->dbIgnore
			))
		{
			throw new \Exception('Trying to set/get unknown key ' . print_r($key, true) . ' / ' . print_r($this->data, true)
								 . ' / Db Ignore: ' . print_r($this->dbIgnore, true)
								 . ' on ' . __CLASS__ . ' data object ' . print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), true), 500);
		}

		return $this;
	}

	/**
	 * Encode full length values into their indexable form.
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return mixed
	 */
	public function encodeValue($key, $value)
	{
		if (array_key_exists($key, $this->storageSafeColumns))
		{
			$this->data[$this->storageSafeColumns[$key]] = $this->helper->storageSafe($value);
		}

		return parent::encodeValue($key, $value);
	}

	/**
	 * Delete the database record:
	 * - current one if no key supplied and data as been loaded already (ie we have a key value)
	 * - if key is a string, the record identified by that key.
	 * - if array of keys, the records identified by those keys.
	 *
	 * @param null|string|array $keys
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function delete($keys = null)
	{
		$currentKey = Wb\arrayGet(
			$this->data,
			$this->keyName,
			null
		);

		if (empty($keys) && is_null($currentKey))
		{
			throw new \Exception('wbLib: cannot delete item from database without a key value.', 500);
		}

		if (empty($keys))
		{
			$keys = [$currentKey];
		}

		$keys = $this->beforeDelete(
			Wb\arrayEnsure($keys)
		);

		if (empty($keys))
		{
			return $this;
		}

		$this->db->deleteIn(
			$this->tableName(),
			$this->keyName,
			$keys
		);

		$this->afterDelete($keys);

		return $this;
	}

	/**
	 * Purge function, empty this object database table.
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function deleteAll()
	{
		$this->db->truncate(
			$this->tableName()
		);

		return $this;
	}

	/**
	 * Filter list of keys to be deleted. Return false or empty
	 * array to cancel the deletion operation.
	 *
	 * @param array $keys
	 *
	 * @return mixed
	 */
	protected function beforeDelete($keys)
	{
		return $keys;
	}

	/**
	 * Perform actions after a deletion has taken place.
	 *
	 * @param array $keys
	 *
	 * @return Dataobject
	 */
	protected function afterDelete($keys)
	{
		return $this;
	}

	/**
	 * Loads a record from database and store its content in this object.
	 *
	 * @param null|string $key
	 * @param bool        $reload
	 *
	 * @return $this
	 */
	public function load($key = null, $reload = false)
	{
		$currentKey = Wb\arrayGet($this->data, $this->keyName, '');
		if (
			!empty($currentKey)
			&&
			$currentKey == $key
			&&
			!$reload
		)
		{
			// already have the data
			return $this;
		}

		// don't have the data, or force reload
		$key        = empty($key) ? $currentKey : $key;
		$this->data = $this->db->selectAssoc(
			$this->tableName(),
			'*',
			[
				$this->keyName => $key
			]
		);

		$this->data = $this->afterLoad(
			$this->data,
			$key
		);

		return $this;
	}

	/**
	 * Filter the returned data after a get.
	 *
	 * @param mixed       $data
	 * @param null|string $key
	 *
	 * @return mixed
	 */
	protected function afterLoad($data, $key = null)
	{
		return array_merge(
			$this->defaults,
			empty($data)
				? []
				: $data
		);
	}

	/**
	 * Loads an item from DB based on specific column value.
	 * Key value must be provided and loaded data replaces any data
	 * already stored in this instance if some data is found in db.
	 * Data is always loaded from DB, on each call.
	 *
	 * @param string $keyNameOrArray
	 * @param mixed  $value
	 * @param array  $aWhereData
	 * @param array  $orderBy
	 * @param int    $offset
	 * @param int    $lines
	 *
	 * @return $this
	 */
	public function loadPerColumn($keyNameOrArray, $value = null, $aWhereData = [], $orderBy = [], $offset = 0, $lines = 0)
	{
		$dbData = $this->db->selectAssoc(
			$this->tableName(),
			'*',
			is_array($keyNameOrArray)
				? $keyNameOrArray
				: [
				$keyNameOrArray => $value
			],
			$aWhereData,
			$orderBy,
			$offset,
			$lines
		);
		if (!empty($dbData))
		{
			$this->data = $this->afterLoad(
				$dbData
			);
		}

		return $this;
	}

	/**
	 * Loads an item from db based on a set of column values.
	 * Passed key => value array is considered a set of
	 * where clauses, stitched together with AND operators.
	 *
	 * @param [] $where
	 *
	 * @param array $whereData
	 *
	 * @return $this
	 */
	public function loadWhere($where, $whereData = [])
	{
		$dbData = $this->db->selectAssoc(
			$this->tableName(),
			'*',
			$where
		);
		if (!empty($dbData))
		{
			$this->data = $this->afterLoad(
				$dbData
			);;
		}

		return $this;
	}

	/**
	 * Stores current data to the database.
	 *
	 * @param array $storeOptions Passed to other methods used in storing
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function store($storeOptions = [])
	{
		if (!$this->validate()->beforeStore($storeOptions))
		{
			return $this;
		}

		// filter out any unwanted data
		$data = $this->data;
		if (!empty($this->dbIgnore))
		{
			$data = array_diff_key(
				$data,
				array_flip($this->dbIgnore)
			);
		}

		// finally store/update
		$key = Wb\arrayGet($this->data, $this->keyName, '');
		if (empty($key))
		{
			// create
			$this->db->insert(
				$this->tableName(),
				$data
			);

			$this->data[$this->keyName] = $this->db->getInsertId();
		}
		else
		{
			// update
			$this->db->update(
				$this->tableName(),
				$data,
				array(
					$this->keyName => $key
				)
			);
		}

		return $this;
	}

	/**
	 * A chance to massage data before storing it. If returning false,
	 * the store operation is cancelled silently.
	 *
	 * @param $storeOptions Possible options when storing
	 *
	 * @return bool
	 */
	public function beforeStore($storeOptions = [])
	{
		return true;
	}

	/**
	 * Returns the record key from the db.
	 *
	 * @return int|null
	 */
	public function getId()
	{
		return Wb\arrayGet(
			$this->data,
			$this->keyName,
			null
		);
	}

	/**
	 * Load instance from db by searching for a given full length column.
	 * The provided full length value is first processed to be "indexable", ie shortened
	 * as needed and match the format of the indexable database field.
	 *
	 * @param string $fullLengthValue
	 * @param string $columnName
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function loadStorageSafe($fullLengthValue, $columnName)
	{
		$indexableName = Wb\arrayGet(
			$this->storageSafeColumns,
			$columnName
		);

		if (empty($indexableName))
		{
			System\Log::libraryError('URL data object::loadPerURL, field %s not in urlFields, searching for %s - %s', $columnName, $fullLengthValue, print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), true));
			throw new \Exception('Internal error. See error log file.', System\Http::RETURN_INTERNAL_ERROR);
		}

		$indexableValue = $this->helper->storageSafe($fullLengthValue);

		return $this->loadPerColumn(
			$indexableName,
			$indexableValue
		);
	}

	/**
	 * @return string Getter for this dataObject main associated database table.
	 */
	public function tableName()
	{
		return $this->table;
	}

	/**
	 * @return string[] Getter for this dataObject list of searchable columns and their ids.
	 */
	public function searchableColumnsList()
	{
		return $this->searchableColumns;
	}

	/**
	 * @return string[] Getter for this dataObject list of orderable columns and their ids.
	 */
	public function orderableColumnsList()
	{
		return $this->orderableColumns;
	}

	/**
	 * @return string[] Getter for this dataObject list of orderable columns and their ids.
	 */
	public function storageSafeColumnsList()
	{
		return $this->storageSafeColumns;
	}

	/**
	 * Whether this instance has a database record.
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function exists()
	{
		return !empty($this->data) && $this->isTruthy($this->keyName);
	}
}
