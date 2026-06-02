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

namespace Weeblr\Forseo\Helper;

use Weeblr\Forseo\Data;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Db;
use Weeblr\Wblib\Forseo\Joomla\Uri;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;


// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Page extends Base\Base
{
	/**
	 * @var Robotstxt Memoized instance of robots.txt Helper.
	 */
	private $robotstxtHelper;

	/**
	 * Builds a unique content id for a provided page data object.
	 * Then triggers a hook for plugins to do the job.
	 *
	 * @param Data\Page $pageData
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function contentId(Data\Page $pageData)
	{
		$contentId = $pageData->get('full_content_id', '');
		if (!empty($contentId))
		{
			parse_str(
				$pageData->get('full_content_id', ''),
				$parsedContentId
			);
		}
		else
		{
			$parsedContentId = [];
		}

		/**
		 * Filter the content id of a page.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\pages
		 * @var forseo_page_build_content_id
		 * @since   1.0.0
		 *
		 * @param array     $contentIdBits Array of key/values pairs
		 * @param Data\Page $pageData      The page object to describe with the content id.
		 *
		 * @return string
		 *
		 */
		$contentIdBits = $this->factory
			->getThe('hook')
			->filter(
				'forseo_page_build_content_id',
				$parsedContentId,
				$pageData
			);

		ksort($contentIdBits);

		$appConfig     = $this->factory->getThis('forseo.config', 'app');
		$contentIdBits = array_diff_key(
			$contentIdBits,
			array_flip(
				array_merge(
					$appConfig->get('commonTrackingVars'),
					$appConfig->get('queryVarsToStrip')
				)
			)
		);

		return http_build_query(
			$contentIdBits
		);
	}

	/**
	 * Builds a content id from page information, that should
	 * suit most extensions following the standard Joomla MVC pattern.
	 *
	 * @param array $pageData
	 * @return array
	 */
	public function defaultContentId($pageData)
	{
		return [
			'extension' => $pageData->get('extension'),
			'view'      => $pageData->get('view'),
			'layout'    => $pageData->get('layout'),
			'item_id'   => $this->compactValuesList(
				$this->cleanIdsWithColons(
					$pageData->get('item_id')
				)
			)
		];
	}

	/**
	 * Special handling of categories content_id computation.
	 * We keep page number as each page in a category view must be canonical,
	 * a separate piece of content.
	 *
	 * @param null|array     $id
	 * @param null|Data\Page $pageData
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function defaultCategoryContentid($id, $pageData)
	{
		// Categories specific processing.
		$view = $pageData->get('view');
		if (!in_array($view, ['category', 'categories', 'featured']))
		{
			return $id;
		}
		// this a category view, are we on a second or more pages?
		// if so, include page number in id to distinguish them.
		$filteredQueryVars = $this->filterQueryVarsForId($pageData->get('query', []));

		$inputVars = array_merge(
			$pageData->get('input_vars', []),
			$filteredQueryVars
		);
		$page      = Wb\arrayGet(
			$inputVars,
			'start',
			null
		);
		if (is_null($page))
		{
			$page = Wb\arrayGet(
				$inputVars,
				'limitstart',
				null
			);
		}

		if (is_null($page))
		{
			return $id;
		}

		$id['start'] = $page;

		return $id;
	}

	/**
	 * Apply allow list to query variables, to use them to build an item id.
	 *
	 * @param array $queryVars
	 * @return array
	 * @throws \Exception
	 */
	public function filterQueryVarsForId($queryVars)
	{
		$filteredQueryVars = array_intersect_key(
			$queryVars,
			// allow overriding some variables through the query.
			[
				'option'     => true,
				'view'       => true,
				'layout'     => true,
				'id'         => true,
				'catid'      => true,
				'start'      => true,
				'limitstart' => true,
				'limit'      => true
			]
		);
		$filteredQueryVars = Wb\arrayEnsure($filteredQueryVars);

		// drop columns and alias embedded in ids
		$idNames = ['id', 'catid'];
		foreach ($idNames as $idName)
		{
			if (!empty($filteredQueryVars[$idName]))
			{
				$filteredQueryVars[$idName] = $this->cleanIdsWithColons(
					$filteredQueryVars[$idName]
				);
			}
		}

		return $filteredQueryVars;
	}

	/**
	 * Clear a specific var of an input array.
	 *
	 * @param array  $input
	 * @param string $varName
	 */
	public function cleanQueryVar($input, $varName)
	{
		return array_filter(
			$input,
			function ($queryVarName) use ($varName)
			{
				return $queryVarName !== $varName;
			},
			ARRAY_FILTER_USE_KEY
		);
	}

	/**
	 * Implode a list of items into a string if an array.
	 *
	 * @param string|array $list
	 *
	 * @return string
	 */
	public function compactValuesList($list)
	{
		return is_array($list)
			? implode(
				'_',
				$list
			)
			: $list;
	}

	/**
	 * Some ids can be in the form nn:xxxxx where xxx is an alias.
	 * For compacity, we only keep the actual id.
	 *
	 * @param string | array $ids
	 * @param bool           $forceIntegers
	 *
	 * @return string|string[]
	 */
	public function cleanIdsWithColons($ids, $forceIntegers = false)
	{
		if (empty($ids))
		{
			return $ids;
		}

		$wasArray = is_array($ids);
		$ids      = Wb\arrayEnsure($ids);
		$ids      = array_map(
			function ($item) use ($forceIntegers)
			{
				if ($forceIntegers)
				{
					$item = (int)$item;
				}

				if (!Wb\contains($item, ':'))
				{
					return $item;
				}
				$bits = explode(':', $item);

				return $bits[0];
			},
			$ids
		);

		return $wasArray
			? $ids
			: array_shift($ids);
	}

	/**
	 * Finds out and return when a page was last modified. Done by plugins actually.
	 *
	 * @param Data\page $pageData
	 * @return string
	 */
	public function modifiedAt(Data\page $pageData)
	{
		/**
		 * Filters a Page modified_at date time. Use MYSQL format (Y-m-d H:i:s), assumes UTC.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\pages
		 * @var forseo_page_modified_at
		 * @since   1.0.0
		 *
		 * @param string    $lastMod
		 * @param Data\Page $pageData The page object to find the modified_at date for.
		 *
		 * @return string
		 *
		 */
		return $this->factory
			->getThe('hook')
			->filter(
				'forseo_page_modified_at',
				null,
				$pageData
			);

	}

	/**
	 * Get current request URL, cleaned of any cache busting or other
	 * 4SEO internal query vars that could have been added to it.
	 *
	 * @return string
	 */
	public function getCleanedCurrentUrl()
	{
		return System\Route::removeQueryVarFromUrl(
			$this->platform->getCurrentUrl(),
			FORSEO_CRAWLER_CDN_BUST_VAR
		);
	}

	/**
	 * Tries to build a canonical based on the current page characteristics,
	 * independantly of duplicates or similar info about the rest of the site.
	 *
	 * If none found, returns null.
	 *
	 * @param Data\Page $pageData Known information about the current page.
	 *
	 * @return null|string Null if no canonical found, canonical if found.
	 */
	public function getDynamicCanonical($pageData)
	{
		/**
		 * Filter a dynamically generated canonical link for the current page.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\frontend\pages
		 * @var forseo_pages_dynamic_canonical
		 * @since   2.1.0
		 *
		 * @param bool      $dynamicCanonical
		 * @param Data\Page $pageData Collected request information.
		 *
		 * @return string
		 *
		 */
		$canonical = $this->factory
			->getThe('hook')
			->filter(
				'forseo_pages_dynamic_canonical',
				null,
				$pageData
			);

		return Wb\lTrim(
			$canonical,
			[
				$this->platform->getBaseUrl(true),
				$this->platform->getUrlRewritingPrefix(),
				'/'
			]
		);
	}

	/**
	 * Return the canonical, if any for the specified full_content_id.
	 * If none found, returns null.
	 *
	 * @param string $contentId    The full content id
	 * @param string $requestedUrl The full requested URL
	 * @return null|string Null if no canonical found, canonical if found.
	 */
	public function getDefaultCanonical($contentId, $requestedUrl = null)
	{
		if (empty($contentId))
		{
			return null;
		}

		$db        = $this->factory->getThe('db');
		$contentId = $db->storageSafe($contentId);
		$query     = Wb\join(
			' ',
			'select ' . $db->quoteName('full_url') . ' from ' . $db->quoteName('#__forseo_pages'),
			' where ',
			$db->quoteName('content_id') . '=' . $db->quote($contentId),
			'and',
			'(',
			'(' . $db->quoteName('canonical_mode') . '=' . $db->quote(Data\Page::USER) . ' and ' . $db->quoteName('canonical_user') . '=' . $db->quote(Data\Page::CANONICAL) . ')',
			'or',
			'(' . $db->quoteName('canonical_mode') . '=' . $db->quote(Data\Page::AUTO) . ' and ' . $db->quoteName('canonical_auto') . '=' . $db->quote(Data\Page::CANONICAL) . ')',
			')',
			'order by ' . $db->quoteName('canonical_mode') . ' desc' // user set canonical before auto canonical
		);

		$defaultCanonical = $db->setQueryAnd($query)
							   ->loadResult();

		if (
			empty($defaultCanonical)
			&&
			!empty($requestedUrl)
			&&
			$this->factory
				->getThis('forseo.config', 'pages')
				->isTruthy('useCanonicalFallbackStrategy')
		) {
			// drop the query string and search on the URL
			$urlWithoutQuery = System\Route::trimQuery($requestedUrl);
			$where           = Wb\join(
				' ',
				$db->quoteName('full_url') . '=' . $db->quote($urlWithoutQuery),
				'and',
				'(',
				'(' . $db->quoteName('canonical_mode') . '=' . $db->quote(Data\Page::USER) . ' and ' . $db->quoteName('canonical_user') . '=' . $db->quote(Data\Page::CANONICAL) . ')',
				'or',
				'(' . $db->quoteName('canonical_mode') . '=' . $db->quote(Data\Page::AUTO) . ' and ' . $db->quoteName('canonical_auto') . '=' . $db->quote(Data\Page::CANONICAL) . ')',
				')'
			);
			if (
				!empty($urlWithoutQuery)
				&&
				$db->exists(
					'#__forseo_pages',
					$where
				)
			) {
				$defaultCanonical = $urlWithoutQuery;
			}
		}

		return $defaultCanonical;
	}

	/**
	 * Whether current page should be considered canonical (automatically).
	 * By default true, except if another page with same content_id is already listed as
	 * canonical (automatically).
	 *
	 * Result is then filtered.
	 *
	 * @param Data\page $pageData
	 * @return int
	 * @throws \Exception
	 */
	public function canonicalType(Data\page $pageData)
	{
		$urlType   = Data\Page::CANONICAL;
		$contentId = $pageData->get('content_id');
		if (!empty($contentId))
		{
			$duplicatepagesCount = $this->factory
				->getThe('db')
				->selectObjectList(
					'#__forseo_pages',
					'id',
					[
						'content_id'     => $contentId,
						'canonical_auto' => Data\Page::CANONICAL,
						['url', '!=', $pageData->get('url')]
					]
				);

			$urlType = empty($duplicatepagesCount)
				? $urlType
				: Data\Page::DUPLICATE;
		}

		$requestInfo = $this->factory->getThe('forseo.requestInfo');

		$canonical = $requestInfo->get('page_custom_canonical');
		$canonical = empty($canonical)
			? $requestInfo->get('page_canonical')
			: $canonical;
		$canonical = empty($canonical)
			? $requestInfo->get('page_auto_canonical')
			: $canonical;

		if (!$this->isSelfCanonical(
			$requestInfo->get('page_url'),
			System\Route::urlEncodeUrl($canonical)
		))
		{
			// exclude if canonical is set and not the same as current URL
			$urlType = Data\Page::DUPLICATE;
			$this->factory->getThe('forseo.logger')->debug(
				Wb\join(
					', ',
					__METHOD__,
					'URL ' . $requestInfo->get('page_url') . ' marked as not canonical as it has a canonical tag not pointing at itself.',
					'Request info page_canonical is: ' . $requestInfo->get('page_canonical'),
					'Request info page_auto_canonical is: ' . $requestInfo->get('page_auto_canonical'),
					'Computed $canonical is: ' . $canonical
				)
			);
		}

		/**
		 * Whether passed page should be considered canonical or duplicate (automatically). Presence of a duplicate
		 * (ie with same content_id) has already been checked.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\pages
		 * @var forseo_page_canonical_or_duplicate
		 * @since   1.0.0
		 *
		 * @param int       $urlType  Data\Page::CANONICAL | Data\Page::DUPLICATE
		 * @param Data\Page $pageData The page object.
		 *
		 * @return int
		 *
		 */
		return $this->factory
			->getThe('hook')
			->filter(
				'forseo_page_canonical_or_duplicate',
				$urlType,
				$pageData
			);
	}

	/**
	 * Look up all pages with same content id as provided page, which is supposed to
	 * be the canonical one. Changes the auto_canonical column of all these other
	 * pages to duplicate.
	 *
	 * @param Data\Page $page
	 * @return Page
	 * @throws \Exception
	 */
	public function ensureUniqueAutoCanonical($page)
	{
		if (Data\Page::CANONICAL !== $page->get('canonical_auto'))
		{
			return $this;
		}

		$contentId = $page->get('content_id');
		if (empty($contentId))
		{
			return $this;
		}

		$this->factory
			->getThe('db')
			->update(
				'#__forseo_pages',
				[
					'canonical_auto' => Data\Page::DUPLICATE
				],
				[
					'content_id'     => $contentId,
					'canonical_auto' => Data\Page::CANONICAL,
					['url', '!=', $page->get('url')]
				]
			);

		return $this;
	}

	/**
	 * Look up all pages with same content id as provided page, which is supposed to
	 * be the canonical one. Changes the sitemap_auto column of all these other
	 * pages to NOT included.
	 *
	 * @param Data\Page $page
	 * @return Page
	 * @throws \Exception
	 */
	public function ensureCanonicalIsInSitemap($page)
	{
		if (Data\Page::CANONICAL !== $page->get('canonical_auto'))
		{
			return $this;
		}

		$contentId = $page->get('content_id');
		if (empty($contentId))
		{
			return $this;
		}

		$this->factory
			->getThe('db')
			->update(
				'#__forseo_pages',
				[
					'sitemap_auto' => Data\Page::EXCLUDED
				],
				[
					'content_id'   => $contentId,
					'sitemap_auto' => Data\Page::INCLUDED,
					['url', '!=', $page->get('url')]
				]
			);

		return $this;
	}

	/**
	 * Whether current page should be included (automatically) in a sitemap.
	 * By default true, except if another page with same content_id is already listed as
	 * included (automatically) in that sitemap.
	 * Result is then filtered.
	 *
	 * NB: can only be called inside of a crawler request.
	 *
	 * @param Data\page $pageData
	 * @param Data\page $storedPageData
	 * @param int       $sitemapType Data\Page::INCLUDED | Data\Page::EXCLUDED
	 * @param array     $rules       List of exclusion rules
	 *
	 * @return int
	 * @throws \Exception
	 */
	public function shouldIncludeInSitemap($pageData, $storedPageData, $sitemapType = Data\Sitemap::CONTENT, $rules = [])
	{
		$sitemapsConfig = $this->factory
			->getThis('forseo.config', 'sitemaps');

		// Exclude based on user-set exclusions list
		$inclusionStatus = $this->factory
			->getA(Url::class)
			->passExclusionRules(
				$pageData->get('full_url'),
				$sitemapsConfig->get('exclusions'),
				$sitemapsConfig->get('inclusions')
			)
			? Data\Page::INCLUDED
			: Data\Page::EXCLUDED;

		// Optionally exclude archived content
		$isArchived            = false;
		$shouldExcludeArchived = $sitemapsConfig->isTruthy('excludeArchived');
		if (Data\Page::INCLUDED === $inclusionStatus)
		{
			if ($shouldExcludeArchived)
			{
				/**
				 * Filter whether the current request is for an archived page.
				 *
				 * @api     forseo
				 * @package 4SEO\filter\pages
				 * @var forseo_page_is_archived
				 * @since   1.1.2
				 *
				 * @param bool      $isArchived
				 * @param Data\Page $pageData The page object to find whether it's archived.
				 *
				 * @return array
				 *
				 */
				$isArchived = $this->factory
					->getThe('hook')
					->filter(
						'forseo_page_is_archived',
						false,
						$pageData
					);
			}
		}

		if (Data\Page::INCLUDED === $inclusionStatus)
		{
			// Apply user-set rules
			foreach ($rules as $rule)
			{
				$ruleDefinition = $rule->getRule();

				// absolute exclude
				if (Wb\arrayIsTruthy($ruleDefinition, 'actionSmExclude'))
				{
					$inclusionStatus = Data\Page::EXCLUDED;
					$this->factory->getThe('forseo.logger')->debug(
						'url ' . $pageData->get('full_url') . ' excluded from sitemap per rule #' . $rule->getId()
					);
				}

				if (Data\Page::INCLUDED === $inclusionStatus)
				{
					// exclude older than N days
					$excludeOlderThan = (int)Wb\arrayGet($ruleDefinition, 'actionSmExcludeAge', 0);
					if (!empty($excludeOlderThan))
					{
						$modifiedAt = $this->modifiedAt($pageData);
						if (
							!empty($modifiedAt)
							&&
							System\Date::toExtendedDateTime($modifiedAt)
									   ->isBeforeBy('now', 'P' . $excludeOlderThan . 'D')
						) {
							$inclusionStatus = Data\Page::EXCLUDED;
							$this->factory->getThe('forseo.logger')->debug(
								'url ' . $pageData->get('full_url') . ' excluded from sitemap as older than ' . $excludeOlderThan . ' days per rule #' . $rule->getId()
							);
						}
					}
				}

				// Exclude if archived
				if (Data\Page::INCLUDED === $inclusionStatus)
				{
					$shouldExcludeArchived = Wb\arrayIsTruthy(
						$ruleDefinition,
						'actionSmExcludeArchived',
						true
					);
				}
			}
		}

		if (
			Data\Page::INCLUDED === $inclusionStatus
			&&
			$isArchived
			&&
			$shouldExcludeArchived
		) {
			$inclusionStatus = Data\Page::EXCLUDED;
			$this->factory->getThe('forseo.logger')->debug(
				'url ' . $pageData->get('full_url') . ' excluded as archived.'
			);
		}

		// exclude by computed or manually set flags
		if (Data\Page::INCLUDED === $inclusionStatus)
		{
			$canonicalFlag = Data\Page::USER === $pageData->get('canonical_mode')
				? $pageData->get('canonical_user')
				: $pageData->get('canonical_auto');
			if (Data\Page::DUPLICATE === $canonicalFlag)
			{
				$inclusionStatus = Data\Page::EXCLUDED;
			}
		}

		$requestInfo = empty($requestInfo)
			? $this->factory->getThe('forseo.requestInfo')
			: $requestInfo;

		// Exclude by canonical URL value
		if (Data\Page::INCLUDED === $inclusionStatus)
		{
			$canonical = $requestInfo->get('page_custom_canonical');
			$canonical = empty($canonical)
				? $requestInfo->get('page_canonical')
				: $canonical;
			$canonical = empty($canonical)
				? $requestInfo->get('page_auto_canonical')
				: $canonical;

			$isSelfCanonical = $this->isSelfCanonical(
				$requestInfo->get('page_url'),
				System\Route::urlEncodeUrl($canonical)
			);
			if (!$isSelfCanonical)
			{
				// exclude if canonical is set and not the same as current URL
				$inclusionStatus = Data\Page::EXCLUDED;
				$this->factory->getThe('forseo.logger')->debug(
					Wb\join(
						', ',
						__METHOD__,
						'url ' . $pageData->get('full_url') . ' excluded from sitemap as not canonical',
						'canonical is: ' . $requestInfo->get('page_canonical')
					)
				);
			}
		}

		// Exclude by robots meta
		if (Data\Page::INCLUDED === $inclusionStatus)
		{
			$pageMetaRobots = $requestInfo->getMetaRobots();
			if ($this->factory
				->getA(Meta::class)
				->hasMetaNoindex($pageMetaRobots))
			{
				$inclusionStatus = Data\Page::EXCLUDED;
				$this->factory->getThe('forseo.logger')->debug(
					Wb\join(
						', ',
						__METHOD__,
						'url ' . $requestInfo->get('page_url') . ' excluded from sitemap as has noindex robots meta',
						'robots meta is: ' . $pageMetaRobots
					)
				);
			}
		}

		// Exclude from robots.txt
		if (Data\Page::INCLUDED === $inclusionStatus)
		{
			$inclusionStatus = $this->getRobotstxtHelper()
									->isExcluded(
										'/' . $pageData->get('full_url')
									)
				? Data\Page::EXCLUDED
				: $inclusionStatus;

			if (Data\Page::EXCLUDED === $inclusionStatus)
			{
				$this->factory
					->getThe('forseo.logger')
					->debug(__METHOD__ . ': url ' . $requestInfo->get('page_url') . ' excluded from sitemap based on robots.txt rule.'
					);
			}
		}

		/**
		 * Whether current page should be included (automatically) in a sitemap. Presence of a duplicate
		 * (ie with same content_id) has already been checked.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\sitemap
		 * @var forseo_page_should_include_in_sitemap
		 * @since   1.0.0
		 *
		 * @param int       $inclusionStatus Data\Page::INCLUDED | Data\Page::EXCLUDED
		 * @param Data\Page $pageData        The page object to find the modified_at date for.
		 * @param int       $sitemapType     @see Data\Sitemap
		 *
		 * @return int
		 *
		 */
		return $this->factory
			->getThe('hook')
			->filter(
				'forseo_page_should_include_in_sitemap',
				$inclusionStatus,
				$pageData,
				$sitemapType
			);
	}

	/**
	 * Memoized an instance of a robots.txt helper.
	 *
	 * @return Robotstxt
	 */
	private function getRobotstxtHelper()
	{
		if (is_null($this->robotstxtHelper))
		{
			$this->robotstxtHelper = $this->factory->getThe('forseo.robotsTxtHelper');
		}

		return $this->robotstxtHelper;
	}

	/**
	 * Finds out if a page URL and a page canonical are the same.
	 *
	 * @param string $url
	 * @param string $canonical
	 * @return bool
	 */
	public function isSelfCanonical($url, $canonical)
	{
		$this->factory
			->getThe('forseo.logger')
			->debug(__METHOD__ . ': url ' . $url . ', canonical ' . $canonical);

		$canonical = empty($canonical)
			? ''
			: System\Route::absolutify(
				$canonical,
				true
			);

		$cleanedUrl = System\Route::removeQueryVarFromUrl(
			$url,
			FORSEO_CRAWLER_CDN_BUST_VAR
		);

		$url = empty($url)
			? ''
			: System\Route::absolutify(
				$cleanedUrl,
				true
			);

		$this->factory
			->getThe('forseo.logger')
			->debug(__METHOD__ . ': absolutify url ' . $url . ', cleaned URL ' . $cleanedUrl . ', absolutify canonical ' . $canonical);

		return empty($canonical)
			   ||
			   $url == $canonical;
	}

}
