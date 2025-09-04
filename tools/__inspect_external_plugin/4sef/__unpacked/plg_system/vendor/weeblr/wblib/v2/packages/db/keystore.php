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

use Weeblr\Wblib\Forsef\System\Log;
use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\System;

defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * Generic simple key/value storage
 *
 */
class Keystore extends Base\Base
{
	/**
	 * Default db table name
	 *
	 * CREATE TABLE IF NOT EXISTS `#__wbl_keystore`
	 * (
	 * `id`          int(10) unsigned                                                NOT NULL AUTO_INCREMENT COMMENT
	 * 'Primary Key',
	 * `scope`       VARCHAR(40)                                                     NOT NULL DEFAULT 'default',
	 * `key`         VARCHAR(150)                                                    NOT NULL,
	 * `value`       VARCHAR(16000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
	 * `large_value` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci     NOT NULL,
	 * `user_id`     INT(11)                                                         NOT NULL DEFAULT 0,
	 * `version`     int(10) unsigned                                                NOT NULL DEFAULT 0 COMMENT 'Future
	 * use',
	 * `lock`        CHAR(40)                                                        NOT NULL DEFAULT '' COMMENT
	 * `lock_expires_at`        datetime                                             NULL
	 * 'Future use',
	 * `format`      TINYINT                                                         NOT NULL DEFAULT 1,
	 * `modified_at` datetime                                                        NOT NULL,
	 *
	 * PRIMARY KEY (`id`),
	 * UNIQUE KEY `main` (`scope`, `key`)
	 *
	 * ) ENGINE = InnoDB
	 * DEFAULT CHARSET = utf8
	 * DEFAULT COLLATE = utf8_unicode_ci;
	 */

	const TABLE_NAME = '';

	/**
	 * Base format constant. Right now we de/serialize to and from php and json, and things are likely to stay like this
	 */
	const FORMAT_PHP = 0;
	const FORMAT_JSON = 1;
	const FORMAT_JSON_ARRAY = 2;

	/**
	 * Do not encode
	 */
	const FORMAT_RAW = 128;

	/**
	 * Not supported yet
	 */
	const FORMAT_YAML = 2;

	/**
	 * default scope, when missing from requests
	 */
	const DEFAULT_SCOPE = 'default';

	/**
	 * Cache for current user id
	 *
	 * @var int|null
	 */
	protected $userId = null;

	/**
	 * @var string name of db table to hold keystore values
	 */
	protected $tableName = '';

	/**
	 * @var Helper A helper for all database access.
	 */
	protected $dbHelper = null;

	/**
	 * @var int Default value for the storage format.
	 */
	protected $defaultFormat = null;

	/**
	 * Store commonly used upstream object
	 * DB table to use for storage must be set. There is no shared storage as this would cause
	 * dependencies issues if multiple extensions were to use the same one.
	 *
	 * @param   array  $options
	 *
	 * @throws \Exception
	 */
	public function __construct($options)
	{
		parent::__construct();

		$this->dbHelper  = $this->factory->getThe('db');
		$this->tableName = Wb\arrayGet($options, 'tableName');
		if (empty($this->tableName))
		{
			throw new \Exception('wbLib Internal error: empty table name passed to keystore constructor. Cannot continue.', 500);
		}
		$this->defaultFormat = Wb\arrayGet($options, 'format', self::FORMAT_JSON);
		$this->userId        = $this->platform->getUser()->id;
	}

