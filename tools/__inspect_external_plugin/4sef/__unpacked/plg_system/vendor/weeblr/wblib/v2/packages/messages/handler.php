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
 *
 */

namespace Weeblr\Wblib\Forsef\Messages;

use Weeblr\Wblib\Forsef\Api;
use Weeblr\Wblib\Forsef\Wb;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * API class, makes API available.
 */
class Handler extends Api\Handler
{
	/**
	 * Register all routes with the API layer.
	 */
	public function register($options = [])
	{
		$msgManager = Wb\arrayGet($options, 'msgManager');
		if (empty($msgManager))
		{
			throw new \Exception(__METHOD__ . ': no messages manager supplied when registering api handler.');
		}
		$apiController = $this->factory
			->getA(Controller::class)
			->setMsgManager(
				$msgManager
			);

		$this->api
			//
			// Change status ---------------------------------------------------------------------
			//
			->patch(
				$this->namespace,
				$this->version,
				'/messages/{id}',
				[
					$apiController,
					'patch',
				],
				$this->defaultApiOptions
			)
			//
			// Reset reminders -------------------------------------------------------------------
			//
			->patch(
				$this->namespace,
				$this->version,
				'/messages',
				[
					$apiController,
					'resetReminders',
				],
				$this->defaultApiOptions
			);

		return $this;
	}
}
