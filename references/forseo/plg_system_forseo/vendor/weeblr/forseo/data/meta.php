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

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Db;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Meta extends Db\Dataobject
{
	public const ID = 'metas';

	public const DISABLED = 0;
	public const ENABLED  = 1;

	/**
	 * Set of constants to describe inclusion/exclusion from sitemap.
	 */
	public const AUTO     = 0;
	public const PLATFORM = 1;
	public const CUSTOM   = 2;
	public const NONE     = 3;

	/**
	 * Set of constants to describe where the custom meta originates from.
	 */
	public const SOURCE_USER            = 0;
	public const SOURCE_IMPORT_SH404SEF = 100;

	/**
	 * Default recommended length for auto-generated descriptions.
	 */
	public const META_DESC_RECOMMENDED_LENGTH = 160;

	/**
	 * @var string[] do not change ordering, it matters
	 */
	private $metaTypesPriorities = ['custom', 'platform', 'auto'];

	/**
	 * @var Helper\Meta Convenience instance of meta data helper;
	 */
	protected $metaHelper = null;

	/**
	 * @var string Database table associated with this instance.
	 */
	protected $table = '#__forseo_custom_meta';

	/**
	 * @var string[] List of data fields representing URLs, which needs to be converted to indexable, with the
	 *     corresponding indexable column name.
	 */
	protected $storageSafeColumns = [
		'url' => 'url',
	];

	/**
	 * @var array Holds data read/written to db. Also a specification of the columns.
	 */
	protected $defaults = [
		'id'                 => 0,
		'source'             => self::SOURCE_USER,
		'content_id'         => '',
		'url'                => '',
		'data'               => '',
		'status_title'       => self::AUTO,
		'status_description' => self::AUTO,
		'hash_title'         => '',
		'hash_description'   => '',
		'crawled_at'         => null,
		'enabled'            => self::ENABLED,
	];

	/**
	 * @var array List of types that should be enforced if present for properties.
	 */
	protected $dataTypes = [
		'id'                 => System\Convert::INT,
		'status_title'       => System\Convert::INT,
		'status_description' => System\Convert::INT,
		'redirects_count'    => System\Convert::INT,
		'enabled'            => System\Convert::INT,
	];

	/**
	 * @var array Holds data read/written to db. Also a specification of the columns.
	 */
	protected $metaDefaults = [
		'crawled_at'              => null,
		'enabled'                 => 1,
		'useTitle'                => self::AUTO,
		'useCanonical'            => self::AUTO,
		'useDescription'          => self::AUTO,
		'useRobots'               => self::AUTO,
		'useImage'                => self::AUTO,
		'platform'                => [
			'title'         => '',
			'description'   => '',
			'robots'        => '',
			'sharing_image' => '',
			'image'         => '',
			'canonical'     => '',
		],
		'meta_hash_platform'      => '',
		'auto'                    => [
			'title'         => '',
			'description'   => '',
			'robots'        => '',
			'sharing_image' => '',
			'image'         => '',
			'canonical'     => '',
		],
		'meta_hash_auto'          => '',
		'custom'                  => [
			'title'              => '',
			'title_ogp'          => '',
			'title_tcards'       => '',
			'description'        => '',
			'description_ogp'    => '',
			'description_tcards' => '',
			'robots'             => '',
			'sharing_image'      => '',
			'image'              => '',
			'canonical'          => '',
		],
		'meta_hash_custom'        => '',
		'raw_content_head_top'    => '',
		'raw_content_head_bottom' => '',
		'raw_content_body_top'    => '',
		'raw_content_body_bottom' => '',
	];

	/**
	 * @var array List of max length per column.
	 */
	protected $autotrimSpec = [
		'raw_content_head_top'    => 2048,
		'raw_content_head_bottom' => 2048,
		'raw_content_body_top'    => 2048,
		'raw_content_body_bottom' => 2048,
	];

	/**
	 * @var array List of max length per column.
	 */
	protected $metaAutotrimSpec = [
		'title'         => 255,
		'description'   => 512,
		'robots'        => 40,
		'image'         => 2048,
		'sharing_image' => 2048,
	];

	/**
	 * Associate this instance to a database table.
	 *
	 * @param string $table
	 *
	 * @throws \Exception
	 */
	public function __construct($table = '')
	{
		parent::__construct($table);

		$this->metaHelper = $this->factory->getA(Helper\Meta::class);
	}

	/**
	 * Load instance from db by searching for a given URL.
	 * The provided URL is first processed to be "indexable", ie shortened
	 * as needed and match the format of the indexable database field.
	 *
	 * @param string $searchedFullUrl
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function loadPerUrl($searchedFullUrl)
	{
		$indexableUrl = $this->helper
			->storageSafe(
				$searchedFullUrl
			);

		return $this->loadPerColumn(
			'url',
			$indexableUrl,
			[], // $whereData
			[
				'id' => 'DESC'
			]   // $orderBy
		);
	}

	/**
	 * Shortcut to get the actual meta data.
	 *
	 * @return array
	 */
	public function getMeta()
	{
		return $this->decodeValue(
			'data',
			$this->data['data']
		);
	}

	/**
	 * Update the data stored in the "data" field when a key/pair stored
	 * at top level is modified.
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return Meta
	 * @throws \Exception
	 */
	public function updateDataField($key, $value)
	{
		$dataField = json_decode(
			$this->data['data'],
			true
		);

		$dataField[$key]    = $value;
		$this->data['data'] = $this->encodeValue(
			'data',
			$dataField
		);

		if ('custom' == $key)
		{
			// changing some custom values may require a sitemap update.
			// we put that page up for recrawl, which in turn will update
			// sitemap as needed
			if (!$this->factory->getThe('forseo.crawlerHelper')->isCrawlerRequest())
			{
				$page = $this->factory
					->getA(Page::class)
					->loadPerColumn(
						'url',
						$this->get('url')
					);

				$this->factory
					->getThe('forseo.linksCollectorHelper')
					->storeCollectedLinks(
						[
							$page->get('full_url')
						],
						$page,
						[
							'forceCollection' => true
						]
					);
			}
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
	 * @throws \Exception
	 */
	public function encodeValue($key, $value)
	{
		switch ($key)
		{
			case 'enabled':
			case 'crawled_at':
				$this->updateDataField(
					$key,
					$value
				);
				break;
			case 'data':
				// merge with default data so that new fields are added after updates/ changes in data structure
				$value = array_merge(
					$this->metaDefaults,
					$value
				);

				// copy over the id
				$value['id'] = $this->data['id'];

				// apply autotrim
				foreach ($this->metaTypesPriorities as $metaType)
				{
					foreach ($this->metaAutotrimSpec as $property => $maxLength)
					{
						$value[$metaType][$property] = $this->autotrim(
							$property,
							$value[$metaType][$property]
						);
					}

					// compute meta hash to easily identify changes
					$value['meta_hash_' . $metaType] = $this->metaHelper
						->hashMeta(
							$value[$metaType]
						);
				}

				// merge with defaults to ensure completeness, especially after updates
				$value = array_merge(
					$this->metaDefaults,
					$value
				);

				// Compute hash of CURRENT title and description
				$this->updateMetaHash($value, 'title');
				$this->updateMetaHash($value, 'description');

				// encode to json for storage
				$value = json_encode(
					$value
				);

				break;
		}

		return parent::encodeValue($key, $value);
	}

	/**
	 * Update the hash of a given meta data value, to be stored at top level
	 * for easy access.
	 *
	 * @param array  $value
	 * @param string $metaName
	 */
	private function updateMetaHash($value, $metaName)
	{
		foreach ($this->metaTypesPriorities as $metaType)
		{
			$metaValue = Wb\arrayGet($value, [$metaType, $metaName], '');
			if (!empty($metaValue))
			{
				$this->data['status_' . $metaName] = $this->metaHelper->typeCodeFromTypeName($metaType);
				$this->data['hash_' . $metaName]   = md5($metaValue);
				break;
			}
		}
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
			case 'data':
				$value = array_merge(
					$this->metaDefaults,
					[
						'id' => Wb\arrayget($this->data, 'id', 0)
					],
					json_decode(
						Wb\initEmpty($value, '[]'),
						true
					)
				);
				break;
		}

		return parent::decodeValue($key, $value);
	}

	/**
	 * Store a UTC datetime into the designated data field.
	 *
	 * @param string $key
	 * @param bool   $update If true, a new timestamp is created, else the request timestamp is used.
	 *
	 * @return Meta
	 * @throws \Exception
	 */
	public function timestamp($key, $update = false)
	{
		parent::timestamp(
			$key,
			$update
		);

		$this->updateDataField(
			$key,
			$this->data[$key]
		);

		return $this;
	}

	/**
	 * Get the array of default values for a rule.
	 *
	 * @return array
	 */
	public function metaDefaults()
	{
		return $this->metaDefaults;
	}
}
