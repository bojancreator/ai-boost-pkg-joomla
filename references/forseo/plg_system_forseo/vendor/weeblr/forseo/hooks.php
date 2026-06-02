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

namespace Weeblr\Forseo;

use Weeblr\Forseo\Controller;
use Weeblr\Forseo\Model;
use Weeblr\Forseo\Model\Extensions;
use Weeblr\Forseo\Model\Integrations\Google;
use Weeblr\Forseo\Helper\Integrations as IntegrationsHelper;
use Weeblr\Forseo\Platform\Helpers as PlatformHelpers;

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Hooks related to ForSEO application.
 *
 * @package Weeblr\Forseo
 */
class Hooks extends Base\Base
{
	public function add()
	{
		$hook = $this->factory->getThe('hook');

		/********************************************************************************************************
		 * Fixes: must be right at the beginning, else the "shared variables" section below may not
		 *        use some of the fixes.
		 *******************************************************************************************************/
		$hook->add(
			'forseo_config',
			function ($config, $scope)
			{

				if ('sitemaps' === $scope)
				{
					// enforce not submitting to Bing, even if that was saved in user config
					// at the time that feature was removed
					$config['searchEnginesPingEnabled'] = array_filter(
						$config['searchEnginesPingEnabled'],
						function ($engine)
						{
							return 'bing' !== $engine;
						}
					);
				}

				return $config;
			}
		);

		/********************************************************************************************************
		 * Shared variables. To be avoided as side effects can happen.
		 *******************************************************************************************************/
		$pageDataCollector = $this->factory->getThe('forseo.pageDataCollector');
		$pageProcessor     = $this->factory->getThe('forseo.pageProcessor');
		$rulesController   = $this->factory->getThe('forseo.rulesController');
		$aliasesController = $this->factory->getThe('forseo.aliasesController');

		/********************************************************************************************************
		 * Cron events
		 *******************************************************************************************************/

		// run crawl
		$hook->add(
			'forseo_cron',
			[
				$this->factory
					->getA(Controller\Crawler::class),
				'fromCron'
			]
		);

		$hook->add(
			'forseo_cron',
			function ()
			{
				$this->factory
					->getA(Model\Referrers::class)
					->purgeUnused();
			},
			System\Hook::PRIORITY_LOW
		);

		$hook->add(
			'forseo_cron',
			function ()
			{
				$this->factory
					->getA(Model\Errors::class)
					->purgeAfter();
			},
			System\Hook::PRIORITY_LOW
		);

		$hook->add(
			'forseo_cron',
			function ()
			{
				$this->factory
					->getA(Model\Sitemaps::class)
					->pingSearchEngines();
			},
			System\Hook::PRIORITY_LOW
		);

		//if ($this->factory->getThis('forseo.config', 'integrations')->isGoogleSearchConsoleActive())
		//{
		//	$hook->add(
		//		'forseo_cron',
		//		function () {
		//			$this->factory->getA(Google\Searchconsoledata::class)
		//						  ->dailyFetch();
		//		},
		//		System\Hook::PRIORITY_LOW
		//	);
		//}

		/********************************************************************************************************
		 * onAfterInitialise
		 *******************************************************************************************************/

		$crawlerHelper = $this->factory->getThe('forseo.crawlerHelper');
		$hook->add(
			'forseo_onAfterInitialise',
			[
				$crawlerHelper,
				'isCrawlerRequest'
			]
		);

		if ($this->platform->isFrontend())
		{
			$hook->add(
				'forseo_onAfterInitialise',
				[
					$pageProcessor,
					'enforceCanonicalRootUrl'
				]
			);

			$hook->add(
				'forseo_onAfterInitialise',
				function ()
				{
					$this->factory->getA(Controller\Sitemap::class)->run();
				}
			);

			$hook->add(
				'forseo_onAfterInitialise',
				[
					$this->factory
						->getA(Model\Rules::class),
					'loadRules'
				]
			);

			$hook->add(
				'forseo_onAfterInitialise',
				function ()
				{
					$this->factory->getA(Model\Platform::class)->reconfigurePlatformPlugins();
				}
			);
		}

		if ($this->platform->isBackend())
		{
			$this->platform->registerEventHandler(
				'onForsefBeforeApiProcessing',
				function ()
				{
					/********************************************************************************************************
					 * 4SEF interface
					 *******************************************************************************************************
					 */
					if (is_callable('\Forsef::getHook'))
					{
						\Forsef::getHook()->add(
							'forsef_url_customized',
							function ($data, $originalBasePath, $customizedSefs, $originalSef, $extraPathLeadingSlash, $customizeDuplicates)
							{
								$this->factory
									->getA(Extensions\Forsef::class)
									->onUrlCustomized(
										$data,
										$originalBasePath,
										$customizedSefs,
										$originalSef,
										$extraPathLeadingSlash,
										$customizeDuplicates
									);
							}
						);
					}
				}
			);
		}

		/********************************************************************************************************
		 * onAfterRoute::disable offline mode for our crawler
		 *******************************************************************************************************/

		if ($this->platform->isFrontend())
		{
			$hook->add(
				'forseo_onAfterRoute',
				[
					$this->factory->getThe('forseo.requestInfo'),
					'collectInfoAfterRoute'
				]
			);

			$hook->add(
				'forseo_onAfterRoute',
				function ()
				{
					$this->factory->getA(
						PlatformHelpers\Urls::class
					)->enforceLowerCaseUrls();
				}
			);

			$hook->add(
				'forseo_onAfterRoute',
				[
					$crawlerHelper,
					'disableOfflineMode'
				]
			);

			$hook->add(
				'forseo_onAfterRoute',
				function ()
				{
					$this->factory->getA(Model\Platform::class)->disableCaching();
				}
			);
		}

		/********************************************************************************************************
		 * onAfterRoute::data collection
		 *******************************************************************************************************/

		if ($this->platform->isFrontend())
		{
			$hook->add(
				'forseo_onAfterRoute',
				[
					$pageDataCollector,
					'onAfterRoute'
				]
			);

			// collect specific page data on errors
			$hook->add(
				'forseo_on_404_error',
				[
					$pageDataCollector,
					'onError'
				]
			);
			$hook->add(
				'forseo_on_error',
				[
					$pageDataCollector,
					'onError'
				]
			);
		}

		/********************************************************************************************************
		 * onAfterRoute::aliases
		 *******************************************************************************************************/

		if ($this->platform->isFrontend())
		{
			$hook->add(
				'forseo_onAfterRoute',
				[
					$aliasesController,
					'execute'
				],
				System\Hook::PRIORITY_HIGHEST
			);
		}

		/********************************************************************************************************
		 * onAfterRoute::discard rules that do not apply to current request
		 *******************************************************************************************************/

		if ($this->platform->isFrontend())
		{
			$hook->add(
				'forseo_onAfterRoute',
				[
					$rulesController,
					'filterExecutableRules'
				],
				System\Hook::PRIORITY_HIGHEST
			);
		}

		/********************************************************************************************************
		 * onAfterRoute::redirects
		 *******************************************************************************************************/

		if ($this->platform->isFrontend())
		{
			$hook->add(
				'forseo_onAfterRoute',
				[
					$rulesController,
					'executeRedirects'
				],
				System\Hook::PRIORITY_HIGHER
			);
		}

		/********************************************************************************************************
		 * onAfterRoute::WAF
		 *******************************************************************************************************/

		if ($this->platform->isFrontend())
		{
			$hook->add(
				'forseo_onAfterRoute',
				[
					$rulesController,
					'executeWaf'
				],
				System\Hook::PRIORITY_HIGH
			);
		}

		/********************************************************************************************************
		 * onAfterRoute::Miscelleanous
		 *******************************************************************************************************/

		if ($this->platform->isBackend())
		{
			$hook->add(
				'forseo_onAfterRoute',
				function ()
				{
					$this->factory
						->getA(Helper\Sitemaps::class)
						->updateRobotsTxtAfterInstall();
				}
			);
		}

		/********************************************************************************************************
		 * onContentPrepare:Compute content hash
		 *******************************************************************************************************/

		$hook->add(
			'forseo_onContentPrepare',
			[
				$pageDataCollector,
				'collectContentData'
			]
		);

		/********************************************************************************************************
		 * onContentPrepare::discard rules that do not apply to current request
		 *******************************************************************************************************/

		if ($this->platform->isFrontend())
		{
			$hook->add(
				'forseo_onContentPrepare',
				[
					$rulesController,
					'filterExecutableRulesByCustomField'
				],
				System\Hook::PRIORITY_HIGHEST
			);
		}

		/********************************************************************************************************
		 * onContentPrepare::redirects
		 *******************************************************************************************************/

		if ($this->platform->isFrontend())
		{
			$hook->add(
				'forseo_onContentPrepare',
				[
					$rulesController,
					'executeRedirects'
				],
				System\Hook::PRIORITY_HIGHER
			);
		}

		/********************************************************************************************************
		 * onContentPrepare::WAF
		 *******************************************************************************************************/

		if ($this->platform->isFrontend())
		{
			$hook->add(
				'forseo_onContentPrepare',
				[
					$rulesController,
					'executeWaf'
				],
				System\Hook::PRIORITY_HIGH
			);
		}

		/********************************************************************************************************
		 * onContentPrepare:Compute content replacer
		 *******************************************************************************************************/

		$hook->add(
			'forseo_onContentPrepare',
			[
				$rulesController,
				'runContentReplacers'
			]
		);

		/********************************************************************************************************
		 * onContentPrepare: Replace dynamic variables & extract sharing/SD images
		 *******************************************************************************************************/

		$hook->add(
			'forseo_onContentPrepare',
			[
				$pageProcessor,
				'onContentPrepare'
			]
		);

		/********************************************************************************************************
		 * onAfterDispatch: replace dynamic variables, inject custom meta
		 *******************************************************************************************************/

		/********************************************************************************************************
		 * onAfterDispatchComplete: Fake event, hopefully running after all onAfterDispatch handlers have ran.
		 *******************************************************************************************************/

		$hook->add(
			'forseo_onAfterDispatchComplete',
			[
				$pageDataCollector,
				'loadCustomMetaData'
			]
		);

		$hook->add(
			'forseo_onAfterDispatchComplete',
			[
				$pageProcessor,
				'onAfterDispatchComplete'
			]
		);

		$hook->add(
			'forseo_onAfterDispatchComplete',
			[
				$pageProcessor,
				'injectMetaData'
			],
			System\Hook::PRIORITY_LOWEST
		);

		/********************************************************************************************************
		 * onBeforeRender
		 *******************************************************************************************************/


		/********************************************************************************************************
		 * onBeforeCompileHeadComplete
		 *******************************************************************************************************/

		// Collect title, desc, canonical and robots a second time in case
		// some extension overrode any but only at onBeforeCompileHead
		$hook->add(
			'forseo_onBeforeCompileHeadComplete',
			[
				$pageProcessor,
				'extractMetadata'
			]
		);

		$hook->add(
			'forseo_onBeforeCompileHeadComplete',
			[
				$pageDataCollector,
				'collectPlatformMetaData'
			]
		);

		$hook->add(
			'forseo_onBeforeCompileHeadComplete',
			[
				$rulesController,
				'executeMeta'
			]
		);

		$hook->add(
			'forseo_onBeforeCompileHeadComplete',
			[
				$rulesController,
				'runMetaReplacers'
			]
		);

		$hook->add(
			'forseo_onBeforeCompileHeadComplete',
			[
				$pageProcessor,
				'injectSeoData'
			]
		);

		$hook->add(
			'forseo_onBeforeCompileHeadComplete',
			[
				$pageProcessor,
				'customCanonical'
			]
		);

		$hook->add(
			'forseo_onBeforeCompileHeadComplete',
			[
				$pageProcessor,
				'autoCanonical'
			]
		);

		// Inject metadata a second time, to override other extensions.
		$hook->add(
			'forseo_onBeforeCompileHeadComplete',
			[
				$pageProcessor,
				'injectMetaData'
			],
			System\Hook::PRIORITY_LOWEST
		);

		// Inject Google site verification token
		$hook->add(
			'forseo_onBeforeCompileHeadComplete',
			function ()
			{
				$this->factory
					->getA(IntegrationsHelper\Googleapis::class)
					->injectVerificationToken();
			},
			System\Hook::PRIORITY_LOWEST
		);

		$hook->add(
			'forseo_onBeforeCompileHeadComplete',
			function ()
			{
				$breadcrumbHelper = $this->factory->getA(PlatformHelpers\Breadcrumb::class);
				$breadcrumbHelper->killNativeStucturedData();
			},
			System\Hook::PRIORITY_LOWEST
		);


		/********************************************************************************************************
		 * onAfterRender
		 *******************************************************************************************************/

		$hook->add(
			'forseo_onAfterRender_body',
			[
				$rulesController,
				'injectRawContent'
			]
		);

		$hook->add(
			'forseo_onAfterRender_body',
			[
				$pageProcessor,
				'expandVariablesOnPage'
			]
		);

		$hook->add(
			'forseo_onAfterRender_body',
			[
				$pageProcessor,
				'injectAnalytics'
			]
		);

		// inject content into current page body

		// soft cron pixel
		$hook->add(
			'forseo_onAfterRender_body',
			[
				$this->factory
					->getA(Controller\Triggers::class),
				'inject'
			],
			System\Hook::PRIORITY_LOW // make sure pixel is injected after other processing
		);

		// front-end editing
		if ($this->platform->isFrontend())
		{
			$hook->add(
				'forseo_onAfterRender_body',
				[
					$this->factory
						->getA(Controller\Fe::class),
					'injectLoader'
				],
				System\Hook::PRIORITY_LOW // make sure loader is injected after other processing
			);

			// variable expansions AFTER replacers, analytics, etc so that variables can be replacement targets
			$hook->add(
				'forseo_onAfterRender_body',
				[
					$pageProcessor,
					'expandVariables'
				],
				System\Hook::PRIORITY_LOW
			);
		}

		/********************************************************************************************************
		 * onAfterRenderComplete: Fake event, hopefully running after all onAfterRender handlers have ran.
		 *******************************************************************************************************/

		if ($this->platform->isFrontend())
		{
			// collect data about current page
			$hook->add(
				'forseo_onAfterRenderComplete',
				[
					$pageDataCollector,
					'onAfterRenderComplete'
				]
			);

			// Inject robots tags
			$hook->add(
				'forseo_onAfterRenderComplete_body',
				[
					$pageProcessor,
					'injectRobotsTag'
				]
			);

			$hook->add(
				'forseo_onAfterRenderComplete_body',
				[
					$rulesController,
					'runGlobalReplacers'
				]
			);

			$hook->add(
				'forseo_onAfterRenderComplete_body',
				[
					$this->factory->getThe('forseo.variablesExpander'),
					'cleanVariablesTags'
				],
				System\Hook::PRIORITY_LOWEST
			);

			$hook->add(
				'forseo_onAfterRenderComplete_body',
				[
					$pageProcessor,
					'cleanStructuredData'
				],
				System\Hook::PRIORITY_LOWEST
			);

			$hook->add(
				'forseo_onAfterRenderComplete_body',
				[
					$pageProcessor,
					'injectStructuredData'
				],
				System\Hook::PRIORITY_LOWEST
			);
		}

		/********************************************************************************************************
		 * onAfterRespond
		 *******************************************************************************************************/

		/********************************************************************************************************
		 * Sitemap
		 *******************************************************************************************************/
		$hook->add(
			'forseo_on_crawl_complete',
			function ($crawl)
			{
				$this->factory
					->getA(Controller\Sitemap::class)
					->onCrawlComplete($crawl);
			}
		);

		/********************************************************************************************************
		 * Integrations
		 *******************************************************************************************************/
		$hook->add(
			'forseo_integrations_service_disconnected',
			function ($service)
			{
				$this->factory
					->getA(IntegrationsHelper\Googleapis::class)
					->onServiceDisconnected(
						$service
					);
			}
		);

		$hook->add(
			'forseo_sitemap_submit',
			function ($submissionResult, $searchEngine, $sitemapUrl)
			{
				if (
					is_null($submissionResult)
					&&
					'google' === $searchEngine
				) {
					$submissionResult = $this->factory
						->getA(Google\Searchconsole::class)
						->updateSitemaps(
							[
								'sitemapPath' => $sitemapUrl
							]
						);
				}

				return $submissionResult;
			}
		);
	}
}
