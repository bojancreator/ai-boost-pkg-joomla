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

namespace Weeblr\Forseo\Platform;

use Weeblr\Wblib\Forseo\Base;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Hooks related to the platform handling, ie code specific to one
 * platform or another.
 *
 * @package Weeblr\Forseo\Platform
 */
class Hooks extends Base\Base
{
	/**
	 * @var string[] List of extension-specific support plugins. Each provide filters for various data events.
	 */
	protected $componentsPlugins = [
		'common',
		'contact',
		'content',
		'finder',
		'newsfeeds',
		'search',
		'tags',
		'users',

		// 3rd party
		'hikashop',
		'igallery',
		'j2store',
		'k2',
		'phocagallery',
		'sh404sef',
		'sppagebuilder',
		'virtuemart'
	];

	/**
	 * @var \int[][] Platform-specific UI-related constants.
	 */
	protected $uiConstants = [
		'5' => [
			'headerHeight' => 66
		],
		'4' => [
			'headerHeight' => 66
		],
		'3' => [
			'headerHeight' => 0
		]
	];

	/**
	 * Register hooks handler for this platform.
	 */
	public function add()
	{
		$hooks = $this->factory
			->getThe('hook');

		$hooks->add(
			'forseo_admin_ui_constants',
			[
				$this,
				'adminUiConstants'
			]
		);

		$hooks->add(
			'forseo_custom_admin_css',
			[
				$this,
				'adminCustomCss'
			]
		);
		$hooks->add(
			'forseo_custom_admin_js',
			[
				$this,
				'adminCustomJs'
			]
		);

		/**
		 * Filter the list of built-in components support plugins. This allows 3rd-party to disable
		 * native support and replace by their own.
		 * Remove an item from the list to disable native support. Better provide your own then.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\frontend\page
		 * @var forseo_components_support_plugins
		 *
		 * @param array $componentsPlugins List of natively supported component.
		 *
		 * @return bool
		 * @since   1.0.0
		 *
		 */
		$this->componentsPlugins = $this->factory->getThe('hook')->filter(
			'forseo_components_support_plugins',
			$this->componentsPlugins
		);

		// register all built-in per components support plugins left after filtering.
		foreach ($this->componentsPlugins as $plugin)
		{
			$this->factory
				->getThe('\Weeblr\Forseo\Platform\Components\\' . ucfirst($plugin))
				->addHooks();
		}

		$this->thirdPartyHooks();
	}

	/**
	 * Hook into any 3rd-party API we can handle.
	 */
	private function thirdPartyHooks()
	{
		// sh404SEF 404 error handling
		if (is_callable(['ShlHook', 'add']))
		{
			\ShlHook::add(
				'sh404sef_before_404_handling',
				function ($error)
				{
					return $this->factory->getThe('forseo.pageDataCollector')->onError($error);
				}
			);
		}
	}

	/**
	 * Hook into the list of UI constants to be passed to the admin page.
	 *
	 * @param array $constants
	 *
	 * @return array
	 */
	public function adminUiConstants($constants)
	{
		return array_merge(
			$constants,
			$this->uiConstants[$this->platform->majorVersion()] ?? []
		);
	}

	/**
	 * Provides the custom CSS needed for the admin page.
	 *
	 * @param string $css
	 *
	 * @return string
	 */
	public function adminCustomCss($css)
	{
		$sharedCss = <<<STYLES

/** Partial CSS baseline */

/*
html {
  box-sizing: border-box;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
*, *::before, *::after {
  box-sizing: inherit;
}
strong, b {
  font-weight: bolder;
}
@media print {
  body {
    background-color: #fff;
  }
}
*/

/* Forseo custom styles */

#forseo_app {
    width: 100%;
    min-height: 100%;
}

@media screen and ( min-height: 769px ) {
   #forseo_app {
    height: 100%;
  }
}

#forseo_app{
    font-size: 1rem;
    line-height: 1.5;
    font-weight: normal;
    font-family: system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Noto Sans",sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol","Noto Color Emoji";
    margin: 0;
    padding: 0;
}

#forseo_app input,#forseo_app button, #forseo_app select, #forseo_app textarea,#forseo_app input[type=text],#forseo_app input[type=datetime],#forseo_app input[type=date],#forseo_app input[type=email],#forseo_app input[type=checkbox],#forseo_app input[type=radio],
#forseo_app select,
#forseo_app textarea {
  font-size: 1rem;
  line-height: 1.5;
  font-family: system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Noto Sans",sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol","Noto Color Emoji";
}

#forseo_app a,#forseo_app a:hover {
  color: inherit;
  text-decoration: inherit;
}
STYLES;

		$platformVersion = $this->platform->majorVersion();

		if (3 == $platformVersion)
		{
			$perVersionCss = <<<STYLES

/** Joomla 3 admin fixes */

header.header,
.system-message.container,
.subhead-collapse {
  display: none;
}

.container-main {
  margin: 0;
  padding: 0;
  height: 100%;
}

body .navbar, body .navbar-fixed-top {
  z-index: 1500;
}

/* Forseo custom styles */

section#content {
  height: 100vh;
  margin: -31px 0 -30px 0;
  padding: 31px 0 30px 0;
}
section#content .row-fluid,
section#content .row-fluid .span12{
  height: 100%;
  margin:0;
  padding:0;
  min-height:0;
}

STYLES;
		}
		else
		{
			$headerHeight  = 66;
			$perVersionCss = <<<STYLES
/** Joomla 4 admin fixes */
#subhead, #subhead-container {
	display: none;
}
.container-fluid.container-main {
	padding: 0;
	height: calc(100vh - {$headerHeight}px);
	min-width: 0;
}
  
#content {
	position: relative;
	height: 100%;
	padding:0;
	margin:0;
	border-left: 1px solid var(--wb-gray-900);
	border-top: 1px solid var(--wb-gray-900);
}

#content > .row, #content > .row > div, #content > .row > div.col-md-12 > main {
	height: 100%;
	padding:0;
	margin:0;
}

#content > .row > div > main {
	padding-bottom: 1rem;
}

.navbar-toggler.toggler-toolbar {
	display: none;
}

#forseo_app  a[target=_blank]:before {
  content: "";
  padding: 0;
  margin: 0;
  
}

@media (max-width: 575.98px) {
	body.com_forseo #wrapper.d-flex {
	    display: flex!important;
	    flex-grow: 1;
	}
	#forseo_app {
		padding-bottom: 20px;
	}	
}

STYLES;
		}

		return implode("\n", [$css, $sharedCss, $perVersionCss]);
	}

	/**
	 * Provides the custom javascript needed for the admin page.
	 *
	 * @param array $js
	 *
	 * @return array
	 */
	public function adminCustomJs($js)
	{
		$jsCron = '';

		return implode("\n", [$js, $jsCron]);
	}
}
