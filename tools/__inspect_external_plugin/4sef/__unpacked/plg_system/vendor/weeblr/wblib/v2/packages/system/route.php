<?php
/**
 * Project:                 4SEF
 *
 * @package                 4SEF
 * @copyright               Copyright Weeblr llc - 2022 -2025
 * @author                  Yannick Gaultier - Weeblr llc
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 @build_version_full_build@
 *
 * 2025-06-02
 */

namespace Weeblr\Wblib\Forsef\System;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Joomla\StringHelper\StringHelper;
use Weeblr\Wblib\Forsef\Joomla\Uri\Uri;
use Weeblr\Wblib\Forsef\Factory;

defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * Route helper
 *
 */
class Route
{
	/**
	 * Rought list of common non-http schemes, to be skipped when extracting links.
	 */
	const nonHttpSchemes = ['cid://', 'ftp://', 'ftps://', 'git://', 'gopher://', 'mailto://', 'modem://', 'fax://', 'fb-messenger://', 'file://', 'intent://', 'ldap://', 'mid://', 'news://', 'nntp://', 'skype://', 'sms://', 'snapchat://', 'tel://', 'telnet://', 'sftp://', 'tg://', 'threema://', 'twitter://', 'urn://', 'viber://', 'whatsapp'];

	const SITEMAP_URL_CHARACTER_PRESERVED = [
		// Commonly encoded:
		// " < > # % |
		'%20'   => '__WBL_ENC_SPACE__',
		'%22'   => '__WBL_ENC_QUOTE__',
		'%3C'   => '__WBL_ENC_LT__',
		'%3E'   => '__WBL_ENC_GT__',
		'%23'   => '__WBL_ENC_HASH__',
		'%25'   => '__WBL_ENC_AMP__',
		'%7C'   => '__WBL_ENC_PIPE__',

		// Reserved but sometimes found
		// ! * ' ( ) ; : @ & = + $ , / ? % # [ ]
		'!'     => '__WBL_RES_EXCL__',
		'*'     => '__WBL_RES_STAR__',
		'\''    => '__WBL_RES_SQUOTE__',
		'('     => '__WBL_RES_OPAR__',
		')'     => '__WBL_RES_CPAR__',
		';'     => '__WBL_RES_SCOL__',
		':'     => '__WBL_RES_COL__',
		'@'     => '__WBL_RES_AT__',
		'&amp;' => '__WBL_RES_AMP__',
		'='     => '__WBL_RES_EQU__',
		'+'     => '__WBL_RES_PLUS__',
		'$'     => '__WBL_RES_DOLL__',
		','     => '__WBL_RES_COMM__',
		'/'     => '__WBL_RES_SLASH__',
		'?'     => '__WBL_RES_QUE__',
		'%'     => '__WBL_RES_PERC__',
		'#'     => '__WBL_RES_HASH__',
		'['     => '__WBL_RES_OBR__',
		']'     => '__WBL_RES_CBR__'
	];

	static public $canonicalRoot = null;

