<?php
/**
 * Project: 4SEF
 *
 * @author           Yannick Gaultier - Weeblr llc
 * @copyright        Copyright Weeblr llc - 2022 -2025
 * @package          4SEF
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          2.6.2.644
 * @date        2025-06-02
 */

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Bootstrap app, called from higher up.
 */

// definitions
defined('WBLIB_Forsef_OP_MODE') or define(
	'WBLIB_Forsef_OP_MODE',
	'prod'
);

defined('WBLIB_Forsef_ROOT_PATH') or define(
	'WBLIB_Forsef_ROOT_PATH',
	JPATH_ROOT . '/plugins/system/forsef/vendor/weeblr/wblib/v2'
);
defined('FORSEF_APP_PATH') or define(
	'FORSEF_APP_PATH',
	JPATH_ROOT . '/plugins/system/forsef/vendor/weeblr/forsef'
);
defined('FORSEF_LAYOUTS_PATH') or define(
	'FORSEF_LAYOUTS_PATH',
	FORSEF_APP_PATH . '/layouts'
);
defined('FORSEF_APP_PLATFORM_PATH') or define(
	'FORSEF_APP_PLATFORM_PATH',
	JPATH_ROOT . '/plugins/system/forsef'
);
defined('FORSEF_APP_ASSETS_BASE_URL') or define(
	'FORSEF_APP_ASSETS_BASE_URL',
	'/media/com_forsef/vendor/weeblr/forsef/assets'
);
defined('FORSEF_APP_ASSETS_BASE_PATH') or define(
	'FORSEF_APP_ASSETS_BASE_PATH',
	'/media/com_forsef/vendor/weeblr/forsef/assets'
);
