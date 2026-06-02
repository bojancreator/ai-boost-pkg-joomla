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
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Db;
use Weeblr\Wblib\Forseo\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Sitemaps history
 *
 * @package Weeblr\Forseo\Data
 */
class Sitemap extends Db\Dataobject
{
	public const DISABLED = 0;
	public const ENABLED  = 1;

	/**
	 * Multiple sitemaps types.
	 */
	public const CONTENT = 0;
	public const NEWS    = 1;
	public const IMAGES  = 2;
	public const VIDEOS  = 3;

	/**
	 * Build state.
	 */
	public const READY       = 0;
	public const STALE       = 1;
	public const IN_PROGRESS = 2;

	/**
	 * File type for a non-sitemap request.
	 */
	public const FILE_NONE = 128;

	/**
	 * File type for an index sitemap.
	 */
	public const FILE_INDEX = 0;

	/**
	 * File type for a partial.
	 */
	public const FILE_PARTIAL = 1;

	/**
	 * @var string Database table associated with this instance.
	 */
	protected $table = '#__forseo_sitemaps';

	/**
	 * @var array Holds data read/written to db. Also a specification of the columns.
	 */
	protected $defaults = [
		'id'                  => 0,
		'type'                => 0,
		'file_type'           => self::FILE_INDEX,
		'lang'                => '',
		'crawl_id'            => '',
		'hash'                => '',
		'created_at'          => null,
		'url_count'           => 0,
		'processed_url_count' => 0,
		'image_count'         => 0,
		'serial'              => 0,
		'google_submitted_at' => null,
		'google_last_fetch'   => null,
		'google_fetches'      => 0,
		'bing_submitted_at'   => null,
		'bing_last_fetch'     => null,
		'bing_fetches'        => 0,
		'state'               => self::READY,
		'enabled'             => self::ENABLED,
	];

	/**
	 * @var array List of types that should be enforced if present for properties.
	 */
	protected $dataTypes = [
		'id'                  => System\Convert::INT,
		'type'                => System\Convert::INT,
		'file_type'           => System\Convert::INT,
		'url_count'           => System\Convert::INT,
		'processed_url_count' => System\Convert::INT,
		'image_count'         => System\Convert::INT,
		'serial'              => System\Convert::INT,
		'hash'                => System\Convert::STRING,
		'google_fetches'      => System\Convert::INT,
		'bing_fetches'        => System\Convert::INT,
		'state'               => System\Convert::INT,
		'enabled'             => System\Convert::INT,
	];
}
