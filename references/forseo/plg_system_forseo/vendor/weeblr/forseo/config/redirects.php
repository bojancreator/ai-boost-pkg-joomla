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

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

return [
	'doNotStore' => [
		'runOnAfterRouteFirst'
	],
	'config'     => [
		// global
		'enabled'              => true,
		'aliasesEnabled'       => true,
		// On J4+, run our onAfterRoute handler before any other
		// Needed to bypass the J! (and others) full page caches
		// in case redirects need to be triggered.
		// Forcefully set to true from 6.2.1.
		// Use forseo_run_on_after_route_first filter to change if needed.
		'runOnAfterRouteFirst' => true
	]
];
