<?php
/**
 * Project: 4SEO
 *
 * @package                 4SEO
 * @copyright               Copyright Weeblr llc - 2020 - 2026
 * @author                  Yannick Gaultier - Weeblr llc
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 6.10.1.2660
 *
 * 2026-01-30
 */

namespace Weeblr\Forseo\Controller;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Html;

use Weeblr\Forseo\Data;
use Weeblr\Forseo\Model;
use Weeblr\Forseo\Model\Injector;
use Weeblr\Forseo\Helper;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;
use Weeblr\Wblib\Forseo\Joomla\Uri;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Render a story page
 */
class Pageprocessor extends Base\Base
{
	/**
	 * @var Injector\Variables Convenience dynamic variables model instance.
	 */
	protected $variablesModel;

	/**
	 * @var Data\Requestinfo Convenience instance of the current request computed data.
	 */
	private $requestInfo = null;

	/**
	 * @var Data\Page Convenience instance of the current request details.
	 */
	private $pageData = null;

	/**
	 * @var Data\Meta Convenience instance of the current request meta data.
	 */
	private $pageMeta = null;

	/**
	 * @var Helper\Meta Convenience instance of metadata helper.
	 */
	private $metaHelper = null;

	/**
	 * @var Model\Config Convenience instance of pages config.
	 */
	private $pagesConfig = null;

	/**
	 * @var Model\Config Convenience instance of socialNetworks config.
	 */
	private $socialNetworksConfig = null;

	/**
	 * @var Model\Config Convenience instance of structured data config.
	 */
	private $sdConfig = null;

	/**
	 * Initialize convenience instances.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->variablesModel = $this->factory->getThe('forseo.variablesExpander');

		$this->requestInfo          = $this->factory->getThe('forseo.requestInfo');
		$pageDataCollector          = $this->factory->getThe('forseo.pageDataCollector');
		$this->pageData             = $pageDataCollector->get();
		$this->pageMeta             = $pageDataCollector->getMeta();
		$this->pagesConfig          = $this->factory->getThis('forseo.config', 'pages');
		$this->socialNetworksConfig = $this->factory->getThis('forseo.config', 'socialNetworks');
		$this->sdConfig             = $this->factory->getThis('forseo.config', 'sd');
		$this->metaHelper           = $this->factory->getA(Helper\Meta::class);
	}

	/**
	 * Run content replacers set for onContentPrepare events, ie Main content and modules.
	 *
	 * [
	 * 'modified' => false,
	 * 'context'  => $context,
	 * 'content'  => $row,
	 * 'params'   => $params,
	 * 'page'     => $page
	 * ]
	 *
	 * 'modified' must be set to true for any change to be taken into consideration.
	 *
	 * @param array $contentData
	 *
	 * @return array
	 */
	public function onContentPrepare($contentData)
	{
		$contentObject = Wb\arrayGet($contentData, 'content');
		if (empty($contentObject) || empty($contentObject->text))
		{
			return $contentData;
		}

		try
		{
			$originalText = $contentObject->text;

			// Variable expansion. We do not care about context, variables are always expanded if present
			$expansionCount = 0;
			$processed      = $this->expandVariables(
				$contentObject->text,
				$expansionCount
			);

			$contentData['modified'] = !empty($expansionCount);
			$contentObject->text     = $processed;

			// auto-generated description for some content types
			$context = Wb\arrayGet($contentData, 'context');

			// compute auto desc at OnContentPrepare for articles for best results.
			$this->extractMetaDescription(
				$context,
				$contentObject
			);

			// user-specified page image for some content types
			$this->extractPageImageFromArticleData(
				$contentObject->text,
				$context,
				$contentObject
			);

			if (!empty($this->platform->getConfig()->get('caching', 0)))
			{
				// if caching is enabled, we restore the original text,
				// which has the replacement tags and not the replacement
				// values.
				// The tags will be stored in the cache and so will eventually
				// be replaced at onAfterDispatch. That's required for instance for
				// time-based variables. If replacing them at onAfterContent, their
				// value will be stored into the cache and won't update at each page load.
				// Note that this won't help for full page caching (ie the cache system plugin).
				// Note also that we do perform the replacement and then restore the original text
				// so that meta description and page image methods above operate on the replaced
				// content. It won't be good in some cases, but it's a choice.
				// Maybe adding a filter would be best.
				$contentObject->text = $originalText;
			}
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}

		return $contentData;
	}

