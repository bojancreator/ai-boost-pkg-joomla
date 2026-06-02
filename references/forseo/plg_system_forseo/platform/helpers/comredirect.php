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

namespace Weeblr\Forseo\Platform\Helpers;

use Joomla\CMS\Factory;
use Joomla\CMS\Event\AbstractEvent;
use Joomla\CMS\Event\ErrorEvent;

use Joomla\CMS\Router\Exception\RouteNotFoundException;

use Weeblr\Wblib\Forseo\Base;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Helper to check if a redirect exists for the current requested URL.
 */
class Comredirect extends Base\Base
{
	/**
	 * Check if current request matches a stored redirect and
	 * execute it if so, by using the native Redirect plugin.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function checkRedirects()
	{
		$exception = new RouteNotFoundException();

		try
		{
			$platformVersion = $this->platform->majorVersion();

			// J4+
			if (version_compare($platformVersion, '3', '>'))
			{
				$app    = Factory::getApplication();
				$plugin = $app->bootPlugin('redirect', 'system');
				if (
					empty($plugin)
					||
					empty($plugin->params)
				) {
					// plugin is disabled
					return;
				}

				$errorEvent = AbstractEvent::create(
					'onError',
					[
						'subject'     => $exception,
						'eventClass'  => ErrorEvent::class,
						'application' => $app,
					]
				);
				$plugin->handleError($errorEvent);
			}

			// J3
			if (
				version_compare($platformVersion, '4', '<')
				&&
				class_exists(\PlgSystemRedirect::class)
			) {
				\PlgSystemRedirect::handleException(
					$exception
				);
			}
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
	}
}
