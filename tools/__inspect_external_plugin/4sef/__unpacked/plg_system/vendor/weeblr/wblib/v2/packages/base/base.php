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
use Weeblr\Wblib\Forsef\Factory;
use Weeblr\Wblib\Forsef\Platform\Platform;

/** ensure this file is being included by a parent file */
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * Base class to access the factory.
 *
 */
class Base
{
	/**
	 * @var Factory Unique instance of the factory.
	 */
	protected $factory = null;

	/**
	 * @var Platform The platform instance.
	 */
	protected $platform = null;

	/**
	 * Stores factory instance.
	 *
	 * @param   array  $options  Can inject custom factory and platform.
	 */
	public function __construct($options = [])
	{
		$this->factory = Wb\arrayGet(
			$options,
			'factory',
			Factory::get()
		);

		$this->platform = Wb\arrayGet(
			$options,
			'platform',
			$this->factory->getThe('platform')
		);
	}
}
