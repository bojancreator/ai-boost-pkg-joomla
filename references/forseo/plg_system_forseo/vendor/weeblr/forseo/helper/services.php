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

use Weeblr\Forseo\Data;

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Services extends Base\Base
{
	/**
	 * Check the requested service name is in allowed range.
	 *
	 * @param string $service
	 *
	 * @return bool | \Exception
	 */
	public function validateService($service)
	{
		if (!in_array(
			$service,
			Data\Services::ALLOWED_SERVICES
		))
		{
			return new \Exception(
				'Invalid service name ' . print_r($service, true),
				System\Http::RETURN_NOT_FOUND
			);
		}

		return true;
	}

	/**
	 * Extract a provider name (Google) from a service id (google.search_console).
	 *
	 * @param string $serviceName
	 * @return string
	 */
	public function providerFromServiceName($serviceName)
	{
		$serviceBits = explode('.', $serviceName);
		return !empty($serviceBits[0])
			? ucfirst($serviceBits[0])
			: '';
	}

	/**
	 * Build a model name (Searchconsole) based on a service name (google.search_console)
	 * with an optional suffix (Searchconsoledata).
	 *
	 * @param string $serviceName
	 * @param string $suffix
	 * @return string
	 */
	public function modelNameFromServiceName($serviceName, $suffix = '')
	{
		$serviceBits = explode('.', $serviceName, 2);
		$name        = !empty($serviceBits[1])
			? $serviceBits[1]
			: '';

		return ucfirst(
			str_replace('_', '', $name) . $suffix
		);
	}

	/**
	 * Surface a more detailed error message if error is unauthorized.
	 *
	 * @param \Throwable $e
	 * @return \Exception
	 */
	public function processException(\Throwable $e)
	{
		if (in_array($e->getCode(), [System\Http::RETURN_UNAUTHORIZED, System\Http::RETURN_BAD_REQUEST]))
		{
			return new \Exception('Cannot connect to Service: ' . $e->getMessage(), $e->getCode());
		}

		return new \Exception('Full error message has been stored to the 4SEO log file on the server', 500);
	}
}
