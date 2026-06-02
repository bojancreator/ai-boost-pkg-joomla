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

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Db;
use Weeblr\Wblib\Forseo\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Referrer extends Url
{
	/**
	 * @var string Database table associated with this instance.
	 */
	protected $table = '#__forseo_referrers';

	/**
	 * @var array Holds data read/written to db. Also a specification of the columns.
	 */
	protected $defaults = [
		'id'       => 0,
		'url'      => '',
		'full_url' => '',
	];

	/**
	 * @var array List of types that should be enforced if present for properties.
	 */
	protected $dataTypes = [
		'id' => System\Convert::INT,
	];

	/**
	 * @var array List of max length per column.
	 */
	protected $autotrimSpec = [
		'full_url' => 2048,
	];

	/**
	 * @var string[] List of columns and their id which can be searched.
	 */
	protected $searchableColumns = [
		'refurl' => 'url'
	];

	/**
	 * @var string[] List of columns and their id which can be ordered by.
	 */
	protected $orderableColumns = [
		'url'
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
		switch ($fullName)
		{
			case 'full_url':
				// home page is empty, make it just /
				$searchedFullUrl = Wb\initEmpty(
					$searchedFullUrl,
					'/'
				);

				break;
		}

		return parent::loadPerUrl(
			$searchedFullUrl,
			$fullName
		);
	}

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
				// home page is empty, make it just /
				$value = Wb\initEmpty(
					$value,
					'/'
				);

				break;
		}

		return parent::encodeValue($key, $value);
	}
}
