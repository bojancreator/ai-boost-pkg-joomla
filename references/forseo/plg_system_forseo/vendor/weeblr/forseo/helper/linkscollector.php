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
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;

use Weeblr\Forseo\Data;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Linkscollector extends Base\Base
{
	/**
	 * @var array List of URL specification to be excluded from URL collection
	 */
	private $userExclusionsRules = [];

	/**
	 * @var array List of URL specification to be included in URL collection, applied AFTER userExclusionsRules
	 */
	private $userInclusionRules = [];

	/**
	 * @var array List of domains specification that should be excluded from URL collection.
	 */
	private $domainsRules = [];

	/**
	 * @var System\Config Convenience instance of the Pages configuration.
	 */
	private $pagesConfig = null;

	/**
	 * @var Robotstxt
	 */
	private $robotsTxtHelper = null;

	/**
	 * @var System\Log Application logger instance.
	 */
	private $logger = null;

	/**
	 * Loads up URL collection rules from user configuration.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->logger              = $this->factory->getThe('forseo.logger');
		$this->pagesConfig         = $this->factory->getThis('forseo.config', 'pages');
		$this->robotsTxtHelper     = $this->factory->getThe('forseo.robotsTxtHelper');
		$this->userExclusionsRules = $this->pagesConfig->get('collectionExclusions');
		$this->userInclusionRules  = $this->pagesConfig->get('collectionInclusions');
		$this->domainsRules        = array_merge(
			$this->pagesConfig->get('collectionDomainsBuiltin'),
			$this->pagesConfig->get('collectionDomains')
		);
	}

	/**
	 * Search all tables to decide whether a given URL
	 * has already been processed.
	 *
	 * @param string $url Original, full length URL
	 *
	 * @param bool   $excludeCollected
	 *
	 * @return mixed
	 */
	public function urlAlreadySeen($url, $excludeCollected = false)
	{
		if (!$excludeCollected)
		{
			$collectedUrl = $this->factory
				->getA(Data\Collected::class)
				->loadPerUrl($url);
			if ($collectedUrl->exists())
			{
				return $collectedUrl;
			}
		}

		$pageUrl = $this
			->factory
			->getA(Data\Page::class)
			->loadPerUrl(
				$url
			);
		if ($pageUrl->exists())
		{
			return $pageUrl;
		}

		// already a known link
		$linkUrl = $this
			->factory
			->getA(Data\Link::class)
			->loadPerUrl(
				$url
			);
		if ($linkUrl->exists())
		{
			return $linkUrl;
		}

		// already an error?
		$errorUrl = $this
			->factory
			->getA(Data\Error::class)
			->loadPerUrl(
				$url
			);
		if ($errorUrl->exists())
		{
			return $errorUrl;
		}

		// finally a URL excluded from collection?
		$excludedUrl = $this
			->factory
			->getA(Data\Excluded::class)
			->loadPerUrl(
				$url
			);
		if ($excludedUrl->exists())
		{
			return $excludedUrl;
		}

		return null;
	}

	/**
	 * Store collected links from the page body into the #__forseo_collected_urls table
	 * for further data collection.
	 *
	 * @param array          $links
	 * @param null|Data\Page $currentPage
	 * @param array          $options
	 *                               forceCollection => store as collectedURl no matter what.
	 *                               isUnknownRequest => straight incoming request, not a link, not from crawler
	 * @throws \Exception
	 */
	public function storeCollectedLinks($links, $currentPage = null, $options = [])
	{
		$counter       = 0;
		$hooks         = $this->factory->getThe('hook');
		$originalLinks = $links;
		$storedLinks   = [];

		/**
		 * Filter whether a single URL should be added to the collected link table.
		 * Preliminary test based only on the raw URL.
		 *
		 * We pass in a copy of the original list of links to filter so that plugins can
		 * override links exclusion that may have been decided by other plugins before them.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\frontend\collection
		 * @var forseo_should_collect_urls_found_on_page
		 * @since   1.0.0
		 *
		 * @param array $links
		 * @param array $originalLinks
		 *
		 * @return array
		 *
		 */
		$filteredLinks = $this->factory
			->getThe('hook')
			->filter(
				'forseo_should_collect_urls_found_on_page',
				$links,
				$originalLinks
			);

		if (empty($filteredLinks))
		{
			return;
		}

		/**
		 * Filter whether 4SEO should check robots.txt rules before crawling a page.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\frontend\collection
		 * @var forseo_should_check_robots_txt_before_collecting
		 * @since   1.0.4
		 *
		 * @param array $links
		 * @param array $originalLinks
		 *
		 * @return array
		 *
		 */
		$shouldCheckRobotsTxt = $this->factory
			->getThe('hook')
			->filter(
				'forseo_should_check_robots_txt_before_collecting',
				true
			);

		// Calling party can force the collection of links, even
		// if we already saw them.
		$forceCollection = Wb\arrayGet(
			$options,
			'forceCollection',
			false
		);

		if (
			!$forceCollection
			&&
			in_array(
				$currentPage->get('full_url'),
				$links
			))
		{
			$links = array_diff(
				$links,
				[$currentPage->get('full_url')]
			);
		}

		foreach ($filteredLinks as $link)
		{
			if (
				$shouldCheckRobotsTxt
				&&
				!$this->shouldCollectByRobotsTxt($link)
			) {
				continue;
			}

			// apply user-set filtering rules (Pages settings)
			if (!$this->shouldCollectByRules($link))
			{
				continue;
			}

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
			$filteredLink = $this->factory
				->getThe('hook')
				->filter(
					'forseo_clean_query_vars_to_strip',
					$link
				);

			// already a known collected links/page/link/error/excluded?
			$alreadySeenUrl = $this->urlAlreadySeen(
				$filteredLink
			);

			if ($alreadySeenUrl instanceof Data\Excluded)
			{
				// we've already seen this link and classified as something
				// we must not crawl. Stop here.
				continue;
			}

			// We store a number of different referrer URLs for the same collected URLs, not all.
			// The max number is dictated by the size of a the referrers column in the collected_urls table.
			// It means we may lose some referrers, however unlikely that is considering how many we can collect,
			// which is not exactly known as it depends on the length of the referring URLs.
			if (
				!empty($alreadySeenUrl)
				&&
				Wb\arrayIsEmpty($options, 'isUnknownRequest', false)
			) {
				$this->processAlreadySeenLink(
					$filteredLink,
					$alreadySeenUrl,
					$currentPage,
					$options
				);

				if (!$forceCollection)
				{
					continue;
				}
			}

			// prepare a new collected URL object
			$collectedUrl = $this->factory
				->getA(Data\Collected::class)
				->loadPerUrl($filteredLink);

			if ($collectedUrl->exists())
			{
				// URL has already been collected, is pending crawling, do nothing
				continue;
			}

			/**
			 * Filter whether a single URL should be added to the collected link table.
			 *
			 * @api     forseo
			 * @package 4SEO\filter\frontend\collection
			 * @var forseo_should_collect_url
			 * @since   1.0.0
			 *
			 * @param bool           $shouldCollectUrl
			 * @param Data\Collected $collectedUrl
			 *
			 * @return bool
			 *
			 */
			$shouldCollectUrl = $hooks->filter(
				'forseo_should_collect_url',
				true,
				$collectedUrl
			);

			if ($shouldCollectUrl)
			{
				// store URL after all checks
				$clickDepth = empty($currentPage)
					? Data\Url::CLICK_DEPTH_NONE
					: $currentPage->get('click_depth', 0) + 1;

				$collectedUrl->set(
					[
						'full_url'    => $filteredLink,
						'click_depth' => (int)$clickDepth,
						'priority'    => $this->buildPriority($filteredLink)
					]
				);

				if (
					!empty($currentPage)
					&&
					Wb\arrayIsEmpty($options, 'isUnknownRequest', false)
				) {
					$collectedUrl->addReferrers(
						$currentPage->get('full_url')
					);
				}

				try
				{
					$collectedUrl->store();
				}
				catch (\Throwable $e)
				{
					// due to concurrency, this very link may have been written to the collected_urls
					// table between the time we checked for its existence a few lines above and the time
					// we try to insert it, causing `Duplicate entry 'xxx' for key 'url'` and stopping the
					// manual crawling in admin (no adverse effect if crawl is front-end or cron triggered).
					//
					// Solution picked: accept the error. Discussion: /weeblr/forseo/-/issues/383
					if (1062 == $e->getCode())
					{
						continue;
					}
					else
					{
						throw $e;
					}
				}

				$counter++;
				$storedLinks[] = $collectedUrl->get('full_url');
			}
			else
			{
				// conclusion is that this URL should be excluded. Store it as such
				$this->factory
					->getA(Data\Excluded::class)
					->set('full_url', $filteredLink)
					->store();
			}
		}

		$currentPageUrl = empty($currentPage)
			? 'unknown'
			: $currentPage->get('full_url');

		$this->logger->debug('linksCollector helper. Found ' . count($links) . ' links on page ' . $currentPageUrl . ', ' . $counter . ' stored to collected_urls table');
		$this->logger->debug('linksCollector helper. Links stored: ' . print_r($storedLinks, true));
	}

	/**
	 * Computes a crawl priority on a simplistic heuristic: any image or media like URL
	 * has a lower priority than normal pages.
	 *
	 * @param string $url
	 * @return mixed
	 */
	private function buildPriority($url)
	{
		static $priorities;

		if (empty($priorities))
		{
			$priorities = array_merge(
				$this->pagesConfig->get('loggedMediaExtensions'),
				$this->pagesConfig->get('crawlerPhysicalFilesExtensions'),
				$this->pagesConfig->get('lowPriorityExtensions')
			);
		}

		$priority = Data\Collected::PRIORITY_NORMAL;

		if (Wb\endsWith(
			StringHelper::strtolower(
				$url
			),
			$priorities)
		) {
			$priority = Data\Collected::PRIORITY_LOW;
		}

		return $priority;
	}

	/**
	 * Process a link found on current page that has already been seen
	 * previously and has one or more records in the database.
	 *
	 * @param string   $link
	 * @param Data\Url $alreadySeenUrl
	 * @param Data\Url $currentPage
	 * @param array    $options
	 *                               forceCollection => store as collectedURl no matter what.
	 *                               isUnknownRequest => straight incoming request, not a link, not from crawler
	 * @throws \Exception
	 */
	private function processAlreadySeenLink($link, $alreadySeenUrl, $currentPage, $options = [])
	{
		$referrerHelper = $this->factory->getA(Referrer::class);

		switch (true)
		{
			case $alreadySeenUrl instanceof Data\Collected:
				// add current page as a referrer and stop there,
				// this URL is already scheduled for crawling.
				if (!empty($currentPage) && $currentPage->get('full_url') != $alreadySeenUrl->get('full_url'))
				{
					$alreadySeenUrl
						->addReferrers(
							$currentPage->get('full_url')
						)->store();
				}
				break;
			case $alreadySeenUrl instanceof Data\Link:
			case $alreadySeenUrl instanceof Data\Error:
				// This URL has already been crawled (as an Error or a Link),
				// we can add the referrer if not already existing.
				if (!empty($currentPage) && $currentPage->get('full_url') != $alreadySeenUrl->get('full_url'))
				{
					$referrerHelper->store(
						$alreadySeenUrl,
						$currentPage->get('full_url')
					);
				}
				break;
		}

		// handle special case: current link being looked at has been seen and stored
		// has an error. If this is a link on the site (ie not a direct request), we check
		// whether it's been also previously recorded as a Link. If not, we record it.
		if (
			!empty($currentPage)
			&&
			$alreadySeenUrl instanceof Data\Error
		) {
			$linkRecord = $this->factory
				->getA(Data\Link::class)
				->set(
					'full_url',
					$alreadySeenUrl->get('full_url')
				)->loadPerUrl(
					$link
				);

			if (!$linkRecord->exists())
			{
				$linkRecord->set(
					[
						'status'         => $alreadySeenUrl->get('status'),
						'full_final_url' => $alreadySeenUrl->get('full_url'),
						'last_hit'       => $alreadySeenUrl->get('last_hit'),
						'hits'           => $alreadySeenUrl->get('hits'),
					]
				)->store();

				$referrerHelper->store(
					$linkRecord,
					$currentPage->get('full_url')
				);
			}
		}

	}

	/**
	 * Whether a given URL passes the filtering rules set in a robots.txt file, if any.
	 *
	 * @param string $link
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function shouldCollectByRobotsTxt($link)
	{
		if ($this->pagesConfig->isFalsy('collectApplyRobotsTxt'))
		{
			return true;
		}

		// External link are not subject to robots.txt
		if (!System\Route::isInternal($link))
		{
			return true;
		}

		$link = $this->platform->getRootUrl()
				. $this->platform->getUrlRewritingPrefix()
				. System\Route::makeRootRelative($link);

		if ($this->robotsTxtHelper->isExcluded($link))
		{
			$this->logger->debug('linksCollector helper shouldCollectByRobotsTxt. Rejecting internal link ' . $link . ', excluded by robots.txt');
			return false;
		}

		return true;
	}

	/**
	 * Whether a given URL passes the filtering rules set by user in site analysis configuration.
	 * Also applies a set of default, built-in rules.
	 *
	 * @param string $link
	 *
	 * @return bool
	 */
	public function shouldCollectByRules($link)
	{
		$shouldCollectUrl = true;

		// External link, only apply external domains rules
		if (!System\Route::isInternal($link))
		{
			foreach ($this->domainsRules as $host)
			{
				if (System\Route::hostMatch(
					$host,
					$link)
				) {
					$this->logger->debug('linksCollector helper shouldCollectByRules. Rejecting external link ' . $link . ', it matches excluded domain ' . $host);

					return false;
				}
			}
		}
		else
		{
			$shouldCollectUrl = $this->factory
				->getA(Url::class)
				->passExclusionRules(
					$link,
					$this->userExclusionsRules,
					$this->userInclusionRules
				);
			if (!$shouldCollectUrl)
			{
				$this->logger->debug('linksCollector helper shouldCollectByRules. Rejecting internal link ' . $link . ', it triggered rejection rule');
			}
		}

		return $shouldCollectUrl;
	}

	/**
	 * Create a new record in the collected_urls table,
	 * based on knowing only the requested URL and
	 * checking it does not existing first.
	 *
	 * @param string $url
	 */
	public function addExcludedUrl($url)
	{
		if (Data\Url::HOME_PAGE === $url)
		{
			// never allow excluding the home page
			return;
		}

		$excludedUrl = $this->factory
			->getA(Data\Excluded::class)
			->loadPerUrl(
				$url
			);

		if (!$excludedUrl->exists())
		{
			$excludedUrl->set(
				[
					'full_url' => $url
				]
			)->store();

			$this->logger->debug('PDC Helper storeCollectedUrl: added ' . $url . ' to excluded_urls table.');
		}

		// in all cases, move it out of collected URLs
		$collectedUrl = $this->factory
			->getA(Data\Collected::class)
			->loadPerUrl('full_url');

		if ($collectedUrl->exists())
		{
			$collectedUrl->delete();
		}
	}
}