	/**
	 * Store data in keystore without any serialization
	 *
	 * @param   string  $key    unique id for the data
	 * @param   mixed   $value  data to store
	 *
	 * @param   string  $scope
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function putRaw($key, $value, $scope = self::DEFAULT_SCOPE)
	{
		if (!is_scalar($value) && !is_null($value))
		{
			Wb\throwException(new \InvalidArgumentException('wbLib: Raw value passed to keystore is invalid, not scalar'));
		}

		return $this->put($key, $value, $scope, self::FORMAT_RAW);
	}

	/**
	 * Store a value into the keystore, identified by a key. Overwrite any pre-existing value with same key.
	 * Value is serialized prior to being stored, using JSON serialization by default
	 * Alternative is PHP.
	 *
	 * @param   string  $key
	 * @param   mixed   $value
	 * @param   string  $scope
	 * @param   int     $format        use of the class constants
	 * @param   bool    $isLargeValue  Whether to use the large value field, a MEDIUMTEXT
	 *
	 * @return $this
	 * @throws \Exception
	 */
	protected function doPut($key, $value, $scope = self::DEFAULT_SCOPE, $format = null, $isLargeValue = false)
	{
		if (empty($key))
		{
			Wb\throwException(new \InvalidArgumentException('wbLib: Empty key while trying to put some data in key store'));
		}

		if (is_null($format))
		{
			$format = $this->defaultFormat;
		}

		$data = array(
			'scope'           => $scope,
			'key'             => $key,
			'value'           => $isLargeValue ? '' : $this->_encode($value, $format),
			'large_value'     => $isLargeValue ? $this->_encode($value, $format) : '',
			'user_id'         => $this->userId,
			'modified_at'     => System\Date::getUTCNow(),
			'lock'            => '',
			'lock_expires_at' => null,
			'format'          => $format
		);

		// insert or update the record in database
		$this->dbHelper->insertUpdate($this->tableName, $data, array('scope' => $scope, 'key' => $key));

		return $this;
	}

	/**
	 * Store a value into the keystore, identified by a key. Size limit equivalent to MEDIMUMTEXT column.
	 * Overwrite any pre-existing value with same key.
	 * Value is serialized prior to being stored, using JSON serialization by default
	 * Alternative is PHP.
	 *
	 * @param   string  $key
	 * @param   mixed   $value
	 * @param   string  $scope
	 * @param   null    $format
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function putLargeValue($key, $value, $scope = self::DEFAULT_SCOPE, $format = null)
	{
		return $this->doPut($key, $value, $scope, $format, true);
	}

	/**
	 * Store a value into the keystore, identified by a key. Size limit is 16000 UTF8MB4 characters.
	 *
	 * Overwrite any pre-existing value with same key.
	 * Value is serialized prior to being stored, using JSON serialization by default
	 * Alternative is PHP.
	 *
	 * @param   string  $key
	 * @param   mixed   $value
	 * @param   string  $scope
	 * @param   null    $format
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function put($key, $value, $scope = self::DEFAULT_SCOPE, $format = null)
	{
		return $this->doPut($key, $value, $scope, $format, false);
	}

	/**
	 * Retrieves a value from the keystore, identified by its key. Size limit equivalent to MEDIMUMTEXT column.
	 * If not found, returns default value passed in.
	 *
	 * @param   string  $key
	 * @param   mixed   $default
	 * @param   string  $scope
	 *
	 * @return mixed|null
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 */
	public function getLargeValue($key, $default = null, $scope = self::DEFAULT_SCOPE)
	{
		return $this->doGet($key, $default, $scope, true);
	}

