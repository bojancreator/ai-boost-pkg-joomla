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
use Weeblr\Wblib\Forseo\Html;
use Weeblr\Wblib\Forseo\Joomla\Registry;

use Joomla\CMS\Factory as JoomlaFactory;
use Joomla\CMS\Router\Route;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 */
class Content extends Base
{
	public const STATUS_UNPUBLISHED = 0;
	public const STATUS_PUBLISHED   = 1;
	public const STATUS_ARCHIVED    = 2;
	public const STATUS_TRASHED     = -2;

	/**
	 * @var string Component name - with leading com_ removed, eg content for com_content
	 */
	protected $component = 'content';

	/**
	 * @var \stdClass Cache for com_content being rendered.
	 */
	protected $contentData = null;

	/**
	 * @var string[] com_content views we can store.
	 */
	protected $includedViews = [
		'archive',
		'article',
		'categories',
		'category',
		'featured'
	];

	/**
	 * @var null|array List of layouts names that should be stored. None if null. All if empty.
	 */
	protected $includedLayouts = [];

	/**
	 * @var null|array List of layouts names that should NOT be stored. No effect if null or empty.
	 */
	protected $excludedLayouts = [
		'edit'
	];

	/**
	 * @var array List of schema types supported by this plugin.
	 */
	protected $supportedSdTypes = [
		Data\Sd::ARTICLE,
		Data\Sd::NEWS_ARTICLE,
		Data\Sd::BLOG_POSTING,
		Data\Sd::VIDEO_OBJECT,
		Data\Sd::COURSE,
		Data\Sd::EVENT,
		Data\Sd::PRODUCT,
		Data\Sd::RECIPE,
		Data\Sd::FAQ_PAGE,
		Data\SD::MOVIE
	];

	/**
	 * @var bool Whether the plugin wants to filter raw, SEF URL. Time consuming, avoid if not needed.
	 */
	protected $filterShouldCollectUrlsFoundOnPage = false;

	/**
	 * Add handlers for desired com_content hooks.
	 */
	public function addHooks()
	{
		parent::addHooks();

		$this->platform->runActionOnEvent(
			'onContentAfterSave',
			'forseo_platformOnAfterSave',
			[
				$this,
				'doOnContentAfterSave'
			]
		);

		$this->platform->runActionOnEvent(
			'onContentChangeState',
			'forseo_platformOnChangeState',
			[
				$this,
				'doOnContentStateChange'
			]
		);

		if ($this->platform->isFrontend())
		{
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

			$this->hook->add(
				'forseo_extract_images',
				[
					$this,
					'filterExtractImages'
				]
			);
		}
	}

	/**
	 * What we want to check:
	 * - item (article or category) is published (how about workflow??)
	 * - item has a public access level - but that's not defined actually
	 * @param array $event
	 * @return void
	 */
	public function doOnContentAfterSave($event)
	{
		// if workflow is disabled, there's a state field
		// If workflow is enabled, there's a transition field instead
		// problem is, workflow steps can be anything. We can work using
		// the defaults, but then we need a param to let user disable them
		// if they don't use the defaults transitions
		if (
			Wb\arrayIsFalsy($event, 'context')
			||
			Wb\arrayIsFalsy($event, 'data')
			||
			!Wb\arrayHasKey($event, 'isNew')
			||
			!\is_array($event['data'])
			||
			// no state, workflow enabled, we don't support that
			!Wb\arrayHasKey($event, ['data', 'state'])
		)
		{
			return;
		}

		if ('com_content.article' !== $event['context'])
		{
			return;
		}

		if ($event['isNew'])
		{
			// new item, not been discovered yet
			// we let the regular crawling process find it
			return;
		}

		// existing item, pretend the state changed
		$this->doOnContentStateChange(
			[
				'context' => $event['context'],
				'pks'     => [Wb\arrayGet($event, ['data', 'id'])],
				'value'   => Wb\arrayGetInt($event, ['data', 'state'])
			]
		);

	}

