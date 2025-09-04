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

namespace Weeblr\Forsef\Helper;

use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Db;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Task extends Base\Base
{
	/**
	 * @var System\Config System configuration instance.
	 */
	private $config = null;

	/**
	 * @var Db\Keystore Convenience app keystore instance.
	 */
	private $store = null;

	/**
	 * @var System\Logger Convenience logger instance.
	 */
	private $logger = null;

	/**
	 * String prefix to identify timed tasks in the keystore.
	 */
	const PREFIX = 'tasks.lastRunAt';

	/**
	 * String prefix used in system configuration to designate period between 2 consecutive run of a task.
	 */
	const CONFIG_PREFIX = 'tasks.period';

	/**
	 * Store a logger and config for convenience.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->config = $this->factory->getThis('forsef.config', 'system');
		$this->store  = $this->factory->getThe('forsef.keystore');
		$this->logger = $this->factory->getThe('forsef.logger');
	}

	/**
	 * Search the app keystore for a lastRunAt.{$id} record, and compare it
	 * to any period.{$id} from the config. If last run at was earlier than
	 * the period, then returns true.
	 *
	 * @param string $id
	 *
	 * @return bool
	 */
	public function shouldRun($id)
	{
		$lastRunAt = $this->store->get(
			self::PREFIX . '.' . $id
		);

		$timePeriod = $this->config
			->get(
				static::CONFIG_PREFIX . '.' . $id,
				'P1D'
			);

		if (
			!empty($lastRunAt)
			&&
			!System\Date::toExtendedDateTime(
				$lastRunAt
			)->isBeforeBy(
				'now',
				$timePeriod
			)
		)
		{
			// not a good time
			$this->factory->getThe('forsef.logger')
						  ->debug($id . ' task: not running, not enough time since last run.');

			return false;
		}

		return true;
	}

	/**
	 * Timestamp the designated task as ran in the app keystore.
	 *
	 * @param string $id
	 */
	public function markRanAt($id)
	{
		$this->store->put(
			self::PREFIX . '.' . $id,
			System\Date::getUTCNow()
		);

		$this->factory->getThe('forsef.logger')
					  ->debug($id . ' task: updated lastRunAt timestamp after run.');
	}
}