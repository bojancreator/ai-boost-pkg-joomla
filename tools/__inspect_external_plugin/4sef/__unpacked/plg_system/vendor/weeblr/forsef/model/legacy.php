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

namespace Weeblr\Forsef\Model;

use Joomla\CMS\Router\Router as JoomlaRouter;
use Joomla\CMS\Uri;

use Weeblr\Forsef\Data;
use Weeblr\Forsef\Helper;

use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Wb;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Legacy extends Base\Base
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

	const functionsDef = [
//		'defines.php',
//		'language.php',
//		'pluginsupport.php',
//		'routing.php',
//		'sh404sef.class.php'
	];

	const classesDef = [
//		'sef_404'                    => 'sef404.php',
//		'Sh404sefClassBaseextplugin' => 'sh404sefclassbaseextplugin.php',
//		'Sh404sefClassConfig'        => 'sh404sefclassconfig.php',
//		'Sh404sefFactory'            => 'sh404seffactory.php',
//		'Sh404sefHelperGeneral'      => 'sh404sefhelpergeneral.php.php',
//		'Sh404sefHelperUrl'          => 'sh404sefhelperurl.php',
//		'Sh404sefModelSlugs'         => 'sh404sefmodelslugs.php',
//		'Sh404sefClassPageinfo'      => 'sh404sefclasspageinfo.php',
//		'ShlDbHelper'                => 'shldbhelper.php',
//		'shSEFConfig'                => 'shsefconfig.php',
//		'ShlSystem_Log'              => 'shlsystemlog.php',
	];

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

	/**
	 * Set up the minimal context required to build SEF URLs using the sh404SEF legacy context.
	 *
	 * @return Legacy
	 */
	public function enableBuildContext()
	{
		array_map(
			function ($file) {
				include_once __DIR__ . '/legacy/compat/' . $file;
			},
			self::functionsDef
		);

		$this->factory->getThe('autoloader')->registerClasses(
			$this->prependRootPath(
				self::classesDef,
				__DIR__ . '/legacy/compat'
			)
		);


		return $this;
	}

	private function prependRootPath($relativeDefs, $rootPath)
	{
		$defs = [];
		foreach ($relativeDefs as $key => $file)
		{
			$defs[$key] = $rootPath . '/' . $file;
		}

		return $defs;
	}

	/**
	 * Set up the minimal context required to parse SEF URLs using the sh404SEF legacy context.
	 *
	 * @return Legacy
	 */

	public function enableParseContext()
	{

		return $this;
	}
}
