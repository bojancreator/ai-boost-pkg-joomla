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

class Yandex extends Searchengine
{
	/**
	 * @var string Internal id of this search engine.
	 */
	public $id = self::YANDEX;

	/**
	 * @var string Public name of this search engine.
	 */
	public $name = 'Yandex';

	/**
	 * @var string[] List of possible user agents.
	 */
	protected $userAgents = [
		'Yandex'
	];

	/**
	 * @var string[] List of possible hosts.
	 */
	protected $allowedHosts = [
		'*.yandex.com',
		'*.yandex.ru',
		'*.yandex.net'
	];

	/**
	 * @var string[] List of possible Ip addresses.
	 */
	protected $allowedIps = [];
}
