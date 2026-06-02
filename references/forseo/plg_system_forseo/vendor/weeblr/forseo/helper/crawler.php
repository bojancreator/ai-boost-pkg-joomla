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

namespace Weeblr\Forseo\Helper;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Db;

use Weeblr\Forseo\Data;
use Weeblr\Forseo\Model;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Crawler extends Base\Base
{
	/**
	 * @var [} Array of detection flags for normal or debug mode crawler requests.
	 */
	private static $isCrawlerRequestFlags;

	/**
	 * Force refresh of stored completed and in progress crawl ids.
	 *
	 * For performance, we cache completed and in progress crawl id during
	 * the request. However, if a crawl completes during the current request
	 * there are several things that needs to be updated and depends on the
	 * current and valid values of these crawl ids.
	 *
	 * And so when a crawl complete, a call to this method will make sure
	 * any subsequent call to read ids will have updated values.
	 * @return $this
	 */
	public function updateCrawlIds()
	{
		$this->getCompletedCrawlId(true);
		$this->getInProgressCrawlId(true);

		return $this;
	}

	/**
	 * Get the currently completed crawl id from the keystore, returns an empty
	 * string if none completed yet.
	 *
	 * @return string
	 */
	private function getCrawlId($status)
	{
		$crawl = $this->factory
			->getThe('forseo.keystore')
			->get($status);

		return Wb\arrayGet($crawl, 'id', '');
	}

	/**
	 * Get the currently completed crawl id from the keystore, returns an empty
	 * string if none completed yet.
	 *
	 * @param bool $forceUpdate
	 * @return string
	 */
	public function getCompletedCrawlId($forceUpdate = false)
	{
		static $crawlId = null;

		if (is_null($crawlId) || $forceUpdate)
		{
			$crawlId = $this->getCrawlId('crawl.completed');
		}

		return $crawlId;
	}

	/**
	 * Whether a completed crawl is currently available.
	 *
	 * @param bool $forceUpdate
	 * @return bool
	 */
	public function isCrawlComplete($forceUpdate = false)
	{
		return !empty($this->getCompletedCrawlId($forceUpdate));
	}

	/**
	 * Id of a crawl in progress, if any.
	 *
	 * @param bool $forceUpdate
	 * @return string
	 */
	public function getInProgressCrawlId($forceUpdate = false)
	{
		static $crawlId = null;

		if (is_null($crawlId) || $forceUpdate)
		{
			$crawlId = $this->getCrawlId('crawl.in_progress');
		}

		return $crawlId;
	}

	/**
	 * Whether a crawl is currently in progress.
	 *
	 * @param bool $forceUpdate
	 * @return bool
	 */
	public function isCrawlInProgress($forceUpdate = false)
	{
		return !empty($this->getInProgressCrawlId($forceUpdate));
	}

	/**
	 * Whether current request is coming from our crawler as identified
	 * by the secret key passed in the FORSEO_CRAWLER_SEC_HEADER request header.
	 *
	 * @return bool
	 */
	public function isCrawlerRequest()
	{
		$this->detectCrawlerRequest();

		return self::$isCrawlerRequestFlags['normal'];
	}

	/**
	 * Whether current request is a crawler request in debugging mode, as identified
	 * by the secret key passed in the FORSEO_CRAWLER_DEBUG_HEADER request header.
	 *
	 * @return bool
	 */
	public function isDebugCrawlerRequest()
	{
		$this->detectCrawlerRequest();

		return self::$isCrawlerRequestFlags['debug'];
	}

	/**
	 * Whether current request is a crawler request, in normal or debugging mode, as identified
	 * by the secret key passed in the x-wblr-* request headers.
	 *
	 * @return bool|null[]
	 */
	private function detectCrawlerRequest()
	{
		if (is_null(self::$isCrawlerRequestFlags))
		{
			self::$isCrawlerRequestFlags = [
				'normal' => false,
				'debug'  => false
			];

			// have our header?
			self::$isCrawlerRequestFlags['normal'] = $this->getAndValidateCrawlerHeader(FORSEO_CRAWLER_SEC_HEADER);
			self::$isCrawlerRequestFlags['debug']  = $this->getAndValidateCrawlerHeader(FORSEO_CRAWLER_DEBUG_HEADER);

			if (self::$isCrawlerRequestFlags['debug'])
			{
				self::$isCrawlerRequestFlags['normal'] = true;
			}
		}

		return $this;
	}

	/**
	 * Finds out if a given header has been set for the current request and if it matches
	 * the secret key in configuration.
	 *
	 * @param string $headerName
	 *
	 * @return bool
	 */
	private function getAndValidateCrawlerHeader($headerName)
	{
		$headerValue = System\Http::getRequestHeader($headerName);

		if (empty($headerValue))
		{
			return false;
		}

		// is its value ok? if not, kill the request with a 404
		if ($headerValue !== $this->factory->getThis('forseo.config', 'system')->get('cronKey'))
		{
			// kill the request
			System\Http::render(
				System\Http::RETURN_NOT_FOUND,
				$cause = '',
				$type = 'text/html',
				$otherHeaders = [],
				$endRequest = true
			);
		}

		return true;
	}

	/**
	 * Disable site offline mode when this is a crawler request.
	 */
	public function disableOfflineMode()
	{
		if ($this->isCrawlerRequest())
		{
			$this->platform->disableOfflineMode();
		}
	}

	/**
	 * Gets the referrer info from the crawler request header in FORSEO_CRAWLER_REFERRER_HEADER.
	 *
	 * @return array
	 */
	public function getCrawlReferrer()
	{
		static $crawlReferrer = null;

		if (is_null($crawlReferrer))
		{
			// get request headers
			$crawlReferrer = System\Http::getRequestHeader(FORSEO_CRAWLER_REFERRER_HEADER);
		}

		return $crawlReferrer;
	}

	/**
	 * Whether current crawler request was part of a redirect.
	 *
	 * @return bool
	 */
	public function redirectCount()
	{
		static $redirectCounts = null;

		if (is_null($redirectCounts))
		{
			// get request headers
			$redirectCounts = System\Http::getRequestHeader(FORSEO_CRAWLER_REDIRECT_COUNT_HEADER);
			$redirectCounts = (int)$redirectCounts;
		}

		return $redirectCounts;
	}

	/**
	 * Search for a home page record in pages, errors, collectd_urls or excluded_urls
	 * @return bool
	 */
	public function homePageAlreadySeen()
	{
		// If no URL to crawl, we may just be starting a new crawl, or 4SEO has just been installed.
		// Try to start from home page.
		// Check home page is not already in pages
		$dbHelper = $this->factory->getThe('db');
		$homeUrl  = $this->platform->getHomeUrl();
		$found    = $dbHelper->count(
			'#__forseo_pages',
			'*',
			[
				'url' => $homeUrl
			]
		);

		// check home page is not already in errors
		$found =
			!empty($found)
			||
			$dbHelper->count(
				'#__forseo_errors',
				'*',
				[
					'url' => $homeUrl
				]
			);

		// or in collected URLs
		$found =
			!empty($found)
			||
			$dbHelper->count(
				'#__forseo_collected_urls',
				'*',
				[
					'url' => $homeUrl
				]
			);

		// or in excluded URLs
		$found =
			!empty($found)
			||
			$dbHelper->count(
				'#__forseo_excluded_urls',
				'*',
				[
					'url' => $homeUrl
				]
			);

		return $found;
	}

	/**
	 * Insert a record for the home page into the collected_urls table.
	 * Also: delete any record for home page in Links or Errors table:
	 *  - that would prevent a crawl to actually start
	 *  - it gives a new chance to re-evaluate the home page, which should really
	 * NOT be an error or a link.
	 * This will cause a crawl to be triggered from the start.
	 *
	 * @throw \Exception
	 */
	public function insertHomePage()
	{
		$this->factory->getA(Data\Link::class)
					  ->loadPerUrl('')
					  ->delete();
		$this->factory->getA(Data\Error::class)
					  ->loadPerUrl('')
					  ->delete();

		$homeUrl = $this->platform->getHomeUrl(true);
		$home    = $this->factory
			->getA(Data\Collected::class)
			->set(
				[
					'full_url'    => $homeUrl,
					'click_depth' => 0
				]
			)
			->store();

		$this->factory->getThe('forseo.logger')->debug(static::class . ', crawler: Inserted home page to collected_urls table for a start, new row id: ' . $home->getId() . ', home url: ' . $homeUrl);

		return $this;
	}

}