	/**
	 * Turn a relative-to-page URL into an absolute one, using the site canonical domain if any
	 *
	 * @param   string  $url
	 * @param   bool    $forceDomain          if URL is already absolute, we won't fully qualify it with a domain (if relative we
	 *                                        still prepend the full domain)
	 * @param   string  $currentUrl           Used if URL is relative.
	 * @param   bool    $skipRewritingprefix  Whether the URL is for an asset: no need for URL rewriting.
	 *
	 * @return string
	 * @throws \Exception
	 */
	public static function absolutify($url, $forceDomain = false, $currentUrl = null, $skipRewritingprefix = false)
	{
		static $scheme = null;

		// is it already absolute?
		if (
			self::isFullyQualified($url)
			&&
			!self::isProtocolRelative($url)
			&&
			!self::isNonHttp($url)
		)
		{
			return $url;
		}

		$platform      = Factory::get()->getThe('platform');
		$canonicalRoot = self::getCanonicalRoot();
		if (is_null($currentUrl))
		{
			$currentUrl = $canonicalRoot;
		}

		if (is_null($scheme))
		{
			$scheme = $platform->getScheme();
		}

		if (self::isProtocolRelative($url))
		{
			// protocol relative URL, remove domain and path
			$rootRelative = Wb\lTrim($canonicalRoot, 'https:');
			$rootRelative = Wb\lTrim($rootRelative, 'http:');
			$originalUrl  = $url;
			$url          = Wb\lTrim($url, $rootRelative);
			if ($url == $originalUrl)
			{
				// trimming the root URL had not effect, this is a relative
				// URL to another domain (?), prepend the current scheme
				return $scheme . ':' . $originalUrl;
			}
			else
			{
				return StringHelper::rtrim($canonicalRoot, '/') . ($skipRewritingprefix ? '' : $platform->getUrlRewritingPrefix()) . '/' . StringHelper::ltrim($url, '/');
			}
		}
		else if (Wb\startsWith($url, '/'))
		{
			// already a root-relative URL, only add domain if asked to
			if ($forceDomain)
			{
				// remove root path
				$rootUri  = new Uri($canonicalRoot);
				$rootPath = $rootUri->getPath();
				$url      = Wb\lTrim($url, $rootPath);
				if (empty($url))
				{
					$url = $canonicalRoot;
				}
				else
				{
					$url = StringHelper::rtrim($canonicalRoot, '/') . ($skipRewritingprefix ? '' : $platform->getUrlRewritingPrefix()) . '/' . StringHelper::ltrim($url, '/');
				}
			}
			else
			{
				$url = ($skipRewritingprefix ? '' : $platform->getUrlRewritingPrefix()) . $url;
			}

			return $url;
		}
		else
		{
			return self::resolveRelativeUrl(
				$url,
				$currentUrl,
				$skipRewritingprefix
			);
		}
	}

	/**
	 * Finds if a URL is fully qualified, ie starts with a scheme
	 * Protocal-relative URLs are considered fully qualified
	 *
	 * @param $url
	 *
	 * @return bool
	 */
	public static function isFullyQualified($url)
	{
		if (empty($url))
		{
			return false;
		}

		return StringHelper::substr($url, 0, 7) == 'http://' || StringHelper::substr($url, 0, 8) == 'https://' || self::isProtocolRelative($url);
	}

	/**
	 * Whether a URL starts with a scheme prefix that's not HTTP(S) - but still is a valid scheme.
	 *
	 * @param   string  $url
	 *
	 * @return bool
	 */
	public static function isNonHttp($url)
	{
		return !empty($url)
			&&
			Wb\startsWith(
				$url,
				self::nonHttpSchemes
			);
	}

	/**
	 * Whether a URL is protocol-relative.
	 *
	 * @param   string  $url
	 *
	 * @return bool
	 */
	public static function isProtocolRelative($url)
	{
		return !empty($url)
			&&
			Wb\startsWith(
				$url,
				'//'
			);
	}

	/**
	 * Make a url fully qualified and protocol relative
	 *
	 * @param   string  $url
	 *
	 * @return mixed|string
	 * @throws \Exception
	 */
	public static function makeProtocolRelative($url)
	{
		$url = self::absolutify($url, true);
		$url = preg_replace('#^https?://#', '//', $url);

		return $url;
	}

