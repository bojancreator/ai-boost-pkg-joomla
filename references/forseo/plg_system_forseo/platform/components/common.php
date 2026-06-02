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
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Weeblr\Extensions\Sh404sef;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Com_content specific support.
 */
class Common extends Base
{
	/**
	 * @var string Component name - with leading com_ removed, eg content for com_content
	 */
	protected $component = '__common__';

	/**
	 * @var null|array List of view names that should be stored. None if null. All if empty array.
	 */
	protected $includedViews = null;

	/**
	 * @var null|array List of layouts names that should be stored. None if null. All if empty array.
	 */
	protected $includedLayouts = null;

	/**
	 * @var bool Whether the plugin wants to filter raw, SEF URL. Time consuming, avoid if not needed.
	 */
	protected $filterShouldCollectUrlsFoundOnPage = true;

	/**
	 * @var array Convienence copy of the WAF defautl blocking rules. Needed to filter out 404s created by the WAF from the 404 log.
	 */
	protected $defaultWafRules;

	/**
	 * @var array List of common tracking (or otherwise) query variables that should be filtered out of collected URLs.
	 */
	protected $queryVarsToStrip;

	/**
	 * @var array Stores whether some specific extensions are running.
	 */
	protected $extensions = [];

	/**
	 * @var array Stores a list of extensions that must use language code in their content_id as they use the same item for multiple languages.
	 */
	protected $extensionsRequiringLanguageInId;

	public const LANGUAGE_VARIABLES = [
		'lang'     => '*',
		'language' => '*'
	];

	/**
	 * Stores factory instance.
	 *
	 * @param array $options Can inject custom factory and platform.
	 */
	public function __construct($options = [])
	{
		parent::__construct($options);

		/**
		 * Filter the list of default, built-in WAF block rules URL specifications.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\rules
		 * @var forseo_current_request_category
		 *
		 * @param array $wafRulesSpecs An array of URL specifications, such as /{*} or /blog/{*}.
		 *
		 * @return array
		 *
		 * @since   1.0.0
		 */
		$this->defaultWafRules = $this->factory->getThe('hook')->filter(
			'forseo_default_block_rules',
			$this->factory
				->getThis('forseo.config', 'app')
				->get('wafBlockedUrlsDefault', [])
		);

		$appConfig = $this->factory->getThis('forseo.config', 'app');

		$this->extensionsRequiringLanguageInId = $appConfig->get('extensionsRequiringLanguageInId');
		$this->extensions['falang']            = $this->platform->hasFalang();
		// flip list to be ready for array_diff_key
		$this->queryVarsToStrip = array_flip(
			array_merge(
				$appConfig->get('commonTrackingVars'),
				$appConfig->get('queryVarsToStrip')
			)
		);
	}

	/**
	 * Add handlers for desired com_content hooks.
	 */
	public function addHooks()
	{
		if (
			$this->filterShouldCollectUrlsFoundOnPage
			&&
			$this->platform->isFrontend()
		) {
			$this->hook->add(
				'forseo_should_collect_urls_found_on_page',
				[
					$this,
					'filterShouldCollectUrlsFoundOnPage'
				]
			);
		}

		$this->hook->add(
			'forseo_clean_query_vars_to_strip',
			[
				$this,
				'filterCleanQueryVarsToStrip'
			]
		);

		$this->hook->add(
			'forseo_should_collect_url',
			[
				$this,
				'filterShouldCollectUrl'
			]
		);

		$this->hook->add(
			'forseo_page_build_content_id',
			[
				$this,
				'filterPageBuildContentIdWrapper'
			]
		);

		$this->hook->add(
			'forseo_should_collect_page_data',
			[
				$this,
				'filterShouldCollectPageData'
			]
		);

		$this->hook->add(
			'forseo_should_collect_error',
			[
				$this,
				'filterShouldCollectError'
			]
		);
	}

