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

namespace Weeblr\Forseo\Model;

use Weeblr\Forseo\Helper;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;


// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Geo extends Base\Base
{
	/**
	 * Whether to cache geocoding requests.
	 */
	public const CACHE_GEOCODING_REQUESTS = 1;

	/**
	 * Cache fetched GEO coordinates for 7 days
	 */
	public const GEO_CACHE_TIME = 604800;

	/**
	 * Finds the geo coordinates for a given street address, caching the
	 * values found, whether the search was successful or not.
	 *
	 * @param array $addressRecord
	 * @param false $cache
	 * @return array|null
	 */
	public function findCoordinates($addressRecord, $cache = false)
	{
		$locality = array_filter([
				Wb\arrayGet($addressRecord, 'postalCode', ''),
				Wb\arrayGet($addressRecord, 'addressLocality', '')
			]
		);
		$location = array_filter([
				Wb\arrayGet($addressRecord, 'streetAddress', ''),
				implode(' ', $locality),
				Wb\arrayGet($addressRecord, 'addressRegion', ''),
				Wb\arrayGet($addressRecord, 'addressCountry', '')
			]
		);
		$location = implode(',', $location);

		if (empty($location))
		{
			return [
				'error_message' => 'Unable to geo-locate, provided location is empty.',
				'longitude'     => null,
				'latitude'      => null
			];
		}

		if ($cache)
		{
			$cache = $this->platform->getCache(
				'callback',
				array(
					'defaultgroup' => '4seo_sd_geo',
					'lifetime'     => self::GEO_CACHE_TIME,
					'caching'      => self::CACHE_GEOCODING_REQUESTS,
				)
			);

			return $cache->get(
				[
					$this,
					'fetchCoordinates'
				],
				$location,
				md5($location)
			);
		}

		return $this->fetchCoordinates($location);

	}

	/**
	 * Fetches Google Maps search result for a given street address
	 * and extact the geo coordinates.
	 *
	 * @param string $address
	 * @return array|null
	 */
	public function fetchCoordinates($address)
	{
		$url = 'https://maps.googleapis.com/maps/api/geocode/json?address='
			   . urlencode($address)
			   . '&key=' . $this->factory->getA(Helper\Config::class)->getMapsApiKey();

		try
		{
			$client = $this->platform->getHttpClient(
				[
					'follow_location' => true,
					'timeout'         => 3,
				]
			);

			$response = $client->get($url);
			return System\Http::isSuccess($response->code)
				? $this->extractGeoFromResponse($response->body)
				: [
					'error_message' => 'Error communicating with GeoCoding server. Maybe try again later?',
					'longitude'     => null,
					'latitude'      => null
				];
		}
		catch (\Exception $e)
		{
			// error while fetching, wait before retry for standard caching period
			return null;
		}
	}

	/**
	 * Parse response from a Google Maps search and extract geo coordinates.
	 * Pure hack, don't do this if you don't have to.
	 *
	 * @param string $body
	 * @return array|null
	 */
	private function extractGeoFromResponse($body)
	{
		$errorRecord = [
			'error_message' => 'Error communicating with GeoCoding server. Maybe try again later?',
			'longitude'     => null,
			'latitude'      => null
		];

		if (empty($body))
		{
			return $errorRecord;
		}

		$response = json_decode($body, true);
		if (empty($response))
		{
			return $errorRecord;
		}

		$errorMessage = Wb\arrayGet($response, 'error_message');
		if (!empty($errorMessage))
		{
			$errorRecord['error_message'] = $errorMessage;
			return $errorRecord;
		}

		// extract from json
		$status = Wb\arrayGet(
			$response,
			'status'
		);
		if ('OK' !== $status)
		{
			$errorRecord['error_message'] = 'GeoCoding server cannot find this location. Maybe try again another one?';
			return $errorRecord;
		}

		$latitude  = Wb\arrayGet(
			$response,
			['results', 0, 'geometry', 'location', 'lat'],
			null
		);
		$longitude = Wb\arrayGet(
			$response,
			['results', 0, 'geometry', 'location', 'lng'],
			null
		);

		if (is_null($longitude) || is_null($latitude))
		{
			$errorRecord['error_message'] = 'Invalid latitude or longitude received from GeoCoding server. Maybe try again another location?';
			return $errorRecord;
		}

		return [
			'longitude' => $longitude,
			'latitude'  => $latitude,
		];
	}
}
