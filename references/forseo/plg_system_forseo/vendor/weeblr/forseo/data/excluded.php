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

use Weeblr\Wblib\Forseo\Db;
use Weeblr\Wblib\Forseo\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Excluded extends Url
{
	/**
	 * @var string Database table associated with this instance.
	 */
	protected $table = '#__forseo_excluded_urls';

	protected $defaults = [
		'id'       => 0,
		'url'      => '',
		'full_url' => '',
	];

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
	 * Before storing any URL to be crawled, perform some checks on it.
	 *
	 * @param array $storeOptions
	 * @return bool
	 */
	public function beforeStore($storeOptions = [])
	{
		// never allow home page to be excluded
		return Url::HOME_PAGE !== $this->get('full_url');

	}
}
