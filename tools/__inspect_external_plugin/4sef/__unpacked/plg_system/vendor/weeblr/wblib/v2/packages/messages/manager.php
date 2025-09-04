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

use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\System;

defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * Manages a message/notification center
 *
 *
 */
class Manager extends Base\Base
{
	/**
	 * @var string Namespace for the message manager, used by the API
	 */
	private $namespace = '';

	/**
	 * @var array Stores all options for the message manager: database table and any future option.
	 */
	protected $options = [];

	/**
	 * Validates and stores manager options.
	 *
	 * @param   array  $options
	 *                      string scope scope to use
	 *                      string table database table name
	 *                      array defaultApiOptions default acess options
	 *
	 * @throws \Exception
	 */
	public function __construct($options)
	{
		parent::__construct($options);

		$table = Wb\arrayGet($options, 'table');
		if (empty($table))
		{
			throw new \Exception(__METHOD__ . ': missing database table name trying to create a message manager instance.');
		}

		$this->options['table'] = $table;

		// We have a valid definition, register this instance with the corresponding API endpoint.
		$defaultApiOptions = Wb\arrayGet($options, 'defaultApiOptions');
		if (empty($defaultApiOptions))
		{
			throw new \Exception(__METHOD__ . ': missing default API options trying to create a message manager instance.');
		}

		$registered = $this->factory
			->getA(
				Handler::class,
				[
					'namespace'         => Wb\arrayGet($options, 'namespace'),
					'version'           => Wb\arrayGet($options, 'version'),
					'defaultApiOptions' => $defaultApiOptions
				]
			)->register(
				[
					'msgManager' => $this
				]
			);

		if (!$registered)
		{
			throw new \RuntimeException(__METHOD__ . ': Failed registering messages manager API with wbLib, aborting.');
		}
	}

	/**
	 * Get all messages that should be displayed.
	 *
	 * @return array
	 */
	public function get($options = [])
	{
		$db = $this->factory->getThe('db');

		$where = [];

		$scope = Wb\arrayGet($options, 'scope');
		if (!empty($scope))
		{
			$where[] = $db->qn('scope') . ' = ' . $db->q($scope);
			$where[] = 'and';
		}

		$where[] = $db->qn('user_id') . ' = ' . $db->q($this->platform->getUser()->id);
		$where[] = 'and';

		$where[] = '(';

		$where[] = $db->qn('state') . ' = ' . $db->q(Message::STATE_CREATED);

		$where[] = 'or';

		$where[] = '('
			. $db->qn('state') . ' = ' . $db->q(Message::STATE_PENDING)
			. ' and '
			. $db->qn('show_at') . ' < ' . $db->q(System\Date::getUTCNow())
			. ')';

		$where[] = ')';


		$displayableMessages = $this->factory->getThe('db')->selectAssocList(
			$this->options['table'],
			'*',
			implode(' ', $where),
			[],
			[
				'type'    => 'asc',
				'show_at' => 'asc'
			]
		);

		return empty($displayableMessages)
			? []
			: $displayableMessages;
	}

