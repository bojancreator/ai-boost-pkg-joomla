<?php
/**
 * Project: 4SEO
 *
 * @package          4SEO
 * @copyright        Copyright Weeblr llc - 2020 - 2026
 * @author           Yannick Gaultier - Weeblr llc
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          6.10.1.2660
 * @date        2026-01-30
 */

namespace Weeblr\Forseo\Model;

use Weeblr\Forseo\Data;
use Weeblr\Forseo\Helper;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Db;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Config extends System\Config
{
	/**
	 * @var string Name of database table to store this config.
	 */
	protected $tablename = '#__forseo_config';

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
		) {
			$this->preload()
				 ->load();
		}

		/**
		 * Filter configuration right after creating it.
		 *
		 * @api     forseo
		 * @package 4SEO\config
		 * @var forseo_config
		 *
		 * @param array  $config
		 * @param string $scope
		 *
		 * @return void
		 *
		 * @since   1.0.0
		 */
		$this->config = $this->factory
			->getThe('hook')
			->filter(
				'forseo_config',
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
				->getThe('db')
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

		$this->setIsSitePublic();
		$this->setCanonicalRoot();
		$this->setDownloadId();

		/**
		 * Filter configuration right after loading it from the database.
		 *
		 * @api     forseo
		 * @package 4SEO\config
		 * @var forseo_config_loaded
		 *
		 * @param array  $config
		 * @param string $scope
		 *
		 * @return void
		 *
		 * @since   1.0.0
		 */
		$this->config = $this->factory
			->getThe('hook')
			->filter(
				'forseo_config_loaded',
				$this->config,
				$this->scope
			);

		return $this->validate();
	}

	private function setIsSitePublic()
	{
		if (
			'system' != $this->scope
			||
			$this->isTruthy('configWizardCompleted')
		) {
			return;
		}

		// If wizard has not been run, we try to find out if
		// the site is public based on other settings.
		try
		{
			$isPublic      = false;
			$sitemapConfig = $this->factory->getThis('forseo.config', 'sitemaps');
			if (
				$sitemapConfig->isTruthy('addToRobotsTxt')
				||
				$sitemapConfig->isTruthy('searchEnginesPingEnabled')
			) {
				$isPublic = true;
			}
			$pagesConfig = $this->factory->getThis('forseo.config', 'pages');
			$pagesConfig->set(
				'siteIsPublic',
				$isPublic
			)->store();
		}
		catch (\Throwable $e)
		{
			// cannot use forseo logger here, as it needs system config available
			System\Log::libraryError('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
	}

	private function setDownloadId()
	{
		if ('prod' !== WBLIB_Forseo_OP_MODE)
		{
			return;
		}

		if (
			$this->platform->majorVersion() < 4
			||
			'system' != $this->scope
		) {
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
			) {
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
	 * Ensures we always have a default value for the canonical root URL.
	 */
	private function setCanonicalRoot()
	{
		if ('pages' != $this->scope)
		{
			return;
		}

		try
		{
			if (!Wb\startsWith(
				$this->get('canonicalRootUrl'),
				[
					'http://',
					'https://'
				]
			))
			{
				$this->set(
					'canonicalRootUrl',
					$this->platform->getCanonicalRoot()
				);
			}

			System\Route::setCanonicalRoot(
				$this->get('canonicalRootUrl')
			);
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s', $e->getFile(), $e->getLine(), $e->getMessage());
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
			'prod' === WBLIB_Forseo_OP_MODE
		) {
			// store download id in update site extra query
			switch (true)
			{
				case 'dlid' === $keys:

					// Debug info for disapearing updates key on Joomla 4
					$logger = $this->factory->getThe('forseo.logger');
					$logger->custom('update_key_debug', 'About to update Joomla dlid with ' . print_r($newValue, true) . ' from ' . print_r($previousValue, true));
					$request = "URL: " . $this->platform->getCurrentUrl();
					$input   = $this->platform->getHttpInput();
					$request .= "\nMethod: " . $input->getMethod();
					$request .= "\nGET:   ------------------------\n" . print_r($input->get->getArray(), true);
					$request .= "\nPOST:   ------------------------\n" . print_r($input->post->getArray(), true);

					$logger->custom('update_key_debug', "Request:   ------------------------\n" . $request);
					$e         = new \Exception();
					$backtrace = $e->getTraceAsString();
					$logger->custom('update_key_debug', "Backtrace: ------------------------\n" . $backtrace);

					$this->factory
						->getA(Credentials::class)
						->update(
							'update',
							'dlid',
							trim($newValue)
						);
					break;
			}
		}

		if ('sitemaps' === $this->scope)
		{
			switch (true)
			{
				case 'enabled' === $keys:
				case 'addToRobotsTxt' === $keys:
					$this->factory
						->getA(Helper\Sitemaps::class)
						->updateRobotsTxt(
							[
								Data\Sitemap::CONTENT
							]
						);
					break;
			}
		}

		if ('pages' === $this->scope)
		{
			try
			{
				switch (true)
				{
					case 'siteIsPublic' === $keys:
						$sitemapConfig = $this->factory->getThis('forseo.config', 'sitemaps');
						if (empty($newValue))
						{
							// site is NOT public
							$sitemapConfig
								->set('addToRobotsTxt', false)
								->set('searchEnginesPingEnabled', [])
								->store();
						}
						if (!empty($newValue))
						{
							// site is public now
							$sitemapConfig
								->set('addToRobotsTxt', true)
								->set(
									'searchEnginesPingEnabled',
									[
										'google',
										'bing'
									]
								)->store();
						}
				}
			}
			catch (\Throwable $e)
			{
				$this->factory->getThe('forseo.logger')->error('%s::%d %s', $e->getFile(), $e->getLine(), $e->getMessage());
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

		// Pages config validation
		if ('pages' == $this->scope)
		{
			$collections = [
				'collectionExclusions',
				'collectionInclusions',
				'collectionDomains',
				'perfDataExclusions',
				'perfDataInclusions'
			];
			foreach ($collections as $collection)
			{
				if (!is_array($this->config[$collection]))
				{
					$this->config[$collection] = [];
				}
			}

			// domain exclusion specification should only have domains spec
			$domainSpecs = [];
			foreach ($this->config['collectionDomains'] as $domainSpec)
			{
				$domainSpec = str_replace(' ', '%22', $domainSpec);
				if (!System\Route::isFullyQualified($domainSpec))
				{
					$domainSpec = '//' . $domainSpec;
				}
				$host          = parse_url(
					$domainSpec,
					PHP_URL_HOST
				);
				$domainSpecs[] = empty($host) ? '' : $host;
			}
			$this->config['collectionDomains'] = $domainSpecs;
		}

		// Sitemaps config validation
		if ('sitemaps' == $this->scope)
		{
			$collections = [
				'exclusions',
				'inclusions',
				'imagesExclusions',
				'imagesInclusions'
			];
			foreach ($collections as $collection)
			{
				if (!is_array($this->config[$collection]))
				{
					$this->config[$collection] = [];
				}
			}
			$domainSpecs = [];
			foreach ($this->config['imagesDomainsExclusions'] as $domainSpec)
			{
				$domainSpec = str_replace(' ', '%22', $domainSpec);
				if (!System\Route::isFullyQualified($domainSpec))
				{
					$domainSpec = '//' . $domainSpec;
				}
				$host          = parse_url(
					$domainSpec,
					PHP_URL_HOST
				);
				$domainSpecs[] = empty($host) ? '' : $host;
			}
			$this->config['imagesDomainsExclusions'] = $domainSpecs;
		}

		return $this;
	}

	/**
	 * Get the underlying keystore, creating it as needed.
	 *
	 * @return Weeblr\Wblib\Forseo\Db\Keystore
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
		$file = FORSEO_APP_PATH . '/config/' . $this->scope . '.php';
		if (file_exists($file))
		{
			$defaults            = include $file;
			$this->defaults      = Wb\arrayGet($defaults, 'config', []);
			$this->doNotStore    = Wb\arrayGet($defaults, 'doNotStore', []);
			$this->persist       = Wb\arrayGet($defaults, 'persist', true);
			$this->enforcedTypes = Wb\arrayGet($defaults, 'enforcedTypes', []);
			$this->config        = empty($this->defaults) || !is_array($this->defaults)
				? $this->config
				: $this->defaults;
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