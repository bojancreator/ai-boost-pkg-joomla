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

namespace Weeblr\Forseo\Data;

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Db;
use Weeblr\Wblib\Forseo\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Services extends Base\Base
{
	/**
	 * Prefix used when storing credentials to keystore
	 */
	public const STORE_PREFIX_OAUTH             = 'services.tokens.oauth.';
	public const STORE_PREFIX_SITE_VERIFICATION = 'services.tokens.site_verification.';

	public const ALLOWED_SERVICES = [
		'google.search_console',
		'google.analytics_v4',
		'google.analytics_u',
		'indexnow.bing'
	];

	public const SCOPES_PER_SERVICE = [
		'google' => [
			'analytics_v4'         => 'https://www.googleapis.com/auth/analytics',
			'analytics_v4_ro'      => 'https://www.googleapis.com/auth/analytics.readonly',
			'analytics_u'          => 'https://www.googleapis.com/auth/analytics',
			'analytics_u_ro'       => 'https://www.googleapis.com/auth/analytics.readonly',
			'search_console'       => 'https://www.googleapis.com/auth/webmasters',
			'search_console_ro'    => 'https://www.googleapis.com/auth/webmasters.readonly',
			'site_verification'    => 'https://www.googleapis.com/auth/siteverification',
			'site_verification_ro' => 'https://www.googleapis.com/auth/siteverification.verify_only',
		]
	];
}
