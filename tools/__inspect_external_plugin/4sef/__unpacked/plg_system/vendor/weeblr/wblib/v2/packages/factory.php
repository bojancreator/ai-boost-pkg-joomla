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

namespace Weeblr\Wblib\Forsef;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Api;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Db;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * Initialization of the plugin
 */
class Factory
{
	/**
	 * @var array List of supported platforms
	 */
	private $platforms = array(
		'joomla',
		'wordpress'
	);

	/**
	 * Stores built objects
	 *
	 * @var array
	 */
	static protected $objects = array();

	/*
	 * Instance created by static facades
	 */
	static protected $instances = array();

	/**
	 * @var Hook Holds hook instance for the app.
	 */
	static protected $hook = null;

	/**
	 * @var array List of native namespaces for automatic autoloader registration.
	 */
	static protected $nativeNamespaces = array(
		'ai',
		'api',
		'db',
		'base',
		'customerror',
		'fs',
		'html',
		'integrations',
		'messages',
		'notifications',
		'mvc',
		'platform',
		'seo',
		'system',
		'Wb',
		'Weeblr'
	);

	/**
	 * Instantiate a Hook, will be used to hold all hooks on the system.
	 */
	protected function __construct()
	{
		$hookFile = WBLIB_Forsef_PACKAGES_PATH . '/system/hook.php';
		if (file_exists($hookFile))
		{
			include_once $hookFile;
		}
		else
		{
			Wb\throwException(new \RuntimeException('wbLib: unable to build hook system, file missing.'));
		}
		self::$hook = is_null(self::$hook) ? new System\Hook : self::$hook;
	}

	/**
	 * Create a new instance of an object of the requested class
	 * passing in optional array of parameters to its constructor
	 * Only works for array of params, or a single params
	 *
	 * @param   string  $class
	 * @param   mixed   $args
	 *
	 * @return mixed
	 */
	public function getA($class, $args = null)
	{
		return self::get()->getObject('a', $class, $args);
	}

	/**
	 * Singleton method, can pass parameters to the constructor
	 *
	 * @param   string  $class
	 * @param   mixed   $parameters
	 *
	 * @return mixed
	 */
	public function getThe($class, $args = null)
	{
		return self::get()->getObject('the', $class, $args);
	}

	/**
	 * Multiton method, can pass parameters to the constructor
	 *
	 * @param   string  $class
	 * @param   string  $key
	 * @param   null    $args
	 *
	 * @return mixed
	 */
	public function getThis($class, $key, $args = null)
	{
		return self::get()->getObject('this', $class, $args, $key);
	}

	/**
	 * Builder for factory instance.
	 *
	 * @return WblFactory
	 */
	public static function get()
	{
		if (empty(self::$instances['__default__']))
		{
			self::$instances['__default__'] = new static();
		}

		return self::$instances['__default__'];
	}

	/**
	 * Ensure method used to request an object is allowed by design.
	 *
	 * @param   string  $class
	 * @param   string  $method
	 * @param   array   $allowedMethods
	 */
	public function enforceMethodAllowed($class, $method, $allowedMethods = array('a', 'the', 'this'))
	{
		if (
			empty($allowedMethods)
			||
			!in_array(
				$method,
				$allowedMethods
			)
		)
		{
			$this->invalidMethod(
				$method,
				$class
			);
		}
	}

	/**
	 * Ensure method used to request an object is a singleton.
	 *
	 * @param   string  $class
	 * @param   string  $method
	 */
	public function enforceSingleton($class, $method)
	{
		$this->enforceMethodAllowed(
			$class,
			$method,
			array('the')
		);
	}

	/**
	 * Ensure method used to request an object is a multiton.
	 *
	 * @param   string  $class
	 * @param   string  $method
	 */
	public function enforceMultiton($class, $method)
	{
		$this->enforceMethodAllowed(
			$class,
			$method,
			array('this')
		);
	}

