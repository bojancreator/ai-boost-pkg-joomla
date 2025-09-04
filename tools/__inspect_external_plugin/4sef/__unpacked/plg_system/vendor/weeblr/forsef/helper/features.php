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

namespace Weeblr\Forsef\Helper;

use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\Factory;

use Weeblr\Forsef\Model;

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
			self::$appConfig = Factory::get()->getThis('forsef.config', 'app');
		}

		return self::$appConfig->isTruthy(
			[
				'features',
				$feature
			]
		);
	}
}