	/**
	 * Adds a message, checking a number of conditions before doing so.
	 * Messages can be:fixed, dismissable, postponable or closable.
	 *
	 * If fixed or closable, message is added only if same message does not exist already
	 * If dismissable or postponable, it is added except if same message exists but is pending dismissing or postponing.
	 *
	 * @param   array  $msg  Holds the message data
	 *                       string msg_id Unique message type identifier
	 *                       string title 190
	 *                       string body 2048
	 *                       string type see TYPE_* constants
	 *                       int dismiss_type Combination of binary flags, see DISMISS_TYPE* constants
	 *                       string postpone_spec A PHP period spec, required if dismiss_types includes DISMISS_TYPE_POSTPONABLE
	 *                       string scope "default" optional scope
	 *
	 * @return Message|bool The message object if added, false is already existing or some other reason no to add.
	 * @throw \Exception
	 */
	public function add($msg)
	{
		if ($this->platform->isGuest())
		{
			return false;
		}

		$dismissType = Wb\arrayGet($msg, 'dismiss_type', Message::DISMISS_TYPE_DISMISSABLE);

		// any existing such message?
		$existings = $this->factory->getThe('db')->selectObjectList(
			$this->options['table'],
			['id', 'state'],
			[
				'scope'   => Wb\arrayGet($msg, 'scope', 'default'),
				'msg_id'  => Wb\arrayGet($msg, 'msg_id'),
				'user_id' => $this->platform->getUser()->id,
				['dismiss_type', '&', $dismissType]
			]
		);

		if (Message::DISMISS_TYPE_NONE == $dismissType
			&&
			count($existings) > 0
		)
		{
			return false;
		}

		if (
			$dismissType & Message::DISMISS_TYPE_DISMISSABLE
			&&
			count($existings) > 0
		)
		{
			foreach ($existings as $existing)
			{
				if (Message::STATE_DISMISSED == $existing->state)
				{
					return false;
				}
			}
		}

		if (
			$dismissType & Message::DISMISS_TYPE_POSTPONABLE
			&&
			count($existings) > 0
		)
		{
			foreach ($existings as $existing)
			{
				if (Message::STATE_DISMISSED != $existing->state)
				{
					return false;
				}
			}
		}

		return $this->factory
			->getA(
				Message::class,
				$this->options['table']
			)->set($msg)
			->store();
	}

	public function dismiss($id)
	{
		$existing = $this->factory
			->getA(
				Message::class,
				$this->options['table']
			)->load($id);

		if (!$existing->exists())
		{
			throw new \Exception(__METHOD__ . ': Trying to dismiss non-existing message.');
		}

		if ($existing->get('state') == Message::STATE_DISMISSED)
		{
			return $existing;
		}

		return $existing->dismiss();
	}

	public function postpone($id)
	{
		$existing = $this->factory
			->getA(
				Message::class,
				$this->options['table']
			)->load($id);

		if (!$existing->exists())
		{
			throw new \Exception(__METHOD__ . ': Trying to postpone non-existing message.');
		}

		if ($existing->get('state') == Message::STATE_DISMISSED)
		{
			return $existing;
		}

		return $existing->postpone();
	}

	/**
	 * Permanently delete a message, without any condition.
	 *
	 * @param   int  $id
	 *
	 * @throws \Exception
	 */
	public function delete($id)
	{
		$existing = $this->factory
			->getA(
				Message::class,
				$this->options['table']
			)->load($id);

		if (!$existing->exists())
		{
			throw new \Exception(__METHOD__ . ': Trying to postpone non-existing message.');
		}

		$existing->delete()();
	}

	/**
	 * Permanently delete a message by type if active (ie not dismissed).
	 * Useful for messages where the triggering condition has disappeared before
	 * user has dismissed the message.
	 *
	 * @param   int  $msgId
	 *
	 * @return Manager
	 * @throws \Exception
	 */
	public function deleteByMsgId($msgId)
	{
		if (empty($msgId))
		{
			throw new \Exception(__METHOD__ . ': trying to delete a message group but msgId is empty.');
		}

		$this->factory
			->getThe('db')
			->delete(
				$this->options['table'],
				[
					['msg_id', '=', $msgId],
					['state', '!=', Message::STATE_DISMISSED]
				]
			);

		return $this;
	}

	/**
	 * Set all "Remind me later" messages to be displayed again.
	 *
	 * Useful in case of too quick a click.
	 *
	 * @return Manager
	 * @throw \Exception
	 */
	public function resetReminders()
	{
		$this->factory
			->getThe('db')
			->update(
				$this->options['table'],
				[
					'show_at' => System\Date::getUTCNow()
				],
				[
					['state', '=', Message::STATE_PENDING],
					['dismiss_type', '&', Message::DISMISS_TYPE_POSTPONABLE]
				]
			);

		return $this;
	}

	/**
	 * Purge all records that seems safe to get rid of:
	 *
	 * - closable that are closed (unless they are dimissable)
	 * - dismissable that are dismissed
	 */
	public function purge()
	{
	}
}