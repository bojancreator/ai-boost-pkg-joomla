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

namespace Weeblr\Forsef\Model;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Db;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Config extends System\Config
{
	/**
	 * @var string Name of database table to store this config.
	 */
	protected $tablename = '#__forsef_config';

	/**
	 * @var null Keystore object to persist the config.
	 */
	protected $store = null;

	/**
	 * @var array Default values for this config, loaded from disk file.
	 */
	protected $defaults = [];

	/**
	 * @var bool Whether to store this config into a keystore for persistence
	 */
	protected $persist = true;

	/**
	 * @var array List of config keys not to be stored (ie hardcoded only)
	 */
	protected $doNotStore = [];

	/**
	 * @var array List of per key type defintion. Optional.
	 */
	protected $enforcedTypes = [];

	/**
	 * @var array Static storage for preloading all configs from keystore in one go.
	 */
	protected static $preloadedConfigs = null;

	/**
	 * Load hardcoded defaults and optionnally read saved values from database.
	 *
	 * @param         $scope
	 * @param bool    $autoload
	 */
	public function __construct($scope, $autoload = true)
	{
		parent::__construct($scope);

		$this->loadDefaults();

		// load from keystore
		if (
			$this->persist
			&&
			$autoload
		)
		{
			$this->preload()
				 ->load();
		}

		/**
		 * Filter configuration right after creating it.
		 *
		 * @api     forsef
		 * @package 4SEF\filter\config
		 * @var forsef_config
		 * @since   1.0.0
		 *
		 * @param array  $config
		 * @param string $scope
		 *
		 * @return void
		 *
		 */
		$this->config = $this->factory
			->getThe('hook')
			->filter(
				'forsef_config',
				$this->config,
				$this->scope
			);
	}

	/**
	 * Loads all config objects from this keystore into a buffer to avoid multiple queries.
	 *
	 * @return $this
	 */
	protected function preload()
	{
		if (is_null(self::$preloadedConfigs))
		{
			$configs = $this->factory
				->getA(Db\Helper::class)
				->selectAssocList(
					$this->tablename,
					['key', 'value']
				);
			$configs = empty($configs)
				? []
				: $configs;
			foreach ($configs as $config)
			{
				self::$preloadedConfigs[$config['key']] = json_decode(
					$config['value'],
					true
				);
			}
		}

		return $this;
	}

	/**
	 * Load configuration from database.
	 *
	 * @return $this
	 */
	public function load()
	{
		$config = Wb\arrayGet(
			self::$preloadedConfigs,
			$this->scope,
			null
		);

		$this->config = is_null($config)
			? $this->config
			: array_merge(
				$this->config,
				$config
			);

		$this->setDownloadId();

		/**
		 * Filter configuration right after loading it from the database.
		 *
		 * @api     forsef
		 * @package 4SEF\filter\config
		 * @var forsef_config_loaded
		 * @since   1.0.0
		 *
		 * @param array  $config
		 * @param string $scope
		 *
		 * @return void
		 *
		 */
		$this->config = $this->factory
			->getThe('hook')
			->filter(
				'forsef_config_loaded',
				$this->config,
				$this->scope
			);

		return $this->validate();
	}

