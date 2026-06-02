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

use Weeblr\Forseo\Helper;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Db;
use Weeblr\Wblib\Forseo\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

abstract class Url extends Db\Dataobject
{
	public const STATUS_OK = 0;

	public const DISABLED = 0;
	public const ENABLED  = 1;

	public const TARGET_INTERNAL = 0;
	public const TARGET_EXTERNAL = 1;

	public const SOURCE_INTERNAL = 0;
	public const SOURCE_EXTERNAL = 1;
	public const SOURCE_CRAWL    = 2;
	public const SOURCE_UNKNOWN  = 0;

	public const CLICK_DEPTH_NONE = -1;

	// Home page stored as an empty string,
	// though displayed as / in the UI
	public const HOME_PAGE = '';

	/**
	 * @var array List of max length per column.
	 */
	protected $autotrimSpec = [
		'full_url' => 2048
	];

	/**
	 * @var string[] List of data fields representing URLs, which needs to be converted to indexable, with the
	 *     corresponding indexable column name.
	 */
	protected $storageSafeColumns = [
		'full_url' => 'url',
	];

	/**
	 * Load instance from db by searching for a given URL.
	 * The provided URL is first processed to be "indexable", ie shortened
	 * as needed and match the format of the indexable database field.
	 *
	 * @param string $searchedFullUrl
	 * @param string $fullName
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function loadPerUrl($searchedFullUrl, $fullName = 'full_url')
	{
		return $this->loadStorageSafe(
			$searchedFullUrl,
			$fullName
		);
	}

	/**
	 * Encode full URL values into their indexable form.
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
			case 'full_referrer':
				// referrer must be passed as HTTP header sometimes
				// HTTP client just drops empty headers so better off
				// standardizing on using / for home page referrers;
				$value = Wb\initEmpty(
					$value,
					'/'
				);

				break;
		}

		return parent::encodeValue($key, $value);
	}
}
