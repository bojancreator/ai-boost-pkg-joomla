<?php
/**
 * Project:                 4SEF
 *
 * @package                 4SEF
 * @copyright               Copyright Weeblr llc - 2022 -2025
 * @author                  Yannick Gaultier - Weeblr llc
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 @build_version_full_build@
 *
 * 2025-06-02
 */

namespace Weeblr\Wblib\Forsef\Seo;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

class Google extends Searchengine
{
	/**
	 * @var string Internal id of this search engine.
	 */
	public $id = self::GOOGLE;

	/**
	 * @var string Public name of this search engine.
	 */
	public $name = 'Google';

	/**
	 * https://developers.google.com/search/docs/advanced/crawling/overview-google-crawlers
	 *
	 * @var string[] List of possible user agents.
	 */
	protected $userAgents = [
		'Googlebot',
		'Storebot-Google',
	];

	/**
	 * @var string[] List of possible hosts.
	 */
	protected $allowedHosts = [
		'.google.com',
		'.googlebot.com'
	];
}
