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

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Support for J2Store.
 *
 */
class J2store extends Base
{
	/**
	 * @var string Component name - with leading com_ removed, eg content for com_content
	 */
	protected $component = 'j2store';

	/**
	 * @var \stdClass Cache for items being rendered.
	 */
	protected $contentData = null;

	/**
	 * @var string[] Views we can store.
	 */
	protected $includedViews = [
		'products',
		'producttags'
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
	 * @var Model\Config
	 */
	private $config;

	/**
	 * @var array Storage for current page product, if applicable.
	 */
	private $product;

	/**
	 * @var array Storage for current page built standardized product data.
	 */
	private $productData;

	/**
	 * @var array Storage for dynamic vars generated on the fly.
	 */
	private $dynamicVars = [];

	/**
	 * Add handlers for desired extension hooks.
	 */
	public function addHooks()
	{
		parent::addHooks();

		$this->config = $this->factory
			->getThis('forseo.config', 'extensions');

		if ($this->platform->isFrontend())
		{
			$this->hook->add(
				'forseo_page_should_include_in_sitemap',
				[
					$this,
					'filterShouldIncludeInSitemap'
				]
			);

			$this->hook->add(
				'forseo_expandable_variables',
				[
					$this,
					'filterExpandableVariables'
				]
			);
		}
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
		$id = $this->defaultPageBuildContentId($id, $pageData);

		// Possible duplicate
		$filterCatid = Wb\arrayGet($id, 'filter_catid');
		if (!empty($filterCatid))
		{
			$catid = Wb\arrayGet($id, 'catid');
			if (
				is_array($catid)
				&&
				1 === count($catid)
			) {
				$catidId = array_shift($catid);
				if ((int)$catidId === (int)$filterCatid)
				{
					unset($id['filter_catid']);
				}
			}
		}

		return $id;
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
		if ($this->config->isFalsy('j2storeEnableStructuredData'))
		{
			return $rules;
		}

		if (
			!$this->shouldRunFilter($pageData)
			&&
			!$this->isArticleProductPage($pageData)
		) {
			return $rules;
		}

		$rule     = $this->factory->getA(Data\Rule::class);
		$ruleData = [
			'actionSdType'                => [Data\Sd::PRODUCT],
			'actionSdAggregateRatingAuto' => Data\Sd::FIELD_AUTO,
			'actionSdReviewAuto'          => Data\Sd::FIELD_AUTO,
		];

		$rule->set(
			[
				'rule'   => $ruleData,
				'source' => Data\Rule::SOURCE_BUILT_IN
			]
		);

		// override Article type rule that may have been set by the Content plugin.
		$rules = $this->clearBuiltInRulesOfTypes(
			$rules,
			[
				Data\Sd::ARTICLE
			]
		);

		$rules[] = $rule;

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
		if (Data\Sd::PRODUCT !== Wb\arrayGet($spec, 'type'))
		{
			return $autoFieldsData;
		}

		if ($this->config->isFalsy('j2storeEnableStructuredData'))
		{
			return $autoFieldsData;
		}

		$shouldRunFilter = $this->shouldRunFilter($pageData);
		if ($shouldRunFilter)
		{
			// this is a J2Store page, is it a product or category page?
			$task = Wb\arrayGet($pageData->get('input_vars'), 'task');
			if ('view' !== $task)
			{
				// not product
				return $autoFieldsData;
			}
		}

		if (
			!$shouldRunFilter
			&&
			!$this->isArticleProductPage($pageData)
		) {
			return $autoFieldsData;
		}

		// id is either the article id or the product id, depending on whether we are on a real J2Store page
		// or a com_content page that includes a product.
		$this->loadProductFromRequest($pageData)
			 ->buildProductData();

		if (
			empty($this->product)
			||
			empty($this->productData)
		) {
			return $autoFieldsData;
		}

		if (array_key_exists('name', $autoFields))
		{
			$autoFieldsData['sdData']['name'] = $this->productData['name'];
		}
		if (array_key_exists('sku', $autoFields))
		{
			$autoFieldsData['sdData']['sku'] = $this->productData['sku'];
		}

		if (array_key_exists('offerPrice', $autoFields) && array_key_exists('offerPrice', $this->productData))
		{
			$autoFieldsData['sdData']['offerPrice']        = $this->productData['offerPrice'];
			$autoFieldsData['sdData']['priceCurrency']     = $this->productData['priceCurrency'];
			$autoFieldsData['sdData']['offerUrl']          = $this->productData['offerUrl'];
			$autoFieldsData['sdData']['offerAvailability'] = $this->productData['offerAvailability'];
		}

		if (array_key_exists('image', $autoFields) && array_key_exists('image', $this->productData))
		{
			$autoFieldsData['sdData']['image'] = $this->productData['image'];
		}

		if (
			array_key_exists('brand', $autoFields)
			&&
			array_key_exists('brand', $this->productData)
		) {
			$autoFieldsData['sdData']['brand'] = $this->productData['brand'];
		}

		if (array_key_exists('description', $autoFields) && array_key_exists('description', $this->productData))
		{
			$autoFieldsData['sdData']['description'] = $this->productData['description'];
		}

		if (
			array_key_exists('aggregateRating', $autoFields)
			&&
			array_key_exists('rating', $this->productData)
			&&
			array_key_exists('reviewCount', $this->productData)
		) {
			$autoFieldsData['sdData']['aggregateRating'] = [
				'@type'       => Data\Sd::AGGREGATE_RATING,
				'ratingValue' => $this->productData['rating'],
				'reviewCount' => $this->productData['reviewCount'],
				'worstRating' => 0,
				'bestRating'  => 5
			];
		}

		if (array_key_exists('review', $autoFields) && array_key_exists('review', $this->productData))
		{
			$autoFieldsData['sdData']['review'] = $this->productData['review'];
		}

		return $autoFieldsData;
	}

	/**
	 * Loads J2Store product object based on request.
	 *
	 * @param Data\Page $pageData
	 * @return $this
	 * @throws \Exception
	 */
	private function loadProductFromRequest($pageData)
	{
		$id            = Wb\arrayGetInt($pageData->get('input_vars'), 'id');
		$this->product = $this->isArticleProductPage($pageData)
			? $this->loadProductFromArticle($id)
			: $this->loadProduct($id);

		return $this;
	}

	/**
	 * Build standard data set from product details.
	 *
	 * @return $this
	 */
	private function buildProductData()
	{
		if (empty($this->product))
		{
			$this->productData = [];
			return $this;
		}

		if (is_null($this->productData))
		{
			$this->productData         = [];
			$this->productData['name'] = $this->product->product_name;
			$this->productData['sku']  = (isset($this->product->variant->sku) && !empty($this->product->variant->sku))
				? $this->product->variant->sku
				: '';

			if (isset($this->product->variant->j2store_variant_id) && !empty($this->product->variant->j2store_variant_id))
			{
				$this->productData['offerPrice']        = round($this->product->pricing->price, 2);
				$this->productData['priceCurrency']     = \J2store::currency()->getCode();
				$this->productData['offerUrl']          = System\Route::absolutify(
					$this->product->product_link,
					true);
				$this->productData['offerAvailability'] = 'https://schema.org/' . ($this->product->variant->availability ? 'InStock' : 'OutOfStock');
			}

			if (!empty($this->product->main_image))
			{
				$main_image                 = System\Route::absolutify(
					ltrim($this->product->main_image, '/'),
					true
				);
				$this->productData['image'] = $main_image;
			}
			elseif (!empty($item->thumb_image))
			{
				$thumb_image                = System\Route::absolutify(
					ltrim($this->product->thumb_image, '/'),
					true
				);
				$this->productData['image'] = $thumb_image;
			}

			if (
				!empty($this->product->brand_name)
				||
				!empty($this->product->manufacturer)
			) {
				$this->productData['brand'] = empty($this->product->brand_name)
					? $this->product->manufacturer
					: $this->product->brand_name;
			}

			if (!empty($this->product->introtext))
			{
				$this->productData['description'] = substr($this->product->introtext, 0, 200);
			}

			if (
				!empty($this->contentData)
				&&
				!empty($this->contentData->content)
				&&
				!empty($this->contentData->content->rating)
				&&
				!empty($this->contentData->content->rating_count)
			) {
				$this->productData['ratingValue'] = $this->contentData->content->rating;
				$this->productData['reviewCount'] = $this->contentData->content->rating_count;
			}

			// review
		}

		return $this;
	}

	/**
	 * Whether current page is a request for a product.
	 *
	 * @param Data\Page $pageData
	 * @return bool|mixed
	 * @throws \Exception
	 */
	private function isArticleProductPage($pageData)
	{
		static $isArticleProductPage;

		if (is_null($isArticleProductPage))
		{
			$inputVars = $pageData->get('input_vars');
			$option    = Wb\arrayGet($inputVars, 'option');
			$view      = Wb\arrayGet($inputVars, 'view');

			$isArticleProductPage = 'com_content' === $option
									&&
									'article' === $view
									&&
									class_exists('\F0FTable')
									&&
									class_exists('\F0FModel')
									&&
									class_exists('\J2Store');
			if ($isArticleProductPage)
			{
				$id = Wb\arrayGet($inputVars, 'id');

				$isArticleProductPage = !empty($this->loadProductFromArticle($id));
			}
		}

		return $isArticleProductPage;
	}

	/**
	 * Try and load a J2Store product details based on a provided article id. If the article
	 * is not associated with a product, returns false.
	 *
	 * @param int $articleId
	 * @return false
	 */
	private function loadProductFromArticle($articleId)
	{
		static $products = [];

		if (!isset($products[$articleId]))
		{
			$products[$articleId] = false;
			$rawProduct           = \F0FTable::getAnInstance('Product', 'J2StoreTable')->getClone();
			if (empty($rawProduct))
			{
				return $products[$articleId];
			}
			$productId = $rawProduct->get_product_by_source('com_content', (int)$articleId)
				? $rawProduct->j2store_product_id
				: null;

			if (!empty($productId))
			{
				$products[$articleId] = $this->loadProduct($productId);
			}
		}

		return $products[$articleId];
	}

	/**
	 * Loads a J2Store product details based on the product J2Store id.
	 *
	 * @param int $productId
	 * @return \F0FTable|false|mixed
	 */
	private function loadProduct($productId)
	{
		static $products = [];

		if (!isset($products[$productId]))
		{
			$products[$productId] = false;
			$rawProduct           = \F0FTable::getAnInstance('Product', 'J2StoreTable')->getClone();
			if (empty($rawProduct))
			{
				return $products[$productId];
			}
			$product = \J2Store::product()->setId($productId)->getProduct();
			\F0FModel::getTmpInstance('Products', 'J2StoreModel')->runMyBehaviorFlag(true)->getProduct($product);
			$products[$productId] = $product;
		}

		return $products[$productId];
	}

	/**
	 * Filter auto-generated dynamic variables that will be substituted by 4SEO based on rules.
	 *
	 * @param array     $variables
	 * @param Data\Page $pageData
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function filterExpandableVariables($variables, $pageData)
	{
		if (!$this->shouldRunFilter($pageData))
		{
			return $variables;
		}

		$this->loadProductFromRequest($pageData)
			 ->buildProductData();

		if (empty($this->productData))
		{
			return $variables;
		}

		$newVariables = [];

		$newVariables['product_sku']   = Wb\arrayGet($this->productData, 'sku', '');
		$newVariables['product_name']  = Wb\arrayGet($this->productData, 'name', '');
		$newVariables['product_price'] = Wb\arrayGet($this->productData, 'offerPrice', '') . Wb\arrayGet($this->productData, 'priceCurrency', '');

		$newVariables['product_description'] = Wb\arrayGet($this->productData, 'description', '');
		$newVariables['product_brand']       = Wb\arrayGet($this->productData, 'brand', '');

		return array_merge(
			$variables,
			$newVariables
		);
	}

	/**
	 * Hook to store the finalized content data of current page.
	 * Used to force J2Store to output structured data also on com_content pages.
	 *
	 * @param array $contentData
	 */
	public function actionStorePreparedContent($contentData)
	{
		if ($this->config->isFalsy('j2storeEnableStructuredData'))
		{
			return;
		}

		$context = Wb\arrayGet($contentData, 'context', '');
		if (in_array($context, ['com_content.article', 'com_content.category.productlist']))
		{
			$this->contentData = $contentData;
		}
	}
}
