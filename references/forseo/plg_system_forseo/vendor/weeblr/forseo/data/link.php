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

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\Db;
use Weeblr\Wblib\Forseo\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Link extends Url
{
	/**
	 * @var string Database table associated with this instance.
	 */
	protected $table = '#__forseo_links';

	/**
	 * @var array Holds data read/written to db. Also a specification of the columns.
	 */
	protected $defaults = [
		'id'              => 0,
		'status'          => Url::STATUS_OK,
		'target'          => Url::TARGET_INTERNAL,
		'url'             => '',
		'full_url'        => '',
		'scheme'          => '',
		'host'            => '',
		'final_url'       => '',
		'full_final_url'  => '',
		'redirects_count' => 0,
		'last_hit'        => null,
		'hits'            => 0,
	];

	/**
	 * @var array List of types that should be enforced if present for properties.
	 */
	protected $dataTypes = [
		'id'              => System\Convert::INT,
		'status'          => System\Convert::INT,
		'target'          => System\Convert::INT,
		'redirects_count' => System\Convert::INT,
		'hits'            => System\Convert::INT,
	];

	/**
	 * @var array List of max length per column.
	 */
	protected $autotrimSpec = [
		'full_url'       => 2048,
		'scheme'         => 40,
		'host'           => 100,
		'full_final_url' => 2048,
	];

	/**
	 * @var string[] List of data fields representing URLs, which needs to be converted to indexable, with the
	 *     corresponding indexable column name.
	 */
	protected $storageSafeColumns = [
		'full_url'       => 'url',
		'full_final_url' => 'final_url'
	];

	/**
	 * @var string[] List of columns and their id which can be searched.
	 */
	protected $searchableColumns = [
		'url'   => 'url',
		'final' => 'final_url',
		'host'  => 'host'
	];

	/**
	 * @var string[] List of columns and their id which can be ordered by.
	 */
	protected $orderableColumns = [
		'hits',
		'url',
		'host',
		'final_url'
	];

	/**
	 * Fetch and returns all referrers for this object.
	 *
	 * @return array
	 */
	public function referrers()
	{

//		$query = 'select links.*, referrers.url as referrer_url, referrers.full_url as referrer_full_url'
//			. ' from ' . $this->dbHelper->quoteName('#__forseo_links') . ' as links'
//			. ' join ' . $this->dbHelper->quoteName('#__forseo_referrers') . ' as referrers'
//			. ' join ' . $this->dbHelper->quoteName('#__forseo_referrers_links') . ' as referrers_xref'
//			. ' on links.id = referrers_xref.referree_id';
//
//		$data = $this->dbHelper
//			->setQueryAnd($query)
//			->loadAssocList('id');
//
//		$data = empty($data)
//			? []
//			: $data;

//			select links .*, referrers . url as referrer_url, referrers . full_url as referrer_full_url
//			from o8dbj_forseo_links as links
//			         join o8dbj_forseo_referrers as referrers
//			         join o8dbj_forseo_referrers_links as referrers_xref
//			              on links . id = referrers_xref . referree_id


		return [];
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
				// compute short version of URL, for db storage and indexation

				// update host and scheme based on latest URL
				$this->data['host']   = System\Route::getHost($value);
				$this->data['scheme'] = System\Route::getScheme($value);

				// update whether this is an internal URL
				$this->data['target'] = System\Route::isInternal($value)
					? Url::TARGET_INTERNAL
					: Url::TARGET_EXTERNAL;

				break;
		}

		return parent::encodeValue($key, $value);
	}
}
