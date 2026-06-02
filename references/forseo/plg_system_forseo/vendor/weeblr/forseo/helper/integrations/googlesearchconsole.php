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

namespace Weeblr\Forseo\Helper\Integrations;

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Googlesearchconsole extends Base\Base
{
	/**
	 * Get the currently connected property and throws if none is set.
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function getCurrentProperty()
	{
		$property = $this->factory->getThis('forseo.config', 'integrations')->get('gscProperty');
		$property = Wb\arrayGet($property, 0);
		if (empty($property))
		{
			throw new \Exception('No property configured for Google Search Console access. Maybe try configuring it again.', System\Http::RETURN_INTERNAL_ERROR);
		}

		return $property;
	}

	/**
	 * Log a Google Search console error and return an exception.
	 *
	 * @param \Exception $exception
	 * @param string     $defaultMessage
	 * @return \Exception
	 */
	public function logConsoleError($exception, $defaultMessage)
	{
		$this->factory->getThe('forseo.logger')->error('Google Search console error. ' . print_r($exception->getErrors(), true));
		foreach ($exception->getErrors() as $error)
		{
			$singleMessage = Wb\arrayGet($error, 'message');
			if (!empty($singleMessage))
			{
				$message = $singleMessage;
				break;
			}
		}
		$message = empty($message)
			? $defaultMessage
			: $message;
		return new \Exception($message, $exception->getCode());
	}
}
