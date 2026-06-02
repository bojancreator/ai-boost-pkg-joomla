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

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Api;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Config extends Api\Controller
{
	private $allowedConfigNames = [
		'system',
		'edit',
		'integrations',
		'insights',
		'sd',
		'sitemaps',
		'socialnetworks',
		'pages',
		'redirects',
		'replacer',
		'rules',
		'analytics',
		'extensions',
		'sh404sef'
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
				->getThis('forseo.config', $configName)
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
	 * @return array|\Exception
	 */
	public function put($request, $options)
	{
		$configName = Wb\arrayGet($options, 'name');
		$valid      = $this->validateConfigName($configName);
		if ($valid instanceof \Throwable)
		{
			return $valid;
		}

		$config = $this->factory->getThis('forseo.config', $configName);
		try
		{
			$values = $request->getBody();
			foreach ($values as $key => $value)
			{
				$config->set(
					$key,
					$value
				);
			}

			$config->store();
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s', $e->getFile(), $e->getLine(), $e->getMessage());

			return new \Exception('Full error message has been stored to the 4SEO log file on the server', 500);
		}

		return [
			'data' => $config->get()
		];
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

		$config = $this->factory->getThis('forseo.config', $configName);
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
			$this->factory->getThe('forseo.logger')->error('%s::%d %s', $e->getFile(), $e->getLine(), $e->getMessage());

			return new \Exception('Full error message has been stored to the 4SEO log file on the server', 500);
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
}
