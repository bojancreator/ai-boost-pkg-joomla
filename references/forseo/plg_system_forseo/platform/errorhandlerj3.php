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
use Joomla\CMS\Exception;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Intercepts errors on Joomla 3. init method only called on the frontend.
 *
 * @package Weeblr\Forseo\Platform
 */
class Errorhandlerj3 extends Base\Base
{
	/**
	 * @var callable The upstream error handler, shared with all Joomla versions.
	 */
	private $appHandler = null;

	/**
	 * @var callable Pre-existing Joomla exception handler, used when 4SEO should not handle an exception.
	 */
	private $platformExceptionHandler = null;

	/**
	 * Hooks up with Joomla 3 event dispatching to intercept 404 errors.
	 *
	 * @param   callable  $appHandler
	 */
	public function init($appHandler)
	{
		$this->appHandler = $appHandler;

		\JError::setErrorHandling(
			E_ERROR,
			'callback',
			[
				$this,
				'handleError'
			]
		);

		$this->platformExceptionHandler = set_exception_handler(
			[
				$this,
				'exceptionProxy'
			]
		);
	}

	/**
	 * Proxy method for handling exception and passing them
	 * on to the shared error/Exception handler.
	 *
	 * Copied from Joomla redirect system plugin.
	 *
	 * @param   \Thowable|\Exception  $exception
	 *
	 * @throws \Throwable
	 */
	public function exceptionProxy($exception)
	{
		// If this isn't a Throwable then bail out
		if (!($exception instanceof \Throwable) && !($exception instanceof \Exception))
		{
			throw new InvalidArgumentException(
				sprintf('The error handler requires an Exception or Throwable object, a "%s" object was given instead.', get_class($exception))
			);
		}

		$this->handleError($exception);
	}

	/**
	 * Joomla 3 wrapper around error handling. Calls 4SEO error handler
	 * and pass-thru to previous Joomla error handler if 4SEO should not handle.
	 *
	 * @param   \Throwable| \Exception  $error
	 *
	 * @throws \Throwable
	 */
	public function handleError($error)
	{
		// pass on to 4SEO: if handled, it won't return.
		call_user_func_array(
			$this->appHandler,
			[$error]
		);

		// Proxy to the previous exception handler if available, otherwise just render the error page
		if ($this->platformExceptionHandler)
		{
			call_user_func_array(
				$this->platformExceptionHandler,
				[$error]
			);
		}
		else
		{
			Exception\ExceptionHandler::render($error);
		}
	}
}

