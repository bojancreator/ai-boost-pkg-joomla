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
use Weeblr\Forseo\Helper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Referrers extends Db\Dataobjectlist
{
	/**
	 * @var string Name of the ordering column if none specified.
	 */
	protected $defaultOrderBy = 'url';

	/**
	 * @var array List of columns that can be searched when specified in a search query - syntax: column_id => search_term
	 */
	protected $searchableColumns = [
		'url'
	];

	/**
	 * Store information about managed data.
	 *
	 * @param string $dataObjectClass
	 */
	public function __construct($dataObjectClass = null)
	{
		$this->dataObjectClass = Data\Referrer::class;

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
	 * Get the list of referrers for a given Link.
	 *
	 * @param string $type links|errors
	 * @param array  $options
	 *
	 * @return array|\Exception
	 */
	public function getReferrers($type, $options)
	{
		try
		{
			switch ($type)
			{
				case 'links':
					$className = Data\Link::class;
					break;
				case 'errors':
					$className = Data\Error::class;
					break;
				default:
					return new \Exception('Internal error. See error log file.', System\Http::RETURN_BAD_REQUEST);
			}

			$url = $this->factory
				->getA($className)
				->load(
					Wb\arrayGet(
						$options,
						'id'
					)
				);

			return $this->getUrlReferrers(
				$url,
				$options
			);

		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s', __METHOD__, __LINE__, $e->getMessage());

			return new \Exception('Internal error. See error log file.', System\Http::RETURN_INTERNAL_ERROR);
		}
	}

	/**
	 * Get the list of referrers for a given Link.
	 *
	 * @param Data\Url $url
	 * @param array    $options
	 *
	 * @return array|\Exception
	 */
	protected function getUrlReferrers($url, $options)
	{
		try
		{
			$referrers = $this->queryDbForReferrers(
				$url,
				$options
			);

			$total       = count($referrers);
			$queryParams = $this->queryParams(
				$options,
				$total
			);

			$data = $this->queryDbForReferrers(
				$url,
				$options,
				$queryParams
			);

			$queryResult = [
				'data'  => empty($data) ? [] : $data,
				'count' => count($data)
			];

			return [
				'data' => $queryResult['data'],
				'meta' => [
					'count'   => $queryResult['count'],
					'total'   => $total,
					'current' => $queryParams['page'],
					'perPage' => $queryParams['perPage']
				]
			];

		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s', __METHOD__, __LINE__, $e->getMessage());

			return new \Exception('Internal error. See error log file.', System\Http::RETURN_INTERNAL_ERROR);
		}
	}

	/**
	 * Load existing referrers information for a provided Data\Url descendant.
	 * Returns an array indexed on referrer primary key.
	 *
	 * This is a variant of the standard getList() method, that fetches less data
	 * and does not do search or any particular processing that may be needed
	 * for referrers display in admin. It's purely used internally, when crawling
	 * the site.
	 *
	 * @param Data\Url $url
	 * @param array    $options
	 * @param array    $queryParams
	 *
	 * @return array
	 */
	protected function queryDbForReferrers(Data\Url $url, $options = [], $queryParams = [])
	{
		if (!$url->exists())
		{
			return [];
		}

		$referrersXTable = $this->getXRefTablename($url);

		$query = 'select'
				 . ' referrers.*'
				 . ' from ' . $this->dbHelper->quoteName('#__forseo_referrers') . ' as referrers'
				 . ' join ' . $this->dbHelper->quoteName($referrersXTable)
				 . ' on ' . $this->dbHelper->quoteName('referrers') . '.id = ' . $this->dbHelper->quoteName($referrersXTable) . '.referrer_id'
				 . ' where ' . $this->dbHelper->quoteName($referrersXTable) . '.referree_id' . ' = ' . $url->getId();

		$search = Wb\arrayGet($options, 'search');
		if (!empty($search))
		{
			$searchClause = $this->buildSearchWhereClause($options);
			if (!empty($searchClause))
			{
				$query .= ' and ' . $searchClause;
			}
		}

		$orderBy = Wb\arrayGet($options, 'orderBy', 'url');
		if (!empty($orderBy))
		{
			$query .= ' order by ' . $this->dbHelper->quoteName('referrers.' . $orderBy);
		}

		$offset      = Wb\arrayGet($queryParams, 'offset', 0);
		$perPage     = Wb\arrayGet($queryParams, 'perPage', 10);
		$limitClause = $this->dbHelper->buildLimitClause($offset, $perPage);
		if (!empty($limitClause))
		{
			$query .= $limitClause;
		}


		$referrers = $this->dbHelper
			->setQueryAnd($query)
			->loadAssocList();

		return empty($referrers)
			? []
			: $referrers;
	}

	/**
	 * Removes one or more referrers and any pages they refer to.
	 * Does nothing if not found.
	 *
	 * @param string|array $urls
	 */
	public function purgeReferrersByUrls($urls)
	{
		// identify referrers IDs
		$referrerUrls  = Wb\arrayEnsure($urls);
		$referrersKeys = $this->dbHelper->selectColumn(
			'#__forseo_referrers',
			$this->keyName,
			$this->dbHelper->quoteName('url') . ' in (' . $this->dbHelper->arrayToQuotedList($referrerUrls) . ')'
		);

		$this->purgeReferrersByKeys($referrersKeys);
	}

	/**
	 * Removes one or more referrers and any pages they refer to.
	 * Does nothing if not found.
	 *
	 * @param int|array $keys
	 */
	public function purgeReferrersByKeys($keys)
	{
		$keys = Wb\arrayEnsure($keys);
		if (empty($keys))
		{
			return;
		}

		// Delete any referral record by those ids
		$referrersTables = [
			'#__forseo_referrers_errors',
			'#__forseo_referrers_links',
			'#__forseo_referrers_pages',
		];

		$whereClause = $this->dbHelper->quoteName('referrer_id') . ' in (' . $this->dbHelper->arrayToQuotedList($keys) . ')';
		foreach ($referrersTables as $referrersTable)
		{
			$this->dbHelper->delete(
				$referrersTable,
				$whereClause
			);
		}

		// finally delete the referrers records themselves
		$this->factory
			->getA(Data\Referrer::class)
			->delete($keys);
	}

	/**
	 * Purge referrers for a provided Data\Url descendant.
	 *
	 * @param Data\Url $url
	 */
	public function purgeReferrersForUrl(Data\Url $url)
	{
		if (!$url->exists())
		{
			return;
		}

		$referrersXTable = $this->getXRefTablename($url);

		// we're only purging the referrer XRef. It may be that
		// a referrer does not refer to anything after deletion. We don't
		// check it and delete it if so as this would require checking
		// all other referree type XRef table.
		// We have a purge mechanism that runs on a regular basis, see PurgeUnused() method.
		$this->dbHelper
			->delete(
				$referrersXTable,
				[
					'referree_id' => $url->getId()
				]
			);
	}

	/**
	 * Search for referrers that do not refer to any error or link and
	 * delete them.
	 *
	 * When Errors or Links are deleted, their XRef with referrers are deleted
	 * but we do not check if those referrers (there can be many) are still used
	 * for any other Error or Link as it's likely too time consuming.
	 * To prevent them from accumulating, this method is called from a cron
	 * on a regular basis.
	 */
	public function purgeUnused()
	{
		$helper = $this->factory->getA(
			Helper\Task::class
		);

		if (!$helper->shouldRun('purgeReferrers'))
		{
			return;
		}


		// then delete referrers which don't refer to anything anymore.
		$where = [
			$this->dbHelper->quoteName('id'),
			' not in',

			'(',
			'select',
			$this->dbHelper->quoteName('referrer_id'),
			'from',
			$this->dbHelper->quoteName('#__forseo_referrers_errors'),
			')',

			' and ',

			$this->dbHelper->quoteName('id'),
			' not in',

			'(',
			'select',
			$this->dbHelper->quoteName('referrer_id'),
			'from',
			$this->dbHelper->quoteName('#__forseo_referrers_links'),
			')',
		];

		$this->dbHelper->delete(
			'#__forseo_referrers',
			implode(' ', $where)
		);

		// Update last run timestamp
		$helper->markRanAt('purgeReferrers');

		$this->factory->getThe('forseo.logger')
					  ->debug('purgeReferrers task: ran successfully.');
	}

	/**
	 * Builds the XRef table name for a Url descendant based
	 * on the URL type table name.
	 *
	 * @param Data\Url $url
	 *
	 * @return string
	 */
	private function getXRefTablename(Data\Url $url)
	{
		return str_replace(
			'#__forseo_',
			'#__forseo_referrers_',
			$url->tableName()
		);
	}

	/**
	 * Override handles special case where when searching for home page
	 * URL (ie /), we actually need to search for an empty URL.
	 * BUT: for referrers, this override is not needed because - for reasons
	 * when referrer is home page, we do store / as the referring URL.
	 * So no need to change / into "".
	 *
	 *
	 * @param string      $term
	 * @param null|string $column
	 * @return mixed
	 */
//	protected function rewriteSearchTerm($term, $column = null)

}
