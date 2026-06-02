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

namespace Weeblr\Forseo\Model;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Db;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;

use Weeblr\Forseo\Data;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Pages extends Db\Dataobjectlist
{
	/**
	 * @var string Name of the ordering column if none specified.
	 */
	protected $defaultOrderBy = 'url';

	/**
	 * Store information about managed data.
	 *
	 * @param string $dataObjectClass
	 */
	public function __construct($dataObjectClass = null)
	{
		$this->dataObjectClass = Data\Page::class;

		parent::__construct();

		$this->defaultItemsPerPage = $this->factory
			->getThis(
				'forseo.config',
				'system'
			)->getInt(
				'defaultItemsPerPage',
				10
			);
	}

	/**
	 * Delete one or more pages.
	 *
	 * @param array $keys
	 *
	 * @return array|\Exception
	 */
	public function delete($keys)
	{
		try
		{
			// identify pages URLs
			$keys      = Wb\arrayEnsure($keys);
			$pagesUrls = $this->dbHelper->selectColumn(
				'#__forseo_pages',
				'url',
				$this->dbHelper->quoteName($this->keyName) . ' in (' . $this->dbHelper->arrayToQuotedList($keys) . ')'
			);

			// Delete pages records. We do keep custom meta data records though.
			$this->dbHelper->deleteIn(
				'#__forseo_pages',
				$this->keyName,
				$keys,
				Db\Helper::INTEGER
			);

			// delete referrers
			$this->factory
				->getA(Referrers::class)
				->purgeReferrersByUrls(
					$pagesUrls
				);
		}
		catch (\Throwable $e)
		{
			return new \Exception($e->getMessage(), System\Http::RETURN_NOT_FOUND);
		}

		return [
			'data'  => null,
			'count' => count($keys),
			'total' => count($keys),
		];
	}

	/**
	 * Hook to post-process list of data read.
	 *
	 * @param mixed $data
	 * @param array $options
	 *
	 * @return mixed
	 */
	protected function afterGet($data, array $options)
	{
		$data = parent::afterGet($data, $options);

		$withMeta = (int)Wb\arrayGet(
			$options,
			'with_meta',
			0
		);

		$withAliases = (int)Wb\arrayGet(
			$options,
			'with_aliases',
			0
		);

		$withPerf = (int)Wb\arrayGet(
			$options,
			'with_perf',
			0
		);

		if (
			empty($withMeta)
			&&
			empty($withPerf)
			&&
			empty($withAliases)
		) {
			return $data;
		}

		// insert additonal data in each URL record.
		if (Wb\arrayIsEmpty($data, 'data'))
		{
			return $data;
		}

		if (!empty($withMeta))
		{
			$dataWithMeta = $this->insertMetaDataInPagesList(
				[$data['data']]
			);
			$data['data'] = empty($dataWithMeta)
				? $data['data']
				: array_shift($dataWithMeta);
		}

		if (!empty($withAliases))
		{
			$dataWithAliases = $this->insertAliasesInPagesList(
				[$data['data']]
			);
			$data['data']    = empty($dataWithAliases)
				? $data['data']
				: array_shift($dataWithAliases);
		}

		if (!empty($withPerf))
		{
			$dataWithPerf = $this->insertPerfDataInPagesList(
				[$data['data']]
			);
			$data['data'] = empty($dataWithPerf)
				? $data['data']
				: array_shift($dataWithPerf);
		}

		return $data;
	}

	/**
	 * Hook to post-process list of data read.
	 *
	 * @param mixed $data
	 * @param array $options
	 *
	 * @return mixed
	 */
	protected function afterGetList($data, array $options)
	{
		$data = parent::afterGetList($data, $options);

		// inject pending urls count
		$meta             = Wb\arrayGet($data, 'meta');
		$pendingUrlsCount = $this->dbHelper->count('#__forseo_collected_urls');
		$data             = Wb\arraySet(
			$data,
			'meta',
			array_merge(
				$meta,
				[
					'pending' => $pendingUrlsCount
				]
			)
		);

		// decide whether meta data for each page should be included
		$withMeta = (int)Wb\arrayGet(
			$options,
			'with_meta',
			0
		);

		$withAliases = (int)Wb\arrayGet(
			$options,
			'with_aliases',
			0
		);

		$withPerf = (int)Wb\arrayGet(
			$options,
			'with_perf',
			0
		);

		if (
			empty($withMeta)
			&&
			empty($withAliases)
			&&
			empty($withPerf)
		) {
			return $data;
		}

		// insert meta data in each URL record.
		$pages = Wb\arrayGet($data, 'data');
		if (empty($pages))
		{
			return $data;
		}

		if (!empty($withMeta))
		{
			$pages = $this->insertMetaDataInPagesList($pages);
		}

		if (!empty($withAliases))
		{
			$pages = $this->insertAliasesInPagesList($pages);
		}

		if (!empty($withPerf))
		{
			$pages = $this->insertPerfDataInPagesList($pages);
		}

		return Wb\arraySet($data, 'data', $pages);
	}

	/**
	 * Inject associated meta data to a list of URLs records.
	 *
	 * @param array $pages
	 *
	 * @return array
	 */
	private function insertMetaDataInPagesList($pages)
	{
		if (empty($pages))
		{
			return [];
		}

		$urls = array_map(
			function ($item)
			{
				return Wb\arrayGet($item, 'url');
			},
			$pages
		);

		$metaData = $this->dbHelper
			->selectAssocList(
				'#__forseo_custom_meta',
				'*',
				$this->dbHelper->quoteName('url') . ' in (' . $this->dbHelper->arrayToQuotedList($urls) . ')',
				[],
				[],
				0,
				0,
				'url' // $key
			);

		$pages = array_map(
			function ($item) use ($metaData)
			{
				$url               = Wb\arrayGet($item, 'url', '');
				$metaDataItem      = Wb\arrayGet($metaData, $url, []);
				$item['_metadata'] = Wb\arrayGet(
					$metaDataItem,
					'data',
					'{}'
				);

				if (!is_array($item['_metadata']))
				{
					$item['_metadata'] = json_decode(
						$item['_metadata'],
						true
					);
				}

				$item['_metadata']['id']  = Wb\arrayGet($metaDataItem, 'id', 0);
				$item['hash_title']       = Wb\arrayGet($metaDataItem, 'hash_title', '');
				$item['hash_description'] = Wb\arrayGet($metaDataItem, 'hash_description', '');

				// v 4.4 Added custom social meta data. We must manually defaults for new values for pre-existing records.
				// Not very nice, but still faster to read direct from DB with an SQL query than use the dedicated Data\* classes
				$item['_metadata']['custom']['title_ogp']          = Wb\arrayGet($item, ['_metadata', 'custom', 'title_ogp'], '');
				$item['_metadata']['custom']['title_tcards']       = Wb\arrayGet($item, ['_metadata', 'custom', 'title_tcards'], '');
				$item['_metadata']['custom']['description_ogp']    = Wb\arrayGet($item, ['_metadata', 'custom', 'description_ogp'], '');
				$item['_metadata']['custom']['description_tcards'] = Wb\arrayGet($item, ['_metadata', 'custom', 'description_tcards'], '');

				return $item;
			},
			$pages
		);

		$hashNames = [
			'title',
			'description'
		];

		$hashesCounts = [];
		foreach ($hashNames as $hashName)
		{
			$hashes = array_map(
				function ($item) use ($hashName)
				{
					return Wb\arrayGet($item, 'hash_' . $hashName);
				},
				$pages
			);

			// count occurences of each hashes
			$query = ' select min(' . $this->dbHelper->quotename('meta_sub.hash_' . $hashName) . ') as hash, count(' . $this->dbHelper->quotename('meta_sub.id') . ') as duplicates'
					 . ' from ' . $this->dbHelper->quotename('#__forseo_custom_meta') . ' as meta_sub'
					 . ' join ' . $this->dbHelper->quotename('#__forseo_pages') . ' as pages_sub on ' . $this->dbHelper->quoteName('meta_sub.url') . '=' . $this->dbHelper->quoteName('pages_sub.url')
					 . ' where ' . $this->dbHelper->quoteName('meta_sub.hash_' . $hashName) . ' in (' . $this->dbHelper->arrayToQuotedList($hashes) . ')'
					 . ' group by ' . $this->dbHelper->quotename('hash_' . $hashName)
					 . ' having (duplicates > 1)';

			$hashesCounts[$hashName] = $this->dbHelper
				->setQueryAnd($query)
				->loadAssocList('hash');

		}

		// merge into data array
		return array_map(
			function ($item) use ($hashesCounts)
			{

				$item['duplicates_title']       = Wb\arrayGet($hashesCounts['title'], [$item['hash_title'], 'duplicates'], 0);
				$item['duplicates_description'] = Wb\arrayGet($hashesCounts['description'], [$item['hash_description'], 'duplicates'], 0);

				return $item;
			},
			$pages
		);
	}

	/**
	 * Inject associated aliases data to a list of URLs records.
	 *
	 * @param array $pages
	 *
	 * @return array
	 */
	private function insertAliasesInPagesList($pages)
	{
		if (empty($pages))
		{
			return [];
		}

		$urls = array_map(
			function ($item)
			{
				return Wb\arrayGet($item, 'url');
			},
			$pages
		);

		$aliasesData = $this->dbHelper
			->selectAssocList(
				'#__forseo_pages_aliases',
				[
					'url',
					'alias'
				],
				$this->dbHelper->quoteName('url') . ' in (' . $this->dbHelper->arrayToQuotedList($urls) . ')',
				[], // whereData
				[
					'alias' => 'asc'
				]
			);

		return array_map(
			function ($item) use ($aliasesData)
			{
				$aliasList = [];
				$url       = Wb\arrayGet($item, 'url', '');
				foreach ($aliasesData as $aliasRecord)
				{
					if (Wb\arrayGet($aliasRecord, 'url') === $url)
					{
						$aliasList[] = Wb\arrayGet($aliasRecord, 'alias');
					}
				}
				$item['_aliases'] = implode(
					"\n",
					$aliasList
				);

				return $item;
			},
			$pages
		);
	}

	/**
	 * Inject associated performance data to a list of URLs records.
	 *
	 * @param array $pages
	 *
	 * @return array
	 */
	private function insertPerfDataInPagesList($pages)
	{
		if (empty($pages))
		{
			return [];
		}

		$urls = array_map(
			function ($item)
			{
				return Wb\arrayGet($item, 'url');
			},
			$pages
		);

		$perfData = $this->dbHelper
			->selectAssocList(
				'#__forseo_perf_data_agg',
				'*',
				$this->dbHelper->quoteName('url') . ' in (' . $this->dbHelper->arrayToQuotedList($urls) . ')',
				[],
				[],
				0,
				0,
				$key = 'url'
			);

		$pages = array_map(
			function ($item) use ($perfData)
			{
				$url               = Wb\arrayGet($item, 'url', '');
				$item['_perfData'] = Wb\arrayGet($perfData, $url, []);

				return $item;
			},
			$pages
		);

		// merge into data array
		return $pages;
	}

	/**
	 * Count the total number of items from the query, without pagination.
	 *
	 * @param array $options
	 * @param array $whereClause
	 *
	 * @return array
	 * @throws \Exception
	 */
	protected function queryTotal($options, $whereClause)
	{
		$totalQuery = $this->runQuery(
			array_merge(
				$options,
				[
					'count_only' => true
				]
			),
			$whereClause,
			[
				'perPage' => 0,
				'page'    => 0,
				'offset'  => 0,
				'maxPage' => 0,
				'orderBy' => ''
			]
		);

		return Wb\arrayGet(
			$totalQuery,
			'count',
			0
		);
	}

	/**
	 * Actually run the database query based on all options and parameters
	 * computed for the request.
	 *
	 * @param array $options
	 * @param array $whereClause
	 * @param array $queryParams
	 * @param bool  $indexOnKey
	 *
	 * @return array
	 * @throws \Exception
	 */
	protected function runQuery($options, $whereClause, $queryParams, $indexOnKey = false)
	{
		$countOnly = Wb\arrayGet($options, 'count_only', false);
		$count     = 0;

		if ($countOnly)
		{
			$count = $this->dbHelper
				->count(
					$this->table,
					'*',
					$whereClause['clause'],
					$whereClause['whereParams']
				);
		}

		if (!$countOnly)
		{
			$filterSort = Wb\arrayGet(
				$options,
				'filter_sort',
				''
			);

			if ('item' == $filterSort)
			{
				$queryParams['orderBy'] = array_merge(
					[
						'content_id'   => 'asc',
						'sitemap_auto' => 'asc'
					],
					empty($queryParams['orderBy'])
						? []
						: $queryParams['orderBy']
				);
			}

			$data = $this->dbHelper
				->selectAssocList(
					$this->table,
					'*',
					$whereClause['clause'],
					$whereClause['whereParams'],
					$queryParams['orderBy'],
					$queryParams['offset'],
					$queryParams['perPage'],
					$indexOnKey
						? $this->keyName
						: ''
				);

			$count = count($data);
		}

		return [
			'data'  => empty($data) || $countOnly ? [] : $data,
			'count' => $count
		];
	}

	/**
	 * Count pages in error, taking into account other
	 * filtering if any.
	 *
	 * @param $options
	 *
	 * @return int
	 */
	protected function countErrors($options)
	{
		$whereClause  = $this->dbHelper->quoteName('status')
						. ' >= 400';
		$statusClause = $this->buildStatusWhereClause($options);
		if (!empty($statusClause))
		{
			$whereClause .= ' and ' . $statusClause;
		}

		return $this->dbHelper
			->count(
				'#__forseo_pages',
				'*',
				$whereClause
			);
	}

	/**
	 * Count pages that triggered a redirect, taking into account other
	 * filtering if any.
	 *
	 * @param $options
	 *
	 * @return int
	 */
	protected function countRedirects($options)
	{
		$whereClause  = $this->dbHelper->quoteName('status')
						. ' >= 300'
						. ' and '
						. $this->dbHelper->quoteName('status')
						. ' < 400';
		$statusClause = $this->buildStatusWhereClause($options);
		if (!empty($statusClause))
		{
			$whereClause .= ' and ' . $statusClause;
		}

		return $this->dbHelper
			->count(
				'#__forseo_pages',
				'*',
				$whereClause
			);
	}

	/**
	 * Method to extend the default where clause.
	 *
	 * @param array $options
	 * @param array $clause
	 *
	 * @return mixed
	 */
	protected function extendWhereClause($options, $clause)
	{
		$statusClause = $this->buildStatusWhereClause($options);
		if (!empty($statusClause))
		{
			$clause[] = $statusClause;
		}

		$languageClause = $this->buildLanguageWhereClause($options);
		if (!empty($languageClause))
		{
			$clause[] = $languageClause;
		}

		$canonicalClause = $this->buildCanonicalWhereClause($options);
		if (!empty($canonicalClause))
		{
			$clause[] = $canonicalClause;
		}

		$perfClause = $this->buildPerfWhereClause($options);
		if (!empty($perfClause))
		{
			$clause[] = $perfClause;
		}

		return $clause;
	}

	/**
	 * Build a where clause taking into account desired
	 * status values.
	 *
	 * @param Array $options
	 *
	 * @return string
	 */
	protected function buildStatusWhereClause($options)
	{
		$status = Wb\arrayGet(
			$options,
			'status',
			'all'
		);

		$clause = '';
		switch ($status)
		{
			case 'ok':
				$clause = $this->dbHelper->quoteName('status')
						  . ' = ' . Data\Url::STATUS_OK;
				break;
			case 'redirect':
				$clause = $this->dbHelper->quoteName('status')
						  . ' >= 300'
						  . ' and '
						  . $this->dbHelper->quoteName('status')
						  . ' < 400';
				break;
			case 'error':
				$clause = $this->dbHelper->quoteName('status')
						  . ' >= 400';
				break;
		}

		return $clause;
	}

	/**
	 * Build a where clause taking into account desired
	 * language values.
	 *
	 * @param Array $options
	 *
	 * @return string
	 */
	protected function buildLanguageWhereClause($options)
	{
		if (!$this->platform->isMultilingual())
		{
			return '';
		}

		$filter = Wb\arrayGet(
			$options,
			'filter_lang'
		);

		$clause = '';
		if (!empty($filter))
		{
			$clause = Wb\join(
				' ',
				$this->dbHelper->quoteName('lang') . '=' . $this->dbHelper->quote($filter)
			);
		}

		return $clause;
	}

	/**
	 * Build a where clause taking into account desired
	 * canonical values.
	 *
	 * @param Array $options
	 *
	 * @return string
	 */
	protected function buildCanonicalWhereClause($options)
	{
		$filter = Wb\arrayGet(
			$options,
			'filter_canonical'
		);

		$clause = '';
		if ('only' === $filter)
		{
			$clause = Wb\join(
				' ',
				'(',
				$this->dbHelper->quoteName('canonical_mode') . '=' . Data\Page::AUTO,
				'and',
				$this->dbHelper->quoteName('canonical_auto') . '=' . Data\Page::CANONICAL,
				')',
				'or',
				'(',
				$this->dbHelper->quoteName('canonical_mode') . '=' . Data\Page::USER,
				'and',
				$this->dbHelper->quoteName('canonical_user') . '=' . Data\Page::CANONICAL,
				')'
			);
		}

		if ('except' === $filter)
		{
			$clause = Wb\join(
				' ',
				'(',
				$this->dbHelper->quoteName('canonical_mode') . '=' . Data\Page::AUTO,
				'and',
				$this->dbHelper->quoteName('canonical_auto') . '=' . Data\Page::DUPLICATE,
				')',
				'or',
				'(',
				$this->dbHelper->quoteName('canonical_mode') . '=' . Data\Page::USER,
				'and',
				$this->dbHelper->quoteName('canonical_user') . '=' . Data\Page::DUPLICATE,
				')'
			);
		}

		return $clause;
	}

	/**
	 * Build a where clause taking into account desired
	 * performance status, failing or not.
	 *
	 * @param Array $options
	 *
	 * @return string
	 */
	protected function buildPerfWhereClause($options)
	{
		$filter = Wb\arrayGet(
			$options,
			'filter_perf'
		);

		$clause = '';
		if ('failing' === $filter)
		{
			$clause = $this->dbHelper->quoteName('perf_status')
					  . ' = '
					  . $this->dbHelper->quote(Data\Page::PERF_FAILING);
		}

		if ('withData' === $filter)
		{
			$clause = $this->dbHelper->quoteName('perf_status')
					  . ' <> '
					  . $this->dbHelper->quote(Data\Page::PERF_NO_DATA);
		}

		return $clause;
	}

	/**
	 * Override handles special case where when searching for home page
	 * URL (ie /), we actually need to search for an empty URL.
	 *
	 * @param string      $term
	 * @param null|string $column
	 * @return mixed
	 */
	protected function rewriteSearchTerm($term, $column = null)
	{
		if (
			'/' === $term
			&&
			'url' === $column
		) {
			return '';
		}

		return $term;
	}
}

