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

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Db;
use Weeblr\Wblib\Forseo\Route;

use Weeblr\Forseo\Data;
use Weeblr\Forseo\Helper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Crawler extends Base\Base
{
	/**
	 * @const Crawler is in running state
	 */
	public const STATE_RUNNING = 'running';

	/**
	 * @const Crawler is in error state
	 */
	public const STATE_ERROR = 'error';

	/**
	 * @const Possible sources triggering a cron request
	 */
	public const CRON_SOURCE_IMAGE = 'image';
	public const CRON_SOURCE_HTTP  = 'http';
	public const CRON_SOURCE_PHP   = 'php';

	/**
	 * @var string Unique uuidV1 for this crawler run.
	 */
	private $id = null;

	/**
	 * @var bool Flag for when running a crawl is forced (from the backend UI normally)
	 */
	private $forceRun = false;

	/**
	 * @var int What triggered a crawl execution
	 */
	private $source = self::CRON_SOURCE_IMAGE;

	/**
	 * @var bool Flag for when crawling a URL immediately
	 */
	private $immediateRun = false;

	/**
	 * @var Urls Convenience instance of Urls model.
	 */
	private $urlsModel = null;

	/**
	 * @var Helper\Crawler A helper for crawler-related features.
	 */
	private $crawlerHelper = null;

	/**
	 * @var Keystore General purpose storage.
	 */
	private $keystore = null;

	/**
	 * @var System\Config Holds the page collection config object.
	 */
	private $pagesConfig = null;

	/**
	 * @var System\Config Holds the system config object.
	 */
	private $systemConfig = null;

	/**
	 * @var System\Config Holds the application static config object.
	 */
	private $appConfig = null;

	/**
	 * @var Db\Helper Database access helper.
	 */
	protected $dbHelper = null;

	/**
	 * @var System\Logger Convenience logger instance.
	 */
	private $logger = null;

	/**
	 * @var array List of URLs to crawl this run.
	 */
	private $toCrawl = [];

	/**
	 * @var int Counter for URLs actually crawled.
	 */
	private $crawled = 0;

	/**
	 * @var int Counter for urls crawling errors.
	 */
	private $errors = 0;

	/**
	 * @var int Counter for urls crawled but with error HTTP status.
	 */
	private $pageErrors = 0;

	/**
	 * Assign a unique uuidV1. Better V1 than v4 as they are indexed
	 * in DB.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->urlsModel     = $this->factory->getA(Urls::class);
		$this->pagesConfig   = $this->factory->getThis('forseo.config', 'pages');
		$this->systemConfig  = $this->factory->getThis('forseo.config', 'system');
		$this->appConfig     = $this->factory->getThis('forseo.config', 'app');
		$this->dbHelper      = $this->factory->getThe('db');
		$this->crawlerHelper = $this->factory->getThe('forseo.crawlerHelper');
		$this->keystore      = $this->factory->getThe('forseo.keystore');
		$this->logger        = $this->factory->getThe('forseo.logger');
	}

	/**
	 * Pick some collected URLs and fetch them, which will trigger data collection when they
	 * are rendered.
	 * URLs are collected by the Collector controller.
	 *
	 * @param bool $force
	 * @param int  $source
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function run($force = false, $source = self::CRON_SOURCE_IMAGE)
	{
		$this->forceRun = $force;
		$this->source   = $source;

		if (
			$force
			||
			$this->pagesConfig->get('collectionEnabled')
		) {
			$this->id = System\Auth::uuidv1();

			$this->logger->debug(__METHOD__ . ': Starting crawl run, id ' . $this->id);

			$result = $this->purgeCrawls()
						   ->grabUrls()
						   ->loadUrlsToCrawl()
						   ->crawlUrlBatch();

			if ($result instanceof \Throwable)
			{
				$this->logger->error(__METHOD__ . ': crawl ' . $this->id . ' stopped with an error, ' . $result->getMessage());
				return $result;
			}

			$this->checkIfComplete();

			$this->logger->debug(__METHOD__ . ': Ending crawl run, id ' . $this->id . ', crawled: ' . $this->crawled . ', crawl errors: ' . $this->errors . ', pages with errors: ' . $this->pageErrors);
		}
		else
		{
			$this->logger->debug(__METHOD__ . ': NOT running crawler, collectionEnabled is ' . ($this->pagesConfig->get('collectionEnabled') ? 'enabled' : 'disabled'));
		}

		return $this->status();
	}

	/**
	 * Reports on current crawlser status.
	 *
	 * @return array
	 */
	public function status()
	{
		return [
			'enabled'           => $this->pagesConfig->get('collectionEnabled'),
			'backgroundEnabled' => $this->systemConfig->get('clientCron'),
			'crawled'           => $this->dbHelper->count('#__forseo_pages'),
			'pending'           => $this->dbHelper->count('#__forseo_collected_urls'),
			'errors'            =>
				$this->dbHelper->count(
					'#__forseo_pages',
					'*',
					[
						['status', '!=', Data\Url::STATUS_OK]
					]
				)
				+
				$this->dbHelper->count('#__forseo_errors')
			,
		];
	}

	/**
	 * Restart an entirely new crawl. This is done by:
	 *
	 * - Clearing those tables:
	 *
	 *      - #__forseo_collected_urls
	 *      - #__forseo_errors (only records with source == 1, ie crawl errors)
	 *      - #__forseo_links
	 *      - #__forseo_pages
	 *      - #__forseo_referrers
	 *      - #__forseo_referrers_errors
	 *      - #__forseo_referrers_links
	 *      - #__forseo_referrers_pages
	 *
	 * NB: meta data and user-generated data should not be deleted. Only that data resulting
	 * from crawl.
	 *
	 */
	public function reset()
	{
		$this->logger->debug(__METHOD__ . ': Resetting crawler , current status ' . ($this->pagesConfig->get('enabled') ? 'enabled' : 'disabled'));

		$truncateTableList = [
			'#__forseo_collected_urls',
			'#__forseo_excluded_urls',
			'#__forseo_links',
			'#__forseo_pages',
			'#__forseo_referrers',
			'#__forseo_referrers_errors',
			'#__forseo_referrers_links',
			'#__forseo_referrers_pages',
			'#__forseo_images'
		];

		foreach ($truncateTableList as $table)
		{
			$this->dbHelper->truncate($table);
		}

		$this->dbHelper->delete(
			'#__forseo_errors',
			[
				'source' => Data\Url::SOURCE_CRAWL
			]
		);

		$this->keystore
			->delete('crawl.in_progress');

		/**
		 * Filter whether the last completed crawl data (namely the sitemap)
		 * should be deleted when resetting analysis data.
		 *
		 * Off by default before 2025-02-12, on after that.
		 *
		 * With it enabled, any existing sitemap will keep being served
		 * as long as user does not click the Rebuild sitemap button. Hitting that
		 * button would cause the sitemap to be rebuilt right away, and as analysis
		 * has just been reset, the sitemap would become empty.
		 * This is a risk to take, as it allows background analysis to restart
		 * analysis automatically and immediately.
		 * Without deleting the crawl.completed flag, background analysis does not restart
		 * because if there's no URL to crawl, it sees that a crawl was already completed
		 * and does not see a need to start a new one. Only using Analyze Now button
		 * to trigger a manual analysis can restart the analysis process.
		 * This may have been a source of trouble if users do a Reset analysis without
		 * following that up with a manual analysis.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\cron
		 * @var forseo_delete_last_crawl_on_reset
		 * @since   4.6.0
		 *
		 * @param bool $deleteLastCompletedCrawl
		 *
		 * @return bool
		 *
		 */
		$deleteLastCompletedCrawl = $this->factory
			->getThe('hook')
			->filter(
				'forseo_delete_last_crawl_on_reset',
				true
			);

		if ($deleteLastCompletedCrawl)
		{
			$this->keystore
				->delete('crawl.completed');
		}

		$this->pagesConfig
			->set(
				'crawlerStatus',
				static::STATE_RUNNING
			)->store();

		$this->factory->getThe('forseo.msgManager')->deleteByMsgId('dashboard.resetRequiredAfterUpdate');

		return $this->status();
	}

	/**
	 * Crawl immediately one or more URLs passed in parameters. Possibly include in sitemap if succesful.
	 *
	 * @param array $params
	 * @return array
	 * @throws \Exception
	 */
	public function crawl($params)
	{
		// Validation
		$urls = Wb\ArrayGet($params, 'urls', []);
		$url  = array_shift($urls);
		if (
			empty($url)
			||
			!Wb\startsWith($url, '/')
		) {
			throw new \Exception('No or invalid URL to crawl provided', System\Http::RETURN_BAD_REQUEST);
		}

		$this->immediateRun = true;

		// we remove the leading slash. It's only there to ensure users enter proper, internal URLs.
		$url = Wb\lTrim($url, '/');

		// Delete any prior exclusion for this URL
		$excludedUrl = $this->factory
			->getA(Data\Excluded::class)
			->set(
				[
					'full_url' => $url
				]
			)->loadPerUrl($url);

		if ($excludedUrl->exists())
		{
			$excludedUrl->delete();
		}

		// Delete any prior, failed attempt for this URL, or pending instance
		// which would cause a duplicate key error
		$collectedUrl = $this->factory
			->getA(Data\Collected::class)
			->set(
				[
					'full_url' => $url
				]
			)->loadPerUrl($url);

		if ($collectedUrl->exists())
		{
			$collectedUrl->delete();
		}

		$this->factory
			->getA(Data\Collected::class)
			->set(
				[
					'full_url'    => $url,
					'click_depth' => 0,
					'priority'    => Data\Collected::PRIORITY_IMMEDIATE,
				]
			)->store();

		$this->id = System\Auth::uuidv1();

		$result = $this->grabUrls()
					   ->loadUrlsToCrawl()
					   ->crawlUrlBatch();

		if ($result instanceof \Throwable)
		{
			$this->logger->error(__METHOD__ . ': immediate crawl ' . $this->id . ' stopped with an error, ' . $result->getMessage());
			return [
				'status' => 'error',
				'errors' => [
					'message' => $result->getMessage(),
					'code'    => $result->getCode(),
				],
			];
		}

		$this->logger->debug(__METHOD__ . ': Immediate crawl ending, id ' . $this->id . ', crawled: ' . $this->crawled . ', crawl errors: ' . $this->errors . ', pages with errors: ' . $this->pageErrors);

		return [
			'status'  => 'ok',
			'message' => '',
			'errors'  => [],
		];
	}

	/**
	 * Grab a set of URLs from collected_urls and mark them up for crawling
	 * by this crawler instance.
	 *
	 * @return Crawler
	 * @throws \Exception
	 */
	private function grabUrls()
	{
		// check concurrency
		$runningCrawls = $this->dbHelper->count(
			'#__forseo_collected_urls',
			'*',
			[
				['crawled_by', '!=', '']
			]
		);

		$pagesPerRun = self::CRON_SOURCE_HTTP === $this->source
			? $this->appConfig->getInt('crawlerCronPagesPerRun')
			: $this->appConfig->getInt('crawlerPagesPerRun');

		if ($runningCrawls >= $pagesPerRun * $this->appConfig->getInt('crawlerConcurrency'))
		{
			$this->logger->debug(__METHOD__ . ': grabUrls, id ' . $this->id . ', too many crawls already, aborting');

			throw new \Exception('Too many requests', System\Http::RETURN_TOO_MANY_REQUESTS);
		}

		// actually grab URLs for crawling
		$query = 'update ' . $this->dbHelper->quoteName('#__forseo_collected_urls');
		$query .= ' set '
				  . $this->dbHelper->quoteName('crawled_by') . ' = ' . $this->dbHelper->quote($this->id)
				  . ','
				  . $this->dbHelper->quoteName('crawl_started_at') . ' = ' . $this->dbHelper->quote(
				System\Date::getUTCNow('Y-m-d H:i:s', true)
			)
				  . ','
				  . $this->dbHelper->quoteName('crawl_timeout_at') . ' = ' . $this->dbHelper->quote(
				System\Date::getUTCFromNow(
					$this->appConfig->get('crawlerTimeout'),
					'Y-m-d H:i:s', // $format
					true           // $refresh
				)
			)
				  . ','
				  . $this->dbHelper->quoteName('attempts') . ' = ' . $this->dbHelper->quoteName('attempts') . ' + 1';

		$query .= ' where '
				  . $this->dbHelper->quoteName('crawled_by') . ' = ""'
				  . ' and '
				  . $this->dbHelper->quoteName('attempts') . ' < ' . $this->appConfig->getInt('crawlerMaxAttempts');

		$query .= ' and '
				  . $this->dbHelper->quoteName('priority')
				  . ($this->immediateRun ? ' = ' : ' != ')
				  . $this->dbHelper->quote(Data\Collected::PRIORITY_IMMEDIATE);

		$query .= ' order by '
				  . $this->dbHelper->quoteName('priority') . ' DESC,'
				  . $this->dbHelper->quoteName('target') . ' ASC,'
				  . $this->dbHelper->quoteName('attempts') . ' ASC,'
				  . $this->dbHelper->quoteName('click_depth') . ' ASC';

		$query .= ' limit '
				  . $pagesPerRun;

		$this->dbHelper->query($query);

		return $this;
	}

	/**
	 * Read list of URLs that have been marked as grabbed by this crawler instance.
	 *
	 * @return Crawler
	 * @throws \Exception
	 */
	private function loadUrlsToCrawl()
	{
		$this->toCrawl = $this->dbHelper
			->selectColumn(
				'#__forseo_collected_urls',
				'id',
				[
					'crawled_by' => $this->id
				]
			);

		if ($this->immediateRun)
		{
			// immediate crawl exists outside of the typical crawl cycle
			return $this;
		}

		$activeCrawl    = $this->keystore->get('crawl.in_progress');
		$completedCrawl = $this->keystore->get('crawl.completed');
		if (
			empty($this->toCrawl)
			&&
			empty($activeCrawl)
			&&
			(
				empty($completedCrawl)
				||
				$this->forceRun
			)
		) {
			$this->startNewCrawl();

			// start right away, why wait?
			$this->grabUrls();
			$this->toCrawl = $this->dbHelper
				->selectColumn(
					'#__forseo_collected_urls',
					'id',
					[
						'crawled_by' => $this->id
					]
				);
		}
		else if (!empty($this->toCrawl) && empty($activeCrawl))
		{
			// We have URLs to crawl but do we have a crawl in progress?
			// This may happen if new URLs are added to collected_urls
			// when a content or meta data change is detected and a single URL is
			// collected;
			$this->markNewCrawlStarted();
		}

		if (!empty($this->toCrawl))
		{
			$this->pagesConfig->set(
				'crawlerStatus',
				static::STATE_RUNNING
			)->store();
		}

		return $this;
	}

	/**
	 * Create a new crawl record in the keystore - without touching
	 * any completed one.
	 *
	 * @return Crawler
	 */
	private function markNewCrawlStarted()
	{
		// add a new running crawl record
		$newCrawl = [
			'started_at' => System\Date::getUTCNow('Y-m-d H:i:s', true),
			'id'         => System\Auth::uuidv4(),
		];
		$this->keystore
			->put(
				'crawl.in_progress',
				$newCrawl,
				Db\Keystore::DEFAULT_SCOPE,
				Db\Keystore::FORMAT_JSON_ARRAY
			);

		return $this;
	}

	/**
	 * If no URL found to crawl in the collected_urls table, initiate
	 * the crawl by adding the home page to the list of URLs to crawl.
	 *
	 * @return Crawler
	 */
	private function startNewCrawl()
	{
		$found = $this->crawlerHelper->homePageAlreadySeen();

		// if not, insert home page into collected_urls, next run will grab it
		if (empty($found))
		{
			$this->crawlerHelper->insertHomePage();
			$found = 1;
			$this->markNewCrawlStarted();
		}

		// store status in config object
		if (empty($found))
		{
			$this->pagesConfig->set(
				'crawlerStatus',
				self::STATE_ERROR
			)->store();
		}

		return $this;
	}

	/**
	 * Check whether there are some URLs left to crawl and mark current
	 * crawl as complete if so.
	 *
	 * @return Crawler
	 */
	private function checkIfComplete()
	{
		// count URLs left in collected_urls. If none,
		// current crawl is done.
		$urlsLeftToCrawl = $this->dbHelper->count('#__forseo_collected_urls');
		if (empty($urlsLeftToCrawl))
		{
			$this->markCurrentCrawlAsComplete();
		}

		return $this;
	}

	/**
	 * If an in_progress crawl exists, move it to a completed crawl record,
	 * overwriting any existing completed crawl record. Trigger onCrawlComplete event.
	 *
	 * @return Crawler
	 */
	private function markCurrentCrawlAsComplete()
	{
		$activeCrawl = $this->keystore->get('crawl.in_progress');
		if (!empty($activeCrawl))
		{
			$activeCrawl['completed_at'] = System\Date::getUTCNow('Y-m-d H:i:s', true);
			$this->keystore
				->put(
					'crawl.completed',
					$activeCrawl,
					Db\Keystore::DEFAULT_SCOPE,
					Db\Keystore::FORMAT_JSON_ARRAY
				)->delete('crawl.in_progress');

			$this->logger->debug(__METHOD__ . ', id: ' . $this->id . ', crawl complete: ' . print_r($activeCrawl, true));

			// make sure crawl ids are updated for everyone as they are normally cached.
			$this->crawlerHelper->updateCrawlIds();

			/**
			 * Run actions when a full crawl has just completed.
			 *
			 * @api     forseo
			 * @package 4SEO\action\crawl
			 * @var forseo_on_crawl_complete
			 * @since   1.0.0
			 *
			 * @param array $crawl
			 *
			 * @return void
			 *
			 */
			$this->factory->getThe('hook')->run(
				'forseo_on_crawl_complete',
				$activeCrawl
			);
		}

		return $this;
	}

	/**
	 * Builds the fully qualified URL to crawl starting from the link collected in a page.
	 *
	 * - make it fully qualified in all cases
	 * - when URL rewriting is not used, handle links to existing files (PDF or txt files): they should not have the
	 * URL rewriting prefix added to them.
	 *
	 * @param string $url
	 * @return string
	 */
	private function buildCrawlableUrl($url)
	{
		$needsUrlRewritingPrefix = !empty($this->platform->getUrlRewritingPrefix());

		if (
			$needsUrlRewritingPrefix
			&&
			!System\Route::isFullyQualified($url)
			&&
			// likely a physical file? do not change
			Wb\endsWith($url, $this->pagesConfig->get('crawlerPhysicalFilesExtensions', []))
		) {
			$needsUrlRewritingPrefix = !file_exists(
				Wb\slashTrimJoin(
					$this->platform->getRootPath(),
					$url
				)
			);
		}

		$encodedUrl = System\Route::urlEncodeUrl(
			$url,
			true // $encodeQuery
		);
		$this->logger->debug(__METHOD__ . ' encoding URL before crawl from ' . $url . ' to ' . $encodedUrl);

		return System\Route::absolutify(
			$encodedUrl,
			true,
			null,
			!$needsUrlRewritingPrefix
		);
	}

	/**
	 * Crawl the URLs that have been marked as grabbed by this crawler
	 * instance.
	 */
	private function crawlUrlBatch()
	{
		if (empty($this->toCrawl))
		{
			$this->logger->debug(__METHOD__ . ', id: ' . $this->id . ', crawl: no URL to crawl.');

			return $this;
		}

		$httpClient = $this->getHttpClient();
		/* @var Helper\Url */
		$urlHelper = $this->factory->getA(Helper\Url::class);

		$useHeadOnExternalLinks = $this->appConfig->isTruthy('crawlerUseHeadOnExternal');

		foreach ($this->toCrawl as $urlId)
		{
			/* @var Data\Collected */
			$url = $this->factory
				->getA(Data\Collected::class)
				->load($urlId);

			/* @var string */
			$currentRequestUrl = $this->buildCrawlableUrl(
				$url->get('full_url')
			);

			$requestHeaders        = [];
			$isExcludedRedirectUrl = false;

			$this->logger->debug(__METHOD__ . ', id: ' . $this->id . ', start crawling url ' . $currentRequestUrl);

			try
			{
				$redirectsCounter = 0;
				do
				{

					$isInternalRequest = System\Route::isInternal($currentRequestUrl);
					// Did we end up on an excluded page?
					if (
						$redirectsCounter > 0
						&&
						$isInternalRequest
					) {
						$this->logger->debug(__METHOD__ . ', redirectsCounter > 0, validating redirect target is allowed.');

						// apply exclusions rules set by users before trying to crawl the page
						$redirectUrlTarget = System\Route::makeRootRelative(
							$currentRequestUrl,
							true
						);

						$this->logger->debug(__METHOD__ . ', redirectUrlTarget made root relative as ' . $redirectUrlTarget);

						$isExcludedRedirectUrl = !$urlHelper->passExclusionRules(
							$redirectUrlTarget,
							$this->pagesConfig->get('collectionExclusions'),
							$this->pagesConfig->get('collectionInclusions')
						);
						if ($isExcludedRedirectUrl)
						{
							$this->logger->debug(__METHOD__ . ', redirectUrlTarget made root relative does not pass exclusion rules, breaking redirect loop.');
							break;
						}
					}

					$requestHeaders[FORSEO_CRAWLER_REDIRECT_COUNT_HEADER] = $redirectsCounter;
					if ($isInternalRequest)
					{
						// only add crawler secret header on internal requests.
						// On external requests, they may cause 404s if the target site
						// is also running 4SEO (which will reject such requests as it uses a different cron key)
						$requestHeaders[FORSEO_CRAWLER_SEC_HEADER] = $this->systemConfig->get('cronKey');
					}

					// cache busting
					$cacheProtectedUrl =
						$this->pagesConfig->isTruthy('crawlerBypassExternalCache')
						&&
						$isInternalRequest
							? System\Route::cacheBust(
							$currentRequestUrl,
							FORSEO_CRAWLER_CDN_BUST_VAR,
							System\Auth::uuidv4())
							: $currentRequestUrl;

					$this->logger->debug(
						__METHOD__ . ', id: ' . $this->id
						. ', starting fetch on url ' . $currentRequestUrl
						. ', requested URL ' . $cacheProtectedUrl
						. ', referrer: '
						. json_encode($url->get('referrers'))
					);

					$response     = $useHeadOnExternalLinks && !$isInternalRequest
						? $httpClient->head(
							$cacheProtectedUrl,
							$requestHeaders
						)
						: $httpClient->get(
							$cacheProtectedUrl,
							$requestHeaders
						);
					$responseCode = $response->code;

					// follow redirects that don't risk requiring a method change
					if ($responseCode >= 300 && $responseCode < 304)
					{
						$redirectsCounter++;
						if ($redirectsCounter >= $this->appConfig->getInt('crawlerMaxRedirects'))
						{
							// too many redirects, stop there
							$this->logger->debug(
								__METHOD__ . ': crawler ' . $this->id . ', too many redirects crawling ' . $url->get('full_url')
							);
							$response = new \Exception('Too many redirects', $responseCode);
							break;
						}

						// what's the new redirect target?
						$redirectTarget = System\Http::extractResponseHeader(
							$response->headers,
							'Location',
							true
						);

						$redirectTarget = $this->normalizeRedirectTarget(
							$redirectTarget,
							$currentRequestUrl
						);

						$redirectTarget = System\Route::removeCacheBust(
							$redirectTarget,
							FORSEO_CRAWLER_CDN_BUST_VAR
						);

						if ($urlHelper->areSameUrl(
							[
								$redirectTarget,
								$currentRequestUrl
							]
						))
						{
							$this->logger->debug(
								__METHOD__ . ': crawler ' . $this->id . ', Invalid redirect ' . $redirectTarget . ' from ' . $currentRequestUrl . ' crawling ' . $url->get('full_url')
							);
							$response = new \Exception('Invalid redirect: ' . $redirectTarget, $responseCode);
							break;
						}
						else
						{
							// actually follow the redirect
							$this->logger->debug(
								__METHOD__ . ': crawler ' . $this->id . ', following redirect to ' . $redirectTarget . ' crawling ' . $url->get('full_url') . ', redirects counter is ' . $redirectsCounter . ' out of max ' . $this->appConfig->getInt('crawlerMaxRedirects')
							);
							// Location header will be relative to site root, make it FQDN
							$redirectTarget = System\Route::absolutify(
								$redirectTarget,
								true
							);
							$this->logger->debug(
								__METHOD__ . ': crawler ' . $this->id . ', normalized redirect target to ' . $redirectTarget
							);
							$currentRequestUrl = $redirectTarget;
						}
					}
					else if ($responseCode >= 304 && $responseCode < 399)
					{
						// not acceptable redirect
						$this->logger->debug(
							__METHOD__ . ': crawler ' . $this->id . ', Invalid redirect code ' . $responseCode . ' crawling ' . $url->get('full_url')
						);
						$response = new \Exception('Invalid redirect code', $responseCode);
						break;
					}
					else
					{
						// request completed
						if (
							$this->isCachedExternally($response)
							&&
							$this->pagesConfig->isFalsy('crawlerBypassExternalCache')
						) {
							$this->logger->debug(
								__METHOD__ . ', id: ' . $this->id
								. ', fetch success of  ' . $currentRequestUrl
								. ', requested URL ' . $cacheProtectedUrl
								. ', is cached externally.'
							);
							return new \Exception('pagesSettings.crawlerSuggestBypassExternalCache');
						}

						// exit following possible redirects
						break;
					}
				} while (
					$redirectsCounter < $this->appConfig->getInt('crawlerMaxRedirects')
				);
			}
			catch (\Throwable $e)
			{
				$response     = $e;
				$responseCode = $e->getCode();
				$errorMessage = $e->getMessage();
				$this->logger->error(
					__METHOD__ . ', id: ' . $this->id
					. ', crawler throw error on url : ' . $currentRequestUrl
				);
				$this->logger->error('%s::%d %d / %s - %s', $e->getFile(), $e->getLine(), $responseCode, $errorMessage, $e->getTraceAsString());

				$reason = null;
				if ($this->shouldEnterHtPassword($responseCode, $url->get('full_url'), $redirectsCounter))
				{
					$reason = new \Exception('pagesSettings.crawlerSuggestHtPassword', $responseCode);
				}
				else if ($this->shouldDisableTlsChecks($responseCode, $errorMessage, $currentRequestUrl, $requestHeaders))
				{
					if (System\Route::isInternal($currentRequestUrl))
					{
						$reason = new \Exception('pagesSettings.crawlerSuggestDisableCertsCheck', $responseCode);
					}
					else
					{
						// likely invalid certificate on other site
						$responseCode = System\Http::RETURN_MISDIRECTED_REQUEST;
					}
				}

				if (
					!empty($reason)
					&&
					!$this->immediateRun
				) {
					// clear currently crawled URL so that it can be re-crawled later
					// except if an immediate crawl, where user is waiting for result.
					$url->set(
						[
							'crawled_by'       => '',
							'crawl_started_at' => null,
							'crawl_timeout_at' => null,
							'attempts'         => 0
						]
					)->store();

					return $reason;
				}
			}

			$this->crawled++;

			$url->set(
				'status',
				$responseCode
			);

			if ($this->immediateRun)
			{
				$url->delete();

				if ($response instanceof \Throwable)
				{
					return $response;
				}

				if (!System\Http::isSuccess($responseCode))
				{
					return new \Exception('crawl.crawlError', $responseCode);
				}
			}

			$this->processCrawlResponse(
				$currentRequestUrl,
				$response,
				$url,
				$redirectsCounter,
				$isExcludedRedirectUrl
			);

		}

		return $this;
	}

	private function normalizeRedirectTarget($redirectTarget, $currentRequestUrl)
	{
		$redirectTarget = is_array($redirectTarget) && !empty($redirectTarget)
			? $redirectTarget[0]
			: $redirectTarget;

		if (
			System\Route::isInternal($currentRequestUrl)
			||
			System\Route::isFullyQualified($redirectTarget)
		) {
			// internal or external but fully qualified, no problem
			return $redirectTarget;
		}

		// request is external but the server replied with a root-relative URL
		// yes, that happens. We must prepend the external domain to the redirect target.
		$host               = System\Route::getHost($currentRequestUrl);
		$scheme             = System\Route::getScheme($currentRequestUrl);
		$fqdnRedirectTarget = Wb\slashTrimJoin(
			$scheme . '://' . $host,
			$redirectTarget
		);

		$this->logger->debug('normalizeRedirectTarget: normalized external redirect during crawl from: ' . $redirectTarget . ' to ' . $fqdnRedirectTarget);

		return $fqdnRedirectTarget;
	}

	/**
	 * When response succeeded, whether it was cached by a known CDN or caching system.
	 *
	 * @param $response
	 *
	 * @return bool
	 */
	private function isCachedExternally($response)
	{
		// Cloudflare: search for cf-cache-status == HIT
		$cloudflareCacheStatus = Wb\arrayGet(
			Wb\arrayEnsure($response->headers),
			'cf-cache-status',
			''
		);

		return 'HIT' === $cloudflareCacheStatus;
	}

	/**
	 * Detect if a request may have failed due to .htaccess password applied to site, to alert
	 * user of the reason analysis it is failing.
	 *
	 * This only tested on home page and if no redirect has happened.
	 *
	 * @param string $responseCode
	 * @param string $url
	 * @param int    $redirectsCounter
	 *
	 * @return bool
	 */
	private function shouldEnterHtPassword($responseCode, $url, $redirectsCounter)
	{
		return empty($url)
			   &&
			   empty($redirectsCounter)
			   &&
			   System\Http::RETURN_UNAUTHORIZED === $responseCode;
	}

	/**
	 * Tries to perform a failing HTTP request without checking TLS certificate, to alert
	 * user of the reason analysis is failing.
	 *
	 * @param string $responseCode
	 * @param string $errorMessage
	 * @param string $currentRequestUrl
	 * @param array  $requestHeaders
	 * @return bool
	 */
	private function shouldDisableTlsChecks($responseCode, $errorMessage, $currentRequestUrl, $requestHeaders)
	{
		$testResponseCode = 0;
		if (
			0 === $responseCode
			&&
			Wb\contains($errorMessage, 'SSL')
			&&
			$this->pagesConfig->isTruthy('crawlerEnableCertsCheck')
		) {
			// failure may be due to validating TLS certs, typically on localhost
			// Test to see if we succeed with certs check disabled
			$testHttpClient = $this->getHttpClient(
				[
					'asCrawler'               => false,
					'crawlerEnableCertsCheck' => false
				]
			);
			try
			{
				$testResponse     = $testHttpClient->get(
					$currentRequestUrl,
					$requestHeaders
				);
				$testResponseCode = $testResponse->code;
			}
			catch (\Throwable $e)
			{
				$this->logger->debug(
					__METHOD__ . ': crawler ' . $this->id . ', error testing request without TLS cert check ' . $responseCode . ' testing ' . $currentRequestUrl
				);
			}
		}

		return 0 !== $testResponseCode;
	}

	/**
	 * Process the response from crawling a collected URL.
	 *
	 * @param string         $lastCrawledUrl The URL finally requested, possibly after some redirects
	 * @param Http           $response
	 * @param Data\Collected $url
	 * @param int            $redirectsCounter
	 * @param bool           $isExcludedRedirectUrl
	 *
	 * @throws \Exception
	 */
	private function processCrawlResponse($lastCrawledUrl, $response, $url, $redirectsCounter, $isExcludedRedirectUrl)
	{
		$responseCode = $url->get('status');

		// normalize $lastCrawledUrl. If local, remove domain and path
		$lastCrawledUrl = System\Route::makeRootRelative(
			$lastCrawledUrl,
			true
		);

		$isInternal = System\Route::isInternal(
			$url->get('full_url')
		);

		if ($response instanceof \Throwable)
		{
			// unable to crawl the page, no network, no response from server,...
			$this->errors++;
			$this->logger->debug(
				__METHOD__ . ', id: ' . $this->id . ', Error crawling url '
				. $url->get('full_url')
				. ': '
				. $response->getMessage() . ', was attempt ' . $url->get('attempts')
				. ', error code ' . $response->getCode()
				. ', isInternal: ' . ($isInternal ? 'yes' : 'no')
				. "\nTrace: \n" . print_r(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), true)
			);
		}

		// was able to crawl the page
		$this->logger->debug(__METHOD__ . ', id: ' . $this->id . ', Response crawling url ' . $this->getPrintableUrl($url) . ', last crawled URL: ' . $lastCrawledUrl . ', response code:' . $responseCode . ', redirects: ' . $redirectsCounter . ', isInternal: ' . ($isInternal ? 'yes' : 'no'));

		if (
			!$response instanceof \Throwable
			&&
			System\Http::isSuccess($responseCode)
		) {
			// crawl or redirects chain of crawling has ended, there was a success response
			// we store the initial URL as a link as we want to track links with redirects to signal them
			// and have them removed, except if initial URL is home page, cause that would not be an actual link)

			// If there were no redirects, then we just crawled a regular URL and the PDC
			// will have stored all its related data.

			// @TODO Ideally though, we should detect 'external' links going to alternate versions of the site such as http/s and www/non-www
			// that'd be rather useful.
			// Could have a setting, log redirect for external URLs?
			// also could restrict this external URL redirect detection (or at least storage) to http/s and www variations.

			if (
				$redirectsCounter > 0
				&&
				!$isExcludedRedirectUrl
			) {
				// internal original link
				$this->urlsModel->storeToLink(
					$url,
					$lastCrawledUrl,
					$redirectsCounter
				);
				if ($isInternal)
				{
					// Delete possible reference(s) to this URL in the Page or Error table, as we now know
					// it's covered by a redirect.
					// no need to do this for external URLs, they cannot have been stored as a page or an error.
					// @TODO: if we were to detect http/s and www/non-www variations (see todo above), we should make
					// sure to NOT delete the canonical version from Page if it's there. But that should not happen as the URL
					// is external.
					$this->urlsModel
						->deleteFromPage($url)
						->deleteFromError($url);
				}
			}

			if ($redirectsCounter == 0)
			{
				// success without any redirect. The PageDataCollector will have collected all data and
				// stored this as a Page. We delete any outstanding records for this URL as a past Error or Link.
				$this->urlsModel
					->deleteFromError($url)
					->deleteFromLink($url);
			}

			// all stored, forget about that collected URL
			// NB: we do not store referrer information on regular pages:
			// - would be a lot, as many pages are linked from menu, which appears on ALL pages, so basically all site pages would be referrers
			// - not reliable as we collect some URLs from direct external requests, for which we do not have a referrer
			$this->logger->debug(__METHOD__ . ', id: ' . $this->id . ', Successfully crawled ' . $this->getPrintableUrl($url) . ', removing from collected URLs table');
			$url->delete();
		}
		else
		{
			$this->pageErrors++;
			$this->logger->debug(__METHOD__ . ', id: ' . $this->id . ', Crawled url but got Error response ' . $this->getPrintableUrl($url) . ', status: ' . $responseCode . ', was attempt ' . $url->get('attempts'));
			// last URL crawled (the initial URL or the end of a redirect chain)
			// ended up as an error. We also store this has a link, whether internal or external
			// to identify bad links wherever they are. We know the URL is a link on the site
			// if it has a referrer
			if (!$this->immediateRun)
			{
				$this->storeAttempt(
					$url,
					$lastCrawledUrl,
					$redirectsCounter
				);
			}
		}
	}

	/**
	 * Stores the result of a (failed) attempt at crawling a URL.
	 *
	 * @param Data\Collected $url
	 *
	 * @param string         $lastCrawledUrl
	 * @param int            $redirectsCounter
	 *
	 * @return $this
	 * @throws \Exception
	 */
	private function storeAttempt($url, $lastCrawledUrl, $redirectsCounter)
	{
		// there was a response but an error happened, try again later to confirm error
		if ($url->get('attempts') < $this->appConfig->getInt('crawlerMaxAttempts'))
		{
			return $this->storeAttemptAndReset(
				$url
			);
		}

		// we're past the number of attempts, we now store that URL and stop trying
		$this->logger->debug(__METHOD__ . ' Past last attempt, marking as error, for URL:' . print_r($url->get(), true));

		/**
		 * Sometimes we get a 0 status, as some error occured somewhere in the process,
		 * and we have no been able to identify where and why. This causes apparently
		 * valid URLs to be marked as errors, which is not good.
		 * This filter allows disabling the storage of such pages as either error or links.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\crawler
		 * @var forseo_collect_zero_status_urls
		 * @since   5.1.0
		 *
		 * @param \bool $shouldCollectZeroStatusUrls
		 *
		 * @return \bool
		 *
		 */
		$shouldCollectZeroStatusUrls = $this->factory->getThe('hook')->filter(
			'forseo_collect_zero_status_urls',
			true
		);

		$storageEnabled = $shouldCollectZeroStatusUrls
						  ||
						  !empty($url->get('status'));

		// if URL has a referrer, it was a link somewhere on the site
		// In that case, we store the error as a link
		// if no referrer, this is just some page we collected (landing page, new page
		// added without a link, or that was picked up directly, not from scraping links)
		// In that case, we do store it as an error
		$referrers = $url->get('referrers');
		if (
			$storageEnabled
			&&
			empty($referrers)
		) {
			// store to page error tables, with one record per url/status code
			$this->urlsModel
				->storeToError(
					$url,
					Data\Url::SOURCE_CRAWL
				);

			$this->logger->debug(__METHOD__ . ' Past last attempt, storing as error as there was an error or there is no referrer, for URL:' . print_r($url->get(), true));
		}

		if (
			$storageEnabled
			&&
			!empty($referrers)
		) {
			// we have a referrer, this is a link
			$this->urlsModel
				->storeToLink(
					$url,
					$lastCrawledUrl,
					$redirectsCounter
				)->deleteFromPage($url);

			$this->logger->debug(__METHOD__ . ' Past last attempt, storing as link as we have a referrer, for URL:' . print_r($url->get(), true));
		}

		// delete from collected_urls
		$url->delete();

		return $this;
	}

	/**
	 * Stores a new crawl attempt for a collected URL and
	 * reset crawled_by and crawl_started_at fields to
	 * prepare for a new attempt.
	 *
	 * @param Data\Collected $url
	 *
	 * @return Crawler
	 * @throws \Exception
	 */
	private function storeAttemptAndReset($url)
	{
		$url->set(
			[
				'status'           => $url->get('status'),
				'crawled_by'       => '',
				'crawl_started_at' => null,
				'crawl_timeout_at' => null
			]
		)->store();

		return $this;
	}

	/**
	 * Search trough the collected_urls table and identify
	 * timedout crawls.
	 * If max # of attemps reached, move the URL to the error table.
	 * If not, increase # of attempts and reset crawl id and crawl_started_at
	 */
	private function purgeCrawls()
	{
		$this->logger->debug(__METHOD__ . ': purgeCrawls start');

		// Clear runs marked with crawled_by but crawl_timeout_at has passed already
		$query = 'update ' . $this->dbHelper->quoteName('#__forseo_collected_urls');
		$query .= ' set '
				  . $this->dbHelper->quoteName('crawled_by') . ' = ""'
				  . ','
				  . $this->dbHelper->quoteName('crawl_started_at') . ' = NULL'
				  . ','
				  . $this->dbHelper->quoteName('crawl_timeout_at') . ' = NULL'
				  . ','
				  . $this->dbHelper->quoteName('attempts') . ' = ' . $this->dbHelper->quoteName('attempts') . ' + 1';

		$query .= ' where '
				  . $this->dbHelper->quoteName('crawled_by') . ' != ""'
				  . ' and '
				  . $this->dbHelper->quoteName('crawl_timeout_at') . ' < UTC_TIMESTAMP()';

		// not too many in one go
		$query .= ' limit 50';

		$this->dbHelper->query($query);

		// delete attempts > config && not marked as crawled_by
		// URL was stuck for some reason but won't be picked up
		// due to attempts > max. Should have been deleted after
		// last crawl attempt.
		$stuckUrls = $this->dbHelper->selectObjectList(
			'#__forseo_collected_urls',
			['id', 'full_url', 'status'],
			$this->dbHelper->quoteName('crawled_by') . ' = ""'
			. ' and '
			. $this->dbHelper->quoteName('attempts') . ' >= ' . $this->appConfig->getInt('crawlerMaxAttempts')
		);

		if (!empty($stuckUrls))
		{
			$this->logger->debug(__METHOD__ . ': purgeCrawls: found ' . count($stuckUrls) . ' stuck URLs');

			$ids = [];
			foreach ($stuckUrls as $stuckUrl)
			{
				$ids[] = $stuckUrl->id;

				// add a filter to allow storing stuck URLs as errors or not
				// pass the URL object to the filter so that it can decide based on the URL

				// crawler could never reach the URL (network or server errors), store it in errors table as a crawl error
				if (!empty($stuckUrl->status))
				{
					$this->urlsModel
						->storeToError(
							$this->factory->getA(Data\Collected::class)
										  ->set(
											  [
												  'full_url' => $stuckUrl->full_url,
												  'status'   => $stuckUrl->status
											  ]
										  ),
							Data\Url::SOURCE_CRAWL,
							'Stuck URL, max attempts reached, no crawled_by'
						);

					$this->logger->custom(
						'code_zero',
						'purgeCrawls: storing as error ' . print_r($stuckUrl->full_url, true) . ', status code ' . print_r($stuckUrl->status, true)
					);
				}

				if (empty($stuckUrl->status))
				{
					$this->logger->custom(
						'code_zero',
						'purgeCrawls: NOT storing as error ' . print_r($stuckUrl->full_url, true) . ', status code ' . print_r($stuckUrl->status, true)
					);
				}
			}

			// remove from collected URLs
			// RESET IT instead of removing it?
			// clear crawled_by, crawl_started_at, crawl_timeout_at and attempts
			// probably status as well. Or delete but insert back to collected
			// as a fresh URL?
			// likely not. But also change priority so that it comes back later only
			$this->factory
				->getA(Data\Collected::class)
				->delete($ids);
		}

		$this->logger->debug(
			__METHOD__ . ': purgeCrawls end, crawler status is: '
			. $this->pagesConfig->get('crawlerStatus')
		);

		return $this;
	}

	/**
	 * Builds a configured HTTP client.
	 *
	 * @param array $options
	 *                      bool asCrawler Include headers to identify http client as 4SEO crawler.
	 *                      bool crawlerEnableCertsCheck
	 * @return HTTPClient
	 */
	private function getHttpClient($options = [])
	{
		$headers = [];

		$basicAuth = $this->pagesConfig->get('basicAuthId', '') . ':' . $this->pagesConfig->get('basicAuthPassword', '');
		if (':' != $basicAuth)
		{
			$headers['Authorization'] = 'Basic ' . base64_encode($basicAuth);
		}

		$curlConfig = $this->appConfig->get('crawlerCurlConfig');
		if (!Wb\arrayGet($options, 'crawlerEnableCertsCheck', $this->pagesConfig->get('crawlerEnableCertsCheck', true)))
		{
			$curlConfig = array_replace(
				$curlConfig,
				[
					CURLOPT_SSL_VERIFYPEER => false,
					CURLOPT_SSL_VERIFYHOST => 0
				]
			);
		}

		return $this->platform->getHttpClient(
			[
				'follow_location' => false,
				'timeout'         => $this->appConfig->getInt('crawlerRequestTimeout'),
				'userAgent'       => $this->appConfig->get('crawlerUserAgent'),
				'headers'         => $headers,
				'transport'       => [
					'curl' => $curlConfig
				]
			]
		);
	}

	/**
	 * Format a URL for printing in log.
	 *
	 * @param Data\Dataobject $url
	 *
	 * @return string
	 */
	private function getPrintableUrl($url)
	{
		$printable = $url->get('full_url');
		if (empty($printable))
		{
			$printable = $url->get('url');
		}
		if (empty($printable))
		{
			$printable = '/';
		}

		return $printable;
	}
}

