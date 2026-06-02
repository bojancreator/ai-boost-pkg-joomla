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

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Db;
use Weeblr\Wblib\Forseo\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Collected extends Url
{
	public const PRIORITY_IMMEDIATE = 127;
	public const PRIORITY_NORMAL    = 0;
	public const PRIORITY_LOW       = -10;
	/**
	 * @var string Database table associated with this instance.
	 */
	protected $table = '#__forseo_collected_urls';

	protected $defaults = [
		'id'               => 0,
		'crawled_by'       => '',
		'crawl_started_at' => null,
		'crawl_timeout_at' => null,
		'status'           => Url::STATUS_OK,
		'target'           => Url::TARGET_INTERNAL,
		'url'              => '',
		'full_url'         => '',
		'referrers'        => '',
		'click_depth'      => Url::CLICK_DEPTH_NONE,
		'attempts'         => 0,
		'priority'         => self::PRIORITY_NORMAL
	];

	/**
	 * @var array List of max length per column.
	 */
	protected $autotrimSpec = [
		'full_url' => 2048,
	];

	/**
	 * @var int Max number of referrers to store for the collected URL.
	 */
	private $maxReferrers = 10;

	/**
	 * @var string A string to concatenate multiple referrers for the same URL.
	 */
	private $referrerMarker = '::::';

	/**
	 * Associate this instance to a database table.
	 *
	 * @param string $table
	 *
	 * @throws \Exception
	 */
	public function __construct($table = '')
	{
		parent::__construct();
		$this->maxReferrers = $this->factory
			->getThis('forseo.config', 'app')
			->get('crawlerMaxReferrersStored');
	}

	/**
	 * Before storing any URL to be crawled, check that we have not
	 * seen it before and discarded it.
	 *
	 * @param array $storeOptions
	 * @return bool
	 */
	public function beforeStore($storeOptions = [])
	{
		$found = $this->factory
			->getA(Excluded::class)
			->loadPerUrl(
				$this->get('full_url')
			)->exists();

		return !$found;
	}

	/**
	 * Adds a single referrer to the referrers array.
	 *
	 * @param string|array $referrers
	 *
	 * @return Collected
	 */
	public function addReferrers($referrers)
	{
		$this->setKey(
			'referrers',
			array_unique(
				Wb\arrayMerge(
					$this->get('referrers'),
					$referrers
				)
			)
		);

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
			case 'full_url':

				// update whether this is an internal URL
				$this->data['target'] = System\Route::isInternal($value)
					? Url::TARGET_INTERNAL
					: Url::TARGET_EXTERNAL;
				break;

			case 'referrers':
				$value = Wb\arrayEnsure($value);
				array_walk(
					$value,
					function (&$v)
					{
						// referrer must be passed as HTTP header, sometimes
						// HTTP clients just drop empty headers so better off
						// standardizing on using / for home page referrers;
						$v = Wb\initEmpty(
							$v,
							'/'
						);
					}
				);

				$value = implode(
					$this->referrerMarker,
					$value
				);

				while (
					strpos($value, $this->referrerMarker) !== false
					&&
					mb_strlen($value) > $this->maxReferrers)
				{
					// remove the last string(s) until we are below the threshold
					$lastPos = strrpos($value, $this->referrerMarker);
					if (!empty($lastPos))
					{
						$value = substr($value, 0, $lastPos);
					}
				}
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
			case 'referrers':
				$value = empty($value)
					? []
					: explode(
						$this->referrerMarker,
						$value
					);

				break;
		}

		return parent::decodeValue($key, $value);
	}
}
