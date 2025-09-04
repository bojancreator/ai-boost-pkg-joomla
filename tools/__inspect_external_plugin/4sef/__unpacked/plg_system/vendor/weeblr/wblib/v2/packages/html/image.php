<?php
/**
 * 4SEF
 *
 * @author           Yannick Gaultier - Weeblr llc
 * @copyright        Copyright Weeblr llc - 2022 -2025
 * @package          4SEF
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          2.6.2.644
 * @date        2025-06-02
 */

namespace Weeblr\Wblib\Forsef\Html;

use Joomla\CMS\Cache\Cache;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Joomla\StringHelper\StringHelper;
use Weeblr\Wblib\Forsef\Html\Remoteimage as Remoteimage;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

class Image extends Base\Base
{
	const IMAGE_SEARCH_NONE = 0;
	const IMAGE_SEARCH_FIRST = 1;
	const IMAGE_SEARCH_LARGEST = 2;

	/**
	 * @var bool
	 */
	private $cacheLocalImages = false;

	/**
	 * @var Cache
	 */
	private $localImageCache;

	/**
	 * @var bool True by default for B/C
	 */
	private $cacheRemoteImages = true;

	/**
	 * @var Cache
	 */
	private $remoteImageCache;

	/**
	 * @var Cache
	 */
	private $imageLockCache;

	/**
	 *
	 * @param   array  $options
	 */
	public function __construct($options = [])
	{
		parent::__construct($options);

		$this->cacheLocalImages = Wb\arrayGet($options, 'cacheLocalImages', $this->cacheLocalImages);
		$this->localImageCache  = $this->platform->getCache(
			'output',
			[
				'caching'      => 1,
				'lifetime'     => 10080,
				'defaultgroup' => 'wblib_local_img_size'
			]
		);

		$this->cacheRemoteImages = Wb\arrayGet($options, 'cacheRemoteImages', $this->cacheRemoteImages);
		$this->remoteImageCache  = $this->platform->getCache(
			'output',
			[
				'caching'      => 1,
				'lifetime'     => 10080,
				'defaultgroup' => 'wblib_remote_img_size'
			]
		);

		$this->imageLockCache = $this->platform->getCache(
			'output',
			[
				'caching'      => 1,
				'lifetime'     => 1,
				'defaultgroup' => 'wblib_remote_img_lock'
			]
		);
	}

