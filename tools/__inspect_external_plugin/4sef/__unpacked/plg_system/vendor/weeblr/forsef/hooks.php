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

namespace Weeblr\Forsef;

use Weeblr\Forsef\Controller;
use Weeblr\Forsef\Model;

use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\System;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Hooks related to 4SEF application.
 *
 * @package Weeblr\Forsef
 */
class Hooks extends Base\Base
{
	public function add()
	{
		$hook = $this->factory->getThe('hook');

		/********************************************************************************************************
		 * Cron events
		 *******************************************************************************************************/

		// run crawl
		$hook->add(
			'forsef_cron',
			[
				$this->factory
					->getA(Controller\Crawler::class),
				'fromCron'
			]
		);

		$hook->add(
			'forsef_cron',
			[
				$this->factory
					->getA(Model\Referrers::class),
				'purgeUnused'
			],
			System\Hook::PRIORITY_LOW
		);

		$hook->add(
			'forsef_cron',
			[
				$this->factory
					->getA(Model\Errors::class),
				'purgeAfter'
			],
			System\Hook::PRIORITY_LOW
		);

		/********************************************************************************************************
		 * Before parsing request, execute legacy shURLs
		 *******************************************************************************************************/

		if ($this->platform->isFrontend())
		{
			// Execute any shURL redirect.
			$hook->add(
				'forsef_request_path_to_parse',
				function ($requestToParse) {
					if ($this->factory->getThis('forsef.config', 'sh404sef')->isTruthy('executeShurls'))
					{
						$this->factory->getA(Model\Shurls::class)->execute();
					}

					return $requestToParse;
				}
			);
		}

		$hook->add(
			'forsef_onAfterInitialise',
			[
				$this->factory->getThe('forsef.platformController'),
				'configure'
			]
		);

		/********************************************************************************************************
		 * onAfterRoute:
		 *******************************************************************************************************/

		/********************************************************************************************************
		 * onContentPrepare
		 *******************************************************************************************************/

		/********************************************************************************************************
		 * onAfterDispatch
		 *******************************************************************************************************/

		/********************************************************************************************************
		 * onAfterDispatchComplete: Fake event, hopefully running after all onAfterDispatch handlers have ran.
		 *******************************************************************************************************/

		/********************************************************************************************************
		 * onBeforeRender
		 *******************************************************************************************************/

		/********************************************************************************************************
		 * onBeforeCompileHead
		 *******************************************************************************************************/

		/********************************************************************************************************
		 * onAfterRender
		 *******************************************************************************************************/

		/********************************************************************************************************
		 * onAfterRenderComplete: Fake event, hopefully running after all onAfterRender handlers have ran.
		 *******************************************************************************************************/

		/********************************************************************************************************
		 * onAfterRespond
		 *******************************************************************************************************/

	}
}
