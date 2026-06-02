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

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Routing-related helpers
 */
class Site extends Base\Base
{
	/**
	 * Find out the search URL in use on the site.
	 *
	 * @param   string  $searchTermPlaceholder
	 *
	 * @return string
	 */
	public function getSearchUrl(string $searchTermPlaceholder)
	{
		$nonSefUrl = $this->platform->majorVersion() < 4
			? 'index.php?option=com_search&searchword='
			: 'index.php?option=com_finder&view=search&q=';

		$searchUrl = $this->platform->route(
			$nonSefUrl . $searchTermPlaceholder
		);

		/**
		 * Filter the search URL used in website sitelinks structured data.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\sd
		 * @var forseo_sd_sitelinks_search_url
		 * @since   1.0.0
		 *
		 */
		$searchUrl = $this->factory->getThe('hook')
			->filter(
				'forseo_sd_sitelinks_search_url',
				$searchUrl
			);

		return
			$this->platform->getScheme()
			. '://'
			. $this->platform->getHost()
			. $searchUrl;
	}
}
