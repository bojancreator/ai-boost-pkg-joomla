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

class Dataobjectsortedbystorelist extends Dataobjectlist
{
	/**
	 * @var string|null A unique scope for the data being sorted.
	 */
	protected $orderingScope = null;

	/**
	 * @var null Keystore
	 */
	protected $orderingStore = null;

	/**
	 * Store information about managed data.
	 *
	 * @param   string    $dataObjectClass
	 * @param   string    $orderingScope  An identifier for the list ordering data.
	 * @param   Keystore  $store          A wbLib keystore where ordering list will be stored.
	 */
	public function __construct($dataObjectClass = null, $orderingScope = '', $store = null)
	{
		parent::__construct($dataObjectClass);

		$this->orderingScope = $orderingScope;
		$this->orderingStore = $store;
	}

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
		$orderAfter = Wb\arrayGet(
			$data,
			'orderAfter'
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

		$this->orderingMoveTo(
			$id,
			$orderAfter
		);

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
		$orderAfter = Wb\arrayGet(
			$data,
			'orderAfter'
		);

		$stored = parent::store($data);

		// append the newly created object to the ordering list
		$newKey = Wb\arrayGet(
			$stored,
			[
				'data',
				$this->keyName
			]
		);

		$this->orderingAdd(
			$newKey,
			$orderAfter
		);

		return $stored;
	}

