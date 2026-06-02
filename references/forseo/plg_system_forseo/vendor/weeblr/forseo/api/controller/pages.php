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
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;
use Weeblr\Forseo\Model;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Pages extends Api\Controller
{
	/**
	 * Builds up an array of data for use in API response.
	 *
	 * @param Weeblr\Wblib\APi\Request $request
	 * @param array                    $options
	 *
	 * @return void
	 */
	public function get($request, $options)
	{
		try
		{
			$model = $this->factory->getA(
				Model\Pages::class
			);

			if (Wb\arrayIsEmpty($options, 'id'))
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
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return new \Exception('Internal error. See error log file.', System\Http::RETURN_INTERNAL_ERROR);
		}
	}

	/**
	 * Delete ore or more pages.
	 *
	 * @param Weeblr\Wblib\APi\Request $request
	 * @param array                    $options
	 *
	 * @return \Exception|array
	 */
	public function delete($request, $options)
	{
		try
		{
			$ids = $request->getBody();
			if (empty($ids))
			{
				return new \Exception('No ids provided for delete operation.', System\Http::RETURN_BAD_REQUEST);
			}

			$deleted = $this->factory
				->getA(Model\Pages::class)
				->delete($ids);

			return $deleted instanceof \Throwable
				? $deleted
				: array_merge(
					$deleted,
					['status' => System\Http::RETURN_NO_CONTENT]
				);
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return new \Exception('Internal error. See error log file.', System\Http::RETURN_INTERNAL_ERROR);
		}
	}

	/**
	 * Update a single page.
	 *
	 * @param Weeblr\Wblib\APi\Request $request
	 * @param array                    $options
	 *
	 * @return \Exception
	 */
	public function put($request, $options)
	{
		try
		{
			$id = Wb\arrayGet($options, 'id');
			if (empty($id))
			{
				return new \Exception('No ids provided for put operation.', System\Http::RETURN_BAD_REQUEST);
			}

			return $this->factory
				->getA(
					Model\Pages::class
				)->save(
					$id,
					$request->getBody()
				);
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return new \Exception('Internal error. See error log file.', System\Http::RETURN_INTERNAL_ERROR);
		}
	}

	/**
	 * Update a single page.
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
				->getA(
					Model\Pages::class
				)->save(
					$id,
					$request->getBody()
				);
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return new \Exception('Internal error. See error log file.', System\Http::RETURN_INTERNAL_ERROR);
		}
	}
}
