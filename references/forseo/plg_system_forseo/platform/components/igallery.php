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

namespace Weeblr\Forseo\Platform\Components;

use Weeblr\Forseo\Data;
use Weeblr\Forseo\Helper;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Db;
use Weeblr\Wblib\Forseo\Html;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Support for Ignite Gallery.
 *
 */
class Igallery extends Base
{
	/**
	 * @var string Component name - with leading com_ removed, eg content for com_content
	 */
	protected $component = 'igallery';

	/**
	 * @var \stdClass Cache for items being rendered.
	 */
	protected $contentData = null;

	/**
	 * @var string[] Views we can store.
	 */
	protected $includedViews = [
		'category'
	];

	/**
	 * @var null|array List of layouts names that should be stored. None if null. All if empty.
	 */
	protected $includedLayouts = [];

	/**
	 * @var null|array List of layouts names that should NOT be stored. No effect if null or empty.
	 */
	protected $excludedLayouts = [];

	/**
	 * @var array List of schema types supported by this plugin.
	 */
	protected $supportedSdTypes = [];

	/**
	 * @var bool Whether the plugin wants to filter raw, SEF URL. Time consuming, avoid if not needed.
	 */
	protected $filterShouldCollectUrlsFoundOnPage = false;

	/**
	 * Add handlers for desired the extension hooks.
	 */
	public function addHooks()
	{
		parent::addHooks();

		if ($this->platform->isFrontend())
		{
			$this->hook->add(
				'forseo_extract_images',
				[
					$this,
					'filterExtractImages'
				]
			);

			$this->hook->add(
				'forseo_page_should_include_in_sitemap',
				[
					$this,
					'filterShouldIncludeInSitemap'
				]
			);
		}
	}

	/**
	 * Filters page data collected at the onAfterRoute event.
	 *
	 * @param Data\Page $pageData
	 *
	 * @return Data\Page
	 * @throws \Exception
	 */
	protected function filterAfterRoutePageData($pageData)
	{
		$pageData = parent::filterAfterRoutePageData($pageData);

		$inputVars = $pageData->get('input_vars', []);
		$pageData->set(
			'item_id',
			Wb\arrayGet($inputVars, 'igid', '')
		);

		return $pageData;
	}

	/**
	 * Implement construction of extension item unique id.
	 *
	 * @param null|array     $id
	 * @param null|Data\Page $pageData
	 *
	 * @return       array
	 * @throws \Exception
	 */
	protected function filterPageBuildContentId($id, $pageData)
	{
		$id = $this->defaultPageBuildContentId($id, $pageData);

		// iGallery does not have settings at menu item level
		// so we can safely remove Itemid and spare us some
		// duplicate content
		unset($id['Itemid']);

		return $id;
	}

	/**
	 * Filters whether to include a URL into the sitemap.
	 *
	 * @param int       $shouldInclude Data\Page::INCLUDED | Data\Page::EXCLUDED
	 * @param Data\Page $pageData      The page object to find the modified_at date for.
	 * @param int       $sitemapType   @see Data\Sitemap
	 *
	 * @return int
	 * @throws \Exception
	 */
	public function filterShouldIncludeInSitemap($shouldInclude, $pageData, $sitemapType)
	{
		if (!$this->shouldRunFilter($pageData))
		{
			return $shouldInclude;
		}

		if (!Wb\arrayIsFalsy($pageData->get('input_vars', []), 'i'))
		{
			// exclude start=0 page, same as without
			$shouldInclude = Data\Page::EXCLUDED;
		}

		return $shouldInclude;
	}

	/**
	 * Extract best images from gallery found in a page.
	 *
	 * $images[$href] = [
	 * 'url'       => $href,
	 * 'title'     => $imgTag->getAttribute('title'),
	 * 'alt'       => $imgTag->getAttribute('alt'),
	 * 'el_width'  => $imgTag->getAttribute('width'),
	 * 'el_height' => $imgTag->getAttribute('height'),
	 * 'data'      => $dataAttributes
	 * ];
	 *
	 * @param null|array   $extractedImages
	 * @param string       $buffer
	 * @param \DOMDocument $dom
	 * @param \DocNodeList $imgTags
	 * @param array        $options
	 * @param Data\Page    $pageData
	 * @return null|array
	 * @throws \Exception
	 */
	public function filterExtractImages($extractedImages, $buffer, $dom, $imgTags, $options, $pageData)
	{
		if (!$this->shouldRunFilter($pageData))
		{
			return $extractedImages;
		}

		// do not work on non-crawled pages
		if ($this->excludeByViewAndLayout($pageData))
		{
			return $extractedImages;
		}

		// do not work on non-crawled pages
		if ($this->excludeByInputVar($pageData))
		{
			return $extractedImages;
		}

		if (!Wb\contains($buffer, 'data-ig-imageid'))
		{
			return $extractedImages;
		}

		// Whether we can gather extra information (from database for instance). Normally disabled on
		// regular pages rendering, while enabled when crawling and we have more time.
		//$thorough           = Wb\arrayGet($options, 'thorough', false);

		// METHOD 3: iterate over tags
		$imageRecords = [];
		/**
		 * @var \DOMElement $imgTag
		 */
		foreach ($imgTags as $imgTag)
		{
			if (!$imgTag->hasAttribute('data-ig-lazy-src'))
			{
				continue;
			}
			$url                = $imgTag->getAttribute('data-ig-lazy-src');
			$imageRecords[$url] = [
				'url'       => $url,
				'title'     => $imgTag->getAttribute('title'),
				'alt'       => $imgTag->getAttribute('alt'),
				'el_width'  => $imgTag->getAttribute('width'),
				'el_height' => $imgTag->getAttribute('height'),
				'data'      => []
			];
		}

		if (!empty($imageRecords))
		{
			$extractedImages = $imageRecords;
			$this->factory->getThe('forseo.logger')->debug(__METHOD__ . ', extracted ' . count($imageRecords) . ' from ' . $pageData->get('full_url'));
		}

		return $extractedImages;
	}
}
