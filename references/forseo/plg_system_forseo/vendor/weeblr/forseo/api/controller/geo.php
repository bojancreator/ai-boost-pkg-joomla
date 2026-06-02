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

namespace Weeblr\Forseo\Api\Controller;

use Weeblr\Forseo\Model;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Api;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;


// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Geo extends Api\Controller
{
	/**
	 * Get the lat/long for a physical location.
	 *
	 * @param          $request
	 * @param array    $options
	 *
	 *
	 * @return \Exception|array
	 */
	public function get($request, $options)
	{
		$location = [
			'streetAddress'   => Wb\arrayGet($options, 'streetAddress', ''),
			'postalCode'      => Wb\arrayGet($options, 'postalCode', ''),
			'addressLocality' => Wb\arrayGet($options, 'addressLocality', ''),
			'addressRegion'   => Wb\arrayGet($options, 'addressRegion', ''),
			'addressCountry'  => Wb\arrayGet($options, 'addressCountry', '')
		];

		$textlocation = implode('', $location);
		if (empty($textlocation))
		{
			return new \Exception('No location provided to fetch geo coordinates for.', System\Http::RETURN_BAD_REQUEST);
		}

		try
		{
			$geo = $this->factory
				->getA(Model\Geo::class)
				->findCoordinates(
					$location,
					true
				);

			return [
				'data'  => $geo,
				'count' => 1,
				'total' => 1,
			];
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s', $e->getFile(), $e->getLine(), $e->getMessage());

			return new \Exception('Full error message has been stored to the 4SEO log file on the server', 500);
		}
	}


}
