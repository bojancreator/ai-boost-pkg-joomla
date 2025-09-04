<?php
/**
 * Project: 4SEF
 *
 * @package          4SEF
 * @copyright        Copyright Weeblr llc - 2022 -2025
 * @author           Yannick Gaultier - Weeblr llc
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          2.6.2.644
 * @date        2025-06-02
 */

namespace Weeblr\Forsef\Api\Controller;

use Weeblr\Forsef\Model\Extensions;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Api;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Config extends Api\Controller
{
	private $allowedConfigNames = [
		'routing',
		'extensions',
		'pagination',
		'sh404sef',
		'stats',
		'system'
	];

	/**
	 * Builds up an array of data for use in API response.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public function get($request, $options)
	{
		$configName = Wb\arrayGet($options, 'name');
		$valid      = $this->validateConfigName($configName);
		if ($valid instanceof \Throwable)
		{
			return $valid;
		}

		return [
			'data'  => $this->factory
				->getThis('forsef.config', $configName)
				->toArray(),
			'count' => 1,
			'total' => 1,
		];
	}

	/**
	 * Update configuration globally.
	 *
	 * @param $request
	 * @param $options
	 *
	 * @return void
	 */
	public function put($request, $options)
	{
		return new \Exception('Sorry this feature is not implemented yet.', System\Http::RETURN_NOT_FOUND);
	}

	/**
	 * Update a single key in a config.
	 *
	 * @param $request
	 * @param $options
	 *
	 * @return array|\Exception
	 */
	public function patch($request, $options)
	{
		$configName = Wb\arrayGet($options, 'name');
		$valid      = $this->validateConfigName($configName);
		if ($valid instanceof \Throwable)
		{
			return $valid;
		}

		$key = Wb\arrayGet($options, 'key', '');
		if (empty($key))
		{
			return new \Exception('Invalid configuration key used in API request - 1', System\Http::RETURN_NOT_FOUND);
		}

		$config = $this->factory->getThis('forsef.config', $configName);
		if (!$config->hasKey($key))
		{
			return new \Exception('Invalid configuration key used in API request - 2', System\Http::RETURN_NOT_FOUND);
		}

		try
		{
			$value = $request->getBody();
			$config->set(
				$key,
				$value
			)->store();
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forsef.logger')->error('%s::%d %s', $e->getFile(), $e->getLine(), $e->getMessage());

			return new \Exception(
				$e->getMessage(),
				$e->getCode()
			);
		}

		return [
			'data' => [
				$key => $config->get($key)
			]
		];
	}

	/**
	 * Check the requested config name is in allowed range.
	 *
	 * @param string $configName
	 *
	 * @return bool | \Exception
	 */
	private function validateConfigName($configName)
	{
		if (!in_array(
			$configName,
			$this->allowedConfigNames
		))
		{
			return new \Exception(
				'Invalid configuration name.',
				System\Http::RETURN_NOT_FOUND
			);
		}

		return true;
	}

	/**
	 * Import configuration from another extension.
	 *
	 * @param $request
	 * @param $options
	 *
	 * @return array|\Exception
	 */
	public function import($request, $options)
	{
		$from = Wb\arrayGet($options, 'from');
		if ('sh404sef' !== $from)
		{
			return new \Exception('Invalid source configuration name trying to import config: ' . print_r($from, true), System\Http::RETURN_NOT_FOUND);
		}

		try
		{
			return $this->factory
				->getA(Extensions\Sh404sef::class)
				->importConfiguration();
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forsef.logger')->error('%s::%d %s', $e->getFile(), $e->getLine(), $e->getMessage());

			return new \Exception(
				$e->getMessage(),
				$e->getCode()
			);
		}
	}
}
