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

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Api;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;

use Weeblr\Forseo\Model;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Crawler extends Api\Controller
{
	/**
	 * Use model to fetch data about crawler state.
	 *
	 * @param Api\Request $request
	 * @param array       $options
	 *
	 * @return array
	 */
	public function get($request, $options)
	{
		return [
			'data'  => $this->factory
				->getA(Model\Crawler::class)
				->status(),
			'count' => 1,
			'total' => 1
		];
	}

	/**
	 * Use model to change crawler state.
	 *
	 * @param Api\Request $request
	 * @param array       $options
	 *
	 * @return array
	 */
	public function patch($request, $options)
	{
		$action = StringHelper::trim(
			$request->getBody()
		);

		return $this->execute(
			$action,
			$request,
			$options
		);
	}

	/**
	 * Crawl immediately the provided URLs.
	 * The URLs are provided as a JSON array of strings.
	 *
	 * @param Api\Request $request
	 * @param array       $options
	 * @return array
	 * @throws \Exception
	 */
	public function crawl($request, $options)
	{
		return $this->execute(
			'crawl',
			$request,
			$options
		);
	}

	/**
	 * Use model to change crawler state.
	 *
	 * @param Api\Request $request
	 * @param array       $options
	 *
	 * @return array
	 */
	public function delete($request, $options)
	{
		return [
			'status' => System\Http::RETURN_NO_CONTENT,
			'data'   => $this->factory
				->getA(Model\Crawler::class)
				->reset(),
			'count'  => 1,
			'total'  => 1
		];
	}

	/**
	 * Actually perform crawler state change.
	 *
	 * @param string      $action
	 * @param Api\Request $request
	 * @param array       $options
	 *
	 * @return array
	 */
	public function execute($action, $request, $options)
	{
		try
		{
			switch ($action)
			{
				case 'pause':
				case 'resume':
				case 'run':
					$data = $this->factory
						->getA(Model\Crawler::class)
						->{$action}(
							true
						);
					break;
				case 'crawl':
					$params = $request->getBody();

					if (empty($params))
					{
						throw new \Exception('No URL provided for immediate crawl', System\Http::RETURN_BAD_REQUEST);
					}

					$data = $this->factory
						->getA(Model\Crawler::class)
						->crawl(
							$params
						);

					break;
				default:
					throw new \Exception('Unknown crawler action', System\Http::RETURN_NOT_FOUND);
			}
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return $e;
		}

		return $data instanceof \Throwable
			? $data
			: [
				'data'  => $data,
				'count' => 1,
				'total' => 1
			];
	}
}
