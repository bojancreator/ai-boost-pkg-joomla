<?php
/**
 * Project:                 4SEF
 *
 * @author           Yannick Gaultier - Weeblr llc
 * @copyright        Copyright Weeblr llc - 2022 -2025
 * @package          4SEF
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          @build_version_full_build@
 * @date         2025-06-02
 */

namespace Weeblr\Wblib\Forsef\Seo;

// Security check to ensure this file is being included by a parent file.
use Weeblr\Wblib\Forsef\Base\Base;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Wb;

defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * HTML output helper.
 *
 */
class Helper extends Base
{
	/**
	 * @var string|null The detected search engines making the request, if any.
	 */
	private static $engine;

	/**
	 * @var bool Whether to validate search engines requesting IP address against domains.
	 */
	private static $validateIp = true;

	/**
	 * @var string This configuration unique ID.
	 */
	protected $engines = [
		'google',
		'bing',
		'baidu',
		'duckduckgo',
		'yandex',
		'others'
	];

	/**
	 * Stores options.
	 *
	 * @param   array  $options  Can inject custom factory and platform.
	 */
	public function __construct($options = [])
	{
		parent::__construct($options);

		self::$validateIp = Wb\arrayGet(
			$options,
			'validateIp',
			true
		);
	}

	/**
	 * List of supported search engines identifiers.
	 *
	 * @return string[]
	 */
	public function getSupported()
	{
		return $this->engines;
	}

	/**
	 * Returns the search engines code making the current request or empty string
	 * if no search engines is recognized.
	 *
	 * @return string
	 */
	public function getRequestingSearchEngine()
	{
		if (is_null(self::$engine))
		{
			$this->detect(self::$validateIp);
		}

		return self::$engine;
	}

	/**
	 * Whether current request is by a known search engine.
	 *
	 * @return bool
	 */
	public function isSearchEngineRequest()
	{
		if (is_null(self::$engine))
		{
			$this->detect(self::$validateIp);
		}

		return self::$engine != Searchengine::NONE;
	}

	/**
	 * Fetch from our server and caches for a few days the list of search engines updates
	 * we know of.
	 *
	 * @return array
	 */
	public function getEnginesUpdates()
	{
		$cache = $this->platform->getCache(
			'callback',
			array(
				'defaultgroup' => '4seo_search_engines_updates',
				'lifetime'     => 86300, // less than a day
				'caching'      => 1,
			)
		);

		try
		{
			$list = $cache->get(
				[
					$this,
					'fetchUpdatesList'
				]
			);
		}
		catch (\Throwable $e)
		{
			// @TODO: surface error to client
			$this->logger->error('Error fetching %s::%d %s %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
			$list = [];
		}

		return $list;
	}

	/**
	 * Fetch from update server and parse the manifest describing latest version.
	 *
	 * @return array
	 */
	public function fetchUpdatesList()
	{
		$httpClient = $this->platform->getHttpClient(
			array(
				'follow_location' => true
			)
		);

		try
		{
			$response = $httpClient->get(
				'https://cdn.weeblr.net/data/search_engines_updates.json',
				[],
				5
			);
		}
		catch (\Exception $e)
		{
			System\Log::libraryError('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return [];
		}

		$list = json_decode($response->body, true);
		if (empty($list))
		{
			System\Log::libraryError('Error fetching search engines list file, unable to parse json from ' . $response->body);

			return [];
		}

		return $list;
	}

	/**
	 * Iterate over known search engines to recognize the request.
	 *
	 * @param   bool  $validateIp  Whether to validate the IP address against search engines hosts.
	 *
	 */
	private function detect($validateIp = true)
	{
		$userAgent = System\Http::userAgent();
		$ip        = System\Http::getIpAddress();

		foreach ($this->engines as $engineId)
		{
			$engine = $this->factory->getA(
				'Weeblr\Wblib\Forsef\Seo\\' . ucfirst($engineId),
				[
					'validateIp' => self::$validateIp
				]
			);
			if ($engine->isRequesting(
				$userAgent,
				$ip,
				$validateIp
			))
			{
				self::$engine = $engineId;
				break;
			}
		}
	}
}
