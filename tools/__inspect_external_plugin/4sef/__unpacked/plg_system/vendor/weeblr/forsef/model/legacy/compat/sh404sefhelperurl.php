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

use Joomla\CMS\Router\Router as JoomlaRouter;
use Joomla\CMS\Uri;

use Weeblr\Forsef\Data;
use Weeblr\Forsef\Helper;

use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Wb;

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

	public static function clearUrlVar()
	{

	}

	public static function sortUrl()
	{

	}

	public static function setUrlVar()
	{

	}
}
