<?php
/**
 * Project: 4SEO
 *
 * @package                 4SEO
 * @copyright               Copyright Weeblr llc - 2020 - 2026
 * @author                  Yannick Gaultier - Weeblr llc
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 6.10.1.2660
 *
 * 2026-01-30
 */

namespace Weeblr\Forseo\Controller;

use Weeblr\Forseo\Helper;

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Mvc;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Render a story page
 */
class Triggers extends Base\Base
{
	/**
	 * @var Config Convenience instance of the system configuration.
	 */
	private $systemConfig;

	/**
	 * @var Config Convenience instance of the pages analysis configuration.
	 */
	private $pagesConfig;

	/**
	 * @var string
	 */
	private $body = '';

	private $injected = [];

	/**
	 * @param array $options Can inject custom factory and platform.
	 */
	public function __construct($options = [])
	{
		parent::__construct();

		$this->systemConfig = $this->factory->getThis('forseo.config', 'system');
		$this->pagesConfig  = $this->factory->getThis('forseo.config', 'pages');
	}

	/**
	 * Possibly inject some javascript inside HTML pages to trigger actions.
	 *
	 * @param string $body
	 *
	 * @return string
	 */
	public function inject(string $body)
	{
		try
		{
			if (!$this->platform->isHtmlPage())
			{
				return $body;
			}

			if ($this->factory->getThe('forseo.crawlerHelper')->isCrawlerRequest())
			{
				// do not inject triggers on crawler requests: won't be executed anyway as crawler
				// does not execute javascript. Also it complicates things when debugging crawler
				// requests in a browser.
				return $body;
			}

			$this->body     = $body;
			$this->injected = [];

			$this->injectCronPixel()
				 ->injectPerfProbe();

			return $this->body;

		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return $body;
		}
	}

	/**
	 * Possibly inject some javascript inside HTML pages to trigger a cron API request.
	 *
	 * @return $this
	 */
	private function injectCronPixel()
	{
		if ($this->systemConfig->isFalsy('clientCron'))
		{
			return $this;
		}

		$odd = $this->systemConfig->get('cronPerPages', 1); // one every N pages
		if (mt_rand(1, $odd) != 1)
		{
			return $this;
		}

		/**
		 * Filter whether the cron pixel should be removed from current page.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\cron
		 * @var forseo_disable_cron_pixel
		 * @since   1.3.2
		 *
		 * @param bool $disableCronPixel
		 *
		 * @return bool
		 *
		 */
		$disableCronPixel = $this->factory
			->getThe('hook')
			->filter(
				'forseo_disable_cron_pixel',
				false
			);

		if (!empty($disableCronPixel))
		{
			return $this;
		}

		$api           = $this->factory->getThe('api');
		$renderedPixel = Mvc\LayoutHelper::render(
			'forseo.triggers.cron',
			[
				'triggerCronUrl'       => $api->routeLink('forseo', 'v1', '/cron/image/'),
				'softCronTriggerAfter' => $this->systemConfig->getInt('softCronTriggerAfter', 300),
				'softCronRemoveAfter'  => $this->systemConfig->getInt('softCronRemoveAfter', 3000),
			],
			FORSEO_LAYOUTS_PATH,
			'default'
		);

		$this->body = System\Strings::tagInBuffer(
			$this->body,
			'</body>',
			$renderedPixel,
			[
				'where'    => 'before',
				'lastOnly' => true
			]
		);

		$this->injected[] = 'cron';

		return $this;
	}

	/**
	 * Possibly inject some javascript inside HTML pages to trigger a performance measurement.
	 *
	 * @return $this
	 */
	private function injectPerfProbe()
	{
		if ($this->factory->getThe('forseo.searchEnginesHelper')->isSearchEngineRequest())
		{
			return $this;
		}

		if ($this->pagesConfig->isFalsy('perfMeasurementEnabled'))
		{
			return $this;
		}

		$pageDataCollector = $this->factory->getThe('forseo.pageDataCollector');
		$storedCurrentPage = $pageDataCollector->getStored();
		if (empty($storedCurrentPage) || !$storedCurrentPage->exists())
		{
			// only collect perf for pages with a crawling record
			return $this;
		}

		if (!$this->factory
			->getA(Helper\Url::class)
			->passExclusionRules(
				$storedCurrentPage->get('full_url'),
				$this->pagesConfig->get('perfDataExclusions'),
				$this->pagesConfig->get('perfDataInclusions')
			))
		{
			return $this;
		}

		$status = $this->factory->getThe('forseo.pageDataCollector')->get()->get('status');
		if (!empty($status) && System\Http::RETURN_OK !== $status)
		{
			// don't inject on any kind of error page
			return $this;
		}

		// help in local dev
		$isDev = 'dev' === WBLIB_Forseo_OP_MODE;

		// When full page caching is on, insert the code in all pages. We'll throttle/validate
		// incoming perf data anyway.
		$isFullPageCachingEnabled = $this->platform->isPluginEnabled('system', 'cache');
		if (!$isFullPageCachingEnabled && !$isDev)
		{
			$odd = $this->pagesConfig->get('perfProbePerPages', 1); // one every N pages
			if (mt_rand(1, $odd) != 1)
			{
				return $this;
			}
		}

		$renderedPerfProbe = Mvc\LayoutHelper::render(
			'forseo.triggers.perf',
			[
				'triggerUrl' => $this->factory
					->getThe('api')
					->routeLink('forseo', 'v1', '/perf/data'),
				'fullUrl'    => $storedCurrentPage->get('full_url'),
				'url'        => $storedCurrentPage->get('url')
			],
			FORSEO_LAYOUTS_PATH,
			'default'
		);

		$this->body = System\Strings::tagInBuffer(
			$this->body,
			'</body>',
			$renderedPerfProbe,
			[
				'where'    => 'before',
				'lastOnly' => true
			]
		);

		$this->injected[] = 'perfProbe';

		return $this;
	}
}
