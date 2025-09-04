<?php
/**
 * 4SEF
 *
 * @package          4SEF
 * @copyright        Copyright Weeblr llc - 2022 -2025
 * @author           Yannick Gaultier - Weeblr llc
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          2.6.2.644
 * @date        2025-06-02
 */

namespace Weeblr\Wblib\Forsef\Messages;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Api;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Controller extends Api\Controller
{
	/**
	 * @var Manager Messages manager instance to use when dismissing or postponing messages.
	 */
	protected $msgManager = null;

	/**
	 * Setter for the messages manager to use.
	 *
	 * @param   Manager  $manager
	 *
	 * @return Controller
	 */
	public function setMsgManager($manager)
	{
		$this->msgManager = $manager;

		return $this;
	}

	/**
	 * Change the state of a specific message..
	 *
	 * @param   Api\Request  $request
	 * @param   array        $options
	 *
	 * @return array|\Exception
	 */
	public function patch($request, $options)
	{
		$messageId = (int) Wb\arrayGet($options, 'id', 0);
		if (empty($messageId))
		{
			return new \Exception('Trying to dismiss or postpone a message without providing a message id.', System\Http::RETURN_NOT_FOUND);
		}

		try
		{
			$newState = $request->getBody();
			if (
				empty($newState)
				||
				!Wb\arrayIsSet($newState, 'state')
			)
			{
				return new \Exception('Trying to dismiss or postpone a message with invalid new state.', System\Http::RETURN_NOT_FOUND);
			}

			$newState = (int) Wb\arrayGet($newState, 'state');
			switch ($newState)
			{
				case Message::STATE_DISMISSED:
					$this->msgManager->dismiss($messageId);
					break;
				case Message::STATE_PENDING:
					$this->msgManager->postpone($messageId);
					break;
				default:
					return new \Exception('Trying update a message state with an invalid state.', System\Http::RETURN_NOT_FOUND);
			}

		}
		catch (\Throwable $e)
		{
			System\Log::libraryError(
				sprintf('%s::%d: %s', $e->getFile(), $e->getLine(), $e->getMessage())
			);

			return new \Exception('Error trying to dismiss or postpone a message. More details have been logged on the server.', 500);
		}

		return [
			'status' => System\Http::RETURN_NO_CONTENT
		];
	}

	/**
	 * Change the state of a specific message..
	 *
	 * @param   Api\Request  $request
	 * @param   array        $options
	 *
	 * @return array|\Exception
	 */
	public function resetReminders($request, $options)
	{
		try
		{
			$this->msgManager->resetReminders();
		}
		catch (\Throwable $e)
		{
			System\Log::libraryError(
				sprintf('%s::%d: %s', $e->getFile(), $e->getLine(), $e->getMessage())
			);

			return new \Exception('Error trying to reset all postponed messages. More details have been logged on the server.', 500);
		}

		return [
			'status' => System\Http::RETURN_NO_CONTENT
		];
	}
}
