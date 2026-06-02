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

/**
 * Sitemaps manual inclusion/exclusion
 *
 * @package Weeblr\Forseo\Data
 */
class Sitemapsincludes extends Db\Dataobject
{
	/**
	 * @var string Database table associated with this instance.
	 */
	protected $table = '#__forseo_sitemaps_includes';

	/**
	 * @var array Holds data read/written to db. Also a specification of the columns.
	 */
	protected $defaults = [
		'id'           => 0,
		'url'          => '',
		'sitemap_mode' => Page::AUTO,
		'sitemap_user' => Page::INCLUDED
	];

	/**
	 * @var array List of types that should be enforced if present for properties.
	 */
	protected $dataTypes = [
		'id'           => System\Convert::INT,
		'sitemap_mode' => System\Convert::INT,
		'sitemap_user' => System\Convert::INT,
	];
}
