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

namespace Weeblr\Forseo\Data;

use Weeblr\Wblib\Forseo\Db;
use Weeblr\Wblib\Forseo\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Image extends Url
{
	/**
	 * Set of constants to describe sitemap operation mode
	 */
	public const AUTO = 0;
	public const USER = 1;

	/**
	 * Set of constants to describe inclusion/exclusion from sitemap.
	 */
	public const INCLUDED = 0;
	public const EXCLUDED = 1;

	/**
	 * @var string Database table associated with this instance.
	 */
	protected $table = '#__forseo_images';

	/**
	 * @var array Holds data read/written to db. Also a specification of the columns.
	 *
	 * data =>    string mime
	 *            string file_type Image type code, eg png, jpg, webp,...
	 *            int width Image width in pixels
	 *            int height Image height in pixels
	 *            int pixels Number of pixels in image
	 *            string alt  Image alt text if any
	 *            string title Image title text if any
	 *            array data Key/values of all data attributes found
	 *            string geo Location the image represents
	 *            string license URL to the license under which the images is distributed
	 */
	protected $defaults = [
		'id'            => 0,
		'status'        => Url::STATUS_OK,
		'url'           => '',
		'full_url'      => '',
		'page_url'      => '',
		'page_full_url' => '',
		'target'        => Url::TARGET_INTERNAL,
		'data'          => '',
		'sitemap_mode'  => self::AUTO,
		'sitemap_user'  => self::INCLUDED,
		'sitemap_auto'  => self::INCLUDED,
		'crawled_at'    => null,
		'modified_at'   => null
	];

	/**
	 * @var array Default values for an image descriptor, stored in the data field.
	 */
	protected $dataDefaults = [
		'file_type' => '',
		'width'     => 0,
		'height'    => 0,
		'el_width'  => 0,
		'el_height' => 0,
		'pixels'    => 0,
		'alt'       => '',
		'title'     => '',
		'geo'       => '',
		'license'   => '',
		'data'      => ''
	];

	protected $storageSafeColumns = [
		'full_url'      => 'url',
		'page_full_url' => 'page_url',
	];

	/**
	 * @var array List of max length per column.
	 */
	protected $autotrimSpec = [
		'full_url' => 2048
	];

	/**
	 * @var string[] List of columns and their id which can be searched.
	 */
	protected $searchableColumns = [
		'url' => 'url'
	];

	/**
	 * @var string[] List of columns and their id which can be ordered by.
	 */
	protected $orderableColumns = [
		'url'
	];

	/**
	 * Optionally encode a value before it's stored in the data object.
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return mixed
	 */
	public function encodeValue($key, $value)
	{
		switch ($key)
		{
			case 'full_url':
				// update whether this is an internal URL
				$this->data['target'] = System\Route::isInternal($value)
					? Url::TARGET_INTERNAL
					: Url::TARGET_EXTERNAL;
				break;
			case 'data':
				// encode to json for storage
				$value = json_encode(
					array_merge(
						$this->dataDefaults,
						$value
					)
				);
		}

		return parent::encodeValue($key, $value);
	}

	/**
	 * Optionally decode a value before it's returned from the data object.
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return mixed
	 */
	public function decodeValue($key, $value)
	{
		switch ($key)
		{
			case 'data':
				$decodedValue = json_decode($value, true);

				$value = array_merge(
					$this->dataDefaults,
					empty($decodedValue)
						? []
						: $decodedValue
				);
				break;
		}

		return parent::decodeValue($key, $value);
	}
}
