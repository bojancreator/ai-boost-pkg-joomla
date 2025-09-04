<?php
/**
 * Project:                 4SEF
 *
 * @package                 4SEF
 * @copyright               Copyright Weeblr llc - 2022 -2025
 * @author                  Yannick Gaultier - Weeblr llc
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 2.6.2.644
 *
 * 2025-06-02
 */

namespace Weeblr\Wblib\Forsef\Messages;

use Weeblr\Wblib\Forsef\Db;
use Weeblr\Wblib\Forsef\System;

defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * A message to be stored and retrieve based on specification and user actions.
 *
 *
 */
class Message extends Db\Dataobject
{
	const TYPE_DANGER = '1_danger';
	const TYPE_WARNING = '2_warning';
	const TYPE_INFO = '3_info';

	const STATE_CREATED = 0;
	const STATE_PENDING = 1;
	const STATE_DISMISSED = 2;

	const DISMISS_TYPE_NONE = 0;
	const DISMISS_TYPE_POSTPONABLE = 1;
	const DISMISS_TYPE_DISMISSABLE = 2;

	const DELAY_5MN = 'PT5M';
	const DELAY_10MN = 'PT10M';
	const DELAY_15MN = 'PT15M';
	const DELAY_30MN = 'PT30M';
	const DELAY_1H = 'PT1H';
	const DELAY_24H = 'P1D';
	const DELAY_1W = 'P1W';
	const DELAY_2W = 'P2W';
	const DELAY_1M = 'P1M';
	const DELAY_3M = 'P3M';

	/**
	 * @var array Holds data read/written to db. Also a specification of the columns.
	 */
	protected $defaults = [
		'id'            => 0,
		'user_id'       => 0,
		'scope'         => 'default',
		'msg_id'        => '',
		'type'          => self::TYPE_INFO,
		'dismiss_type'  => self::DISMISS_TYPE_DISMISSABLE,
		'postpone_spec' => self::DELAY_24H,
		'title'         => '',
		'body'          => '',
		'created_at'    => null,
		'dismissed_at'  => null,
		'show_at'       => null,
		'state'         => self::STATE_CREATED
	];

	/**
	 * @var array List of max length per column.
	 */
	protected $autotrimSpec = [
		'title' => 190,
		'body'  => 2048,
	];

	/**
	 * Dismiss this message by setting its state.
	 *
	 * @return Message
	 * @throws \Exception
	 */
	public function dismiss()
	{
		if (!$this->isDismissable())
		{
			throw new \Exception(__METHOD__ . ': trying to dismiss a non-dismissable message - ' . $this->get('msg_id') . ' / id: ' . $this->getId());
		}

		if ($this->get('user_id') != $this->platform->getUser()->id)
		{
			throw new \Exception(__METHOD__ . ': trying to dismiss a dismissable message for another user - ' . $this->get('msg_id') . ' / id: ' . $this->getId());
		}

		return $this->set(
			'state',
			self::STATE_DISMISSED)
			->timestamp('dismissed_at')
			->store();
	}

	public function postpone()
	{
		if (!$this->isPostponable())
		{
			throw new \Exception(__METHOD__ . ': trying to postpone a non-postponable message - ' . $this->get('msg_id') . ' / id: ' . $this->getId());
		}

		if ($this->get('user_id') != $this->platform->getUser()->id)
		{
			throw new \Exception(__METHOD__ . ': trying to postpone a postponable message for another user - ' . $this->get('msg_id') . ' / id: ' . $this->getId());
		}

		return $this
			->set(
				[
					'state'   => self::STATE_PENDING,
					'show_at' => System\Date::toExtendedDateTime()
						->add(
							$this->get('postpone_spec')
						)->toMysql()
				]
			)->timestamp('dismissed_at')
			->store();
	}

	/**
	 * A chance to massage data before storing it. If returning false,
	 * the store operation is cancelled silently.
	 *
	 * @param $storeOptions Possible options when storing
	 *
	 * @return bool
	 */
	public function beforeStore($storeOptions = [])
	{
		$parentResult = parent::beforeStore();
		if ($parentResult && !$this->exists())
		{
			// record creation
			$this->data['created_at'] = System\Date::getUTCNow();
			$this->data['show_at']    = System\Date::getUTCNow();
			$this->data['user_id']    = $this->platform->getUser()->id;
		}

		return $parentResult;
	}

	private function isDismissable()
	{
		return $this->get('dismiss_type') & self::DISMISS_TYPE_DISMISSABLE;
	}

	private function isPostponable()
	{
		return $this->get('dismiss_type') & self::DISMISS_TYPE_POSTPONABLE;
	}
}
