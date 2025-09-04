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
 *
 * build 2.6.1.642
 */

namespace Weeblr\Forsef;

use Weeblr\Forsef\Platform;
use Weeblr\Forsef\Api;

use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\System;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Bootstrap app, called from higher up.
 */
class ForsefApp extends Base\App
{
	protected $id        = 'forsef';
	protected $namespace = '\Weeblr\Forsef';

	public function __construct($options = [])
	{
		parent::__construct($options);

		// include 4SEF factory to extend wbLib factory.
		include_once __DIR__ . '/factory.php';

		// register local platform code with the autoloader
		// for instance J3 or J4 specific code
		$this->factory->getThe('autoloader')->registerNamespace(
			'\Weeblr\Forsef\Platform',
			FORSEF_APP_PLATFORM_PATH . '/platform'
		);

		// initialize the app timezone
		System\Date::setTimezoneName(
			$this->platform->getTimezone()
		);

		// register the API handler with the wbLib API manager.
		$registered = $this->factory
			->getA(Api\Handler::class)
			->register();
		if (!$registered)
		{
			$this->enabled = false;
			$msg           = 'Failed registering 4SEF API with wbLib, aborting.';
			$this->factory->getThe('forsef.logger')->error($msg);
			throw new \RuntimeException($msg);
		}

		// load the api hooks, if any
		$apiHooks = $this->factory->getA(Api\Hooks::class);
		if (!empty($apiHooks))
		{
			$apiHooks->add();
		}

		// load the local platform hooks, if any
		$platformHooks = $this->factory->getA(Platform\Hooks::class);
		if (!empty($platformHooks))
		{
			$platformHooks->add();
		}

		// load the application hooks, if any
		$appHooks = $this->factory->getA(Hooks::class);
		if (!empty($appHooks))
		{
			$appHooks->add();
		}

		// Create the messages manager, which will register its API endpoints
		$this->factory->getThe('forsef.msgManager');

		// register a version object
		$this->setVersionInfo();

	}

	/**
	 * @param array $data
	 */
	public function renderAdmin($data)
	{
		$controller = new Controller\Admin();
		$controller->render(
			$data
		);
	}

	/**
	 * Builds a version information object and store it with wbLib.
	 *
	 * @throws \Exception
	 */
	private function setVersionInfo()
	{
		static $version = null;

		if (is_null($version))
		{
			if (false === strpos('2.6.2.644', '_version_'))
			{
				$versionInfo = [
					'package'           => '4SEF',
					'platform'          => '@build_platform_build@',
					'package_title'     => 'Project: 4SEF',
					'version'           => '2.6.2.644',
					'version_full'      => '2.6.2.644',
					'date'              => '2025-06-02',
					'license'           => 'GNU General Public License version 3; see LICENSE.md',
					'copyright'         => 'Copyright Weeblr llc - 2022 -2025',
					'author'            => 'Yannick Gaultier - Weeblr llc',
					'url'               => 'https://weeblr.com/',
					'edition'           => 'full',
					'documentation_url' => '@build_documentation_url_build@',
					'php'               => [
						'min' => '7.1.0',
						'max' => ''
					],
					'platform_version'  => [
						'min' => '3.9.0',
						'max' => '6.0'
					]
				];
			}
			else
			{
				$versionInfo = [
					'package'           => 'forsef',
					'platform'          => 'joomla',
					'package_title'     => 'ForSEF',
					'version'           => '5.0.0',
					'version_full'      => '5.0.0.1234',
					'date'              => '2020-05-25',
					'license'           => 'GPL Version 3',
					'copyright'         => '(c) Weeblr,llc - 2021',
					'author'            => 'Weeblr',
					'url'               => 'https://weeblr.com',
					'edition'           => 'full',
					'documentation_url' => 'https://weeblr.com/9k',
					'php'               => [
						'min' => '7.1',
						'max' => '8'
					],
					'platform_version'  => [
						'min' => '3.9',
						'max' => '5'
					]
				];
			}

			$version = new System\Version($versionInfo);
		}
	}
}
