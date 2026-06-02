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

class Errors extends Db\Dataobjectlist
{
	/**
	 * @var string Name of the ordering column if none specified. Prefix with - for DESC ordering.
	 */
	protected $defaultOrderBy = '-hits';

	/**
	 * Store information about managed data.
	 *
	 * @param string $dataObjectClass
	 */
	public function __construct($dataObjectClass = null)
	{
		$this->dataObjectClass = Data\Error::class;

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
	 * Method to extend the default where clause.
	 *
	 * @param array $options
	 * @param array $clause
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	protected function extendWhereClause($options, $clause)
	{
		$statusClause = $this->buildStatusWhereClause($options);
		if (!empty($statusClause))
		{
			$clause[] = $statusClause;
		}

		$lastHitClause = $this->buildLastHitWhereClause($options);
		if (!empty($lastHitClause))
		{
			$clause[] = $lastHitClause;
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
			case '0':
			case '404':
			case '500':
				$clause = $this->dbHelper->quoteName('status')
						  . ' = ' . $status;
				break;
			case 'others':
				$clause = $this->dbHelper->quoteName('status') . ' != 0 '
						  . ' and '
						  . $this->dbHelper->quoteName('status') . ' != 404 ';
				break;
		}

		return $clause;
	}

	/**
	 * Build a where clause taking into account desired
	 * date range for last_hit.
	 *
	 * @param Array $options
	 *
	 * @return string
	 * @throws \Exception
	 */
	protected function buildLastHitWhereClause($options)
	{
		$range = Wb\arrayGet(
			$options,
			'last_hit',
			''
		);

		$clause = '';
		if (
			empty($range)
			||
			'all' == $range
		) {
			return $clause;
		}

		switch ($range)
		{
			case '1h':
				$range = 'PT1H';
				break;
			case '1d':
				$range = 'P1D';
				break;
			case '2d':
				$range = 'P2D';
				break;
			case '1w':
				$range = 'P1W';
				break;
		}

		if (!empty($range))
		{
			$now    = System\Date::toExtendedDateTime();
			$clause = $this->dbHelper->quoteName('last_hit')
					  . ' >= '
					  . $this->dbHelper->quote(
					$now->sub($range)
						->toMysql()
				);
		}

		return $clause;
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
	public function purgeAfter()
	{
		$purgeErrorsAfter = $this->factory
			->getThis(
				'forseo.config',
				'system'
			)->get(
				'purgeErrorsAfter',
				['P1M']
			);

		$purgeErrorsAfter = is_array($purgeErrorsAfter)
			? ''
			: $purgeErrorsAfter;

		if (!in_array($purgeErrorsAfter, ['P1D', 'P7D', 'P1M', 'P3M', '']))
		{
			$purgeErrorsAfter = '';
		}

		if (empty($purgeErrorsAfter))
		{
			// set to never purge errors or invalid value
			return;
		}

		$helper = $this->factory->getA(
			Helper\Task::class
		);

		if (!$helper->shouldRun('purgeErrors'))
		{
			// set to run this task only once or twice a day, see system config.
			return;
		}


		// then delete errors older than selected
		$now = System\Date::toExtendedDateTime();
		$this->dbHelper->delete(
			'#__forseo_errors',
			[
				['status', '>', 400],
				['last_hit', '<', $now->sub($purgeErrorsAfter)->toMysql()]
			]
		);

		// Update last run timestamp
		$helper->markRanAt('purgeErrors');

		$this->factory->getThe('forseo.logger')
					  ->debug('purgeErrors task: ran successfully.');
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
