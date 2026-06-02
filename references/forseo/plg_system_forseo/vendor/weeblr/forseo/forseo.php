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
 *
 * build 6.10.1.2660
 */

namespace Weeblr\Forseo;

use Weeblr\Forseo\Platform;
use Weeblr\Forseo\Api;

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Bootstrap app, called from higher up.
 */
class ForseoApp extends Base\App
{
	protected $id        = 'forseo';
	protected $namespace = '\Weeblr\Forseo';

	public function __construct($options = [])
	{
		parent::__construct($options);

		// include ForSEO factory to extend wbLib factory.
		include_once __DIR__ . '/factory.php';

		// register local platform code with the autoloader
		// for instance J3 or J4 specific code
		$this->factory->getThe('autoloader')->registerNamespace(
			'\Weeblr\Forseo\Platform',
			FORSEO_APP_PLATFORM_PATH . '/platform'
		);

		// Add a platform error interceptor, allows logging them
		// other optional actions.
		$this->factory->getThe(Controller\Error::class)
					  ->init();

		// initialize the app timezone
		System\Date::setTimezoneName(
			$this->platform->getTimezone()
		);

		// register the API handler with the wbLib API manager.
		$currentRequest = $this->platform->getCurrentUrl();
		if (Wb\contains($currentRequest, '_wblapi'))
		{
			$registered = $this->factory
				->getA(Api\Handler::class)
				->register();
			if (!$registered)
			{
				$this->enabled = false;
				$msg           = 'Failed registering ForSEO API with wbLib, aborting.';
				$this->factory->getThe('forseo.logger')->error($msg);
				throw new \RuntimeException($msg);
			}

			// load the api hooks, if any
			$apiHooks = $this->factory->getA(Api\Hooks::class);
			if (!empty($apiHooks))
			{
				$apiHooks->add();
			}
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
		$this->factory->getThe('forseo.msgManager');

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
			if (false === strpos('6.10.1.2660', '_version_'))
			{
				$versionInfo = [
					'package'           => '4SEO',
					'platform'          => '@build_platform_build@',
					'package_title'     => 'Project: 4SEO',
					'version'           => '6.10.1.2660',
					'version_full'      => '6.10.1.2660',
					'date'              => '2026-01-30',
					'license'           => 'GNU General Public License version 3; see LICENSE.md',
					'copyright'         => 'Copyright Weeblr llc - 2020 - 2026',
					'author'            => 'Yannick Gaultier - Weeblr llc',
					'url'               => 'https://weeblr.com/',
					'edition'           => 'full',
					'documentation_url' => '@build_documentation_url_build@',
					'php'               => [
						'min' => '7.2.5',
						'max' => ''
					],
					'platform_version'  => [
						'min' => '3.9.0',
						'max' => '7.0'
					]
				];
			}
			else
			{
				$versionInfo = [
					'package'           => 'forseo',
					'platform'          => 'joomla',
					'package_title'     => 'ForSEO',
					'version'           => '5.0.0',
					'version_full'      => '5.0.0.1234',
					'date'              => '2020-05-25',
					'license'           => 'GPL Version 2',
					'copyright'         => '(c) Weeblr,llc - 2020',
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
