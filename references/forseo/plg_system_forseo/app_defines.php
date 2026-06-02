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
 * These will enable development features
 */
defined('FORSEO_FEATURES_OVERRIDES') or define(
	'FORSEO_FEATURES_OVERRIDES',
	[
		'integrations.google.search_console' => true,
		'system.wbExecBreadcrumbEnabled'     => true
	]
);

/**
 * Crawler headers and query vars names
 */
defined('FORSEO_CRAWLER_USER_AGENT') or define(
	'FORSEO_CRAWLER_USER_AGENT',
	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/106.0.0.0 Safari/537.36'
);
defined('FORSEO_CRAWLER_CDN_BUST_VAR') or define(
	'FORSEO_CRAWLER_CDN_BUST_VAR',
	'x-wblr-cw-cdn-bpass-id'
);
defined('FORSEO_CRAWLER_SEC_HEADER') or define(
	'FORSEO_CRAWLER_SEC_HEADER',
	'x-wblr-cw-req-sys-id'
);
defined('FORSEO_CRAWLER_REFERRER_HEADER') or define(
	'FORSEO_CRAWLER_REFERRER_HEADER',
	'x-wblr-cw-req-ref'
);
defined('FORSEO_CRAWLER_REDIRECT_COUNT_HEADER') or define(
	'FORSEO_CRAWLER_REDIRECT_COUNT_HEADER',
	'x-wblr-cw-redir-count'
);
defined('FORSEO_CRAWLER_DEBUG_HEADER') or define(
	'FORSEO_CRAWLER_DEBUG_HEADER',
	'x-wblr-cw-dbg'
);