	/**
	 * @param array $event
	 * @return void
	 */
	public function doOnContentStateChange($event)
	{
		if (
			Wb\arrayIsFalsy($event, 'context')
			||
			Wb\arrayIsFalsy($event, 'pks')
		)
		{
			return;
		}

		if ('com_content.article' !== $event['context'])
		{
			return;
		}

		$db = $this->factory->getThe('db');
		if (self::STATUS_PUBLISHED !== $event['value'])
		{
			// item has been unpublished or archived or trashed
			// We mark it as so and trigger a sitemap rebuild
			foreach ($event['pks'] as $itemId)
			{
				$contentId = 'id=' . $itemId . '&option=com_content&view=article';
				$db->update(
					'#__forseo_pages',
					[
						'status' => System\Http::RETURN_SERVICE_UNAVAILABLE
					],
					[
						'content_id' => $contentId
					]
				);
			}
		}

		if (self::STATUS_PUBLISHED === $event['value'])
		{
			// item has been unpublished or archived or trashed
			// We mark it as so and trigger a sitemap rebuild
			foreach ($event['pks'] as $itemId)
			{
				$contentId = 'id=' . $itemId . '&option=com_content&view=article';
				$db->update(
					'#__forseo_pages',
					[
						'status' => 0
					],
					[
						'content_id' => $contentId,
						'status'     => System\Http::RETURN_SERVICE_UNAVAILABLE
					]
				);
			}
		}

		// trigger sitemap rebuild
		$this->factory
			->getThe('forseo.keystore')
			->safePut(
				'sitemap.rebuildRequired',
				System\Date::getUTCNow(
					'Y-m-d H:i:s',
					true  // $refresh =
				)
			);
	}

	/**
	 * The save event.
	 *
	 * @param string  $context The context
	 * @param JTable  $item    The article data
	 * @param boolean $isNew   Is new item
	 * @param array   $data    The validated data
	 *
	 * @return  boolean
	 *
	 * @since   3.9.0
	 */
//	public function onContentAfterSave($context, $item, $isNew, $data = array())
//	{
//		// Create correct context for category
//		if ($context == 'com_categories.category')
//		{
//			$context = $item->get('extension') . '.categories';
//
//			// Set the catid on the category to get only the fields which belong to this category
//			$item->set('catid', $item->get('id'));
//		}
//
//		// Check the context
//		$parts = FieldsHelper::extract($context, $item);
//
//		if (!$parts)
//		{
//			return true;
//		}
//
//		// Compile the right context for the fields
//		$context = $parts[0] . '.' . $parts[1];
//
//		// Loading the fields
//		$fields = FieldsHelper::getFields($context, $item);
//
//		if (!$fields)
//		{
//			return true;
//		}
//
//		// Get the fields data
//		$fieldsData = !empty($data['com_fields']) ? $data['com_fields'] : array();
//
//		// Loading the model
//		/** @var FieldsModelField $model */
//		$model = BaseDatabaseModel::getInstance('Field', 'FieldsModel', array('ignore_request' => true));
//
//		// Loop over the fields
//		foreach ($fields as $field)
//		{
//			// Find the field of this type repeatable
//			if ($field->type !== $this->_name)
//			{
//				continue;
//			}
//
//			// Determine the value if it is available from the data
//			$value = key_exists($field->name, $fieldsData) ? $fieldsData[$field->name] : null;
//
//			// Handle json encoded values
//			if (!is_array($value))
//			{
//				$value = json_decode($value, true);
//			}
//
//			// Setting the value for the field and the item
//			$model->setFieldValue($field->id, $item->get('id'), json_encode($value));
//		}
//
//		return true;
//	}

// onContentChangeState
	/**
	 * After save content logging method
	 * This method adds a record to #__action_logs contains (message, date, context, user)
	 * Method is called right after the content is saved
	 *
	 * @param Model\AfterSaveEvent $event The event instance.
	 *
	 * @return  void
	 *
	 * @since   3.9.0
	 */
//	public function onContentAfterSave(Model\AfterSaveEvent $event): void
//	{
//		$context = $event->getContext();
//		$article = $event->getItem();
//		$isNew   = $event->getIsNew();
//
//		if (isset($this->contextAliases[$context])) {
//			$context = $this->contextAliases[$context];
//		}
//
//		$params = $this->getActionLogParams($context);
//
//		// Not found a valid content type, don't process further
//		if ($params === null) {
//			return;
//		}
//
//		[$option, $contentType] = explode('.', $params->type_alias);
//
//		if (!$this->checkLoggable($option)) {
//			return;
//		}
//
//		if ($isNew) {
//			$messageLanguageKey = $params->text_prefix . '_' . $params->type_title . '_ADDED';
//			$defaultLanguageKey = 'PLG_SYSTEM_ACTIONLOGS_CONTENT_ADDED';
//		} else {
//			$messageLanguageKey = $params->text_prefix . '_' . $params->type_title . '_UPDATED';
//			$defaultLanguageKey = 'PLG_SYSTEM_ACTIONLOGS_CONTENT_UPDATED';
//		}
//
//		// If the content type doesn't have its own language key, use default language key
//		if (!$this->getApplication()->getLanguage()->hasKey($messageLanguageKey)) {
//			$messageLanguageKey = $defaultLanguageKey;
//		}
//
//		$id = empty($params->id_holder) ? 0 : $article->{$params->id_holder};
//
//		$message = [
//			'action'   => $isNew ? 'add' : 'update',
//			'type'     => $params->text_prefix . '_TYPE_' . $params->type_title,
//			'id'       => $id,
//			'title'    => $article->{$params->title_holder} ?? '',
//			'itemlink' => ActionlogsHelper::getContentTypeLink($option, $contentType, $id, $params->id_holder, $article),
//		];
//
//		$this->addLog([$message], $messageLanguageKey, $context);
//	}

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
		$dynamicCanonical = parent::filterDynamicCanonical($dynamicCanonical, $pageData);
		if (!is_null($dynamicCanonical))
		{
			return $dynamicCanonical;
		}

