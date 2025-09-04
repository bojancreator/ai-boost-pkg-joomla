<?php
/**
 * Project:                 4SEF
 *
 * @author                  Yannick Gaultier - Weeblr llc
 * @copyright               Copyright Weeblr llc - 2022 -2025
 * @package                 4SEF
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 @build_version_full_build@
 *
 * 2025-06-02
 */

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Joomla\Uri\Uri;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * A few specific helpers
 */
class WblWordpress_Helper
{
	private static $activePlugins = null;
	private static $allPlugins = null;

	private static $rootUrl = null;
	private static $rootUri = null;

	private static $currentUrl = null;
	private static $currentPath = null;
	private static $currentUri = null;

	/**
	 * Shortand to get the site name
	 *
	 * @return string
	 */
	public static function getSiteName()
	{
		return get_bloginfo('name');
	}

	/**
	 * Shortand to get the site description (tag line)
	 *
	 * @return string
	 */
	public static function getSiteTagline()
	{
		return get_bloginfo('description');
	}

	/**
	 * Builds the target upload dir for this site. Optionally append a subfolder.
	 * The upload dir has to be per site (for multisite operation), but not time-based
	 * as we would otherwise not have a fixed location, that can be retrieved over time.
	 * Returns an array to make available both the full path and the URL.
	 *
	 * @param   string  $subDir  Optional sub directory slug to create in uploads dir.
	 *
	 * @return array $uploadInfo Array of 'url' and 'path' to access the uploads folder.
	 */
	public static function getUploadsInfo($subDir = 'weeblramp')
	{
		static $uploadInfo = array();
		$subDirId = 'subdir_' . $subDir;

		if (empty($uploadInfo[$subDirId]))
		{
			$uploadInfo[$subDirId]         = array();
			$wpUploads                     = wp_upload_dir();
			$baseWpUploadsDir              = Wb\arrayGet($wpUploads, 'basedir');
			$baseWpUploadsUrl              = Wb\arrayGet($wpUploads, 'baseurl');
			$uploadInfo[$subDirId]['path'] = Wb\slashJoin($baseWpUploadsDir, $subDir);
			if (wp_mkdir_p($uploadInfo[$subDirId]['path']))
			{
				$uploadInfo[$subDirId]['url'] = Wb\slashJoin($baseWpUploadsUrl, $subDir);
			}
			else
			{
				// default to uploads, so that we always have a dir
				$uploadInfo[$subDirId]['path'] = $baseWpUploadsDir;
				$uploadInfo[$subDirId]['url']  = $baseWpUploadsUrl;
			}
		}

		return $uploadInfo[$subDirId];
	}

	/**
	 * Shorthand to get the site URL
	 *
	 * @param   array  $parts
	 *
	 * @return string
	 */
	public static function getSiteUrl(
		array $parts = array(
			'scheme',
			'user',
			'pass',
			'host',
			'port',
			'path',
			'query',
			'fragment'
		)
	)
	{
		if (is_null(self::$rootUrl))
		{
			self::$rootUri = new Uri(home_url('/'));
		}

		return self::$rootUri->toString($parts);
	}

	/**
	 * Build and store the current home url that can be used as
	 * an action URL.
	 */
	public static function getHomeActionUrl()
	{
		$homeActionUrl = self::getSiteUrl();

		/**
		 * Filter the site home page URL for an action.
		 *
		 * This differs from the "default" home URL in that
		 * a filter allows multilingual plugins integrations to
		 * modify it to match the current language home URL
		 *
		 * @api
		 * @package weeblrAMP\filter\route
		 * @var weeblramp_home_action_url
		 * @since   1.0.4
		 *
		 * @param   string  $homeActionUrl  The fully qualified home URL to use in a form action
		 * @param   string  $language       Optional. The desired language as a 2 letters code. If not supplied, current language is used.
		 *
		 * @return string
		 */
		$homeActionUrl = apply_filters(
			'weeblramp_home_action_url',
			$homeActionUrl,
			$language = ''
		);

		return $homeActionUrl;
	}

