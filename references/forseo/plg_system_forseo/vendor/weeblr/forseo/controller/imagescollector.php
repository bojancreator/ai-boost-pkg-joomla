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

use Weeblr\Forseo\Model\Config;
use Weeblr\Forseo\Data;
use Weeblr\Forseo\Helper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * This collector is in charge of collecting images with various methods and storing
 * them to the #__forseo_images table so that later they can be included in a sitemap.
 *
 * @package Weeblr\Forseo\Controller
 */
class Imagescollector extends Base\Base
{
	/**
	 * @var array Storage for extracted images.
	 */
	private $images = [];

	/**
	 * @var Object Storage for current page.
	 */
	private $currentPage = null;

	/**
	 * @var Helper\Linkscollector A helper for page collection-related features.
	 */
	private $imagesCollectorHelper = null;

	/**
	 * @var Config Holds the pages collection config object.
	 */
	private $pagesConfig = null;

	/**
	 * @var string[] List of data-xxxx attributes to read images src from.
	 */
	private $dataAttrToReadFrom = ['data-src'];

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

		$this->currentPage           = $currentPage;
		$this->logger                = $this->factory->getThe('forseo.logger');
		$this->pagesConfig           = $this->factory->getThis('forseo.config', 'pages');
		$this->imagesCollectorHelper = $this->factory->getA(Helper\Imagescollector::class);


		/**
		 * Filter configuration right after creating it.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\frontend\collection
		 * @var forseo_image_extraction_src_data_attributes
		 *
		 * @param array $dataAttrToReadFrom
		 *
		 * @return array
		 *
		 * @since   5.2.0
		 */
		$this->dataAttrToReadFrom = $this->factory
			->getThe('hook')
			->filter(
				'forseo_image_extraction_src_data_attributes',
				$this->dataAttrToReadFrom
			);
	}

	/**
	 * Store any link collected, checking whether they've been seen before and
	 * other conditions.
	 *
	 * @throws \Exception
	 */
	public function store()
	{
		if (!empty($this->images))
		{
			$this->imagesCollectorHelper->storeCollected(
				$this->images,
				$this->currentPage
			);
			$this->logger->debug(__METHOD__ . ', stored images for page ' . $this->currentPage->get('full_url') . ': ' . print_r($this->images, true));
		}
	}

	/**
	 * Collects images found in current page.
	 *
	 * Returns a hash of the images URLs found, if any or an empty string if no images found.
	 *
	 * @param string $body
	 * @param bool   $wrapContentInHtmlDoc
	 *
	 * @return string
	 */
	public function extractImagesFromBody($body, $wrapContentInHtmlDoc = false)
	{
		$currentPageImagesHash = '';

		try
		{
			// run conditions: frontend, html document,...
			if (!$this->canRun())
			{
				return '';
			}

			$currentUrl = $this->currentPage->get('full_url');

			// gather a list of images from the body
			$this->images = Html\Extract::extractImages(
				$body,
				[
					'wrapContentInHtmlDoc' => $wrapContentInHtmlDoc,
					'currentUrl'           => System\Route::makeRootRelative(
						$currentUrl
					),
					'onlyInternal'         => false,
					'stripAnchors'         => true,
					'skipRelative'         => false,
					'removeLeadingSlash'   => false,
					'queryVarsToStrip'     => [
						FORSEO_CRAWLER_CDN_BUST_VAR
					],
					/**
					 * Filter to extract images from the current page.
					 * Will be passed the current page DOMContent and an empty array of images.
					 *
					 * Should return an array of images records to be used as is
					 * (hence possibly empty) or null to indicate the handler does not handle the request.
					 *
					 * An image is defined by an array:
					 *
					 * $image = [
					 * 'url'       => URL of the image, relative to root || FQDN if another site,
					 * 'title'     => title attr,
					 * 'alt'       => alt attr,
					 * 'el_width'  => width attr of the HTML element, may be different from image intrinsic width,
					 * 'el_height' => height attr of the HTML element, may be different from image intrinsic height,
					 * 'data'      => key/value array of all data-xxxx attributes.
					 * ]
					 *
					 * @api     forseo
					 * @package 4SEO\filter\frontend\collection
					 * @var forseo_extract_images
					 * @since   1.4.0
					 *
					 * @param array        $extractedImages
					 * @param string       $buffer
					 * @param \DOMDocument $dom
					 * @param \DOMNodeList $imgTags
					 * @param array        $options
					 * @param Data\Page    $pageData
					 *
					 * @return null|array
					 *
					 */
					'filter'               => 'forseo_extract_images',
					'filterParams'         => $this->currentPage,
					'excludeUrls'          => [
						'/forseo/v1/cron'
					],
					'thorough'             => $this->factory->getThe('forseo.crawlerHelper')->isCrawlerRequest(),
					'rawUrlDecode'         => true,
					'dataAttrToReadFrom'   => $this->dataAttrToReadFrom
				]
			);

			$this->logger->debug('Collected images on ' . $currentUrl . ': ' . print_r($this->images, true));

			/**
			 * Filter the list of images collected on the current page.
			 *
			 * @api     forseo
			 * @package 4SEO\filter\frontend\collection
			 * @var forseo_collected_images
			 * @since   1.4.0
			 *
			 * @param bool      $collectedImages
			 * @param Data\Page $pageData
			 *
			 * @return bool
			 *
			 */
			$this->images = $this->factory->getThe('hook')->filter(
				'forseo_collected_images',
				$this->images,
				$this->currentPage
			);

			$this->logger->debug('Collected images on (after filtering) ' . $currentUrl . ': ' . print_r($this->images, true));

			$currentPageImagesHash = System\Auth::hashContent($this->images);
		}
		catch (\Throwable $e)
		{
			$this->logger->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}

		// make sure to return body in all cases
		return $currentPageImagesHash;
	}

	/**
	 * Check whether current request is on frontend and for an HTML page.
	 *
	 * @return bool
	 */
	private function canRun()
	{
		$shouldCollectImages = $this->pagesConfig->isTruthy('collectionEnabled')
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
		 * Filter whether images collection should happen on the current page.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\frontend\collection
		 * @var forseo_should_collect_images
		 * @since   1.4.0
		 *
		 * @param bool      $shouldCollectImages
		 * @param Data\Page $pageData
		 *
		 * @return bool
		 *
		 */
		return $this->factory->getThe('hook')->filter(
			'forseo_should_collect_images',
			$shouldCollectImages,
			$this->currentPage
		);
	}
}
