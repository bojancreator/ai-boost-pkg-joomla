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

use Weeblr\Forsef\Model\Admin;
use Weeblr\Forsef\Model\Extensions;
use Weeblr\Forsef\Data;

use Weeblr\Wblib\Forsef\Api;
use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\System;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Urls extends Api\Controller
{
	/**
	 * Use model to load URL pairs.
	 *
	 * @param Api\Request $request
	 * @param array       $options
	 *
	 * @return array|\Exception
	 */
	public function get($request, $options)
	{
		try
		{
			$model = $this->factory->getA(
				Admin\Urls::class,
				Data\Urlpair::class
			);

			if (
				Wb\arrayIsEmpty($options, 'id')
				||
				!Wb\arrayIsEmpty($options, 'duplicates_only')
			)
			{
				// page list
				return $model->getList($options);
			}
			else
			{
				// single page
				return $model->get($options);
			}
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

	/**
	 * Delete ore or more pages.
	 *
	 * @param Weeblr\Wblib\Api\Request $request
	 * @param array                    $options
	 *
	 * @return \Exception|array
	 */
	public function delete($request, $options)
	{
		try
		{
			$deleted = $this->factory
				->getA(
					Admin\Urls::class,
					Data\Urlpair::class
				)->delete(
					$request->getBody(),
					$options
				);

			return $deleted instanceof \Throwable
				? $deleted
				: array_merge(
					$deleted,
					['status' => System\Http::RETURN_NO_CONTENT]
				);
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

	/**
	 * Create a single URL pair.
	 *
	 * @param Weeblr\Wblib\APi\Request $request
	 * @param array                    $options
	 *
	 * @return \Exception
	 */
	public function post($request, $options)
	{
		try
		{
			return $this->factory
				->getA(Data\Urlpair::class)
				->create(
					$request->getBody()
				);
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

	/**
	 * Update a single URL pair.
	 *
	 * @param $request
	 * @param $options
	 *
	 * @return array|\Exception
	 */
	public function patch($request, $options)
	{
		try
		{
			$id = Wb\arrayGet($options, 'id');
			if (empty($id))
			{
				return new \Exception('No ids provided for patch operation.', System\Http::RETURN_BAD_REQUEST);
			}

			return $this->factory
				->getA(Data\Urlpair::class)
				->modify(
					$id,
					$request->getBody(),
					$options
				);
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
			return new \Exception('Invalid source configuration name trying to import URLs: ' . print_r($from, true), System\Http::RETURN_NOT_FOUND);
		}

		try
		{
			return $this->factory
				->getA(Extensions\Sh404sef::class)
				->importUrls($options);
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
