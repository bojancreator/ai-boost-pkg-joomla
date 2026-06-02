<?php
/**
 * Project: 4SEO
 *
 * @package                 4SEO
 * @copyright               Copyright Weeblr llc - 2020 - 2026
 * @author                  Yannick Gaultier - Weeblr llc
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 6.10.1.2660
 *
 * 2026-01-30
 */

namespace Weeblr\Forseo\Controller;

use Weeblr\Forseo\View;
use Weeblr\Forseo\Data;

use Weeblr\Wblib\Forseo\Mvc;
use Weeblr\Wblib\Forseo\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Render a story page
 */
class Admin extends Mvc\ControllerHtml
{
	/**
	 * Constructor
	 *
	 * @param array $options An array of options.
	 */
	public function __construct($options = array())
	{
		parent::__construct($options);

		// perform a redirect if some options are set by our OAuth proxy
		// after saving these options to db. This ensure a "clean" URL when showing 4SEO user interface.
		$authServicePreselect = $this->platform->getHttpInput()
											   ->getCmd('authServicePreselect', '');

		if (!in_array($authServicePreselect, Data\Services::ALLOWED_SERVICES))
		{
			return;
		}

		$this->factory->getThis('forseo.config', 'integrations')
					  ->set(
						  'authServicePreselect',
						  $authServicePreselect
					  )->store();

		if (!empty($authServicePreselect))
		{
			$currentUrl = $this->platform->getCurrentUrl();
			$target     = System\Route::removeQueryVarFromUrl(
				$currentUrl,
				'authServicePreselect'
			);

			if ($this->platform->canRedirect($currentUrl, $target))
			{
				$this->platform->redirectTo($target);
			}

		}
	}

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
					'layout'           => 'forseo.admin.main',
					'base_layout_path' => FORSEO_LAYOUTS_PATH,
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
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
	}
}