	/**
	 * Get an image size from the file
	 *
	 * @param   string  $url
	 *
	 * @return array Width/height of the image, 0/0 if not found
	 */
	public function getImageSize($url)
	{
		static $rootPath = '';
		static $pathLength = 0;
		static $rootUrl = '';
		static $rootLength = 0;
		static $protocoleRelRootUrl = '';
		static $protocoleRelRootLength = 0;
		static $scheme = '';

		if (empty($rootPath))
		{
			$rootUrl                = $this->platform->getRootUrl(false);
			$rootLength             = StringHelper::strlen($rootUrl);
			$protocoleRelRootUrl    = str_replace(array('https://', 'http://'), '//', $rootUrl);
			$protocoleRelRootLength = StringHelper::strlen($protocoleRelRootUrl);
			$rootPath               = $this->platform->getBaseUrl(true);
			$pathLength             = StringHelper::strlen($rootPath);
			if ($this->platform->isBackend())
			{
				$rootPath = str_replace('/administrator', '', $rootPath);
			}
			$scheme = $this->platform->getScheme();
		}

		// default values ?
		$dimensions = array('width' => 0, 'height' => 0);

		if (
			Wb\contains(
				$url,
				[
					'_wblapi=',
					'wbl_api=',
					'data:image/'
				]
			)
		)
		{
			// softcron, bail out
			return $dimensions;
		}

		// build the physical path from the URL
		if (Wb\startsWith($url, ['/index.php/component/ajax/', '/component/ajax/']))
		{
			// YT using ajax component to load images, consider it remote
			$remoteDimensions = $this->getCachedRemoteImageDimensions($url);

			return empty($remoteDimensions) ? $dimensions : $remoteDimensions;
		}

		if (StringHelper::substr($url, 0, 2) == '//' && StringHelper::substr($url, 0, $protocoleRelRootLength) == $protocoleRelRootUrl)
		{
			// protocol relative URL
			$url = StringHelper::substr($url, $protocoleRelRootLength);
		}
		else if (StringHelper::substr($url, 0, 2) == '//')
		{
			$url = $scheme . ':' . $url;
		}

		if (StringHelper::substr($url, 0, $rootLength) == $rootUrl)
		{
			$cleanedPath = $this->trimQuery(StringHelper::substr($url, $rootLength));
		}
		else if (!empty($rootPath) && StringHelper::substr($url, 0, $pathLength) == $rootPath)
		{
			$cleanedPath = $this->trimQuery(StringHelper::substr($url, $pathLength));
		}
		else if (System\Route::isFullyQualified($url))
		{
			// a URL, but not on this site, try to download it and read its size
			$remoteDimensions = $this->getCachedRemoteImageDimensions($url);

			return empty($remoteDimensions) ? $dimensions : $remoteDimensions;
		}
		else
		{
			$cleanedPath = $this->trimQuery($url);
		}

		$cleanedPath = !empty($rootPath) && substr($cleanedPath, 0, $pathLength) == $rootPath ? substr($url, $pathLength) : $cleanedPath;
		$imagePath   = trim($this->platform->getRootPath() . '/' . Wb\lTrim($cleanedPath, '/'));

		$key = md5($imagePath);
		if (
			$this->cacheLocalImages
			&&
			$this->localImageCache->contains($key)
		)
		{
			return $this->localImageCache->get($key);
		}

		$fileExists = \is_file($imagePath);
		if (
			!$fileExists
			&&
			Wb\Contains($imagePath, '%20')
		)
		{
			// if image has spaces in name, and was set as an article image on Joomla edit page,
			// then Joomla URL-encode the image file name, replacing the spaces with %20.
			// That's ok with a URL but not a file name and make file_exists() fail.
			// So if the check fails and there are %20 in the path, try again with spaces
			$imagePath  = str_replace('%20', ' ', $imagePath);
			$fileExists = \is_file($imagePath);
		}

		if ($fileExists)
		{
			$imageInfos = \getimagesize($imagePath);
			if (!empty($imageInfos))
			{
				$dimensions = [
					'width'  => $imageInfos[0],
					'height' => $imageInfos[1]
				];

				if ($this->cacheLocalImages)
				{
					$this->localImageCache
						->store(
							$dimensions,
							$key
						);
				}
			}
		}

		return $dimensions;
	}

	private function trimQuery($url)
	{
		$bits = explode('?', $url);

		return $bits[0];
	}

	public function getCachedRemoteImageDimensions($url)
	{
		$key = md5($url);

		if (
			$this->cacheRemoteImages
			&&
			$this->remoteImageCache->contains($key)
		)
		{
			return $this->remoteImageCache->get($key);
		}

		$dimensionsRead = $this->getRemoteImageDimensions($url);

		// format response
		if (is_array($dimensionsRead))
		{
			$cachedDimensions = $dimensionsRead;
		}
		else if (!empty($dimensionsRead))
		{
			$cachedDimensions = [
				'width'  => $dimensionsRead[0],
				'height' => $dimensionsRead[1]
			];
		}
		else
		{
			$cachedDimensions = [
				'width'  => 0,
				'height' => 0
			];
		}

		if ($this->cacheRemoteImages)
		{
			$this->remoteImageCache
				->store(
					$cachedDimensions,
					$key
				);
		}

		return $cachedDimensions;
	}

	/**
	 * Read a remote image site. Result should be cached for some time
	 * by the calling party, as this is an expensive operation.
	 *
	 * @param   string  $url
	 *
	 * @return array|bool|mixed
	 * @throws \Exception
	 */
	public function getRemoteImageDimensions($url)
	{
		$dimensions = false;
		$lockKey    = md5($url);

		if ($this->imageLockCache->contains($lockKey))
		{
			return $this->imageLockCache->get($lockKey);
		}

		$this->imageLockCache
			->store(
				$url,
				$lockKey
			);

		// use utility class to fetch remote image
		$sizeReader     = new Remoteimage\Fasterimage();
		$dimensionsRead = $sizeReader->batch([$url]);

		// clear lock
		$this->imageLockCache
			->remove($lockKey);

		// format response
		if (
			is_array($dimensionsRead)
			&&
			!empty($dimensionsRead[$url])
			&&
			is_array($dimensionsRead[$url]['size'])
		)
		{
			$dimensions = [
				'width'  => $dimensionsRead[$url]['size'][0],
				'height' => $dimensionsRead[$url]['size'][1]
			];
		}

		return $dimensions;
	}

