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

use \Weeblr\Forseo\Data;
use \Weeblr\Forseo\Helper;
use \Weeblr\Forseo\Controller;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\Db;
use Weeblr\Wblib\Forseo\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * This collector collects detailed data about the current page
 * and store them in the #__forseo_pages table.
 *
 * The data collection is a resource-intensive operation so it's meant to be ran
 * only when current page is fetched by deferred trigger such as our cron task.
 *
 * On a normal request, only the Linkscollector runs and identifies
 * whether current page needs to have its data collected.
 *
 * @package Weeblr\Forseo\Controller
 */
class Pagedatacollector extends Base\Base
{
	/**
	 * @var Helper\Crawler A helper for crawler-related features.
	 */
	private $crawlerHelper = null;

	/**
	 * @var Helper\Linkscollector A helper for crawler-related features.
	 */
	private $linksCollectorHelper = null;

	/**
	 * @var Config Holds the page collection config object.
	 */
	private $pagesConfig = null;

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
		$this->pagesConfig          = $this->factory->getThis('forseo.config', 'pages');
		$this->crawlerHelper        = $this->factory->getThe('forseo.crawlerHelper');
		$this->linksCollectorHelper = $this->factory->getThe(Helper\Linkscollector::class);
	}

	/**
	 * Store current page content information to database.
	 *
	 * @param Data\Url $currentPage
	 * @param Data\Url $existingRecord
	 * @param bool     $isCrawlerRequest
	 *
	 * @return void
	 */
	public function storePage($currentPage, $existingRecord, $isCrawlerRequest)
	{
		try
		{
			$this->logger->debug('PDC Model storePage receiving page data:' . print_r($currentPage->get(), true));
			if (!empty($existingRecord))
			{
				$this->logger->debug('PDC Model storePage receiving existing record of class: ' . get_class($existingRecord) . ', ' . print_r($existingRecord->get(), true));
			}

			if ($existingRecord instanceof Data\Excluded)
			{
				// it was already discovered and deemed not worth crawling
				return;
			}

			// we've seen this URL somewhere before, it may need updating if its content has changed
			if ($existingRecord instanceof Data\Page)
			{
				// if page is known, compare recorded content with this page rendering. Update record if changed.
				$this->logger->debug('PDC Model storePage updating collected info for PAGE:' . print_r($currentPage->get(), true));

				$hasChanged = false;
				$fields     = [
					'status',
					'hash',
					'hash_links',
					'hash_images',
					'full_content_id',
					'lang',
					'content_lang',
					'canonical_auto',
					'sitemap_auto',
					// Do not override user-set values with automatic/default ones
					//'canonical_user',
					//'sitemap_user'
					//'canonical_mode',
					//'sitemap_mode',
				];

				foreach ($fields as $field)
				{
					if ($existingRecord->get($field) != $currentPage->get($field))
					{
						$existingRecord
							->set(
								$field,
								$currentPage->get($field)
							);
						$hasChanged = true;
					}
				}

				// Input vars have changed
				$currentPageInputvars    = json_encode($currentPage->get('input_vars'));
				$existingRecordInputvars = json_encode($existingRecord->get('input_vars'));
				if ($existingRecordInputvars != $currentPageInputvars)
				{
					$existingRecord
						->set(
							'input_vars',
							$currentPage->get('input_vars')
						);
					$hasChanged = true;
				}

				// Did anything changed?
				if ($hasChanged)
				{
					$this->logger->debug('PDC Model storePage updating collected info for PAGE, content has changed, calling updateExistingPage ');
					$this->updateExistingPage(
						$currentPage,
						$existingRecord,
						$isCrawlerRequest
					);
				}
				else
				{
					$this->logger->debug('PDC Model storePage updating collected info for PAGE, content has NOT changed, NOT calling updateExistingPage');
				}

				// Last thing: it is a page, but it may also have been in error at some point.
				// Should we remove it from the list of errors as well?
				// -> Nope, that's valid information, keep it.

			}
			else if ($existingRecord instanceof Data\Error)
			{
				$this->upgradeErrorToPage(
					$currentPage,
					$existingRecord,
					$isCrawlerRequest
				);
			}
			else if ($existingRecord instanceof Data\Link)
			{
				$this->upgradeLinkToPage(
					$currentPage,
					$existingRecord,
					$isCrawlerRequest
				);
			}
			else
			{
				$this->storeNewPage(
					$currentPage,
					$isCrawlerRequest
				);
			}

		}
		catch (\Throwable $e)
		{
			$this->logger->error('PDC Model storePage data collection %s::%d %s %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
	}

	/**
	 * Stores current page content information to database for a never seen page.
	 *
	 * @param Data\Url $currentPage
	 * @param bool     $isCrawlerRequest
	 * @throws \Exception
	 */
	private function storeNewPage($currentPage, $isCrawlerRequest)
	{
		// This is a totally new page
		$this->logger->debug(
			'PDC Model storePage, storing a new crawled page: '
			. $currentPage->get('full_url')
			. ', isCrawlerRequest: ' . ($this->crawlerHelper->isCrawlerRequest() ? 'yes' : 'no')
			. ', hasRedirects: ' . ($this->crawlerHelper->redirectCount() ? 'yes' : 'no')
		);

		// No existing record (or existing record is a Collected URL - which we don't want to store
		// and will be deleted by the crawler after that request as completed)
		// -> store as a new page.
		if (
			$isCrawlerRequest
			&&
			$this->crawlerHelper->redirectCount() <= 0
		) {
			// Check for previously user-set canonical override
			$userSetCanonicalInclusion = $this->factory
				->getA(Data\Canonicalincludes::class)
				->loadPerColumn(
					'url',
					$currentPage->get('url')
				);
			if ($userSetCanonicalInclusion->exists())
			{
				// carry over sitemap inclusion/exclusion set by user
				$currentPage->set(
					[
						'canonical_mode' => $userSetCanonicalInclusion->get('canonical_mode'),
						'canonical_user' => $userSetCanonicalInclusion->get('canonical_user')
					]
				);
			}

			// Check for previously user-set sitemap inclusion
			$userSetSitemapInclusion = $this->factory
				->getA(Data\Sitemapsincludes::class)
				->loadPerColumn(
					'url',
					$currentPage->get('url')
				);
			if ($userSetSitemapInclusion->exists())
			{
				// carry over sitemap inclusion/exclusion set by user
				$currentPage->set(
					[
						'sitemap_mode' => $userSetSitemapInclusion->get('sitemap_mode'),
						'sitemap_user' => $userSetSitemapInclusion->get('sitemap_user')
					]
				);
			}

			// If we had some performance metrics for that page but an analysis reset was done, we must
			// re-attach the perf status flag (the joy of denormalization)
			$perfModel        = $this->factory->getA(Perf::class);
			$existingPerfData = $perfModel->getPerfMetrics(
				$currentPage->get('full_url')
			);
			$currentPage->set(
				'perf_status',
				$perfModel->pass($existingPerfData)
			);

			// is a crawler request, all data has been collected, can store the Page data
			$currentPage->timestamp('crawled_at')
						->timestamp('last_hit')
						->store();

			$this->factory
				->getA(Controller\Sitemap::class)
				->markOutDated();

			$this->logger->debug('PDC Model storePage added collected info for a newly collected URL:' . print_r($currentPage->get(), true));
		}
		else if ($isCrawlerRequest)
		{
			// request from our crawler but there were one or more redirects to get here. The crawler will store this URL as a link
			// but this page rendered properly (no more redirect) so we should put it up on the collected_urls list
			// (unles it's already there)
			// @TODO Potential issue if the redirects go to a page we do not index (query vars, excluded by user, etc)
			$collectedUrl = $this->factory
				->getA(Data\Collected::class)
				->set(
					[
						'full_url' => $currentPage->get('full_url')
					]
				)->loadPerUrl($currentPage->get('full_url'));

			if (!$collectedUrl->exists())
			{
				$collectedUrl->store();
			}

			$this->logger->debug('PDC Model storePage: crawler request after one or more redirect rendered successfully, storing it as collected_urls for later recrawl.');
		}
		//else
		//{
		// this is a request by a regular visitor for a URL that's already on the list of URLs to crawl: do nothing
		//}
	}

	/**
	 * Stores current page content information to database for an existing page
	 * after we have detected it has changed. If content has changed, it must be recrawled.
	 *
	 * @param Data\Url $currentPage
	 * @param Data\Url $existingRecord
	 * @param bool     $isCrawlerRequest
	 * @throws \Exception
	 */
	private function updateExistingPage($currentPage, $existingRecord, $isCrawlerRequest)
	{
		// if a crawler request, page has been fully processed, store it
		if ($this->crawlerHelper->isCrawlerRequest())
		{
			$existingRecord->timestamp('crawled_at');
			$this->factory
				->getA(Controller\Sitemap::class)
				->markOutDated();
		}

		if (!$this->crawlerHelper->isCrawlerRequest())
		{
			// if not a crawler request, processing is partial (discovered links have not been stored for instance)
			// but we don't care about whatever data we may have found, we just ask the crawler to re-crawl the page.
			$this->factory
				->getA(Helper\Linkscollector::class)
				->storeCollectedLinks(
					[
						$currentPage->get('full_url')
					],
					$currentPage
				);

			$this->logger->debug('PDC Model storePage: valid page but not a crawler request, was seen before however so added back to the collected_urls for later recrawling');
		}

		$existingRecord->timestamp('last_hit')
					   ->store();
	}

	/**
	 * Stores current page content information to database for a known error which is now
	 * rendering fine it seems. The crawler will delete any Error record and their referrers if any.
	 *
	 * @param Data\Url $currentPage
	 * @param Data\Url $existingRecord
	 * @param bool     $isCrawlerRequest
	 * @throws \Exception
	 */
	private function upgradeErrorToPage($currentPage, $existingRecord, $isCrawlerRequest)
	{
		$this->logger->debug('PDC Model storePage updating collected info for an ERROR:' . print_r($currentPage->get(), true));

		if ($isCrawlerRequest)
		{
			// If there are some redirects, the crawler will delete the Page or Error records
			// for that URL so no need to write to the page, it'll be deleted right away
			// if no redirect took place, the crawler will also delete error and links records, including referrers
			// So the only thing to do is store as a page.
			$this->storeNewPage(
				$currentPage,
				$isCrawlerRequest
			);
		}
		else
		{
			$this->factory
				->getA(Helper\Linkscollector::class)
				->storeCollectedLinks(
					[
						$currentPage->get('full_url')
					],
					$currentPage
				);

			$this->logger->debug('PDC Model storePage: valid page but not a crawler request, was seen before however so added back to the collected_urls for later recrawling');
		}

		// finally invalidate the sitemap, as we now have a new page
		$this->factory
			->getA(Controller\Sitemap::class)
			->markOutdated();
	}

	/**
	 * Stores current page content information to database for a known Link which is now
	 * rendering fine it seems. The crawler will delete any Link record and their referrers if any.
	 *
	 * @param Data\Url $currentPage
	 * @param Data\Url $existingRecord
	 * @param bool     $isCrawlerRequest
	 * @throws \Exception
	 */
	private function upgradeLinkToPage($currentPage, $existingRecord, $isCrawlerRequest)
	{
		// found this URL elsewhere, links, collected urls
		$this->logger->debug('PDC Model upgradeLinkToPage updating collected info for a LINK : ' . print_r($currentPage->get(), true));

		// Beware, current request can be from Crawler or NOT.

		// it was a link (hence an error or a redirect), but now it's a page being rendered?
		// Reason #1: it was previously redirected to something else, but now that redirect has been removed.
		// Reason #2: the link ended up an error the 1st time and the problem was solved (we must have an error record)

		if ($isCrawlerRequest)
		{
			// we have collected all information, store as a new page
			// the Crawler will delete all Error/Link information (and their referrers if any)
			$this->storeNewPage(
				$currentPage,
				$isCrawlerRequest
			);
		}
		else
		{
			// put back the URL onto collected_urls for recrawl.
			$this->factory
				->getA(Helper\Linkscollector::class)
				->storeCollectedLinks(
					[
						$currentPage->get('full_url')
					],
					$currentPage
				);

			$this->logger->debug('PDC Model storePage: was a link but is now rendered as a valid page. Add to collected_urls for later recrawl as this is not a crawler request.');
		}

		// finally invalidate the sitemap, as we now have a new page
		$this->factory
			->getA(Controller\Sitemap::class)
			->markOutdated();
	}

	/**
	 * Collect data about errors on the site. Note that errors on crawler requests
	 * are recorded by the crawler, so the this method will only be called on
	 * non-crawler requests.
	 *
	 * @param \Exception $error
	 * @param Data\Url   $currentPage
	 *
	 */
	public function storeError($error, $currentPage)
	{
		try
		{
			$errorCode = $error->getCode();
			$indexableUrl = $this->factory
				->getThe('db')
				->storageSafe(
					$currentPage->get('full_url')
				);

			$message = Wb\join(':::', $error->getFile(), $error->getLine(), $error->getMessage());

			$this->factory
				->getA(Data\Error::class)
				->set(
					[
						'full_url' => $currentPage->get('full_url'),
						'status'   => $errorCode,
					]
				)->loadWhere(
					[
						'url'    => $indexableUrl,
						'status' => $errorCode,
					]
				)->timestamp('last_hit')
				->increment('hits')
				->set(
					[
						'message' => $message
					]
				)->store();

			// Does this exist as a page? if so, update the status and the hit counter
			$page = $this->factory
				->getA(Data\Page::class)
				->set(
					[
						'full_url' => $currentPage->get('full_url'),
					]
				)->loadWhere(
					[
						'url' => $indexableUrl,
					]
				);
			if ($page->exists())
			{
				$page->timestamp('last_hit')
					 ->set('status', $errorCode)
					 ->increment('hits')
					 ->store();
			}

			// does this exist as a link? if so, update the hit counter there as well.
			$link = $this->factory
				->getA(Data\Link::class)
				->set(
					[
						'full_url' => $currentPage->get('full_url'),
						'status'   => $errorCode,
					]
				)->loadWhere(
					[
						'url'    => $indexableUrl,
						'status' => $errorCode,
					]
				);
			if ($link->exists())
			{
				$link->timestamp('last_hit')
					 ->increment('hits')
					 ->store();
			}

			// In all cases, log to error log
			if (System\Http::RETURN_NOT_FOUND !== $errorCode)
			{
				$this->logger->error('PDC onError Stored error %s::%d %s %s', $error->getFile(), $error->getLine(), $error->getMessage(), $error->getTraceAsString());
			}
		}
		catch (\Throwable $e)
		{
			$this->logger->error('PDC onAfterRender data collection %s::%d %s %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
	}
}
