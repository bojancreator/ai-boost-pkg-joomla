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

class Error extends Url
{
	/**
	 * @var string Database table associated with this instance.
	 */
	protected $table = '#__forseo_errors';

	/**
	 * @var array Holds data read/written to db. Also a specification of the columns.
	 */
	protected $defaults = [
		'id'       => 0,
		'status'   => Url::STATUS_OK,
		'message'  => '',
		'target'   => Url::TARGET_INTERNAL,
		'source'   => Url::SOURCE_UNKNOWN,
		'url'      => '',
		'full_url' => '',
		'last_hit' => null,
		'hits'     => 0
	];

	/**
	 * @var array List of max length per column.
	 */
	protected $autotrimSpec = [
		'message'  => 2048,
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
		'hits',
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
		}

		return parent::encodeValue($key, $value);
	}
}