		$inputVars = $pageData->get('input_vars', []);
		$format    = Wb\arrayGet($inputVars, 'format', 'html');
		if ('html' !== $format)
		{
			return $dynamicCanonical;
		}

		$view = Wb\arrayGet($inputVars, 'view');
		if (!in_array($view, ['article', 'category']))
		{
			return $dynamicCanonical;
		}

		if (
			Wb\arrayIsTruthy($inputVars, 'task')
			||
			Wb\arrayIsTruthy($inputVars, 'a_id')
		)
		{
			return $dynamicCanonical;
		}

		$id = Wb\arrayGet($inputVars, 'id');
		if (empty($id))
		{
			return $dynamicCanonical;
		}

		// hack for Joomla 4 bug
		if ('article' === $view)
		{
			$layout = Wb\arrayGet($inputVars, 'layout');
			if ('blog' === $layout)
			{
				unset($inputVars['layout']);
			}
		}

		// keep going now
		$nonSefVars = array_intersect_key(
			$inputVars,
			array_flip(
				[
					'option',
					'view',
					'layout',
					'id',
					'catid',
					'limitstart',
					'limit',
					'Itemid'
				]
			)
		);

		$nonSefUrl = implode(
			'?',
			[
				'index.php',
				http_build_query(
					$nonSefVars,
					'',
					'&',
					PHP_QUERY_RFC3986
				)
			]
		);

		return Route::link(
			'site',
			$nonSefUrl,
			false // $xhtml
		);
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
		if ('article' !== $pageData->get('view'))
		{
			return $rules;
		}

		if ($this->factory->getThis('forseo.config', 'sd')->isFalsy('enabledBuiltInRules'))
		{
			return $rules;
		}

		$rule = $this->factory->getA(Data\Rule::class);

