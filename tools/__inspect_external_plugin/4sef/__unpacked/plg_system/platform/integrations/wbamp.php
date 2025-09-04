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

namespace Weeblr\Forsef\Platform\Integrations;

use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Base class to provide support for an extension.
 */
class Wbamp extends Base\Base
{
	/**
	 * Rewrite current path (including the amp suffix) to the canonical one.
	 *
	 * @param string $originalPath
	 * @return string
	 */
	public function ampToCanonicalPath($originalPath)
	{
		if (!class_exists('\WbAMP'))
		{
			return $originalPath;
		}

		if (\WbAMP::isAMPRequest())
		{
			$originalPath = Wb\lTrim(
				\WbAMP::getCanonicalUrl(),
				$this->platform->getBaseUrl(false)
			);
		}

		return $originalPath;
	}
}
