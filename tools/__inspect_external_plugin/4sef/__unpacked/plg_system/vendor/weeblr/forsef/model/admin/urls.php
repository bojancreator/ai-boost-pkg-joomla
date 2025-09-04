<?php
/**
 * Project: 4SEF
 *
 * @package          4SEF
 * @copyright        Copyright Weeblr llc - 2022 -2025
 * @author           Yannick Gaultier - Weeblr llc
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          2.6.2.644
 * @date        2025-06-02
 */

namespace Weeblr\Forsef\Model\Admin;

use Weeblr\Forsef\Data;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Db;
use Weeblr\Wblib\Forsef\Joomla\StringHelper\StringHelper;


// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Urls extends Db\Dataobjectlist
{
	/**
	 * @var string Name of the ordering column if none specified.
	 */
	protected $defaultOrderBy = 'sef';

	/**
	 * Store information about managed data.
	 *
	 * @param string $dataObjectClass
	 */
	public function __construct($dataObjectClass = null)
	{
		parent::__construct($dataObjectClass);

		$this->defaultItemsPerPage = $this->factory
			->getThis(
				'forsef.config',
				'system'
			)->getInt(
				'defaultItemsPerPage',
				10
			);
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

		// decide whether meta data for each page should be included
		$withDuplicatesCount = (int)Wb\arrayGet(
			$options,
			'with_duplicates_count',
			0
		);

		if (empty($withDuplicatesCount))
		{
			return $data;
		}

		$urls = $this->insertDuplicatesCountInList(
			Wb\arrayGet(
				$data,
				'data'
			)
		);

		return Wb\arraySet(
			$data,
			'data',
			$urls
		);
	}

	/**
	 * Inject duplicates count for each URL in the list.
	 *
	 * @param array $urlsList
	 *
	 * @return array
	 */
	private function insertDuplicatesCountInList($urlsList)
	{
		if (empty($urlsList))
		{
			return [];
		}

		$urls = [];
		foreach ($urlsList as $url)
		{
			if (Data\Urlpair::CANONICAL === Wb\arrayGet($url, 'duplicate'))
			{
				$urls[] = Wb\arrayGet($url, 'sef');
			}
		}

		$duplicatesCounts = $this->dbHelper
			->setQueryAnd(
				'select ' . $this->dbHelper->qn('sef') . ',count(' . $this->dbHelper->qn('duplicate') . ') as duplicates'
				. ' from ' . $this->dbHelper->qn('#__forsef_urls')
				. ' where' . $this->dbHelper->qn('sef') . ' in (' . $this->dbHelper->arrayToQuotedList($urls) . ')'
				. ' and ' . $this->dbHelper->qn('duplicate') . ' <> 0'
				. ' group by ' . $this->dbHelper->qn('sef')
			)->loadAssocList(
				'sef'
			);

		$duplicatesCounts = empty($duplicatesCounts)
			? []
			: $duplicatesCounts;

		foreach ($urlsList as $key => $urlRecord)
		{
			if (!empty($duplicatesCounts[$urlRecord['sef']]))
			{
				$urlsList[$key]['duplicates'] = $duplicatesCounts[$urlRecord['sef']]['duplicates'];
			}
		}

		return $urlsList;
	}

	/**
	 * Delete one or more URL pairs.
	 *
	 * @param array $keys
	 * @param array $options
	 *
	 * @return array|\Exception
	 */
	public function delete($keys, $options = [])
	{
		$affectedRows = 0;

		try
		{
			$withDuplicates = (bool)Wb\arrayGet(
				$options,
				'with_duplicates',
				0
			);

			$withCustom = (bool)Wb\arrayGet(
				$options,
				'with_custom',
				0
			);

			$withPreserveImportedCount = (bool)Wb\arrayGet(
				$options,
				'with_preserve_imported_count',
				0
			);

			if (empty($keys))
			{
				$withCustomClause = $withCustom
					? []
					: [
						'custom' => Data\Urlpair::AUTO
					];

				$this->dbHelper->delete(
					$this->table,
					$withCustomClause
				);

				if (empty($withPreserveImportedCount))
				{
					$this->factory->getThe('forsef.keystore')
								  ->delete(
									  'sh404sef_import.processed'
								  );
				}
			}
			else
			{
				// delete some URLs and their duplicates
				$sefs         = $this->dbHelper->selectColumn(
					$this->table,
					'sef',
					$this->dbHelper->qn($this->keyName) . ' in (' . $this->dbHelper->arrayToIntvalList($keys) . ')'
				);
				$affectedRows = count($sefs);

				// used later to delete redirects
				$remainingSefs = [];

				if (!empty($sefs) && empty($withDuplicates))
				{
					// promote next duplicate as main URL, if any
					$firstDuplicates = $this->dbHelper
						->setQueryAnd(
							' select min(' . $this->dbHelper->qn($this->keyName) . ')'
							. ' from ' . $this->dbHelper->qn($this->table)
							. ' where ' . $this->dbHelper->qn('duplicate') . ' = 1'
							. ' and ' . $this->dbHelper->qn('sef') . ' in (' . $this->dbHelper->arrayToQuotedList($sefs) . ')'
							. ' group by ' . $this->dbHelper->qn('sef')
						)->loadColumn();

					if (!empty($firstDuplicates))
					{
						$this->dbHelper->update(
							$this->table,
							[
								'duplicate' => 0
							],
							$this->dbHelper->qn($this->keyName) . ' in (' . $this->dbHelper->arrayToIntvalList($firstDuplicates) . ')'
						);
					}

					// only delete specified URLs
					$this->dbHelper
						->deleteIn(
							$this->table,
							$this->keyName,
							Wb\arrayEnsure($keys),
							Db\Helper::INTEGER
						);

					// check whether some duplicates are still present
					$remainingSefs = $this->dbHelper->selectColumn(
						$this->table,
						'sef',
						$this->dbHelper->qn('sef') . ' in (' . $this->dbHelper->arrayToQuotedList($sefs) . ')'
					);

					$remainingSefs = empty($remainingSefs)
						? []
						: $remainingSefs;
				}
				else if (!empty($sefs))
				{
					$whereClause = $this->dbHelper->qn('sef')
								   . ' in (' . $this->dbHelper->arrayToQuotedList($sefs) . ')';

					$affectedRows = $this->dbHelper
						->count(
							$this->table,
							'*',
							$whereClause
						);

					// delete all URLs with the same sef
					$this->dbHelper->delete(
						$this->table,
						$whereClause
					);
				}

				// search and delete any redirect targeting one of the deleted URLs
				// if no such URL is still present in the URLs table (we may have deleted only one
				// of several duplicates, so only deleting a URL does not mean we must also delete redirects
				// targeting it.
				$entirelyGoneSefs = array_diff(
					$sefs,
					$remainingSefs
				);
				if (!empty($entirelyGoneSefs))
				{
					$this->dbHelper->delete(
						'#__forsef_redirects',
						$this->dbHelper->qn('target')
						. ' in (' . $this->dbHelper->arrayToQuotedList($entirelyGoneSefs) . ')'
					);
				}
			}
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forsef.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return new \Exception($e->getMessage(), System\Http::RETURN_INTERNAL_ERROR);
		}

		return [
			'data'  => null,
			'count' => $affectedRows,
			'total' => $affectedRows,
		];
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
		$duplicatesOnly = (bool)Wb\arrayGet(
			$options,
			'duplicates_only',
			0
		);

		$this->searchableColumns = $duplicatesOnly
			? ['nonsef']
			: ['sef', 'nonsef'];

		return parent::buildSearchWhereClause($options);
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
		if (Wb\arrayIsTruthy($options, 'filter_custom'))
		{
			$clause[] = $this->dbHelper->quoteName('custom')
						. ' = '
						. Data\Urlpair::CUSTOM;
		}

		$duplicatesOnly = (bool)Wb\arrayGet(
			$options,
			'duplicates_only',
			0
		);

		$clause[] = $this->dbHelper->qn('duplicate')
					. ' = '
					. (
					$duplicatesOnly
						? Data\Urlpair::DUPLICATE
						: Data\Urlpair::CANONICAL
					);

		if (!empty($duplicatesOnly))
		{
			$sef = Wb\arrayGet(
				$options,
				'sef',
				''
			);

			$clause[] = $this->dbHelper->qn('sef')
						. ' = '
						. $this->dbHelper->q($sef);
		}

		return $clause;
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
			'sef' === $column
		)
		{
			return '';
		}

		return $term;
	}
}
