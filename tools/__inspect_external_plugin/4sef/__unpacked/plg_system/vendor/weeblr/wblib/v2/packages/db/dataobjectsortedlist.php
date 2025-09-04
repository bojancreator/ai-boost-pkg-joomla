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
use Weeblr\Wblib\Forsef\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die();

class Dataobjectsortedlist extends Dataobjectlist
{
	/**
	 * Update an existing pages record.
	 *
	 * @param   int    $id
	 * @param   array  $data
	 *
	 * @return array|\Exception
	 * @throws \Exception
	 */
	public function save($id, $data)
	{
		$orderTarget    = Wb\arrayGetInt(
			$data,
			'orderTarget',
			-1
		);
		$orderDirection = Wb\arrayGet(
			$data,
			'orderDirection',
			'after'
		);

		$saved = parent::save(
			$id,
			$data
		);

		if (
			$saved instanceof \Throwable
			||
			$saved instanceof \Exception
		)
		{
			return $saved;
		}

		if (-1 !== $orderTarget)
		{
			$this->reOrder(
				$saved,
				$orderTarget,
				$orderDirection
			);
		}

		return $saved;
	}

	/**
	 * Create a record
	 *
	 * @param   array  $data
	 *
	 * @return array|\Exception
	 * @throws \Exception
	 * @throws \Throwable
	 */
	public function store($data)
	{
		$existingOrdering = Wb\arrayGetInt(
			$data,
			'ordering',
			-1
		);
		$type             = Wb\arrayGetInt(
			$data,
			'type'
		);

		if (
			-1 === $existingOrdering
			||
			empty($existingOrdering)
		)
		{
			// we are creating a rule, we must find out the proper value of ordering
			$lastOrdering     = $this->findOrdering($type, 'last');
			$data['ordering'] = $lastOrdering + 1;
		}

		return parent::store($data);
	}

	/**
	 * Reorder the list of items after an addition, removal or re-ordering.
	 *
	 * @param   array        $item
	 * @param   int|null     $orderTarget
	 * @param   string|null  $orderDirection
	 *
	 * @return $this
	 * @throws \Throwable
	 */
	protected function reOrder($item, $orderTarget = null, $orderDirection = null)
	{
		try
		{
			$id   = Wb\arrayGetInt(
				$item,
				['data', $this->keyName]
			);
			$type = Wb\arrayGetInt(
				$item,
				['data', 'type']
			);

			$this->dbHelper->db()->transactionStart();

			// if orderAfter is empty, append
			if (
				is_null($orderTarget)
				||
				-1 === $orderTarget
			)
			{
				//  find max in type, add 1, save
				$previousLast = $this->findLastOrdering($type);
				$this->dbHelper->update(
					$this->table,
					[
						'ordering' => $previousLast + 1
					],
					[
						$this->keyName => $id
					]
				);
			}

			// if orderAfter is 0 (order as first)
			//   find min in type, subtract 1, save
			if (0 === $orderTarget)
			{
				$previousFirst = $this->findOrdering($type, 'first');
				$this->dbHelper->update(
					$this->table,
					[
						'ordering' => $previousFirst - 1
					],
					[
						$this->keyName => $id
					]
				);
			}

			if ($orderTarget > 0)
			{
				$orderTargetItemOrdering = $this->dbHelper
					->selectResult(
						$this->table,
						'ordering',
						[
							'type'         => $type,
							$this->keyName => $orderTarget
						]
					);

				// should we insert after or before?
				// increase ordering of all items after the target position
				// including or not the target position depending on whether we want to put the item before or after the target
				$comparisonOperator = 'before' === $orderDirection
					? ' >= '
					: ' > ';

				$this->dbHelper->setQueryAnd(
					'update ' . $this->dbHelper->qn($this->table)
					. ' set ' . $this->dbHelper->qn('ordering') . ' = ' . $this->dbHelper->qn('ordering') . ' + 1'
					. ' where ' . $this->dbHelper->qn('type') . ' = ' . $this->dbHelper->q($type)
					. ' and ' . $this->dbHelper->qn('ordering') . $comparisonOperator . $this->dbHelper->q($orderTargetItemOrdering)
					// must start by the end to make room for the new index, else we'll create some duplicate key
					. ' order by ' . $this->dbHelper->qn('ordering') . ' desc'
				)->execute();

				// set item in the created gap
				$increment = 'before' === $orderDirection
					? 0
					: 1;
				$this->dbHelper->update(
					$this->table,
					[
						'ordering' => $orderTargetItemOrdering + $increment
					],
					[
						$this->keyName => $id
					]
				);
			}

			$this->dbHelper->db()->transactionCommit();

			return $this;
		}
		catch (\Throwable $e)
		{
			$this->dbHelper->db()->transactionRollback();
			throw $e;
		}
	}

	/**
	 * Lookup the lowest or highest ordering value for a given type.
	 *
	 * @param   int     $type
	 * @param   string  $position  lst | first
	 *
	 * @return int
	 */
	protected function findOrdering($type, $position)
	{
		$ordering = $this->dbHelper
			->selectResult(
				$this->table,
				'ordering',
				[
					'type' => $type
				],
				[],
				[
					'ordering' => 'last' === $position ? 'DESC' : 'ASC'
				]
			);

		return (int) $ordering ?? 0;
	}

	/**
	 * Query the actual data for the request, taking into account pagination.
	 *
	 * @param   array  $options
	 * @param   array  $whereClause
	 * @param   int    $total
	 * @param   bool   $indexOnKey
	 *
	 * @return array
	 * @throws \Exception
	 */
	protected function queryData($options, $whereClause, $total, $indexOnKey = false)
	{
		// override default setting
		$indexOnKey = true;

		$originalQueryParams = $this->queryParams(
			$options,
			$total
		);

		// remove pagination info
		$queryParams            = $originalQueryParams;
		$queryParams['perPage'] = null;
		$queryParams['page']    = null;
		$queryParams['offset']  = null;

		// inject ordering clause
		$queryParams['orderBy'] = [
			'type'     => 'ASC',
			'ordering' => 'ASC'
		];

		// read ALL the rules, without pagination
		$queryResult = $this->runQuery($options, $whereClause, $queryParams, $indexOnKey);

		$queryResult['data'] =
			array_values(
				$queryResult['data']
			);

		// now applies pagination
		$queryParams          = $originalQueryParams;
		$paginatedData        = array_slice(
			$queryResult['data'],
			$queryParams['offset'],
			$queryParams['perPage'],
			true // preserve keys
		);
		$queryResult['data']  = array_values(
			$paginatedData
		);
		$queryResult['count'] = count($queryResult['data']);

		return [
			'data' => $queryResult['data'],
			'meta' => [
				'count'   => $queryResult['count'],
				'total'   => $total,
				'errors'  => $this->countErrors($options),
				'current' => $queryParams['page'],
				'perPage' => $queryParams['perPage'],
			]
		];
	}
}
