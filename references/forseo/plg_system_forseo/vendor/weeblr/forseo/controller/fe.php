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

use Weeblr\Forseo\Data;
use Weeblr\Forseo\Helper;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Mvc;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Api;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Render a story page
 */
class Fe extends Mvc\ControllerHtml
{
	/**
	 * @var Instance of the user making the request.
	 */
	private $user;

	/**
	 * @var Language code for the user making the request.
	 */
	private $userLanguage;

	/**
	 * @var Direction of the language for the user making the request.
	 */
	private $userLanguageDirection;

	/**
	 * @var Html\Assetsmanager
	 */
	protected $assetsManager;

	/**
	 * @var bool
	 */
	protected $isDev;

	/**
	 * Constructor
	 *
	 * @param array $options An array of options.
	 */
	public function __construct($options = array())
	{
		parent::__construct($options);

		$this->user                  = $this->platform->getUser();
		$this->userLanguage          = $this->user->getParam(
			'language',
			$this->platform->getDefaultLanguageTag()
		);
		$this->userLanguageDirection = $this->platform
			->getLanguageDirection(
				$this->userLanguage
			);
		$this->isDev                 = 'dev' === WBLIB_Forseo_OP_MODE;
		$this->assetsManager         = $this->factory->getThe('forseo.assetsManager');
	}

	/**
	 * Possibly inject some javascript inside HTML pages to let user load
	 * the front-end editing script.
	 *
	 * @param string $body
	 *
	 * @return string
	 */
	public function injectLoader(string $body)
	{
		try
		{
			if (
				!$this->platform->isHtmlPage()
				||
				$this->platform->isLikelyEditPage()
			) {
				return $body;
			}

			$pageStatus = $this->factory->getThe('forseo.requestInfo')->get('page_status');
			if (System\Http::isError($pageStatus))
			{
				return $body;
			}

			$editConfig = $this->factory->getThis('forseo.config', 'edit');
			if ($editConfig->isFalsy('frontendEnabled'))
			{
				return $body;
			}

			if ($this->factory->getThe('forseo.crawlerHelper')->isCrawlerRequest())
			{
				// do not inject front end edit code on crawler requests: won't be executed anyway as crawler
				// does not inject javascript. Also it complicates things when debugging crawler
				// requests in a browser.
				return $body;
			}

			// auth
			if (!$this->platform->authorize('forseo.edit.frontend', 'com_forseo'))
			{
				return $body;
			}

			$page = $this->factory->getThe('forseo.pageDataCollector')->get();

			// do render
			$renderedLoader = Mvc\LayoutHelper::render(
				'forseo.fe.loader',
				[
					'showAfter' => 300
				],
				FORSEO_LAYOUTS_PATH,
				'default'
			);

			$snippet = Wb\join(
				"\n",
				'<script class="4SEO_fe_config" ' . $this->platform->getDocumentNonce(true /* $sAttribute */) . '>',
				'var forSeoConfig = ' . System\Strings::jsonPrettyPrint(
					$this->getConfig(
						'fe-loader',
						$page
					)
				) . ';',
				'var forSeoLanguageStrings = ' . System\Strings::jsonPrettyPrint(
					$this->getLanguageStrings(
						'fe-loader'
					)
				) . ';',
				'</script>',
				$renderedLoader
			);

			return System\Strings::tagInBuffer(
				$body,
				'</body>',
				$snippet,
				[
					'where'    => 'before',
					'lastOnly' => true
				]
			);
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return $body;
		}
	}

	/**
	 * Render the front end editor page, meant to be used inside an iframe.
	 *
	 * @param array $options
	 * @return array | \Exception
	 */
	public function edit($options)
	{
		$id = (int)Wb\arrayGet($options, 'id');

		if (empty($id))
		{
			return new \Exception('No URL id provided to be edited.', System\Http::RETURN_BAD_REQUEST);
		}

		try
		{
			$page = $this->factory
				->getA(Data\Page::class)
				->load($id);

			if (!$page->exists())
			{
				return new \Exception('Invalid page id provided for page to be edited.', System\Http::RETURN_NOT_FOUND);
			}

			return [
				'data' => Mvc\LayoutHelper::render(
					'forseo.fe.edit',
					[
						'config'          => $this->getConfig(
							'fe-edit',
							$page
						),
						'languageStrings' => $this->getLanguageStrings('fe-edit'),
						'page'            => $page->get()
					],
					FORSEO_LAYOUTS_PATH,
					'default'
				)
			];
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s', $e->getFile(), $e->getLine(), $e->getMessage());

			return new \Exception('Full error message has been stored to the 4SEO log file on the server', 500);
		}
	}

