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

namespace Weeblr\Forseo\Data;

use Weeblr\Forseo\Helper;
use Weeblr\Forseo\Controller;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Page extends Url
{
	/**
	 * Set of constants to describe sitemap operation mode
	 */
	public const AUTO = 0;
	public const USER = 1;

	/**
	 * Set of constants to describe canonical state.
	 */
	public const CANONICAL = 0;
	public const DUPLICATE = 1;

	/**
	 * Set of constants to describe inclusion/exclusion from sitemap.
	 */
	public const INCLUDED = 0;
	public const EXCLUDED = 1;

	/**
	 * Set of constants to describe performance test status. Denormalized from aggregate perf data.
	 */
	public const PERF_NO_DATA = 0;
	public const PERF_OK      = 1;
	public const PERF_FAILING = 2;

	/**
	 * @var string Database table associated with this instance.
	 */
	protected $table = '#__forseo_pages';

	/**
	 * @var array Holds data read/written to db. Also a specification of the columns.
	 */
	protected $defaults = [
		'id'              => 0,
		'status'          => Url::STATUS_OK,
		'perf_status'     => self::PERF_NO_DATA,
		'url'             => '',
		'full_url'        => '',
		'non_sef_vars'    => '',
		'input_vars'      => '',
		'query'           => '',
		'content_id'      => '',
		'full_content_id' => '',
		'lang'            => '',
		'page'            => '',
		'extension'       => '',
		'view'            => '',
		'layout'          => '',
		'item_id'         => '',
		'content_lang'    => '',
		'hash'            => '',
		'hash_links'      => '',
		'hash_images'     => '',
		'scheme'          => '',
		'host'            => '',
		'click_depth'     => Url::CLICK_DEPTH_NONE,
		'last_hit'        => null,
		'hits'            => 0,
		'canonical_mode'  => self::AUTO,
		'canonical_user'  => self::CANONICAL,
		'canonical_auto'  => self::CANONICAL,
		'sitemap_mode'    => self::AUTO,
		'sitemap_user'    => self::INCLUDED,
		'sitemap_auto'    => self::INCLUDED,
		'crawled_at'      => null,
		'modified_at'     => null,
		'enabled'         => Url::ENABLED,
		// not stored
		'ignore'          => false,
		'rawContent'      => '',
		'isMultiPage'     => false,
	];

	/**
	 * @var array List of types that should be enforced if present for properties.
	 */
	protected $dataTypes = [
		'id'             => System\Convert::INT,
		'page'           => System\Convert::INT,
		'hits'           => System\Convert::INT,
		'canonical_mode' => System\Convert::INT,
		'canonical_user' => System\Convert::INT,
		'canonical_auto' => System\Convert::INT,
		'sitemap_mode'   => System\Convert::INT,
		'sitemap_user'   => System\Convert::INT,
		'sitemap_auto'   => System\Convert::INT,
		'enabled'        => System\Convert::INT
	];

	/**
	 * @var array List of max length per column.
	 */
	protected $autotrimSpec = [
		'full_url'        => 2048,
		'content_id'      => 190,
		'full_content_id' => 2048,
		'lang'            => 50,
		'page'            => 50,
		'extension'       => 50,
		'view'            => 50,
		'layout'          => 50,
		'item_id'         => 190,
		'content_lang'    => 40,
		'hash'            => 40,
		'hash_links'      => 40,
		'hash_images'     => 40,
		'scheme'          => 40,
		'host'            => 100,
	];

	/**
	 * @var string[] List of data fields representing URLs, which needs to be converted to indexable, with the
	 *     corresponding indexable column name.
	 */
	protected $storageSafeColumns = [
		'full_url'        => 'url',
		'full_content_id' => 'content_id',
	];

	/**
	 * @var string[] List of columns and their id which can be searched.
	 */
	protected $searchableColumns = [
		'lang'      => 'lang',
		'url'       => 'url',
		'extension' => 'extension',
		'view'      => 'view',
		'id'        => 'item_id',
		'nonsef'    => 'input_vars',
		'type'      => 'content_id'
	];

	/**
	 * @var string[] List of columns and their id which can be ordered by.
	 */
	protected $orderableColumns = [
		'url',
		'extension',
		'lang'
	];

	/**
	 * @var array List of data key that should be ignored when storing to the DB.
	 */
	protected $dbIgnore = [
		'ignore',
		'rawContent',
		'isMultiPage'
	];

	/**
	 * @var Helper\Crawler A helper for crawler-related features.
	 */
	private $crawlerHelper = null;

	/**
	 * Load instance from db by searching for a given content_id.
	 *
	 * @param string $searchedFullContentId
	 * @param string $fullName
	 * @return mixed
	 * @throws \Exception
	 */
	public function loadPerContentId($searchedFullContentId, $fullName = 'full_content_id')
	{
		return empty($searchedFullContentId)
			? null
			: $this->loadStorageSafe(
				$searchedFullContentId,
				$fullName
			);
	}

	/**
	 * A chance to massage data before storing it.
	 *
	 * @param array $storeOptions
	 * @return bool
	 */
	public function beforeStore($storeOptions = [])
	{
		$pageHelper = $this->factory
			->getThe('forseo.pageHelper');

		$this->data['full_content_id'] = $this->encodeValue(
			'full_content_id',
			$pageHelper->contentId($this)
		);

		$this->data['modified_at'] = $this->encodeValue(
			'modified_at',
			$pageHelper->modifiedAt($this)
		);

		return true;
	}

	/**
	 * Hook to perform additional options after setting a value.
	 *
	 * @param string $key
	 * @param mixed  $newValue
	 * @param mixed  $previousValue
	 *
	 * @return Page
	 * @throws \Exception
	 */
	protected function afterSetKey($key, $newValue, $previousValue)
	{
		// if one of the canonical_values has changed:
		if (
			$newValue !== $previousValue
			&&
			in_array(
				$key,
				[
					'canonical_mode',
					'canonical_user',
				]
			))
		{
			// store the manually set value so that it's not lost when
			// pages analysis data is cleared
			$url = $this->get('url');
			$this->factory
				->getA(
					Canonicalincludes::class
				)->loadPerColumn(
					'url',
					$url
				)->set(
					[
						'url' => $url,
						$key  => $newValue,
					]
				)->store();

			// if a new canonical option has been selected, all other URLs with the same
			// content_id must be updated: both Pages and within forseo_canonical_includes

			$this->updatePagesAfterCanonicalChange($key, $newValue, $previousValue);
		}

		// if one of the sitemap_values has changed:
		if (
			$newValue !== $previousValue
			&&
			in_array(
				$key,
				[
					'sitemap_mode',
					'sitemap_user',
				]
			))
		{
			// store the manually set value so that it's not lost when
			// pages analysis data is cleared
			$url = $this->get('url');
			$this->factory
				->getA(
					Sitemapsincludes::class
				)->loadPerColumn(
					'url',
					$url
				)->set(
					[
						'url' => $url,
						$key  => $newValue,
					]
				)->store();
		}

		// if one of the sitemap or canonical values has changed:
		// and a crawl is not in progress
		if (
			$newValue !== $previousValue
			&&
			in_array(
				$key,
				[
					'canonical_mode',
					'canonical_user',
					'canonical_auto',
					'sitemap_mode',
					'sitemap_user',
					'sitemap_auto'
				]
			)
			&&
			!$this->getCrawlerHelper()->isCrawlInProgress()
		) {
			// mark sitemap as stale, so that a new one can be rebuilt.
			$this->factory
				->getA(Controller\Sitemap::class)
				->markOutDated();
		}

		return $this;
	}

	/**
	 * Get the app crawler helper instance when needed.
	 *
	 * @return Helper\Crawler
	 */
	private function getCrawlerHelper()
	{
		static $helper = null;

		if (is_null($helper))
		{
			$helper = $this->factory->getThe('forseo.crawlerHelper');
		}

		return $helper;
	}

	/**
	 * Fix other URLs when the canonical setting is changed by user for this page from the UI.
	 *
	 * @param string $key
	 * @param mixed  $newValue
	 * @param mixed  $previousValue
	 *
	 * @return Page
	 * @throws \Exception
	 */
	private function updatePagesAfterCanonicalChange($key, $newValue, $previousValue)
	{
		$contentId = $this->get('content_id');
		if (empty($contentId))
		{
			return $this;
		}

		$url = $this->get('url');
		if ('canonical_user' === $key && self::CANONICAL === $newValue)
		{
			// This URL has been set to USER:Canonical, all other USER CANONICAL URLs become AUTO

			// update the Pages table
			$this->db->update(
				'#__forseo_pages',
				[
					'canonical_mode' => self::AUTO
				],
				[
					'canonical_mode' => self::USER,
					'canonical_user' => self::CANONICAL,
					'content_id'     => $contentId,
					['url', '!=', $url]
				]
			);

			// update the canonical_includes table
			$this->db->update(
				'#__forseo_canonical_includes',
				[
					'canonical_mode' => self::AUTO
				],
				[
					'canonical_mode' => self::USER,
					'canonical_user' => self::CANONICAL,
					['url', '!=', $url]
				]
			);
		}

		return $this;
	}

	/**
	 * Optionally encode a value before it's stored in the data object.
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return mixed
	 */
	public function encodeValue($key, $value)
	{
		switch ($key)
		{
			case 'status':
				$value = (int)$value;
				if (Url::STATUS_OK === $value)
				{
					break;
				}
				// HTTP status
				if (System\Http::isSuccess($value))
				{
					// success
					$value = Url::STATUS_OK;
				}
				break;
			case 'extension':
				$value = Wb\lTrim($value, 'com_');
				break;
			case 'non_sef_vars':
			case 'input_vars':
			case 'query':
				// alpha-sort for reproducibility
				if (is_array($value))
				{
					ksort($value);
				}
				else
				{
					$value = [];
				}
				$value = json_encode($value);
				break;
			case 'item_id':
				$value = json_encode($value);
				break;
			case 'last_hit':
				$this->timestamp('last_hit');
				break;
		}

		return parent::encodeValue($key, $value);
	}

	/**
	 * Optionally decode a value before it's returned from the data object.
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return mixed
	 */
	public function decodeValue($key, $value)
	{
		switch ($key)
		{
			case 'non_sef_vars':
			case 'input_vars':
			case 'query':
			case 'item_id':
				$value = json_decode($value, true);
				break;
		}

		return parent::decodeValue($key, $value);
	}
}

