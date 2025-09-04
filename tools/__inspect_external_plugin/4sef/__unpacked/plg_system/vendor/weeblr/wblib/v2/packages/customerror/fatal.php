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

namespace Weeblr\Wblib\Forsef\Customerror;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die();

class Fatal extends \Exception
{
	public function __construct($message = null, $code = 0, Exception $previous = null, $options = [])
	{
		parent::__construct($message, $code, $previous);

		$this->line = empty($options['line'])
			? $this->line
			: $options['line'];
		$this->file = empty($options['file'])
			? $this->file
			: $options['file'];
	}
}