	/**
	 * Manages storage of objects
	 *
	 * @param           $method
	 * @param           $class
	 * @param   null    $args
	 * @param   string  $key
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	protected function getObject($method, $class, $args = null, $key = '')
	{
		// then instantiate object
		switch ($method)
		{
			// return new object at each call
			case 'a':
				$object = $this->buildObject($method, $class, $args, $key);
				break;

			// singleton
			case 'the':
				if (empty(self::$objects[$class]))
				{
					self::$objects[$class] = $this->buildObject($method, $class, $args, $key);
				}

				$object = self::$objects[$class];
				break;

			// multiton
			case 'this':
				if (empty($key))
				{
					throw new \Exception('wbLib: no key specified while using method ' . $method . ', requesting object ' . $class);
				}

				$signature = $class . '.' . $key;
				if (empty(self::$objects[$signature]))
				{
					self::$objects[$signature] = $this->buildObject($method, $class, $args, $key);
				}

				$object = self::$objects[$signature];
				break;

			// invalid method
			default:
				$this->invalidMethod($method, $class, $args, $key);
		}

		return $object;
	}

	/**
	 * Build an object, with optional arguments
	 *
	 * @param           $method
	 * @param           $class
	 * @param   null    $args
	 *
	 * @param   string  $key
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	protected function buildObject($method, $class, $args = null, $key = '')
	{
		switch ($class)
		{
			// Custom classes or descriptor handling can be extended
			// using the wblib_factory_build_object_filter

			// manages links to assets
			case 'platform':
				$this->enforceSingleton($class, $method);

				return $this->buildPlatform();
			case 'db':
				$this->enforceSingleton($class, $method);

				return new Db\Helper;
			case 'autoloader':
				$this->enforceSingleton($class, $method);

				return $this->buildAutoloader();
			case 'hook':
				$this->enforceSingleton($class, $method);

				return self::$hook;
			case 'api':
				$this->enforceSingleton($class, $method);

				return new Api\Api($args);
			case 'app':
				$this->enforceMultiton($class, $method);

				return $this->buildApp($args);

			// simply build an object of a class
			default:

				// extension mechanism
				$object = self::$hook->filter(
					'wblib_factory_build_object_filter',
					null,
					$this, $method, $class, $args, $key
				);
				if (!is_null($object))
				{
					return $object;
				}

				// Default handling
				if (class_exists($class))
				{
					if (is_null($args))
					{
						return new $class();
					}
					else
					{
						return new $class($args);
					}
				}
				break;
		}
	}

	/**
	 * Throw an invalid method exception
	 *
	 * @param           $method
	 * @param           $class
	 * @param   null    $args
	 * @param   string  $key
	 *
	 * @throws \RuntimeException
	 */
	protected function invalidMethod($method, $class, $args = null, $key = '')
	{
		throw new \RuntimeException('wbLib: invalid method ' . $method . ' when requesting object ' . $class);
	}

	/**
	 * Builds the autoloader instance and register native namespaces.
	 *
	 * @return System\Autoloader
	 * @throws \Exception
	 */
	protected function buildAutoloader()
	{
		$autoloaderFile = WBLIB_Forsef_PACKAGES_PATH . '/system/autoloader.php';
		if (file_exists($autoloaderFile))
		{
			include_once $autoloaderFile;
			$autoloader = System\Autoloader::getThe()
				->register();

			foreach (self::$nativeNamespaces as $namespace)
			{
				$autoloader->registerNameSpace(
					'Weeblr\Wblib\Forsef\\' . ucfirst($namespace),
					join('/', array_filter(
						array(
							WBLIB_Forsef_PACKAGES_PATH,
							$namespace
						)
					))
				);
			}
		}
		else
		{
			throw new \RuntimeException('wbLib: cannot initialize autoloader, autoloader file is missing');
		}

		return $autoloader;
	}

	/**
	 * Detects the current platform and builds corresponding
	 * support object.
	 */
	protected function buildPlatform()
	{
		foreach ($this->platforms as $platformName)
		{
			$className = 'Weeblr\Wblib\Forsef\Platform\\' . ucfirst($platformName) . 'Platform';
			$platform  = new $className;
			if ($platform->detect())
			{
				$platform->boot();

				return $platform;
			}
		}

		// don't go unnoticed
		throw new \Exception('wblib: Unsupported platform.');
	}

	/**
	 * Build and initialize an application.
	 *
	 * @return Object
	 * @throws \Exception
	 */
	protected function buildApp($args)
	{
		try
		{
			// init application and render
			$appFile = Wb\arrayGet($args, 'rootpath') . '/' . Wb\arrayGet($args, 'id') . '.php';
			if (!file_exists($appFile))
			{
				return;
			}

			include_once $appFile;

			$className = Wb\arrayGet($args, 'namespace') . '\\' . ucfirst(Wb\arrayGet($args, 'id')) . 'App';

			return new $className($args);
		}
		catch (\Throwable $e)
		{
			System\Log::libraryError('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
		catch (\Exception $e)
		{
			System\Log::libraryError('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
	}
}
