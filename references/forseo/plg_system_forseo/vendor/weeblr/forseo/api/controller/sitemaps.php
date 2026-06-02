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
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;

use Weeblr\Forseo\Model;
use Weeblr\Forseo\Data;
use Weeblr\Forseo\Helper;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Sitemaps extends Api\Controller
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
				->getA(Model\Sitemapsstatus::class)
				->status($options),
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
	 * @throws \Exception
	 */
	public function delete($request, $options)
	{
		$type = Wb\arrayGet($options, 'type');
		if (
			is_null($type)
			||
			!in_array(
				$type,
				[
					Data\Sitemap::CONTENT,
					Data\Sitemap::NEWS,
					Data\Sitemap::IMAGES,
					Data\Sitemap::VIDEOS
				]
			)
		) {
			return new \Exception('Not found, invalid sitemap type.', 404);
		}

		$this->factory
			->getA(Model\Sitemaps::class)
			->deleteCurrentSitemapCachedFiles($type);

		$currentCrawlId = $this->factory->getThe('forseo.crawlerHelper')
			->getCompletedCrawlId();

		return [
			'status' => System\Http::RETURN_OK,
			'data'   => [
				'status' => empty($currentCrawlId)
					? Data\Sitemap::IN_PROGRESS
					: Data\Sitemap::READY,
				'url'    => $this->factory
					->getA(Helper\Sitemaps::class)
					->xmlUrl(
						$type
					)
			],
			'count'  => 1,
			'total'  => 1
		];
	}
}
