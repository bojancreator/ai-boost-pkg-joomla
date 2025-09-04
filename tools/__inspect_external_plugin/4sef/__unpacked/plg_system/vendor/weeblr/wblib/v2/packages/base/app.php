<?php
/**
 * Project:                 4SEF
 *
 * @author                  Yannick Gaultier - Weeblr llc
 * @copyright               Copyright Weeblr llc - 2022 -2025
 * @package                 4SEF
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 @build_version_full_build@
 *
 * 2025-06-02
 */

namespace Weeblr\Wblib\Forsef\Base;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\System;

/** ensure this file is being included by a parent file */
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * Base app class.
 *
 */
class App extends Base
{
	/**
	 * @var Array Stored options.
	 */
	protected $options = null;

	protected $id = '';
	protected $namespace = '';
	protected $rootpath = '';
	protected $enabled = true;

	public function __construct($options = array())
	{
		parent::__construct();

		$this->options = $options;
		$this->id      = Wb\arrayGet(
			$options,
			'id',
			''
		);
		if (empty($this->id))
		{
			Wb\throwException(new \RuntimeException('Wblib: cannot start application, no id provided.'));
		}

		$this->namespace = Wb\arrayGet(
			$options,
			'namespace',
			''
		);
		$this->rootpath  = Wb\arrayGet(
			$options,
			'rootpath',
			''
		);
		if (
			!empty($this->namespace)
			&&
			!empty($this->rootpath)
		)
		{
			$this->factory->getThe('autoloader')
				->registerNamespace(
					$this->namespace,
					$this->rootpath
				);
		}

		$siteTimeZone = $this->platform->getTimezone();
		if (!empty($siteTimeZone))
		{
			System\Date::setTimezoneName(
				$siteTimeZone
			);
		}

		// allow user hooks by loading the "functions" file for the app.
		try
		{
			$this->factory->getThe('hook')
				->load(
					$this->id . '_functions.php'
				);
		}
		catch (\Throwable $e)
		{
			$logMsg = 'Exception ' . \get_class($e) . ' with message "' . $e->getMessage() . '" in ' . $e->getFile() . ':'
				. $e->getLine();

			System\Log::libraryError($logMsg);
		}
	}
}
