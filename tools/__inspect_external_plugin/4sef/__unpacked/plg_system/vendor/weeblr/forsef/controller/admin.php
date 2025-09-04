<?php
/**
 * Project: 4SEF
 *
 * @package                 4SEF
 * @copyright               Copyright Weeblr llc - 2022 -2025
 * @author                  Yannick Gaultier - Weeblr llc
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 2.6.2.644
 *
 * @date        2025-06-02
 */

namespace Weeblr\Forsef\Controller;

use Weeblr\Forsef\View;
use Weeblr\Wblib\Forsef\Mvc;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Render a story page
 */
class Admin extends Mvc\ControllerHtml
{
	/**
	 * Builds a view and render it with the provided data.
	 */
	public function render($data = null)
	{
		try
		{
			$view = new View\Admin(
				[
					'theme'            => 'default',
					'layout'           => 'forsef.admin.main',
					'base_layout_path' => FORSEF_LAYOUTS_PATH,
					'echo_output'      => false
				]
			);

			$output = $view->setDisplayData($data)
						   ->outputHeaders()
						   ->render();

			echo $output;
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forsef.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
	}
}
