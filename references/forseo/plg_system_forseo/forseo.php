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

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Event\Model;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Factory as wblFactory;
use Weeblr\Wblib\Forseo\System;

// no direct access
defined('_JEXEC') || die();

/**
 * ForSEO for Joomla system plugin.
 */
class plgSystemForseo extends Plugin\CMSPlugin
{
	/**
	 * The Application object
	 *
	 * @var    JApplicationSite
	 * @since  3.9.0
	 */
	protected $app;

	/**
	 * Load the language file on instantiation.
	 *
	 * @var    boolean
	 * @since  3.9.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * @var bool Flag configured by user to force running onAfterRoute handler before all others (only work on J4)
	 */
	private $runOnAfterRouteFirst = true;

	/**
	 * Mimic Base\Base and load up wbLib factory and platform objects.
	 * Hook is added as well as this plugin is essentially relaying
	 * Joomla events through wbLib hooks.
	 */
	private $factory  = null;
	private $platform = null;
	private $hook     = null;

	/**
	 * @var Instance of the 4SEO app.
	 */
	private $forseo = null;

	public function __construct($subject, array $config = array())
	{
		parent::__construct($subject, $config);

		if (file_exists(__DIR__ . '/app_defines.php'))
		{
			include_once(__DIR__ . '/app_defines.php');
		}

		$this->cleanCdnCacheBuster();

		defined('WBLIB_EXEC') or define('WBLIB_EXEC', true);
		if (file_exists(__DIR__ . '/boot.php'))
		{
			include_once __DIR__ . '/boot.php';
		}
		else
		{
			return;
		}

		$this->factory  = wblFactory::get();
		$this->platform = $this->factory->getThe('platform');
		$this->hook     = $this->factory->getThe('hook');
		if (empty($this->app))
		{
			$this->app = \is_callable([$this, 'getApplication'])
				? $this->getApplication()
				: Factory::getApplication();
		}
	}

	/**
	 * Clear from all Joomla internal data structures, the optional query var added
	 * to bypass CDN caching on crawler requests.
	 */
	private function cleanCdnCacheBuster()
	{
		if (!isset($_GET[FORSEO_CRAWLER_CDN_BUST_VAR]))
		{
			return;
		}
		$buster = FORSEO_CRAWLER_CDN_BUST_VAR . '=' . $_GET[FORSEO_CRAWLER_CDN_BUST_VAR];
		unset($_GET[FORSEO_CRAWLER_CDN_BUST_VAR]);
		unset($_REQUEST[FORSEO_CRAWLER_CDN_BUST_VAR]);
		$_SERVER['REQUEST_URI']  = str_replace(['&' . $buster, '?' . $buster], '', $_SERVER['REQUEST_URI']);
		$_SERVER['QUERY_STRING'] = str_replace(['&' . $buster, '?' . $buster, $buster], '', $_SERVER['QUERY_STRING']);
	}

