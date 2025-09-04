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

use Joomla\CMS\Plugin;
use Joomla\CMS\Language\Text;
use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Factory as wblFactory;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Joomla\StringHelper\StringHelper;

// no direct access
defined('_JEXEC') || die();

/**
 * 4SEF for Joomla system plugin.
 */
class plgSystemForsef extends Plugin\CMSPlugin
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
	 * Mimic Base\Base and load up wbLib factory and platform objects.
	 * Hook is added as well as this plugin is essentially relaying
	 * Joomla events through wbLib hooks.
	 */
	private $factory  = null;
	private $platform = null;
	private $hook     = null;

	/**
	 * @var Instance of the 4SEF app.
	 */
	private $forsef = null;

	public function __construct($subject, array $config = array())
	{
		parent::__construct($subject, $config);

		if (file_exists(__DIR__ . '/app_defines.php'))
		{
			include_once(__DIR__ . '/app_defines.php');
		}

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

		if ($this->platform->isFrontend())
		{
			include $this->platform->getRootPath() . '/plugins/system/forsef/platform/overrides/pagination/pagination.php';
		}
	}

	/**
	 * Next earliest event, create application.
	 */
	public function onAfterInitialise()
	{
		// something went wrong during init.
		if (!defined('FORSEF_APP_PATH'))
		{
			return;
		}

		// setup some basic path and initialize the app.
		// This will also register the app API handler
		// which needs to be done as early as possible.
		$this->forsef = $this->factory->getThis(
			'app',
			'forsef',
			array(
				'id'        => 'forsef',
				'namespace' => '\Weeblr\Forsef',
				'rootpath'  => FORSEF_APP_PATH
			)
		);

		/**
		 * Hook to run the registered onAfterInitialise handlers.
		 *
		 * @api     forsef
		 * @package 4SEF\action\events
		 * @var forsef_onAfterInitialise
		 * @since   1.0.0
		 *
		 */
		$this->hook->run(
			'forsef_onAfterInitialise'
		);
	}

	public function onPrivacyCollectAdminCapabilities()
	{
		// something went wrong during init.
		if (!defined('FORSEF_APP_PATH'))
		{
			return;
		}

		/**
		 * Hook to run the registered onAfterInitialise handlers.
		 *
		 * @api     forsef
		 * @package 4SEF\filter\events
		 * @var forsef_onPrivacyCollectAdminCapabilities
		 * @since   1.0.0
		 *
		 */
		return $this->hook->filter(
			'forsef_onPrivacyCollectAdminCapabilities',
			[
				'4SEF' => [
					Text::_('PLG_SYSTEM_FORSEF_PRIVACY_CAPABILITIES')
				]
			]
		);
	}

	/**
	 * Hook into wbLib after initial parsing's been done by Joomla.
	 */
	public function onAfterRoute()
	{
		// something went wrong during init.
		if (!defined('FORSEF_APP_PATH'))
		{
			return;
		}

		/**
		 * Shared event, can be handled by anyone, even if our apps
		 * hook system is not initialized yet.
		 */
		$this->platform->triggerEvent('onForsefBeforeApiProcessing');

		/**
		 * Hook to run the registered API handlers.
		 *
		 * @api     wblib
		 * @package wbLib\action\api
		 * @var wblib_api_process_request
		 * @since   1.0.0
		 *
		 */
		$this->hook->run(
			'wblib_api_process_request'
		);

		// Allow 3rd-party to provide extensions or overrides to 4SEF. They will have to hook into the proper
		// filters when their plugin is instantiated. Best look at native plugins addHook() method.
		Plugin\PluginHelper::importPlugin('forsef');

		/**
		 * Hook to run the registered onAfterRoute handlers.
		 *
		 * @api     forsef
		 * @package 4SEF\action\events
		 * @var forsef_onAfterRoute
		 * @since   1.0.0
		 *
		 */
		$this->hook->run(
			'forsef_onAfterRoute'
		);
	}

	/**
	 * Plugin that retrieves contact information for contact
	 *
	 * @param string    $context The context of the content being passed to the plugin.
	 * @param mixed    &$row     An object with a "text" property
	 * @param mixed     $params  Additional parameters. See {@see PlgContentContent()}.
	 * @param integer   $page    Optional page number. Unused. Defaults to zero.
	 *
	 * @return  void
	 */
	public function onContentPrepare($context, &$row, &$params, $page = 0)
	{
		// something went wrong during init.
		if (!defined('FORSEF_APP_PATH'))
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
		 * @api     forsef
		 * @package 4SEF\filter\content
		 * @var forsef_onContentPrepare
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
			'forsef_onContentPrepare',
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
		 * @api     forsef
		 * @package 4SEF\action\content
		 * @var forsef_content_prepared
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
			'forsef_content_prepared',
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
		if (!defined('FORSEF_APP_PATH'))
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
		 * @api     forsef
		 * @package 4SEF\action\events
		 * @var forsef_onAfterDispatch
		 * @since   1.0.0
		 *
		 */
		$this->hook->run(
			'forsef_onAfterDispatch'
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
		if (!defined('FORSEF_APP_PATH'))
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
		 * @api     forsef
		 * @package 4SEF\action\events
		 * @var forsef_onAfterDispatchComplete
		 * @since   1.0.0
		 *
		 */
		$this->hook->run(
			'forsef_onAfterDispatchComplete'
		);
	}

	public function onBeforeRender()
	{
		// something went wrong during init.
		if (!defined('FORSEF_APP_PATH'))
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
		 * @api     forsef
		 * @package 4SEF\action\events
		 * @var forsef_onBeforeRender
		 * @since   1.0.0
		 *
		 */
		$this->hook->run(
			'forsef_onBeforeRender'
		);

		// Trick: register onAfterRenderComplete handler as late as possible
		$onAfterRenderCompleteHandler = function () {
			$this->doOnAfterRenderComplete();
		};

		$this->platform->registerEventHandler(
			'onAfterRender',
			$onAfterRenderCompleteHandler
		);
	}

	public function onBeforeCompileHead()
	{
		// something went wrong during init.
		if (!defined('FORSEF_APP_PATH'))
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
		 * @api     forsef
		 * @package 4SEF\action\events
		 * @var forsef_onBeforeCompileHead
		 * @since   1.0.0
		 *
		 */
		$this->hook->run(
			'forsef_onBeforeCompileHead'
		);
	}

	public function onAfterRender()
	{
		// something went wrong during init.
		if (!defined('FORSEF_APP_PATH'))
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
		 * @api     forsef
		 * @package 4SEF\action\events
		 * @var forsef_onAfterRender
		 * @since   1.0.0
		 *
		 */
		$this->hook->run(
			'forsef_onAfterRender'
		);

		/**
		 * Filter the body of the CMS response at onAfterRender.
		 *
		 * @api     forsef
		 * @package 4SEF\filter\output
		 * @var forsef_onAfterRender_body
		 *
		 * @param string $body Body of the current request.
		 *
		 * @since   1.0.0
		 */
		$body = $this->hook->filter(
			'forsef_onAfterRender_body',
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
		if (!defined('FORSEF_APP_PATH'))
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
		 * @api     forsef
		 * @package 4SEF\action\events
		 * @var forsef_onAfterRenderComplete
		 * @since   1.0.0
		 *
		 */
		$this->hook->run(
			'forsef_onAfterRenderComplete'
		);

		/**
		 * Filter the body of the CMS response at onAfterRender.
		 *
		 * @api     forsef
		 * @package 4SEF\filter\output
		 * @var forsef_onAfterRenderComplete_body
		 *
		 * @param string $body Body of the current request.
		 *
		 * @since   1.0.0
		 */
		$body = $this->hook->filter(
			'forsef_onAfterRenderComplete_body',
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
		if (!defined('FORSEF_APP_PATH'))
		{
			return;
		}

		/**
		 * Hook to run the registered onAfterRespond handlers.
		 *
		 * Warning: body may be gzipped at this time.
		 *
		 * @api     forsef
		 * @package 4SEF\action\events
		 * @var forsef_onAfterRespond
		 * @since   1.0.0
		 *
		 */
		$this->hook->run(
			'forsef_onAfterRespond'
		);
	}
}
