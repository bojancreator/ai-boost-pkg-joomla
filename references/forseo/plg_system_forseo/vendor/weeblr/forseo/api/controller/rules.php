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
use Weeblr\Forseo\Model\Extensions;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Api;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Rules extends Api\Controller
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
				Model\Rules::class
			);
			if (Wb\arrayIsEmpty($options, 'id'))
			{
				// rules list
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
	 * Delete ore or more rules.
	 *
	 * @param Weeblr\Wblib\APi\Request $request
	 * @param array                    $options
	 *
	 * @return \Exception
	 */
	public function delete($request, $options)
	{
		try
		{
			$source = Wb\arrayGet(
				$options,
				'source'
			);

			if (!empty($source))
			{
				// safety: we only allow deleting by source for non-empty sources,
				// meaning only imported rules can be deleted that way.
				return $this->factory
					->getA(
						Model\Rules::class
					)->deleteImportedRules($source);

			}

			$ids = $request->getBody();
			if (empty($ids))
			{
				return new \Exception('No ids provided for delete operation.', System\Http::RETURN_BAD_REQUEST);
			}

			return $this->factory
				->getA(
					Model\Rules::class
				)->delete($ids);
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
					Model\Rules::class
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
	 * Create a single page.
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
			return array_merge(
				$this->factory
					->getA(
						Model\Rules::class
					)->create(
						$request->getBody()
					),
				['status' => System\Http::RETURN_CREATED]
			);
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return new \Exception('Internal error. See error log file.', System\Http::RETURN_INTERNAL_ERROR);
		}
	}

	/**
	 * Update a single rule.
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
					Model\Rules::class
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
	 * Perform a rule data import operation.
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
			return new \Exception('Invalid source configuration name trying to import aliases from sh404SEF: ' . print_r($from, true), System\Http::RETURN_NOT_FOUND);
		}

		try
		{
			return $this->factory
				->getA(Extensions\Sh404sef::class)
				->import(
					Extensions\Sh404sef::ALIASES,
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