		// Article
		$ruleData = [
			'actionSdType' => [Data\Sd::ARTICLE],
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
		if ('article' !== strtolower($pageData->get('view')))
		{
			return false;
		}

		return $canRunRule;
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
		if (!$this->shouldRunFilter($pageData))
		{
			return $autoFieldsData;
		}

		if (array_key_exists('url', $autoFields))
		{
			$autoFieldsData['sdData']['url'] = $requestInfo->get('page_url');
		}

		if (
			!empty($this->contentData)
			&&
			!empty($this->contentData['content'])
		)
		{
			if (
				array_key_exists('headline', $autoFields)
				&&
				isset($this->contentData['content']->title))
			{
				$autoFieldsData['sdData']['headline'] = $this->contentData['content']->title;
			}
			if (
				array_key_exists('name', $autoFields)
				&&
				isset($this->contentData['content']->title))
			{
				$autoFieldsData['sdData']['name'] = $this->contentData['content']->title;
			}

			if (
				array_key_exists('datePublished', $autoFields)
				&&
				isset($this->contentData['content']->publish_up)
			)
			{
				$autoFieldsData['sdData']['datePublished'] = $this->contentData['content']->publish_up;
			}

			if (
				array_key_exists('dateCreated', $autoFields)
				&&
				isset($this->contentData['content']->publish_up)
			)
			{
				$autoFieldsData['sdData']['dateCreated'] = $this->contentData['content']->publish_up;
			}

			if (
				array_key_exists('dateModified', $autoFields)
				&&
				isset($this->contentData['content']->modified))
			{
				$dateModified                             =
					empty($this->contentData['content']->modified)
					||
					'0000-00-00 00:00:00' == $this->contentData['content']->modified
						? $this->contentData['content']->publish_up
						: $this->contentData['content']->modified;
				$autoFieldsData['sdData']['dateModified'] = $dateModified;
			}

			if (
				array_key_exists('author', $autoFields)
				&&
				isset($this->contentData['content']->author)
				&&
				isset($this->contentData['content']->created_by)
				&&
				!Wb\arrayIsEmpty($spec, 'useItemAuthor')
			)
			{
				$authorUserName = $this->contentData['content']->author;
				$authorId       = $this->factory
					->getA(Helper\Sd::class)
					->toId(
						$authorUserName
						. '_'
						. System\Auth::shortHash(
							$authorUserName . $this->contentData['content']->created_by
						)
					);

				$autoFieldsData['identitiesUsed']    = [
					'author' => $authorId
				];
				$autoFieldsData['identitiesCreated'] = [
					$authorId => [
						'@type' => Data\Sd::PERSON, // Person | Organization
						'name'  => $authorUserName
					]
				];

				$authorField = [
					'@id' => $baseId . '#' . $authorId
				];

				$autoFieldsData['sdData']['author'] = $authorField;
			}

			if (array_key_exists('aggregateRating', $autoFields))
			{
				$reviews = $this->buildAggregateRating($spec);
				if (!empty($reviews))
				{
					$autoFieldsData['sdData']['aggregateRating'] = $reviews;
				}
			}

			// VideoObject
			if (
				array_key_exists('contentUrl', $autoFields)
				&&
				isset($this->contentData['content']->publish_up))
			{
				// search for 1st mp4 file in src attr in content
				$url = Html\Extract::extractVideo(
					$this->contentData['content']->text,
					'mp4'
				);
				if (!empty($url))
				{
					$autoFieldsData['sdData']['contentUrl'] = $url;
				}
			}
			if (
				array_key_exists('uploadDate', $autoFields)
				&&
				isset($this->contentData['content']->publish_up))
			{
				$autoFieldsData['sdData']['uploadDate'] = $this->contentData['content']->publish_up;
			}

			// Event
			if (
				array_key_exists('startDate', $autoFields)
				&&
				isset($this->contentData['content']->publish_up))
			{
				$autoFieldsData['sdData']['startDate'] = $this->contentData['content']->publish_up;
			}

		}

		return $autoFieldsData;
	}

	/**
	 * Build an aggregateRating record if applicable to current content type.
	 *
	 * @param array $spec
	 * @return array|null
	 */
	private function buildAggregateRating($spec)
	{
		$reviews  = null;
		$itemType = Wb\arrayGet($spec, 'actualType', '');
		if (in_array($itemType, Data\Sd::REVIEWABLE_TYPES))
		{
			$rating      = $this->contentData['content']->rating;
			$ratingCount = $this->contentData['content']->rating_count;
			if (null !== $rating && !empty($ratingCount))
			{
				$reviews = [
					'@type'       => Data\Sd::AGGREGATE_RATING,
					'ratingValue' => $rating,
					'reviewCount' => $ratingCount,
					'worstRating' => 0,
					'bestRating'  => 5
				];
			}
		}

		return $reviews;
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

		$view = $pageData->get('view');
		if (!in_array($view, ['featured']))
		{
			return $pageData;
		}

		// this is a featured view
		// this a category view, are we on a second or more pages?
		// if so, include page number in id to distinguish them.
		$inputVars = $pageData->get('input_vars', []);
		$Itemid    = Wb\arrayGet(
			$inputVars,
			'Itemid',
			null
		);

		if (is_null($Itemid))
		{
			return $pageData;
		}

		// search menu item for a category specification
		$menuItem = JoomlaFactory::getApplication()
								 ->getMenu('site')
								 ->getItem($Itemid);
		if (empty($menuItem))
		{
			return $pageData;
		}

		$featuredCategories = $menuItem
			->getParams()
			->get('featured_categories');
		if (empty($featuredCategories))
		{
			return $pageData;
		}

		// update the item_id based on non-sef variables
		$pageData->set(
			'item_id',
			$this->helper->compactValuesList($featuredCategories)
		);

		return $pageData;
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

		// Multipage articles: if current page has ?showall, it should be canonical
		$inputVars          = $pageData->get('input_vars', []);
		$hasShowallVar      = Wb\arrayGetInt($inputVars, 'showall') === 1;
		$isShowAllMultipage = $hasShowallVar
							  ||
							  Wb\contains($pageData->get('full_url'), 'showall=1');

		if (
			!$isShowAllMultipage
			&&
			$pageData->isFalsy('isMultiPage')
		)
		{
			return $urlType;
		}

		$hasShowAll = $this->platform->isShowAllEnabled();

		// Site is configured to display a showAll page.
		if ($hasShowAll)
		{
			$urlType = $isShowAllMultipage
				? Data\Page::CANONICAL
				: Data\Page::DUPLICATE;
		}

		// Site is not configured to display show all
		// all pages are canonical
		if (!$hasShowAll)
		{
			$urlType = Data\Page::CANONICAL;
		}

		return $urlType;
	}

