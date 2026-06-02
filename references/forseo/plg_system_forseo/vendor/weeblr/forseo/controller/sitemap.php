<?php
/**
 * Project: 4SEO
 *
 * @package                 4SEO
 * @copyright               Copyright Weeblr llc - 2020 - 2026
 * @author                  Yannick Gaultier - Weeblr llc
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 6.10.1.2660
 *
 * 2026-01-30
 */

namespace Weeblr\Forseo\Controller;

use Weeblr\Forseo\Data;
use Weeblr\Forseo\Helper;
use Weeblr\Forseo\Model;

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Seo;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Render a story page
 */
class Sitemap extends Base\Base
{
	/**
	 * @var System\Log Convenience access to app logger.
	 */
	private $logger;

	/**
	 * @var Helper\Sitemaps
	 */
	private $helper;

	/**
	 * @var System\Config Convenience access to sitemap config.
	 */
	private $sitemapConfig;

	/**
	 * @var System\Config Convenience access to system config.
	 */
	private $systemConfig;

	/**
	 * @var string The normalized file name requested, ie sitemap.xml
	 */
	private $normalizedCurrentRequestUrl;

	/**
	 * Instantiate convenience properties to main configs and logger.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->logger        = $this->factory->getThe('forseo.logger');
		$this->helper        = $this->factory->getA(Helper\Sitemaps::class);
		$this->sitemapConfig = $this->factory->getThis('forseo.config', 'sitemaps');
		$this->systemConfig  = $this->factory->getThis('forseo.config', 'system');
	}

	/**
	 * Render the site xml sitemap in response to a request
	 * to /sitemap.xml or a partial.
	 *
	 */
	public function run()
	{
		try
		{
			if ($this->sitemapConfig->isFalsy('enabled'))
			{
				return;
			}

			$requestType = $this->getSitemapRequest();
			if (Data\Sitemap::FILE_NONE != $requestType)
			{
				$this->render($requestType);
			}
		}
		catch (\Throwable $e)
		{
			$this->logger->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
			System\Http::render(
				$e->getCode(),
				$e->getMessage(),
				'text/html',
				[
					'X-4seo-generator' => '4SEO',
				],   // $otherHeaders
				true // $endRequest
			);
		}
	}

	/**
	 * Triggered when a full crawl has just completed and the sitemap
	 * should be created/updated.
	 *
	 * @param array $crawl
	 */
	public function onCrawlComplete($crawl)
	{
		try
		{
			if ($this->sitemapConfig->isFalsy('enabled'))
			{
				return;
			}
		}
		catch (\Throwable $e)
		{
			$this->logger->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
	}

	/**
	 * Add a flag in keystore to signal that sitemap is outdate with respect
	 * to current data set (eg some new page has been discovered, some now return 404,etc)
	 */
	public function markOutdated()
	{
		$this->factory
			->getThe('forseo.keystore')
			->safePut(
				'sitemap.rebuildRequired',
				System\Date::getUTCNow(
					'Y-m-d H:i:s',
					true  // $refresh =
				)
			);
	}

	/**
	 * Determine whether this is a request for the site sitemap.
	 *
	 * @return string
	 */
	private function getSitemapRequest()
	{
		$sitemapFileName = $this->factory
			->getA(Helper\Sitemaps::class)
			->getMainFilename(
				Data\Sitemap::CONTENT
			);
		if (empty($sitemapFileName))
		{
			return Data\Sitemap::FILE_NONE;
		}

		$currentRequestUrl                 = $this->factory->getThe('forseo.pageHelper')->getCleanedCurrentUrl();
		$this->normalizedCurrentRequestUrl = $this->platform->normalizeUrl($currentRequestUrl);

		// main sitemap
		if ($sitemapFileName == $this->normalizedCurrentRequestUrl)
		{
			return Data\Sitemap::FILE_INDEX;
		}

		// match sub-file format
		return $this->helper->isPartialFileName($this->normalizedCurrentRequestUrl)
			? Data\Sitemap::FILE_PARTIAL
			: Data\Sitemap::FILE_NONE;
	}

	/**
	 * Get sitemap content from model and return it.
	 *
	 * Simplified version, will have to stream the output back. Unless the
	 * limited number of URLs per file makes it OK to stick with simple rendering?
	 *
	 * @param string $requestType The request type, main or partial.
	 *
	 */
	private function render($requestType)
	{
		$model = $this->factory
			->getA(Model\Sitemaps::class);

		$sitemap = $model->getXml(
			$this->normalizedCurrentRequestUrl,
			$requestType
		);

		$status = Wb\arrayGet($sitemap, 'status');

		if (System\Http::RETURN_OK === $status)
		{
			$model->trackSearchEnginesVisits($requestType);
		}

		// NB: using "private" to invalidate Siteground "Supercacher"
		$responseHeaders = [
			'Cache-Control'    => 'private, no-store, no-cache, no-transform, must-revalidate, post-check=0, pre-check=0',
			'Expires'          => 'Wed, 17 Aug 2005 00:00:00 GMT',
			'X-4seo-generator' => '4SEO',
			'X-4seo-crawl-id'  => Wb\arrayGet($sitemap, 'crawl_id'),
			'X-4seo-sitemap'   => Wb\arrayGet($sitemap, 'file')
		];

		$isPreCompressed = System\Http::RETURN_OK === $status
			? Wb\endsWith(
				$this->normalizedCurrentRequestUrl,
				'.gz'
			)
			: false;

		if ($isPreCompressed)
		{
			$responseHeaders['Content-Encoding'] = 'gzip';
			$responseHeaders['Vary']             = 'Accept-Encoding';
		}

		System\Http::send(
			Wb\arrayGet($sitemap, 'content'),
			$status,
			[
				'compress' => $isPreCompressed
					? 'none'
					: 'gzip',
				'type'     => System\Http::RETURN_OK == $status
					? 'application/xml; charset=utf-8'
					: 'text/html; charset=utf-8',
				'headers'  => $responseHeaders
			]
		);
	}
}
