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

namespace Weeblr\Forseo\Platform\Components;

use Weeblr\Forseo\Data;

use Weeblr\Wblib\Forseo\Wb;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Support for Virtuemart.
 *
 */
class Virtuemart extends Base
{
	/**
	 * @var string Component name - with leading com_ removed, eg content for com_content
	 */
	protected $component = 'virtuemart';

	/**
	 * @var \stdClass Cache for items being rendered.
	 */
	protected $contentData = null;

	/**
	 * @var string[] Views we can store.
	 */
	protected $includedViews = [
		'category',
		'productdetails'
	];

	/**
	 * @var null|array List of layouts names that should be stored. None if null. All if empty.
	 */
	protected $includedLayouts = [];

	/**
	 * @var null|array List of layouts names that should NOT be stored. No effect if null or empty.
	 */
	protected $excludedLayouts = [];

	/**
	 * @var array List of schema types supported by this plugin.
	 */
	protected $supportedSdTypes = [];

	/**
	 * @var bool Whether the plugin wants to filter raw, SEF URL. Time consuming, avoid if not needed.
	 */
	protected $filterShouldCollectUrlsFoundOnPage = false;

	/**
	 * Add handlers for desired extension hooks.
	 */
	public function addHooks()
	{
		parent::addHooks();

		if ($this->platform->isFrontend())
		{
			$this->hook->add(
				'forseo_page_should_include_in_sitemap',
				[
					$this,
					'filterShouldIncludeInSitemap'
				]
			);
		}
	}

	/**
	 * Filters page data collected at the onAfterRoute event.
	 *
	 * @param Data\Page $pageData
	 *
	 * @return Data\Page
	 * @throws \Exception
	 */
	protected function filterAfterRoutePageData($pageData)
	{
		$pageData = parent::filterAfterRoutePageData($pageData);

		if ($pageData->isFalsy('ignore'))
		{
			$inputVars       = $pageData->get('non_sef_vars', []);
			$duplicatingKeys = [
				'dir',
				'orderby',
				'keyword'
			];
			foreach ($duplicatingKeys as $duplicatingKey)
			{
				if (Wb\arrayIsSet($inputVars, $duplicatingKey))
				{
					$pageData->set('ignore', true);
					return $pageData;
				}
			}
		}

		return $pageData;
	}

	/**
	 * Implement construction of extension item unique id.
	 *
	 * @param null|array     $id
	 * @param null|Data\Page $pageData
	 *
	 * @return       array
	 * @throws \Exception
	 */
	protected function filterPageBuildContentId($id, $pageData)
	{
		return $this->defaultPageBuildContentId($id, $pageData);
	}

	/**
	 * Filters whether to include a URL into the sitemap.
	 *
	 * @param int       $shouldInclude Data\Page::INCLUDED | Data\Page::EXCLUDED
	 * @param Data\Page $pageData      The page object to find the modified_at date for.
	 * @param int       $sitemapType   @see Data\Sitemap
	 *
	 * @return int
	 * @throws \Exception
	 */
	public function filterShouldIncludeInSitemap($shouldInclude, $pageData, $sitemapType)
	{
		if (!$this->shouldRunFilter($pageData))
		{
			return $shouldInclude;
		}

		$inputVars = $pageData->get('input_vars', []);
		$task      = Wb\arrayGet($inputVars, 'task', null);
		if (!empty($task) && 'view' !== $task)
		{
			// only show category and product page
			$shouldInclude = Data\Page::EXCLUDED;
		}

		return $shouldInclude;
	}
}