	/**
	 * Search a piece of content OR an object (for some contexts) for a representative image.
	 *
	 * @param null|string $content
	 * @param null|string $context
	 * @param null        $contentObject
	 *
	 * @return Pageprocessor
	 * @throws \Exception
	 */
	private function extractPageImageFromArticleData($content, $context = '', $contentObject = null)
	{
		// do we already have all images we need?
		$pageImage        = $this->requestInfo->get(
			'page_image'
		);
		$pageSharingImage = $this->requestInfo->get(
			'page_sharing_image'
		);

		// We could already have image(s), usually from
		// parsing custom fields content
		$alreadyHasImage =
			!empty($pageImage)
			||
			!empty($pageSharingImage);

		$appConfig    = $this->factory->getThis('forseo.config', 'app');
		$imageSpec    = $appConfig->get('imageDetectionRequireSizeSd');
		$ogpImageSpec = $appConfig->get('imageDetectionRequireSizeOgp');

		// 1 - custom meta data from DB

		// 2 - Full or Intro image
		/**
		 * Filter automatically detected images from content data object.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\frontend\meta
		 * @var forseo_extract_page_images_from_content_data
		 * @since   1.3.0
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
		$imagesFromDataObject = $this->factory
			->getThe('hook')
			->filter(
				'forseo_extract_page_images_from_content_data',
				[
					'page_image'         => $pageImage,
					'page_sharing_image' => $pageSharingImage
				],
				$context,
				$content,
				$contentObject,
				$this->pageData,
				$this->pageMeta
			);

		$pageImageFromDataObject        = Wb\arrayGet($imagesFromDataObject, 'page_image');
		$pageSharingImageFromDataObject = Wb\arrayGet($imagesFromDataObject, 'page_sharing_image');

		if (
			empty($pageImageFromDataObject)
			&&
			$alreadyHasImage
		)
		{
			// did not find image in article options
			// but we had one already, use it
			return $this;
		}

		// we already had an image (from custom fields likely), but we also found in the
		// data object, which one should we use. Bigger still usually better...
		/**
		 * @var Html\Image
		 */
		$imageHelper = $this->factory->getA(Html\Image::class);
		if (1 === $imageHelper->compareImagesSize($pageImageFromDataObject, $pageImage))
		{
			$pageImage        = $pageImageFromDataObject;
			$pageSharingImage = $pageSharingImageFromDataObject;
		}

		// still here, no luck from data object, search the actual content
		if (empty($pageImage))
		{
			$pageImage        = $this->metaHelper->searchPageImage(
				$content,
				$imageSpec,
				$this->sdConfig->get('imageDetectionMethod', Html\Image::IMAGE_SEARCH_LARGEST)
			);
			$pageSharingImage = $this->metaHelper->searchPageImage(
				$content,
				$ogpImageSpec,
				$this->socialNetworksConfig->get('imageDetectionMethod', Html\Image::IMAGE_SEARCH_LARGEST)
			);
		}

		if (!empty($pageImage))
		{
			$this->requestInfo->set(
				'page_image',
				$pageImage
			);
		}

		if (!empty($pageSharingImage))
		{
			$this->requestInfo->set(
				'page_sharing_image',
				$pageSharingImage
			);
		}

		return $this;
	}

