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
use Weeblr\Forseo\Controller;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Alias extends Url
{
	/**
	 * @var string Database table associated with this instance.
	 */
	protected $table = '#__forseo_pages_aliases';

	/**
	 * @var array Holds data read/written to db. Also a specification of the columns.
	 */
	protected $defaults = [
		'id'         => 0,
		'url'        => '',
		'full_url'   => '',
		'alias'      => '',
		'full_alias' => '',
		'last_hit'   => null,
		'hits'       => 0,
		'enabled'    => Url::ENABLED
	];

	/**
	 * @var array List of types that should be enforced if present for properties.
	 */
	protected $dataTypes = [
		'id'      => System\Convert::INT,
		'hits'    => System\Convert::INT,
		'enabled' => System\Convert::INT
	];

	/**
	 * @var array List of max length per column.
	 */
	protected $autotrimSpec = [
		'url'        => 190,
		'full_url'   => 2048,
		'alias'      => 190,
		'full_alias' => 2048
	];

	/**
	 * @var string[] List of data fields representing URLs, which needs to be converted to indexable, with the
	 *     corresponding indexable column name.
	 */
	protected $storageSafeColumns = [
		'full_url'   => 'url',
		'full_alias' => 'alias',
	];

	/**
	 * @var string[] List of columns and their id which can be searched.
	 */
	protected $searchableColumns = [
		'alias' => 'alias',
		'url'   => 'url'
	];

	/**
	 * @var string[] List of columns and their id which can be ordered by.
	 */
	protected $orderableColumns = [
		'alias',
		'url',
		'hits'
	];

	/**
	 * @var array List of data key that should be ignored when storing to the DB.
	 */
	protected $dbIgnore = [];

	/**
	 * Load instance from db by searching for a given alias
	 *
	 * @param string $searchedFullAlias
	 * @param string $fullName
	 * @return mixed
	 * @throws \Exception
	 */
	public function loadPerAlias($searchedFullAlias, $fullName = 'full_alias')
	{
		return empty($searchedFullAlias)
			? null
			: $this->loadStorageSafe(
				$searchedFullAlias,
				$fullName
			);
	}

	/**
	 * A chance to massage data before storing it.
	 *
	 * @param array $storeOptions
	 * @return bool
	 */
	public function beforeStore($storeOptions = [])
	{
		return true;
	}

	/**
	 * Hook to perform additional options after setting a value.
	 *
	 * @param string $key
	 * @param mixed  $newValue
	 * @param mixed  $previousValue
	 *
	 * @return Page
	 * @throws \Exception
	 */
	protected function afterSetKey($key, $newValue, $previousValue)
	{
		return $this;
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
			case 'last_hit':
				$this->timestamp('last_hit');
				break;
		}

		return parent::encodeValue($key, $value);
	}
}
