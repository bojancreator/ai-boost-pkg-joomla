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
use Weeblr\Wblib\Forseo\Joomla\Uri\Uri;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Sitemaps extends Base\Base
{
	/**
	 * @var System\Config Convenience access to sitemap config.
	 */
	private $sitemapConfig;

	/**
	 * @var String Root path of cache storage
	 */
	private $cacheRootPath;

	/**
	 * @var string Unique id of last completed crawl.
	 */
	private $completedCrawlId;

	/**
	 * @var Data\Sitemap Data object record for any newly built sitemap.
	 */
	private $sitemapIndexRecord;

	/**
	 * @var Db\Keystore Convenience instance of the 4SEO keystore.
	 */
	private $keystore;

	/**
	 * @var string Unique lock id to acquire
	 */
	private $lockId = '';

	/**
	 * @var string Requested file.
	 */
	private $file;

	/**
	 * @var string Holds the currently requested file type.
	 */
	private $fileType;

	/**
	 * @var int  Holds the currently requested file serial, if any.
	 */
	private $fileSerial;

	/**
	 * @var string Content of the index file
	 */
	private $indexFileContent;

	/**
	 * @var string Aggregated hashes of the partials content.
	 */
	private $partialsHashes = '';

	/**
	 * @var string Content of the manifest file, holds the crawl id.
	 */
	private $manifestFileContent;

	/**
	 * @var string Computed cache file name of the index file of current sitemap.
	 */
	private $cachedIndexFilePath;

	/**
	 * @var string Computed cache file name.
	 */
	private $cachedFilePath;

	/**
	 * @var string When building a sitemap, holds the full path to the sitemap folder
	 */
	private $finalCacheFolder;

	/**
	 * @var string When building a sitemap, full path to a temp folder. When ready, the folder is renamed to the finalCacheFolder
	 */
	private $tempCacheFolder;

	/**
	 * @var array Total number of URLs in the sitemap (ie, not current file)
	 */
	private $urlCount;

	/**
	 * @var array Total number of URLs processed and stored in partials
	 */
	private $processedUrlCount;

	/**
	 * @var array Total number of URLs in the sitemap (ie, not current file)
	 */
	private $urlCountPerLanguage;

	/**
	 * @var Helper\Crawler
	 */
	private $crawlerHelper;

	/**
	 * @var string Site root URL
	 */
	private $siteRootUrl;

	/**
	 * @var array List of xls stylesheets per sitemap file type
	 */
	private $stylesheetUrls = [
		'index'   => '/sitemap/sitemap-4seo.xsl',
		'partial' => '/sitemap/sitemap-4seo.xsl',
	];

	/**
	 * @var array List of language tags covering all URLs in the sitemap.
	 */
	private $languages;

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

		$this->db = $this->factory
			->getThe('db');

		$this->sitemapConfig = $this->factory
			->getThis('forseo.config', 'sitemaps');

		$this->helper = $this->factory
			->getA(Helper\Sitemaps::class);

		$this->cacheRootPath = $this->platform
			->getCachePath('sitemaps', 'com_forseo');

		$this->logger = $this->factory
			->getThe('forseo.logger');

		$this->crawlerHelper = $this->factory
			->getThe('forseo.crawlerHelper');

		$this->completedCrawlId = $this->crawlerHelper
			->getCompletedCrawlId();

		$this->keystore = $this->factory
			->getThe('forseo.keystore');

		$this->siteRootUrl = $this->factory
			->getThis('forseo.config', 'pages')
			->get('canonicalRootUrl');

		$this->buildLockId();
	}

	/**
	 * Get the content of the current xml sitemap for the site.
	 *
	 * Temporary: currently directly reading the content of the cached sitemap file(s).
	 *
	 * Later, will be streamed back.
	 *
	 * @param string $file     The requested sitemap file.
	 * @param string $fileType The request type, main or partial.
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function getXml($file, $fileType)
	{
		$this->file     = $file;
		$this->fileType = $fileType;

		try
		{
			$this->parseFileName();
		}
		catch (\Throwable $e)
		{
			return [
				'status'   => $e->getCode(),
				'crawl_id' => $this->completedCrawlId,
				'file'     => $this->fileType . (empty($this->fileSerial) ? '' : '-' . $this->fileSerial),
				'content'  => $e->getMessage()
			];
		}

		$hasCachedSitemapFile = file_exists($this->cachedFilePath);
		if (
			empty($this->completedCrawlId)
			&&
			!$hasCachedSitemapFile
		) {
			$this->logger->custom('sitemap', 'Process ' . $this->lockId . ', no completed crawl and no previous cached sitemap, need to wait a bit.');

			// no completed crawl and no previous cached sitemap, need to wait a bit.
			return [
				'status'   => System\Http::RETURN_SERVICE_UNAVAILABLE,
				'crawl_id' => '',
				// file: main | partial-{{serial}}
				'file'     => '',
				'content'  => 'Service unavailable.'
			];
		}

		$cachedSitemapCrawlId = null;
		if ($hasCachedSitemapFile)
		{
			$manifestFile = $this->cacheRootPath . '/current/manifest.php';
			if (file_exists($manifestFile))
			{
				$manifestData         = include($manifestFile);
				$cachedSitemapCrawlId = Wb\arrayGet(
					$manifestData,
					'crawl_id',
					''
				);
			}
		}

		// Try to read cached content from file.
		$cachedFileContent = $hasCachedSitemapFile
			? $this->readFile()
			: '';

		$hasNewContentAndRequiredDelayPassed = $this->hasNewContentAndRequiredDelayPassed();
		if (
			(
				// completed crawl but cached sitemap file not present
				// (typically after user pressed the Rebuild now button in sitemap page toolbar
				// We rebuild, regardless of time passed
				!empty($this->completedCrawlId)
				&&
				empty($cachedFileContent)
			)
			||
			(
				// completed crawl and we have already a sitemap cached file
				// but it's for a previous crawl. If enough time has passed
				// we rebuild, regardless of whether another crawl is in progress.
				!empty($this->completedCrawlId)
				&&
				$this->completedCrawlId !== $cachedSitemapCrawlId
				&&
				$hasNewContentAndRequiredDelayPassed
			)
			||
			(
				// crawl completed but a rebuild is required
				// and there's no in progress crawl, it's safe to rebuild
				// as we don't risk
				!empty($this->completedCrawlId)
				&&
				empty($this->crawlerHelper->getInProgressCrawlId())
				&&
				$hasNewContentAndRequiredDelayPassed
			)
		) {
			// (Re)-Build the sitemap if this is a request for a valid sitemap file, either the index or one
			// of the partials and a crawl has been completed.
			// We must always rebuild the entire sitemap as the reason one or more files have been deleted is if
			// user or plugins decided to add/remove files from the sitemap. When that happens, we don't know
			// if the number of partials will still be the same afterwards so everything has to be rebuilt.
			try
			{
				$isSitemapBuildInProgressByOtherProcess = $this->sitemapBuildInProgress();

				if ($isSitemapBuildInProgressByOtherProcess)
				{
					$this->keystore->unlock(
						'sitemap.rebuildRequired',
						$this->lockId
					);

					if (empty($cachedFileContent))
					{
						$this->logger->custom('sitemap', 'Process ' . $this->lockId . ', sitemap is being rebuilt by another process and we don\'t have a previously cached version.');

						// sitemap is being rebuilt by another process and we don't have a previously cached version
						return [
							'status'   => System\Http::RETURN_SERVICE_UNAVAILABLE,
							'crawl_id' => '',
							// file: main | partial-{{serial}}
							'file'     => '',
							'content'  => 'Service unavailable.'
						];
					}

					$this->logger->custom('sitemap', 'Process ' . $this->lockId . ', sitemap is being rebuilt by another process but we have a previously cached version.');
				}

				if (!$isSitemapBuildInProgressByOtherProcess)
				{
					$this->logger->custom('sitemap', 'Process ' . $this->lockId . ', sitemap needs rebuild, no other process building it, starting to rebuild');

					$this->buildSitemap();

					$this->logger->custom('sitemap', 'Process ' . $this->lockId . ', sitemap needs rebuild, no other process building it, sitemap rebuilt completed.');

					$this->keystore->delete(
						'sitemap.sitemapBuildInProgress'
					);
					$this->keystore->deleteLocked(
						'sitemap.rebuildRequired',
						$this->lockId
					);
				}
			}
			catch (\Throwable $e)
			{
				$this->logger->error(__METHOD__ . ': Error building sitemap to ' . $this->completedCrawlId . ', file: ' . $e->getFile() . ',line: ' . $e->getLine() . ', msg: ' . $e->getMessage() . ', trace: ' . $e->getTraceAsString());

				$this->keystore->delete(
					'sitemap.sitemapBuildInProgress'
				);
				$this->keystore->unlock(
					'sitemap.rebuildRequired',
					$this->lockId
				);

				return [
					'status'   => System\Http::RETURN_INTERNAL_ERROR,
					'crawl_id' => $this->completedCrawlId,
					'file'     => $this->fileType . (empty($this->fileSerial) ? '' : '-' . $this->fileSerial),
					'content'  => 'Internal error during sitemap request (' . $e->getCode() . ') ' . $e->getMessage()
				];
			}

			$cachedFileContent = $this->readFile();
		}

		if (
			empty($this->completedCrawlId)
			&&
			!empty($cachedFileContent)
		) {
			// no crawlId but we do have a sitemap content?
			// only if Pages data has been reset and we're serving the last good - but stale - sitemap
			// We can get the crawl_id from the manifest.php file created at the same time as the sitemap.
			// CrawlId is required for proper update of search engines access.
			$this->completedCrawlId = $cachedSitemapCrawlId;
		}

		$status = empty($cachedFileContent)
			? System\Http::RETURN_NOT_FOUND
			: System\Http::RETURN_OK;

		$this->keystore->unlock(
			'sitemap.rebuildRequired',
			$this->lockId
		);

		return [
			'status'   => $status,
			'crawl_id' => $this->completedCrawlId,
			'file'     => $this->fileType . (empty($this->fileSerial) ? '' : '-' . $this->fileSerial),
			'content'  => $cachedFileContent
		];
	}

	/**
	 * Check if a sitemap is being built (by another process).
	 *
	 * @return bool
	 */
	private function sitemapBuildInProgress()
	{
		$buildInProgressRecord = $this->keystore->getAndLock(
			'sitemap.sitemapBuildInProgress',
			true,
			$this->lockId,
			'PT15M'
		);

		if (Wb\arrayIsFalsy($buildInProgressRecord, 'locked'))
		{
			// Could not aquire a lock: likely another process is already building it.
			return true;
		}

		return false;
	}

	/**
	 * Whether we can and should rebuild the current sitemap.
	 * Sitemap is only rebuilt if a rebuildRequired flag
	 * has been set by the analysis process.
	 * Sitemap is also only rebuild after a wait period to avoid too
	 * frequent updates.
	 *
	 * @return bool
	 */
	private function hasNewContentAndRequiredDelayPassed()
	{
		$rebuildRequiredRecord = $this->keystore->getAndLockExisting(
			'sitemap.rebuildRequired',
			$this->lockId,
			'PT1M'
		);

		if (Wb\arrayIsFalsy($rebuildRequiredRecord, 'locked'))
		{
			// Could not aquire a lock, another process already using this record
			// or simply rebuilding sitemap is not required.
			return false;
		}

		$rebuildRequiredDateTime = Wb\arrayGet(
			$rebuildRequiredRecord,
			'value'
		);

		$pastRebuildTime = empty($rebuildRequiredDateTime)
			? false
			: System\Date::toExtendedDateTime(
				$rebuildRequiredDateTime
			)->isBeforeBy(
				'now',
				$this->sitemapConfig->get('refreshSitemapDelay', 'PT30M')
			);

		return $pastRebuildTime;
	}

	/**
	 * Builds a unique lock id for the current request, used to
	 * lock keystore items:
	 *
	 * - sitemap.rebuildRequired
	 * - sitemap.sitemapBuildInProgress
	 *
	 * @return void
	 */
	private function buildLockId()
	{
		$this->lockId = empty($this->lockId)
			? System\Auth::uuidv4(
				System\Auth::UUID4_NO_DASHES,
				System\Auth::UUID4_LOWERCASE
			)
			: $this->lockId;
	}

	/**
	 * Detect a visit by a search engine and if so store it to sitemap record.
	 *
	 * @param int $fileType
	 *
	 * @return $this
	 */
	public function trackSearchEnginesVisits($fileType)
	{
		if ($this->sitemapConfig->isFalsy('trackSearchEnginesVisits'))
		{
			return $this;
		}

		try
		{
			$engineId = $this->factory
				->getThe('forseo.searchEnginesHelper')
				->getRequestingSearchEngine();

			if (
				!in_array(
					$engineId,
					$this->sitemapConfig->get('searchEnginesStatsEnabled', [])
				)
			) {
				return $this;
			}

			// Prepare to read the sitemap record from DB
			$selectors = [
				'file_type' => $fileType,
				'crawl_id'  => Wb\initEmpty($this->completedCrawlId, ''),
				'serial'    => Wb\initEmpty($this->fileSerial, 0),
				'state'     => Data\Sitemap::READY
			];

			$sitemapRecord = $this->factory->getA(Data\Sitemap::class)
										   ->loadPerColumn($selectors);

			if (!$sitemapRecord->exists())
			{
				return $this;
			}

			$sitemapRecord
				->set(
					$engineId . '_last_fetch',
					System\Date::getUTCNow()
				)->increment(
					$engineId . '_fetches'
				)->store();

		}
		catch (\Throwable $e)
		{
			$this->logger->error(__METHOD__ . ' %s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}

		return $this;
	}

	/**
	 * Delete the cached files for the current sitemap, if any.
	 * Only the files are deleted, the database record is untouched.
	 * This will cause the sitemap to be rebuilt upon next request for it.
	 *
	 * @param int $type Optional sitemap type to delete. If missing, all types are deleted.
	 *
	 * @return Sitemaps
	 * @throws \Exception
	 */
	public function deleteCurrentSitemapCachedFiles($type = Data\Sitemap::CONTENT)
	{
		$currentSitemapFolder = Wb\slashTrimJoin(
			$this->cacheRootPath,
			'current'
		);

		$this->platform
			->deleteFolders($currentSitemapFolder);

		$this->loadSitemapIndexRecord();
		if ($this->sitemapIndexRecord->exists())
		{
			$this->resetSitemapIndexRecord()
				 ->store();
		}

		return $this;
	}

	/**
	 * Build the full sitemap for the current crawlId. Currently only doing content sitemap.
	 *
	 * @TODO: Block changes to Pages table while the sitemap is being rebuilt. See #86.
	 *
	 * @param int $type Sitemap content type.
	 * @return Sitemaps
	 * @throws \Exception
	 */
	public function buildSitemap($type = Data\Sitemap::CONTENT)
	{
		@set_time_limit(0);

		$this->logger->debug(__METHOD__ . ', starting to build sitemap, current crawlId is ' . $this->completedCrawlId);

		$this->indexFileContent = '';
		$this->partialsHashes   = '';

		$this->loadSitemapIndexRecord($type)
			 ->prepareFileCache($type)
			 ->loadLanguages()
			 ->countTotalUrls()
			 ->initSitemapIndexRecord($type)
			 ->buildPartials($type)
			 ->addCustomToIndex()
			 ->buildIndex($type)
			 ->buildManifest($type)
			 ->moveFileCache()
			 ->purgeSitemaps($type);

		// ping search engines if the sitemap content has changed
		$newSitemapHash = sha1($this->partialsHashes);
		if ($newSitemapHash !== $this->sitemapIndexRecord->get('hash'))
		{
			$this->keystore
				->put(
					'sitemap.pingSearchEngines',
					true
				);
		}

		$imageCount = $this->db
			->setQueryAnd('SELECT count(distinct ' . $this->db->qn('url') . ') FROM ' . $this->db->qn('#__forseo_images') . ';')
			->loadResult();

		$this->sitemapIndexRecord
			->set(
				[
					'type'        => $type,
					'url_count'   => $this->urlCount,
					'image_count' => empty($imageCount)
						? 0
						: $imageCount,
					'state'       => Data\Sitemap::READY,
					'hash'        => $newSitemapHash
				]
			)->store();

		$this->factory->getThe('forseo.logger')->debug(__METHOD__ . ', sitemap built successfully, current crawlId is ' . $this->completedCrawlId);

		return $this;
	}

	/**
	 * Finalize index content and write it to disk.
	 *
	 * @param int $type Sitemap content type.
	 * @return Sitemaps
	 */
	private function buildIndex($type)
	{
		// write the index file
		$this->indexFileContent = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>"
								  . $this->getStylesheetTag('index')
								  . "\n<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">"
								  . $this->indexFileContent
								  . "\n</sitemapindex>\n";

		$indexFilePath = Wb\slashTrimJoin(
			$this->tempCacheFolder,
			$this->helper->getMainFileName(Data\Sitemap::CONTENT)
		);

		$this->writeFile(
			$indexFilePath,
			$this->indexFileContent
		);

		return $this;
	}

	/**
	 * Add a manifest file, holding meta info, used when servin stale sitemap.
	 *
	 * @param int $type Sitemap content type.
	 * @return $this
	 */
	private function buildManifest($type)
	{
		// write the index file
		$this->manifestFileContent = Wb\join(
			"\n",
			'<?php',
			'defined(\'WBLIB_EXEC\') || die();',
			'return [',
			'  \'crawl_id\' => ' . var_export($this->completedCrawlId, true) . ',',
			'  \'type\' => ' . var_export(Data\Sitemap::CONTENT, true) . ',',
			'  \'languages\' => ' . var_export($this->languages, true) . ',',
			'  \'url_count\' => ' . var_export($this->urlCount, true) . ',',
			'  \'url_count_per_language\' => ' . var_export($this->urlCountPerLanguage, true) . ',',
			'];',
			''
		);

		$manifestFilePath = Wb\slashTrimJoin(
			$this->tempCacheFolder,
			'manifest.php'
		);

		$this->writeFile(
			$manifestFilePath,
			$this->manifestFileContent
		);

		return $this;
	}

	/**
	 * Build per language partials, write them to disk and update index content as we go.
	 *
	 * @param int $type Sitemap content type.
	 * @return $this
	 * @throws \Exception
	 */
	private function buildPartials($type = Data\Sitemap::CONTENT)
	{
		$rootUrl = Wb\slashTrimJoin(
			$this->siteRootUrl,
			$this->platform->getUrlRewritingPrefix()
		);

		$maxPartials = $this->sitemapConfig->get('maxPartials', 500);

		// iterate over urls sets
		foreach ($this->languages as $language)
		{
			$serial    = 0;
			$offset    = 0;
			$toProcess = $this->urlCountPerLanguage[$language];

			// Include a partials number watchdog
			while ($offset < $toProcess && $serial < $maxPartials)
			{
				$serial++;
				$partialRecord = $this->factory
					->getA(Data\Sitemap::class)
					->loadPerColumn(
						[
							'file_type' => Data\Sitemap::FILE_PARTIAL,
							'lang'      => $language,
							'crawl_id'  => $this->completedCrawlId,
							'serial'    => $serial,
							'state'     => Data\Sitemap::READY,
						]
					);

				$urls = $this->loadUrls(
					$language,
					$offset
				);

				$urlsThisBatch = count($urls);
				if ($urlsThisBatch <= 0)
				{
					continue;
				}

				$hash = $this->buildPartial(
					$urls,
					$language,
					$serial,
					$rootUrl
				);

				$partialRecord->set(
					[
						'file_type'           => Data\Sitemap::FILE_PARTIAL,
						'lang'                => $language,
						'crawl_id'            => $this->completedCrawlId,
						'hash'                => $hash,
						'serial'              => $serial,
						'url_count'           => $urlsThisBatch,
						'state'               => Data\Sitemap::READY,
						// also reset counters, as we may be using a pre-existing record
						// which stats are now obsolete
						'google_submitted_at' => null,
						'google_last_fetch'   => null,
						'google_fetches'      => 0,
						'bing_submitted_at'   => null,
						'bing_last_fetch'     => null,
						'bing_fetches'        => 0,
					]
				)->timestamp(
					'created_at'
				)->store();

				$this->addPartialToIndex(
					$language,
					$serial,
					$hash,
					$rootUrl
				);

				$offset += $urlsThisBatch;

				$this->processedUrlCount += $urlsThisBatch;
				$this->sitemapIndexRecord
					->set(
						[
							'processed_url_count' => $this->processedUrlCount
						]
					)->store();
			}
		}

		return $this;
	}

	/**
	 * Build and write to disk a single partial file.
	 *
	 * @param $urls
	 * @param $language
	 * @param $serial
	 * @param $rootUrl
	 * @return string
	 */
	private function buildPartial($urls, $language, $serial, $rootUrl)
	{
		$fileContent = '';
		$images      = $this->getPartialImages($urls);
		foreach ($urls as $urlRecord)
		{
			$item = Wb\join('',
				"\n  <url>",
				"\n    <loc>",
				Wb\slashTrimJoin(
					$rootUrl,
					System\Route::encodeUrlForSitemap(
						$urlRecord['full_url']
					)
				),
				"</loc>"
			);

			if (!empty($urlRecord['modified_at']))
			{
				$formattedDate = $this->formatLastMod($urlRecord['modified_at']);
				if (!empty($formattedDate))
				{
					$item .= "\n    <lastmod>" . $formattedDate . "</lastmod>";
				}
			}

			$urlKey    = empty($urlRecord['url'])
				? '/'
				: $urlRecord['url'];
			$urlImages = Wb\arrayGet($images, $urlKey, []);
			foreach ($urlImages as $urlImage)
			{

				/**
				 * Filter an image details before including it into an Image sitemap.
				 * If returned value is empty, this image will not be included in sitemap.
				 *
				 * @api     forseo
				 * @package 4SEO\sitemaps
				 * @var forseo_sitemaps_image_details
				 *
				 * @param array $urlImage
				 *
				 * @return void | array
				 *
				 * @since   1.0.0
				 */
				$urlImage = $this->factory
					->getThe('hook')
					->filter(
						'forseo_sitemaps_image_details',
						$urlImage
					);

				if (empty($urlImage))
				{
					continue;
				}

				$urlImagesOutputBits = $this->buildImageRecord($urlImage);
				if (!empty($urlImagesOutputBits))
				{
					$item .= implode('', $urlImagesOutputBits);
				}
			}

			$item        .= "\n  </url>";
			$fileContent .= $item;
		}

		$fileContent = Wb\join(
			'',
			"<?xml version=\"1.0\" encoding=\"UTF-8\"?>",
			$this->getStylesheetTag('partial'),
			"\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\" "
			. ($this->sitemapConfig->isTruthy('includeImages', true) ? ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"' : '')
			. ">",
			$fileContent,
			"\n</urlset>\n"
		);

		$hash = sha1($fileContent);

		$filePath = Wb\slashTrimJoin(
			$this->tempCacheFolder,
			$this->buildPartialFileName(
				$language,
				$serial,
				$hash
			)
		);

		$this->writeFile(
			$filePath,
			$fileContent,
			$this->sitemapConfig->isTruthy('usePreCompressedPartials')
		);

		$this->partialsHashes .= $hash;

		return $hash;
	}

	/**
	 * Builds the full tag for the stylesheet, including a filter to let user
	 * disable it.
	 *
	 * @param string $type index | partial
	 * @return string
	 */
	private function getStylesheetTag($type)
	{
		/**
		 * Filter the URL of a stylesheet to be applied to a sitemap. Return an empty string to
		 * disabling including a stylesheet with your sitemap.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\sitemap
		 * @var forseo_sitemap_stylesheet_url
		 * @since   1.5.2
		 *
		 * @param string $stylesheetUrl Fully qualified URL of a sitemap stylesheet.
		 * @param string $type          index | partial the sitemap type the stylesheet applies to.
		 *
		 * @return int
		 *
		 */
		$stylesheetUrl = $this->factory
			->getThe('hook')
			->filter(
				'forseo_sitemap_stylesheet_url',
				Wb\slashTrimJoin(
				// We use a path relative to root instead of the user-provided
				// site main address to still show a stylesheet even if that
				// main site URL is wrong, or has not been updated yet (when changing
				// the site URL for instance)
					$this->platform->getRootUrl(true), // $pathOnly = true
					FORSEO_APP_ASSETS_BASE_URL,
					$this->stylesheetUrls[$type]
				),
				$type
			);

		return empty($stylesheetUrl)
			? ''
			: '<?xml-stylesheet type="text/xsl" href="' . $stylesheetUrl . '"?>';
	}

	/**
	 * Builds the text record for an image in an XML sitemap.
	 *
	 * @param array  $image
	 * @param string $siteRootUrl
	 * @return array
	 */
	private function buildImageRecord($image)
	{
		$bits[] = "\n    <image:image>";
		$bits[] = "\n        <image:loc>";

		$imageUrl = System\Route::absolutify(
			$image['full_url'],
			true,
			System\Route::absolutify(
				$image['page_full_url'],
				true
			),
			true   // skip rewriting prefix
		);

		$uriObject = new Uri($imageUrl);
		$domain    = $uriObject->getScheme() . '://' . $uriObject->getHost();

		$bits[] = Wb\slashTrimJoin(
			$domain,
			System\Route::encodeUrlForSitemap(
				Wb\lTrim(
					$imageUrl,
					$domain
				)
			)
		);

		$bits[] = "</image:loc>";
		$bits[] = "\n    </image:image>";

		return $bits;
	}

	/**
	 * Read from database per url images collected, if any, for the
	 * provided list of URLs. No caching as this is supposed to only
	 * happen once per partial.
	 *
	 * @param array $urls
	 * @return array
	 */
	private function getPartialImages($urls)
	{
		if ($this->sitemapConfig->isFalsy('includeImages', true))
		{
			return [];
		}

		$urlList = [];
		foreach ($urls as $url)
		{
			$urlList[] = $url['url'];
		}

		$whereClause = $this->db->quoteName('page_url') . ' in (' . $this->db->arrayToQuotedList($urlList) . ')'
					   . ' and ('
					   . $this->buildSitemapExclusionClause()
					   . ')';

		$images = $this->db->selectAssocList(
			'#__forseo_images',
			'*',
			$whereClause
		);

		$perUrlImages = [];
		foreach ($images as $image)
		{
			$key                = empty($image['page_url'])
				? '/'
				: $image['page_url'];
			$perUrlImages[$key] = empty($perUrlImages[$key])
				? [$image]
				: array_merge(
					$perUrlImages[$key],
					[$image]
				);
		}

		return $perUrlImages;
	}

	/**
	 * Build the filename for a partial - without compression extension.
	 *
	 * Note: used to use the file hash instead of '4seo'in the file name, to inform
	 * Google of partial changes. This appears to be counterproductive with Google
	 * failing to read all partials in a sitemap before they were updated (due to content update
	 * and lastmod value changes). This causes Google to requests already outdated partials, which
	 * results in 404s and incomplete/incorrect coverage reports in GSC.
	 * Therefore now having a fixed string instead of hash. Left this in place as we may revisit
	 * in the future.
	 *
	 * @param string $language
	 * @param int    $serial
	 * @param string $hash sha1 hash of the actual file content.
	 * @return string
	 */
	private function buildPartialFileName($language, $serial, $hash)
	{
		// write the file, no need for stream, we're below 1M for sure
		return Wb\dotJoin(
			'sitemap',
			$language,
			'4seo',
			$serial,
			$this->helper->getSitemapTypeSuffix(Data\Sitemap::CONTENT),
			'xml'
		);
	}

	/**
	 * Insert the record for a given partial into the being-built index file content.
	 *
	 * @param string $language
	 * @param int    $serial
	 * @param string $hash
	 * @param string $rootUrl
	 * @return $this
	 */
	private function addPartialToIndex($language, $serial, $hash, $rootUrl)
	{
		// Build index file content at the same time.
		// No encoding, we know it's safe.
		$compressionSuffix = $this->sitemapConfig->isTruthy('usePreCompressedPartials')
			? '.gz'
			: '';

		$url = Wb\slashTrimJoin(
			$rootUrl,
			$this->buildPartialFileName(
				$language,
				$serial,
				$hash
			) . $compressionSuffix
		);

		$this->indexFileContent .= wb\join(
			'',
			"\n  <sitemap>",
			"\n    <loc>",
			$url,
			"</loc>",
			"\n    <lastmod>",
			System\Date::toExtendedDateTime()->toW3c(),
			"</lastmod>",
			"\n  </sitemap>"
		);

		return $this;
	}

	/**
	 * If custom txt or xml sitemap file is detected at root of site,
	 * include them in the index - without any validation.
	 *
	 * @return $this
	 */
	private function addCustomToIndex()
	{
		$customFiles = [
			'sitemap-4seo-custom.txt',
			'sitemap-4seo-custom.xml'
		];
		$rootPath    = $this->platform->getRootPath();

		foreach ($customFiles as $customFile)
		{
			$fullPath = Wb\slashTrimJoin(
				$rootPath,
				$customFile
			);

			if (file_exists($fullPath))
			{
				$this->indexFileContent .= wb\join(
					'',
					"\n  <sitemap>",
					"\n    <loc>",
					Wb\slashTrimJoin(
						$this->siteRootUrl,
						$customFile
					),
					"</loc>",
					"\n  </sitemap>"
				);
			}
		}

		return $this;
	}

	/**
	 * Figure out the list of languages used but all URLs that are to be included in the sitemap.
	 * @return $this
	 */
	private function loadLanguages()
	{
		$whereClause = $this->buildWhereClause();

		// List all languages used by all pages.
		$query = 'select distinct ' . $this->db->quoteName('lang') . ' from ' . $this->db->quoteName('#__forseo_pages')
				 . $whereClause;

		$this->languages = $this->db
			->setQueryAnd($query)
			->loadColumn();

		return $this;
	}

	/**
	 * Load all URLs from the db that qualify for sitemap inclusion, per language.
	 */
	private function loadUrls($language, $offset = 0)
	{
		$whereClause = $this->buildWhereClause();

		// Load URLs for the current file.
		$query = 'select ' . $this->db->quoteName('full_content_id')
				 . ', ' . $this->db->quoteName('url')
				 . ', ' . $this->db->quoteName('full_url')
				 . ', ' . $this->db->quoteName('modified_at')
				 . ' from ' . $this->db->quoteName('#__forseo_pages')
				 . $whereClause
				 . ' and ' . $this->db->quoteName('lang') . ' = ' . $this->db->quote($language)
				 . ' limit ' . (int)$offset . ', ' . (int)$this->sitemapConfig->get('maxUrlsPerFile');

		$languageUrls = $this->db
			->setQueryAnd($query)
			->loadAssocList();

		return empty($languageUrls)
			? []
			: $languageUrls;
	}

	/**
	 * Count the total number of URLs to be included in the sitemap for this crawl_id, per language.
	 *
	 * The sitemap will be split into smaller partials.
	 *
	 * @return $this
	 */
	private function countTotalUrls()
	{
		$whereClause               = $this->buildWhereClause();
		$this->urlCount            = 0;
		$this->urlCountPerLanguage = [];

		foreach ($this->languages as $language)
		{
			$query = 'select count(*) from ' . $this->db->quoteName('#__forseo_pages')
					 . $whereClause
					 . ' and ' . $this->db->quoteName('lang') . ' = ' . $this->db->quote($language);

			$this->urlCountPerLanguage[$language] = $this->db
				->setQueryAnd($query)
				->loadResult();

			$this->urlCount += $this->urlCountPerLanguage[$language];
		}

		return $this;
	}

	/**
	 * Build an SQL where clause to select only valid, non-duplicate and user-included pages.
	 * @return string
	 */
	private function buildWhereClause()
	{
		return ' where ' . $this->db->quoteName('enabled') . ' = 1'
			   . ' and ' . $this->db->quoteName('status') . ' = 0'
			   . ' and ' . $this->buildSitemapExclusionClause();
	}

	/**
	 * Build an SQL where clause to select only pages or images that are cleared to go into a sitemap.
	 * @return string
	 */
	private function buildSitemapExclusionClause()
	{
		return '((' . $this->db->quoteName('sitemap_mode') . ' = 0 and ' . $this->db->quoteName('sitemap_auto') . ' = 0)'
			   . ' or '
			   . '(' . $this->db->quoteName('sitemap_mode') . ' = 1 and ' . $this->db->quoteName('sitemap_user') . ' = 0))';
	}

	/**
	 * Create a temp folder to build the new sitemap
	 */
	private function prepareFileCache($type = Data\Sitemap::CONTENT)
	{
		$this->finalCacheFolder = Wb\slashTrimJoin(
			$this->cacheRootPath,
			'current'
		);

		$this->tempCacheFolder = Wb\slashTrimJoin(
			$this->cacheRootPath,
			'tmp'
		);

		$this->platform
			->createFolders($this->tempCacheFolder);

		return $this;
	}

	/**
	 * Delete all folders in the cache folders, then move completed sitemap files to their final location.
	 *
	 * @return $this
	 */
	private function moveFileCache()
	{
		// list existing folders
		$toDelete = $this->platform->listFolders(
			$this->cacheRootPath,
			'.',   // filter
			false, // recurse
			true,  // full path
			[ // exclude
			  'tmp',
			  'current'
			]
		);

		if (!empty($toDelete))
		{
			$this->platform->deleteFolders(
				$toDelete
			);
		}

		if (is_dir($this->finalCacheFolder))
		{
			// now look into 'current' and delete:
			// 		- manifest.php - will always be updated, no need to delete
			// 		- sitemap.xml - will always be updated, no need to delete
			// 		- any partial that's not part of the current crawlId, to avoid accumulation in case of errors
			$toDelete = $this->platform->listFiles(
				$this->finalCacheFolder,
				$filter = '\.xml(\.gz)?$',
				$recurse = false,
				$full = true,
				$exclude = array('.svn', 'CVS', '.DS_Store', '__MACOSX'),
				$excludeFilter = array('^\..*', '.*~'),
				$naturalSort = false
			);
			$toDelete = empty($toDelete)
				? []
				: $toDelete;

			// filter out current crawl_id files, if any, so as to recover from previous failures
			// for instance
			$toDelete = array_filter(
				$toDelete,
				function ($item)
				{
					return !Wb\contains(
						$item,
						$this->completedCrawlId
					);
				}
			);

			$toDelete = array_values($toDelete);
			$toDelete = array_merge(
				$toDelete,
				[
					Wb\slashTrimJoin(
						$this->finalCacheFolder,
						'manifest.php'
					),
				]
			);

			$this->platform
				->deleteFiles(
					$toDelete
				);
		}

		// Then move the new/updated files. 2 strategies:
		$existingFiles = $this->platform->listFiles(
			$this->finalCacheFolder
		);
		if (empty($existingFiles))
		{
			// 1. No files left in 'current': entirely new sitemap, we can move the entire tmp folder
			$this->platform->deleteFolders(
				$this->finalCacheFolder
			);
			$this->platform->moveFolders(
				[
					$this->tempCacheFolder => $this->finalCacheFolder
				]
			);
		}
		else
		{
			// 2. Some partials are left in the 'current', only a few partials were rebuilt
			// We keep them and move the extra/newer ones one by one to the new location
			// along the manifest and index sitemap files.
			$toMove = $this
				->platform->listFiles(
					$this->tempCacheFolder
				);

			if (!empty($toMove))
			{
				$this->platform
					->moveToFolder(
						$toMove,
						$this->tempCacheFolder,
						$this->finalCacheFolder
					);
			}

			$this->platform
				->deleteFolders($this->tempCacheFolder);
		}

		return $this;
	}

	/**
	 * Called after completing the creation of a sitemap, delete all sitemaps but the current one from database.
	 *
	 * Note: May want to keep some historical data. Maybe remove older than a year or so. Or on demand? In any case, those
	 * should be kept in a separate db table, with one row per search_engines/sitemap_type combination.
	 *
	 * @return $this
	 */
	private function purgeSitemaps($type = Data\Sitemap::CONTENT)
	{
		$this->db->delete(
			'#__forseo_sitemaps',
			[
				'type' => $type,
				['crawl_id', '!=', $this->completedCrawlId]
			]
		);

		return $this;
	}

	/**
	 * Create/load prior database record for current crawl_id sitemap.
	 *
	 * @param int $type The sitemap type, @see Data\Sitemap
	 *
	 * @return Sitemaps
	 */
	private function loadSitemapIndexRecord($type = Data\Sitemap::CONTENT)
	{
		$this->sitemapIndexRecord = $this->factory
			->getA(Data\Sitemap::class)
			->set(
				[
					'type'      => $type,
					'file_type' => Data\Sitemap::FILE_INDEX,
					'crawl_id'  => $this->completedCrawlId
				]
			)->loadPerColumn(
				[
					'crawl_id'  => $this->completedCrawlId,
					'file_type' => Data\Sitemap::FILE_INDEX
				]
			);

		return $this;
	}

	/**
	 * Initialize record for current crawl_id sitemap.
	 */
	private function initSitemapIndexRecord($type = Data\Sitemap::CONTENT)
	{
		$this->processedUrlCount = 0;

		$this->resetSitemapIndexRecord()
			 ->store();

		$this->sitemapIndexRecord
			->timestamp('created_at');

		return $this;

	}

	/**
	 * Reset and store the current sitemap index db record when
	 * we re-build the sitemap. Ie only some fields are reset.
	 *
	 * @return Data\Sitemap
	 * @throws \Exception
	 */
	private function resetSitemapIndexRecord()
	{
		$this->sitemapIndexRecord->set(
			[
				'hash'                => '',
				'google_submitted_at' => null,
				'google_last_fetch'   => null,
				'google_fetches'      => 0,
				'bing_submitted_at'   => null,
				'bing_last_fetch'     => null,
				'bing_fetches'        => 0,
				'state'               => Data\Sitemap::IN_PROGRESS,
			]
		);

		return $this->sitemapIndexRecord;
	}

	/**
	 * Ping the configured list of search engines with the current sitemap.
	 * Does not perform any check on existence of said sitemap, or whether it was
	 * already submitted.
	 *
	 * Should only be called right after creating/updating a sitemap.
	 */
	public function pingSearchEngines($type = Data\Sitemap::CONTENT)
	{
		$pending = $this->keystore->get(
			'sitemap.pingSearchEngines',
			false
		);

		if (!$pending)
		{
			return;
		}

		$taskHelper = $this->factory->getA(
			Helper\Task::class
		);

		if (!$taskHelper->shouldRun('pingSearchEngines'))
		{
			// set to run this task at most every minutes, see system config.
			return;
		}

		$this->loadSitemapIndexRecord();
		if (
			!$this->sitemapIndexRecord->exists()
			||
			$this->sitemapIndexRecord->get('state') != Data\Sitemap::READY
		) {
			$this->logger->debug('Sitemap: not pinging search engines, not sitemap built just yet.');
			// we can remove the ping flag as pinging will happen naturally when sitemap build is complete.
			$this->keystore->put('sitemap.pingSearchEngines', false);
			return;
		}

		$searchEnginesPingEnabled = $this->sitemapConfig->get('searchEnginesPingEnabled', []);
		$mainSitemapUrl           = $this->factory
			->getA(Helper\Sitemaps::class)
			->xmlUrl();

		/**
		 * Filter the list of fully qualified sitemaps URLs to be submitted to search engines
		 * when 4SEO creates/updates the site main sitemap.
		 *
		 * Can be used to include extra sitemaps such as additional languages ones for instance
		 * when using some 3rd-party multilingual extensions.
		 *
		 * You should not urlencode the URL, this will be done automatically when pinging the search engines.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\sitemap
		 * @var forseo_sitemap_se_ping_list
		 * @since   1.4.0
		 *
		 * @param array $sitemapUrls Array of fully qualified URLs of sitemaps to ping. Prefilled with 4SEO own.
		 *
		 * @return int
		 *
		 */
		$sitemapUrls = $this->factory
			->getThe('hook')
			->filter(
				'forseo_sitemap_se_ping_list',
				[
					$mainSitemapUrl
				]
			);

		$this->logger->debug('Before submitting sitemap to search engines: ' . "\n" . print_r($searchEnginesPingEnabled, true) . "\n" . ' with sitemap ' . print_r($sitemapUrls, true));

		foreach ($searchEnginesPingEnabled as $searchEngine)
		{
			try
			{
				foreach ($sitemapUrls as $sitemapUrl)
				{
					$this->logger->debug('Submitting sitemap to search engine ' . $searchEngine . ' with sitemap ' . $sitemapUrl);

					/**
					 * Filter the result of submitting the provided sitemap URL to the speficied search engine.
					 *
					 * @api     forseo
					 * @package 4SEO\filter\sitemap
					 * @var forseo_sitemap_submit
					 * @since   5.2.1
					 *
					 * @param string $searchEngine The search engine to ping.
					 * @param string $sitemapUrl   The fully qualified URL of the sitemap to ping.
					 *
					 * @return bool | \Exception
					 *
					 */
					$submissionResult = $this->factory
						->getThe('hook')
						->filter(
							'forseo_sitemap_submit',
							null,
							$searchEngine,
							$sitemapUrl
						);

					if ($submissionResult instanceof \Exception)
					{
						$this->logger->error(__METHOD__ . ': Error submitting sitemap to ' . $searchEngine . ', response code: ' . $submissionResult->getMessage());
					}
					else if (false === $submissionResult)
					{
						$this->logger->debug('Sitemap NOT submitted to ' . $searchEngine . ', probably not connected to search console.');
					}
					else
					{
						$this->logger->debug('Sitemap submitted to ' . $searchEngine . ', url submitted: ' . $sitemapUrl . ', main sitemap: ' . $mainSitemapUrl);
					}

					if (
						true === $submissionResult
						&&
						$sitemapUrl === $mainSitemapUrl
					) {
						// only update record for our own sitemap, additional ones may
						// have been added by user through the forseo_sitemap_se_ping_list filter
						$this->sitemapIndexRecord
							->timestamp($searchEngine . '_submitted_at')
							->store();
					}

					$this->logger->debug('Sitemap : Submitted sitemap ' . $this->completedCrawlId . ' to ' . $searchEngine . ' - url: ' . $sitemapUrl . ', main sitemap: ' . $mainSitemapUrl);
				}
			}
			catch (\Throwable $e)
			{
				$this->logger->error(__METHOD__ . ': Error submitting sitemap to ' . $searchEngine . ', response code: ' . $e->getCode() . ', message: ' . $e->getMessage());
			}
		}

		$this->keystore->put('sitemap.pingSearchEngines', false);
		$taskHelper->markRanAt('pingSearchEngines');

	}

	/**
	 * Format a MYSQL UTC datetime for use in sitemap, ie W3C datetime.
	 *
	 * @param string $datetime
	 * @return string
	 */
	private function formatLastMod($datetime)
	{
		if (
			empty($datetime)
			||
			Wb\contains($datetime, '0000')
		) {
			return '';
		}
		$parts = explode(' ', $datetime);
		return $parts[0] . 'T' . $parts[1] . 'Z';
	}

	/**
	 * Parse the requested file name to identify its crawl id and serial number (in case of a partial).
	 *
	 * @throws \Exception
	 */
	private function parseFileName()
	{
		$this->cachedIndexFilePath = Wb\slashTrimJoin(
			$this->cacheRootPath,
			'current',
			$this->helper->getMainFileName(Data\Sitemap::CONTENT)
		);

		if (Data\Sitemap::FILE_PARTIAL == $this->fileType)
		{
			$parts            = explode('.', $this->file);
			$this->fileSerial = $parts[3] ?? 0;
			if (empty($this->fileSerial))
			{
				// this partial does not have a proper serial number
				throw new \Exception('Sitemap partial not found, invalid serial number. Please check back sitemap-4seo.xml for up to date partials URLs.', System\Http::RETURN_NOT_FOUND);
			}
		}

		$this->cachedFilePath = Wb\slashTrimJoin(
			$this->cacheRootPath,
			'current',
			$this->file
		);

		return $this;
	}

	private function writeFile($filePath, $fileContent, $compressAlso = false)
	{
		file_put_contents(
			$filePath,
			$fileContent
		);

		if ($compressAlso)
		{
			$compressed = System\Http::compress(
				$fileContent
			);

			if ('gzip' == Wb\arrayGet($compressed, 'compressionMethod'))
			{
				file_put_contents(
					$filePath . '.gz',
					Wb\arrayGet($compressed, 'compressedContent')
				);
			}
		}

		return $this;
	}

	/**
	 * Read the content of the currently requested file, returning an empty string
	 * if it does not exist.
	 *
	 * @return string
	 */
	private function readFile()
	{
		return file_exists($this->cachedFilePath)
			? file_get_contents($this->cachedFilePath)
			: '';
	}
}

