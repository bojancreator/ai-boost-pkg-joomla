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

use Weeblr\Forseo\Data;
use Weeblr\Forseo\Model\Extensions;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Api;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;


// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Meta extends Api\Controller
{
	/**
	 * Get the meta data associated with a URL.
	 *
	 * @param          $request
	 * @param array    $options
	 *
	 * @return array | \Exception
	 */
	public function get($request, $options)
	{
		$url = StringHelper::trim(
			Wb\arrayGet($options, 'url')
		);

		if (empty($url))
		{
			return new \Exception('No URL provided to fetch meta data for.', System\Http::RETURN_BAD_REQUEST);
		}

		try
		{
			$metaRecord = $this->factory
				->getA(Data\Meta::class)
				->loadPerUrl($url);

			if (!$metaRecord->exists())
			{
				return new \Exception('Cannot find meta data for this URL.', System\Http::RETURN_NOT_FOUND);
			}

			return [
				'data'  => $metaRecord->get(),
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

	/**
	 * Set a new meta data object for a URL.
	 *
	 * @param $request
	 * @param $options
	 *
	 * @return \Exception
	 */
	public function put($request, $options)
	{
		return new \Exception('Sorry this feature is not implemented yet.', System\Http::RETURN_NOT_FOUND);
	}

	/**
	 * Delete meta data for a URL.
	 *
	 * @param $request
	 * @param $options
	 *
	 * @return \Exception
	 */
	public function delete($request, $options)
	{
		return new \Exception('Sorry this feature is not implemented yet.', System\Http::RETURN_NOT_FOUND);
	}

	/**
	 * Update a single key in the meta data associated with a URL.
	 *
	 * @param $request
	 * @param $options
	 *
	 * @return array|\Exception
	 */
	public function patch($request, $options)
	{
		$url = StringHelper::trim(
			Wb\arrayGet($options, 'url')
		);

		if (empty($url))
		{
			return new \Exception('No URL provided to patch meta data for.', System\Http::RETURN_BAD_REQUEST);
		}

		$key = StringHelper::trim(
			Wb\arrayGet($options, 'key')
		);

		if (empty($key))
		{
			return new \Exception('Invalid meta data key used in API request.', System\Http::RETURN_BAD_REQUEST);
		}

		try
		{
			$metaRecord = $this->factory
				->getA(Data\Meta::class)
				->loadPerUrl($url);

			if (!$metaRecord->exists())
			{
				return new \Exception('Cannot find meta data for this URL.', System\Http::RETURN_NOT_FOUND);
			}

			$value = $request->getBody();
			$metaRecord->set(
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
				$key => $metaRecord->get(
					$key
				)
			]
		];
	}

	/**
	 * Update a single key in the meta data associated with a URL.
	 *
	 * @param $request
	 * @param $options
	 *
	 * @return array|\Exception
	 */
	public function patchData($request, $options)
	{
		$id = (int)Wb\arrayGet($options, 'id');

		if (empty($id))
		{
			return new \Exception('No URL provided to patch meta data for.', System\Http::RETURN_BAD_REQUEST);
		}

		$item = StringHelper::trim(
			Wb\arrayGet($options, 'item')
		);

		if (empty($item))
		{
			return new \Exception('Invalid meta data key used in API request.', System\Http::RETURN_BAD_REQUEST);
		}

		try
		{
			$metaRecord = $this->factory
				->getA(Data\Meta::class)
				->load($id);

			if (!$metaRecord->exists())
			{
				return new \Exception('Cannot find meta data for this URL.', System\Http::RETURN_NOT_FOUND);
			}

			$metaRecord
				->updateDataField(
					$item,
					$request->getBody()
				)->store();
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s', $e->getFile(), $e->getLine(), $e->getMessage());

			return new \Exception('Full error message has been stored to the 4SEO log file on the server', 500);
		}

		return [
			'data' => [
				$item => Wb\arrayGet(
					$metaRecord->getMeta(),
					$item
				)
			]
		];
	}

	/**
	 * Perform a meta data import operation.
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
			return new \Exception('Invalid source configuration name trying to import meta data from sh404SEF: ' . print_r($from, true), System\Http::RETURN_NOT_FOUND);
		}

		try
		{
			return $this->factory
				->getA(Extensions\Sh404sef::class)
				->import(
					Data\Meta::ID,
					$options
				);
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s', $e->getFile(), $e->getLine(), $e->getMessage());

			return new \Exception(
				$e->getMessage(),
				$e->getCode()
			);
		}
	}
}