	/**
	 * Implement construction of com_content item unique id.
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

		// clean up id of item title
		$itemId = Wb\arrayGet($id, 'id');
		if (!empty($itemId))
		{
			$id['id'] = $this->helper->cleanIdsWithColons($itemId);
		}

		// multipage article
		unset($id['showall']);

		if ('article' === Wb\arrayGet($id, 'view'))
		{
			// for an article, only keep option, view and id
			$contentIdvars = [
				'option',
				'view',
				'id'
			];

			if (!$this->platform->isShowAllEnabled())
			{
				$contentIdvars[] = 'limitstart';
			}

			// and limitstart if showall is not enabled (ie all subpages re canonical)
			$id = array_intersect_key(
				$id,
				array_flip(
					$contentIdvars
				)
			);
		}

		return $id;
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
		$context = Wb\arrayGet($contentData, 'context', '');

		if ('com_content.article' != $context)
		{
			return $hash;
		}

		$content = Wb\arrayGet($contentData, 'content');
		if (
			empty($content)
			||
			// Some extensions (GSD) create invalid records, missing some parts.
			!isset($content->catid)
			||
			!isset($content->author)
			||
			!isset($content->title)
		)
		{
			return $hash;
		}

		$id = $content->catid
			  . $content->author
			  . strip_tags($content->text)
			  . $content->images
			  . $content->language
			  . $content->title
			  . $content->urls;

		return md5(json_encode($id));
	}

	/**
	 * Hook to store the finalized content data of current page.
	 *
	 * @param array $contentData
	 * @return void
	 */
	public function actionStorePreparedContent($contentData)
	{
		$context = Wb\arrayGet($contentData, 'context', '');

		if ('com_content.article' !== $context)
		{
			return;
		}

		$this->contentData = $contentData;
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
		if ('com_content.article' == $context)
		{
			$appConfig    = $this->factory->getThis('forseo.config', 'app');
			$imageSpec    = $appConfig->get('imageDetectionRequireSizeSd');
			$ogpImageSpec = $appConfig->get('imageDetectionRequireSizeOgp');

			$extractedImages['page_image']         = $this->detectComContentImages($contentObject, $imageSpec);
			$extractedImages['page_sharing_image'] = $this->detectComContentImages($contentObject, $ogpImageSpec);
		}

		return $extractedImages;
	}