	/**
	 * Delete one or more pages.
	 *
	 * @param   array  $ids
	 *
	 * @return array|\Exception
	 * @throws \Exception
	 */
	public function delete($ids)
	{
		$deleted = parent::delete($ids);

		// delete ordering even if an exception happened when deleting the items;
		$this->orderingDelete($ids);

		return $deleted;
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

		// read ALL the rules, without pagination
		$queryResult = $this->runQuery($options, $whereClause, $queryParams, $indexOnKey);

		// sort that list and strip it of index as this is the expected output format
		$queryResult['data'] =
			array_values(
				$this->orderingSort(
					$queryResult['data']
				)
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

	/**
	 * Sorts an array of arrays or objects according to the stored ordering list.
	 * If items have an ordering field, that field is filled up with the ordering value.
	 * Items must be indexed on the key name.
	 *
	 * @param   \array   $items
	 * @param   \string  $keyName
	 * @param   \string  $itemType
	 * @param   \string  $itemOrderingField
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	protected function orderingSort(array $items, $keyName = 'id', $itemType = 'array', $itemOrderingField = 'ordering')
	{
		$sortedItems = [];

		$currentList = $this->orderingLoad();
		foreach ($currentList as $index => $id)
		{
			if (array_key_exists($id, $items))
			{
				$item = $items[$id];

				if (!empty($itemOrderingField) && 'array' == $itemType)
				{
					$item[$itemOrderingField] = $index;
				}
				else if (!empty($itemOrderingField))
				{
					$item->{$itemOrderingField} = $index;
				}

				$sortedItems[] = $item;
			}
		}

		return $sortedItems;
	}

	/**
	 * Append one or more ids to the end of the ordering list,
	 * or after a specified item.
	 *
	 * @param   array| int  $ids
	 * @param   int         $orderAfter
	 *
	 * @return $this
	 * @throws \Exception
	 * @throws \Throwable
	 */
	protected function orderingAdd($ids, $orderAfter = 0)
	{
		try
		{
			$ids = Wb\arrayEnsure($ids);
			$this->dbHelper->db()->transactionStart();
			$currentList = $this->orderingLoad();
			$currentList = empty($currentList)
				? []
				: $currentList;

			$intersection = array_intersect(
				$currentList,
				$ids
			);

			if (!empty($intersection))
			{
				// log error but do not anything visible
				System\Log::libraryError('%s::%d %s', __METHOD__, __LINE__, 'Trying to append already existing ids to ordered list ' . $this->orderingScope);
				$this->dbHelper->db()->transactionRollback();

				return $this;
			}

			if (empty($orderAfter))
			{
				$currentList = array_merge(
					$currentList,
					$ids
				);
			}
			else
			{
				// search speficied item
				$orderAfterPosition = array_search(
					$orderAfter,
					$currentList
				);

				if (false === $orderAfterPosition)
				{
					System\Log::libraryError('%s::%d %s', __METHOD__, __LINE__, 'Specified order after position not found while adding a new item to ordered list "' . $this->orderingScope . '", orderAfter: ' . $orderAfterPosition);
					$this->dbHelper->db()->transactionRollback();

					throw new \Exception('Cannot insert at specified position, this item may have been removed in the mean time.');
				}

				array_splice(
					$currentList,
					$orderAfterPosition + 1,
					0,
					$ids
				);
			}

			$this->orderingSave(
				$currentList
			);

			$this->dbHelper->db()->transactionCommit();

			return $this;
		}
		catch (\Throwable $e)
		{
			$this->dbHelper->db()->transactionRollback();
			throw $e;
		}
		catch (\Exception $e)
		{
			$this->dbHelper->db()->transactionRollback();
			throw $e;
		}
	}

	/**
	 * Removes one or more ids from the ordering list.
	 *
	 * @param   array| int  $ids
	 *
	 * @return $this
	 * @throws \Exception
	 */
	protected function orderingDelete($ids)
	{
		try
		{
			$ids = Wb\arrayEnsure($ids);
			$this->dbHelper->db()->transactionStart();
			$currentList = $this->orderingLoad();
			$currentList = empty($currentList)
				? []
				: $currentList;

			$intersection = array_intersect(
				$currentList,
				$ids
			);

			if (empty($intersection))
			{
				// nothing to do, those ids are just not there already
				$this->dbHelper->db()->transactionRollback();

				return $this;
			}

			$currentList = array_values(
				array_diff(
					$currentList,
					$ids
				)
			);

			$this->orderingSave(
				$currentList
			);

			$this->dbHelper->db()->transactionCommit();

			return $this;
		}
		catch (\Throwable $e)
		{
			$this->dbHelper->db()->transactionRollback();
			throw $e;
		}
		catch (\Exception $e)
		{
			$this->dbHelper->db()->transactionRollback();
			throw $e;
		}
	}

	/**
	 * Moves a given id after another specified id.
	 * If moveAfter is zero, it means move to start of list.
	 *
	 * @param   int  $id
	 * @param   int  $moveAfter
	 *
	 * @return $this
	 * @throws \Exception
	 */
	protected function orderingMoveTo($id, $moveAfter = null)
	{
		try
		{
			// don't change ordering if none specified
			if (is_null($moveAfter))
			{
				return $this;
			}

			$id = (int) $id;

			$this->dbHelper->db()->transactionStart();
			$currentList = $this->orderingLoad();
			$currentList = empty($currentList)
				? []
				: $currentList;

			if (!in_array(
				$id,
				$currentList
			))
			{
				// nothing to do, this id is just not there already
				$this->dbHelper->db()->transactionRollback();

				return $this;
			}

			// then insert into new position
			if (empty($moveAfter))
			{
				$currentList = array_filter(
					$currentList,
					function ($itemId) use ($id) {
						return $itemId !== $id;
					}
				);

				System\Log::libraryDebug('%s::%d %s', __METHOD__, __LINE__, 'MoveAfter is empty, adding at front');
				array_unshift(
					$currentList,
					$id
				);
			}

			if (!empty($moveAfter))
			{
				$currentPosition = array_search(
					$id,
					$currentList
				);

				$moveAfterPosition = array_search(
					$moveAfter,
					$currentList
				);

				if (
					false === $moveAfterPosition
					||
					false === $currentPosition
					||
					$currentPosition == $moveAfterPosition
				)
				{
					// nothing to do, either source or target position not in the list already
					// or item is already in the desired position
					$this->dbHelper->db()->transactionRollback();

					return $this;
				}

				// filter out current instance
				$currentList = array_filter(
					$currentList,
					function ($itemId) use ($id) {
						return $itemId !== $id;
					}
				);

				$updatedList = [];
				foreach ($currentList as $key => $item)
				{
					$updatedList[] = $currentList[$key];
					if ($item == $moveAfter)
					{
						$updatedList[] = $id;
					}
				}

				$currentList = $updatedList;
			}

			$currentList = array_values(
				$currentList
			);

			$this->orderingSave(
				array_values(
					$currentList
				)
			);

			$this->dbHelper->db()->transactionCommit();

			return $this;
		}
		catch (\Throwable $e)
		{
			$this->dbHelper->db()->transactionRollback();
			throw $e;
		}
		catch (\Exception $e)
		{
			$this->dbHelper->db()->transactionRollback();
			throw $e;
		}
	}

	/**
	 * Writes ordered list of items keys to the db store,
	 * making sure they are unique.
	 *
	 * @param   array  $orderingList
	 *
	 * @return $this
	 * @throws \Exception
	 */
	protected function orderingSave($orderingList)
	{
		// make unique and numerically keyed for good measure.
		$orderingList = array_values(
			array_unique($orderingList)
		);

		// then save
		$this->orderingStore->put(
			$this->orderingStorageKey(),
			$orderingList
		);

		return $this;
	}

	/**
	 * Reads the currently stored ordering data from the db store.
	 *
	 * @return array|null
	 * @throws \Exception
	 */
	protected function orderingLoad()
	{
		$loaded = $this->orderingStore->get(
			$this->orderingStorageKey()
		);

		return empty($loaded)
			? []
			: $loaded;
	}

	/**
	 * Computes a string key to save the current list under to the store.
	 *
	 * @return string
	 */
	protected function orderingStorageKey()
	{
		return 'lists.' . $this->orderingScope . '.ordering';
	}
}
