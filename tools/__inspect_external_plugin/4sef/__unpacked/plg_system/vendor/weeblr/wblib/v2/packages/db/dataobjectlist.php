<?php
/**
 * Project:                 4SEF
 *
 * @package          4SEF
 * @copyright        Copyright Weeblr llc - 2022 -2025
 * @author           Yannick Gaultier - Weeblr llc
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          2.6.2.644
 * @date        2025-06-02
 */

namespace Weeblr\Wblib\Forsef\Db;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Base as Base;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Joomla\StringHelper\StringHelper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die();

class Dataobjectlist extends Base\Base
{
	/**
	 * @var string The main data object type associated with this model.
	 */
	protected $dataObjectClass = null;

	/**
	 * @var array Options needed to create a main data object instance
	 */
	protected $dataObjectOptions = [];

	/**
	 * @var string The name of the primary key column.
	 */
	protected $keyName = 'id';

	/**
	 * @var array List of columns that can be searched when specified in a search query - syntax: column_id => search_term
	 */
	protected $searchableColumns = [];

	/**
	 * @var array List of columns that can be used to order lists.
	 */
	protected $orderableColumns = [];

	/**
	 * @var string[] List of data fields representing long varchar content that still must be indexed.
	 * Keys are full length column names, values are corresponding indexable column name.
	 */
	protected $storageSafeColumns = [];

	/**
	 * @var string Name of the ordering column if none specified.
	 */
	protected $defaultOrderBy = '';

	/**
	 * @var string The main database table associated with this model.
	 */
	protected $table = '';

	/**
	 * @var Helper Database access helper.
	 */
	protected $dbHelper = null;

	/**
	 * @var int Default number of items per page.
	 */
	protected $defaultItemsPerPage = 10;

	/**
	 * @var int Upper limit of number of items per page.
	 */
	protected $maxItemsPerPage = 100;

	/**
	 * @var array Convenience array of the defaults values for an item.
	 */
	protected $defaults = [];

	/**
	 * Store information about managed data.
	 *
	 * @param string $dataObjectClass
	 * @param array  $dataObjectOptions
	 */
	public function __construct($dataObjectClass = null, $dataObjectOptions = [])
	{
		parent::__construct();

		$this->dbHelper = $this->factory
			->getThe('db');

		$this->dataObjectClass = empty($dataObjectClass)
			? $this->dataObjectClass
			: $dataObjectClass;

		$this->dataObjectOptions = empty($dataObjectOptions)
			? $this->dataObjectOptions
			: $dataObjectOptions;

		if (!empty($this->dataObjectClass))
		{
			$dataObject               = $this->factory->getA(
				$this->dataObjectClass,
				$this->dataObjectOptions
			);
			$this->table              = $dataObject->tableName();
			$this->searchableColumns  = $dataObject->searchableColumnsList();
			$this->orderableColumns   = $dataObject->orderableColumnsList();
			$this->storageSafeColumns = $dataObject->storageSafeColumnsList();
			$this->defaults           = $dataObject->defaults();
		}
	}

	/**
	 * Loads up a single object information.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public function get($options)
	{
		$options = $this->beforeGet($options);

		$id         = (int)Wb\arrayGet($options, $this->keyName, 0);
		$dataObject = $this->factory
			->getA(
				$this->dataObjectClass,
				$this->dataObjectOptions
			)->load(
				$id
			);

		if (!$dataObject->exists())
		{
			$data = new \Exception('Page not found.', System\Http::RETURN_NOT_FOUND);
		}
		else
		{
			$data = [
				'data'  => $dataObject->get(),
				'count' => 1,
				'total' => 1,
			];
		}

		return $this->afterGet(
			$data,
			$options
		);
	}

	/**
	 * Hook to pre-process options before loading data.
	 *
	 * @param array $options
	 *
	 * @return mixed
	 */
	protected function beforeGet(array $options)
	{
		return $options;
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
		$dataItem = Wb\arrayGet($data, 'data');
		if (empty($dataItem))
		{
			return $data;
		}

		$filteredDataItem = array_merge(
			$this->defaults,
			empty($dataItem)
				? []
				: $dataItem
		);

		$data['data'] = $filteredDataItem;

		return $data;
	}

	/**
	 * Update an existing pages record.
	 *
	 * @param int   $id
	 * @param array $data
	 *
	 * @return array|\Exception
	 */
	public function save($id, $data)
	{
		$dataObject = $this->factory
			->getA(
				$this->dataObjectClass,
				$this->dataObjectOptions
			)->load(
				$id
			);

		if (!$dataObject->exists())
		{
			return new \Exception('Page not found.', System\Http::RETURN_NOT_FOUND);
		}

		$dataObject->set($data)
				   ->store();

		return [
			'data'  => $dataObject->get(),
			'count' => 1,
			'total' => 1,
		];
	}

