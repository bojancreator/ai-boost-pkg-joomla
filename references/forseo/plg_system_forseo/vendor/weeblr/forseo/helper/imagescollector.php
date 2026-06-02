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
use Weeblr\Wblib\Forseo\Db;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Html;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;

use Weeblr\Forseo\Data;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Imagescollector extends Base\Base
{
	/**
	 * @var System\Config Convenience instance of the Pages configuration.
	 */
	private $pagesConfig = null;

	/**
	 * @var System\Config Convenience instance of the Pages configuration.
	 */
	private $sitemapsConfig = null;

	/**
	 * @var Robotstxt
	 */
	private $robotsTxtHelper = null;

	/**
	 * @var Html\Image
	 */
	private $imageHelper = null;
	/**
	 *
	 * @var Url
	 */
	private $urlHelper = null;

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

		$this->logger          = $this->factory->getThe('forseo.logger');
		$this->pagesConfig     = $this->factory->getThis('forseo.config', 'pages');
		$this->sitemapsConfig  = $this->factory->getThis('forseo.config', 'sitemaps');
		$this->robotsTxtHelper = $this->factory->getThe('forseo.robotsTxtHelper');
		$this->imageHelper     = $this->factory->getA(
			Html\Image::class,
			[
				'cacheLocalImages' => true,
				'cacheRemoteImages' => true
			]
		);
		$this->urlHelper       = $this->factory->getA(Url::class);
	}

	/**
	 * Store collected links from the page body into the #__forseo_collected_urls table
	 * for further data collection.
	 *
	 * @param array          $images
	 * @param null|Data\Page $currentPage
	 * @param array          $options
	 *                               forceCollection => store as collectedURl no matter what.
	 *                               isUnknownRequest => straight incoming request, not a link, not from crawler
	 * @throws \Exception
	 */
	public function storeCollected($images, $currentPage = null, $options = [])
	{
		$counter      = 0;
		$storedImages = [];

		// wipe out existing records for that page. We want to maintain a list of images
		// in the page at the moment, which we have in $images but there might be images that
		// have been removed from the page and we need to remove these outdated images from the db.
		if (!empty($currentPage))
		{
			$this->factory->getThe('db')
						  ->delete(
							  '#__forseo_images',
							  [
								  'page_url' => $currentPage->get('url')
							  ]
						  );
		}

		if (empty($images))
		{
			return;
		}

		foreach ($images as $imageRecord)
		{
			// apply user-set and other filtering rules
			if (!$this->shouldCollectByUrl(
				$imageRecord,
				$currentPage
			))
			{
				continue;
			}

			if (!$this->shouldCollectBySize($imageRecord))
			{
				continue;
			}

			/**
			 * Removes common unwanted vars from an image, before it's used.
			 *
			 * @api     forseo
			 * @package 4SEO\filter\frontend\collection
			 * @var forseo_clean_query_vars_to_strip
			 * @since   1.0.0
			 *
			 * @param array $imageRecord
			 *
			 * @return array
			 *
			 */
			$imageRecord['url'] = $this->factory
				->getThe('hook')
				->filter(
					'forseo_clean_query_vars_to_strip',
					$imageRecord['url']
				);

			// prepare a new image object
			$image = $this->factory
				->getA(Data\Image::class)
				->loadPerColumn(
					[
						'page_url' => $currentPage->get('url'),
						'url'      => $imageRecord['url']
					]
				);

			if ($image->exists())
			{
				// Image has already been collected, is pending crawling, do nothing
				continue;
			}

			try
			{
				$image->set(
					[
						'full_url'      => Wb\arrayGet($imageRecord, 'url'),
						'page_full_url' => $currentPage->get('full_url'),
						'sitemap_auto'  => $this->shouldCollectByRobotsTxt($imageRecord['url'])
							? Data\Image::INCLUDED
							: Data\Image::EXCLUDED,
						'data'          => $imageRecord
					])->timestamp('crawled_at')
					  ->timestamp('modified_at')
					  ->store();
				$counter++;
				$storedImages[] = $image->get('full_url');
			}
			catch (\Throwable $e)
			{
				// due to concurrency, this very imageUrl may have been written to the #__forseo_images
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
		}

		$currentPageUrl = empty($currentPage)
			? 'unknown'
			: $currentPage->get('full_url');

		$this->logger->debug('imagesCollector helper. Found ' . count($images) . ' images on page ' . $currentPageUrl . ', ' . $counter . ' stored to #__forseo_images table');
		$this->logger->debug('imagesCollector helper. Images stored: ' . print_r($storedImages, true));
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
				. System\Route::makeRootRelative($link);

		if ($this->robotsTxtHelper->isExcluded($link))
		{
			$this->logger->debug('linksCollector helper shouldCollectByRobotsTxt. Rejecting internal link ' . $link . ', excluded by robots.txt');
			return false;
		}

		return true;
	}

	/**
	 * Whether a given collected image should be collected based on user set
	 * URL exclusion rules.
	 *
	 * As currently images are only collected to be included in sitemaps, we apply the
	 * sitemaps config image URL exclusion configuration. If/when we collect images to detect
	 * broken images, we'll have to have a separate set of URL exclusions.
	 *
	 * @param array & $imageRecord
	 *
	 * @return bool
	 */
	private function shouldCollectByUrl($imageRecord, $currentPage)
	{
		if (
			!$this->sitemapsConfig->get('collectImagesOnCategories', false)
			&&
			in_array(
				$currentPage->get('view', ''),
				[
					'category',
					'categories'
				],
				true
			)
		) {
			return false;
		}

		$excludedDomains = $this->sitemapsConfig->get('imagesDomainsExclusions', []);
		if (!empty($excludedDomains))
		{
			if (!System\Route::isInternal($imageRecord['url']))
			{
				foreach ($excludedDomains as $host)
				{
					if (System\Route::hostMatch(
						$host,
						$imageRecord['url'])
					) {
						$this->logger->debug('linksCollector helper shouldCollectByRules. Rejecting external link ' . $imageRecord['url'] . ', it matches excluded domain ' . $host);

						return false;
					}
				}
			}
		}

		$imagesExclusions = array_merge(
			$this->pagesConfig->get('crawlerImagesExclusions', []),
			$this->sitemapsConfig->get('imagesExclusions', [])
		);

		if (
			!empty($imagesExclusions)
			&&
			!$this->urlHelper->passExclusionRules(
				$imageRecord['url'],
				$imagesExclusions,
				$this->sitemapsConfig->get('imagesInclusions')
			)
		) {
			return false;
		}

		return true;
	}

	/**
	 * Whether a given collected image should be collected based on its size and
	 * user configuration.
	 *
	 * As currently images are only collected to be included in sitemaps, we apply the
	 * sitemaps config image size configuration. If/when we collect images to detect
	 * broken images, we'll have to have a separate set of size spec.
	 *
	 * NB: svg are not handled ATM. SVG will not, as they most often don't have intrinsic dimensions.
	 *
	 * @param array & $imageRecord
	 *
	 * @return bool
	 */
	private function shouldCollectBySize(&$imageRecord)
	{
		if (
			$this->sitemapsConfig->isFalsy('imageInclusionMinWidth')
			&&
			$this->sitemapsConfig->isFalsy('imageInclusionMinHeight')
		) {
			return true;
		}

		if (Wb\endsWith(
			System\Route::trimQuery(
				$imageRecord['url']
			),
			'.svg'
		))
		{
			return true;
		}

		// at least one size specification, we must find the image size and validate it.
		$size        = $this->imageHelper->getImageSize($imageRecord['url']);
		$imageRecord = array_merge(
			$imageRecord,
			$size
		);

		return $this->imageHelper->isLargeEnough(
			$size,
			[
				'width'  => $this->sitemapsConfig->get('imageInclusionMinWidth'),
				'height' => $this->sitemapsConfig->get('imageInclusionMinHeight'),
			]
		);
	}
}