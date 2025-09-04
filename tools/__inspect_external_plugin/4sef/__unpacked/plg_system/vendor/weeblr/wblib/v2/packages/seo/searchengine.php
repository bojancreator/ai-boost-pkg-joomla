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

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * A class to detect search engines requests.
 *
 */
class Searchengine extends Base\Base
{
	const NONE = '';
	const BING = 'bing';
	const GOOGLE = 'google';
	const BAIDU = 'baidu';
	const DUCK_DUCK_GO = 'duckduckgo';
	const YANDEX = 'yandex';
	const OTHERS = 'others';

	/**
	 * @var string Internal id of this search engine.
	 */
	public $id = '';

	/**
	 * @var string Public name of this search engine.
	 */
	public $name = '';

	/**
	 * @var bool Whether to validate search engines requesting IP address against domains.
	 */
	protected $validateIp = true;

	/**
	 * @var string[] List of possible user agents.
	 */
	protected $userAgents = [];

	/**
	 * @var string[] List of possible Ip addresses.
	 */
	protected $allowedIps = [];

	/**
	 * @var string[] List of possible hosts, each in the form .google.com (ie with a leading dot).
	 */
	protected $allowedHosts = [];

	/**
	 * @var string[][] A list of known IP addresses to use for development.
	 */
	protected $devIps = [
		self::BING   => '157.55.39.84',
		self::GOOGLE => '66.249.66.1'
	];

	/**
	 * Stores options.
	 *
	 * @param   array  $options  Can inject custom factory and platform.
	 */
	public function __construct($options = [])
	{
		parent::__construct($options);

		$this->validateIp = Wb\arrayGet(
			$options,
			'validateIp',
			true
		);
	}

	/**
	 * Whether this search engine is making the current request, using cached information.
	 *
	 * @param   string  $userAgent
	 * @param   string  $ip
	 *
	 * @return bool
	 */
	public function isRequesting($userAgent, $ip)
	{
		static $cache;

		if ('dev' == WBLIB_Forsef_OP_MODE)
		{
			$ip = Wb\arrayGet(
				$this->devIps,
				$this->id,
				$ip
			);
		}

		$key = md5($this->id . $userAgent . $ip . ($this->validateIp ? '1' : '0'));

		// was it cached?
		if (is_null($cache))
		{
			$cache = $this->platform->getCache(
				'output',
				[
					'caching'      => 1,
					'lifetime'     => 10080,
					'defaultgroup' => 'wblib_search_engines'
				]
			);
		}

		if ($cache->contains($key))
		{
			return true;
		}

		$isRequesting = $this->isRequestBySearchEngine($userAgent, $ip);

		if ($isRequesting)
		{
			// cache positive responses, ie identified bots
			$cache->store(
				true,
				$key
			);
		}

		return $isRequesting;
	}

	/**
	 * Whether this search engine is making the current request.
	 *
	 * @param $userAgent
	 * @param $ip
	 *
	 * @return bool
	 */
	public function isRequestBySearchEngine($userAgent, $ip)
	{
		return $this->isUserAgent($userAgent)
			&& $this->isIpAddress($ip)
			&& $this->isAllowedHost($ip);
	}

	/**
	 * Whether user agent matches that of this search engine.
	 *
	 * @param   string  $userAgent
	 *
	 * @return bool
	 */
	protected function isUserAgent($userAgent)
	{
		return Wb\contains(
			$userAgent,
			$this->userAgents,
			false // $caseSensitive
		);
	}

	/**
	 * Whether requesting IP address matches that of this search engine.
	 *
	 * @param   string  $ip
	 *
	 * @return bool
	 */
	protected function isIpAddress($ip)
	{
		if (empty($this->allowedIps))
		{
			return true;
		}

		return in_array(
			$ip,
			$this->allowedIps
		);
	}

	/**
	 * Whether requesting IP address is from a host from this search engine.
	 *
	 * @param   string  $ip
	 *
	 * @return bool
	 */
	protected function isAllowedHost($ip)
	{
		if (!$this->validateIp)
		{
			return true;
		}

		if (empty($this->allowedHosts))
		{
			return true;
		}

		$host = System\Http::getReverseDns($ip);
		if (false === $host)
		{
			return false;
		}

		if (Wb\Endswith(
			'.' . $host,
			$this->allowedHosts
		))
		{
			$hostIp = System\Http::getForwardDns($host);

			return $ip == $hostIp;
		}

		return false;
	}
}