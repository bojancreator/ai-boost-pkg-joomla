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

namespace Weeblr\Wblib\Forsef\Mvc;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Base;

/** ensure this file is being included by a parent file */
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * Updates to a standard HTML page, which has an AMP version
 */
abstract class ControllerController extends Base\Base
{
	protected $options = array();

	/**
	 * Constructor
	 *
	 * @param   array  $options  An array of options.
	 */
	public function __construct($options = array())
	{
		parent::__construct();
		$this->options = $options;
	}

	/**
	 * Builds a view and render it with the provided data.
	 */
	public abstract function render($data);

	/**
	 * Builds up an array of data for use in layouts output.
	 *
	 * @param   array  $incomingData
	 *
	 * @return array
	 */
	protected function getData($incomingData = array())
	{
		$data = Wb\arrayMerge(
			array(),
			$incomingData
		);

		return $data;
	}
}