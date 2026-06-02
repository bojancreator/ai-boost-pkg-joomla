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

namespace Weeblr\Forseo\Controller;

use \Weeblr\Forseo\Platform;
use \Weeblr\Forseo\Data;
use \Weeblr\Forseo\Model;
use \Weeblr\Forseo\Helper;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * This collector collects detailed data about the current page
 * and store them in the #__forseo_pages table.
 *
 * The data collection is a resource-intensive operation so it's meant to be ran
 * only when current page is fetched by our crawler, running in the background (deferred cron).
 *
 * @package Weeblr\Forseo\Controller
 */
class Pagedatacollector extends Base\Base
{
	/**
	 * @var string Current page requested URL with superfluous query vars filtered out.
	 */
	private $filteredUrl = '';

	/**
	 * @var Data\Page Storage for current page.
	 */
	private $currentPage = null;

	/**
	 * @var Data\Page Storage for current page record from database, if any.
	 */
	private $storedCurrentPage = null;

	/**
	 * @var Data\Meta Storage for current page meta data.
	 */
	private $currentMeta = null;

	/**
	 * @var Data\Collected The collected_urls record that triggered this page load.
	 */
	private $collectedUrl = null;

	/**
	 * @var bool Whether current request should be processed and possibly stored.
	 */
	private $shouldProcessCurrentPage = true;

	/**
	 * @var Platform\Collector Platform-specific page data collector.
	 */
	private $platformCollector = null;

	/**
	 * @var Helper\Crawler A helper for crawler-related features.
	 */
	private $crawlerHelper = null;

	/**
	 * @var Helper\Page A page processing helper.
	 */
	private $pageHelper = null;

	/**
	 * @var Helper\Linkscollector A helper for crawler-related features.
	 */
	private $linksCollectorHelper = null;

	/**
	 * @var Config Holds the page collection config object.
	 */
	private $config = null;

	/**
	 * @var System\Logger Convenience logger instance.
	 */
	private $logger = null;