	/**
	 * Make a URL relative to the site root.
	 *
	 * @param   string  $url                 An internal URL.
	 * @param   bool    $removeLeadingSlash  Whether to strip the leading slash after making the URL relative to root.
	 * @param   string  $currentUrl
	 * @param   bool    $isAsset             No need for URL rewriting prefix if an asset.
	 *
	 * @return string
	 * @throws \Exception
	 */
	public static function makeRootRelative($url, $removeLeadingSlash = false, $currentUrl = null, $isAsset = false)
	{
		if (!self::isInternal($url))
		{
			return $url;
		}

		$platform         = Factory::get()->getThe('platform');
		$sefRewritePrefix = $platform->getUrlRewritingPrefix();

		$base = $platform->getBaseUrl();
		if (!empty($sefRewritePrefix))
		{
			$url = Wb\lTrim(
				$url,
				$sefRewritePrefix
			);
		}

		if (Wb\startsWith($url, $base))
		{
			$url = Wb\lTrim(
				$url,
				$base
			);
			if (!empty($sefRewritePrefix))
			{
				$url = Wb\lTrim(
					$url,
					$sefRewritePrefix
				);
			}

			return $removeLeadingSlash
				? Wb\lTrim($url, '/')
				: $url;
		}

		$url = self::absolutify($url, true, $currentUrl, $isAsset);
		$url = Wb\lTrim(
			$url,
			self::getCanonicalRoot()
		);

		if (!Wb\startsWith($url, '/'))
		{
			$url = '/' . $url;
		}

		if (!empty($sefRewritePrefix))
		{
			$url = Wb\lTrim(
				$url,
				$sefRewritePrefix
			);
		}

		if (!Wb\startsWith($url, '/') && !$removeLeadingSlash)
		{
			$url = '/' . $url;
		}

		return $removeLeadingSlash
			? Wb\lTrim($url, '/')
			: $url;
	}

	/**
	 * Resolve a URL based on the URL of the page it's relative to.
	 *
	 * @param   string  $url                   The relative URL.
	 * @param   string  $currentUrl            The URL it's relative to.
	 * @param   bool    $isAsset               No need for URL rewriting prefix if an asset.
	 * @param   bool    $stripQueryAndAnchors  Legacy compat, if true, query and anchor is stripped from reference URL.
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public static function resolveRelativeUrl($url, $currentUrl = null, $isAsset = false, $stripQueryAndAnchors = true)
	{
		$platform   = Factory::get()->getThe('platform');
		$currentUrl = is_null($currentUrl)
			? $platform->getCurrentUrl()
			: $currentUrl;

		if (Wb\startsWith(
			$url,
			[
				'https://',
				'http://',
				'//',
				'/'
			]
		))
		{
			// not a relative link
			return $url;
		}
		if (!Wb\startsWith(
			$currentUrl,
			[
				'https://',
				'http://',
				'//',
				'/'
			]
		))
		{
			// current URL is relative, cannot relate to that
			throw new \Exception('wbLib: resolveRelativeUrl: current URL is relative, cannot use as reference: url: ' . $url . ', current URL: ' . $currentUrl);
		}

		// remove anchors
		$baseUrl = $currentUrl;
		if ($stripQueryAndAnchors)
		{
			$baseUrl = preg_replace(
				'~#.*$~',
				'',
				$baseUrl
			);

			// remove query
			$baseUrl = preg_replace(
				'~\?.*$~',
				'',
				$baseUrl
			);
		}

		$referenceUri = new Uri($baseUrl);

		// remove anything trailing that's not a slash
		$referenceUri->setPath(
			preg_replace(
				'~[^/]+$~',
				'',
				$referenceUri->getpath()
			)
		);

		$uri = self::dotSegmentsSafePath(
			$url,
			$referenceUri,
			$isAsset || empty($url)
				? ''
				: $platform->getUrlRewritingPrefix()
		);

		return $uri->toString();
	}

	/**
	 * Removes single and double dot segments from a relative path,
	 * based on the reference URL the path is relative to.
	 *
	 * @param   string  $relativeUrl
	 * @param   Uri     $referenceUri
	 * @param   string  $prefix
	 *
	 * @return string
	 */
	private static function dotSegmentsSafePath($relativeUrl, $referenceUri, $prefix = '')
	{
		if (empty($relativeUrl))
		{
			return $referenceUri;
		}

		$referenceUriPath = $referenceUri->getPath() ?? '';
		$referenceUriPath = empty($referenceUriPath)
			? '/'
			: $referenceUriPath;

		$referenceUrlSegments = array_filter(explode('/', $referenceUriPath));
		$maxDotSegs           = count($referenceUrlSegments);

		$relativeUrl = self::cleanSingleDot($relativeUrl);

		$relativeUrlDotSegBits         = explode('../', $relativeUrl);
		$relativeUrlDotSegBitsCount    = count($relativeUrlDotSegBits);
		$relativeUrlNonDotSegBits      = array_filter($relativeUrlDotSegBits);
		$relativeUrlNonDotSegBitsCount = count($relativeUrlNonDotSegBits);
		$relativeUrlDotSegCount        = $relativeUrlDotSegBitsCount - $relativeUrlNonDotSegBitsCount;
		for ($counter = 0; $counter < $relativeUrlDotSegCount; $counter++)
		{
			$relativeUrl = Wb\lTrim(
				$relativeUrl,
				'../'
			);
		}
		$toTrimDotSegCount = $relativeUrlDotSegCount <= $maxDotSegs
			? $relativeUrlDotSegCount
			: $maxDotSegs;
		for ($counter = 0; $counter < $toTrimDotSegCount; $counter++)
		{
			array_pop($referenceUrlSegments);
		}
		$referenceUrl = '/' . (empty($referenceUrlSegments) ? '' : Wb\slashTrimJoin($referenceUrlSegments));

		$referenceUri->setpath(
			Wb\slashTrimJoin(
				$referenceUrl,
				$prefix,
				$relativeUrl
			)
		);

		return $referenceUri;
	}

