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

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Forseo\Helper;
use Weeblr\Forseo\Config;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Platform extends Base\Base
{
	/**
	 * @var System\Logger Convenience logger instance.
	 */
	private $logger = null;

	/**
	 * @var Config
	 */
	private $pagesConfig;

	/**
	 * @var bool Cache whether this is a crawler request.
	 */
	private $isCrawlerRequest = false;

	/**
	 * @param array $options Can inject custom factory and platform.
	 */
	public function __construct($options = [])
	{
		parent::__construct();

		$this->logger           = $this->factory->getThe('forseo.logger');
		$this->pagesConfig      = $this->factory->getThis('forseo.config', 'pages');
		$this->isCrawlerRequest = $this->factory->getThe('forseo.crawlerHelper')->isCrawlerRequest();
	}

	/**
	 * Disable application-level caching on crawler requests.
	 */
	public function disableCaching()
	{
		if (!$this->isCrawlerRequest)
		{
			return;
		}

		$this->platform
			->getConfig()
			->set(
				'caching',
				0
			);
	}

	/**
	 * Disable or reconfigure one or more plugins from a given plugins group as set in Pages configuration.
	 *
	 * @return void
	 */
	public function reconfigurePlatformPlugins()
	{
		try
		{
			$this->doReconfigurePlatformPlugins()
				 ->doDisablePlatformPlugins();
		}
		catch (\Throwable $e)
		{
			$this->logger->error(__METHOD__ . ', %s::%d %s %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
	}

	/**
	 * Disable one or more plugins from a given plugins group as set in Pages configuration.
	 *
	 * @return Platform
	 */
	private function doDisablePlatformPlugins()
	{
		if (!$this->isCrawlerRequest)
		{
			return $this;
		}

		try
		{
			/**
			 * Filter the list of plugins that should be disabled on crawler requests.
			 * Full page cache plugins should be disabled as they prevent onContentPrepare
			 * and similar events to be fired.
			 *
			 * @api     forseo
			 * @package 4SEO\filter\crawler
			 * @var forseo_crawler_plugins_to_disable
			 * @since   1.0.0
			 *
			 * @param array $plugins List of plugins, grouped by plugin group.
			 *
			 * @return array
			 *
			 */
			$pluginsToDisable = $this->factory
				->getThe('hook')
				->filter(
					'forseo_crawler_plugins_to_disable',
					$this->pagesConfig->get('crawlerPluginsToDisable', [])
				);

			if (empty($pluginsToDisable))
			{
				return $this;
			}

			foreach ($pluginsToDisable as $type => $plugins)
			{
				$this->platform
					->disablePlugins(
						$type,
						$plugins
					);
			}
		}
		catch (\Throwable $e)
		{
			$this->logger->error('Platform Model disabling plugins %s::%d %s %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}

		return $this;
	}

	/**
	 * Reconfigure Joomla and 3rd-party plugins either for direct SEO benefit
	 * or to facilitate crawling.
	 *
	 * @return Platform
	 */
	private function doReconfigurePlatformPlugins()
	{
		$defs = $this->pagesConfig->get('pluginsToConfigure', []);

		if ($this->isCrawlerRequest)
		{
			$defs = array_merge(
				$defs,
				$this->pagesConfig->get('crawlerPluginsToConfigure', [])
			);
		}

		/**
		 * Filter the list of plugins that should be re-configured, either on crawler requests or always.
		 *
		 * $def is an array defining which plugins and what options should be modified:
		 *
		 * [
		 *   'PlgSystemCache' => [
		 *      'key_1' => $value1,
		 *      'key_2' => $value2,
		 *      'key_3' => $value3
		 *   ]
		 * ]
		 *
		 * @api     forseo
		 * @package 4SEO\filter\platform
		 * @var forseo_plugins_to_configure
		 * @since   1.5.1
		 *
		 * @param array $defs             Definition of reconfiguration, keyed on plugin class name.
		 * @param bool  $isCrawlerRequest Whether current request is from 4SEO crawler.
		 *
		 * @return array
		 *
		 */
		$pluginsToConfigure = $this->factory
			->getThe('hook')
			->filter(
				'forseo_plugins_to_configure',
				$defs,
				$this->isCrawlerRequest
			);

		if (!empty($pluginsToConfigure))
		{
			$this->platform->reconfigurePlugins($pluginsToConfigure);
		}

		return $this;
	}
}