	/**
	 * Detect whether an article Full or intro image are suitable as a page image.
	 *
	 * @param Object $contentObject
	 * @param array  $imageSpec
	 *
	 * @return array|string
	 */
	private function detectComContentImages($contentObject, $imageSpec)
	{
		if (!empty($contentObject) && !empty($contentObject->images))
		{
			$imageDef       = new Registry\Registry($contentObject->images);
			$possibleImages = [
				'image_fulltext',
				'image_intro'
			];
			$imageHelper    = $this->factory->getA(Helper\Meta::class);
			foreach ($possibleImages as $possibleImage)
			{
				$imageUrl = $imageDef->get(
					$possibleImage,
					''
				);

				// special J4 cleanup
				if (Wb\contains($imageUrl, '#joomlaImage'))
				{
					$imageUrlBits = explode('#joomlaImage', $imageUrl, 2);
					$imageUrl     = array_shift($imageUrlBits);
				}

				$image = $imageHelper->validateImageFromContent(
					$imageUrl,
					$imageSpec
				);
				// return right away if a valid image is found
				// User has set those images, they should be representative
				if (!empty($image))
				{
					// possibly extract alt
					$image['alt'] = $imageDef->get($possibleImage . '_alt', '');
					// possibly extract caption
					$image['caption'] = $imageDef->get($possibleImage . '_caption', '');

					return $image;
				}
			}
		}

		return '';
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
		$inputVars = $pageData->get('input_vars', []);
		$view      = Wb\arrayGet($inputVars, 'view', '');
		if (!in_array($view, ['article', 'category']))
		{
			return $lastMod;
		}

		$itemId = (int)Wb\arrayGet($inputVars, 'id');
		if (empty($itemId))
		{
			return $lastMod;
		}

		if ('article' == $view)
		{
			$dbTable                  = '#__content';
			$modificationColumn       = 'modified';
			$modificationBackupColumn = 'created';
		}
		else
		{
			$dbTable                  = '#__categories';
			$modificationColumn       = 'modified_time';
			$modificationBackupColumn = 'created_time';
		}

		$modData = $this->factory
			->getThe('db')
			->selectAssoc(
				$dbTable,
				[$modificationColumn, $modificationBackupColumn],
				[
					'id' => $itemId
				]
			);

		if (empty($modData))
		{
			return null;
		}

		$lastMod = Wb\arrayGet($modData, $modificationColumn, null);
		$lastMod = empty($lastMod) || '0000-00-00 00:00:00' == $lastMod
			? Wb\arrayGet($modData, $modificationBackupColumn, null)
			: $lastMod;

		return $lastMod;
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
		$inputVars = $pageData->get('input_vars', []);
		$view      = Wb\arrayGet($inputVars, 'view', '');
		if (!in_array($view, ['article', 'category']))
		{
			return $isArchived;
		}

		$itemId = (int)Wb\arrayGet($inputVars, 'id');
		if (empty($itemId))
		{
			return $isArchived;
		}

		if ('article' == $view)
		{
			$dbTable = '#__content';
			$column  = 'state';
		}
		else
		{
			$dbTable = '#__categories';
			$column  = 'published';
		}

		$stateData = $this->factory
			->getThe('db')
			->selectAssoc(
				$dbTable,
				[$column],
				[
					'id' => $itemId
				]
			);

		$state = Wb\arrayGet($stateData, $column, null);

		return !empty($state) && 2 == $state;
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
			empty($this->contentData['content'])
		)
		{
			return $variables;
		}

		$contentVariables = [];
		if (
			'article' === $pageData->get('view')
			&&
			'com_content.article' === $this->contentData['context']
		)
		{
			$contentVariables['article_id'] = $this->contentData['content']->id;
			if (!empty($this->contentData['content']->title))
			{
				$contentVariables['article_title'] = $this->contentData['content']->title;
			}
			if (!empty($this->contentData['content']->metadesc))
			{
				$contentVariables['article_description'] = $this->contentData['content']->metadesc;
			}
			$contentVariables['article_date_modified']  = $this->contentData['content']->modified;
			$contentVariables['article_date_published'] = $this->contentData['content']->publish_up;
			$contentVariables['article_category_id']    = $this->contentData['content']->catid;
			$contentVariables['article_category']       = $this->contentData['content']->category_title;
			$contentVariables['article_author']         = $this->contentData['content']->author;
			$contentVariables['article_rating']         = empty($this->contentData['content']->rating) ? 0 : $this->contentData['content']->rating;
			$contentVariables['article_rating_count']   = empty($this->contentData['content']->rating_count) ? 0 : $this->contentData['content']->rating_count;
			$contentVariables['article_hits']           = $this->contentData['content']->hits;
		}

		return array_merge(
			$variables,
			$contentVariables
		);
	}

	/**
	 * Extract best images from gallery found in a page.
	 *
	 * $images[$href] = [
	 * 'url'       => $href,
	 * 'title'     => $imgTag->getAttribute('title'),
	 * 'alt'       => $imgTag->getAttribute('alt'),
	 * 'el_width'  => $imgTag->getAttribute('width'),
	 * 'el_height' => $imgTag->getAttribute('height'),
	 * 'data'      => $dataAttributes
	 * ];
	 *
	 * @param null|array   $extractedImages
	 * @param string       $buffer
	 * @param \DOMDocument $dom
	 * @param \DocNodeList $imgTags
	 * @param array        $options
	 * @param Data\Page    $pageData
	 * @return null|array
	 * @throws \Exception
	 */
	public function filterExtractImages($extractedImages, $buffer, $dom, $imgTags, $options, $pageData)
	{
		if (!$this->shouldRunFilter($pageData))
		{
			return $extractedImages;
		}

		$view = strtolower($pageData->get('view'));
		if ('article' !== $view)
		{
			return [];
		}

		return $extractedImages;
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
			'com_content.article'
		);
	}
}