	/**
	 * Returns the current website host
	 *
	 * @return string
	 */
	public static function getHost()
	{
		if (is_null(self::$rootUrl))
		{
			self::$rootUri = new Uri(home_url('/'));
		}

		return self::$rootUri->getHost();
	}

	/**
	 * Returns the current website scheme
	 *
	 * @return string
	 */
	public static function getScheme()
	{
		if (is_null(self::$rootUrl))
		{
			self::$rootUri = new Uri(home_url('/'));
		}

		return self::$rootUri->getScheme();
	}

	/**
	 * Returns the site base URL, ie full root URL
	 * but without the host
	 *
	 * @return string
	 */
	public static function getBaseUrl($pathOnly = true)
	{
		if (is_null(self::$rootUri))
		{
			self::getSiteUrl();
		}

		$parts = array('path', 'query', 'fragment');
		if (!$pathOnly)
		{
			$parts = array_merge($parts, array('scheme', 'user', 'pass', 'host', 'port'));
		}

		return self::$rootUri->toString($parts);
	}

	/**
	 * Returns the current request
	 * @return string
	 */
	public static function getCurrentRequestUrl($absolute = false)
	{
		self::buildCurrentUri();

		return $absolute ? self::$currentUrl : self::$currentPath;
	}

	/**
	 * Gets the current requested URL under a custom format
	 *
	 * @param   array  $options
	 *
	 * @return mixed
	 */
	public static function getCurrentRequestUrlCustom($options = array('path', 'query', 'fragment'))
	{
		self::buildCurrentUri();

		return self::$currentUri->toString($options);
	}

	/**
	 * Execute Wordpress die function, displaying a formatted message
	 * with a follow up link, which is passed through admin_url
	 *
	 * @param           $title
	 * @param           $details
	 * @param   string  $link
	 */
	public static function adminDie($title, $details, $link = '')
	{
		$msgTemplate =
			'<h1>%s</h1>'  // title
			. __('<p>Looks like we were not able to perform this action. More details below:</p><i>%s</i>', 'weeblramp') // details
			. __('<p>Sorry about the trouble! Go back by clicking <a href="%s">on this link</a>.</p>', 'weeblramp'); // footer
		$msg         = sprintf(
			$msgTemplate,
			$title,
			$details,
			admin_url($link)
		);
		wp_die($msg);
	}

	/**
	 * Builds up the current request URL, from PHP server information
	 */

	/**
	 * Execute Wordpress die function, displaying a formatted message
	 * with a follow up link
	 *
	 * @param           $title
	 * @param           $details
	 * @param   string  $link
	 */
	public static function dieNicely($title, $details, $args)
	{
		$msgTemplate =
			'<h1>%s</h1>'  // title
			. __('<p>Looks like we were not able to perform this action. More details below:</p><i>%s</i>'); // details
		$msg         = sprintf(
			$msgTemplate,
			$title,
			$details
		);
		wp_die($msg, '', $args);
	}

	/**
	 * Format a notice to be displayed in the admin
	 *
	 * @param           $message
	 * @param           $pluginName
	 * @param   string  $onlyOn
	 */
	public static function adminError($message, $pluginName, $onlyOn = '')
	{
		if (!empty($onlyOn) && get_current_screen()->id == $onlyOn)
		{
			return;
		}

		echo '
<div class="error notice">
	<p>' . sprintf(__('%s - <a href="%s">Deactivate</a>'), $message, admin_url('plugins.php?s=' . $pluginName)) . '
</div>
';
	}

	/**
	 * Returns a list of active plugins
	 *
	 * @return array
	 */
	public static function getActivePlugins()
	{
		if (is_null(self::$activePlugins))
		{
			self::$activePlugins = (array) get_option('active_plugins', array());
			foreach (self::$activePlugins as $key => $plugin)
			{
				if (WEEBLRAMP_PLUGIN == $plugin)
				{
					unset(self::$activePlugins[$key]);
					break;
				}
			}
		}

		return self::$activePlugins;
	}

