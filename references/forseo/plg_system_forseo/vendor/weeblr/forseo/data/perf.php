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

class Perf extends Url
{
	public const MOBILE  = 0;
	public const DESKTOP = 1;
	public const ANY     = 126;
	public const UNKNOWN = 127;

	/**
	 * @var string Database table associated with this instance.
	 */
	protected $table = '#__forseo_perf_data';

	/**
	 * @var array Holds data read/written to db. Also a specification of the columns.
	 */
	protected $defaults = [
		'id'       => 0,
		'url'      => '',
		'full_url' => '',
		'fid'      => null,
		'inp'      => null,
		'lcp'      => null,
		'cls'      => null,
		'ttfb'     => null,
		'ts'       => 0,
		'device'   => self::UNKNOWN
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
		'url' => 'url'
	];

	/**
	 * @var string[] List of columns and their id which can be ordered by.
	 */
	protected $orderableColumns = [
		'url',
		'ts'
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
			case 'cls':
			case 'fid':
			case 'inp':
			case 'lcp':
			case 'ttfb':
				if ('cls' === $key)
				{
					$value = empty($value) ? 0 : (int)(1000000 * $value);
				}

				if ('cls' !== $key)
				{
					$value = is_null($value) ? null : (int)(1000 * $value);
				}
				break;
			case 'device':
				$value = is_null($value) ? self::UNKNOWN : $value;
				break;
		}

		return parent::encodeValue($key, $value);
	}
}
