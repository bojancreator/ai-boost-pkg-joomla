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
/** ensure this file is being included by a parent file */
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * Updates to a standard HTML page, which has an AMP version
 */
class ViewHtml extends ViewView
{
	protected $headers = array(
		'Content-Type'           => 'text/html; charset=utf-8',
		'X-Content-Type-Options' => 'nosniff',
		'x-wblib-version'        => 'v1'
	);

	/**
	 * Renders the view content, returning it in a string and
	 * optionally echoing it
	 */
	protected function doRender()
	{
		return LayoutHelper::render(
			Wb\dotJoin($this->theme, $this->layout),
			$this->data,
			$this->baseLayoutPath
		);
	}
}