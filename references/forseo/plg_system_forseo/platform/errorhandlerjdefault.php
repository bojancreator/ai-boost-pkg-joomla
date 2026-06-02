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

namespace Weeblr\Forseo\Platform;

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Joomla\CMS\Factory;
use Joomla\CMS\Event as PlatformEvent;
use Joomla\Event;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Intercepts errors on Joomla 4. init method only called on the frontend.
 *
 * @package Weeblr\Forseo\Platform
 */
class Errorhandlerjdefault extends Base\Base implements Event\SubscriberInterface, Event\DispatcherAwareInterface
{
	use Event\DispatcherAwareTrait;

	/**
	 * @var callable The upstream error handler, shared with all Joomla versions.
	 */
	private $appHandler = null;

	/**
	 * Loads up the Joomla 4 application dispatcher.
	 *
	 * @throws \Exception
	 */
	public function __construct()
	{
		// Use a dedicated dispatcher
		$this->setDispatcher(
			Factory::getApplication()->getDispatcher()
		);
	}

	/**
	 * Hooks up with Joomla 4 event dispatching to intercept 404 errors.
	 *
	 * @param   callable  $appHandler
	 */
	public function init($appHandler)
	{
		$this->appHandler = $appHandler;
		// register for onError event
		$this->getDispatcher()->addSubscriber($this);
	}

	public static function getSubscribedEvents(): array
	{
		return [
			'onError' => ['handleError', Event\Priority::MAX],
		];
	}

	/**
	 * Joomla 4+ wrapper around error handling. Calls 4SEO error handler
	 * and pass-thru to previous Joomla error handler if 4SEO should not handle.
	 *
	 * @param   PlatformEvent\ErrorEvent  $event  The event object
	 *
	 */
	public function handleError(PlatformEvent\ErrorEvent $event)
	{
		// pass on to 4SEO: if handled, it won't return.
		call_user_func_array(
			$this->appHandler,
			[$event->getError()]
		);
	}
}