	/**
	 * Create a record
	 *
	 * @param array $data
	 *
	 * @return array|\Exception
	 */
	public function store($data)
	{
		$dataObject = $this->factory
			->getA(
				$this->dataObjectClass,
				$this->dataObjectOptions
			)->set(
				$data
			);

		if ($dataObject->exists())
		{
			return new \Exception('Item already exists.', System\Http::RETURN_BAD_REQUEST);
		}

		$dataObject->store();

		return [
			'data'  => $dataObject->get(),
			'count' => 1,
			'total' => 1,
		];
	}


	/**
	 * Delete one or more pages.
	 *
	 * @param array $ids
	 *
	 * @return array|\Exception
	 */
	public function delete($ids)
	{
		try
		{
			$this->factory
				->getA(
					$this->dataObjectClass,
					$this->dataObjectOptions
				)->delete(
					$ids
				);
		}
		catch (\Exception $e)
		{
			return new \Exception('Page not found.', System\Http::RETURN_NOT_FOUND);
		}

		return [
			'data'  => null,
			'count' => count($ids),
			'total' => count($ids),
		];
	}

	/**
	 * Purge function, empty this object database table.
	 *
	 * @return array|\Exception
	 */
	public function deleteAll()
	{
		try
		{
			$itemsCount = $this->queryTotal([], []);
			$this->factory
				->getA(
					$this->dataObjectClass,
					$this->dataObjectOptions
				)->deleteAll();
		}
		catch (\Exception $e)
		{
			return new \Exception('Page not found.', System\Http::RETURN_NOT_FOUND);
		}

		return [
			'data'  => null,
			'count' => $itemsCount,
			'total' => $itemsCount,
		];
	}

	/**
	 * Loads a list of pages.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public function getList(array $options)
	{
		try
		{
			$options     = $this->beforeGetList($options);
			$whereClause = $this->buildWhereClause($options);
			$total       = $this->queryTotal($options, $whereClause);

			return $this->afterGetList(
				$this->queryData(
					$options,
					$whereClause,
					$total
				),
				$options
			);
		}
		catch (\Exception $e)
		{
			System\Log::libraryError('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return new \Exception('Internal error. See error log file.', System\Http::RETURN_INTERNAL_ERROR);
		}
	}

	/**
	 * Hook to pre-process options before loading data.
	 *
	 * @param array $options
	 *
	 * @return mixed
	 */
	protected function beforeGetList(array $options)
	{
		return $options;
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
		$dataItems = Wb\arrayGet($data, 'data');
		if (empty($dataItems))
		{
			return $data;
		}
		$filteredData = [];
		$dataObject   = $this->factory
			->getA(
				$this->dataObjectClass,
				$this->dataObjectOptions
			);

		foreach ($dataItems as $dataItem)
		{
			$dataObject->withData(
				array_merge(
					$this->defaults,
					empty($dataItem)
						? []
						: $dataItem
				)
			);

			$filteredData[] = $dataObject->get();
		}

		$data['data'] = $filteredData;

		return $data;
	}

	/**
	 * Count the total number of items from the query, without pagination.
	 *
	 * @param array $options
	 * @param array $whereClause
	 *
	 * @return int
	 */
	protected function queryTotal($options, $whereClause)
	{
		return $this->dbHelper
			->count(
				$this->table,
				'*',
				Wb\arrayGet($whereClause, 'clause', ''),
				Wb\arrayGet($whereClause, 'whereParams', [])
			);
	}

	/**
	 * Query the actual data for the request, taking into account pagination.
	 *
	 * @param array $options
	 * @param array $whereClause
	 * @param int   $total
	 * @param bool  $indexOnKey
	 *
	 * @return array
	 */
	protected function queryData($options, $whereClause, $total, $indexOnKey = false)
	{
		$queryParams = $this->queryParams(
			$options,
			$total
		);
		$queryResult = $this->runQuery($options, $whereClause, $queryParams, $indexOnKey);

		return [
			'data' => $queryResult['data'],
			'meta' => [
				'count'   => $queryResult['count'],
				'total'   => $total,
				'errors'  => $this->countErrors($options),
				'current' => $queryParams['page'],
				'perPage' => $queryParams['perPage']
			]
		];
	}

