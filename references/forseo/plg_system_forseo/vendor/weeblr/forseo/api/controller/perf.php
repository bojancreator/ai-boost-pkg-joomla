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
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Api;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Perf extends Api\Controller
{

	/**
	 * Collect data from performance probe.
	 *
	 * @param Api\Request $request
	 * @param array       $options
	 *
	 * @return \Exception | array
	 */
	public function data($request, $options)
	{
		$perfData = $request->getBody();
		if (
			empty($perfData)
			||
			empty($perfData['ts'])
			||
			empty($perfData['FID'])
			||
			empty($perfData['INP'])
			||
			empty($perfData['LCP'])
		) {
			return [
				'status' => System\Http::RETURN_BAD_REQUEST
			];
		}

		$this->factory
			->getA(Model\Perf::class)
			->store(
				array_change_key_case(
					array_merge(
						$perfData,
						[
							'url'      => $options['u'],
							'full_url' => $options['f']
						]
					)
				)
			);


		return [
			'status' => System\Http::RETURN_NO_CONTENT
		];
	}

	/**
	 * Delete all recorded performance data.
	 *
	 * @param Weeblr\Wblib\APi\Request $request
	 * @param array                    $options
	 *
	 * @return array|\Exception
	 */
	public function reset($request, $options)
	{
		try
		{
			$this->factory
				->getA(
					Model\Perf::class
				)->reset();

			return [
				'status' => System\Http::RETURN_NO_CONTENT
			];
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return new \Exception('Internal error. See error log file.', System\Http::RETURN_INTERNAL_ERROR);
		}
	}
}

