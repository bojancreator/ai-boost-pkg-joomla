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

namespace Weeblr\Forsef\Data;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Db;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Base\Dataobject;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Manages dailies statistics.
 */
class Statsdailies extends Db\Dataobject
{
	/**
	 * @var string Database table associated with this instance.
	 */
	protected $table = '#__forsef_stats_dailies';

	/**
	 * @var array Holds data read/written to db. Also a specification of the columns.
	 */
	protected $defaults = [

		'period_start' => null,
		'hits'         => 0,
		'hits_bots'    => 0,
		'hits_se'      => 0
	];

	/**
	 * @var string SQL Datetime for the start of the current day (UTC)
	 */
	protected $periodStart;

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

		$this->periodStart = System\Date::getUTCNow('Y-m-d') . ' 00:00:00';
	}

	/**
	 * Record a single hit for the current day.
	 *
	 * @throws \Exception
	 */
	public function storeHit()
	{
		try
		{
			$this->loadPerColumn(
				'period_start',
				$this->periodStart
			)->set(
				'period_start',
				$this->periodStart
			)->increment(
				'hits'
			)->store();
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forsef.logger')->error(__METHOD__ . ', %s::%d %s %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
	}

}
