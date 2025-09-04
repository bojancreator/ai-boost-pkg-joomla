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
 *
 */

namespace Weeblr\Wblib\Forsef;

// no direct access
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

class Wblib
{
	/**
	 * @var \Weeblr\Wblib\Forsef\Factory Stores singleton of wbLib factory
	 */
	private $factory = null;

	/**
	 * Setup db tables and other items as needed
	 */
	public function activate()
	{
		// db setup
		$this->updateDbSchema();

		return $this;
	}

	/**
	 * Remove db tables and any leftovers
	 */
	public function uninstall()
	{
		$this->removeDbSchema();

		return $this;
	}

	/**
	 * Run time init
	 */
	public function boot()
	{
		$serverTimezone = @date_default_timezone_get();
		@date_default_timezone_set($serverTimezone);

		// path to layouts
		defined('WBLIB_Forsef_LAYOUTS_PATH') or define('WBLIB_Forsef_LAYOUTS_PATH', WBLIB_Forsef_ROOT_PATH . '/layouts');
		defined('WBLIB_Forsef_PACKAGES_PATH') or define('WBLIB_Forsef_PACKAGES_PATH', WBLIB_Forsef_ROOT_PATH . '/packages');
		// assets path from the PLUGIN root
		defined('WBLIB_Forsef_ASSETS_PATH') or define('WBLIB_Forsef_ASSETS_PATH', WBLIB_Forsef_ROOT_PATH . '/assets');

		// global flags
		defined('WBLIB_Forsef_LOG_EXCEPTIONS') or define('WBLIB_Forsef_LOG_EXCEPTIONS', true);

		// load code from Joomla Framework
		include_once WBLIB_Forsef_ROOT_PATH . '/vendor/joomla/string/src/phputf8/utf8.php';
		if (!function_exists('utf8_ltrim'))
		{
			include_once WBLIB_Forsef_ROOT_PATH . '/vendor/joomla/string/src/phputf8/trim.php';
		}
		if (!function_exists('utf8_ucfirst'))
		{
			include_once WBLIB_Forsef_ROOT_PATH . '/vendor/joomla/string/src/phputf8/ucfirst.php';
		}
		include_once WBLIB_Forsef_ROOT_PATH . '/vendor/joomla/string/src/StringHelper.php';
		include_once WBLIB_Forsef_ROOT_PATH . '/vendor/joomla/utilities/src/ArrayHelper.php';
		include_once WBLIB_Forsef_ROOT_PATH . '/vendor/joomla/registry/src/Registry.php';
		include_once WBLIB_Forsef_ROOT_PATH . '/vendor/joomla/registry/src/FormatInterface.php';
		include_once WBLIB_Forsef_ROOT_PATH . '/vendor/joomla/registry/src/Factory.php';
		include_once WBLIB_Forsef_ROOT_PATH . '/vendor/joomla/registry/src/AbstractRegistryFormat.php';
		include_once WBLIB_Forsef_ROOT_PATH . '/vendor/joomla/registry/src/Format/Ini.php';
		include_once WBLIB_Forsef_ROOT_PATH . '/vendor/joomla/registry/src/Format/Json.php';
		include_once WBLIB_Forsef_ROOT_PATH . '/vendor/joomla/registry/src/Format/Php.php';
		//include_once WBLIB_Forsef_ROOT_PATH . '/vendor/joomla/registry/src/Format/Xml.php';
		//include_once WBLIB_Forsef_ROOT_PATH . '/vendor/joomla/registry/src/Format/Yaml.php';
		include_once WBLIB_Forsef_ROOT_PATH . '/vendor/joomla/uri/src/UriInterface.php';
		include_once WBLIB_Forsef_ROOT_PATH . '/vendor/joomla/uri/src/AbstractUri.php';
		include_once WBLIB_Forsef_ROOT_PATH . '/vendor/joomla/uri/src/UriHelper.php';
		include_once WBLIB_Forsef_ROOT_PATH . '/vendor/joomla/uri/src/Uri.php';

		include_once WBLIB_Forsef_ROOT_PATH . '/vendor/matomo/network/src/IP.php';
		include_once WBLIB_Forsef_ROOT_PATH . '/vendor/matomo/network/src/IPUtils.php';
		include_once WBLIB_Forsef_ROOT_PATH . '/vendor/matomo/network/src/IPv4.php';
		include_once WBLIB_Forsef_ROOT_PATH . '/vendor/matomo/network/src/IPv6.php';

		// anything from composer.
		if (version_compare(PHP_VERSION, '7.1.0', 'ge'))
		{
			include_once WBLIB_Forsef_ROOT_PATH . '/vendor/autoload_static.php';
		}

		// load php shortcuts functions, not autoloaded
		$file = WBLIB_Forsef_PACKAGES_PATH . '/system/php_shortcuts.php';
		if (file_exists($file))
		{
			include_once $file;
		}
		else
		{
			throw new \RuntimeException('wbLib: cannot initialize php_shortcuts, php_shortcuts file is missing');
		}

		$factoryFile = WBLIB_Forsef_PACKAGES_PATH . '/factory.php';
		if (file_exists($factoryFile))
		{
			include_once $factoryFile;
			$this->factory = Factory::get();
		}
		if (empty($this->factory))
		{
			throw new \RuntimeException('wbLib: unable to build factory.');
		}

		// fetch the autoloader once, this will create and initialize it.
		$this->factory->getThe('autoloader');

		/**
		 * Filter wether the API system should be enabled.
		 *
		 * @api
		 * @package wbLib\filter\config
		 * @var wblib_enable_api
		 * @since   0.0.1
		 *
		 * @param   bool  $shouldEnableApi
		 *
		 * @return bool
		 */
		if ($this->factory->getThe('hook')->filter(
			'wblib_enable_api',
			true
		))
		{
			// Register our API handler with the appropriate hook.
			$this->factory->getThe('hook')->add(
				'wblib_api_process_request',
				array(
					$this->factory->getThe('api'),
					'handleRequest'
				)
			);
		}

		defined('WBLIB_VERSION') or define('WBLIB_VERSION', 'Forsef');

		return $this;
	}

	/**
	 * @TODO: move to own class
	 */
	private function updateDbSchema()
	{
		return $this;
	}

	/**
	 * @TODO: move to own class
	 */
	private function removeDbSchema()
	{

		return $this;
	}

	/**
	 * @TODO: move to own class
	 */
	private function _runQueries($queries)
	{

		return $this;
	}
}