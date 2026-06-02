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

namespace Weeblr\Forseo\Helper;

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Wb;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Update extends Base\Base
{
	/**
	 * Time between 2 attempts at fetching new update information.
	 */
	public const CACHE_TIME = 60;

	/**
	 * @var System\Config System configuration instance.
	 */
	private $config = null;

	/**
	 * @var System\Logger Convenience logger instance.
	 */
	private $logger = null;

	/**
	 * @var Cache from platform.
	 */
	private $cache = null;

	/**
	 * Store a logger and config for convenience.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->config = $this->factory->getThis('forseo.config', 'app');
		$this->logger = $this->factory->getThe('forseo.logger');

		$this->cache = $this->platform->getCache(
			'callback',
			array(
				'defaultgroup' => '4seo_updates',
				'lifetime'     => self::CACHE_TIME,
				'caching'      => 1,
			)
		);
	}

	/**
	 *
	 * @return string
	 */
	public function latestVersion()
	{
		try
		{
			$update = $this->cache->get(
				[
					$this,
					'findAndCacheAvailableUpdate'
				]
			);
		}
		catch (\Throwable $e)
		{
			// @TODO: surface error to client
			$this->logger->error('Error fetching %s::%d %s %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
			$update = '';
		}

		return $update;
	}

	/**
	 * Make a request to load the online manifest file holding update data
	 * and parse it to extract new version.
	 *
	 * @return array
	 */
	public function findAndCacheAvailableUpdate()
	{
		$latestVersionDetails       = $this->fetchUpdateManifest();
		$parsedLatestVersionDetails = $this->parseManifest(
			$latestVersionDetails,
			'6.10.1.2660'
		);

		return array_merge(
			Wb\arrayEnsure($latestVersionDetails),
			Wb\arrayEnsure($parsedLatestVersionDetails)
		);
	}

	/**
	 * Figure out if current version should be updated to latest, including checks for:
	 *
	 * - dev mode
	 * - platform version
	 * - PHP version
	 *
	 * @param array  $latestVersionDetails
	 * @param string $currentVersion
	 * @return array
	 */
	private function parseManifest($latestVersionDetails, $currentVersion)
	{
		if (empty($latestVersionDetails))
		{
			return [
				'shouldShow'    => false,
				'shouldUpdate'  => false,
				'validPlatform' => true,
				'validPhp'      => true
			];
		}

		$result = [
			'shouldShow'    => true,
			'shouldUpdate'  => true,
			'validPlatform' => true,
			'validPhp'      => true
		];

		if (Wb\contains($currentVersion, 'build_version_build'))
		{
			// dev mode, show details only
			return $result;
		}

		if (version_compare(
			$currentVersion,
			Wb\arrayGet($latestVersionDetails, 'current'),
			'ge')
		) {
			// already there
			$result['shouldShow']   = false;
			$result['shouldUpdate'] = false;
			return $result;
		}

		// should update but can we?
		$platformVersion = $this->platform->version();
		if (!$this->isCompatibleWith(
			Wb\arrayGet($latestVersionDetails, 'minVersionToUpgrade', ''),
			Wb\arrayGet($latestVersionDetails, 'maxVersionToUpgrade', ''),
			$platformVersion
		))
		{
			$result['shouldUpdate']  = false;
			$result['validPlatform'] = false;
		}

		if (in_array(
			$platformVersion,
			Wb\arrayGet($latestVersionDetails, 'platformExclude', ''))
		) {
			$result['shouldUpdate']  = false;
			$result['validPlatform'] = false;
		}

		if (in_array(
			$platformVersion,
			Wb\arrayGet($latestVersionDetails, 'platformInclude', ''))
		) {
			$result['shouldUpdate']  = true;
			$result['validPlatform'] = true;
		}

		$phpVersion = PHP_VERSION;
		if (!$this->isCompatibleWith(
			Wb\arrayGet($latestVersionDetails, 'minPhpToUpgrade', ''),
			Wb\arrayGet($latestVersionDetails, 'maxPhpToUpgrade', ''),
			$phpVersion
		))
		{
			$result['shouldUpdate'] = false;
			$result['validPhp']     = false;
		}

		if (in_array(
			$phpVersion,
			Wb\arrayGet($latestVersionDetails, 'phpExclude', ''))
		) {
			$result['shouldUpdate'] = false;
			$result['validPhp']     = false;
		}

		if (in_array(
			$phpVersion,
			Wb\arrayGet($latestVersionDetails, 'phpInclude', ''))
		) {
			$result['shouldUpdate'] = true;
			$result['validPhp']     = true;
		}

		return $result;
	}

	/**
	 * Compare a value with a min and a max.
	 * value can be equal to min but has to be stricly lower than max to pass.
	 *
	 * @param string $min
	 * @param string $max
	 * @param string $value
	 * @return bool
	 */
	private function isCompatibleWith($min, $max, $value)
	{
		if (!empty($min)
			&&
			version_compare($value, $min, '<')
		) {
			return false;
		}

		if (!empty($max)
			&&
			version_compare($value, $max, '>=')
		) {
			return false;
		}

		return true;
	}

	/**
	 * Fetch from update server and parse the manifest describing latest version.
	 *
	 * @return array
	 */
	private function fetchUpdateManifest()
	{
		$httpClient = $this->platform->getHttpClient(
			array(
				'follow_location' => true
			)
		);

		try
		{
			$response = $httpClient->get(
				$this->updateManifestUrl(),
				[],
				10
			);
		}
		catch (\Exception $e)
		{
			$this->logger->error('Error fetching update manifest file %s::%d %s %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
			return [];
		}

		$latestVersionDetails = json_decode($response->body, true);
		if (empty($latestVersionDetails))
		{
			$this->logger->debug('Error fetching update manifest file, unable to parse json from ' . $response->body);
			return [];
		}

		$latestVersionDetails['noteHtml'] = base64_encode($latestVersionDetails['noteHtml']);

		return $latestVersionDetails;
	}

	/**
	 * Compute the full online manifest URL, taking into account possible dev mode.
	 *
	 * return string
	 */
	private function updateManifestUrl()
	{
		$manifestUrl = $this->config->get('updateManifestUrl');
		if ('dev' == WBLIB_Forseo_OP_MODE)
		{
			// dev mode
			$manifestUrl = str_replace(
				'https://u1.weeblr.com',
				'https://u1.weeblr.com',
				$manifestUrl
			);
		}

		return $manifestUrl;
	}
}