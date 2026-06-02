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

use Joomla\CMS\Factory;
use Joomla\Registry;

use Weeblr\Forseo\Data;
use Weeblr\Forseo\Helper;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Db;
use Weeblr\Wblib\Forseo\Html;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Support for Hikashop.
 *
 * Keep this in mind: https://code.weeblr.com/weeblr/forseo/-/issues/278
 *
 */
class Hikashop extends Base
{
	/**
	 * @var string Component name - with leading com_ removed, eg content for com_content
	 */
	protected $component = 'hikashop';

	/**
	 * @var \stdClass Cache for items being rendered.
	 */
	protected $contentData = null;

	/**
	 * @var string[] Views we can store.
	 */
	protected $includedViews = [
		'category',
		'product'
	];

	/**
	 * @var null|array List of layouts names that should be stored. None if null. All if empty.
	 */
	protected $includedLayouts = [];

	/**
	 * @var null|array List of layouts names that should NOT be stored. No effect if null or empty.
	 */
	protected $excludedLayouts = [
		'compare'
	];

	/**
	 * @var array List of schema types supported by this plugin.
	 */
	protected $supportedSdTypes = [
		Data\Sd::PRODUCT
	];

	/**
	 * @var bool Whether the plugin wants to filter raw, SEF URL. Time consuming, avoid if not needed.
	 */
	protected $filterShouldCollectUrlsFoundOnPage = false;

	/**
	 * @var Db\Helper Convenience Helper instance.
	 */
	protected $dbHelper;

	/**
	 * @var Model\Config
	 */
	private $config;

	/**
	 * Register event handler
	 *
	 * @param array $options Can inject custom factory and platform.
	 */
	public function __construct($options = [])
	{
		parent::__construct($options);

		$this->config = $this->factory
			->getThis('forseo.config', 'extensions');

		$this->dbHelper = $this->factory->getThe('db');
		$this->platform->registerEventHandler(
			'onHikashopBeforeDisplayView',
			[
				$this,
				'onHikashopBeforeDisplayView'
			]
		);
	}