	/**
	 * Returns a list of installed plugins
	 *
	 * @return array
	 */
	public static function getAllPlugins()
	{
		if (is_null(self::$allPlugins))
		{
			if (!function_exists('get_plugins'))
			{
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			self::$allPlugins = get_plugins();
			foreach (self::$allPlugins as $key => $plugin)
			{
				if (WEEBLRAMP_PLUGIN == $key)
				{
					unset(self::$allPlugins[$key]);
					break;
				}
			}
		}

		return self::$allPlugins;
	}

	/**
	 * Builds a Datetimezone object using the configured site
	 * timezone. Returns either this object of just the zone name
	 *
	 * @return string | DateTimeZone
	 */
	public static function getTimezone($asString = false)
	{
		static $zone = null;

		if (is_null($zone))
		{
			$zone = WblSystem_Date::getTimeZone(get_option('timezone_string'));
		}

		return $asString ? $zone->getName() : $zone;
	}

	/**
	 * Shorthand for the WP WP_DEBUG constant
	 *
	 * @return bool
	 */
	public static function isDebug()
	{
		return defined('WP_DEBUG') && WP_DEBUG;
	}

	/**
	 * Extract a post type from a query. Does not use get_post_type(), as it
	 * will resort to the global $post value if input is empty, which we do not want.
	 *
	 * @param   WP_Query  $wpQuery
	 *
	 * @return bool|string
	 */
	public static function getPostTypeFromQuery($wpQuery)
	{
		if (empty($wpQuery) || !$wpQuery instanceof WP_Query)
		{
			return false;
		}

		$post = empty($wpQuery->post) ? null : $wpQuery->post;
		if (empty($post) || !$post instanceof WP_Post)
		{
			return false;
		}

		return $post->post_type;
	}

	/**
	 * Builds the internally stored representation of the current requested URL
	 */
	private static function buildCurrentUri()
	{
		if (is_null(self::$currentUrl))
		{
			self::$currentUri  = new Uri(self::getRequestedURL());
			self::$currentUrl  = self::$currentUri->toString();
			self::$currentPath = self::$currentUri->toString(array('path', 'query', 'fragment'));
		}
	}

	/**
	 * @package     Joomla.Platform
	 * @subpackage  Uri
	 *
	 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
	 * @license     GNU General Public License version 2 or later; see LICENSE
	 */
	private static function getRequestedURL()
	{
		// First we need to detect the URI scheme.
		if (isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) != 'off'))
		{
			$scheme = 'https://';
		}
		else
		{
			$scheme = 'http://';
		}

		/*
		 * There are some differences in the way that Apache and IIS populate server environment variables.  To
		 * properly detect the requested URI we need to adjust our algorithm based on whether or not we are getting
		 * information from Apache or IIS.
		 */
		// Define variable to return
		$uri = '';

		// If PHP_SELF and REQUEST_URI are both populated then we will assume "Apache Mode".
		if (!empty($_SERVER['PHP_SELF']) && !empty($_SERVER['REQUEST_URI']))
		{
			// The URI is built from the HTTP_HOST and REQUEST_URI environment variables in an Apache environment.
			$uri = $scheme . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		} // If not in "Apache Mode" we will assume that we are in an IIS environment and proceed.
		elseif (isset($_SERVER['HTTP_HOST']))
		{
			// IIS uses the SCRIPT_NAME variable instead of a REQUEST_URI variable... thanks, MS
			$uri = $scheme . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];

			// If the QUERY_STRING variable exists append it to the URI string.
			if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']))
			{
				$uri .= '?' . $_SERVER['QUERY_STRING'];
			}
		}

		$uri = trim($uri);

		// Extra cleanup to remove invalid chars in the URL to prevent injections through the Host header
		$uri = str_replace(array("'", '"', '<', '>'), array("%27", "%22", "%3C", "%3E"), $uri);

		// remove any cache busting query var
		$uri = WblSystem_Route::removeCacheBust($uri);

		return $uri;
	}
}