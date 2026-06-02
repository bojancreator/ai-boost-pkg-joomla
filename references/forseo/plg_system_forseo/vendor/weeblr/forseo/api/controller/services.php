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

use Weeblr\Forseo\Helper;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Api;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Services extends Api\Controller
{
	/**
	 * Builds up an array of data for use in API response.
	 *
	 * @param array $options
	 *
	 * @return array| \Exception
	 */
	public function get($request, $options)
	{
		$service = Wb\arrayGet($options, 'service');
		$helper  = $this->factory->getA(Helper\Services::class);

		try
		{
			$valid = $helper->validateService($service);
			if ($valid instanceof \Throwable)
			{
				return $valid;
			}

			$integrationModelName = 'Weeblr\Forseo\Model\Integrations\\'
									. $helper->providerFromServiceName($service)
									. '\\'
									. $helper->modelNameFromServiceName($service);

			return $this->factory
				->getA($integrationModelName)
				->get($options);
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('Services API error: %s::%d %S %s', $e->getFile(), $e->getLine(), $e->getCode(), $e->getMessage());

			return $helper->processException($e);
		}
	}

	/**
	 * Create a piece of data through a service API.
	 *
	 * @param Weeblr\Wblib\APi\Request $request
	 * @param array                    $options
	 *
	 * @return \Exception
	 */
	public function post($request, $options)
	{
		$service = Wb\arrayGet($options, 'service');
		$helper  = $this->factory->getA(Helper\Services::class);

		try
		{
			$valid   = $helper->validateService($service);
			if ($valid instanceof \Throwable)
			{
				return $valid;
			}

			$integrationModelName = 'Weeblr\Forseo\Model\Integrations\\'
									. $helper->providerFromServiceName($service)
									. '\\'
									. $helper->modelNameFromServiceName($service);

			return $this->factory
				->getA(
					$integrationModelName
				)->post(
					$request->getBody(),
					$options
				);
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return $helper->processException($e);
		}
	}

	/**
	 * Create a piece of data through a service API.
	 *
	 * @param Weeblr\Wblib\APi\Request $request
	 * @param array                    $options
	 *
	 * @return \Exception
	 */
	public function put($request, $options)
	{
		$service = Wb\arrayGet($options, 'service');
		$helper  = $this->factory->getA(Helper\Services::class);

		try
		{
			$valid   = $helper->validateService($service);
			if ($valid instanceof \Throwable)
			{
				return $valid;
			}

			$integrationModelName = 'Weeblr\Forseo\Model\Integrations\\'
									. $helper->providerFromServiceName($service)
									. '\\'
									. $helper->modelNameFromServiceName($service);

			return $this->factory
				->getA(
					$integrationModelName
				)->put(
					$request->getBody(),
					$options
				);
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return $helper->processException($e);
		}
	}
}
