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
use Weeblr\Forseo\Helper;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Base as WblibBase;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Com_content specific support.
 */
class Base extends WblibBase\Base
{
	/**
	 * @var string Component name - with leading com_ removed, eg content for com_content
	 */
	protected $component = '';

	/**
	 * @var \stdClass Cache for component content being rendered.
	 */
	protected $contentData = null;

	/**
	 * @var null|array List of view names that should be stored. None if null. All if empty array.
	 */
	protected $includedViews = [];

	/**
	 * @var null|array List of view names that should NOT be stored. No effect if null or empty.
	 */
	protected $excludedViews = [];

	/**
	 * @var null|array List of layouts names that should be stored. None if null. All if empty array.
	 */
	protected $includedLayouts = [];

	/**
	 * @var null|array List of layouts names that should NOT be stored. No effect if null or empty.
	 */
	protected $excludedLayouts = [];

	/**
	 * @var null|array List of variables/values that should cause a page to NOT be stored.
	 */
	protected $excludedInputVars = [
		'print' => ['1'],
		'tmpl'  => ['component', 'form']
	];

	/**
	 * @var array List of schema types supported by this plugin.
	 */
	protected $supportedSdTypes = [];

	/**
	 * @var bool Whether the plugin wants to filter raw, SEF URL. Time consuming, avoid if not needed.
	 */
	protected $filterShouldCollectUrlsFoundOnPage = false;

	/**
	 * @var Convenience instance of the platform hook handler.
	 */
	protected $hook;

	/**
	 * @var Helper\Page Convenience Helper instance.
	 */
	protected $helper;

	/**
	 * Stores factory instance.
	 *
	 * @param array $options Can inject custom factory and platform.
	 */
	public function __construct($options = [])
	{
		parent::__construct($options);

		$this->hook   = $this->factory->getThe('hook');
		$this->helper = $this->factory->getThe('forseo.pageHelper');
	}