	/**
	 * Lookup an image tag in some html content, and returns the src attribute,
	 * based on a selection process:
	 * - none, first found or largest image selection
	 * - an array of minimal width/height the image must have to be included in the selection process
	 *
	 * @param   string  $content        the raw content
	 * @param   int     $selectionMode  one of this class constant for search mode
	 * @param   array   $requiredSize   a minimal width/height specification
	 *
	 * @return array|null Record with image URL (as found in the content (ie relative or absolute)), width, height and number of pixels.
	 */
	public function getBestImage($content, $selectionMode = self::IMAGE_SEARCH_NONE, $requiredSize = ['width' => 0, 'height' => 0])
	{
		$bestImage = null;

		// save time if no image in content
		if (empty($content) || !Wb\contains($content, '<img') || $selectionMode == self::IMAGE_SEARCH_NONE)
		{
			return null;
		}

		// check for a "disable auto search tag" in content
		if (Wb\contains($content, '{4seo_disable_auto_meta_image_detection}'))
		{
			return null;
		}

		// collect images, and select one according to settings
		$regex = '#<img([^>]*)>#Uum';
		$found = preg_match_all($regex, $content, $matches, PREG_SET_ORDER);
		if (!empty($found))
		{
			$bestImageSize = 0;
			foreach ($matches as $match)
			{
				$imageUrl = '';
				if (!empty($match[1]))
				{
					$attributes = System\Strings::parseAttributes($match[1]);
					if (!empty($attributes['src']))
					{
						$imageUrl = $attributes['src'];
					}
				}
				if (!empty($imageUrl))
				{
					// validate size (200x200)
					$imageSize = $this->getImageSize($imageUrl);

					// is it big enough?
					if ($this->isLargeEnough($imageSize, $requiredSize))
					{
						if (self::IMAGE_SEARCH_FIRST == $selectionMode)
						{
							// we got a winner
							$bestImage = [
								'url'    => $imageUrl,
								'width'  => $imageSize['width'],
								'height' => $imageSize['height'],
								'pixels' => $imageSize['width'] * $imageSize['height'],
								'alt'    => Wb\arrayGet($attributes, 'alt', '')
							];
							break;
						}
						else
						{
							// looking for the biggest one
							// store current image size
							$currentImageSize = (int) $imageSize['width'] + (int) $imageSize['height'];
							if ($currentImageSize > $bestImageSize)
							{
								$bestImage     = [
									'url'    => $imageUrl,
									'width'  => $imageSize['width'],
									'height' => $imageSize['height'],
									'pixels' => $imageSize['width'] * $imageSize['height'],
									'alt'    => Wb\arrayGet($attributes, 'alt', '')
								];
								$bestImageSize = $currentImageSize;
							}
						}
					}
				}
			}
		}

		return $bestImage;
	}

	/**
	 * Checks an image dimensions against a required minimal width/height/pixels combination.
	 * Both width and height checks must be present and pass. Pixels check is optional.
	 *
	 * Image size equals to required size is valid.
	 *
	 * @param   array  $imageSize
	 * @param   array  $requiredSize
	 *
	 * @return bool
	 */
	public function isLargeEnough($imageSize, $requiredSize)
	{
		$isLargeEnough = (!empty($imageSize['width']) && $imageSize['width'] >= $requiredSize['width'])
			&&
			(!empty($imageSize['height']) && $imageSize['height'] >= $requiredSize['height']);

		if (!$isLargeEnough)
		{
			return false;
		}

		$requiredPixels = Wb\arrayGet(
			$requiredSize,
			'pixels'
		);

		if (!empty($requiredPixels))
		{
			$isLargeEnough = $imageSize['width'] * $imageSize['height'] > $requiredPixels;
		}

		return $isLargeEnough;
	}
}
