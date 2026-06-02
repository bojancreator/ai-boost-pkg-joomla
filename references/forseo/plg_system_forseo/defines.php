<?php
/**
 * Project: 4SEO
 *
 * @author           Yannick Gaultier - Weeblr llc
 * @copyright        Copyright Weeblr llc - 2020 - 2026
 * @package          4SEO
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          6.10.1.2660
 * @date        2026-01-30
 */

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Bootstrap app, called from higher up.
 */

// definitions
defined('WBLIB_Forseo_OP_MODE') or define(
	'WBLIB_Forseo_OP_MODE',
	'prod'
);

defined('WBLIB_Forseo_ROOT_PATH') or define(
	'WBLIB_Forseo_ROOT_PATH',
	JPATH_ROOT . '/plugins/system/forseo/vendor/weeblr/wblib/v2'
);
defined('FORSEO_VENDOR_PATH') or define(
	'FORSEO_VENDOR_PATH',
	JPATH_ROOT . '/plugins/system/forseo/vendor'
);
defined('FORSEO_APP_PATH') or define(
	'FORSEO_APP_PATH',
	FORSEO_VENDOR_PATH . '/weeblr/forseo'
);
defined('FORSEO_LAYOUTS_PATH') or define(
	'FORSEO_LAYOUTS_PATH',
	FORSEO_APP_PATH . '/layouts'
);
defined('FORSEO_APP_PLATFORM_PATH') or define(
	'FORSEO_APP_PLATFORM_PATH',
	JPATH_ROOT . '/plugins/system/forseo'
);
defined('FORSEO_APP_ASSETS_BASE_URL') or define(
	'FORSEO_APP_ASSETS_BASE_URL',
	'/media/com_forseo/vendor/weeblr/forseo/assets'
);
defined('FORSEO_APP_ASSETS_BASE_PATH') or define(
	'FORSEO_APP_ASSETS_BASE_PATH',
	'/media/com_forseo/vendor/weeblr/forseo/assets'
);

