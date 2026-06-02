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
use Weeblr\Forseo\Data;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Api;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Aliases extends Api\Controller
{
	/**
	 * Get aliases list, possibly filtered by options.
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
			return $this->factory
				->getA(Model\Aliases::class)
				->getList($options);
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return new \Exception('Internal error. See error log file.', System\Http::RETURN_INTERNAL_ERROR);
		}
	}

	/**
	 * Modify aliases list for a URL.
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
			$id = Wb\arrayGetInt($options, 'id');
			if (!empty($id))
			{
				$aliasRecord = $this->factory
					->getA(Data\Alias::class)
					->load($id);

				if (!$aliasRecord->exists())
				{
					return new \Exception('Cannot find alias record with this id.', System\Http::RETURN_NOT_FOUND);
				}

				$parsed = $request->getBody();
				if (empty($parsed))
				{
					return new \Exception('Invalid new alias JSON data.', System\Http::RETURN_BAD_REQUEST);
				}

				$aliasRecord->set(
					$parsed
				)->store();

				return [
					'data' => [
						'enabled' => $aliasRecord->get(
							'enabled'
						)
					]
				];
			}

			if (empty($id))
			{
				return $this->factory
					->getA(
						Model\Aliases::class
					)->saveFromInput(
						$request->getBody()
					);
			}
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return $e;
		}
	}

	/**
	 * Delete ore or more links.
	 *
	 * @param Weeblr\Wblib\APi\Request $request
	 * @param array                    $options
	 *
	 * @return array|\Exception
	 */
	public function delete($request, $options)
	{
		try
		{
			$ids   = $request->getBody();
			$model = $this->factory
				->getA(
					Model\Aliases::class
				);

			return $model->delete($ids);

		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return new \Exception('Internal error. See error log file.', System\Http::RETURN_INTERNAL_ERROR);
		}
	}
}