	/**
	 * Add handlers for desired com_content hooks.
	 */
	public function addHooks()
	{
		$this->hook->add(
			'forseo_after_route_page_data',
			[
				$this,
				'filterAfterRoutePageDataWrapper'
			]
		);

		$this->hook->add(
			'forseo_after_render_page_data',
			[
				$this,
				'filterAfterRenderPageDataWrapper'
			]
		);
		$this->hook->add(
			'forseo_should_collect_urls',
			[
				$this,
				'filterShouldCollectUrls'
			]
		);

		if ($this->filterShouldCollectUrlsFoundOnPage)
		{
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
			'forseo_page_modified_at',
			[
				$this,
				'filterPageModifiedAtWrapper'
			]
		);

		$this->hook->add(
			'forseo_page_is_archived',
			[
				$this,
				'filterPageIsArchivedWrapper'
			]
		);

		$this->hook->add(
			'forseo_page_build_content_hash',
			[
				$this,
				'filterPageBuildContentHashWrapper'
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
			'forseo_content_prepared',
			[
				$this,
				'actionStorePreparedContent'
			]
		);

		$this->hook->add(
			'forseo_extract_page_images_from_content_data',
			[
				$this,
				'filterExtractPageImagesFromContentDataWrapper'
			]
		);

		$this->hook->add(
			'forseo_should_inject_seo_data',
			[
				$this,
				'filterShouldInjectSeoData'
			]
		);

		$this->hook->add(
			'forseo_sd_can_run_rule',
			[
				$this,
				'filterSdCanRunRuleWrapper'
			]
		);

		$this->hook->add(
			'forseo_sd_auto_data',
			[
				$this,
				'filterSdDataWrapper'
			]
		);

		$this->hook->add(
			'forseo_sd_rules',
			[
				$this,
				'filterSdRulesWrapper'
			]
		);

		$this->hook->add(
			'forseo_structured_data_cleanup_patterns',
			[
				$this,
				'filterSdCleanupPatternsWrapper'
			]
		);

		$this->hook->add(
			'forseo_pages_dynamic_canonical',
			[
				$this,
				'filterDynamicCanonicalWrapper'
			]
		);

		$this->hook->add(
			'forseo_cf_get_value_by_id',
			[
				$this,
				'filterCfGetValueById'
			]
		);
	}

	/**
	 * Getter for content data collected at onContentPrepare.
	 *
	 * @return mixed|null
	 */
	public function getContentData()
	{
		return $this->contentData;
	}

	/**
	 * Hook to store the finalized content data of current page.
	 *
	 * @param array $contentData
	 * @return mixed
	 */
	public function actionStorePreparedContent($contentData)
	{
		$context = Wb\arrayGet($contentData, 'context', '');
		if ($this->isValidContentContext($context))
		{
			$this->contentData = $contentData;
		}
	}

	/**
	 * Wrapper around deciding whether a given SD rule can apply to the current page.
	 *
	 * @param bool             $canRunRule
	 * @param array            $spec
	 * @param Data\Requestinfo $requestInfo
	 * @param Data\Page        $pageData
	 * @return bool|null
	 * @throws \Exception
	 */
	public function filterSdCanRunRuleWrapper($canRunRule, $spec, $requestInfo, $pageData)
	{
		if (!$this->shouldRunFilter($pageData))
		{
			return $canRunRule;
		}

		$sdType = Wb\arrayGet($spec, 'actualType', '');

		return (in_array($sdType, $this->supportedSdTypes))
			? $this->filterSdCanRunRule($canRunRule, $spec, $requestInfo, $pageData)
			: $canRunRule;
	}

	/**
	 * Decides whether a given SD rule can apply to the current page.
	 * By default is null.
	 * If a plugin can support, it sets it to true.
	 * If a plugin says this SD type cannot exist on this page, it sets it to false.
	 * Else leave as is.
	 *
	 * In the end, returned value must be true (ie at least one plugin can support and no other
	 * contradict) for the rule to run.
	 *
	 * NB: At this stage, it has already been checked that:
	 *
	 * - the current request is for this plugin extension
	 * - the current plugin lists the SD rule type in its $supportedSdTypes property.
	 *
	 * @param bool             $canRunRule
	 * @param array            $spec
	 * @param Data\Requestinfo $requestInfo
	 * @param Data\Page        $pageData
	 * @return bool
	 * @throws \Exception
	 */
	public function filterSdCanRunRule($canRunRule, $spec, $requestInfo, $pageData)
	{
		return $canRunRule;
	}

	/**
	 * Wrapper around building automatically computed structured data for a com_content article.
	 *
	 * @param array            $rules
	 * @param Data\Requestinfo $requestInfo
	 * @param Data\Page        $pageData
	 * @param string           $baseId
	 * @return array
	 * @throws \Exception
	 */
	public function filterSdRulesWrapper($rules, $requestInfo, $pageData, $baseId)
	{
		if (!$this->shouldRunFilter($pageData))
		{
			return $rules;
		}

		return $this->filterSdRules($rules, $requestInfo, $pageData, $baseId);
	}

	/**
	 * Actually add SD rules to the current request.
	 *
	 * @param array            $rules
	 * @param Data\Requestinfo $requestInfo
	 * @param Data\Page        $pageData
	 * @param string           $baseId
	 * @return array
	 * @throws \Exception
	 */
	protected function filterSdRules($rules, $requestInfo, $pageData, $baseId)
	{
		return $rules;
	}

	/**
	 * Wrapper around building automatically computed structured data for a com_content article.
	 *
	 * @param array            $autoFieldsData
	 * @param array            $autoFields
	 * @param array            $spec
	 * @param Data\Requestinfo $requestInfo
	 * @param Data\Page        $pageData
	 * @param string           $baseId
	 * @return array
	 * @throws \Exception
	 */
	public function filterSdDataWrapper($autoFieldsData, $autoFields, $spec, $requestInfo, $pageData, $baseId)
	{
		if (!$this->shouldRunFilter($pageData))
		{
			return $autoFieldsData;
		}

		$sdType = Wb\arrayGet($spec, 'actualType', '');

		return (in_array($sdType, $this->supportedSdTypes))
			? $this->filterSdData($autoFieldsData, $autoFields, $spec, $requestInfo, $pageData, $baseId)
			: $autoFieldsData;
	}

	/**
	 * Build automatically computed structured data for a com_content article.
	 *
	 * @param array            $autoFieldsData
	 * @param array            $autoFields
	 * @param array            $spec
	 * @param Data\Requestinfo $requestInfo
	 * @param Data\Page        $pageData
	 * @param string           $baseId
	 * @return array
	 * @throws \Exception
	 */
	protected function filterSdData($autoFieldsData, $autoFields, $spec, $requestInfo, $pageData, $baseId)
	{
		return $autoFieldsData;
	}

	/**
	 * Wrapper around filtering the list of regular expressions to be used when cleaning up a page
	 * of existing microdata after inserting 4SEO structured data.
	 *
	 * @param array     $patterns
	 * @param Data\Page $pageData Data on the current page.
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function filterSdCleanupPatternsWrapper($patterns, $pageData)
	{
		if (!$this->shouldRunFilter($pageData))
		{
			return $patterns;
		}

		return $this->filterSdCleanupPatterns($patterns);
	}

	/**
	 * Filter the list of regular expressions to be used when cleaning up a page
	 * of existing microdata after inserting 4SEO structured data.
	 *
	 * @param array $patterns
	 *
	 * @return array
	 */
	protected function filterSdCleanupPatterns($patterns)
	{
		return $patterns;
	}

	/**
	 * Filters whether links should be collected on the current page.
	 *
	 * @param bool      $shouldCollectUrls
	 * @param Data\Page $currentPage
	 *
	 * @return bool
	 */
	public function filterShouldCollectUrls($shouldCollectUrls, $currentPage)
	{
		return $shouldCollectUrls;
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
		return $links;
	}

	/**
	 * Removes common tracking vars from a URL, before it's used.
	 *
	 * @param string $link
	 *
	 * @return array
	 */
	public function filterCleanQueryVarsToStrip($link)
	{
		return $link;
	}

	/**
	 * Filters whether a specific URL collected from current page should be indeed
	 * stored to the collected URLs table.
	 *
	 * @param bool           $shouldCollectUrl
	 * @param Data\Collected $collectedUrl
	 *
	 * @return bool
	 */
	public function filterShouldCollectUrl($shouldCollectUrl, Data\Collected $collectedUrl)
	{
		return $shouldCollectUrl;
	}

	/**
	 * Filters whether SEO data (Structured data, OGP, Twitter Cards) should be injected
	 * in the current page.
	 *
	 * @param bool      $shouldCollectPageData
	 * @param Data\Page $pageData
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function filterShouldCollectPageData($shouldCollectPageData, $pageData)
	{
		if (!$this->shouldRunFilter($pageData))
		{
			return $shouldCollectPageData;
		}

		// default: do not collect data for some pages
		if ($this->excludeByViewAndLayout($pageData))
		{
			$shouldCollectPageData = false;
		}

		// default: do not collect data for some pages
		if ($this->excludeByInputVar($pageData))
		{
			$shouldCollectPageData = false;
		}


		return $shouldCollectPageData;
	}

	/**
	 * Filter automatically detected images from content data object.
	 *
	 * @param array     $extractedImages
	 * @param string    $context       An option string representing the context, the content type.
	 * @param string    $content       Rendered content.
	 * @param Object    $contentObject Data object holding the content data.
	 * @param Data\Page $pageData      Collected request information.
	 * @param Data\Meta $pageMeta      Collected meta data about the request.
	 * @throws \Exception
	 */
	public function filterExtractPageImagesFromContentDataWrapper($extractedImages, $context, $content, $contentObject, $pageData, $pageMeta)
	{
		if (!$this->shouldRunFilter($pageData))
		{
			return $extractedImages;
		}

		return $this->filterExtractPageImagesFromContentData($extractedImages, $context, $content, $contentObject, $pageData, $pageMeta);
	}

	/**
	 * Filter automatically detected images from content data object.
	 *
	 * @param array     $extractedImages
	 * @param string    $context       An option string representing the context, the content type.
	 * @param string    $content       Rendered content.
	 * @param Object    $contentObject Data object holding the content data.
	 * @param Data\Page $pageData      Collected request information.
	 * @param Data\Meta $pageMeta      Collected meta data about the request.
	 *
	 * @return array
	 *
	 */
	protected function filterExtractPageImagesFromContentData($extractedImages, $context, $content, $contentObject, $pageData, $pageMeta)
	{
		return $extractedImages;
	}

	/**
	 * Filters whether SEO data (Structured data, OGP, Twitter Cards) should be injected
	 * in the current page.
	 *
	 * @param bool      $shouldInjectData
	 * @param Data\Page $pageData
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function filterShouldInjectSeoData($shouldInjectData, $pageData)
	{
		if (!$this->shouldRunFilter($pageData))
		{
			return $shouldInjectData;
		}

		// deafult: do not inject seo data into pages we do not store
		if ($this->excludeByViewAndLayout($pageData))
		{
			$shouldInjectData = false;
		}

		if ($this->excludeByInputVar($pageData))
		{
			$shouldInjectData = false;
		}

		return $shouldInjectData;
	}


	/**
	 * Filters page data collected at the onAfterRoute event.
	 * Wrapper make sure request is for this component.
	 *
	 * @param Data\Page $pageData
	 *
	 * @return Data\Page
	 * @throws \Exception
	 */
	public function filterAfterRoutePageDataWrapper($pageData)
	{
		return $this->shouldRunFilter($pageData)
			? $this->filterAfterRoutePageData($pageData)
			: $pageData;
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
		if ($this->excludeByViewAndLayout($pageData))
		{
			$pageData->set('ignore', true);
		}

		if ($this->excludeByInputVar($pageData))
		{
			$pageData->set('ignore', true);
		}

		return $pageData;
	}

	/**
	 * Filters page data collected at the onAfterRender event.
	 * Wrapper make sure request is for this component.
	 *
	 * @param Data\Page $pageData
	 *
	 * @return Data\Page
	 * @throws \Exception
	 */
	public function filterAfterRenderPageDataWrapper($pageData)
	{
		return $this->shouldRunFilter($pageData)
			? $this->filterAfterRenderPageData($pageData)
			: $pageData;
	}

	/**
	 * Filters page data collected at the onAfterRender event.
	 *
	 * @param Data\Page $pageData
	 *
	 * @return Data\Page
	 * @throws \Exception
	 */
	protected function filterAfterRenderPageData($pageData)
	{
		if ($this->excludeByViewAndLayout($pageData))
		{
			$pageData->set('ignore', true);
		}

		if ($this->excludeByInputVar($pageData))
		{
			$pageData->set('ignore', true);
		}

		return $pageData;
	}

	/**
	 * Whether the requested view and layout pass this plugin specified views and layouts lists
	 *
	 * Default behavior, ok for many components. If not,
	 * override the filterAfterRoutePageData and/or
	 * filterAfterRenderPageData methods.
	 *
	 * @param Data\Page $pageData
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function excludeByViewAndLayout(Data\Page $pageData)
	{
		$view = strtolower($pageData->get('view'));

		$exclude = false;

		if (
			is_null($this->includedViews)
			||
			(
				!empty($this->includedViews)
				&&
				!in_array($view, $this->includedViews)
			)
		) {
			$exclude = true;
		}

		if (
			!$exclude
			&&
			!empty($this->excludedViews)
			&&
			in_array($view, $this->excludedViews))
		{
			$exclude = true;
		}

		$layout = strtolower($pageData->get('layout'));

		if (
			is_null($this->includedLayouts)
			||
			(
				!empty($this->includedLayouts)
				&&
				!in_array($layout, $this->includedLayouts)
			)
		) {
			$exclude = true;
		}

		if (
			!$exclude
			&&
			!empty($this->excludedLayouts)
			&&
			in_array($layout, $this->excludedLayouts))
		{
			$exclude = true;
		}

		return $exclude;
	}

	/**
	 * Whether the input variables for this request pass this plugin specified query variables specification.
	 *
	 * NB: Query vars means the input variables resulting from decoding the SEF URL, not just the actual query variables if any.
	 *
	 * Default behavior, ok for many components. If not,
	 * override the filterAfterRoutePageData and/or
	 * filterAfterRenderPageData methods.
	 *
	 * @param Data\Page $pageData
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function excludeByInputVar(Data\Page $pageData)
	{
		if (empty($this->excludedInputVars))
		{
			return false;
		}

		$inputVars = $pageData->get('input_vars', []);
		if (empty($inputVars))
		{
			return false;
		}

		foreach ($this->excludedInputVars as $varName => $varValues)
		{
			if (!Wb\arrayIsset($inputVars, $varName))
			{
				continue;
			}
			foreach ($varValues as $varValue)
			{
				if ($varValue === Wb\arrayGet($inputVars, $varName))
				{
					$this->factory->getThe('forseo.logger')->debug('excludeByInputVar excluding: %s, %s, %s', $varName, print_r($varValue, true), print_r($pageData->get(), true));
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Filters the unique content_id for request described
	 * in $pageData.
	 *
	 * Wrapper make sure request is for this component.
	 *
	 * @param null|array     $id
	 * @param null|Data\Page $pageData
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function filterPageBuildContentIdWrapper($id, $pageData)
	{
		return $this->shouldRunFilter($pageData)
			? $this->filterPageBuildContentId(
				$id,
				$pageData
			)
			: $id;
	}

	/**
	 * Default implementation of Content Id.
	 *
	 * @param null|array $id
	 * @param Data\Page  $pageData
	 * @return null|array
	 */
	protected function filterPageBuildContentId($id, $pageData)
	{
		return $id;
	}

	/**
	 * Filters the last modification data request described
	 * in $pageData.
	 *
	 * Wrapper make sure request is for this component.
	 *
	 * @param null|string    $lastMod
	 * @param null|Data\Page $pageData
	 *
	 * @return null|string
	 * @throws \Exception
	 */
	public function filterPageModifiedAtWrapper($lastMod, $pageData)
	{
		return $this->shouldRunFilter($pageData)
			? $this->filterPageModifiedAt(
				$lastMod,
				$pageData
			)
			: $lastMod;
	}

	/**
	 * Implement default construction of finding out modified_at date time.
	 *
	 * Use MYSQL format (Y-m-d H:i:s), assumes UTC.
	 *
	 * Null if unable to determine.
	 *
	 * @param null|string $lastMod
	 * @param Data\Page   $pageData
	 *
	 * @return null | string
	 * @throws \Exception
	 */
	protected function filterPageModifiedAt($lastMod, $pageData)
	{
		return $lastMod;
	}

	/**
	 * Wrapper for filtering whether the content described by in $pageData is considered archived. Will have an impact on
	 * sitemap inclusion.
	 *
	 * Wrapper make sure request is for this component.
	 *
	 * @param bool      $isArchived
	 * @param Data\Page $pageData
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function filterPageIsArchivedWrapper($isArchived, $pageData)
	{
		return $this->shouldRunFilter($pageData)
			? $this->filterPageIsArchived(
				$isArchived,
				$pageData
			)
			: $isArchived;
	}

	/**
	 * Filters whether the content described by in $pageData is considered archived. Will have an impact on
	 * sitemap inclusion.
	 *
	 * @param bool      $isArchived True if content hyas support for archiving and is archived, false otherwise.
	 * @param Data\Page $pageData
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function filterPageIsArchived($isArchived, $pageData)
	{
		return $isArchived;
	}

	/**
	 * Filters content hash that may be computed from a raw content
	 * array as provided by the platform.
	 *
	 * Wrapper make sure request is for this component.
	 *
	 * @param string         $hash
	 * @param array          $contentData
	 * @param null|Data\Page $pageData
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function filterPageBuildContentHashWrapper($hash, $contentData, $pageData)
	{
		return $this->shouldRunFilter($pageData)
			? $this->filterPageBuildContentHash(
				$hash,
				$contentData,
				$pageData
			)
			: $hash;
	}

	/**
	 * Implement default construction of a content hash that may be computed
	 * from a raw content array as provided by the platform.
	 *
	 * @param string         $hash
	 * @param array          $contentData
	 * @param null|Data\Page $pageData
	 *
	 * @return string
	 * @throws \Exception
	 */
	protected function filterPageBuildContentHash($hash, $contentData, $pageData)
	{
		return $hash;
	}


	/**
	 * Implement default construction of a unique content id based
	 * on information provided in $pageData.
	 *
	 * NB: we do not need language, id is enough.
	 *
	 * id(s) is one or more items id. If multiple, they are separate with _.
	 * If ids have a colon, ie 42:something, then only 42 is kept.
	 *
	 * @param null|array     $id
	 * @param null|Data\Page $pageData
	 *
	 * @return       array
	 * @throws \Exception
	 */
	protected function defaultPageBuildContentId($id, $pageData)
	{
		// do not override contentId computed by others.
		if (empty($id))
		{
			$id = $this->helper->defaultContentId(
				$pageData
			);

			$id = $this->helper->defaultCategoryContentid(
				$id,
				$pageData
			);

			$this->factory->getThe('forseo.logger')->debug('filterPageBuildContentId: %s, %s', print_r($id, true), print_r($pageData->get(), true));
		}

		return $id;
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
		return $pageData->isTruthy('extension')
			   &&
			   $pageData->get('extension') === $this->component;
	}

	/**
	 * Filter out from an existing array of rules those rules that are:
	 * - built-in
	 * - and of of specific types
	 *
	 * @param array $rules
	 * @param array $sdTypes
	 * @return array
	 */
	protected function clearBuiltInRulesOfTypes($rules, $sdTypes)
	{
		$rules = array_values(
			array_filter(
				$rules,
				function ($r) use ($sdTypes)
				{
					$ruleData = $r->get('rule');
					return Data\Rule::SOURCE_BUILT_IN !== $r->get('source')
						   ||
						   !array_intersect(
							   Wb\arrayGet($ruleData, 'actionSdType'),
							   $sdTypes
						   );
				}
			)
		);

		return empty($rules)
			? []
			: $rules;
	}

	/**
	 * Wrapper for a filter that tries to build automatically a canonical link for the current page described
	 * by the Page object passed in.
	 *
	 * Canonical returned will be made absolute downstream if not already fully qualified.
	 *
	 * Return null if no canonical can be determined based solely on the current request data.
	 *
	 * @param bool      $dynamicCanonical
	 * @param Data\Page $pageData Collected request information.
	 *
	 * @return null | string
	 *
	 * @throws \Exception
	 */
	public function filterDynamicCanonicalWrapper($dynamicCanonical, $pageData)
	{
		return $this->shouldRunFilter($pageData)
			? $this->filterDynamicCanonical(
				$dynamicCanonical,
				$pageData
			)
			: $dynamicCanonical;
	}

	/**
	 * Tries to build automatically a canonical link for the current page described
	 * by the Page object passed in.
	 *
	 * Canonical returned will be made absolute downstream if not already fully qualified.
	 *
	 * Return null if no canonical can be determined based solely on the current request data.
	 *
	 * @param bool      $dynamicCanonical
	 * @param Data\Page $pageData Collected request information.
	 *
	 * @return null | string
	 *
	 * @throws \Exception
	 */
	protected function filterDynamicCanonical($dynamicCanonical, $pageData)
	{
		// try detecting the home page and its variations
		$inputVars = $pageData->get('input_vars', []);
		unset($inputVars['format']);
		unset($inputVars['Itemid']);
		unset($inputVars['lang']);
		unset($inputVars['language']);
		$isHome = $this->platform->isAnyHomepageFromVars(
			$inputVars,
			true // $withoutPagination
		);

		$path = $this->factory->getThe('forseo.requestInfo')->get('page_path');
		if (!$isHome)
		{
			$isHome = $this->platform->isAnyHomepagePath($path);
		}

		if ($isHome)
		{
			// if paginated version, this is not the home page, must have its own canonical
			$query       = $pageData->get('query', []);
			$queryString = '';
			$start       = Wb\arrayGet($query, 'start');
			if (!empty($start))
			{
				$queryString = '?start=' . $start;
			}

			$limit = Wb\arrayGet($query, 'limit');
			if (!empty($limit))
			{
				$queryString = System\Route::appendVarToQueryString(
					$queryString,
					'limit',
					$limit
				);
			}

			return $path . $queryString;
		}

		return $dynamicCanonical;
	}

	/**
	 * Filter the value of a custom field for the current page request.
	 *
	 * @param mixed  $customFieldValue The custom field value.
	 * @param int    $customFieldId    Id of custom field in platform table
	 * @param string $fieldContext     The context of the content being passed to the plugin.
	 * @param mixed  $contentData      The main page content object, to which the custom fields has been attached.
	 *
	 * @return mixed
	 *
	 * @since   2.1.1
	 *
	 */
	public function filterCfGetValueById($customFieldValue, $customFieldId, $fieldContext, $contentData)
	{
		if (!$this->isValidCustomFieldContext($fieldContext))
		{
			return $customFieldValue;
		}

		if (empty($contentData))
		{
			$contentData = $this->contentData;
		}

		$contentItem = Wb\arrayGet(
			$contentData,
			'content'
		);
		if (empty($contentItem))
		{
			return $customFieldValue;
		}

		$contentContext = Wb\arrayGet(
			$contentData,
			'context',
			''
		);
		if ($fieldContext !== $contentContext)
		{
			return $customFieldValue;
		}

		return $this->platform->getCustomFieldsValue(
			$customFieldId,
			$fieldContext,
			$contentItem,
			true   // $prepareValue
		);
	}

	/**
	 * Check whether the current plugin can retrieve a custom field value
	 * associated with the provided context string.
	 *
	 * @param string $context
	 * @return bool
	 */
	protected function isValidCustomFieldContext($context)
	{
		return Wb\startsWith(
			$context,
			'com_' . $this->component
		);
	}

	/**
	 * Check whether the provided context describe a main page content type.
	 * Used to discard modules and plugins content for which onContentPrepare is
	 * also triggered.
	 *
	 * @param string $context
	 * @return bool
	 */
	protected function isValidContentContext($context)
	{
		return Wb\startsWith(
			$context,
			'com_' . $this->component
		);
	}
}