	/**
	 * Update variables content after component has rendered and hopefully all onAfterDispatchHandlers have ran.
	 *
	 * @throws \Exception
	 */
	public function onAfterDispatchComplete()
	{
		if (!$this->canRun())
		{
			return;
		}

		$this->catchUnauthorizedAccess();

		// get current output
		$document       = $this->platform->getDocument();
		$currentContent = $document->getBuffer('component');

		if (empty($currentContent))
		{
			return;
		}

		// re-run replacement in case some content was created after our onContentPrepare handle ran.
		try
		{
			// extract page information now available, after main content has been rendered.
			if ($this->platform->isHtmlPage())
			{
				$this->extractContentAfterDispatch(
					$currentContent
				);
			}

			$expansionCount = 0;
			$processed      = $this->expandVariables(
				$currentContent,
				$expansionCount
			);

			if (!empty($expansionCount))
			{
				$document->setBuffer(
					$processed,
					'component'
				);
			}
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
	}

	/**
	 * Joomla does not throw exceptions when a user is not authorized to access a page.
	 * Instead it only sets a 403 header and:
	 * - either redirects to the login page
	 * - or just render the page normally, with only a message enqueued.
	 *
	 * This means we won't catch that error and error pages rules are not triggered.
	 * We try here to detect that condition and throw an exception.
	 *
	 * @return void
	 * @throws \Exception
	 */
	private function catchUnauthorizedAccess()
	{
		if (System\Http::RETURN_FORBIDDEN !== $this->platform->getHttpStatus())
		{
			return;
		}

		$rules = $this->factory
			->getA(Rules::class)
			->getRulesPerType(
				Data\Rule::TYPE_ERROR_PAGE
			);

		foreach ($rules as $rule)
		{
			if ($this->factory
				->getA(Helper\Errorpage::class)
				->ruleAppliesToError(
					$rule,
					new \Exception('Forbidden', System\Http::RETURN_FORBIDDEN)
				)
			)
			{
				// clear queued message
				$this->platform->clearAppMessageQueue();

				// if still there, throw a 403 exception
				throw new \Exception($this->platform->t('JERROR_ALERTNOAUTHOR'), 403);
			}
		}
	}

	/**
	 * @param null|string $buffer
	 *
	 * @throws \Exception
	 */
	private function extractContentAfterDispatch($buffer)
	{
		// get title and description from regular rendering process
		$this->extractMetadata();

		// If platform caching is enabled, we must rely on platform information we collected
		// during analysis, if we have any
		// Note: if Joomla has already cached the page, then we won't have the proper image until
		// analysis have actually taken place, which may be much later.
		$cachingEnabled = !empty($this->platform->getConfig()->get('caching', 0));
		if ($cachingEnabled)
		{
			// caching is enabled (progressive or conservative)
			// use stored values if available
			$storedMeta = $this->factory->getThe('forseo.pageDataCollector')->getMeta()->getMeta();

			$description = Wb\arrayGet($storedMeta, ['platform', 'description']);
			if (empty($description))
			{
				$description = Wb\arrayGet($storedMeta, ['auto', 'description']);
			}
			if (!empty($description))
			{
				$this->requestInfo->set(
					'page_description',
					$description
				);
			}

			$image = Wb\arrayGet($storedMeta, ['platform', 'image']);
			if (empty($image))
			{
				$image = Wb\arrayGet($storedMeta, ['auto', 'image']);
			}
			if (!empty($image))
			{
				$this->requestInfo->set(
					'page_image',
					$image
				);
			}
			$sharingImage = Wb\arrayGet($storedMeta, ['platform', 'sharing_image']);
			if (empty($sharingImage))
			{
				$sharingImage = Wb\arrayGet($storedMeta, ['auto', 'sharing_image']);
			}
			if (!empty($sharingImage))
			{
				$this->requestInfo->set(
					'page_sharing_image',
					$sharingImage
				);
			}
		}

		if (!$cachingEnabled)
		{
			$appConfig    = $this->factory->getThis('forseo.config', 'app');
			$imageSpec    = $appConfig->get('imageDetectionRequireSizeSd');
			$ogpImageSpec = $appConfig->get('imageDetectionRequireSizeOgp');

			// Description and representative images extraction.
			// This time we look at the entire page so we look for the largest image always.
			// Using first image would likely almost never work, we'd catch logos and accessories images.
			$this->extractMetaDescription('', $buffer)
				 ->extractPageImage($buffer, 'page_image', $imageSpec, Html\Image::IMAGE_SEARCH_LARGEST)
				// representative image for the page, to be used in SD, OGP, TCards
				 ->extractPageImage($buffer, 'page_sharing_image', $ogpImageSpec, Html\Image::IMAGE_SEARCH_LARGEST);
		}
	}

	/**
	 * Extract title,, desc, canonical and robots from HTML document,
	 * storing them into the requestInfo object.
	 *
	 * @throws \Exception
	 */
	public function extractMetadata()
	{
		// get title and description from regular rendering process
		$this->requestInfo->set(
			'page_title',
			$this->platform->getTitle()
		);

		$this->requestInfo->set(
			'page_description',
			$this->platform->getDescription()
		);

		$this->requestInfo->set(
			'page_canonical',
			$this->platform->getCanonical()
		);

		$this->requestInfo->set(
			'page_robots',
			$this->platform->getMetadata('robots')
		);
	}

	/**
	 * Compute a meta description based on a piece of content,
	 * if none already exists in RequestInfo.
	 *
	 * @param null|string         $context A context representing the content type.
	 * @param null|string| Object $content An object or a string representing the content.
	 *
	 * @return $this
	 * @throws \Exception
	 */
	private function extractMetaDescription($context, $content)
	{
		if ($this->pagesConfig->isFalsy('metaAutoDescIfMissing'))
		{
			return $this;
		}

		$currentAutoDescription = $this->requestInfo->get('page_auto_description');
		if (empty($currentAutoDescription))
		{
			/**
			 * Filter an automatically computed meta description for a piece of content.
			 *
			 * @api     forseo
			 * @package 4SEO\filter\frontend\meta
			 * @var forseo_auto_build_description
			 * @since   1.0.0
			 *
			 * @param string        $autoDescription
			 * @param string        $context  An option string representing the context, the content type.
			 * @param string|Object $content  Either a string or an object holding the content data.
			 * @param Data\Page     $pageData Collected request information.
			 * @param Data\Meta     $pageMeta Collected meta data about the request.
			 *
			 * @return bool
			 *
			 */
			$autoDescription = $this->factory
				->getThe('hook')
				->filter(
					'forseo_auto_build_description',
					$currentAutoDescription,
					$context,
					$content,
					$this->pageData,
					$this->pageMeta
				);

			// if plugins have not provided us with a description
			if (empty($autoDescription))
			{
				// try with default code
				$text = $content;
				if (is_object($text) && isset($content->text))
				{
					$text = $content->text;
				}
				else if (is_object($text))
				{
					// we don't know where the actual content is stored
					// in the object, can't use it.
					return $this;
				}

				// fallback to generic description extraction from raw HTML.
				$autoDescription = $this->metaHelper->buildDescriptionFromContent(
					$text
				);
			}

			$this->requestInfo->set(
				'page_auto_description',
				$autoDescription
			);
		}

		return $this;
	}

	/**
	 * Extract representative image from the page, based on an image size specification,
	 * if none already exists in RequestInfo.
	 *
	 * @param null|string $content
	 * @param string      $type
	 * @param array       $imageSpec
	 * @param int         $selectionMode
	 *
	 * @return $this
	 * @throws \Exception
	 */
	private function extractPageImage($content, $type, $imageSpec, $selectionMode)
	{
		// do we already have all images we need?
		$pageImage = $this->requestInfo->get(
			$type
		);

		// if not, run a new search, this time on the entire page.
		if (empty($pageImage))
		{
			$pageImage = $this->metaHelper->searchPageImage(
				$content,
				$imageSpec
			);
			if (!empty($pageImage))
			{
				$this->requestInfo->set(
					$type,
					$pageImage,
					$selectionMode
				);
			}
		}

		return $this;
	}

	/**
	 * Update document meta data based on:
	 *
	 * - user set meta per page
	 * - user set meta per rule (future)
	 * - computed meta if none set
	 *
	 * Meta are:
	 *
	 *   - meta model: page title
	 *   - meta model: page description
	 *
	 * NB: This is separate from other SEO data such as Structured data, OGP, TCards,...
	 * as title and description may be used by other extensions. As such, they need to be made
	 * available as early as possible, in our case at onAfterDispatch.
	 */
	public function injectMetaData()
	{
		if (!$this->platform->isHtmlPage())
		{
			return;
		}

		try
		{
			$this->factory->getA(Model\Injector\Meta::class)
						  ->title()
						  ->description()
						  ->generator();
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
	}

	/**
	 * Inject robots tag in page, using default if none set otherwise.
	 * Default is set in config/app.php.
	 *
	 */
	public function injectRobotsTag($body)
	{
		if (!$this->platform->isHtmlPage())
		{
			return $body;
		}

		$originalBody = $body;

		try
		{
			return $this->factory->getA(Model\Injector\Robots::class)
								 ->robots($body);

		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return $originalBody;
		}
	}

	/**
	 * Inject document Analytics snippets based on analytics rules.
	 * NB: we do NOT use onBeforeCompilehead for now as it does not allow control
	 * on where the script declaration is inserted.
	 *
	 * @param null|string $body The full body of the current response.
	 *
	 * @return string
	 */
	public function injectStructuredData($body)
	{
		/**
		 * Filter whether structured data should be injected for this request.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\frontend\features
		 * @var forseo_should_inject_structured_data
		 * @since   1.0.0
		 *
		 * @param bool      $injectData
		 * @param Data\Page $pageData
		 *
		 * @return bool
		 *
		 */
		if (
			$this->platform->isHtmlPage()
			&&
			$this->canRun(
				'forseo_should_inject_structured_data',
				true
			)
		)
		{
			$renderedSd = $this->factory->getA(Model\Injector\Sd::class)
										->build()
										->render();
			if (!empty($renderedSd))
			{
				$body = System\Strings::tagInBuffer(
					$body,
					'</head>',
					"\t" . $renderedSd,
					[
						'firstOnly' => true,
						'where'     => 'before'
					]
				);
			}
		}

		return $body;
	}

	/**
	 * Update document SEO-related data, before CMS compiles the head section, based on:
	 *
	 * - user set meta per page
	 * - user set meta per rule (future)
	 * - computed meta if none set
	 *
	 * SEO data are:
	 *
	 *   - sd model: structured data
	 *   - ogp model: OGP
	 *   - tcard model: Twitter Cards
	 */
	public function injectSeoData()
	{
		if (!$this->platform->isHtmlPage())
		{
			return;
		}

		try
		{
			/**
			 * Filter whether SEO Data (Structured data, OGP tags, Twitter Cards) should be injected for this request.
			 *
			 * @api     forseo
			 * @package 4SEO\filter\frontend\features
			 * @var forseo_should_inject_seo_data
			 * @since   1.0.0
			 *
			 * @param bool      $injectData
			 * @param Data\Page $pageData
			 *
			 * @return bool
			 *
			 */
			if (!$this->canRun(
				'forseo_should_inject_seo_data',
				true
			))
			{
				return;
			}

			/**
			 * Filter whether OGP tags should be injected for this request.
			 *
			 * @api     forseo
			 * @package 4SEO\filter\frontend\features
			 * @var forseo_should_inject_ogp
			 * @since   1.0.0
			 *
			 * @param bool      $injectData
			 * @param Data\Page $pageData
			 *
			 * @return bool
			 *
			 */
			if ($this->canRun(
				'forseo_should_inject_ogp',
				true
			))
			{
				$this->factory->getA(Model\Injector\Ogp::class)
							  ->build()
							  ->inject();
			}

			/**
			 * Filter whether Twitter Cards tags should be injected for this request.
			 *
			 * @api     forseo
			 * @package 4SEO\filter\frontend\features
			 * @var forseo_should_inject_tcards
			 * @since   1.0.0
			 *
			 * @param bool      $injectData
			 * @param Data\Page $pageData
			 *
			 * @return bool
			 *
			 */
			if ($this->canRun(
				'forseo_should_inject_tcards',
				true
			))
			{
				$this->factory->getA(Model\Injector\Tcards::class)
							  ->build()
							  ->inject();
			}
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
	}

	/**
	 * Search for and insert into page any custom canonical set for this URL.
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function customCanonical()
	{
		$customCanonical = $this->requestInfo
			->get('page_custom_canonical');

		if (!empty($customCanonical))
		{
			$this->metaHelper
				->injectCanonical(
					$customCanonical,
					'4SEO_custom_canonical'
				);

			// custom canonical has priority over rules
			$this->requestInfo
				->set(
					'page_canonical',
					$customCanonical
				);
		}

		return $this;
	}

	/**
	 * Automatically compute and insert a canonical in the current page if we detect
	 * it's a duplicate of another one.
	 *
	 * We identify a cluster by the value of "full_content_id". In a given cluster, the 1st URL in the
	 * Pages table is considered the canonical.
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function autoCanonical()
	{
		if (
			$this->pagesConfig->isFalsy('insertAutoCanonical')
			&&
			$this->pagesConfig->isFalsy('insertAutoSelfCanonical')
		)
		{
			return $this;
		}

		// If - up to now - no canonical was inserted into the page, try to generate one automatically.
		$platformCanonical   = $this->requestInfo->get('page_canonical');
		$insertAutoCanonical = empty($platformCanonical);

		/**
		 * Filter whether to insert an automatically computed canonical link in the current page.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\frontend\pages
		 * @var forseo_pages_insert_auto_canonical
		 * @since   1.0.0
		 *
		 * @param bool      $insertAutoCanonical
		 * @param Data\Page $pageData Collected request information.
		 * @param Data\Meta $pageMeta Collected meta data about the request.
		 *
		 * @return bool
		 *
		 */
		$insertAutoCanonical = $this->factory
			->getThe('hook')
			->filter(
				'forseo_pages_insert_auto_canonical',
				$insertAutoCanonical,
				$this->pageData,
				$this->pageMeta
			);

		if (!$insertAutoCanonical)
		{
			return $this;
		}

		$pageHelper       = $this->factory->getThe('forseo.pageHelper');
		$dynamicCanonical = $pageHelper->getDynamicCanonical(
			$this->pageData
		);

		$currentRequestedUrl = System\Route::makeRootRelative(
			$this->pageData->get('full_url'),
			true // remove leading slash
		);

		$defaultCanonical = $pageHelper->getDefaultCanonical(
			$this->pageData
				->get('full_content_id'),
			$currentRequestedUrl
		);

		if (
			!is_null($defaultCanonical)
			&&
			(
				is_null($dynamicCanonical)
				||
				$dynamicCanonical !== $defaultCanonical
			)
		)
		{
			$canonical = $defaultCanonical;
		}
		else
		{
			$canonical = $dynamicCanonical;
		}

		/**
		 * Filter automatically generated canonical link before it's inserted in the page.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\pages
		 * @var forseo_pages_auto_canonical_link
		 * @since   4.8.0
		 *
		 * @param string    $link
		 * @param Data\Page $pageData
		 *
		 * @return array
		 *
		 */
		$canonical = $this->factory
			->getThe('hook')
			->filter(
				'forseo_pages_auto_canonical_link',
				$canonical,
				$this->pageData
			);

		// should insert self-referencing?
		if (
			!is_null($canonical)
			&&
			$this->pagesConfig->isFalsy('insertAutoSelfCanonical'))
		{
			$canonical =
				!empty($canonical) // allow self-referencing on home page
				&&
				$canonical === $this->pageData->get('full_url')
					? null
					: $canonical;
		}

		// actually insert canonical if we still have one at this point
		if (!is_null($canonical))
		{
			// store for any future re-use within the request
			$this->requestInfo->set(
				'page_canonical',
				$canonical
			);

			$this->requestInfo->set(
				'page_auto_canonical',
				$canonical
			);

			// insert a canonical
			$this->factory->getA(Helper\Meta::class)
						  ->injectCanonical(
							  $canonical,
							  '4SEO_auto_canonical'
						  );
		}

		return $this;
	}

	/**
	 * Redirect a request to the same path and query on the main website
	 * home address if configured to do so by user and conditions are met.
	 */
	public function enforceCanonicalRootUrl()
	{
		if (
			$this->pagesConfig->isTruthy('enforceCanonicalRootUrl')
			&&
			$this->platform->isFrontend()
			&&
			$this->platform->isGuest()
		)
		{
			$this->doEnforceCanonicalRootUrl();
		}
	}

	/**
	 * Redirect a request to the same path and query on the main website
	 * home address.
	 *
	 * Note on getting the request root: Joomla platform is not consistent, although this is also dependent
	 * on the web server configuration.
	 *
	 * Uri::getInstance()->getScheme() . '://' . Uri::getInstance()->getHost() . '/' will always return details about current request eg: https://example.com
	 * BUT
	 * Uri::root(false) on the same request will not give the same result. It will return the value of the virtual host, eg sometimes: https://www.example.com
	 *
	 * This prevents properly detecting www vs non-www and redirecting to the expected main site address.
	 *
	 * This was fixed by using $this->platform->getScheme() . '://' . $this->platform->getHost() . '/' to get the current request root, both of which in turn use
	 * Uri::getInstance() and seems to work reliably.
	 *
	 *
	 */
	public function doEnforceCanonicalRootUrl()
	{
		// - root != canonical root
		$requestRoot   = $this->platform->getScheme() . '://' . $this->platform->getHost();
		$requestRoot   = Wb\slashTrimJoin(
			$requestRoot,
			$this->platform->getBaseUrl(true),
			'/'
		);
		$canonicalRoot = $this->pagesConfig->get('canonicalRootUrl');

		if (empty($requestRoot) || empty($canonicalRoot))
		{
			return;
		}

		if ($requestRoot === $canonicalRoot)
		{
			return;
		}

		// now we redirect
		$currentUrl = $this->requestInfo->get('page_url');
		$targetUrl  = Wb\lTrim($currentUrl, $requestRoot);
		$targetUrl  = Wb\slashTrimJoin(
			$canonicalRoot,
			$targetUrl
		);

		if ($this->platform->canRedirect($currentUrl, $targetUrl))
		{
			// do redirect
			$this->platform->redirectTo(
				$targetUrl,
				System\Http::RETURN_MOVED
			);
		}
	}

	/**
	 * Last chance to do full page variable replacement in non-content areas of the page.
	 * Typically structured data placeholders.
	 *
	 * @param string $body
	 */
	public function expandVariablesOnPage($body)
	{
		$expansionCount = 0;
		$processed      = $this->expandVariables(
			$body,
			$expansionCount
		);

		return !empty($expansionCount)
			? $processed
			: $body;
	}

	/**
	 * Inject document Analytics snippets based on analytics rules.
	 * NB: we do NOT use onBeforeCompilehead for now as it does not allow control
	 * on where the script declaration is inserted.
	 *
	 * @param null|string $body The full body of the current response.
	 *
	 * @return string
	 */
	public function injectAnalytics($body)
	{
		/**
		 * Filter whether using cookies from analytics providers is allowed for this request.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\frontend\privacy
		 * @var forseo_analytics_cookies_allowed
		 * @since   1.0.0
		 *
		 * @param bool             $cookiesAllowed
		 * @param Data\Requestinfo $requestInfo
		 *
		 * @return bool
		 *
		 */
		$cookiesAllowed = $this->canRun(
			'forseo_analytics_cookies_allowed',
			true
		);

		if (
			empty($body)
			||
			!$cookiesAllowed
			||
			!$this->platform->isHtmlPage()
		)
		{
			// do not use $this->canRun(), analytics only injected on HTML documents.
			return $body;
		}

		$originalBody = $body;

		try
		{

			$analyticsRules = $this->factory
				->getA(Model\Rules::class)
				->getRulesSpecs(
					Data\Rule::TYPE_ANALYTICS
				);

			if (empty($analyticsRules))
			{
				return $body;
			}

			return $this->factory
				->getA(Model\Injector\Analytics::class)
				->build($analyticsRules)
				->inject(
					$body
				);
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return $originalBody;
		}
	}


	/**
	 * Clean up all remaining variable tags that we may not have been able
	 * to expand prior.
	 *
	 * @param null|string $body
	 *
	 * @return string
	 */
	public function cleanVariablesTags($body)
	{
		if (!$this->canRun())
		{
			return $body;
		}

		$originalBody = $body;

		try
		{
			return $this->variablesModel->cleanVariablesTags($body);

		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return $originalBody;
		}
	}

	/**
	 * Possibly inject some raw content inside HTML response.
	 *
	 * @param null|string $body
	 *
	 * @param null|int    $expansionCount
	 *
	 * @return string
	 */
	private function expandVariables($body, &$expansionCount = null)
	{
		if (
			empty($body)
			||
			false === stripos($body, '{4seo_')
			||
			!$this->canRun())
		{
			return $body;
		}

		$originalBody = $body;

		try
		{
			list($body, $expansionCount) = $this->variablesModel->expand($body);

			return $body;
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			$expansionCount = null;

			return $originalBody;
		}
	}

	/**
	 * Try to cleanup microdata found in page. Done by removing itemtype attributes on:
	 *
	 * - article
	 * - aside
	 * - section
	 *
	 * @param null|string $body
	 *
	 * @return string
	 */
	public function cleanStructuredData($body)
	{
		if (
			!$this->platform->isHtmlPage()
			||
			$this->sdConfig->isFalsy('enabledCleanup')
		)
		{
			return $body;
		}

		$originalBody = $body;

		try
		{
			return $this->factory
				->getA(Model\Injector\Sd::class)
				->cleanup($body);

		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return $originalBody;
		}
	}

	/**
	 * Loads and assign to requestInfo object any custom data
	 * set by user through the UI: meta title, meta description,
	 * page image, custom OGP, custom structured data, etc
	 */
	private function getUserCustomData()
	{
		static $userData = null;

		if (is_null($userData))
		{
			if (empty($this->pageData))
			{
				return $userData;
			}
			$contentId = $this->pageData->get('content_id');
			if (empty($contentId))
			{
				return $userData;
			}

			$userData = $this->factory
				->getA(Data\Meta::class)
				->loadPerColumn(
					'content_id',
					$contentId
					[], // $whereData
					[
						'id' => 'DESC'
					]   // $orderBy
				);
		}

		return $userData;
	}

	/**
	 * Test document type to only run on html doc or feeds.
	 * Optionally, run a hook to let plugins enable/disable
	 * a specific feature.
	 *
	 * @param string $what
	 * @param bool   $defaultCanRun
	 *
	 * @return bool
	 */
	private function canRun($what = '', $defaultCanRun = true)
	{
		if (empty($what))
		{
			return
				$this->platform->isHtmlPage()
				||
				$this->platform->isFeedPage();
		}

		/**
		 * Filter whether a specific feature, identified by a filter name, is allowed for this request.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\frontend\features
		 * @var forseo_*
		 * @since   1.0.0
		 *
		 * @param bool      $shouldRunFeature
		 * @param Data\Page $pageData
		 *
		 * @return bool
		 *
		 */
		return $this->factory
			->getThe('hook')
			->filter(
				$what,
				$defaultCanRun,
				$this->pageData
			);

	}
}
