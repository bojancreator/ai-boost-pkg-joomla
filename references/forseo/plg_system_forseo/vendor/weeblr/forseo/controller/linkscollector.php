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

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Html;
use Weeblr\Wblib\Forseo\Wb;

use Weeblr\Forseo\Model\Config;
use Weeblr\Forseo\Data;
use Weeblr\Forseo\Helper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * This collector is in charge of collecting URLs with various methods and storing
 * them to the #__collected_urls table so that later Pagedatacollector can reload
 * them and collect data for each page.
 *
 * @package Weeblr\Forseo\Controller
 */
class Linkscollector extends Base\Base
{
	/**
	 * @var array Storage for extracted links.
	 */
	private $links = [];

	/**
	 * @var Object Storage for current page.
	 */
	private $currentPage = null;

	/**
	 * @var Helper\Linkscollector A helper for page collection-related features.
	 */
	private $collectorHelper = null;

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
	 *
	 * @param Data\Page $currentPage
	 */
	public function __construct($currentPage)
	{
		parent::__construct();

		$this->currentPage     = $currentPage;
		$this->logger          = $this->factory->getThe('forseo.logger');
		$this->config          = $this->factory->getThis('forseo.config', 'pages');
		$this->collectorHelper = $this->factory->getA(Helper\Linkscollector::class);
	}

	/**
	 * Store any link collected, checking whether they've been seen before and
	 * other conditions.
	 *
	 * @throws \Exception
	 */
	public function store()
	{
		if (!empty($this->links))
		{
			$this->collectorHelper->storeCollectedLinks(
				$this->links,
				$this->currentPage
			);
		}
	}

	/**
	 * Collects links found in current page.
	 *
	 * Returns a hash of the links found, if any or an empty string if no links.
	 *
	 * @param string $body
	 * @param bool   $wrapContentInHtmlDoc
	 *
	 * @return string
	 */
	public function extractLinksFromBody($body, $wrapContentInHtmlDoc = true)
	{
		$currentPageLinksHash = '';

		try
		{
			// run conditions: frontend, html document,...
			if (!$this->canRun())
			{
				return '';
			}

			$currentUrl = $this->currentPage->get('full_url');

			// gather a list of images from the body
			$this->links = Html\Extract::extractLinks(
				$body,
				[
					'currentUrl'       => System\Route::makeRootRelative(
						$currentUrl
					),
					'onlyInternal'     => false,
					'skipTargetBlank'  => $this->config->isTruthy('collectSkipTargetBlank'),
					'skipNoFollow'     => $this->config->isTruthy('collectSkipNoFollow'),
					'skipAnchors'      => true,
					'stripAnchors'     => true,
					'skipNonHttp'      => true,
					'removeQuery'      => false,
					'skipHreflang'     => $this->config->isTruthy('collectSkipHreflang'),
					'queryVarsToStrip' => [
						FORSEO_CRAWLER_CDN_BUST_VAR
					],
					'rawUrlDecode'         => true,
					'wrapContentInHtmlDoc' => $wrapContentInHtmlDoc
				]
			);

			$this->sanitize();

			$this->logger->debug(__METHOD__ . ', collected links on: ' . $currentUrl . "\n" . print_r($this->links, true));

			$currentPageLinksHash = System\Auth::hashContent($this->links);
		}
		catch (\Throwable $e)
		{
			$this->logger->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}

		// make sure to return body in all cases
		return $currentPageLinksHash;
	}

	/**
	 * Runs basic checks on the list of links collected.
	 *
	 * @return void
	 */
	private function sanitize()
	{
		$this->links = empty($this->links)
			? []
			: $this->links;

		// identify - and remove tiny variations of home page
		$siteMainAddress                = $this->factory->getThis('forseo.config', 'pages')->get('canonicalRootUrl');
		$siteMainAddressNoTrailingSlash = Wb\rTrim($siteMainAddress, '/');
		foreach ($this->links as $index => $link)
		{
			if (
				empty($link)
				||
				$link === $siteMainAddressNoTrailingSlash
			) {
				// don't force a crawl of home page, we already did that
				// when analysis was started
				unset($this->links[$index]);
			}
		}

	}

	/**
	 * Check whether current request is on frontend and for an HTML page.
	 *
	 * @return bool
	 */
	private function canRun()
	{
		$shouldCollectUrls = $this->config->isTruthy('collectionEnabled')
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

		/**
		 * Filter whether links collection should happen on the current page.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\frontend\collection
		 * @var forseo_should_collect_urls
		 * @since   1.0.0
		 *
		 * @param bool      $shouldCollectUrls
		 * @param Data\Page $pageData
		 *
		 * @return bool
		 *
		 */
		return $this->factory->getThe('hook')->filter(
			'forseo_should_collect_urls',
			$shouldCollectUrls,
			$this->currentPage
		);
	}
}