	/**
	 * Build a configuration object describing the current page as well as API endpoints,
	 * CMS config and more, needed for client to operate.
	 *
	 * @param string    $app
	 * @param Data\Page $page
	 * @return array
	 * @throws \Exception
	 */
	private function getConfig($app, $page)
	{
		$bundleFile  = '';
		$baseCssFile = '';
		switch ($app)
		{
			case 'fe-edit':
				$baseCssFile = $this->assetsManager->getHashedMediaLink($app . '.base.css');
				$bundleFile  = $this->isDev
					? FORSEO_APP_DEV_JS_BUNDLE_FE_EDIT
					: $this->assetsManager->getHashedMediaLink('fe-edit.js');
				break;
			case 'fe-loader':
				$bundleFile = $this->isDev
					? FORSEO_APP_DEV_JS_BUNDLE_FE_LOADER
					: $this->assetsManager->getHashedMediaLink('fe-loader.js');

		}

		// Load current page, check if we know about it and whether data collection
		// is enabled for it.
		$pageStatus = $page->isTruthy('ignore')
			? 'ignore'
			: 'ok';

		$pageId = null;
		if ('ok' == $pageStatus)
		{
			$pageFromDb = $this->factory->getA(Data\Page::class)->loadPerUrl(
				$page->get('full_url')
			);
			if ($pageFromDb->exists())
			{
				$pageId = $pageFromDb->getId();
			}
			else
			{
				$pageStatus = 'pending';
			}
		}

		// System information
		$rewritingPrefix = $this->platform->getUrlRewritingPrefix();
		$rewritingPrefix = empty($rewritingPrefix)
			? $rewritingPrefix
			: StringHelper::ltrim($rewritingPrefix, '/') . '/';

		// prepare the app configuration object
		$forSeoJsConfig = [
			'appName'           => 'fe',
			'platformVersion'   => [
				'major'   => $this->platform->majorVersion(),
				'version' => $this->platform->version()
			],
			'version'           => [
				'current' => '6.10.1.2660',
				'update'  => $this->factory->getA(Helper\Update::class)->latestVersion(),
				'edition' => 'full',
				'api'     => 'v1'
			],
			'tz'                => $this->platform->getTimeZone(),
			'date'              => '2026-01-30',
			'copyright'         => 'Copyright Weeblr llc - 2020 - 2026',
			'language'          => $this->userLanguage,
			'languageDirection' => $this->userLanguageDirection,
			'displayConfig'     => [],
			'urls'              => [
				'root'             => $this->platform->getRootUrl(true),
				'rootFull'         => $this->platform->getRootUrl(false),
				'rewritePrefix'    => $rewritingPrefix,
				'base'             => $this->platform->getBaseUrl(true),
				'baseFull'         => $this->platform->getBaseUrl(false),
				'xmlSitemap'       => $this->factory->getA(Helper\Sitemaps::class)->xmlUrl(),
				'images'           => Wb\slashTrimJoin(
					$this->platform->getRootUrl(false),
					$this->platform->getUserImagesPath(false)
				),
				'api'              => System\Route::normalizePath(
					$this->platform->getBaseUrl(true)
					. $this->factory->getA(Api\Helper::class)->buildBaseUrl($this->factory->getThe('api')->getSlug())
					. '/forseo'),
				'helpRequirements' => 'https://weeblr.com/doc/requirements'
			],
			'user'              => [
				'id' => empty($user->id) ? 'default' : substr(md5($user->id), 0, 12)
			],
			'path'              => [
				'images' => System\Route::normalizePath(
					Wb\slashTrimJoin(
						FORSEO_APP_ASSETS_BASE_URL,
						$this->platform->getUserImagesPath(false)
					)
				),
			],
			'tokens'            => [
				'csrf' => $this->platform->getCSRFToken()
			],
			'bundleFile'        => $bundleFile,
			'cssFile'           => $this->assetsManager->getHashedMediaLink($app . '.css'),
			'isDev'             => $this->isDev,
			'baseCssFile'       => $baseCssFile, // no Tailwind on loader
			'uiConstants'       => [],
			'pageStatus'        => $pageStatus,
			'pageId'            => $pageId,
			'sharingImageSpec'  => $this->factory->getThis('forseo.config', 'socialnetworks')->get('imageSharingSpec'),
			'imageSpec'         => $this->factory->getThis('forseo.config', 'sd')->get('imageSpec'),
			'profileImageSpec'  => $this->factory->getThis('forseo.config', 'sd')->get('profileImageSpec'),
			'siteName'          => $this->platform->getSitename(),
			'nonce'             => $this->platform->getDocumentNonce()
		];

		return $forSeoJsConfig;
	}

	/**
	 * Build language strings, ready to be injected in page HTML.
	 *
	 * @param $currentLanguageTag
	 * @return mixed
	 */
	private function getLanguageStrings($app)
	{
		return $this->factory
			->getA(System\Language::class)
			->getJsLanguageStrings(
				'forseo',
				\FORSEO_APP_PATH,
				'COM_FORSEO',
				[
					'common',
					$app
				],
				$this->userLanguage
			);
	}

}
