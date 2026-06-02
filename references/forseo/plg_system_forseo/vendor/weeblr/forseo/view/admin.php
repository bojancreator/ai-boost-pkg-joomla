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

namespace Weeblr\Forseo\View;

use Weeblr\Forseo\Data;
use Weeblr\Forseo\Helper;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Api;
use Weeblr\Wblib\Forseo\Mvc;
use Weeblr\Wblib\Forseo\Html;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;


/** ensure this file is being included by a parent file */
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Main admin view. Loads up custom CSS/Js per platform then the app itself.
 */
class Admin extends Mvc\ViewHtml
{
	/**
	 * @var System\Hook
	 */
	protected $hooks;

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

		$this->hooks         = $this->factory->getThe('hook');
		$this->isDev         = 'dev' === WBLIB_Forseo_OP_MODE;
		$this->assetsManager = $this->factory->getThe('forseo.assetsManager');
	}

	/**
	 * Renders the view content, returning it in a string and
	 * optionally echoing it
	 */
	protected function doRender()
	{
		try
		{
			/**
			 * Filter custom CSS for admin.
			 *
			 * @api     forseo
			 * @package 4SEO\filter\admin
			 * @var forseo_admin_ui_constants
			 * @since   1.0.0
			 *
			 * @param array $uiConstants List of constants related to the specific platform visual display.
			 *
			 * @return array
			 *
			 */
			$uiConstants = $this->hooks->filter(
				'forseo_admin_ui_constants',
				[]
			);

			$defaultStyles = '';
			/**
			 * Filter custom CSS for admin.
			 *
			 * @api     forseo
			 * @package 4SEO\filter\admin
			 * @var forseo_custom_admin_css
			 * @since   1.0.0
			 *
			 * @param string $defaultStyles The raw css to be inserted as style tag in the page.
			 *
			 * @return string
			 *
			 *
			 */
			$css = $this->hooks->filter(
				'forseo_custom_admin_css',
				$defaultStyles
			);

			// Get and filter the javascript
			/**
			 * Filter main JS bundle URL.
			 *
			 * @api     forseo
			 * @package 4SEO\filter\bundle
			 * @var forseo_admin_js_bundle_url
			 * @since   1.0.0
			 *
			 * @param string $url The url of the js bundle to be linked from the page.
			 *
			 * @return string
			 *
			 *
			 */
			$forseoAppJs = $this->hooks->filter(
				'forseo_admin_js_bundle_url',
				$this->isDev
					? FORSEO_APP_DEV_JS_BUNDLE_ADMIN
					: $this->assetsManager->getHashedMediaLink('admin.js')
			);

			/**
			 * Filter features overrides
			 *
			 * @api     forseo
			 * @package 4SEO\filter\features
			 * @var forseo_features_overrides
			 * @since   4.1.2
			 *
			 * @param array $featuresOverrides
			 *
			 * @return array
			 *
			 *
			 */
			$featuresOverrides = $this->hooks->filter(
				'forseo_features_overrides',
				FORSEO_FEATURES_OVERRIDES
			);

			// Load language strings
			$currentLanguageTag = $this->platform->getCurrentLanguageTag();
			$languageStrings    = $this->factory
				->getA(System\Language::class)
				->getJsLanguageStrings(
					'forseo',
					\FORSEO_APP_PATH,
					'COM_FORSEO',
					[
						'common',
						'admin'
					],
					$currentLanguageTag
				);

			// Fetch known search engines updates
			$searchEnginesUpdates = $this->factory->getThe('forseo.searchEnginesHelper')
												  ->getEnginesUpdates();

			// Hardcode in the HTML configuration for the app, including language strings
			$user            = $this->platform->getUser();
			$rewritingPrefix = $this->platform->getUrlRewritingPrefix();
			$rewritingPrefix = empty($rewritingPrefix)
				? $rewritingPrefix
				: StringHelper::ltrim($rewritingPrefix, '/') . '/';

			$systemConfig      = $this->factory->getThis('forseo.config', 'system');
			$sh404sefConfig    = $this->factory->getThis('forseo.config', 'sh404sef');
			$pagesConfig       = $this->factory->getThis('forseo.config', 'pages');
			$sdConfig          = $this->factory->getThis('forseo.config', 'sd');
			$insightsConfig    = $this->factory->getThis('forseo.config', 'insights');
			$functionsFilePath = $this->platform->getRootPath() . '/libraries/weeblr/forseo_functions.php';
			$forSeoJsConfig    = [
				'appName'                      => 'admin',
				'platformVersion'              => [
					'major'   => $this->platform->majorVersion(),
					'version' => $this->platform->version()
				],
				'version'                      => [
					'current' => '6.10.1.2660',
					'update'  => $this->factory->getA(Helper\Update::class)->latestVersion(),
					'edition' => 'full',
					'api'     => 'v1'
				],
				'tz'                           => $this->platform->getTimeZone(),
				'date'                         => '2026-01-30',
				'copyright'                    => 'Copyright Weeblr llc - 2020 - 2026',
				'language'                     => $currentLanguageTag,
				'languages'                    => $this->platform->getFrontendLanguages(),
				'isDev'                        => $this->isDev,
				'isMultilingual'               => $this->platform->isMultilingual(),
				'configWizardCompleted'        => $systemConfig->isTruthy('configWizardCompleted'),
				'canImportMetaFromSh404sef'    => $sh404sefConfig->get('canImportMetaFromSh404sef', -1),
				'canImportAliasesFromSh404sef' => $sh404sefConfig->get('canImportAliasesFromSh404sef', -1),
				'importWizardCompleted'        => $sh404sefConfig->isTruthy('importWizardCompleted'),
				'importWizardRunMode'          => $sh404sefConfig->get('importWizardRunMode'),
				'canAutoClearNotifications'    => $systemConfig->isTruthy('canAutoClearNotifications'),
				'displayConfig'                => [],
				'urls'                         => [
					'root'             => $this->platform->getRootUrl(true),
					'rootFull'         => $this->platform->getRootUrl(false),
					'canonicalRoot'    => $pagesConfig->get('canonicalRootUrl'),
					'rewritePrefix'    => $rewritingPrefix,
					'base'             => $this->platform->getBaseUrl(true),
					'baseFull'         => $this->platform->getBaseUrl(false),
					'xmlSitemap'       => $this->factory->getA(Helper\Sitemaps::class)->xmlUrl(),
					'images'           => Wb\slashTrimJoin(
						$this->platform->getRootUrl(false),
						$this->platform->getUserImagesPath(false)
					),
					'assets'           => $this->isDev
						? FORSEO_APP_ASSETS_BASE_URL
						: Wb\slashTrimJoin(
							$this->platform->getRootUrl(false),
							FORSEO_APP_ASSETS_BASE_URL
						),
					'api'              => System\Route::normalizePath(
						$this->platform->getBaseUrl(true)
						. $this->factory->getA(Api\Helper::class)->buildBaseUrl($this->factory->getThe('api')->getSlug())
						. '/forseo'),
					'helpRequirements' => 'https://weeblr.com/doc/products.forseo/current/requirements/',
					'poauth'           => $this->factory->getThis('forseo.config', 'integrations')->get('oAuthProxyEndpoint'),
				],
				'user'                         => [
					'id' => empty($user->id) ? 'default' : substr(md5($user->id), 0, 12)
				],
				'path'                         => [
					'images' => System\Route::normalizePath(
						Wb\slashTrimJoin(
							FORSEO_APP_ASSETS_BASE_URL,
							$this->platform->getUserImagesPath(false)
						)
					),
					'js'     => System\Route::normalizePath(FORSEO_APP_ASSETS_BASE_URL . '/js'),
					'editor' => '/tmce/tinymce.min.4.9.11.js'
				],
				'tokens'                       => [
					'csrf' => $this->platform->getCSRFToken()
				],
				'bundleFile'                   => $forseoAppJs,
				'uiConstants'                  => $uiConstants,
				'sharingImageSpec'             => $this->factory->getThis('forseo.config', 'socialnetworks')->get('imageSharingSpec'),
				'imageSpec'                    => $sdConfig->get('imageSpec'),
				'logoSpec'                     => $sdConfig->get('logoSpec'),
				'eventImageSpec'               => $sdConfig->get('eventImageSpec'),
				'profileImageSpec'             => $sdConfig->get('profileImageSpec'),
				'sdLocalBusinessTypes'         => Data\Sd::LOCAL_BUSINESS_TYPES,
				'countriesAlpha2'              => System\Data::COUNTRIES_ISO3166_1_ALPHA_2,
				'currencies4217'               => System\Data::CURRENCIES_ISO_4217,
				'defaultGoogleMapsApiKey'      => $this->factory->getThis('forseo.config', 'app')->get('apiKeys.googleMaps.1'),
				'perfThresholds'               => $pagesConfig->get('perfThresholds'),
				'gscThresholds'                => $insightsConfig->get('gscThresholds'),
				'featuresOverrides'            => $featuresOverrides,
				'searchEnginesUpdates'         => $searchEnginesUpdates,
				'siteName'                     => $this->platform->getSitename(),
				'functionsFile'                => \file_exists($functionsFilePath)
					? $functionsFilePath
					: '',
				'weeblrExtensions'             => $this->getWeeblrExtensions()
			];

			$jsBundles  = $this->getJsBundles($forseoAppJs);
			$cssBundles = $this->getCssBundles();


			// finally insert into page
			/**
			 * Filter custom JS for admin.
			 *
			 * @api     forseo
			 * @package 4SEO\filter\admin
			 * @var forseo_custom_admin_js
			 * @since   1.0.0
			 *
			 * @param string $js The javascript to be inserted into the page with a script tag.
			 *
			 *
			 */
			$customJs = $this->hooks->filter(
				'forseo_custom_admin_js',
				''
			);

			$js = "\nvar forSeoConfig = " . System\Strings::jsonPrettyPrint($forSeoJsConfig) . ";\n";
			$js .= "\nvar forSeoLanguageStrings = " . System\Strings::jsonPrettyPrint($languageStrings) . ";\n";
			$js .= "\n" . $customJs;

			$this->platform
				->addScripts($jsBundles)
				->addStylesheets($cssBundles)
				->addStyleDeclaration($css)
				->addScriptDeclaration($js);

			$this->platform->setAdminTitle('4SEO - All Joomla! SEO');

			return parent::doRender();
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
	}

	/**
	 * Figure out which Weeblr extensions are installed and running.
	 *
	 * @return array[]
	 */
	private function getWeeblrExtensions()
	{
		$candidates = [
			[
				'class' => 'Forseo',
				'name'  => '4SEO',
				'link'  => 'index.php?option=com_forseo',
				'icon'  => '../media/com_forseo/vendor/weeblr/forseo/assets/images/logo/forseo-symbol.svg'
			],
			[
				'class' => 'Foranalytics',
				'name'  => '4Analytics',
				'link'  => 'index.php?option=com_foranalytics',
				'icon'  => '../media/com_foranalytics/vendor/weeblr/foranalytics/assets/images/logo/foranalytics-symbol.svg'
			],
			[
				'class' => 'Forai',
				'name'  => '4AI',
				'link'  => 'index.php?option=com_forai',
				'icon'  => '../media/com_forai/vendor/weeblr/forai/assets/images/logo/forai-symbol.svg'
			],
			[
				'class' => 'Forcommand',
				'name'  => '4Command',
				'link'  => 'index.php?option=com_forcommand',
				'icon'  => '../media/com_forcommand/vendor/weeblr/forcommand/assets/images/logo/forcommand-symbol.svg'
			],
			[
				'class' => 'Forsef',
				'name'  => '4SEF',
				'link'  => 'index.php?option=com_forsef',
				'icon'  => '../media/com_forsef/vendor/weeblr/forsef/assets/images/logo/forsef-symbol.svg'
			],
		];

		$installedExensions = [];
		foreach ($candidates as $candidate)
		{
			if ('4SEO' === $candidate['name'])
			{
				continue;
			}

			if (is_callable([$candidate['class'], 'getHook']))
			{
				$installedExensions[] = $candidate;
			}
		}

		return $installedExensions;
	}

	/**
	 * Builds and filters definition array of javascript bundles to be included in page.
	 *
	 * @return array
	 */
	private function getJsBundles($appJs)
	{
		/**
		 * Filter list of js files to be inserted before main bundle.
		 *
		 * Each record added to the lsit must be:
		 * [
		 *   'url' => '', mandatory
		 *   'options' => [], | []
		 *   'attr' => [], | []
		 * ]
		 *
		 * @api     forseo
		 * @package 4SEO\filter\bundle
		 * @var forseo_custom_admin_js
		 * @since   1.0.0
		 *
		 * @param array $jsDefs An array of array[url, options, attr], each defining a link to a js file.
		 *
		 * @return string
		 *
		 *
		 */
		$jsBundlesBefore = $this->hooks->filter(
			'forseo_js_before_bundle',
			[]
		);

		/**
		 * Filter list of js files to be inserted after main bundle.
		 *
		 * Each record added to the lsit must be:
		 * [
		 *   'url' => '', mandatory
		 *   'options' => [], | []
		 *   'attr' => [], | []
		 * ]
		 *
		 * @api     forseo
		 * @package 4SEO\filter\bundle
		 * @var forseo_custom_admin_js
		 * @since   1.0.0
		 *
		 * @param array $jsDefs An array of array[url, options, attr], each defining a link to a js file.
		 *
		 * @return string
		 *
		 *
		 */
		$jsBundlesAfter = $this->hooks->filter(
			'forseo_js_after_bundle',
			[]
		);

		return array_merge(
			$jsBundlesBefore,
			[
				[
					'url'     => $appJs,
					'options' => [],
					'attr'    => $this->isDev
						? ['type' => 'module']
						: ['defer' => 'defer']
				]
			],
			$jsBundlesAfter
		);
	}

	/**
	 * Builds and filters definition array of css bundles to be included in page.
	 *
	 * @return array
	 */
	private function getCssBundles()
	{
		/**
		 * Filter list of css files to be inserted before main bundle.
		 *
		 * Each record added to the lsit must be:
		 * [
		 *   'url' => '', mandatory
		 *   'options' => [], | []
		 *   'attr' => [], | []
		 * ]
		 *
		 * @api     forseo
		 * @package 4SEO\filter\bundle
		 * @var forseo_custom_admin_js
		 * @since   1.0.0
		 *
		 * @param array $cssDefs An array of array[url, options, attr], each defining a link to a css file.
		 *
		 * @return string
		 *
		 *
		 */
		$cssBundlesBefore = $this->hooks->filter(
			'forseo_css_before_bundle',
			[]
		);

		/**
		 * Filter list of css files to be inserted after main bundle.
		 *
		 * Each record added to the lsit must be:
		 * [
		 *   'url' => '', mandatory
		 *   'options' => [], | []
		 *   'attr' => [], | []
		 * ]
		 *
		 * @api     forseo
		 * @package 4SEO\filter\bundle
		 * @var forseo_custom_admin_js
		 * @since   1.0.0
		 *
		 * @param array $cssDefs An array of array[url, options, attr], each defining a link to a css file.
		 *
		 * @return string
		 *
		 *
		 */
		$cssBundlesAfter = $this->hooks->filter(
			'forseo_css_after_bundle',
			[]
		);

		return array_merge(
			$cssBundlesBefore,
			[
				[
					'url' => $this->assetsManager->getHashedMediaLink('admin.base.css')
				],
				[
					'url' => $this->assetsManager->getHashedMediaLink('admin.css')
				]
			],
			$cssBundlesAfter
		);
	}

}