	/**
	 * Earliest event: disable full page caching if this is a crawler
	 * request. Shortcut: we don't check the value of the header, only
	 * that it exists.
	 *
	 * @return bool
	 */
	public function onPageCacheSetCaching()
	{
		$headerNames = [
			FORSEO_CRAWLER_SEC_HEADER,
			FORSEO_CRAWLER_DEBUG_HEADER
		];

		foreach ($headerNames as $headerName)
		{
			$headerValue = System\Http::getRequestHeader($headerName);
			if (!empty($headerValue))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Next earliest event, create application.
	 */
	public function onAfterInitialise()
	{
		// something went wrong during init.
		if (!defined('FORSEO_APP_PATH'))
		{
			return;
		}

		// setup some basic path and initialize the app.
		// This will also register the app API handler
		// which needs to be done as early as possible.
		$this->forseo = $this->factory->getThis(
			'app',
			'forseo',
			[
				'id'        => 'forseo',
				'namespace' => '\Weeblr\Forseo',
				'rootpath'  => FORSEO_APP_PATH
			]
		);

		if (!defined('4SEO_IS_RUNNING'))
		{
			define('4SEO_IS_RUNNING', 1);
		}

		/**
		 * Hook to run the registered onAfterInitialise handlers.
		 *
		 * @api     forseo
		 * @package 4SEO\action\events
		 * @var forseo_onAfterInitialise
		 * @since   1.0.0
		 *
		 */
		$this->hook->run(
			'forseo_onAfterInitialise'
		);

		/**
		 * Decide whether to run the onAfterRoute handler
		 * before other plugins handlers have been run. Only has
		 * any effect on Joomla 4. Joomla 3 does not have the required
		 * priority system.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\route
		 * @var forseo_run_on_after_route_first
		 *
		 * @param bool $runFirst
		 *
		 * @since   4.9.0
		 */
		$this->runOnAfterRouteFirst = $this->hook->filter(
			'forseo_run_on_after_route_first',
			$this->runOnAfterRouteFirst
		);

		if (
			$this->platform->majorVersion() > 3
			&&
			$this->runOnAfterRouteFirst
		) {
			// On j4+, events handlers have priorities, and we can have
			// an onAfterRoute handler that runs before the built-in ones.
			// Can be used to override the Cache plugin for instance.
			$this->platform->registerEventHandler(
				'onAfterRoute',
				[
					$this,
					'onAfterRouteFirst'
				],
				9999
			);
		}
	}


	/**
	 * Handle the onPrivacyCollectAdminCapabilities event.
	 *
	 * @return forseo_onPrivacyCollectAdminCapabilities|void
	 */
	public function onPrivacyCollectAdminCapabilities()
	{
		// something went wrong during init.
		if (!defined('FORSEO_APP_PATH'))
		{
			return;
		}

		/**
		 * Hook to run the registered onAfterInitialise handlers.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\events
		 * @var forseo_onPrivacyCollectAdminCapabilities
		 * @since   1.0.0
		 *
		 */
		return $this->hook->filter(
			'forseo_onPrivacyCollectAdminCapabilities',
			[
				'4SEO' => [
					Text::_('PLG_SYSTEM_FORSEO_PRIVACY_CAPABILITIES')
				]
			]
		);
	}

	/**
	 * onAfterRoute event handler that has a good chance to run before any other one
	 * on Joomla 4 only.
	 *
	 * @return void
	 */
	public function onAfterRouteFirst()
	{
		$this->doOnAfterRoute();
	}

	/**
	 * Hook into wbLib after initial parsing's been done by Joomla.
	 */
	public function onAfterRoute()
	{
		if (
			$this->platform->majorVersion() < 4
			||
			!$this->runOnAfterRouteFirst
		) {
			$this->doOnAfterRoute();
		}
	}

	/**
	 * Hook into wbLib after initial parsing's been done by Joomla.
	 */
	public function doOnAfterRoute()
	{
		// something went wrong during init.
		if (!defined('FORSEO_APP_PATH'))
		{
			return;
		}

		/**
		 * Shared event, can be handled by anyone, even if our apps
		 * hook system is not initialized yet.
		 */
		$this->platform->triggerEvent('onForseoBeforeApiProcessing');

		/**
		 * Hook to run the registered API handlers.
		 *
		 * @api     forseo
		 * @package wbLib\action\api
		 * @var wblib_api_process_request
		 * @since   1.0.0
		 *
		 */
		$this->hook->run(
			'wblib_api_process_request'
		);

		// Allow 3rd-party to provide extensions or overrides to 4SEO. They will have to hook into the proper
		// filters when their plugin is instantiated. Best look at native plugins addHook() method.
		Plugin\PluginHelper::importPlugin('forseo');

		/**
		 * Hook to run the registered onAfterRoute handlers.
		 *
		 * @api     forseo
		 * @package 4SEO\action\events
		 * @var forseo_onAfterRoute
		 * @since   1.0.0
		 *
		 */
		$this->hook->run(
			'forseo_onAfterRoute'
		);

		// Possibly register onContentPrepare handler as late as possible.
		// Our handler is from a system plugin, meaning it will always
		// be registered and called before any content plugin based handler.
		// We can override that in Joomla 4, by setting a priority.
		// Did not find any way to do it on Joomla 3 (aside from re-ordering the
		// Dispatcher internal storage structure, don't want to go there).
		$onContentPrepareCompleteHandler = function ()
		{
			$arguments = func_get_args();
			if (
				1 === count($arguments)
				&&
				$arguments[0] instanceof \Joomla\Event\Event)
			{
				$arguments = $arguments[0]->getArguments();
			}
			$this->doOnContentPrepareComplete(...\array_values($arguments));
		};

		/**
		 * Decide whether to run the onContentPrepareComplete handler
		 * after all content plugins handlers have been run. Only has
		 * any effect on Joomla 4. Joomla 3 does not have the required
		 * priority system.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\content
		 * @var forseo_run_on_content_prepare_last
		 *
		 * @param bool $runLast
		 *
		 * @since   4.5.0
		 */
		$runLast = $this->hook->filter(
			'forseo_run_on_content_prepare_last',
			true
		);

		$this->platform->registerEventHandler(
			'onContentPrepare',
			$onContentPrepareCompleteHandler,
			$runLast
				? -9999
				: 0
		);

		// Trick: register onAfterDispatchComplete handler as late as possible
		$onAfterDispatchCompleteHandler = function ()
		{
			$this->doOnAfterDispatchComplete();
		};

		$this->platform->registerEventHandler(
			'onAfterDispatch',
			$onAfterDispatchCompleteHandler
		);
	}

	/**
	 * Handles the onContentPrepare Joomla event, but as this handler is registered
	 * later than usually, in most cases it will be called after all other handlers
	 * for the same event.
	 *
	 * @param string    $context The context of the content being passed to the plugin.
	 * @param mixed    &$row     An object with a "text" property
	 * @param mixed     $params  Additional parameters. See {@see PlgContentContent()}.
	 * @param integer   $page    Optional page number. Unused. Defaults to zero.
	 *
	 * @return  void
	 */
	public function doOnContentPrepareComplete($context, &$row, &$params, $page = 0)
	{
		// something went wrong during init.
		if (!defined('FORSEO_APP_PATH'))
		{
			return;
		}

		if (!$this->platform->isFrontend())
		{
			return;
		}

		/**
		 * Hook to run the registered onContentPrepare handlers.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\content
		 * @var forseo_onContentPrepare
		 *
		 * @param bool        $modified Whether the content was modified.
		 * @param string      $context  The context of the content being passed to the plugin.
		 * @param mixed     & $row      An object with a "text" property
		 * @param mixed     & $params   Additional parameters. See {@see PlgContentContent()}.
		 * @param integer     $page     Optional page number. Unused. Defaults to zero.
		 *
		 * @since   1.0.0
		 *
		 */
		$prepared = $this->hook->filter(
			'forseo_onContentPrepare',
			[
				'modified' => false,
				'context'  => $context,
				'content'  => $row,
				'params'   => $params,
				'page'     => $page
			]
		);

		// we may have modified content or params
		if (Wb\arrayGet($prepared, 'modified'))
		{
			$row    = Wb\arrayGet($prepared, 'row', $row);
			$params = Wb\arrayGet($prepared, 'params', $params);
		}

		/**
		 * Action to let plugins obtain the finalized content for the current request
		 *
		 * @api     forseo
		 * @package 4SEO\action\content
		 * @var forseo_content_prepared
		 *
		 * @param string  $context The context of the content being passed to the plugin.
		 * @param mixed   $row     An object with a "text" property
		 * @param mixed   $params  Additional parameters. See {@see PlgContentContent()}.
		 * @param integer $page    Optional page number. Unused. Defaults to zero.
		 *
		 * @since   1.0.0
		 *
		 */
		$this->hook->run(
			'forseo_content_prepared',
			[
				'context' => $context,
				'content' => $row,
				'params'  => $params,
				'page'    => $page
			]
		);
	}

	public function onAfterDispatch()
	{
		// something went wrong during init.
		if (!defined('FORSEO_APP_PATH'))
		{
			return;
		}

		if (!$this->platform->isFrontend())
		{
			return;
		}

		/**
		 * Hook to run the registered onAfterDispatch handlers.
		 *
		 * @api     forseo
		 * @package 4SEO\action\events
		 * @var forseo_onAfterDispatch
		 * @since   1.0.0
		 *
		 */
		$this->hook->run(
			'forseo_onAfterDispatch'
		);
	}

	/**
	 * Triggered after all onAfterDispatch handlers have been triggered, or
	 * so we hope.
	 * On J4: use Event\Priority::MIN
	 * on J3: register the event as late as possible, on the onAfterRouteHandler
	 */
	protected function doOnAfterDispatchComplete()
	{
		// something went wrong during init.
		if (!defined('FORSEO_APP_PATH'))
		{
			return;
		}

		if (!$this->platform->isFrontend())
		{
			return;
		}

		/**
		 * Hook to run the registered onAfterDispatchComplete handlers.
		 *
		 * @api     forseo
		 * @package 4SEO\action\events
		 * @var forseo_onAfterDispatchComplete
		 * @since   1.0.0
		 *
		 */
		$this->hook->run(
			'forseo_onAfterDispatchComplete'
		);
	}

	public function onBeforeRender()
	{
		// something went wrong during init.
		if (!defined('FORSEO_APP_PATH'))
		{
			return;
		}

		if (!$this->platform->isFrontend())
		{
			return;
		}

		/**
		 * Hook to run the registered onBeforeRender handlers.
		 *
		 * @api     forseo
		 * @package 4SEO\action\events
		 * @var forseo_onBeforeRender
		 * @since   1.0.0
		 *
		 */
		$this->hook->run(
			'forseo_onBeforeRender'
		);

		// Trick: register onAfterRenderComplete handler as late as possible
		$onAfterRenderCompleteHandler = function ()
		{
			$this->doOnAfterRenderComplete();
		};

		$this->platform->registerEventHandler(
			'onAfterRender',
			$onAfterRenderCompleteHandler
		);

		// Same with onBeforeCompileHead
		$onBeforeCompileHeadCompleteHandler = function ()
		{
			$this->doOnBeforeCompileHeadComplete();
		};

		$this->platform->registerEventHandler(
			'onBeforeCompileHead',
			$onBeforeCompileHeadCompleteHandler
		);
	}

	public function onBeforeCompileHead()
	{
		// something went wrong during init.
		if (!defined('FORSEO_APP_PATH'))
		{
			return;
		}

		if (!$this->platform->isFrontend())
		{
			return;
		}

		/**
		 * Hook to run the registered onBeforeCompileHead handlers.
		 *
		 * @api     forseo
		 * @package 4SEO\action\events
		 * @var forseo_onBeforeCompileHead
		 * @since   1.0.0
		 *
		 */
		$this->hook->run(
			'forseo_onBeforeCompileHead'
		);
	}

	/**
	 * Triggered after all onAfterRender handlers have been triggered, or
	 * so we hope.
	 * On J4: use Event\Priority::MIN
	 * on J3: register the event as late as possible, on the onAfterDispatchHandler
	 */
	protected function doOnBeforeCompileHeadComplete()
	{
		// something went wrong during init.
		if (!defined('FORSEO_APP_PATH'))
		{
			return;
		}

		if (!$this->platform->isFrontend())
		{
			return;
		}

		/**
		 * Hook to run the registered onBeforeCompileHeadComplete handlers.
		 *
		 * @api     forseo
		 * @package 4SEO\action\events
		 * @var forseo_onBeforeCompileHeadComplete
		 * @since   1.5.1
		 *
		 */
		$this->hook->run(
			'forseo_onBeforeCompileHeadComplete'
		);
	}

	public function onAfterRender()
	{
		// something went wrong during init.
		if (!defined('FORSEO_APP_PATH'))
		{
			return;
		}

		if (!$this->platform->isFrontend())
		{
			return;
		}

		/**
		 * Hook to run the registered onAfterRoute handlers.
		 *
		 * @api     forseo
		 * @package 4SEO\action\events
		 * @var forseo_onAfterRender
		 * @since   1.0.0
		 *
		 */
		$this->hook->run(
			'forseo_onAfterRender'
		);

		/**
		 * Filter the body of the CMS response at onAfterRender.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\output
		 * @var forseo_onAfterRender_body
		 *
		 * @param string $body Body of the current request.
		 *
		 * @since   1.0.0
		 */
		$body = $this->hook->filter(
			'forseo_onAfterRender_body',
			Wb\initEmpty(
				$this->app->getBody(),
				''
			)
		);

		$this->app->setBody(
			$body
		);
	}

	/**
	 * Triggered after all onAfterRender handlers have been triggered, or
	 * so we hope.
	 * On J4: use Event\Priority::MIN
	 * on J3: register the event as late as possible, on the onAfterDispatchHandler
	 */
	protected function doOnAfterRenderComplete()
	{
		// something went wrong during init.
		if (!defined('FORSEO_APP_PATH'))
		{
			return;
		}

		if (!$this->platform->isFrontend())
		{
			return;
		}

		/**
		 * Hook to run the registered onAfterRoute handlers.
		 *
		 * @api     forseo
		 * @package 4SEO\action\events
		 * @var forseo_onAfterRenderComplete
		 * @since   1.0.0
		 *
		 */
		$this->hook->run(
			'forseo_onAfterRenderComplete'
		);

		/**
		 * Filter the body of the CMS response at onAfterRender.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\output
		 * @var forseo_onAfterRenderComplete_body
		 *
		 * @param string $body Body of the current request.
		 *
		 * @since   1.0.0
		 */
		$body = $this->hook->filter(
			'forseo_onAfterRenderComplete_body',
			Wb\initEmpty(
				$this->app->getBody(),
				''
			)
		);

		$this->app->setBody(
			$body
		);
	}

	public function onAfterRespond()
	{
		// something went wrong during init.
		if (!defined('FORSEO_APP_PATH'))
		{
			return;
		}

		/**
		 * Hook to run the registered onAfterRespond handlers.
		 *
		 * Warning: body may be gzipped at this time.
		 *
		 * @api     forseo
		 * @package 4SEO\action\events
		 * @var forseo_onAfterRespond
		 * @since   1.0.0
		 *
		 */
		$this->hook->run(
			'forseo_onAfterRespond'
		);
	}
}