	/**
	 * Get an existing value and lock its record for a certain time, so that no one else can read it
	 * until this time out has expired or lock has been removed with unlockExisting().
	 *
	 * @param           $key
	 * @param           $lockedBy
	 * @param           $lockTimeout
	 * @param   string  $scope
	 * @param   bool    $isLargeValue
	 *
	 * @return array
	 * @throws \Throwable
	 */
	public function getAndLockExisting($key, $lockedBy, $lockTimeout, $scope = self::DEFAULT_SCOPE, $isLargeValue = false)
	{
		if (empty($key))
		{
			Wb\throwException(new \InvalidArgumentException('wbLib: Empty key while trying to lock some data in key store'));
		}

		try
		{
			$this->garbageCollectLocks();

			// try to acquire lock
			$this->dbHelper->update(
				$this->tableName,
				[
					'lock'            => $lockedBy,
					'lock_expires_at' => System\Date::toExtendedDateTime()
						->add($lockTimeout)
						->toMysql()
				],
				[
					'key' => $key,
					['lock', '=', '']
				]
			);

			// find out if we did
			$record = $this->dbHelper->selectAssoc(
				$this->tableName,
				[
					$isLargeValue ? 'large_value' : 'value',
					'format'
				],
				[
					'scope' => $scope,
					'key'   => $key,
					'lock'  => $lockedBy
				]
			);

			$value = empty($record) ? null : $this->_decode($record['value'], $record['format']);

			return [
				'locked' => !empty($record),
				'value'  => $value,
				'error'  => false
			];
		}
		catch (\Throwable $e)
		{
			Log::libraryError('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}

		return [
			'locked' => false,
			'value'  => null,
			'error'  => true
		];
	}

	/**
	 * Remove the lock on an existing keystore record, as acquired with getAndLockExisting()
	 *
	 * @param   string  $key
	 * @param   string  $lockedBy
	 */
	public function unlock($key, $lockedBy, $scope = self::DEFAULT_SCOPE)
	{
		$this->dbHelper->update(
			$this->tableName,
			[
				'lock'            => '',
				'lock_expires_at' => null
			],
			[
				'scope' => $scope,
				'key'   => $key,
				'lock'  => $lockedBy,
			]
		);
	}

	/**
	 * Delete a record, but only if it was locked under the provided lock id.
	 *
	 * Used after completing an operation requiring a lock, without interfering with what other
	 * processes may have set during the operation completion.
	 *
	 * @param   string  $key
	 * @param   string  $lockedBy
	 */
	public function deleteLocked($key, $lockedBy, $scope = self::DEFAULT_SCOPE)
	{
		$this->dbHelper->delete(
			$this->tableName,
			[
				'scope' => $scope,
				'key'   => $key,
				'lock'  => $lockedBy,
			]
		);
	}

	/**
	 * Delete all expired locks.
	 */
	private function garbageCollectLocks()
	{
		// garbage collect expired locks
		$this->dbHelper->update(
			$this->tableName,
			[
				'lock'            => '',
				'lock_expires_at' => null
			],
			[
				['lock', '!=', ''],
				['lock_expires_at', '<', System\Date::getUTCNow()],
			]
		);
	}

	/**
	 * Acquires a lock on a key, creating it with the provided value if not existing.
	 *
	 * @param           $key
	 * @param           $value
	 * @param           $lockedBy
	 * @param           $lockTimeout
	 * @param   string  $scope
	 * @param   null    $format
	 * @param   false   $isLargeValue
	 *
	 * @return array
	 * @throws \Throwable
	 */
	public function getAndLock($key, $value, $lockedBy, $lockTimeout, $scope = self::DEFAULT_SCOPE, $format = null, $isLargeValue = false)
	{
		if (empty($key))
		{
			Wb\throwException(new \InvalidArgumentException('wbLib: Empty key while trying to insert and lock some data in key store'));
		}

		$this->garbageCollectLocks();

		try
		{

			if (is_null($format))
			{
				$format = $this->defaultFormat;
			}

			$data = [
				'scope'           => $scope,
				'key'             => $key,
				'value'           => $isLargeValue ? '' : $this->_encode($value, $format),
				'large_value'     => $isLargeValue ? $this->_encode($value, $format) : '',
				'user_id'         => $this->userId,
				'modified_at'     => System\Date::getUTCNow(),
				'format'          => $format,
				'lock'            => $lockedBy,
				'lock_expires_at' => System\Date::toExtendedDateTime()
					->add($lockTimeout)
					->toMysql()
			];

			// insert record into database. This will fail if key already exists.
			$this->dbHelper->insert(
				$this->tableName,
				$data
			);
		}
		catch (\Throwable $e)
		{
			// If inserting fails, try to update an existing record.
			$this->dbHelper->update(
				$this->tableName,
				[
					'lock'            => $lockedBy,
					'lock_expires_at' => System\Date::toExtendedDateTime()
						->add($lockTimeout)
						->toMysql()
				],
				[
					'key' => $key,
					['lock', '=', '']
				]
			);
		}

		try
		{
			// find out if we did
			$record = $this->dbHelper->selectAssoc(
				$this->tableName,
				[
					$isLargeValue ? 'large_value' : 'value',
					'format'
				],
				[
					'scope' => $scope,
					'key'   => $key,
					'lock'  => $lockedBy
				]
			);

			$readValue = empty($record) ? null : $this->_decode($record['value'], $record['format']);

			return [
				'locked' => !empty($record),
				'value'  => $readValue,
				'error'  => false
			];
		}
		catch (\Throwable $e)
		{
			Log::libraryError('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}

		return [
			'locked' => false,
			'value'  => null,
			'error'  => true
		];
	}

	/**
	 * Set a key/value pair in a lock-safe way, only if the key does not exists already.
	 *
	 * @param           $key
	 * @param           $value
	 * @param   string  $scope
	 * @param   null    $format
	 * @param   false   $isLargeValue
	 *
	 * @return array
	 * @throws \Throwable
	 */
	public function safePut($key, $value, $scope = self::DEFAULT_SCOPE, $format = null, $isLargeValue = false)
	{
		if (empty($key))
		{
			Wb\throwException(new \InvalidArgumentException('wbLib: Empty key while trying to insert and lock some data in key store'));
		}

		try
		{
			$this->garbageCollectLocks();

			if (is_null($format))
			{
				$format = $this->defaultFormat;
			}

			$data = [
				'scope'           => $scope,
				'key'             => $key,
				'value'           => $isLargeValue ? '' : $this->_encode($value, $format),
				'large_value'     => $isLargeValue ? $this->_encode($value, $format) : '',
				'user_id'         => $this->userId,
				'modified_at'     => System\Date::getUTCNow(),
				'format'          => $format,
				'lock'            => '',
				'lock_expires_at' => null
			];

			// insert record into database. This will fail if key already exists.
			$this->dbHelper->insert(
				$this->tableName,
				$data
			);
		}
		catch (\Throwable $e)
		{
			// inserting failed, can be an actual error or the key already exists.
		}

		try
		{
			// find out if we did
			$record = $this->dbHelper->selectAssoc(
				$this->tableName,
				[
					$isLargeValue ? 'large_value' : 'value',
					'format'
				],
				[
					'scope' => $scope,
					'key'   => $key
				]
			);

			$readValue = empty($record) ? null : $this->_decode($record['value'], $record['format']);

			return [
				'locked' => false,
				'value'  => $readValue,
				'error'  => empty($record)
			];
		}
		catch (\Throwable $e)
		{
			Log::libraryError('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}

		return [
			'locked' => false,
			'value'  => null,
			'error'  => true
		];
	}

	/**
	 * Retrieves a value from the keystore, identified by its key. Size limit is 16000 UTF8MB4 characters.
	 * If not found, returns default value passed in.
	 *
	 * @param   string  $key
	 * @param   mixed   $default
	 * @param   string  $scope
	 *
	 * @return mixed|null
	 * @throws \Exception
	 */
	public function get($key, $default = null, $scope = self::DEFAULT_SCOPE)
	{
		return $this->doGet($key, $default, $scope, false);
	}

	/**
	 * Verify if a value from keystore, or the default value passed if not found in keystore, is falsy, using empty().
	 *
	 * @param   string  $key
	 * @param   mixed   $default
	 * @param   string  $scope
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function isFalsy($key, $default = null, $scope = self::DEFAULT_SCOPE)
	{
		return empty($this->doGet($key, $default, $scope, false));
	}

	/**
	 * Verify if a value from keystore, or the default value passed if not found in keystore, is truthy, using !empty().
	 *
	 * @param   string  $key
	 * @param   mixed   $default
	 * @param   string  $scope
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function isTruthy($key, $default = null, $scope = self::DEFAULT_SCOPE)
	{
		return !$this->isFalsy($this->doGet($key, $default, $scope, false));
	}

	/**
	 * Retrieves a value from the keystore, identified by its key.
	 * If not found, returns default value passed in.
	 *
	 * @param   string  $key
	 * @param   mixed   $default
	 * @param   string  $scope
	 * @param   bool    $isLargeValue  Whether to use the large value field, a MEDIUMTEXT
	 *
	 * @return mixed|null
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 */
	protected function doGet($key, $default = null, $scope = self::DEFAULT_SCOPE, $isLargeValue = false)
	{
		if (empty($key))
		{
			Wb\throwException(new \InvalidArgumentException('wbLib: Empty key while trying to put some data in key store'));
		}

		$storageColumn = $isLargeValue ? 'large_value' : 'value';

		$record = $this->dbHelper->selectAssoc(
			$this->tableName,
			array(
				$storageColumn,
				'format'
			),
			array(
				'scope' => $scope,
				'key'   => $key
			)
		);
		$value  = empty($record) ? null : $this->_decode($record['value'], $record['format']);
		$value  = is_null($value) ? $default : $value;

		return $value;
	}

	/**
	 * Retrieves a value and its meta data from the keystore, identified by its key.
	 * If not found, returns default value passed in.
	 *
	 * @param   string  $key
	 * @param   mixed   $default
	 *
	 * @param   string  $scope
	 *
	 * @return array
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 */
	public function getDetailed($key, $default = null, $scope = self::DEFAULT_SCOPE)
	{
		if (empty($key))
		{
			Wb\throwException(new \InvalidArgumentException('wbLib: Empty key while trying to put some data in key store'));
		}

		$record          = $this->dbHelper->selectAssoc(
			$this->tableName,
			'*',
			array(
				'scope' => $scope,
				'key'   => $key
			)
		);
		$value           = empty($record) ? null : $this->_decode($record['value'], $record['format']);
		$record['value'] = is_null($value) ? $default : $value;

		return $record;
	}

	/**
	 * Delete a record in the keystore
	 *
	 * @param   string  $key
	 *
	 * @param   string  $scope
	 *
	 * @return $this
	 *
	 * @throws \Exception
	 */
	public function delete($key, $scope = self::DEFAULT_SCOPE)
	{

		if (empty($key))
		{
			Wb\throwException(new \InvalidArgumentException('wbLib: Empty key while trying to delete some data from key store'));
		}

		$this->dbHelper->delete($this->tableName, array('scope' => $scope, 'key' => $key));

		return $this;
	}

	/**
	 * Encode a value to one of the supported format, PHP serialization or json
	 *
	 * @param   mixed  $value
	 * @param   int    $format  see class constant
	 *
	 * @return string
	 */
	protected function _encode($value, $format)
	{
		switch ($format)
		{
			case self::FORMAT_PHP:
				$value = serialize($value);
				break;
			case self::FORMAT_JSON:
			case self::FORMAT_JSON_ARRAY:
				$value = \json_encode($value);
				break;
			default:
				break;
		}

		return $value;
	}

	/**
	 * Decode a raw value read from keystore, using the format also retrieved along the value.
	 * See class constants for format code.
	 *
	 * @param   string  $value
	 * @param   int     $format
	 *
	 * @return mixed
	 */
	protected function _decode($value, $format)
	{
		switch ($format)
		{
			case self::FORMAT_PHP:
				$value = unserialize($value);
				break;
			case self::FORMAT_JSON:
				$value = json_decode($value);
				break;
			case self::FORMAT_JSON_ARRAY:
				$value = json_decode($value, true);
				break;
			default:
				break;
		}

		return $value;
	}
}
