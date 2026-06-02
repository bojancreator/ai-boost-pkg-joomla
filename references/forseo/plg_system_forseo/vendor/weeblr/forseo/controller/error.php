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

namespace Weeblr\Forseo\Controller;

use Weeblr\Forseo\Platform\Helpers as PlatformHelpers;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\Customerror;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Error extends Base\Base
{
	/**
	 * List of PHP error codes that will be recorded as errors
	 * through our fatal error handler.
	 */
	public const HANDLED_FATAL_ERRORS_TYPES = [
		E_ERROR,
		E_PARSE,
		E_CORE_ERROR,
		E_COMPILE_ERROR,
		E_USER_ERROR,
	];

	/**
	 * Initialize error interception on platform.
	 */
	public function init()
	{
		try
		{
			if (!$this->platform->isFrontend())
			{
				return;
			}

			$platformVersion          = $this->platform->majorVersion();
			$classVersion             = $platformVersion > 3
				? 'default'
				: $platformVersion;
			$platformHandlerClassName = '\Weeblr\Forseo\Platform\\Errorhandlerj' . $classVersion;
			$platformHandler          = $this->factory->getA($platformHandlerClassName);
			$platformHandler->init(
				[
					$this,
					'errorHandler'
				]
			);

			// Install a global handler to catch fatal errors
			register_shutdown_function(
				[
					$this,
					'fatalErrorHandler'
				]
			);
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('Error initializing platform error interception: %s:%d %d:%s - %s', $e->getFile(), $e->getLine(), $e->getCode(), $e->getMessage(), $e->getTraceAsString());
		}
	}

	/**
	 * A shutdown function to process fatal error that cannot be caught otherwise.
	 */
	public function fatalErrorHandler()
	{
		$error = error_get_last();
		if ($error !== null)
		{
			$errorType = Wb\arrayGet($error, 'type', 0);
			if (!in_array($errorType, self::HANDLED_FATAL_ERRORS_TYPES))
			{
				return;
			}

			$errorObject = new Customerror\Fatal(
				Wb\arrayGet($error, 'message', 'Unknown'),
				0,
				null,
				[
					'line' => Wb\arrayGet($error, 'line', 0),
					'file' => Wb\arrayGet($error, 'file', ''),
					'type' => $errorType
				]
			);
			$this->factory
				->getThe('forseo.pageDataCollector')
				->onError(
					$errorObject
				);
		}
	}

	/**
	 * Handle platforms errors.
	 *
	 * @param \Exception $error
	 */
	public function errorHandler($error)
	{
		$errorCode = (int)$error->getCode();

		$this->factory
			->getThe('forseo.requestInfo')
			->set('page_status', $errorCode);

		// apply user rules, some redirects or URL blocking may apply on errors.
		$rulesController = $this->factory
			->getA(Rules::class)
			->filterExecutableRules();
		$rulesController->executeRedirects();
		$this->factory->getThe('forseo.aliasesController')
					  ->execute();
		$rulesController->executeWaf();

		// are we in Debug mode: if so, do let the platform handle the error
		if (!empty($this->platform->getConfig()->get('debug', 0)))
		{
			return;
		}

		switch ($errorCode)
		{
			case 404:

				$this->factory->getA(
					PlatformHelpers\Urls::class
				)->enforceLowerCaseUrls();

				// Possibly check platform redirects before actually
				// rendering the 404.
				$this->checkComRedirects();

				/**
				 * Run hook with the 404 error to allow actions by other parties.
				 *
				 * @api     forseo
				 * @package 4SEO\action\error
				 * @var forseo_on_404_error
				 * @since   1.0.0
				 *
				 * @param \Exception $error
				 *
				 * @return void
				 *
				 */
				$this->factory->getThe('hook')->run(
					'forseo_on_404_error',
					$error
				);

				break;
			case 500:
				// possibly log 500 and other errors in the future
			default:
				// returning to the platform handlers will cause them
				// to pass the error to normal error processing chain.
				/**
				 * Run hook with the error to allow actions by other parties.
				 *
				 * @api     forseo
				 * @package 4SEO\action\error
				 * @var forseo_on_error
				 * @since   1.0.0
				 *
				 * @param \Exception $error
				 *
				 * @return void
				 *
				 */
				$this->factory->getThe('hook')->run(
					'forseo_on_error',
					$error
				);
				break;
		}
	}

	/**
	 * Check com_redirects for user defined redirects. Should only be called in case a 404 happened.
	 *
	 * @return void
	 */
	private function checkComRedirects()
	{

		/**
		 * Whether to check Joomla user-defined redirects on 404s. The check happens
		 * before searching any applicable 4SEO error rule, so that we can have
		 * error rules and also check redirects.
		 * However, this does not work on J3 as the redirect plugin triggers a 404
		 * if no redirect is found. So we disable com_redirect check on J3, with
		 * this filter allowing user to enable it back if needed.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\error
		 * @var forseo_on_404_error_check_com_redirect
		 * @since   4.5.0
		 *
		 * @param \bool $checkComRedirects
		 *
		 * @return void
		 *
		 */
		$checkComRedirects = $this->factory->getThe('hook')->filter(
			'forseo_on_404_error_check_com_redirect',
			version_compare(
				$this->platform->majorVersion(),
				'3',
				'>'
			)
		);

		if ($checkComRedirects)
		{
			$this->factory->getA(PlatformHelpers\Comredirect::class)
						  ->checkRedirects();
		}
	}
}