	/**
	 * Builds an array of query parameters (offset, limit, ordering) from the
	 * query variables passed in the request.
	 *
	 * @param array $options
	 * @param int   $total
	 *
	 * @return array
	 */
	protected function queryParams($options, $total)
	{
		$perPage = (int)Wb\arrayGet($options, 'per_page');
		$perPage = empty($perPage)
			? $this->defaultItemsPerPage
			: $perPage;
		$perPage = $perPage > $this->maxItemsPerPage
			? $this->maxItemsPerPage
			: $perPage;

		$page    = (int)Wb\arrayGet($options, 'page', 0);
		$page    = max(1, $page);
		$maxPage = empty($perPage)
			? 0
			: (int)ceil($total / $perPage);
		$maxPage = max(1, $maxPage);
		$page    = min($maxPage, $page);
		$offset  = ($page - 1) * $perPage;

		$orderBy    = Wb\arrayGet($options, 'order_by', $this->defaultOrderBy);
		$orderByDir = Wb\startsWith($orderBy, '-') ? ' DESC' : ' ASC';
		$orderBy    = Wb\lTrim($orderBy, '-');
		// white list orderby
		if (in_array(
			$orderBy,
			$this->orderableColumns
		))
		{
			$orderBy = [$orderBy => $orderByDir];
		}
		else
		{
			$orderBy = '';
		}

		return [
			'perPage' => $perPage,
			'page'    => $page,
			'offset'  => $offset,
			'maxPage' => $maxPage,
			'orderBy' => $orderBy
		];
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
	 */
	protected function runQuery($options, $whereClause, $queryParams, $indexOnKey = false)
	{
		$countOnly = Wb\arrayGet($options, 'count_only', false);
		if ($countOnly)
		{
			$data  = [];
			$count = $this->dbHelper
				->count(
					$this->table,
					'*',
					$whereClause['clause'],
					$whereClause['whereParams']
				);
		}
		else
		{
			$data  = $this->dbHelper
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
			'data'  => empty($data) ? [] : $data,
			'count' => $count
		];
	}

	/**
	 * Count pages in error, taking into account possible
	 * filtering on page status.
	 *
	 * @param $options
	 *
	 * @return int
	 */
	protected function countErrors($options)
	{
		return 0;
	}

	/**
	 * Build the where SQL clause needed by both the total items
	 * count query and the actual data query.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	protected function buildWhereClause($options)
	{
		$clause = [];
		$params = [];

		$searchClause = $this->buildSearchWhereClause($options);
		if (!empty($searchClause))
		{
			$clause[] = $searchClause;
		}

		$clause = $this->extendWhereClause(
			$options,
			$clause
		);

		if (count($clause) > 1)
		{
			$clause = implode(' and ', $clause);
		}
		else if (!empty($clause))
		{
			$clause = $clause[0];
		}
		else
		{
			$clause = '';
		}

		return [
			'clause'      => $clause,
			'whereParams' => $params
		];
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
		return $clause;
	}

	/**
	 * Build a where clause taking into account desired
	 * search value.
	 *
	 * @param Array $options
	 *
	 * @return string
	 */
	protected function buildSearchWhereClause($options)
	{
		$search = Wb\arrayGet($options, 'search', '');
		if (empty($search))
		{
			return '';
		}

		$exactSearch = Wb\arrayGet($options, 'exact_search', 0);
		$exactSearch = !empty($exactSearch);

		$clause = '';

		$search = StringHelper::strtolower($search);
		if (Wb\startsWith(
			$search,
			array_map(
				function ($column) {
					return $column . ':';
				},
				array_keys(
					$this->searchableColumns
				)
			)
		))
		{
			// extract column and searched value
			$bits    = explode(':', $search, 2);
			$columns = [
				$this->searchableColumns[$bits[0]]
			];
			$search  = Wb\lTrim($search, $bits[0] . ':');
		}
		else
		{
			$columns = $this->searchableColumns;
		}

		if (
			!empty($columns)
			&&
			!empty($search)
		)
		{
			$queries = [];
			foreach ($columns as $column)
			{
				$rewrittenSearch = $this->rewriteSearchTerm(
					$search,
					$column
				);

				if (\in_array($column, $this->storageSafeColumns))
				{
					$rewrittenSearch = StringHelper::substr(
						$rewrittenSearch,
						0,
						$this->dbHelper->getCutoffLength()
					);
				}

				$query = $this->dbHelper->quoteName($column);
				if ($exactSearch)
				{
					$query .= ' = ' . $this->dbHelper->quote($rewrittenSearch);
				}
				else
				{
					// _ is a wildcard character when used in like clauses
					// requires caution to avoid double-escaping
					$searchQuery = ' like ' .
								   $this->dbHelper->quote(
									   '%{{CVZ!xCQ3y8CW}}%',
									   false
								   );

					$query .= str_replace(
						'{{CVZ!xCQ3y8CW}}',
						addcslashes(
							$rewrittenSearch,
							'_%'
						),
						$searchQuery
					);
				}

				$queries[] = $query;
			}
			$clause = '(' . implode(
					' or ',
					$queries
				)
					  . ')';
		}

		return $clause;
	}

	/**
	 * Let descendant rewrite the searched term based on its usage or
	 * the column it's applied to.
	 *
	 * @param string      $term
	 * @param null|string $column
	 *
	 * @return mixed
	 */
	protected function rewriteSearchTerm($term, $column = null)
	{
		return $term;
	}
}
