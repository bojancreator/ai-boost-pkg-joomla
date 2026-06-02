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

namespace Weeblr\Forseo\Model\Integrations\Google;

use Weeblr\Forseo\Model;
use Weeblr\Forseo\Helper;
use Weeblr\Forseo\Helper\Integrations as IntegrationsHelper;

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Db;

use Weeblr\Wblib\Forseo\Integrations;
use Weeblr\Wblib\Forseo\Integrations\Googleapis\v1\Google;
use Weeblr\Wblib\Forseo\Integrations\Googleapis\v1\Google\Service;
use Weeblr\Wblib\Forseo\Integrations\Googleapis\v1\GuzzleHttp\Client as GuzzleClient;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Searchconsoledata extends Base\Base
{
	public const GSC_DATA_TIMEZONE = 'America/Los_Angeles';

	public const URL_INSPECTION_CACHE_TIME = 604800; // 7 days

	public const URL_SEARCH_ANALYTICS_CACHE_TIME = 86400; // 1 day

	public const SITEMAPS_CACHE_TIME = 86400; // 1 day

	// https://developers.google.com/webmaster-tools/v1/searchanalytics/query
	public const DEFAULT_START_ROW  = 0;
	public const DEFAULT_ROW_LIMIT  = 25000;
	public const DEFAULT_DATA_STATE = 'all'; // final | all

	/**
	 * Data fetching scheduling:
	 */
	public const DAILY_FETCH_TIME_BETWEEN = 'PT1H';

	public const DAILY_FETCH_MAX_RETRY = 5;

	/**
	 * @var Service\SearchConsole An object to communicate with Google search console.
	 */
	private $searchConsoleService;

	/**
	 * @var Model\Config
	 */
	private $integrationsConfig;

	/**
	 * @var Keystore General purpose storage.
	 */
	private $keystore = null;

	/**
	 * @var IntegrationsHelper\Googlesearchconsole
	 */
	private $integrationHelper = null;

	/**
	 * @var System\Logger Convenience logger instance.
	 */
	private $logger;

	/**
	 * A class to access Google Search console.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->searchConsoleService = $this->factory->getThe('forseo.google.searchConsoleService');

		$defaultOptions = [
			'base_uri'    => 'https://www.googleapis.com/',
			'http_errors' => \false
		];

		/**
		 * Filter options used by the HTTP client used in communicating with the Google API.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\gsc
		 * @var forseo_gsc_http_client_options
		 * @since   4.2.2
		 *
		 * @param array $options An array of options to pass to the HTTP client.
		 *
		 * @return array
		 *
		 */
		$options = $this->factory
			->getThe('hook')
			->filter(
				'forseo_gsc_http_client_options',
				$defaultOptions
			);

		if ($options !== $defaultOptions)
		{
			$customHttpClient = new GuzzleClient($options);
			$this->searchConsoleService->getClient()->setHttpClient($customHttpClient);
		}

		$this->integrationsConfig = $this->factory->getThis('forseo.config', 'integrations');
		$this->keystore           = $this->factory->getThe('forseo.keystore');
		$this->logger             = $this->factory->getThe('forseo.logger');
		$this->integrationHelper  = $this->factory->getA(IntegrationsHelper\Googlesearchconsole::class);
	}

	/**
	 * Dispatch API data requests per data type method.
	 *
	 * @param array $options
	 * @return mixed
	 * @throws \Exception
	 */
	public function get($options)
	{
		$dataType   = Wb\arrayGet($options, 'type', '');
		$methodName = 'get' . ucfirst($dataType);
		if (!is_callable([$this, $methodName]))
		{
			return new \Exception('Getting invalid data type in ' . __METHOD__ . ': ' . $dataType, 500);
		}

		return $this->{$methodName}($options);
	}

	/**
	 * Retrieves sitemaps  data for the current property from the Google Search console.
	 *
	 * @param array $options
	 * @return array|\Exception
	 */
	public function getSitemapsList($options)
	{
		try
		{
			$cache = $this->platform->getCache(
				'callback',
				array(
					'defaultgroup' => '4seo_gsc_sitemaps_list',
					'lifetime'     => self::SITEMAPS_CACHE_TIME,
					'caching'      => 1,
				)
			);

			$property     = $this->integrationHelper->getCurrentProperty();
			$forceRefresh = Wb\arrayGet($options, 'forceRefresh');
			unset($options['forceRefresh']);

			$cacheId = md5(
				$property . json_encode($options)
			);

			if ('true' === $forceRefresh)
			{
				$cache->remove($cacheId);
			}

			$sitemapsData = $cache->get(
				[
					$this,
					'doGetSitemapsList'
				],
				$property,
				$cacheId
			);
		}
		catch (Service\Exception $exception)
		{
			$this->integrationHelper->logConsoleError(
				$exception,
				'Error reading sitemaps list with Google. More details in log files on the server.'
			);
		}
		catch (\Exception $e)
		{
			return $e;
		}


		return [
			'data' => $sitemapsData,
			'meta' => [
				'count' => 1,
				'total' => 1
			]
		];
	}

	/**
	 * Retrieves sitemaps list from the Google Search console.
	 *
	 * @params string $property
	 * @return array|\Exception
	 *
	 * @throws \Exception
	 */
	public function doGetSitemapsList($property)
	{
		$fetchStart   = microtime(true);
		$sitemapsList = $this->searchConsoleService->sitemaps->listSitemaps($property);
		$fetchEnd     = microtime(true);
		if (empty($sitemapsList))
		{
			throw new \Exception('Cannot connect to Google Search Console, maybe try again later?', System\Http::RETURN_BAD_REQUEST);
		}

		$renderedList = [];
		$sitemaps     = $sitemapsList->getSitemap();
		$sitemaps     = empty($sitemaps)
			? []
			: $sitemaps;

		foreach ($sitemaps as $sitemap)
		{
			$renderedList[] = $sitemap->toSimpleObject();
		}

		return [
			'fetchedAt' => System\Date::getUTCNow(),
			'fetchedIn' => $fetchEnd - $fetchStart,
			'property'  => $property,
			'sitemaps'  => $renderedList
		];
	}

	/**
	 * Retrieves URL inspection data for a URL from the Google Search console.
	 *
	 * @param array $options
	 * @return array|\Exception
	 */
	public function getUrlInspection($options)
	{
		$url = Wb\arrayGet(
			$options,
			'url'
		);

		$url = rawurldecode($url);

		if (!$this->validateInspectedUrl($url))
		{
			return new \Exception('Trying to get Search console data for an invalid or empty URL ' . print_r($url, true), System\Http::RETURN_BAD_REQUEST);
		}

		try
		{
			$cache = $this->platform->getCache(
				'callback',
				array(
					'defaultgroup' => '4seo_gsc_url_inspection',
					'lifetime'     => self::URL_INSPECTION_CACHE_TIME,
					'caching'      => 1,
				)
			);

			$cacheId = md5($url);
			if ('true' === Wb\arrayGet($options, 'forceRefresh'))
			{
				$cache->remove($cacheId);
			}

			$inspectUrlResults = $cache->get(
				[
					$this,
					'doGetUrlInspection'
				],
				$url,
				$cacheId
			);
		}
		catch (Service\Exception $exception)
		{
			return $this->integrationHelper->logConsoleError(
				$exception,
				'Error verifying site with Google. More details in log files on the server.'
			);
		}
		catch (\Exception $e)
		{
			return $e;
		}


		return [
			'data' => $inspectUrlResults,
			'meta' => [
				'count' => 1,
				'total' => 1
			]
		];
	}

	/**
	 * Retrieves URL inspection data for a URL from the Google Search console.
	 *
	 * @param string $options
	 * @return array|\Exception
	 * @throws \Exception
	 */
	public function doGetUrlInspection($url)
	{
		$inspectionRequest = new Service\SearchConsole\InspectUrlIndexRequest();
		$inspectionRequest->setInspectionUrl($url);
		$inspectionRequest->setSiteUrl(
			Wb\arrayGet(
				$this->factory->getThis('forseo.config', 'integrations')->get('gscProperty')
				,
				0
			)
		);
		$inspectionRequest->setLanguageCode(
			$this->platform->getCurrentLanguageTag()
		);

		$fetchStart         = microtime(true);
		$inspectUrlResponse = $this->searchConsoleService->urlInspection_index->inspect($inspectionRequest);
		$fetchEnd           = microtime(true);
		if (empty($inspectUrlResponse))
		{
			throw new \Exception('Cannot connect to Google Search Console, maybe try again later?', System\Http::RETURN_BAD_REQUEST);
		}

		$inspectUrlResults = $inspectUrlResponse->getInspectionResult()->toSimpleObject();
		$this->logger->debug('URL inspection request raw data' . print_r($inspectUrlResults, true));

		return [
			'fetchedAt'     => System\Date::getUTCNow(),
			'fetchedIn'     => $fetchEnd - $fetchStart,
			'url'           => $url,
			'urlInspection' => $inspectUrlResults
		];
	}

	/**
	 * Run some basic syntax tests on a URL before trying to inspect it with Seach console.
	 * @param string $url
	 * @return bool
	 */
	private function validateInspectedUrl($url)
	{
		if (
			!System\Route::isFullyQualified($url)
			||
			System\Route::isProtocolRelative($url)
		) {
			return false;
		}

		return true;
	}

	/**
	 * Retrieves raw search analytics data from the Google Search console.
	 *
	 * @param array $options
	 * @return array|\Exception
	 */
	public function getSearchanalytics($options)
	{
		try
		{
			$cache = $this->platform->getCache(
				'callback',
				[
					'defaultgroup' => '4seo_gsc_search_analytics',
					'lifetime'     => self::URL_SEARCH_ANALYTICS_CACHE_TIME,
					'caching'      => 1,
				]
			);

			$forceRefresh = Wb\arrayGet($options, 'forceRefresh');
			unset($options['forceRefresh']);

			$propertyId = $this->factory->getThis('forseo.config', 'integrations')->get('gscProperty');
			$propertyId = Wb\arrayGet($propertyId, 0);
			if (empty($propertyId))
			{
				throw new \Exception('No property configured for Google Search Console access. Maybe try configuring it again.', System\Http::RETURN_INTERNAL_ERROR);
			}

			$cacheId = md5(
				$propertyId . json_encode($options)
			);
			if ('true' === $forceRefresh)
			{
				$cache->remove($cacheId);
			}

			$searchAnalyticsResults = $cache->get(
				[
					$this,
					'doGetSearchAnalytics'
				],
				[
					$options,
					$propertyId
				],
				$cacheId
			);
		}
		catch (Service\Exception $exception)
		{
			return $this->integrationHelper->logConsoleError(
				$exception,
				'Error reading Search Console data using Google API. More details in log files on the server.'
			);
		}
		catch (\Exception $e)
		{
			return $e;
		}

		$rowsCount = count(
			Wb\arrayGet($searchAnalyticsResults, 'searchAnalytics', [])
		);

		return [
			'data' => $searchAnalyticsResults,
			'meta' => [
				'count' => $rowsCount,
				'total' => $rowsCount
			]
		];

	}

	/**
	 * Retrieves raw search analytics data from the Google Search console.
	 * Options:
	 * {
	 * startDate: '2022-08-01',
	 * endDate: '2022-08-01',
	 * dimensions: [],
	 * type: 'web',
	 * dimensionFilterGroups: [],
	 * page: '' // optional page to restrict search to
	 * aggregationType: 'auto', // auto | byPage | byProperty
	 * rowLimit: 25000,
	 * startRow: 0,
	 * dataState: 'all',
	 * }
	 *
	 * @param array $options
	 * @params string $propertyId
	 * @return array|\Exception
	 * @throws \Exception
	 */
	public function doGetSearchanalytics($options, $propertyId)
	{
		$searchAnalyticsRequest = new Service\SearchConsole\SearchAnalyticsQueryRequest();

		$rawDimensions = Wb\arrayGet($options, 'dimensions');
		if (!empty($rawDimensions))
		{
			$dimensions = json_decode(
				rawurldecode($rawDimensions),
				true
			);
			$searchAnalyticsRequest->setDimensions(
				$dimensions
			);
		}

		$startDate = WB\arrayGet($options, 'startDate');
		$endDate   = WB\arrayGet($options, 'endDate');
		if (
			empty($startDate)
			||
			empty($endDate)
		) {
			throw new \Exception('Missing start or end date in Search Console search analytics query.', System\Http::RETURN_BAD_REQUEST);
		}
		$searchAnalyticsRequest->setStartDate($startDate);
		$searchAnalyticsRequest->setEndDate($endDate);
		$searchAnalyticsRequest->setType(
			Wb\arrayGet($options, 'targetType', 'web')
		);

		$dimensionFilters = [];
		if (Wb\startsWith($propertyId, 'sc-domain:'))
		{
			$siteFilter = new Service\SearchConsole\ApiDimensionFilter();
			$siteFilter->setDimension('page');
			$siteFilter->setOperator('contains');
			$siteFilter->setExpression(
				$this->factory->getThis('forseo.config', 'pages')->get('canonicalRootUrl')
			);
			$dimensionFilters[] = $siteFilter;
		}

		if (Wb\arrayIsTruthy($options, 'page'))
		{
			$page       = rawurldecode(
				Wb\arrayGet($options, 'page')
			);
			$pageFilter = new Service\SearchConsole\ApiDimensionFilter();
			$pageFilter->setDimension('page');
			$pageFilter->setOperator('equals');
			$pageFilter->setExpression($page);
			$dimensionFilters[] = $pageFilter;
		}

		// If there are relevant filters, add them to the request.
		$rawDimensionFilters = Wb\arrayGet($options, 'dimensionFilterGroups');
		if (!empty($rawDimensionFilters))
		{
			$filterGroup = new Service\SearchConsole\ApiDimensionFilterGroup();
			$filterGroup->setGroupType('and');
			$dimensionFilters = json_decode(
				rawurldecode($rawDimensionFilters),
				true
			);
			$filterGroup->setFilters(
				$dimensionFilters
			);
			$searchAnalyticsRequest->setDimensionFilterGroups(
				array($filterGroup)
			);
		}

		$searchAnalyticsRequest->setAggregationType(
			Wb\arrayGet($options, 'aggregationType', 'auto')
		);

		$rowLimit = Wb\arrayGet($options, 'rowLimit', self::DEFAULT_ROW_LIMIT);
		$searchAnalyticsRequest->setRowLimit(
			min(
				$rowLimit,
				self::DEFAULT_ROW_LIMIT
			)
		);

		$searchAnalyticsRequest->setStartRow(
			Wb\arrayGet($options, 'startRow', 0)
		);
		$searchAnalyticsRequest->setDataState(
			Wb\arrayGet($options, 'dataState', self::DEFAULT_DATA_STATE)
		);

		$fetchStart              = microtime(true);
		$searchAnalyticsResponse = $this->searchConsoleService
			->searchanalytics
			->query(
				$propertyId,
				$searchAnalyticsRequest
			);
		$fetchEnd                = microtime(true);
		if (empty($searchAnalyticsResponse))
		{
			throw new \Exception('Cannot connect to Google Search Console, maybe try again later?', System\Http::RETURN_BAD_REQUEST);
		}

		$searchAnalyticsResults = $searchAnalyticsResponse->getRows();
		$searchAnalyticsResults = empty($searchAnalyticsResults)
			? []
			: $searchAnalyticsResults;

		$this->logger->debug('URL inspection request raw data' . print_r($searchAnalyticsResults, true));

		$aggregateResults = [
			'clicks'      => 0,
			'impressions' => 0,
			'ctr'         => 0,
			'position'    => 0
		];

		if (!empty($searchAnalyticsResults))
		{
			$rowsCount            = count($searchAnalyticsResults);
			$aggregateCtr         = 0;
			$aggregateAvgPosition = 0;
			foreach ($searchAnalyticsResults as $searchAnalyticsResult)
			{
				$aggregateResults['clicks']      += $searchAnalyticsResult->clicks;
				$aggregateResults['impressions'] += $searchAnalyticsResult->impressions;
				$aggregateCtr                    += $searchAnalyticsResult->ctr;
				$aggregateAvgPosition            += $searchAnalyticsResult->position;
			}

			$aggregateResults['ctr']      = $aggregateCtr / $rowsCount;
			$aggregateResults['position'] = $aggregateAvgPosition / $rowsCount;
		}

		return [
			'fetchedAt'       => System\Date::getUTCNow(),
			'fetchedIn'       => $fetchEnd - $fetchStart,
			'startDate'       => $startDate,
			'endDate'         => $endDate,
			'searchAnalytics' => $searchAnalyticsResults,
			'aggregationType' => $searchAnalyticsResponse->getResponseAggregationType(),
			'totals'          => $aggregateResults
		];
	}

	/**
	 * Attempts to fetch data from the Google Search Console using the configured integrations.
	 *
	 * @return void
	 */
	public function fetchDaily()
	{
		if (!$this->integrationsConfig->isGoogleSearchConsoleActive())
		{
			return;
		}

		// We can start fetching data for the day prior, which is today minus one day
		$date = System\Date::toExtendedDateTime(
			'now',
			new \DateTimeZone(self::GSC_DATA_TIMEZONE)
		);
		$date->sub('P1D');


		$helper = $this->factory->getA(
			Helper\Task::class
		);

		$this->logger->debug('GCS data: daily fetch triggered for ' . $date->format('Y-m-d') . ' Pacific Time. NOT IMPLEMENTED YET.');

	}
}
