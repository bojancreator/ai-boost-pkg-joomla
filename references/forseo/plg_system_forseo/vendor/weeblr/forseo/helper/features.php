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

namespace Weeblr\Forseo\Helper;

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\Factory;

use Weeblr\Forseo\Model;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Features extends Base\Base
{
	/**
	 * @var Model\Config Convenience instance of the application config config model.
	 */
	private static $appConfig = null;

	/**
	 * Facade for the features item in the app config.
	 *
	 * @param string $feature
	 *
	 * @return mixed
	 */
	public static function isEnabled(string $feature)
	{
		if (is_null(self::$appConfig))
		{
			self::$appConfig = Factory::get()->getThis('forseo.config', 'app');
		}

		return self::$appConfig->isTruthy(
			[
				'features',
				$feature
			]
		);
	}
}