<?php
/**
 * Project: 4SEO
 *
 * @package          4SEO
 * @copyright        Copyright Weeblr llc - 2020 - 2026
 * @author           Yannick Gaultier - Weeblr llc
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          6.10.1.2660
 * @date        2026-01-30
 */

namespace Weeblr\Forseo\Platform\Helpers;

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;
use Weeblr\Wblib\Forseo\Joomla\Uri;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Helper to check if a redirect exists for the current requested URL.
 */
class urls extends Base\Base
{
	/**
	 * Redirect a request to the same path and query with the path
	 * converted to lowercase.
	 *
	 * 4SEF/sh404SEF ensures that requests are redirected to the correct
	 * case, not just to lowercase, so we don't want to interfere with that.
	 * Therefore we do not do anything if either of them are running.
	 *
	 */
	public function enforceLowerCaseUrls()
	{
		if (
			!defined('4SEF_IS_RUNNING')
			&&
			!defined('SH404SEF_IS_RUNNING')
			&&
			$this->factory->getThis('forseo.config', 'pages')->isTruthy('enforceLowerCaseUrls')
			&&
			$this->platform->isFrontend()
		) {
			$this->doEnforceLowerCaseUrls();
		}
	}

	/**
	 * Actually enforce a possible redirect to the lowercase version of a URL.
	 *
	 * @return void
	 */
	private function doEnforceLowerCaseUrls()
	{
		// must only convert the path, not query or fragment
		$currentUrl = $this->factory
			->getThe('forseo.requestInfo')
			->get('page_url');
		$uri        = $this->factory->getA(
			Uri\Uri::class,
			$currentUrl
		);

		// do not change any folder path either, in case the site
		// is in a subfolder, with uppercase letters and on a case-
		// sensitive file system.
		$path          = Wb\lTrim(
			$uri->getPath(),
			$this->platform->getBaseUrl()
		);
		$path          = rawurldecode($path);
		$lowerCasePath = StringHelper::strtolower($path);
		if ($lowerCasePath === $path)
		{
			return;
		}

		// stitch back any subfolder
		$uri->setPath(
			$this->platform->getBaseUrl() . $lowerCasePath
		);
		$targetUrl = $uri->toString();
		if ($this->platform->canRedirect($currentUrl, $targetUrl))
		{
			// do redirect
			$this->platform->redirectTo(
				$targetUrl,
				System\Http::RETURN_MOVED
			);
		}
	}
}
