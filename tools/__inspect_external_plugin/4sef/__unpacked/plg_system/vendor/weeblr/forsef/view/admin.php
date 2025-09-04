<?php
/**
 * Project: 4SEF
 *
 * @package          4SEF
 * @copyright        Copyright Weeblr llc - 2022 -2025
 * @author           Yannick Gaultier - Weeblr llc
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          2.6.2.644
 * @date        2025-06-02
 */

namespace Weeblr\Forsef\View;

use Weeblr\Forsef\Helper;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Api;
use Weeblr\Wblib\Forsef\Mvc;
use Weeblr\Wblib\Forsef\Html;
use Weeblr\Wblib\Forsef\Joomla\StringHelper\StringHelper;


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
		$this->isDev         = 'dev' === WBLIB_Forsef_OP_MODE;
		$this->assetsManager = $this->factory->getThe('forsef.assetsManager');
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
			 * @api     forsef
			 * @package 4SEF\filter\admin
			 * @var forsef_admin_ui_constants
			 * @since   1.0.0
			 *
			 * @param array $uiConstants List of constants related to the specific platform visual display.
			 *
			 * @return array
			 *
			 */
			$uiConstants = $this->hooks->filter(
				'forsef_admin_ui_constants',
				[]
			);

			$defaultStyles = '';
			/**
			 * Filter custom CSS for admin.
			 *
			 *
			 * @api     forsef
			 * @package 4SEF\filter\admin
			 * @var forsef_custom_admin_css
			 * @since   1.0.0
			 *
			 * @param string $defaultStyles The raw css to be inserted as style tag in the page.
			 * @return string
			 */
			$css = $this->hooks->filter(
				'forsef_custom_admin_css',
				$defaultStyles
			);

			// Get and filter the javascript
			/**
			 * Filter main JS bundle URL.
			 *
			 * @api     forsef
			 * @package 4SEF\filter\bundle
			 * @var forsef_admin_js_bundle_url
			 *
			 * @param string $url The url of the js bundle to be linked from the page.
			 *
			 * @return string
			 * @since   1.0.0
			 *
			 */
			$forsefAppJs = $this->hooks->filter(
				'forsef_admin_js_bundle_url',
				$this->isDev
					? FORSEF_APP_DEV_JS_BUNDLE_ADMIN
					: $this->assetsManager->getHashedMediaLink('admin.js')
			);

			// Load language strings
			$currentLanguageTag = $this->platform->getCurrentLanguageTag();
			$languageStrings    = $this->factory
				->getA(System\Language::class)
				->getJsLanguageStrings(
					'forsef',
					\FORSEF_APP_PATH,
					'COM_FORSEF',
					[
						'common',
						'admin'
					],
					$currentLanguageTag
				);

			// Hardcode in the HTML configuration for the app, including language strings
			$user              = $this->platform->getUser();
			$rewritingPrefix   = $this->platform->getUrlRewritingPrefix();
			$rewritingPrefix   = empty($rewritingPrefix)
				? $rewritingPrefix
				: StringHelper::ltrim($rewritingPrefix, '/') . '/';
			$functionsFilePath = $this->platform->getRootPath() . '/libraries/weeblr/forsef_functions.php';
			$forSefJsConfig    = [
				'platformVersion'             => [
					'major'   => $this->platform->majorVersion(),
					'version' => $this->platform->version()
				],
				'version'                     => [
					'current' => '2.6.2.644',
					'update'  => $this->factory->getA(Helper\Update::class)->latestVersion(),
					'edition' => 'full',
					'api'     => 'v1'
				],
				'tz'                          => $this->platform->getTimeZone(),
				'date'                        => '2025-06-02',
				'copyright'                   => 'Copyright Weeblr llc - 2022 -2025',
				'language'                    => $currentLanguageTag,
				'isDev'                       => $this->isDev,
				'configWizardCompleted'       => $this->factory->getThis('forsef.config', 'system')->isTruthy('configWizardCompleted'),
				'canImportFromSh404sef'       => $this->factory->getThis('forsef.config', 'sh404sef')->get('canImportFromSh404sef', -1),
				'canImportCustomFromSh404sef' => $this->factory->getThis('forsef.config', 'sh404sef')->get('canImportCustomFromSh404sef', -1),
				'processedSh404sefImport'     => $this->factory->getThis('forsef.config', 'sh404sef')->get('processedSh404sefImport', 0),
				'importWizardCompleted'       => $this->factory->getThis('forsef.config', 'sh404sef')->isTruthy('importWizardCompleted'),
				'importWizardRunMode'         => $this->factory->getThis('forsef.config', 'sh404sef')->get('importWizardRunMode'),
				'canAutoClearNotifications'   => $this->factory->getThis('forsef.config', 'system')->isTruthy('canAutoClearNotifications'),
				'displayConfig'               => [],
				'urls'                        => [
					'root'             => $this->platform->getRootUrl(true),
					'rootFull'         => $this->platform->getRootUrl(false),
					'canonicalRoot'    => $this->factory->getThis('forsef.config', 'pages')->get('canonicalRootUrl'),
					'rewritePrefix'    => $rewritingPrefix,
					'base'             => $this->platform->getBaseUrl(true),
					'baseFull'         => $this->platform->getBaseUrl(false),
					'images'           => $this->platform->getRootUrl(false) . 'images',
					'assets'           => Wb\slashTrimJoin(
						\FORSEF_APP_ASSETS_BASE_URL
					),
					'api'              => System\Route::normalizePath(
						$this->platform->getBaseUrl(true)
						. $this->factory->getA(Api\Helper::class)->buildBaseUrl($this->factory->getThe('api')->getSlug())
						. '/forsef'),
					'helpRequirements' => 'https://weeblr.com/doc/products.forsef/current/requirements/'
				],
				'user'                        => [
					'id' => empty($user->id) ? 'default' : substr(md5($user->id), 0, 12)
				],
				'path'                        => [
					'images' => System\Route::normalizePath(FORSEF_APP_ASSETS_BASE_URL . '/images')
				],
				'tokens'                      => [
					'csrf' => $this->platform->getCSRFToken()
				],
				'bundleFile'                  => $forsefAppJs,
				'uiConstants'                 => $uiConstants,
				'functionsFile'               => \file_exists($functionsFilePath)
					? $functionsFilePath
					: '',
				'weeblrExtensions'            => $this->getWeeblrExtensions()
			];

			$jsBundles  = $this->getJsBundles($forsefAppJs);
			$cssBundles = $this->getCssBundles();


			// finally insert into page
			/**
			 * Filter custom JS for admin.
			 *
			 * @api     forsef
			 * @package 4SEF\filter\admin
			 * @var forsef_custom_admin_js
			 * @since   1.0.0
			 *
			 * @param string $js The javascript to be inserted into the page with a script tag.
			 */
			$customJs = $this->hooks->filter(
				'forsef_custom_admin_js',
				''
			);

			$js = "\nvar forSefConfig = " . System\Strings::jsonPrettyPrint($forSefJsConfig) . "\n";
			$js .= "\nvar forSefLanguageStrings = " . System\Strings::jsonPrettyPrint($languageStrings) . "\n";
			$js .= "\n" . $customJs;

			$this->platform
				->addScripts($jsBundles)
				->addStylesheets($cssBundles)
				->addStyleDeclaration($css)
				->addScriptDeclaration($js);

			$this->platform->setAdminTitle('4SEF - SEF URLs for Joomla!');

			return parent::doRender();
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forsef.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
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
			if ('4SEF' === $candidate['name'])
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
		 * @api     forsef
		 * @package 4SEF\filter\bundle
		 * @var forsef_custom_admin_js
		 * @since   1.0.0
		 *
		 * @param array $jsDefs An array of array[url, options, attr], each defining a link to a js file.
		 * @return string
		 */
		$jsBundlesBefore = $this->hooks->filter(
			'forsef_js_before_bundle',
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
		 * @api     forsef
		 * @package 4SEF\filter\bundle
		 * @var forsef_custom_admin_js
		 * @since   1.0.0
		 *
		 * @param array $jsDefs An array of array[url, options, attr], each defining a link to a js file.
		 * @return string
		 */
		$jsBundlesAfter = $this->hooks->filter(
			'forsef_js_after_bundle',
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
		 * @api     forsef
		 * @package 4SEF\filter\bundle
		 * @var forsef_custom_admin_js
		 * @since   1.0.0
		 *
		 * @param array $cssDefs An array of array[url, options, attr], each defining a link to a css file.
		 * @return string
		 */
		$cssBundlesBefore = $this->hooks->filter(
			'forsef_css_before_bundle',
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
		 * @api     forsef
		 * @package 4SEF\filter\bundle
		 * @var forsef_custom_admin_js
		 * @since   1.0.0
		 *
		 * @param array $cssDefs An array of array[url, options, attr], each defining a link to a css file.
		 * @return string
		 */
		$cssBundlesAfter = $this->hooks->filter(
			'forsef_css_after_bundle',
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