	public static function cleanSingleDot($path, $asArray = false)
	{
		$pathBits = wb\arrayEnsure(
			explode('/', $path ?? '')
		);
		$pathBits = array_filter(
			$pathBits,
			function ($bit) {
				return $bit !== '.';
			}
		);

		return $asArray
			? $pathBits
			: implode('/', $pathBits);
	}

	/**
	 * Allows setting a custom root URL, as user can override the
	 * platform value.
	 *
	 * @param   string  $rootUrl
	 */
	public static function setCanonicalRoot($rootUrl)
	{
		self::$canonicalRoot = $rootUrl;
	}

	/**
	 * Builds and return the canonical domain of the page.
	 *
	 * @param   null|bool  $isAdmin
	 *
	 * @return string
	 */
	public static function getCanonicalRoot($isAdmin = null)
	{
		return empty(self::$canonicalRoot)
			? Factory::get()->getThe('platform')->getCanonicalRoot($isAdmin)
			: self::$canonicalRoot;
	}

	/**
	 * Parse a raw URL into its components and provide methods to access them.
	 *
	 * @param   string  $url
	 *
	 * @return Uri
	 */
	public static function parseUrl($url)
	{
		return new Uri($url);
	}

	/**
	 * Finds if an URL is internal, ie on the same site
	 *
	 * @param   string  $url
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public static function isInternal($url)
	{
		if (empty($url))
		{
			return true;
		}

		// absolutify, prepending domain if missing
		$url = self::absolutify($url, true);

		$canonicalRootUrl = self::getCanonicalRoot();
		if (self::isProtocolRelative($url))
		{
			$canonicalRootUrl = Wb\lTrim($canonicalRootUrl, 'https:');
			$canonicalRootUrl = Wb\lTrim($canonicalRootUrl, 'http:');
		}

		// is it local?

		/**
		 * Filter whether a URL is internal to the site.
		 *
		 * @api
		 * @package wblib\filter\route
		 * @var wblib_url_is_internal
		 *
		 * @param   bool    $urlIsInternal     Whether the URL is internal
		 * @param   string  $url               The fully qualified URL we want to find about
		 * @param   string  $canonicalRootUrl  The root URL of the site, as reported by WP
		 *
		 * @return bool
		 * @since   1.0.4
		 *
		 */
		return Factory::get()->getThe('hook')->filter(
			'wblib_url_is_internal',
			Wb\startsWith($url, $canonicalRootUrl),
			$url,
			$canonicalRootUrl
		);
	}

	/**
	 * Append a query variable with a random value to bust caching.
	 *
	 * @param   String  $url
	 * @param   string  $varName
	 * @param   string  $value
	 *
	 * @return string|string[]|null
	 */
	public static function cacheBust($url, $varName = '_wb_bust', $value = null)
	{
		$value = is_null($value)
			? mt_rand()
			: $value;
		if (false !== strpos($varName . '=', $url))
		{
			// already there, just update the value
			$url = preg_replace(
				'/' . $varName . '=[^&?]+/',
				$varName . '=' . $value,
				$url
			);
		}
		else
		{
			$separator = false === strpos($url, '?')
				? '?'
				: '&';
			$url       .= $separator . $varName . '=' . $value;
		}

		return $url;
	}

	/**
	 * Removes any _wb_bust query var that may have been added
	 * to a URL
	 *
	 * @param   String  $url
	 * @param   String  $varName
	 *
	 * @return string
	 */
	public static function removeCacheBust($url, $varName = '_wb_bust')
	{
		return self::removeQueryVarFromUrl($url, $varName);
	}

	/**
	 * Removes any query var from a URL.
	 *
	 * @param   String         $url
	 * @param   array |string  $varNames
	 *
	 * @return string
	 */
	public static function removeQueryVarFromUrl($url, $varNames)
	{
		$url = $url ?? '';
		if (Wb\startsWith($url, '//'))
		{
			// The URI class will not work properly with protocol-relative URLs
			// It ends up removing the protocol.
			$bits = explode('?', $url, 2);

			return $bits[0];
		}

		$varNames = Wb\arrayEnsure($varNames);

		$uri = new Uri($url);

		foreach ($varNames as $varName)
		{
			$uri->delVar($varName);
		}

		return $uri->toString();
	}

	/**
	 * Append a query string (param=1&param=2...) to an existing URL. Should make minimal modification
	 * to the original URL, including handling existing query string, fragment, path, protocol and domain if any.
	 *
	 * @param   string  $rawUrl
	 * @param   string  $append
	 *
	 * @return string
	 */
	public static function appendQuery($rawUrl, $append)
	{
		if (empty($append))
		{
			return $rawUrl;
		}

		// fix for protocol-relative URLs
		$isProtocolRelative = Wb\startsWith($rawUrl, '//');

		$uri   = new Uri($rawUrl);
		$query = $uri->getQuery();
		$uri->setQuery(
			Wb\join('&', $query, $append)
		);

		return ($isProtocolRelative ? '//' : '') . $uri->toString();
	}

	/**
	 * Super-simplified append to query vars. Will only work with strings or numbers.
	 * Variables are NOT sorted.
	 *
	 * @param   string      $queryString
	 * @param   string      $varName
	 * @param   string|int  $varValue
	 *
	 * @return string
	 */
	public static function appendVarToQueryString($queryString, $varName, $varValue)
	{
		$separator = strpos($queryString, '?') !== false
			? '&'
			: '?';

		return $queryString . $separator . $varName . '=' . $varValue;
	}

	/**
	 * Canonicalize a URL path that may contain . and ..
	 *
	 * From https://www.php.net/manual/en/function.realpath.php#71334
	 *
	 * @param   string  $path
	 *
	 * @return string|string[]
	 */
	public static function normalizePath($path)
	{
		$path = str_replace(
			'\\',
			'/',
			$path ?? ''
		);
		$path = explode('/', $path);
		$keys = array_keys($path, '..');

		foreach ($keys as $keypos => $key)
		{
			array_splice($path, $key - ($keypos * 2 + 1), 2);
		}

		$path = implode('/', $path);
		$path = str_replace('./', '', $path);

		return $path;
	}

	/**
	 * Execute a URL match rule agains a request URL, and returns any match.
	 *
	 * Rule specs:
	 * {*} => any URL
	 * xxxx => exactly 'xxxxx'
	 * xxx{?}yyy => 'xxx' + any character + 'yyy'
	 * xxx{*}yyy => 'xxx' + any string + 'yyy'
	 * {*}xxxx => any string + 'xxxxx'
	 * xxxx{*} => 'xxxx' + any string
	 * {*}xxxx{*} => any string + 'xxxxx' + any string
	 * {*}xxxx{*}yyyy => any string + 'xxxxx' + any string + 'yyyy'
	 *
	 * @param   string  $rule
	 * @param   string  $path  the path relative to the root of the site, starting with a /
	 *
	 * @param   string  $wildChard
	 * @param   string  $singleChar
	 * @param   string  $regexpChar
	 *
	 * @return array
	 */
	public static function findUrlRuleMatch($rule, $path, $wildChard = '{*}', $singleChar = '{?}', $regexpChar = '~')
	{
		// shortcuts
		if ($wildChard == $rule)
		{
			// simulate a regexp match
			return array(
				$path,
				$path
			);
		}

		// build a reg exp based on rule
		if (StringHelper::substr($rule, 0, 1) == $regexpChar)
		{
			// this is a regexp, use it directly
			$regExp = $rule;
		}
		else
		{
			// actually build the reg exp
			$saneStarBits = array();
			$starBits     = explode($wildChard, $rule);
			foreach ($starBits as $sBit)
			{
				// same thing with ?
				$questionBits = explode($singleChar, $sBit);
				$saneQBit     = array();
				foreach ($questionBits as $qBit)
				{
					$saneQBit[] = preg_quote($qBit);
				}

				$saneStarBits[] = implode($singleChar, $saneQBit);
			}

			// each part has been preg_quoted
			$sanitized = implode($wildChard, $saneStarBits);
			$regExp    = str_replace($singleChar, '(.)', $sanitized);
			$regExp    = str_replace($wildChard, '(.*)', $regExp);
			$regExp    = '~^' . $regExp . '$~uU';
		}

		if (
			!Strings::isValidUtf8($regExp)
			||
			!Strings::isValidUtf8($path)
		)
		{
			return [];
		}

		// execute and return
		@preg_match($regExp, $path, $matches);

		return $matches ?? [];
	}

	/**
	 * Execute a URL match rule agains a request URL, and returns a boolean if a match occured.
	 *
	 * Rule specs:
	 * {*} => any URL
	 * xxxx => exactly 'xxxxx'
	 * xxx{?}yyy => 'xxx' + any character + 'yyy'
	 * xxx{*}yyy => 'xxx' + any string + 'yyy'
	 * {*}xxxx => any string + 'xxxxx'
	 * xxxx{*} => 'xxxx' + any string
	 * {*}xxxx{*} => any string + 'xxxxx' + any string
	 * {*}xxxx{*}yyyy => any string + 'xxxxx' + any string + 'yyyy'
	 *
	 * @param   string  $rule
	 * @param   string  $path  the path relative to the root of the site, starting with a /
	 *
	 * @param   string  $wildChar
	 * @param   string  $singleChar
	 * @param   string  $regexpChar
	 *
	 * @return bool
	 */
	public static function matchUrlRule($rule, $path, $wildChar = '{*}', $singleChar = '{?}', $regexpChar = '~')
	{
		$matches = self::findUrlRuleMatch($rule, $path, $wildChar, $singleChar, $regexpChar);

		return !empty($matches[0]);
	}

	/**
	 * Finds the host for a given URL, making FQDN if not already.
	 *
	 * @param   string  $url
	 * @param   bool    $strict
	 * @param   bool    $withHost  For B/C
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public static function getHost($url, $strict = false, $withPort = false)
	{
		if (
			$strict
			&&
			empty($url)
		)
		{
			return '';
		}

		$uri = new Uri(
			static::absolutify(
				$url,
				true
			)
		);

		$host = $uri->getHost();
		$port = $uri->getPort();
		if (
			$withPort
			&&
			!empty($port)
			&&
			!in_array($port, [80, 443])
		)
		{
			$port = ':' . $port;
		}
		else
		{
			$port = '';
		}

		return empty($host)
			? ''
			: StringHelper::strtolower($host) . $port;
	}

	/**
	 * Finds the scheme for a given URL, making FQDN if not already.
	 *
	 * @param   string  $url
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public static function getScheme($url)
	{
		$uri = new Uri(
			static::absolutify(
				$url,
				true
			)
		);

		$scheme = $uri->getScheme();

		return empty($scheme)
			? ''
			: $scheme;
	}

	/**
	 * Finds the Origin for a given URL.
	 *
	 * @param   string  $url
	 *
	 * @return string
	 * @throws \Exception
	 */
	public static function getOrigin($url)
	{
		$uri = new Uri(
			static::absolutify(
				$url,
				true
			)
		);

		$port = $uri->getPort();
		if (
			!empty($port)
			&&
			!in_array($port, [80, 443])
		)
		{
			$port = ':' . $port;
		}

		return $uri->getScheme()
			. '://'
			. $uri->getHost()
			. $port;
	}

	/**
	 * Verify if a given URL is on the specified host.
	 *
	 * @param   string  $host
	 * @param   string  $url
	 * @param   bool    $strict  If true, there must be an exact host match. If not, only end of string must match (ie weeblr.com will match support.weeblr.com)
	 *
	 * @return bool
	 */
	public static function hostMatch($host, $url, $strict = false)
	{
		$parsed = parse_url($url, PHP_URL_HOST);

		return $parsed !== false
			&&
			(
				($strict && $parsed == $host)
				||
				(!$strict && Wb\endsWith($parsed, $host))
			);
	}

	/**
	 * Extract the query part of a URL provided as a string.
	 *
	 * @param   string  $url
	 *
	 * @return string
	 * @throws \Exception
	 */
	public static function getQuery($url)
	{
		$bits = explode('?', $url ?? '');

		return Wb\arrayGet($bits, 1, '');

	}

	/**
	 * Drop the query part of a URL provided as a string.
	 *
	 * @param   string  $url
	 *
	 * @return string
	 * @throws \Exception
	 */
	public static function trimQuery($url)
	{
		$bits = explode('?', $url ?? '');

		return Wb\arrayGet($bits, 0, '');

	}

	/**
	 * Split a URL in path and query strings.
	 * Query does not have the ?
	 *
	 * @param   string  $url
	 *
	 * @return array
	 * @throws \Exception
	 */
	public static function splitQuery($url)
	{
		$bits = explode('?', $url ?? '', 2);

		return [
			'path'  => Wb\arrayGet($bits, 0, ''),
			'query' => Wb\arrayGet($bits, 1, '')
		];

	}

	/**
	 * Drop the fragment part of a URL provided as a string.
	 *
	 * @param   string  $url
	 *
	 * @return string
	 * @throws \Exception
	 */
	public static function trimFragment($url)
	{
		$bits = explode('#', $url ?? '', 2);

		return Wb\arrayGet($bits, 0, '');

	}

	/**
	 * Raw URL-encode a URL, preserving host, scheme and query.
	 *
	 * If encodeQuery is true, query string is also encoded, a process in which we should be
	 * able to avoid double-encoding.
	 *
	 *  $urls = [
	 * 'https://de.wikipedia.org/w/index.php?title=Europ%C3%A4ischer_Nerz&oldid=200380329',
	 * 'https://de.wikipedia.org/w/index.php?title=Europäischer_Nerz&oldid=200380329',
	 * 'https://example.com/some-path/?var[0]=0&var[1]=1',
	 * 'https://example.com/some-path/?var[name][0]=0&var[name][1]=1',
	 * 'https://example.com/some-path-ä-with-special-char/?var[0]=0&var[1]=1',
	 * 'https://example.com/some-path/?var[name][0]=Europ%C3%A4ischer_Nerz&var[name][1]=Europäischer_Nerz',
	 * 'https://fr.wikipedia.org/wiki/Faisan_dor%C3%A9',
	 * 'https://fr.wikipedia.org/wiki/Faisan_doré',
	 * 'https://example.com/gewervelden-tags/vogels-tags/scharrelaarvogels-tags/ijsvogels?layout=list&types[0]=6',
	 * ];
	 *
	 * foreach ($urls as $url)
	 * {
	 * echo '<p><pre>' . $url . '</pre><pre>' . System\Route::urlEncodeUrl($url, true) . '</pre></p><p>&nbsp;</p>';
	 * }
	 *
	 * @param   string  $url
	 * @param   bool    $encodeQuery
	 * @param   bool    $encodeBrackets
	 *
	 * @return string
	 */
	public static function urlEncodeUrl($url, $encodeQuery = false, $encodeBrackets = true)
	{
		$uri  = new Uri($url);
		$path = $uri->getPath() ?? '';

		$uri->setpath(
			implode(
				'/',
				array_map(
					function ($segment) {
						return rawurldecode($segment) === $segment
							? rawurlencode($segment)
							: $segment;
					},
					explode(
						'/',
						$path
					)
				)
			)
		);

		if ($encodeQuery)
		{
			$rawQuery = $uri->getQuery(
				true // asArray
			);

			$encodedQuery = [];
			foreach ($rawQuery as $key => $value)
			{
				$encodedQuery[$key] = self::encodeQueryVar(
					[],
					$key,
					$value
				);
			}

			$uri->setQuery($encodedQuery);

		}

		$woQuery = $uri->toString(
			['scheme', 'user', 'pass', 'host', 'port', 'path']
		);

		return $encodeBrackets
			? $woQuery . str_replace(
				['[', ']'],
				['%5B', '%5D'],
				$uri->toString(['query'])
			)
			: $uri->toString();
	}

	/**
	 * Encode a normalized URL (path + query) for use in an XML sitemap.
	 *
	 * - rawurlencode ensure RFC 3986 compliance.
	 * - entities encoding uses ENT_QUOTES | ENT_XML1 to ensure single-quote are encoded as &apos;
	 *
	 * Ref: https://www.sitemaps.org/protocol.html#escaping
	 *
	 * @param   string  $url
	 *
	 * @return string
	 */
	public static function encodeUrlForSitemap($url)
	{
		// split query
		$bits  = explode('?', $url ?? '', 2);
		$path  = $bits[0];
		$query = empty($bits[1])
			? ''
			: $bits[1];

		$protectedPath = str_replace(
			array_keys(self::SITEMAP_URL_CHARACTER_PRESERVED),
			array_values(self::SITEMAP_URL_CHARACTER_PRESERVED),
			$path
		);

		$encoded = implode('/', array_map('rawurlencode', explode('/', $protectedPath))) // RFC 3986
			. (
			empty($query)
				? ''
				: '?' . self::encodeTextForSitemap(
					$query,
					false
				)
			);

		return str_replace(
			array_values(self::SITEMAP_URL_CHARACTER_PRESERVED),
			array_keys(self::SITEMAP_URL_CHARACTER_PRESERVED),
			$encoded
		);
	}

	/**
	 * Encode a text string to be used inside an XML sitemap.
	 *
	 * @param   string  $string
	 * @param   bool    $trim
	 *
	 * @return string
	 */
	public static function encodeTextForSitemap($string, $trim = true)
	{
		$cleaned = Strings::stripNonXmlCharSet($string);

		return htmlspecialchars(
			$trim
				? StringHelper::trim($cleaned)
				: $cleaned
			,
			ENT_QUOTES | ENT_XML1,
			'UTF-8',
			false
		);
	}

	/**
	 * Raw URL-encode a query variable value, handling arrays as well as scalar.
	 * Only encode if value is stable through URL decoding, hoping to avoid
	 * double-encoding.
	 *
	 * @param   array   $encodedQuery
	 * @param   string  $key
	 * @param   mixed   $value
	 *
	 * @return array|mixed
	 */
	private static function encodeQueryVar($encodedQuery, $key, $value)
	{
		if (is_array($value))
		{
			foreach ($value as $subKey => $subValue)
			{
				$encodedQuery[$subKey] = self::encodeQueryVar($encodedQuery, $subKey, $subValue);
			}
		}
		else
		{
			$encodedQuery = rawurldecode($value) === $value
				? rawurlencode($value)
				: $value;
		}

		return $encodedQuery;
	}
}