	/**
	 * Instantiate a page object to store current page request data.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->logger               = $this->factory->getThe('forseo.logger');
		$this->config               = $this->factory->getThis('forseo.config', 'pages');
		$this->currentPage          = $this->factory->getA(Data\Page::class);
		$this->currentMeta          = $this->factory->getA(Data\Meta::class);
		$this->platformCollector    = $this->factory->getA(Platform\Pagecollector::class);
		$this->crawlerHelper        = $this->factory->getThe('forseo.crawlerHelper');
		$this->pageHelper           = $this->factory->getThe('forseo.pageHelper');
		$this->linksCollectorHelper = $this->factory->getA(Helper\Linkscollector::class);
	}

	/**
	 * Collects current page routing information and store for the rest of the request.
	 */
	public function onAfterRoute()
	{
		$this->logger->debug('PDC onAfterRoute start');

		// run all platform-specific data collection code
		$this->currentPage = $this->platformCollector
			->onAfterRoute($this->currentPage);

		// let plugins modify collected data as needed
		$this->filterCollectedData('after_route');

		// assign a content identifier, as early as possible
		$this->currentPage->set(
			'full_content_id',
			$this->pageHelper
				->contentId($this->currentPage)
		);

		/**
		 * Removes common unwanted vars from a URL, before it's used.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\frontend\collection
		 * @var forseo_clean_query_vars_to_strip
		 * @since   1.0.0
		 *
		 * @param array $link
		 *
		 * @return array
		 *
		 */
		$this->filteredUrl = $this->factory
			->getThe('hook')
			->filter(
				'forseo_clean_query_vars_to_strip',
				$this->currentPage->get('full_url')
			);

		if (!$this->canRun())
		{
			$this->logger->debug('PDC onAfterRoute, canRun is false');
			$this->shouldProcessCurrentPage = false;

			return;
		}

		try
		{
			$pageRecord = $this->factory
				->getA(Data\Page::class)
				->loadPerUrl(
					$this->filteredUrl
				);

			if ($pageRecord->exists())
			{
				$this->storedCurrentPage = $pageRecord;
			}

			// run conditions: frontend, html document, public access,..
			if ($this->crawlerHelper->isCrawlerRequest())
			{
				// Is the request for the main site address?
				// If not, we should not handle this but instead inform crawler that
				// the link is not valid. This can be done by just redirecting to the main address target.
				// We always redirect for crawler requests. For regular requests, this is only done
				// if the corresponding option is enabled in Pages settings.
				$this->factory
					->getThe('forseo.pageProcessor')
					->doEnforceCanonicalRootUrl();

				// Request by our crawler, can we store it?
				if (!$this->shouldCollectPageData(true))
				{
					$this->currentPage->set('ignore', true);

					return;
				}

				// this is a request from our crawler, normal data gathering process
				// Data collection only runs when request is coming from our crawler
				// The crawler triggers requests based on the collected_urls table content
				// So we must have a record in that table to work off.
				// Except when debugging: in that case, we create a fake collected_urls record
				if ($this->crawlerHelper->isDebugCrawlerRequest())
				{
					$this->collectedUrl = $this->factory
						->getA(Data\Collected::class)
						->set([
							'id'       => 9999999,
							'full_url' => $this->currentPage->get('full_url')
						]);
				}
				else
				{
					$this->collectedUrl = $this->factory
						->getA(Data\Collected::class)
						->loadPerUrl(
							$this->currentPage->get('full_url')
						);
				}

				// if not, 2 options: a redirect happened to a new, never seen before URL, or something's broken.
				// if a redirect, we let the processing continue. The crawler will store the result.
				if (
					!$this->collectedUrl->exists()
					&&
					$this->crawlerHelper->redirectCount() <= 0
				) {
					// no redirect, something's broken, fail.
					$this->shouldProcessCurrentPage = false;
					$this->logger->debug('PDC onAfterRoute : Aborting, no collected_url record, ' . print_r($this->currentPage->get(), true));
					System\Http::render(
						System\Http::RETURN_NOT_FOUND,
						'',          // cause
						'text/html', // type
						[],          // otherHeaders
						true         // endRequest
					);
				}
				else
				{
					if (!$this->collectedUrl->exists())
					{
						$this->logger->debug('PDC onAfterRoute : crawler request and URL is not in collected_urls, but it\'s a redirect so we let it render normally to see what happens. ' . print_r($this->currentPage->get(), true));
					}
				}

				$this->currentPage->set(
					'click_depth',
					$this->collectedUrl->get('click_depth')
				);

			}
			//else
			//{
			// not a request by our crawler
			// we would want to run the data collection
			// but this is a regular page view, not one by our crawler
			// so we don't have time to collect data.

			// If this page should be collected, we should add it to the
			// collected URLs list, for the crawler to take care of it later
			// now this is better done at onAfterRender because here at onAfterRoute
			// we probably don't know yet if it's going to be an error or a successfull page render.

			//}
		}
		catch (\Throwable $e)
		{
			$this->logger->error('PDC onAfterRoute: %s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
	}

	/**
	 * Called at onContentPrepare, tries to identify main content to hash it.
	 *
	 * Warning: onContentPrepare is called multiple times for the same page,
	 * for modules, etc. Really hard to identify the main content, not sure this can be done.
	 * However, onContentPrepare not being fired when caching is enabled, at least this is simpler.
	 *
	 * If a crawler request, we actually collect and store the content hash.
	 * If a regular request, we load any content hash record we have, compare current page hash with that and if
	 * modified, set that URL to be recrawled.
	 *
	 * @param array $contentData All content data.
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function collectContentData($contentData)
	{
		if (!$this->canRun())
		{
			$this->shouldProcessCurrentPage = false;

			if (!$this->shouldCollectPageData(true))
			{
				$this->currentPage->set('ignore', true);
			}

			return $contentData;
		}

		if ($this->currentPage->isTruthy('ignore'))
		{
			return $contentData;
		}

		/**
		 * Ask plugins to build a content hash when possible.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\frontend\collection
		 * @var forseo_page_build_content_hash
		 * @since   1.0.0
		 *
		 * @param string         $hash
		 * @param array          $contentData
		 * @param null|Data\Page $pageData
		 *
		 */
		$hash = $this->factory
			->getThe('hook')
			->filter(
				'forseo_page_build_content_hash',
				'',
				$contentData,
				$this->currentPage
			);

		// if not able to compute a content hash, bail early
		if (empty($hash))
		{
			return $contentData;
		}

		// Try to detect a multipage article
		$this->currentPage->set(
			'isMultiPage',
			$this->platform
				->isMultipageContent($contentData)
		);

		try
		{
			if (
				!$this->crawlerHelper->isCrawlerRequest()
				&&
				(
					empty($this->storedCurrentPage)
					||
					!$this->storedCurrentPage->exists()
				)
			) {
				$this->currentPage->set('ignore', true);
				return $contentData;
			}

			$contentHasChanged =
				!empty($this->storedCurrentPage)
				&&
				$this->storedCurrentPage->exists()
				&&
				$hash != $this->storedCurrentPage->get('hash');

			// update current page record if a crawler request, it'll be needed when saving the record
			if ($this->crawlerHelper->isCrawlerRequest())
			{
				$this->currentPage->set(
					'hash',
					$hash
				);
			}

			if (
				$contentHasChanged
				&&
				!$this->crawlerHelper->isCrawlerRequest()
			) {
				// content has changed but this is a regular request: put the page up for re-crawl
				$this->linksCollectorHelper
					->storeCollectedLinks(
						[
							$this->filteredUrl
						],
						$this->currentPage,
						[
							'forceCollection' => true
						]
					);
			}
		}
		catch (\Throwable $e)
		{
			$this->logger->error('PDC collectContentData data collection %s::%d %s %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}

		return $contentData;
	}

	/**
	 * Called at onAfterDispatchComplete, before custom meta data are injected. Loads custom meta data if any.
	 *
	 * @return Pagedatacollector
	 * @throws \Exception
	 */
	public function loadCustomMetaData()
	{
		$this->currentMeta
			->loadPerUrl(
				$this->filteredUrl
			);
//			->loadPerColumn(
//				'url',
//				$this->filteredUrl,
//				[], // $whereData
//				[
//					'id' => 'DESC'
//				] // $orderBy
//			);

		if ($this->currentMeta->exists())
		{
			$metaData = $this->currentMeta->getMeta();
			$this->factory
				->getThe('forseo.requestInfo')
				->set(
					[
						'page_custom_title'              => Wb\arrayGet($metaData, ['custom', 'title']),
						'page_custom_title_ogp'          => Wb\arrayGet($metaData, ['custom', 'title_ogp']),
						'page_custom_title_tcards'       => Wb\arrayGet($metaData, ['custom', 'title_tcards']),
						'page_custom_description'        => Wb\arrayGet($metaData, ['custom', 'description']),
						'page_custom_description_ogp'    => Wb\arrayGet($metaData, ['custom', 'description_ogp']),
						'page_custom_description_tcards' => Wb\arrayGet($metaData, ['custom', 'description_tcards']),
						'page_custom_canonical'          => Wb\arrayGet($metaData, ['custom', 'canonical']),
						'page_custom_robots'             => Wb\arrayGet($metaData, ['custom', 'robots']),
						'page_custom_image'              => Wb\arrayGet($metaData, ['custom', 'image']),
						'page_custom_sharing_image'      => Wb\arrayGet($metaData, ['custom', 'sharing_image']),
					]
				);
		}

		return $this;
	}

	/**
	 * Called at forseo_onBeforeCompileHeadComplete, before custom meta data are injected. Collects meta data set by platform.
	 * If a crawler request, we actually collect and store the meta data.
	 * If a regular request, we load any meta data record we have, compare meta set by the platform and if
	 * modified, set that URL to be recrawled.
	 *
	 * @return Pagedatacollector
	 */
	public function collectPlatformMetaData()
	{
		try
		{
			if (!$this->canRun())
			{
				$this->shouldProcessCurrentPage = false;

				if (!$this->shouldCollectPageData(true))
				{

					$this->currentPage->set('ignore', true);
				}

				return $this;
			}

			if ($this->currentPage->isTruthy('ignore'))
			{
				return $this;
			}

			if (
				!$this->crawlerHelper->isCrawlerRequest()
				&&
				(
					empty($this->storedCurrentPage)
					||
					!$this->storedCurrentPage->exists()
				)
			) {
				// don't deal with external requests.
				return $this;
			}

			// Get the meta data set by the platform
			$requestInfo = $this->factory->getThe('forseo.requestInfo');
			$metaData    = $this->currentMeta->getMeta();

			// compare them to what we have in stock
			$metaHasChanged = $this->factory
				->getA(Helper\Meta::class)
				->platformMetaHasChanged(
					$requestInfo,
					$metaData
				);

			if (
				$metaHasChanged
				&&
				!$this->crawlerHelper->isCrawlerRequest()
			) {
				// put back the URL onto collected_urls for recrawl.
				$this->linksCollectorHelper
					->storeCollectedLinks(
						[
							$this->filteredUrl
						],
						$this->currentPage,
						[
							'forceCollection' => true
						]
					);

				return $this;
			}

			if (
				$metaHasChanged
				&&
				$this->crawlerHelper->isCrawlerRequest()
			) {
				$metaData = Wb\arraySet($metaData, ['platform', 'title'], $requestInfo->get('page_title'));
				$metaData = Wb\arraySet($metaData, ['platform', 'description'], $requestInfo->get('page_description'));
				$metaData = Wb\arraySet($metaData, ['platform', 'canonical'], $requestInfo->get('page_canonical'));
				$metaData = Wb\arraySet($metaData, ['platform', 'robots'], $requestInfo->get('page_robots'));
				$metaData = Wb\arraySet($metaData, ['auto', 'description'], $requestInfo->get('page_auto_description'));
				$metaData = Wb\arraySet($metaData, ['auto', 'canonical'], $requestInfo->get('page_auto_canonical'));
				$metaData = Wb\arraySet($metaData, ['auto', 'image'], $requestInfo->get('page_image'));
				$metaData = Wb\arraySet($metaData, ['auto', 'sharing_image'], $requestInfo->get('page_sharing_image'));
				$this->currentMeta
					->set(
						[
							'content_id' => $this->currentPage->get('content_id'),
							'url'        => $this->currentPage->get('url'),
							'data'       => $metaData
						]
					)->timestamp('crawled_at')
					->store();

				$this->logger->debug('PDC collectPlatformMetaData added/updated meta data record id: %s, contentId: %s, URL: %s', $this->currentMeta->getId(), $this->currentMeta->get('content_id'), $this->currentMeta->get('url'));
			}

		}
		catch (\Throwable $e)
		{
			$this->logger->error('PDC onAfterRenderComplete data collection %s::%d %s %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}

		return $this;
	}

	/**
	 * Collects current page content information and store to database as needed.
	 *
	 * NB: some redirects happen before onAfterRender and some after.
	 *
	 * - request for user profile are redirected to login page before onAfterRespond
	 * - Banners redirect are redirected after onAfterRender
	 *
	 * So we cannot assume only fully rendered pages will be seen at onAfterRender.
	 *
	 */
	public function onAfterRenderComplete()
	{
		// run conditions: frontend, html document,...
		if (!$this->canRun())
		{
			$this->shouldProcessCurrentPage = false;

			if (!$this->shouldCollectPageData(true))
			{
				$this->currentPage->set('ignore', true);
			}

			if ($this->currentPage->isTruthy('ignore'))
			{
				// store as an excluded URL if filters and plugins decided to exclude that URL.
				$this->linksCollectorHelper
					->addExcludedUrl(
						$this->filteredUrl
					);
			}

			$this->logger->debug('PDC onAfterRenderComplete, canRun is false for: ' . print_r($this->currentPage->get(), true));

			return;
		}

		try
		{
			// extract links from content
			$linksCollector = $this->factory
				->getA(
					Linkscollector::class,
					$this->currentPage
				);

			$currentPageLinksHash = $linksCollector->extractLinksFromBody(
				Wb\initEmpty(
					$this->platform->getDocumentContent(),
					''
				),
				false // $wrapContentInHtmlDoc
			);
			$this->currentPage->set(
				'hash_links',
				$currentPageLinksHash
			);

			// extract images from content
			$imagesCollector = $this->factory
				->getA(
					Imagescollector::class,
					$this->currentPage
				);

			$currentPageImagesHash = $imagesCollector->extractImagesFromBody(
				Wb\initEmpty(
					$this->platform->getDocumentContent(),
					''
				),
				false // $wrapContentInHtmlDoc
			);
			$this->currentPage->set(
				'hash_images',
				$currentPageImagesHash
			);

			$this->currentPage->set(
				'canonical_auto',
				$this->pageHelper->canonicalType(
					$this->currentPage
				)
			);

			// determine possibly modified sitemap inclusion status
			$currentSitemapAuto = !empty($this->storedCurrentPage) && $this->storedCurrentPage->exists()
				? $this->storedCurrentPage->get('sitemap_auto')
				: null;

			if (!is_null($currentSitemapAuto))
			{
				$this->currentPage->set(
					'sitemap_auto',
					$currentSitemapAuto
				);
			}

			$newSitemapAuto = $this->pageHelper->shouldIncludeInSitemap(
				$this->currentPage,
				$this->storedCurrentPage,
				Data\Sitemap::CONTENT,
				$this->factory
					->getA(Rules::class)
					->getRulesPerType(
						Data\Rule::TYPE_SITEMAP
					)
			);

			// update the page record with the new decision
			// so that currentPage is up to date
			$this->currentPage->set(
				'sitemap_auto',
				$newSitemapAuto
			);

			$isCrawlerRequest = $this->crawlerHelper->isCrawlerRequest();
			if (
				!$isCrawlerRequest
				&&
				!empty($this->storedCurrentPage)
				&&
				$this->storedCurrentPage->exists()
			) {
				$storedPageLinksHash  = $this->storedCurrentPage->get('hash_links');
				$storedPageImagesHash = $this->storedCurrentPage->get('hash_images');
				if (
					$storedPageLinksHash !== $currentPageLinksHash
					||
					$storedPageImagesHash !== $currentPageImagesHash
					||
					$newSitemapAuto !== $currentSitemapAuto
				) {
					$this->logger->debug('PDC onAfterRenderComplete, detected change in links or images in content or sitemap status, adding to collected URLs for recrawl: ' . print_r($this->currentPage->get(), true));

					// if a regular request, not crawler, and that page has already been crawled
					// but we found a different set of links or images in it:
					// we put it up for recrawling
					$this->linksCollectorHelper
						->storeCollectedLinks(
							[
								$this->filteredUrl
							],
							$this->currentPage,
							[
								'forceCollection' => true
							]
						);

					return;
				}
			}

			if (!$isCrawlerRequest)
			{
				$this->processUnknownIncomingRequests();

				// no further processing, we've stored this request URL
				// to the collected_urls table for later processing.
				return;
			}

			// we have seen this page before, we may have to update it if it has changed
			// whether it was a Page, a Link or even an Error previously
			// AND whether the request is from the crawler or a regular external visitor.
			// The difference here is that if a visitor, the only action taken can be to put the URL
			// back up for recrawl, instead of spending time working on it.

			$this->logger->debug('PDC onAfterRenderComplete, valid page request from our crawler: ' . print_r($this->currentPage->get(), true));

			// run all platform-specific data collection code
			$this->currentPage = $this->platformCollector
				->onAfterRender($this->currentPage);

			// let others modify or act upon collected data
			$this->filterCollectedData('after_render');

			// do we have non-sef vars? else it's a 404, for instance for a loose image or media
			// later on Joomla will trigger a 404 but we don't want to store anything now.
			// NB: this should only happen on Joomla 3 with legacy routing or when using sh404SEF
			// Modern routing will trigger a 404 right away which will go to onError
			if ($this->currentPage->isFalsy('non_sef_vars'))
			{
				$this->currentPage->set('ignore', true);
			}

			// now process unless set to ignore the page.
			if ($this->currentPage->isTruthy('ignore'))
			{
				// store as an excluded URL if filters and plugins decided to exclude that URL.
				$this->linksCollectorHelper
					->addExcludedUrl(
						$this->filteredUrl
					);

				$this->logger->debug('PDC onAfterRenderComplete: ignoring page ' . print_r($this->currentPage->get(), true));

				return;
			}

			$this->storedCurrentPage = empty($this->storedCurrentPage)
				? $this->linksCollectorHelper
					->urlAlreadySeen(
						$this->filteredUrl,
						true // $excludeCollected
					)
				: $this->storedCurrentPage;

			$this->pageHelper->ensureUniqueAutoCanonical(
				$this->currentPage
			)->ensureCanonicalIsInSitemap(
				$this->currentPage
			);

			/** @var  Helper\Meta $metaHelper */
			$metaHelper = $this->factory
				->getA(Helper\Meta::class);
			$metaRobots = $this->factory
				->getThe('forseo.requestInfo')
				->getMetaRobots();

			// store as a page, unless if noindex
			if (
				$this->config->isTruthy('collectApplyNoIndex')
				&&
				$metaHelper->hasMetaNoindex($metaRobots))
			{
				// store as an excluded URL if filters and plugins decided to exclude that URL.
				$this->linksCollectorHelper
					->addExcludedUrl(
						$this->filteredUrl
					);

				$this->logger->debug('PDC onAfterRenderComplete: page has meta noindex, storing as excluded ' . print_r($this->currentPage->get(), true));
			}
			else
			{
				$this->factory
					->getA(Model\Pagedatacollector::class)
					->storePage(
						$this->currentPage,
						$this->storedCurrentPage,
						$isCrawlerRequest
					);
			}

			// Store collected links, except if the page is nofollow
			if (
				$this->config->isFalsy('collectApplyNoFollow')
				||
				!$metaHelper->hasMetaNofollow($metaRobots))
			{
				$linksCollector->store();
			}

			$imagesCollector->store();
		}
		catch (\Throwable $e)
		{
			$this->logger->error('PDC onAfterRenderComplete data collection %s::%d %s %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
	}

	/**
	 * Store current request to the collected URLs list if:
	 *
	 * - not already seen
	 * - not our crawler requesting it
	 *
	 * This feature is behind a feature flag, collectIncomingRequest. See ticket #172 for details.
	 *
	 */
	private function processUnknownIncomingRequests()
	{
		// It's a succesful page now - and not yet a page record - but maybe it was previously an error or a link?
		// If so, re-crawl to update our records.
		$this->forceRecrawlIfExisting(Data\Error::class)
			 ->forceRecrawlIfExisting(Data\Link::class);

		// feature enabled?
//		if ($this->config->isFalsy('collectIncomingUrls'))
//		{
//			return;
//		}

		// put back the URL onto collected_urls for recrawl.
		// The linksCollector helper will check whether we've seen this link
		// before and whether some filters apply
//		$this->linksCollectorHelper
//			->storeCollectedLinks(
//				[
//					$this->filteredUrl
//				],
//				$this->currentPage,
//				[
//					'isUnknownRequest' => true
//				]
//			);
	}

	/**
	 * Collect data about errors on the site
	 *
	 * @param \Exception $error
	 * @param array      $options
	 *                      bool onlyGuests
	 * @throws \Exception
	 */
	public function onError($error, $options = [])
	{
		$errorCode = $error->getCode();

		$this->currentPage = $this->factory
			->getA(Platform\Pagecollector::class)
			->onAfterRoute(
				$this->factory->getA(Data\Page::class)
			)->set(
				[
					'ignore' => true,  // prevent storing as a Page
					'status' => $errorCode
				]
			);

		if (System\Http::RETURN_NOT_FOUND !== $errorCode)
		{
			$this->logger->error('PDC controller onError before canRun() %s::%d %s %s', $error->getFile(), $error->getLine(), $error->getMessage(), $error->getTraceAsString());
		}

		$shouldCollectErrorData =
			$this->platform->isFrontend()
			&&
			!$this->platform->isOffline()
			&&
			$this->platform->isHtmlPage();

		if (!$shouldCollectErrorData)
		{
			return;
		}

		if (
			Wb\arrayIsTruthy($options, 'onlyGuests')
			&&
			!$this->platform->isGuest()
		) {
			return;
		}

		if ($this->crawlerHelper->isCrawlerRequest())
		{
			if (System\Http::RETURN_NOT_FOUND !== $errorCode)
			{
				$this->logger->debug('PDC controller onError: this is a crawler request, exiting controller error handler without storing anything');
			}
			// the crawler will record itself any error on requests
			// it started.
			return;
		}

		// This request triggered an error. If it was considered a successul page before
		// we want to re-crawl it and possibly demote it to an error -which includes
		// removing it from sitemap for instance.
		$this->forceRecrawlIfExisting(
			Data\Page::class
		);

		$this->logIncoming404s(
			$error,
			$errorCode
		);

		// If an error page display rule has been created, run it
		$rules = $this->factory
			->getA(Rules::class)
			->getRulesPerType(
				Data\Rule::TYPE_ERROR_PAGE
			);

		if (!empty($rules))
		{
			$this->factory->getA(Helper\Errorpage::class)
						  ->render(
							  $rules,
							  $error,
							  $this->currentPage
						  );
		}
	}

	/**
	 * If this is a known page, link or error, we may need to update our records:
	 *
	 * - demote from page to error
	 * - remove from sitemap
	 * - update back from error to page
	 *
	 * To find out, we put the page back up for re-crawl.
	 *
	 * @return $this;
	 */
	private function forceRecrawlIfExisting($class)
	{
		$record = $this
			->factory
			->getA($class)
			->loadPerUrl(
				$this->currentPage->get('full_url')
			);

		if ($record->exists())
		{
			$this->factory
				->getThe('forseo.linksCollectorHelper')
				->storeCollectedLinks(
					[
						$this->currentPage->get('full_url')
					],
					null,
					[
						'forceCollection' => true
					]
				);
		}

		return $this;
	}

	/**
	 * Log to database an incoming error, if set to do so and
	 * after filtering out obvious garbage.
	 *
	 * @param \Throwable $error
	 * @param int        $errorCode
	 * @throws \Exception
	 */
	private function logIncoming404s($error, $errorCode)
	{
		if (!$this->shouldLogIncoming404s($errorCode))
		{
			return;
		}

		// Filter out garbage
		$url     = '/' . $this->currentPage->get('full_url');
		$filters = $this->factory->getThis('forseo.config', 'app')->get('errorLogBypass', []);
		foreach ($filters as $filter)
		{
			// is it a regular expression?
			if (Wb\startsWith($filter, '~'))
			{
				if (preg_match(
					$filter,
					$url
				))
				{
					return;
				}
			}

			if (System\Route::matchUrlRule(
				$filter,
				$url
			))
			{
				return;
			};
		}

		/**
		 * Filter whether data for the current error should be collected.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\frontend\collection
		 * @var forseo_should_collect_error
		 * @since   1.0.0
		 *
		 * @param bool      $shouldCollectPageData
		 * @parma \Exception $error
		 * @param Data\Page $pageData
		 *
		 * @return bool
		 *
		 */
		$shouldCollectErrorData = $this->factory->getThe('hook')->filter(
			'forseo_should_collect_error',
			true,
			$error,
			$this->currentPage
		);

		if ($shouldCollectErrorData)
		{
			$this->factory
				->getA(Model\Pagedatacollector::class)
				->storeError(
					$error,
					$this->currentPage
				);;
		}
	}

	/**
	 * Decide if a 404 should not be logged based on user setting. By-pass user setting
	 * in the case of likely images or videos.
	 *
	 * Broken links to images and videos are not checked by 4SEO so this is the only chance to
	 * detect them.
	 *
	 * @param int $errorCode
	 * @return bool
	 * @throws \Exception
	 */
	private function shouldLogIncoming404s($errorCode)
	{
		if (System\Http::RETURN_NOT_FOUND !== $errorCode)
		{
			return true;
		}

		if ($this->config->isFalsy('collectIncoming404s', false))
		{
			return false;
		}

		return true;
	}

	/**
	 * Getter for the current page information object.
	 *
	 * @return Data\Page
	 */
	public function get()
	{
		return $this->currentPage;
	}

	/**
	 * Getter for the current page information object stored in the db
	 * from previous crawls, if there's one.
	 *
	 * @return Data\Page
	 */
	public function getStored()
	{
		return $this->storedCurrentPage;
	}

	/**
	 * Getter for the current request URL, after filtering out
	 * common query vars.
	 *
	 * @return string
	 */
	public function getFilteredUrl()
	{
		return $this->filteredUrl;
	}

	/**
	 * Getter for the current page meta data information object.
	 *
	 * @return Data\Meta
	 */
	public function getMeta()
	{
		return $this->currentMeta;
	}

	/**
	 * Checks all running condition plus the fact this is a request
	 * by our crawler.
	 *
	 * @return bool
	 * @throws \Exception
	 */
	private function canRunAndIsCrawlerRequest()
	{
		return
			$this->canRun()
			&&
			$this->crawlerHelper->isCrawlerRequest();
	}

	/**
	 * Check whether current request is on frontend and for an HTML page.
	 *
	 * @return bool
	 * @throws \Exception
	 */
	private function canRun()
	{
		$canRun = $this->config->isTruthy('collectionEnabled')
				  &&
				  $this->shouldProcessCurrentPage
				  &&
				  'GET' == $this->platform->getMethod()
				  &&
				  $this->platform->isFrontend()
				  &&
				  !$this->platform->isOffline()
				  &&
				  $this->platform->isGuest()
				  &&
				  $this->platform->isHtmlPage();

		if (!$canRun)
		{
			return false;
		}

		$currentPageExtension = $this->currentPage->get('extension');
		if (
			!empty($currentPageExtension)
			&&
			in_array($currentPageExtension, $this->factory->getThis('forseo.config', 'app')->get('ignoredExtensions')))
		{
			return false;
		}

		return true;
	}

	/**
	 * Apply filter on page data collection for plugins to decide
	 * whether it's worth spending time on this page.
	 *
	 * @param bool $shouldCollectPageData
	 *
	 * @return bool
	 */
	private function shouldCollectPageData($shouldCollectPageData)
	{
		/**
		 * Filter whether data for the current page should be collected.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\frontend\collection
		 * @var forseo_should_collect_page_data
		 * @since   1.0.0
		 *
		 * @param bool      $shouldCollectPageData
		 * @param Data\Page $pageData
		 *
		 * @return bool
		 *
		 */
		return $this->factory->getThe('hook')->filter(
			'forseo_should_collect_page_data',
			$shouldCollectPageData,
			$this->currentPage
		);
	}

	/**
	 * Filters page data at various steps of the collection process.
	 *
	 * NB: Filters doc is listed here to be picked up and included
	 * in website documentation.
	 *
	 * @param string $event
	 */
	private function filterCollectedData($event)
	{
		/**
		 * Filter the data collected at onAfterRoute about the current request.
		 * Setting the ignore field to true will cause the data gathering process to stop
		 * and the page data will not be stored any further.
		 *
		 * @param Data\Page $pageData
		 *
		 * @return bool
		 * @var forseo_after_route_page_data
		 *
		 * @api     forseo
		 *
		 * @package 4SEO\filter\frontend\collection
		 *
		 * @since   1.0.0
		 *
		 */

		/**
		 * Filter the data collected at onAfterRender about the current request.
		 * Setting the ignore field to true will cause the data gathering process to stop
		 * and the page data will not be stored any further.
		 *
		 * @api     forseo
		 * @var forseo_after_render_page_data
		 * @package 4SEO\filter\frontend\collection
		 * @since   1.0.0
		 *
		 * @param Data\Page $pageData
		 *
		 * @return bool
		 *
		 */

		$filterName        = 'forseo_' . $event . '_page_data';
		$this->currentPage = $this->factory->getThe('hook')->filter(
			$filterName,
			$this->currentPage
		);
	}
}
