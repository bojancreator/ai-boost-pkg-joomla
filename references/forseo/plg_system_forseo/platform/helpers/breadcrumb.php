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

use Joomla\CMS\Factory;
use Joomla\Registry\Registry;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Routing-related helpers
 */
class Breadcrumb extends Base\Base
{
	/**
	 * Build and filter the bradcrumb data for the current page, in a format
	 * that can be directly used to output schema.org breadcrumb
	 * structured data.
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function getBreadCrumbData()
	{
		/**
		 * Filter the current page data used to build the structured data breadcrumb.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\sd
		 * @var forseo_sd_breadcrumb
		 * @since   1.0.0
		 *
		 */
		$breadcrumbData = $this->factory->getThe('hook')
										->filter(
											'forseo_sd_breadcrumb',
											$this->buildBreadcrumb()
										);

		return $breadcrumbData;
	}

	/**
	 * Builds Breadcrumb structured data
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function buildBreadcrumb()
	{
		$sdData = [];

		$breadcrumb = Factory::getApplication()->getPathway();
		if (empty($breadcrumb))
		{
			return $sdData;
		}

		$breadcrumbItems = $breadcrumb->getPathway();

		if (empty($breadcrumbItems))
		{
			// need at least 2 items in a valid breadcrumb.
			return $sdData;
		}

		$breadcrumbData = [];

		// add other crumbs
		$position = 2;
		foreach ($breadcrumbItems as $key => $item)
		{
			if (!empty($item->link))
			{
				$itemData = array(
					'position' => $position,
					'item'     => $item->link,
					'name'     => $item->name
				);
				$position++;
				$breadcrumbData[] = $itemData;
			}
		}

		// Home item handling
		if (!empty($breadcrumbData))
		{
			// load breadcrumb module language and params
			$module = ModuleHelper::getModule('mod_breadcrumbs');
			$lang   = Factory::getApplication()->getLanguage();
			$lang->load('mod_breadcrumbs', JPATH_BASE, null, false, true)
			||
			$lang->load('mod_breadcrumbs', JPATH_BASE . '/modules/mod_breadcrumbs', null, false, true);

			if (!empty($module) && !empty($module->id))
			{
				$params = new Registry;
				$params->loadString($module->params);
				$homeTitle = htmlspecialchars($params->get('homeText', Text::_('MOD_BREADCRUMBS_HOME')));
			}
			else
			{
				$homeTitle = Text::_('MOD_BREADCRUMBS_HOME');
			}

			// home link
			$home = Factory::getApplication()
						   ->getMenu('site')
						   ->getDefault(
							   $lang->getTag()
						   );

			// insert home crumb
			array_unshift(
				$breadcrumbData,
				[
					'position' => 1,
					'item'     => 'index.php?Itemid=' . $home->id,
					'name'     => $homeTitle
				]
			);
		}

		// build the SD structure
		$sdData['@context']        = 'http://schema.org';
		$sdData['@type']           = 'BreadcrumbList';
		$sdData['itemListElement'] = [];

		foreach ($breadcrumbData as $itemData)
		{
			$itemUrl                     = Wb\arrayGet($itemData, 'item', '');
			$item                        = [
				'@type'    => 'listItem',
				'position' => Wb\arrayGet($itemData, 'position'),
				'name'     => Wb\arrayGet($itemData, 'name'),
				'item'     => empty($itemUrl)
					? ''
					: System\Route::absolutify(
						Route::_($itemUrl),
						true,
						null,
						true
					)
			];
			$sdData['itemListElement'][] = $item;
		}

		return $sdData;
	}

	/**
	 * Disables native structured data scripts to avoid duplicates
	 * and also prevent some Google Search Console warnings.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function killNativeStucturedData()
	{
		if ($this->factory->getThis('forseo.config', 'sd')->isFalsy('removeJoomlaBreadcrumb'))
		{
			return;
		}

		$platformVersion = $this->platform->majorVersion();

		// J4+
		if (
			version_compare($platformVersion, '4', '>')
			&&
			$this->factory->getThis('forseo.config', 'sd')->isTruthy('enabledBreadcrumb')
		) {
			$assetManager = Factory::getApplication()
								   ->getDocument()
								   ->getWebAssetManager();

			if ($assetManager->assetExists('script', 'inline.mod_breadcrumbs-schemaorg'))
			{
				$assetManager->disableScript(
					'inline.mod_breadcrumbs-schemaorg'
				);
			}
		}
	}
}