	/**
	 * Add handlers for desired com_hikashop hooks.
	 */
	public function addHooks()
	{
		parent::addHooks();

		if ($this->platform->isFrontend())
		{
			$this->config = $this->factory
				->getThis('forseo.config', 'extensions');

			$this->hook->add(
				'forseo_page_canonical_or_duplicate',
				[
					$this,
					'filterPageCanonicalOrDuplicate'
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

		$inputVars = $pageData->get('input_vars', []);
		if (
			'product' == Wb\arrayGet($inputVars, 'ctrl')
			&&
			'show' == Wb\arrayGet($inputVars, 'task')
		) {
			$pageData->set('view', 'product');
			$pageData->set('layout', Wb\arrayGet($inputVars, 'layout', ''));
			$pageData->set(
				'item_id',
				$this->helper->compactValuesList(
					$this->getProductIdFromPageData($pageData)
				)
			);
		}

		return $pageData;
	}

	/**
	 * Extract product id from a page data object. Does not check whether the request is for
	 * a product page, only checking either product_id and then cid.
	 *
	 * @param Data\Page $pageData
	 * @return int|null
	 * @throws \Exception
	 */
	private function getProductIdFromPageData($pageData)
	{
		$inputVars = $pageData->get('input_vars', []);
		if (empty($inputVars))
		{
			return null;
		}

		$productId = Wb\arrayGet(
			$inputVars,
			'product_id',
			Wb\arrayGet(
				$inputVars,
				'cid',
				null
			)
		);

		return is_null($productId)
			? null
			: (int)$productId;
	}

	/**
	 * Implement construction of com_hikashop item unique id.
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

		// add language, as product will be the same in all languages
		// and this causes 4SEO to think pages in 2nd or more languages
		// are duplicates of the default
		$language = $pageData->get('lang');
		if (!empty($language))
		{
			$id['lang'] = $language;
		}

		// clean up id of item title
		$itemId = Wb\arrayGet($id, 'cid');
		if (empty($itemId))
		{
			$idFromRequest = $this->getProductIdFromPageData($pageData);
			if (empty($idFromRequest))
			{
				$id['cid'] = $idFromRequest;
				$itemId    = $idFromRequest;
			}

		}

		if (!empty($itemId))
		{
			// name would be a duplicate of cid really, can cause confusionif edited by user.
			unset($id['name']);
		}

		if (
			'show' === Wb\arrayGet($id, 'task')
			&&
			'product' === Wb\arrayGet($id, 'ctrl')
			&&
			'listing' === Wb\arrayGet($id, 'layout')
		) {
			// menu item options won't change a single product content, we can drop it
			unset($id['Itemid']);
			unset($id['view']);  // view is the source where the link is coming from, not the actual view
		}

		return $id;
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
	 * NB: Hikashop already lists products on category pages, we only provide for product views.
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
		if ($this->config->isFalsy('hikashopEnableStructuredData'))
		{
			return false;
		}

		if (strtolower($pageData->get('view')) != 'product')
		{
			return false;
		}

		return $canRunRule;
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
		if ($this->config->isFalsy('hikashopEnableStructuredData'))
		{
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

		$rules[] = $rule;

		return $rules;
	}

	/**
	 * Build automatically computed structured data for a com_hikashop product.
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
		if ($this->config->isFalsy('hikashopEnableStructuredData'))
		{
			return $autoFieldsData;
		}

		if (!$this->shouldRunFilter($pageData))
		{
			return $autoFieldsData;
		}

		if (
			empty($this->contentData)
			||
			empty($this->contentData->element)
		) {
			return $autoFieldsData;
		}

		$product = $this->contentData->element;
		/** @var Helper\Meta $metaHelper */
		$metaHelper = $this->factory->getA(Helper\Meta::class);

		if (
			array_key_exists('description', $autoFields)
			&&
			!empty($product->product_description))
		{
			$autoFieldsData['sdData']['description'] = $metaHelper->buildDescriptionFromContent(
				$product->product_description,
				[],
				[
					'abridge' => false
				]
			);
		}

		if (
			array_key_exists('image', $autoFields)
			&&
			!empty($product->images))
		{
			$images = [];
			foreach ($product->images as $image)
			{
				$imageRecord = [
					'@type' => Data\Sd::IMAGE_OBJECT,
					'url'   => System\Route::absolutify(
						Wb\slashTrimJoin(
							$this->contentData->image->uploadFolder_url,
							$image->file_path
						),
						true
					)
				];
				if (!empty($image->file_name))
				{
					$imageRecord['caption'] = $image->file_name;
				}
				$images[] = $imageRecord;
			}

			$autoFieldsData['sdData']['image'] = $images;
		}

		if (
			array_key_exists('name', $autoFields)
			&&
			!empty($product->product_name))
		{
			$autoFieldsData['sdData']['name'] = $product->product_name;
		}

		if (
			array_key_exists('offerPrice', $autoFields)
			&&
			!empty($product->prices)
		) {
			$currencyId = $product->prices[0]->price_currency_id;
			$offerPrice = $this->priceFromProduct($product, $currencyId);

			$currencies                                     = [];
			$currencies                                     = $this->contentData->currencyHelper->getCurrencies($currencyId, $currencies);
			$currency                                       = Wb\arrayGet($currencies, $currencyId);
			$autoFieldsData['sdData']['offerPriceCurrency'] = empty($currency)
				? ''
				: $currency->currency_code;
			$autoFieldsData['sdData']['offerPrice']         = $offerPrice;
			$autoFieldsData['sdData']['offerAvailability']  = Data\Sd::OFFERS_IN_STOCK;
			$autoFieldsData['sdData']['offerItemCondition'] = empty($product->product_condition)
				? null
				: 'http://schema.org/' . $product->product_condition;

			$autoFieldsData['sdData']['offerUrl'] = System\Route::absolutify(
				$pageData->get('full_url')
			);
		}

		if (
			array_key_exists('sku', $autoFields)
			&&
			!empty($product->product_code))
		{
			$autoFieldsData['sdData']['sku'] = $product->product_code;
		}

		if (
			array_key_exists('brand', $autoFields)
			&&
			!empty($product->product_manufacturer_id)
		) {
			$categoryClass                     = \hikashop_get('class.category');
			$manufacturer                      = $categoryClass->get($product->product_manufacturer_id);
			$autoFieldsData['sdData']['brand'] = $manufacturer->category_name;
		}

		$voteOption = $this->contentData->config->get('enable_status_vote');
		if (in_array($voteOption, ['vote', 'two', 'both']))
		{
			if (array_key_exists('aggregateRating', $autoFields))
			{
				$rating = $this->buildAggregateRating($product);
				if (!empty($rating))
				{
					$autoFieldsData['sdData']['aggregateRating'] = $rating;
				}
			}
		}

		if (in_array($voteOption, ['comment', 'two', 'both']))
		{
			$reviews = $this->loadReviews($product);
			if (!empty($reviews))
			{
				$autoFieldsData['sdData']['review'] = $reviews;
			}
		}

		return $autoFieldsData;
	}

	/**
	 * Reviews display is disabled for now as Hikashop does not store the necessary information
	 * to build a valid Review Snippet per https://developers.google.com/search/docs/advanced/structured-data/review-snippet#review-properties
	 *
	 * Current spec requires for a review to have at least:
	 * - an author
	 * - a review rating
	 *
	 * Default Hikashop review system separates votinf (ie giving a rating) from commenting (a textual review)
	 * without any mean to reconcile both. So for any given text comment, we cannot retrieve the associated rating value,
	 * simply because users can only enter each separately - and they are stored separately by Hikashop.
	 * Happens whether logged in or not.
	 *
	 * @param $product
	 * @return array
	 * @throws \Exception
	 */
	private function loadReviews($product)
	{
		$hikashopConfig          = \hikashop_config();
		$commentsToShow          = $hikashopConfig->get('number_comment_product');
		$voteCommentSort         = $hikashopConfig->get('vote_comment_sort');
		$voteCommentSortFrontend = $hikashopConfig->get('vote_comment_sort_frontend', 0);
		$usefulRating            = $hikashopConfig->get('useful_rating', 0);
		$app                     = Factory::getApplication();
		$start                   = $app->getUserState('com_hikashop.vote.limitstart', 0);

		$orderBy = 'vote_useful DESC, vote_date ASC';
		if ($voteCommentSort == "date")
		{
			$orderBy = 'vote_date ASC';
		}
		elseif ($voteCommentSort == "date_desc")
		{
			$orderBy = 'vote_date DESC';
		}
		if ($voteCommentSortFrontend)
		{
			$sortComments = \hikaInput::get()->getString('sort_comment', '');
			if ($sortComments == "date")
			{
				$orderBy = 'vote_date ASC';
			}
			else if ($sortComments == "date_desc")
			{
				$orderBy = 'vote_date DESC';
			}
			else if ($usefulRating && $sortComments == "helpful")
			{
				$orderBy = 'vote_useful DESC, vote_date ASC';
			}
		}

		if (!$this->contentData->params instanceof Registry\Registry)
		{
			$this->contentData->params = new Registry\Registry(
				$this->contentData->params
			);
		}

		$voteRefId = (int)$this->contentData->params->get('vote_ref_id');
		$voteType  = $this->contentData->params->get('vote_type');
		$db        = $this->getPlatformDb();
		$query     = 'select ' . $db->qn('hika_vote.vote_date') . ' as ' . $db->qn('datePublished')
					 . ', ' . $db->qn('hika_vote.vote_comment') . ' as ' . $db->qn('reviewBody')
					 . ', ' . $db->qn('hika_vote.vote_rating') . ' as ' . $db->qn('rating')
					 . ', ' . $db->qn('hika_vote.vote_pseudo') . ' as ' . $db->qn('pseudo')
					 . ', ' . $db->qn('users.name') . ' as ' . $db->qn('author')
					 . ' FROM ' . $db->qn('#__hikashop_vote') . ' AS ' . $db->qn('hika_vote')
					 . ' LEFT JOIN ' . $db->qn('#__hikashop_user') . ' AS ' . $db->qn('hika_user') . ' ON ' . $db->qn('hika_vote.vote_user_id') . ' = ' . $db->qn('hika_user.user_id')
					 . ' LEFT JOIN ' . $db->qn('#__users') . ' AS ' . $db->qn('users') . ' ON ' . $db->qn('hika_user.user_cms_id') . ' = ' . $db->qn('users.id')
					 . ' where '
					 . $db->qn('hika_vote.vote_ref_id') . ' = ' . $db->q($voteRefId)
					 . ' and ' . $db->qn('hika_vote.vote_type') . ' = ' . $db->q($voteType)
					 . ' and ' . $db->qn('hika_vote.vote_rating') . ' != 0 '
					 . ' order by ' . $orderBy
					 . ' LIMIT ' . (int)$start . ', ' . (int)$commentsToShow;

		$db->setQuery($query);
		$reviewsData = $db->loadAssocList();
		$reviewsData = empty($reviewsData)
			? []
			: $reviewsData;
		$reviews     = [];
		foreach ($reviewsData as $reviewData)
		{
			$review                 = $reviewData;
			$review['reviewRating'] = [
				'ratingValue' => $reviewData['rating']
			];
			unset($review['rating']);

			if (empty($review['author']))
			{
				$review['author'] = $reviewData['pseudo'];
			}
			unset($review['pseudo']);

			if (!empty($review['datePublished']))
			{
				$dt                      = System\Date::toExtendedDateTime();
				$review['datePublished'] = $dt
					->setTimestamp(
						$review['datePublished']
					)->toMysql();
			}

			if (!empty($review['author']))
			{
				$review['author'] =
					[
						'@type' => 'http://schema.org/' . Data\Sd::PERSON,
						'name'  => $reviewData['author']
					];
				$reviews[]        = array_merge(
					$review,
					[
						'@type' => 'http://schema.org/' . Data\Sd::REVIEW
					]
				);
			}
		}

		return $reviews;
	}

	/**
	 * Build an aggregateRating record if applicable to current content type.
	 *
	 * @param object $product
	 * @return array|null
	 */
	private function buildAggregateRating($product)
	{
		$reviews = null;
		if (
			isset($product->product_average_score)
			&&
			!empty($product->product_total_vote)
		) {
			$reviews = [
				'@type'       => Data\Sd::AGGREGATE_RATING,
				'ratingValue' => $product->product_average_score,
				'reviewCount' => $product->product_total_vote,
				'worstRating' => 0,
				'bestRating'  => 5
			];
		}

		return $reviews;
	}

	/**
	 * Whether passed page should be considered canonical or duplicate (automatically). Presence of a duplicate
	 * (ie with same content_id) has already been checked.
	 *
	 * @param int       $urlType  Data\Page::CANONICAL | Data\Page::DUPLICATE
	 * @param Data\Page $pageData The page object.
	 *
	 * @return int
	 *
	 * @throws \Exception
	 */
	public function filterPageCanonicalOrDuplicate(int $urlType, Data\Page $pageData)
	{
		if (!$this->shouldRunFilter($pageData))
		{
			return $urlType;
		}

		if (
			empty($this->contentData)
			||
			empty($this->contentData->canonical))
		{
			return $urlType;
		}

		$baseUrl             = $this->platform->getBaseUrl();
		$normalizedCanonical = Wb\Ltrim(
			$this->contentData->canonical,
			[
				$baseUrl,
				Wb\Ltrim($baseUrl, '/'),
				'/'
			]
		);

		if ($pageData->get('full_url') !== $normalizedCanonical)
		{
			$urlType = Data\Page::DUPLICATE;
		}

		return $urlType;
	}

	/**
	 * Implement default construction of a content hash that may be computed
	 * from a raw content array as provided by the platform.
	 *
	 * Cannot use onContentPrepare data as Hikashop does not pass much. Use stored $contentData instead.
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
		$inputVars = $pageData->get('input_vars', []);
		if (
			'product' != Wb\arrayGet($inputVars, 'ctrl')
			||
			'show' != Wb\arrayGet($inputVars, 'task')
			||
			empty($this->contentData)
		) {
			return $hash;
		}

		$id = $this->contentData->element->category_id
			  . $this->contentData->element->product_id
			  . $this->contentData->element->product_code
			  . $this->contentData->element->product_name
			  . strip_tags($this->contentData->element->product_description)
			  . $this->contentData->element->product_meta_description;

		return md5(json_encode($id));
	}

	/**
	 * Hook to capture Hikashop content data.
	 *
	 * @param \ProductViewProduct $viewObj
	 */
	public function onHikashopBeforeDisplayView(&$viewObj)
	{
		if (
			$this->platform->isBackend()
			||
			empty($viewObj)
		) {
			return;
		}

		$passedViewObj = $this->platform->getEventData(
			$viewObj
		);

		if (
			empty($passedViewObj)
			||
			!empty($passedViewObj->module)
			||
			empty($passedViewObj->element)
		) {
			return;
		}

		$this->contentData = $passedViewObj;
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
	 * @throws \Exception
	 */
	protected function filterExtractPageImagesFromContentData($extractedImages, $context, $content, $contentObject, $pageData, $pageMeta)
	{
		static $extracted = null;

		if (strtolower($pageData->get('view')) != 'product')
		{
			return $extractedImages;
		}

		if (is_null($extracted))
		{
			if (!empty($this->contentData) && !empty($this->contentData->element) && !empty($this->contentData->element->images))
			{
				$appConfig    = $this->factory->getThis('forseo.config', 'app');
				$imageSpec    = $appConfig->get('imageDetectionRequireSizeSd');
				$ogpImageSpec = $appConfig->get('imageDetectionRequireSizeOgp');
				$metaHelper   = $this->factory->getA(Helper\Meta::class);

				$images = [];
				foreach ($this->contentData->element->images as $image)
				{
					$url = System\Route::absolutify(
						Wb\slashTrimJoin(
							$this->contentData->image->uploadFolder_url,
							$image->file_path
						),
						true
					);

					$caption = empty($image->file_name)
						? ''
						: $image->file_name;

					if (empty($images['page_image']))
					{
						$img = $metaHelper->validateImageFromContent(
							$url,
							$imageSpec
						);
						if (!empty($img))
						{
							$images['page_image'] = array_merge(
								$img,
								[
									'alt' => $caption
								]
							);
						}
					}

					if (empty($images['page_sharing_image']))
					{
						$img = $metaHelper->validateImageFromContent(
							$url,
							$ogpImageSpec
						);
						if (!empty($img))
						{
							$images['page_sharing_image'] = array_merge(
								$img,
								[
									'alt' => $caption
								]
							);
						}
					}

					if (!empty($images['page_image']) && !empty($images['page_sharing_image']))
					{
						break;
					}
				}

				if (!empty($images))
				{
					$extracted       = $images;
					$extractedImages = $images;
				}
			}
		}
		else
		{
			$extractedImages = $extracted;
		}

		return $extractedImages;
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
		$view = $pageData->get('view');
		if (!in_array(strtolower($view), ['product', 'category']))
		{
			return $lastMod;
		}

		$itemId = $this->getProductIdFromPageData($pageData);
		if (empty($itemId))
		{
			return $lastMod;
		}

		if ('product' == $view)
		{
			$dbTable                  = '#__hikashop_product';
			$modificationColumn       = 'product_modified';
			$modificationBackupColumn = 'product_created';
			$idColumn                 = 'product_id';
		}
		else
		{
			$dbTable                  = '#__hikashop_category';
			$modificationColumn       = 'category_modified';
			$modificationBackupColumn = 'category_created';
			$idColumn                 = 'category_id';
		}

		$modData = $this->dbHelper
			->selectAssoc(
				$dbTable,
				[$modificationColumn, $modificationBackupColumn],
				[
					$idColumn => $itemId
				]
			);

		if (empty($modData))
		{
			return null;
		}

		$lastMod = Wb\arrayGet($modData, $modificationColumn, null);
		$lastMod = empty($lastMod)
			? Wb\arrayGet($modData, $modificationBackupColumn, null)
			: $lastMod;

		if (!empty($lastMod))
		{
			$dt      = System\Date::toExtendedDateTime();
			$lastMod = $dt->setTimestamp($lastMod)
						  ->toMysql();
		}

		return $lastMod;
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
		if ($this->config->isFalsy('hikashopEnableStructuredData'))
		{
			return $patterns;
		}

		return array_merge(
			$patterns,
			[
				'~(itemprop="[^"]*")? itemscope(="[^"]*")? itemtype="[^"]*"~isU',
				'~<meta\s+itemprop="[^"]+"\s+content="[^"]+"\s*/?>~isU',
				'~\sitemprop="[^"]+"~isU'
			]
		);
	}

	/**
	 * Filter auto-generated dynamic variables that will be substituted by 4SEO based on rules.
	 *
	 * @param array     $variables
	 * @param Data\Page $pageData
	 * @return array
	 * @throws \Exception
	 */
	public function filterExpandableVariables($variables, $pageData)
	{
		if (!$this->shouldRunFilter($pageData))
		{
			return $variables;
		}

		if (
			empty($this->contentData)
			||
			'product' !== $this->contentData->ctrl
		) {
			return $variables;
		}

		$hikashopVariables = [];

		$hikashopVariables['product_sku']   = $this->contentData->element->product_code;
		$hikashopVariables['product_name']  = $this->contentData->element->product_name;
		$hikashopVariables['product_price'] = $this->contentData->currencyHelper->format(
			empty($this->contentData->params->get('price_with_tax'))
				? $this->contentData->element->prices[0]->price_value
				: $this->contentData->element->prices[0]->price_value_with_tax
		);

		$hikashopVariables['product_description'] = $this->contentData->element->product_description;
		if ($this->contentData->element->product_manufacturer_id)
		{
			$categoryClass                      = \hikashop_get('class.category');
			$manufacturer                       = $categoryClass->get($this->contentData->element->product_manufacturer_id);
			$hikashopVariables['product_brand'] = $manufacturer->category_name;
		}
		if ($this->contentData->element->product_condition)
		{
			$hikashopVariables['product_condition'] = $this->contentData->element->product_condition;
		}

		return array_merge(
			$variables,
			$hikashopVariables
		);
	}

	/**
	 * Builds a price string from a product instance.
	 *
	 * @param \stdClass $product
	 * @param int       $currencyId
	 *
	 * @return array
	 */
	private function priceFromProduct($product, $currencyId = 0)
	{
		$currencyId = empty($currencyId)
			? $product->prices[0]->price_currency_id
			: $currencyId;

		$round      = $this->contentData->currencyHelper->getRounding($currencyId, true);
		$offerPrice = empty($this->contentData->params->get('price_with_tax'))
			? $product->prices[0]->price_value
			: $product->prices[0]->price_value_with_tax;
		return $this->contentData->currencyHelper->round($offerPrice, $round, 0, true);
	}

	/**
	 * Wrapper to get the platform DB object regardless of platform version.
	 *
	 * @return mixed
	 */
	private function getPlatformDb()
	{
		return version_compare(\JVERSION, '4.0', '<')
			? Factory::getDbo()
			: Factory::getContainer()->get('db');
	}
}