	/**
	 * Filters whether a specific URL collected from current page should be indeed
	 * stored to the collected URLs table.
	 *
	 * This preliminary test can only use the SEF URL because it's ran when the URL is found
	 * inside a page.
	 *
	 * @param array $links
	 * @param array $originalLinks
	 *
	 * @return array
	 */
	public function filterShouldCollectUrlsFoundOnPage($links, $originalLinks)
	{
		if (empty($links))
		{
			return $links;
		}

		$filteredLinks = array_filter(
			$links,
			function ($link)
			{

				// basic duplicate versions
				if (Wb\contains(
					$link,
					[
						'tmpl=component',
						'/tmpl,component/',
						'tmpl,component/',
						'tmpl=form',
						'/tmpl,form/',
						'tmpl,form/',
						'component/com_mailto/',
						'component/com_ajax/',
						'print=1',
						'XDEBUG_SESSION',
						'XDEBUG_SESSION_START',
						'XDEBUG_SESSION_STOP',
						'cachebuster',
						'_wbcb',
						'modules/mod_gtranslate'
					]
				))
				{
					return false;
				}

				if (Wb\endsWith(
					$link,
					[
						// javascript map files
						'.map',
						'.min.map',
					]
				))
				{
					return false;
				}

				return true;
			}
		);

		return empty($filteredLinks)
			? []
			: $filteredLinks;
	}

	/**
	 * Removes common unwanted query vars from a URL, before it's used.
	 *
	 * @param string $link
	 *
	 * @return string
	 */
	public function filterCleanQueryVarsToStrip($link)
	{
		if (empty($link))
		{
			return $link;
		}

		if (!Wb\contains($link, '?'))
		{
			return $link;
		}

		$uri          = System\Http::buildUri($link);
		$query        = $uri->getQuery(true);
		$cleanedQuery = array_diff_key(
			$query,
			$this->queryVarsToStrip
		);
		$uri->setQuery($cleanedQuery);

		return $uri->toString();
	}

	/**
	 * Implement construction of a piece of content unique id.
	 * Overriden by extensions as needed.
	 *
	 * @param null|array     $id
	 * @param null|Data\Page $pageData
	 *
	 * @return       array
	 * @throws \Exception
	 */
	public function filterPageBuildContentIdWrapper($id, $pageData)
	{
		/**
		 * Language code is dropped from id as Joomla ML system has separate
		 * content items per language therefore the same item cannot have 2 different languages, or
		 * the additional languages are duplicates.
		 *
		 * However with Falang, the same item (item id in database) has multiple languages assigned to it
		 * and thus the language code must be part of the 4SEO contentId.
		 */
		$languageVariables = Wb\arrayIsTruthy(
			$this->extensions,
			'falang'
		)
			? []
			: self::LANGUAGE_VARIABLES;

		/** There are also some extensions using a single item for all languages. These too should use language
		 * code in content_id.
		 */
		$extension = $pageData->get('extension');
		if (array_key_exists($extension, $this->extensionsRequiringLanguageInId))
		{
			$languageVariables = array_diff_key(
				$languageVariables,
				array_flip($this->extensionsRequiringLanguageInId[$extension])
			);
		}

		return array_diff_key(
			array_merge(
				$pageData->get('input_vars', []),
				$this->helper
					->filterQueryVarsForId($pageData->get('query', []))
			),
			$languageVariables
		);
	}

	/**
	 * Filter whether data for the current page should be collected.
	 *
	 * @param bool      $shouldCollectPageData
	 * @param Data\Page $pageData
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function filterShouldCollectPageData($shouldCollectPageData, $pageData)
	{
		return $shouldCollectPageData;
	}

	/**
	 * Filters whether an captured error should be recorded as such.
	 * Used to removed unwanted requests, from bots for instance.
	 *
	 * @param bool      $shouldCollectError
	 * @param Data\Page $pageData
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function filterShouldCollectError($shouldCollectError, $error, $pageData)
	{
		$skipSuffixes = [
			'.map'
		];

		$url = $pageData->get('full_url');
		foreach ($skipSuffixes as $skipSuffixe)
		{
			if (Wb\endsWith($url, $skipSuffixe))
			{
				return false;
			}
		}

		$skipRules = $this->defaultWafRules;

		foreach ($skipRules as $skipRule)
		{
			if (System\Route::matchUrlRule($skipRule, '/' . $url))
			{
				return false;
			}
		}

		return $shouldCollectError;
	}

	/**
	 * Checks whether current filter is for this component.
	 *
	 * @param Data\Page $pageData
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function shouldRunFilter($pageData)
	{
		return true;
	}
}
