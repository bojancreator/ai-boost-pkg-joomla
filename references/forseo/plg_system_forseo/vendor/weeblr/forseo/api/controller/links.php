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

class Links extends Api\Controller
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
				Model\Links::class
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
			$ids = $request->getBody();
			$model = $this->factory
				->getA(
					Model\Links::class
				);

			return empty($ids)
				? $model->deleteAll()
				: $model->delete($ids);

		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return new \Exception('Internal error. See error log file.', System\Http::RETURN_INTERNAL_ERROR);
		}
	}

	/**
	 * Update a single error.
	 *
	 * @param $request
	 * @param $options
	 *
	 * @return array|\Exception
	 */
	public function patch($request, $options)
	{
		return new \Exception('Sorry this feature is not implemented yet.', System\Http::RETURN_NOT_FOUND);
	}
}
