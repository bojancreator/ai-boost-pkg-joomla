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

namespace Weeblr\Forseo\Model\Injector;

use Weeblr\Forseo\Data;
use Weeblr\Forseo\Model;
use Weeblr\Forseo\Platform\Helpers;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\Html;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Sd extends Base\Base
{
	/**
	 * @var Data\Requestinfo Convenience instance of the current request details.
	 */
	private $requestInfo;

	/**
	 * @var Data\Page Instance of current computed data about the page.
	 */
	private $pageData;

	/**
	 * @var Model\Config Convenience instance of the Structured Data configuration.
	 */
	private $sdConfig;

	/**
	 * @var Injector\Variables Convenience dynamic variables model instance.
	 */
	private $variablesModel;

	/**
	 * @var array Holds all the structured data to be output as json-ld
	 */
	private $data = [];

	/**
	 * Loads pre-computed info about the request.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->variablesModel = $this->factory->getThe('forseo.variablesExpander');

		$this->requestInfo = $this->factory->getThe('forseo.requestInfo');
		$this->pageData    = $this->factory->getThe('forseo.pageDataCollector')->get();
		$this->sdConfig    = $this->factory->getThis('forseo.config', 'sd');
	}

	/**
	 * Builds the final values for the data to inject.
	 */
	public function build()
	{
		if ($this->sdConfig->isFalsy('enabled'))
		{
			return $this;
		}
		$this->buildSitelinks()
			 ->buildSiteName()
			 ->buildBreadcrumb()
			 ->buildCurrentPage();

		return $this;
	}

	/**
	 * Builds the Google sitelinks search structured data data object.
	 *
	 * @return Sd
	 * @throws \Exception
	 */
	private function buildSitelinks()
	{
		if ($this->sdConfig->isFalsy('enabledSiteLinks'))
		{
			return $this;
		}

		if ($this->platform->isHomePage())
		{
			$this->data['sitelinks'] = [
				'@context'        => 'http://schema.org',
				'@type'           => 'WebSite',
				'url'             => $this->requestInfo->get('page_url'),
				'name'            => $this->requestInfo->get('site_name'),
				'potentialAction' => [
					'@type'       => 'SearchAction',
					'target'      => $this->factory
						->getA(Helpers\Site::class)
						->getSearchUrl('{search_term_string}'),
					'query-input' => 'required name=search_term_string'
				]
			];
		}

		return $this;
	}

	private function buildSiteName()
	{
		if (
			$this->sdConfig->isTruthy('enabledSiteLinks')
			||
			!$this->platform->isHomePage()
		) {
			return $this;
		}

		$this->data['sitename'] = [
			'@context' => 'http://schema.org',
			'@type'    => 'WebSite',
			'url'      => $this->requestInfo->get('page_url'),
			'name'     => $this->requestInfo->get('site_name')
		];

		return $this;
	}

	/**
	 * Builds the Google breadcrumb structured data data object.
	 *
	 * @return Sd
	 */
	private function buildBreadcrumb()
	{
		if (
			$this->sdConfig->isFalsy('enabledBreadcrumb')
			||
			$this->platform->isHomepage()
		) {
			return $this;
		}

		$sdData = $this->factory
			->getA(Helpers\Breadcrumb::class)
			->getBreadCrumbData();

		if (!empty($sdData) && !empty($sdData['itemListElement']))
		{
			$this->data['breadcrumb'] = $sdData;
		}

		return $this;
	}

	/**
	 * Builds the Google structured data object.
	 *
	 * @return Sd
	 */
	private function buildCurrentPage()
	{
		if (
			$this->sdConfig->isFalsy('enabledPerPage')
			&&
			$this->sdConfig->isFalsy('enabledLocalBusiness')
		) {
			return $this;
		}

		$sdData = $this->factory
			->getA(
				Model\Sd::class,
				[
					'pageData'    => $this->pageData,
					'requestInfo' => $this->requestInfo
				]
			)->build();

		if (
			!empty($sdData)
			&&
			!empty($sdData['@graph'])
		) {
			foreach ($sdData['@graph'] as $graphKey => $graphValue)
			{
				foreach ($graphValue as $key => $value)
				{
					if (is_string($value))
					{
						$expandedRecord   = $this->variablesModel->expand($value);
						$replacementCount = Wb\arrayGet($expandedRecord, 1, 0);
						if ($replacementCount > 0)
						{
							$sdData['@graph'][$graphKey][$key] = $expandedRecord[0];
						}
					}
				}
			}

			$this->data['page'] = $sdData;
		}

		return $this;
	}

	/**
	 * Render all structured data for direct injection in page content.
	 *
	 * @return string
	 */
	public function render()
	{
		/**
		 * Filter the raw data to be inserted as json-ld.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\sd
		 * @var forseo_structured_data
		 * @since   1.0.0
		 *
		 * @param array $data Array of structured data.
		 *
		 * @return array
		 */
		$this->data = $this->factory
			->getThe('hook')
			->filter(
				'forseo_structured_data',
				$this->data
			);

		$htmlHelper = $this->factory->getA(Html\Helper::class);
		$renderedSd = [];
		foreach ($this->data as $section => $sd)
		{
			$renderedSd[] = $htmlHelper->makeTag(
				'script',
				[
					'type'  => 'application/ld+json',
					'class' => '4SEO_structured_data_' . $section
				],
				System\Strings::jsonPrettyPrint(
					$sd,
					true,
					-1, // serialize precision
					[
						//'JSON_NUMERIC_CHECK', // No numeric check, some fields must keep their leading zeros
						'JSON_UNESCAPED_SLASHES',
						'JSON_UNESCAPED_UNICODE'
					]
				),
				[
					'close' => true
				]
			);
		}

		return implode("\n", $renderedSd);
	}

	/**
	 * Clean up pre-existing (micro)data that could be invalid.
	 *
	 * @param string $body
	 *
	 * @return string
	 */
	public function cleanup($body)
	{
		/**
		 * Filter the list of regular expressions to be used when cleaning up a page
		 * of existing microdata after inserting 4SEO structured data.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\sd
		 * @var forseo_structured_data_cleanup_patterns
		 * @since   1.3.0
		 *
		 * @param array     $patterns List of regular expressions for cleaning microdata in content.
		 * @param Data\Page $pageData Data on the current page.
		 *
		 * @return array
		 */
		$patterns = $this->factory
			->getThe('hook')
			->filter(
				'forseo_structured_data_cleanup_patterns',
				[
					'~itemscope itemtype="[^"]+"~isU'
				],
				$this->pageData
			);

		return empty($patterns)
			? $body
			: System\Strings::pr(
				$patterns,
				'',
				$body
			);
	}
}