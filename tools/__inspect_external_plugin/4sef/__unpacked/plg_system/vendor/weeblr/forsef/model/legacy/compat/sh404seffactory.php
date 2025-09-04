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

use Weeblr\Wblib\Forsef\Factory;
use Weeblr\Wblib\Forsef\Base;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Sh404sefFactory extends Base\Base
{
	/**
	 * @var System\Logger Convenience logger instance.
	 */
	private $logger;

	/**
	 * @var Config
	 */
	private $appConfig;

	/**
	 * @var Config
	 */
	private $routingConfig;

	/**
	 * @var Config
	 */
	private $legacyConfig;

	/**
	 * @var Stores legacy routing config rendered as a stdClass
	 */
	private static $renderedConfig;

	/**
	 * @param array $options Can inject custom factory and platform.
	 */
	public function __construct($options = [])
	{
		parent::__construct();

		$this->logger        = $this->factory->getThe('forsef.logger');
		$this->appConfig     = $this->factory->getThis('forsef.config', 'app');
		$this->routingConfig = $this->factory->getThis('forsef.config', 'routing');
		$this->legacyConfig  = $this->factory->getThis('forsef.config', 'legacy');
	}

	public static function getConfig()
	{
		if (is_null(self::$renderedConfig))
		{
			$legacyConfig = Factory::get()
								   ->getThis('forsef.config', 'legacy')
								   ->toArray();

			self::$renderedConfig = new \shSEFConfig();
//			foreach($legacyConfig as $key => $value) {
//				self::$renderedConfig[$key]
//			}

		}

		return self::$renderedConfig;
	}

	/**
	 * Create and return an object holding a set of
	 * data on the current request
	 *
	 * @return Sh404sefClassPageinfo|null
	 */
	public static function getPageInfo()
	{
		static $instance = null;

		if (is_null($instance))
		{
			$instance = new \Sh404sefClassPageinfo();
		}

		return $instance;
	}
}
