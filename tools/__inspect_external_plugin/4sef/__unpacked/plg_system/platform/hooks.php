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

namespace Weeblr\Forsef\Platform;

use Weeblr\Wblib\Forsef\Base;

use Weeblr\Forsef\Platform\Integrations;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Hooks related to the platform handling, ie code specific to one
 * platform or another.
 *
 * @package Weeblr\Forsef\Platform
 */
class Hooks extends Base\Base
{
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
			'forsef_admin_ui_constants',
			[
				$this,
				'adminUiConstants'
			]
		);

		$hooks->add(
			'forsef_custom_admin_css',
			[
				$this,
				'adminCustomCss'
			]
		);
		$hooks->add(
			'forsef_custom_admin_js',
			[
				$this,
				'adminCustomJs'
			]
		);

		$this->thirdPartyHooks($hooks);
	}

	/**
	 * Hook into any 3rd-party API we can handle.
	 */
	private function thirdPartyHooks($hooks)
	{
		$hooks->add(
			'forsef_request_path_to_parse',
			[
				$this->factory->getA(Integrations\Wbamp::class),
				'ampToCanonicalPath'
			]
		);
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

/* Forsef custom styles */

#forsef_app {
    width: 100%;
    min-height: 100%;
}

@media screen and ( min-height: 769px ) {
   #forsef_app {
    height: 100%;
  }
}

#forsef_app{
    font-size: 1rem;
    line-height: 1.5;
    font-weight: normal;
    font-family: system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Noto Sans",sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol","Noto Color Emoji";
    margin: 0;
    padding: 0;
}

#forsef_app input,#forsef_app button, #forsef_app select, #forsef_app textarea,#forsef_app input[type=text],#forsef_app input[type=datetime],#forsef_app input[type=date],#forsef_app input[type=email],#forsef_app input[type=checkbox],#forsef_app input[type=radio],
#forsef_app select,
#forsef_app textarea {
  font-size: 1rem;
  line-height: 1.5;
  font-family: system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Noto Sans",sans-serif,"Apple Color Emoji","Segoe UI Emoji","Segoe UI Symbol","Noto Color Emoji";
}

#forsef_app a,#forsef_app a:hover {
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

/* Forsef custom styles */

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

#forsef_app  a[target=_blank]:before {
  content: "";
  padding: 0;
  margin: 0;
  
}

@media (max-width: 575.98px) {
	body.com_forsef #wrapper.d-flex {
	    display: flex!important;
	    flex-grow: 1;
	}
	#forsef_app {
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
