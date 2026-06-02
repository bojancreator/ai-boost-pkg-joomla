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
use Weeblr\Wblib\Forseo\Html;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;
use Weeblr\Wblib\Forseo\Joomla\Registry;

use Weeblr\Forseo\Model\Config;
use Weeblr\Forseo\Data;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Meta extends Base\Base
{
	/**
	 * @var int Minimum number of characters in content to abdrige description.
	 */
	private $metaAutoDescRecommendedLength;

	/**
	 * @var array
	 */
	private $descCleanupExpressions;

	/**
	 * @var Requestinfo Convenience instance of the current request details.
	 */
	private $requestInfo = null;

	/**
	 * @var Config Convenience instance of pages config.
	 */
	private $pagesConfig = null;

	/**
	 * @var Html\Image Image manipulation helper.
	 */
	private $imageHelper = null;

	/**
	 * Initialize convenience helpers instances and filter config values.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->requestInfo = $this->factory->getThe('forseo.requestInfo');
		$this->pagesConfig = $this->factory->getThis('forseo.config', 'pages');
		$this->imageHelper = $this->factory->getA(
			Html\Image::class,
			[
				'cacheLocalImages'  => true,
				'cacheRemoteImages' => true
			]
		);
		/**
		 * Filter the recommended number of characters in automatically computed description.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\meta
		 * @var forseo_auto_description_recommended_length
		 * @since   1.0.0
		 *
		 * @param int $metaAutoDescRecommendedLength
		 *
		 * @return int
		 *
		 */
		$this->metaAutoDescRecommendedLength = $this->factory
			->getThe('hook')
			->filter(
				'forseo_auto_description_recommended_length',
				$this->pagesConfig->get(
					'metaAutoDescRecommendedLength',
					Data\Meta::META_DESC_RECOMMENDED_LENGTH
				)
			);

		/**
		 * Filter regular expressions to apply to content to remove unwanted codes and markers.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\meta
		 * @var forseo_auto_description_cleanup_expressions
		 * @since   1.0.0
		 *
		 * @param array $descCleanupExpressions
		 *
		 * @return array
		 *
		 */
		$this->descCleanupExpressions = $this->factory
			->getThe('hook')
			->filter(
				'forseo_auto_description_cleanup_expressions',
				$this->factory->getThis('forseo.config', 'app')->get('descCleanupExpressions')
			);
	}

	/**
	 * Convert a meta data source type name to a storage code.
	 *
	 * @param string $name
	 *
	 * @return int
	 */
	public function typeCodeFromTypeName($name)
	{
		switch ($name)
		{
			case 'auto':
				return Data\Meta::AUTO;
			case 'platform':
				return Data\Meta::PLATFORM;
			case 'custom':
				return Data\Meta::CUSTOM;
			default:
				return Data\Meta::NONE;
		}
	}

	/**
	 * Extract a viable description from a piece of html source.
	 *
	 * @param null|string $content
	 * @param array       $cleanupExpressions
	 * @param array       $options
	 *
	 * @return string|string[]
	 */
	public function buildDescriptionFromContent($content, $cleanupExpressions = [], $options = ['abridge' => true])
	{
		$desc = StringHelper::trim($content);
		if (empty($desc))
		{
			return $desc;
		}

		$cleanupExpressions = array_filter(
			array_merge(
				$this->descCleanupExpressions,
				$cleanupExpressions
			)
		);

		foreach ($cleanupExpressions as $expression)
		{
			if (empty($desc))
			{
				return $desc;
			}

			$desc = preg_replace(
				$expression,
				'',
				$desc
			);
		}

		$desc = strip_tags($desc);
		$desc = preg_replace("#[\s\n\r\t]+#us", ' ', $desc);
		$desc = str_replace(
			['&nbsp;', '"'],
			[' ', '\''],
			$desc
		);
		$desc = html_entity_decode(
			$desc,
			ENT_COMPAT,
			'UTF-8'
		);

		$desc = StringHelper::trim($desc);
		$desc = StringHelper::trim($desc, '"');
		$desc = str_replace(['&#39;', '&#039;', '"'], '\'', $desc);
		$desc = str_replace("\r\n", ' ', $desc);
		$desc = str_replace("\n\r", ' ', $desc);
		$desc = str_replace("\r", ' ', $desc);
		$desc = str_replace("\n", ' ', $desc);

		return Wb\arrayIsTruthy($options, 'abridge')
			? Wb\abridge(
				$desc,
				$this->metaAutoDescRecommendedLength,
				$this->metaAutoDescRecommendedLength
			)
			: $desc;
	}

	/**
	 * Search for an OGP image in some content.
	 *
	 * @param string $content       Content to search.
	 * @param array  $imageSpec     Array or required image dimensions
	 *                              width Minimal width in pixels
	 *                              height Minimal height in pixels
	 *                              pixels Minimal number of pixels in image (W x H)
	 * @param int    $selectionMode 0 = none, 1 = first in content, 2 = largest (by pixel count)
	 *
	 * @return array|void
	 */
	public function searchPageImage($content, $imageSpec, $selectionMode = Html\Image::IMAGE_SEARCH_LARGEST)
	{
		$content = StringHelper::trim($content);
		if (empty($content))
		{
			return [];
		}

		$image = $this->imageHelper->getBestImage(
			$content,
			$selectionMode,
			$imageSpec
		);

		if (empty($image))
		{
			return $image;
		}

		$image['url'] = System\Route::absolutify(
			$image['url'],
			true, // force domain
			$this->factory->getThis('forseo.config', 'pages')->get('canonicalRootUrl'),
			true  // isAsset
		);

		return $image;
	}

	/**
	 * Create a fake piece of content to run the image size validator on a specified
	 * image agains a size specification. If successful, returns the absolute version
	 * of the URL to that image.
	 *
	 * @param null|string $image
	 * @param array       $sizeSpec
	 *
	 * @return array|void
	 */
	public function validateImageFromContent($image, array $sizeSpec)
	{
		if (empty($image))
		{
			return $image;
		}

		$fakeContent = '<img src="' . $image . '">';
		$bestImage   = $this->imageHelper->getBestImage(
			$fakeContent,
			$firstImage = 1,
			$sizeSpec
		);

		if (empty($bestImage))
		{
			return $bestImage;
		}

		$bestImage['url'] = System\Route::absolutify(
			$bestImage['url'],
			true,
				 // Tricky here: images are stored relative by Joomla
				 // So we need to fake being on the home page for the
				 // URL to be properly absolutified
			$this->factory->getThis('forseo.config', 'pages')->get('canonicalRootUrl'),
			true // isAsset, no URL rewriting prefix
		);

		return $bestImage;
	}

	/**
	 * Inject a canonical tag in the page, adding a class attribute to identify the source.
	 *
	 * 2022-12-14: we cannot use Joomla addHeadLink as it only handles one rel type value per href.
	 * Setting a canonical using addHeadLink would delete other rel tags, most notably the hreflang one for the same href.
	 *
	 * @param string $targetUrl The already fully qualified
	 * @param string $sourceTag Default to "4SEO"
	 */
	public function injectCanonical(string $targetUrl, string $sourceTag = '4SEO')
	{
		if (!System\Route::isFullyQualified($targetUrl))
		{
			$targetUrl = System\Route::absolutify(
				$targetUrl,
				true
			);
		}

		$query = System\Route::getQuery($targetUrl);
		$path  = System\Route::trimQuery($targetUrl);

		$href = htmlspecialchars($path, ENT_COMPAT, 'UTF-8');
		if (!empty($query))
		{
			$href .= '?' . $query;
		}

		$this->platform->removeHeadLink(
			$href,
			'canonical',
			'rel'
		);

		$link = $this->factory
			->getA(Html\Helper::class)
			->makeTag(
				'link',
				[
					'rel'   => 'canonical',
					'href'  => $href,
					'class' => $sourceTag
				],
				''
			);

		$this->platform->addCustomTag(
			$link
		);

	}

	public function injectRobots(string $robotsValue, string $sourceTag = '4SEO')
	{
		$tag = $this->factory
			->getA(Html\Helper::class)
			->makeTag(
				'meta',
				[
					'name'    => 'robots',
					'content' => $robotsValue,
					'class'   => $sourceTag,
				],
				''
			);

		$this->platform->addCustomTag(
			$tag
		);
	}

	/**
	 * Compare current request collected data with an existing set
	 * to detect a change.
	 *
	 * @param Data\Requestinfo $requestInfo
	 * @param array            $existingMeta
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function platformMetaHasChanged($requestInfo, $existingMeta)
	{
		$metaHasChanged = $requestInfo->get('page_title') !== Wb\arrayGet($existingMeta, ['platform', 'title'])
						  ||
						  $requestInfo->get('page_description') !== Wb\arrayGet($existingMeta, ['platform', 'description'])
						  ||
						  $requestInfo->get('page_canonical') !== Wb\arrayGet($existingMeta, ['platform', 'canonical'])
						  ||
						  $requestInfo->get('page_robots') !== Wb\arrayGet($existingMeta, ['platform', 'robots'])
						  ||
						  $requestInfo->get('page_auto_description') !== Wb\arrayGet($existingMeta, ['auto', 'description'])
						  ||
						  $requestInfo->get('page_image') !== Wb\arrayGet($existingMeta, ['auto', 'image'])
						  ||
						  $requestInfo->get('page_sharing_image') !== Wb\arrayGet($existingMeta, ['auto', 'sharing_image']);

		$autoCanonical = Wb\arrayGet($existingMeta, ['auto', 'canonical']);
		if (!empty($autoCanonical))
		{
			$metaHasChanged =
				$metaHasChanged
				||
				$requestInfo->get('page_auto_canonical') !== $autoCanonical;
		}

		return $metaHasChanged;
	}

	/**
	 * Whether a raw robots meta tag contains a noindex instruction.
	 *
	 * @param string $robotsMeta
	 * @return bool
	 */
	public function hasMetaNoindex($robotsMeta)
	{
		return $this->hasRobotsMeta($robotsMeta, 'noindex');
	}

	/**
	 * Whether a raw robots meta tag contains a noindex instruction.
	 *
	 * @param string $robotsMeta
	 * @return bool
	 */
	public function hasMetaNofollow($robotsMeta)
	{
		return $this->hasRobotsMeta($robotsMeta, 'nofollow');
	}

	/**
	 * Whether a raw robots meta tag contains a specific instruction.
	 *
	 * @param string $robotsMeta
	 * @return bool
	 */
	public function hasRobotsMeta($robotsMeta, $metaValue)
	{
		$robots = System\Strings::stringToCleanedArray(
			$robotsMeta,
			',',
			System\Strings::LOWERCASE
		);

		return in_array(
			$metaValue,
			$robots
		);
	}

	/**
	 * Hash an array representing the current page meta data.
	 *
	 * @param array $meta
	 *
	 * @return string
	 */
	public function hashMeta($meta)
	{
		return md5(json_encode($meta));
	}
}
