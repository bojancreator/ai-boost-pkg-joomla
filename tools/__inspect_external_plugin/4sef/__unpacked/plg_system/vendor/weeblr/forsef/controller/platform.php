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

namespace Weeblr\Forsef\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Router\Router;
use Joomla\CMS\Router\SiteRouter;

use Weeblr\Forsef\Data;
use Weeblr\Forsef\Model;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Base;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Interacts with the platform to inject routing and parsing rules.
 *
 */
class Platform extends Base\Base
{
	/**
	 * @var System\Logger Convenience logger instance.
	 */
	private $logger = null;

	/**
	 * @var Model\Config
	 */
	private $appConfig;

	/**
	 * @var CMSApplication
	 */
	private $app;

	/**
	 * @var Data\RequestInfo Convenience instance of the current request information.
	 */
	private $requestInfo;

	/**
	 * @var bool Whether routing is enabled for the current request.
	 */
	public $enabled;

	/**
	 * @param array $options Can inject custom factory and platform.
	 */
	public function __construct($options = [])
	{
		parent::__construct();

		$this->logger    = $this->factory->getThe('forsef.logger');
		$this->appConfig = $this->factory->getThis('forsef.config', 'app');
	}

	/**
	 * Getter for 4SEF router enabled state.
	 *
	 * @return bool
	 */
	public function isEnabled()
	{
		if (is_null($this->enabled))
		{
			$this->enabled = $this->factory
				->getThis('forsef.config', 'routing')
				->isTruthy('enabled');
		}

		return $this->enabled;
	}

	/**
	 * Configure 4SEf interaction with Joomla SEF and other needed systems.
	 */
	public function configure()
	{
		try
		{
			if (!defined('4SEF_IS_INSTALLED'))
			{
				define('4SEF_IS_INSTALLED', 1);
			}

			$this->app = Factory::getApplication();

			if ($this->isEnabled())
			{
				$this->attachToRouter()
					 ->reconfigurePlatform()
					 ->doStartup();

				if (!defined('4SEF_IS_RUNNING'))
				{
					define('4SEF_IS_RUNNING', 1);
				}
			}
		}
		catch (\Throwable $e)
		{
			$this->logger->error(__METHOD__ . ', %s::%d %s %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
	}

	/**
	 * Instantiate a router and attach its parse and build rules to the Joomla
	 * router if in an appropriate context.
	 *
	 * @return $this
	 */
	private function attachToRouter()
	{
		// Always attach to instances of the frontend router.
		$joomlaRouter = $this->platform->getRouter('site');
		if (!$joomlaRouter instanceof SiteRouter)
		{
			$this->enabled = false;

			return $this;
		}

		$forsefRouter = $this->factory->getThe('forsef.router');
		$forsefRouter->filterParseRules($joomlaRouter);

		$joomlaRouter->attachParseRule(
			[
				$forsefRouter,
				'preprocessParseRule'
			],
			Router::PROCESS_BEFORE
		);

		$joomlaRouter->attachParseRule(
			[
				$forsefRouter,
				'parseRule'
			]
		);

		$joomlaRouter->attachBuildRule(
			[
				$forsefRouter,
				'preProcessBuildRule'
			],
			Router::PROCESS_BEFORE
		);

		$joomlaRouter->attachBuildRule(
			[
				$forsefRouter,
				'buildRule'
			]
		);

		$joomlaRouter->attachBuildRule(
			[
				$forsefRouter,
				'postProcessBuildRule'
			],
			Router::PROCESS_AFTER
		);

		return $this;
	}

	/**
	 * Possibly configure application level options that we may require
	 * or will improve operation.
	 *
	 * @return $this
	 */
	private function reconfigurePlatform()
	{
		$platformVersion = $this->platform->majorVersion();
		if ($platformVersion < 4)
		{
			// pretend SEF is on, mostly for Joomla SEF plugin to work
			// as it checks directly 'sef' value in config, instead of
			// using $router->getMode()
			$this->platform->getConfig()->set('sef', 1);
			// On Joomla 3, SiteRouter does not use config from Application, must also set this.
			\JFactory::getConfig()->set('sef', 1);
			// and finally this to be sure the instantiated router use the new config.
			$this->platform->getRouter('site')->setMode(JROUTER_MODE_SEF);
		}

		if ($platformVersion >= 4)
		{
			// pretend SEF is on, mostly for Joomla SEF plugin to work
			// as it checks directly 'sef' value in config, instead of
			// using $router->getMode()
			$this->platform->getConfig()->set('sef', true);
		}

		return $this;
	}

	/**
	 * Router level startup operations.
	 *
	 * @return $this
	 */
	private function doStartup()
	{
//		$platformVersion = $this->platform->majorVersion();

//		if ($platformVersion < 4)
//		{
//		}
//
//		if ($platformVersion >= 4)
//		{
//		}

		return $this;
	}
}