	private function setDownloadId()
	{
		if ('prod' !== WBLIB_Forsef_OP_MODE)
		{
			return;
		}

		if (
			$this->platform->majorVersion() < 4
			||
			'system' != $this->scope
		)
		{
			return;
		}

		// Download id is stored in update site tables
		try
		{
			$platformDlid = $this->factory
				->getA(Credentials::class)
				->get('update');
			$currentDlid  = Wb\arrayGet(
				$this->config,
				'dlid'
			);

			// use platform-entered update key if
			// there's none inside of 4SEO
			if (
				$platformDlid !== $currentDlid
				&&
				empty($currentDlid)
			)
			{
				$this->config['dlid'] = $platformDlid;
			}
		}
		catch (\Throwable $e)
		{
			// cannot use main logger here, as it needs system config available
			System\Log::libraryError('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
	}

	/**
	 *
	 * @return $this
	 */
	public function store()
	{
		$this->validate()
			 ->getStore()
			 ->put(
				 $this->scope,
				 array_diff_key(
					 $this->config,
					 array_flip($this->doNotStore)
				 )
			 );

		return $this;
	}

	/**
	 * Enforce any type specified for a given key.
	 *
	 * @param array|string $keys
	 * @param mixed        $newValue
	 * @param mixed        $previousValue
	 *
	 * @return mixed
	 */
	protected function enforceTypes($keys, $newValue, $previousValue)
	{
		if (empty($this->enforcedTypes) || !Wb\arrayIsSet($this->enforcedTypes, $keys))
		{
			return $newValue;
		}

		$enforcedType = Wb\arrayGet($this->enforcedTypes, $keys);
		if (!is_null($enforcedType))
		{
			$newValue = System\Convert::enforceType(
				$newValue,
				$enforcedType
		);
		}

		$this->config = Wb\arraySet(
			$this->config,
			$keys,
			$newValue
		);

		return $newValue;
	}

	/**
	 * Can trigger an action after a key is set.
	 *
	 * @param array|string $keys
	 * @param mixed        $newValue
	 * @param mixed        $previousValue
	 *
	 * @return Config
	 */
	protected function afterSet($keys, $newValue, $previousValue)
	{
		$newValue = $this->enforceTypes(
			$keys,
			$newValue,
			$previousValue
		);

		if ($newValue === $previousValue)
		{
			return $this;
		}

		if (
			'system' === $this->scope
			&&
			'prod' === WBLIB_Forsef_OP_MODE
		)
		{
			// store download id in update site extra query
			switch (true)
			{
				case 'dlid' === $keys:
					$this->factory
						->getA(Credentials::class)
						->update(
							'update',
							'dlid',
							$newValue
						);
					break;
			}
		}

		return $this;
	}

	/**
	 * Validate the configuration content. Used before storing.
	 *
	 * @return $this
	 */
	protected function validate()
	{
		// System config validation
		if ('system' == $this->scope)
		{
			// cronKey should not be empty. If empty, re-create it
			$cronKey = Wb\arrayGet(
				$this->config,
				'cronKey'
			);
			if (empty($cronKey))
			{
				$this->config['cronKey'] = System\Auth::shortId();
				$this->getStore()
					 ->put(
						 $this->scope,
						 $this->config
					 );
			}
		}

		return $this;
	}

	/**
	 * Get the underlying keystore, creating it as needed.
	 *
	 * @return Weeblr\Wblib\Forsef\Db\Keystore
	 */
	protected function getStore()
	{
		if (is_null($this->store))
		{
			// create it
			$this->store = $this->factory
				->getA(
					Db\Keystore::class,
					[
						'tableName' => $this->tablename,
						'format'    => Db\Keystore::FORMAT_JSON_ARRAY
					]
				);
		}

		return $this->store;
	}

	/**
	 * Loads hardcoded default configuration value from /config/{scope}.php.
	 *
	 * @return $this
	 */
	public function loadDefaults()
	{
		$file = FORSEF_APP_PATH . '/config/' . $this->scope . '.php';
		if (file_exists($file))
		{
			$defaults            = include $file;
			$this->defaults      = Wb\arrayGet($defaults, 'config', []);
			$this->doNotStore    = Wb\arrayGet($defaults, 'doNotStore', []);
			$this->persist       = Wb\arrayGet($defaults, 'persist', true);
			$this->enforcedTypes = Wb\arrayGet($defaults, 'enforcedTypes', []);
			$this->config        = empty($this->defaults) || !is_array($this->defaults) ? $this->config : $this->defaults;
		}

		return $this;
	}

	/**
	 * Encode config current content as json.
	 *
	 * @return false|string
	 */
	public function toJson()
	{
		return json_encode($this->config);
	}

	/**
	 * Returns raw config array.
	 *
	 * @return array
	 */
	public function toArray()
	{
		return $this->config;
	}

	/**
	 * Decode some json and replace/merge with current config.
	 *
	 * @param string $json
	 * @param bool   $merge
	 *
	 * @return $this
	 */
	public function fromJson($json, $merge = true)
	{
		$decoded = json_decode($json, true);
		if (!empty($decoded) && is_array($decoded))
		{
			$this->config = $merge
				? array_merge(
					$this->config,
					$decoded
				)
				: $decoded;
		}

		return $this;
	}
}