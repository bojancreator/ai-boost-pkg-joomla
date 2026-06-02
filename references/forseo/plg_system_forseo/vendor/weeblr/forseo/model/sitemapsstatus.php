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

use Weeblr\Forseo\Data;
use Weeblr\Forseo\Helper;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\Db;
use Weeblr\Wblib\Forseo\Seo;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Sitemapsstatus extends Base\Base
{
	/**
	 * @var System\Config Convenience access to sitemap config.
	 */
	private $sitemapConfig;

	/**
	 * @var array Manifest file for the current sitemap, if any.
	 */
	private $manifest;

	/**
	 * @var string Unique id of last completed crawl.
	 */
	private $currentCrawlId;

	/**
	 * @var String Root path of cache storage
	 */
	private $cacheRootPath;

	/**
	 * @var Weeblr\Wblib\Db\Dbhelper Database helper instance.
	 */
	private $db;

	/**
	 * @var Helper\Sitemaps
	 */
	private $helper;

	/**
	 * @var System\Logger Convenience logger instance.
	 */
	private $logger = null;

	/**
	 * @param array $options Can inject custom factory and platform.
	 */
	public function __construct($options = [])
	{
		parent::__construct();

		$this->db = $this->factory->getThe('db');

		$this->cacheRootPath = $this->platform
			->getCachePath('sitemaps', 'com_forseo');

		$this->sitemapConfig = $this->factory
			->getThis('forseo.config', 'sitemaps');

		$this->currentCrawlId = $this->factory
			->getThe('forseo.crawlerHelper')
			->getCompletedCrawlId();

		$this->helper = $this->factory->getA(Helper\Sitemaps::class);
	}

	/**
	 * Reports on current crawlser status.
	 *
	 * @param array $options API request options
	 *                       'type'
	 * @return array
	 * @throws \Exception
	 */
	public function status($options)
	{
		$type = (int)Wb\arrayGet($options, 'type', Data\Sitemap::CONTENT);

		$this->loadManifest($type);
		$manifestCrawlId = Wb\arrayGet($this->manifest, 'crawl_id');

		$response = [
			'enabled'                  => $this->sitemapConfig->isTruthy('enabled'),
			'addToRobotsTxt'           => $this->sitemapConfig->isTruthy('addToRobotsTxt'),
			'searchEnginesPingEnabled' => $this->sitemapConfig->isTruthy('searchEnginesPingEnabled'),
			'trackSearchEnginesVisits' => $this->sitemapConfig->isTruthy('trackSearchEnginesVisits'),
			'url'                      => $this->helper->xmlUrl($type),
			'type'                     => $type,
			'manifest'                 => $this->manifest,
			'status'                   => empty($manifestCrawlId)
				? System\Http::RETURN_SERVICE_UNAVAILABLE
				: System\Http::RETURN_OK
		];

		if (empty($manifestCrawlId))
		{
			// don't have a sitemap, can return now
			return $response;
		}

		// load sitemap records from DB
		$sitemapsRecords = $this->db->selectAssocList(
			'#__forseo_sitemaps',
			'*',
			[
				'type'     => $type,
				'crawl_id' => $manifestCrawlId
			],
			[],
			[
				'file_type'
			]
		);

		if (empty($sitemapsRecords))
		{
			$response['status'] = System\Http\RETURN_SERVICE_UNAVAILABLE;

			return $response;
		}

		$response['details'] = $this->buildSitemapDetailsRecord($sitemapsRecords);

		return $response;
	}

	protected function buildSitemapDetailsRecord($sitemapsRecords)
	{
		// first row is index
		$index = array_shift($sitemapsRecords);
		if (Data\Sitemap::FILE_INDEX !== (int)Wb\arrayGet($index, 'file_type'))
		{
			throw new \Exception('Invalid internal sitemap database record.', 500);
		}
		$details = array_intersect_key(
			$index,
			array_flip(
				[
					'type',
					'created_at',
					'url_count',
					'image_count',
					'google_submitted_at',
					'google_last_fetch',
					'google_fetches',
					'bing_submitted_at',
					'bing_last_fetch',
					'bing_fetches',
				]
			)
		);

		$details['partials'] = count($sitemapsRecords);

		// then subsequent rows
		$googleLastVisit       = Wb\arrayGet($details, 'google_last_fetch');
		$googleLastVisit       = empty($googleLastVisit)
			? $googleLastVisit
			: System\Date::toExtendedDateTime($googleLastVisit);
		$bingLastVisit         = Wb\arrayGet($details, 'bing_last_fetch');
		$bingLastVisit         = empty($bingLastVisit)
			? $bingLastVisit
			: System\Date::toExtendedDateTime($bingLastVisit);
		$partialsFetchedGoogle = 0;
		$partialsFetchedBing   = 0;
		foreach ($sitemapsRecords as $sitemapsRecord)
		{
			$fetched = Wb\arrayGet($sitemapsRecord, 'google_last_fetch');
			if (!empty($fetched))
			{
				$partialsFetchedGoogle += 1;
				$googleLastVisit       = empty($googleLastVisit)
					? System\Date::toExtendedDateTime($fetched)
					: ($googleLastVisit->isAfterOrSame($fetched)
						? $googleLastVisit
						: System\Date::toExtendedDateTime($fetched)
					);
			}
			$fetched = Wb\arrayGet($sitemapsRecord, 'bing_last_fetch');
			if (!empty($fetched))
			{
				$partialsFetchedBing += 1;
				$bingLastVisit       = empty($bingLastVisit)
					? System\Date::toExtendedDateTime($fetched)
					: ($bingLastVisit->isAfterOrSame($fetched)
						? $bingLastVisit
						: System\Date::toExtendedDateTime($fetched)
					);
			}
		}

		$details['google_fetch_complete'] = 0 === $details['partials']
			? 0
			: $partialsFetchedGoogle / $details['partials'];
		$details['bing_fetch_complete']   = 0 === $details['partials']
			? 0
			: $partialsFetchedBing / $details['partials'];

		$details['google_last_fetch'] = empty($googleLastVisit)
			? null
			: $googleLastVisit->toMysql();
		$details['bing_last_fetch']   = empty($bingLastVisit)
			? null
			: $bingLastVisit->toMysql();

		return $details;
	}

	protected function loadManifest($type = Data\Sitemap::CONTENT)
	{
		$manifestFile = Wb\join(
			'',
			$this->cacheRootPath,
			'/current/manifest',
			$this->helper->getSitemapTypeSuffix($type),
			'.php'
		);

		$this->manifest = file_exists($manifestFile)
			? include $manifestFile
			: [];

		$this->manifest = Wb\arrayEnsure($this->manifest);
	}
}
