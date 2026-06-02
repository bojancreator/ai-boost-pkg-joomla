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

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Html;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Support for Ignite Gallery.
 *
 */
class Phocagallery extends Base
{
	/**
	 * @var string Component name - with leading com_ removed, eg content for com_content
	 */
	protected $component = 'phocagallery';

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
	 * @var Db\Helper Convenience Helper instance.
	 */
	protected $dbHelper;

	/**
	 * Register event handler
	 *
	 * @param array $options Can inject custom factory and platform.
	 */
	public function __construct($options = [])
	{
		parent::__construct($options);

		$this->dbHelper = $this->factory->getThe('db');
	}

	/**
	 * Add handlers for desired extension hooks.
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
		return $this->defaultPageBuildContentId($id, $pageData);
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

		$inputVars = $pageData->get('input_vars', []);
		$start     = Wb\arrayGet($inputVars, 'start', null);
		if (0 === $start || "0" === $start)
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

		// Whether we can gather extra information (from database for instance). Normally disabled on
		// regular pages rendering, while enabled when crawling and we have more time.
		$thorough = Wb\arrayGet($options, 'thorough', false);
		if (empty($thorough))
		{
			// Phoca requires database access to extract image information
			// we can't do that on regular page loads, only when crawling.
			// Change detection will work just fine when done over just the thumbnails,
			// as is the default.
			return $extractedImages;
		}

		$imageRecords = [];

		// METHOD: read from DB
		$input      = $this->platform->getHttpInput();
		$categoryId = $input->getInt('id');
		$tagId      = $input->getInt('tagid');
		if (empty($categoryId) && empty($tagId))
		{
			return $extractedImages;
		}

		if (!class_exists('\PhocagalleryModelCategory'))
		{
			return $extractedImages;
		}

		$pgModel = new \PhocagalleryModelCategory();
		$images  = $pgModel->getData(0, $tagId);

		foreach ($images as $image)
		{
			$processedImg       = $this->buildRecord($image);
			$url                = System\Route::absolutify(
				$processedImg->linkthumbnailpath,
				$forceDomain = false, $currentUrl = null, $skipRewritingprefix = true
			);
			$imageRecords[$url] = [
				'url'       => $url,
				'title'     => empty($processedImg->title) ? '' : $processedImg->title,
				'alt'       => empty($processedImg->metadesc) ? '' : $processedImg->metadesc,
				'geo'       => empty($processedImg->geotitle) ? '' : $processedImg->geotitle,
				'width'     => empty($processedImg->realimagewidth) ? '' : $processedImg->realimagewidth,
				'height'    => empty($processedImg->realimageheight) ? '' : $processedImg->realimageheight,
				'el_width'  => 0,
				'el_height' => 0,
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

	/**
	 * Extracted from /components/com_phocagallery/views/detail/view.html.php
	 *
	 * Copyright (C) Jan Pavelka www.phoca.cz
	 * license: http://www.gnu.org/copyleft/gpl.html GNU General Public License version 2 or later;
	 *
	 * @param \stdClass $item
	 * @return mixed
	 */
	private function buildRecord($item)
	{
		// ALT VALUE
		$altValue       = \PhocaGalleryRenderFront::getAltValue($this->t['altvalue'], $item->title, $item->description, $item->metadesc);
		$item->altvalue = $altValue;

		// Get file thumbnail or No Image
		$item->filenameno = $item->filename;
		$item->filename   = \PhocaGalleryFile::getTitleFromFile($item->filename, 1);
		$item->filesize   = \PhocaGalleryFile::getFileSize($item->filenameno);
		$realImageSize    = '';
		$extImage         = \PhocaGalleryImage::isExtImage($item->extid);
		if ($extImage)
		{
			// $item->extl      = $item->extl;
			// $item->exto      = $item->exto;
			$item->imagesize = \PhocaGalleryImage::getImageSize($item->exto, 1, 1);
			if ($item->extw != '')
			{
				$extw       = explode(',', $item->extw);
				$item->extw = $extw[0];
			}
			if ($item->exth != '')
			{
				$exth       = explode(',', $item->exth);
				$item->exth = $exth[0];
			}
			$correctImageRes = \PhocaGalleryPicasa::correctSizeWithRate($item->extw, $item->exth, $this->t['picasa_correct_width_l'], $this->t['picasa_correct_height_l']);
			// Not used
			// $item->linkimage       = \Joomla\CMS\HTML\HTMLHelper::_('image', $item->extl, $item->altvalue, array('width' => $correctImageRes['width'], 'height' => $correctImageRes['height'], 'class' => 'pg-detail-image img img-responsive'));
			$item->realimagewidth  = $correctImageRes['width'];
			$item->realimageheight = $correctImageRes['height'];


		}
		else
		{
			$item->linkthumbnailpath = \PhocaGalleryImageFront::displayCategoryImageOrNoImage($item->filenameno, 'large');
			// Not used
			// $item->linkimage         = \Joomla\CMS\HTML\HTMLHelper::_('image', $item->linkthumbnailpath, $item->altvalue, array('class' => 'pg-detail-image img img-responsive'));
			$realImageSize   = \PhocaGalleryImage::getRealImageSize($item->filenameno);
			$item->imagesize = \PhocaGalleryImage::getImageSize($item->filenameno, 1);
			if (isset($realImageSize['w']) && isset($realImageSize['h']))
			{
				$item->realimagewidth  = $realImageSize['w'];
				$item->realimageheight = $realImageSize['h'];
			}
			else
			{
				$item->realimagewidth  = $this->t['largewidth'];
				$item->realimageheight = $this->t['largeheight'];
			}
		}

		return $item;
	}
}
