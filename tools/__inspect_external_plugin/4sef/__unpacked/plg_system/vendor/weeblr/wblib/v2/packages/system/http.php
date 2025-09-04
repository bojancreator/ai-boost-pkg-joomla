<?php
/**
 * Project:                 4SEF
 *
 * @package          4SEF
 * @copyright        Copyright Weeblr llc - 2022 -2025
 * @author           Yannick Gaultier - Weeblr llc
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          @build_version_full_build@
 * @date                2025-06-02
 */

namespace Weeblr\Wblib\Forsef\System;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Factory;
use Weeblr\Wblib\Forsef\Joomla\Uri;
use Joomla\Utilities\IpHelper;

// no direct access
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

class Http
{

	// return code
	const RETURN_ZERO                          = 0;
	const RETURN_OK                            = 200;
	const RETURN_CREATED                       = 201;
	const RETURN_ACCEPTED                      = 202;
	const RETURN_NO_CONTENT                    = 204;
	const RETURN_MOVED                         = 301;
	const RETURN_FOUND                         = 302;
	const RETURN_SEE_OTHER                     = 303;
	const RETURN_NOT_MODIFIED                  = 304;
	const RETURN_BAD_REQUEST                   = 400;
	const RETURN_UNAUTHORIZED                  = 401;
	const RETURN_FORBIDDEN                     = 403;
	const RETURN_NOT_FOUND                     = 404;
	const RETURN_PROXY_AUTHENTICATION_REQUIRED = 407;
	const RETURN_MISDIRECTED_REQUEST           = 421;
	const RETURN_UNPROCESSABLE_ENTITY          = 422;
	const RETURN_TOO_MANY_REQUESTS             = 429;
	const RETURN_INTERNAL_ERROR                = 500;
	const RETURN_NOT_IMPLEMENTED               = 501;
	const RETURN_SERVICE_UNAVAILABLE           = 503;

	/**
	 * Abort the current HTTP response
	 *
	 * @param int    $code
	 * @param string $cause
	 */
	public static function abort($code = self::RETURN_NOT_FOUND, $cause = '')
	{
		$header = self::getHeader($code, $cause);

		// clean all buffers
		if (ob_get_length())
		{
			ob_end_clean();
		}

		$msg = empty($cause) ? $header->msg : $cause;
		if (!headers_sent())
		{
			header($header->raw);
		}
		die($msg);
	}

	/**
	 * Get HTTP header for response based on status
	 *
	 * @param $code
	 * @param $cause
	 *
	 * @return stdClass
	 */
	public static function getHeader($code, $cause)
	{
		$code   = intval($code);
		$header = new \stdClass();

		switch ($code)
		{
			case self::RETURN_OK:
				$header->raw = 'HTTP/1.0 200 OK';
				$header->msg = 'OK';
				break;
			case self::RETURN_CREATED:
				$header->raw = 'HTTP/1.0 201 CREATED';
				$header->msg = 'Created';
				break;
			case self::RETURN_NO_CONTENT:
				$header->raw = 'HTTP/1.0 204 OK';
				$header->msg = 'No content';
				break;

			case self::RETURN_BAD_REQUEST:
				$header->raw = 'HTTP/1.0 400 BAD REQUEST';
				$header->msg = '<h1>Unauthorized</h1>';
				break;
			case self::RETURN_UNAUTHORIZED:
				$header->raw = 'HTTP/1.0 401 UNAUTHORIZED';
				$header->msg = '<h1>Unauthorized</h1>';
				break;
			case self::RETURN_FORBIDDEN:
				$header->raw = 'HTTP/1.0 403 FORBIDDEN';
				$header->msg = '<h1>Forbidden access</h1>';
				break;
			case self::RETURN_NOT_FOUND:
				$header->raw = 'HTTP/1.0 404 NOT FOUND';
				$header->msg = '<h1>Page not found</h1>';
				break;
			case self::RETURN_PROXY_AUTHENTICATION_REQUIRED:
				$header->raw = 'HTTP/1.0 407 PROXY AUTHENTICATION REQUIRED';
				$header->msg = '<h1>Proxy authentication required</h1>';
				break;
			case self::RETURN_INTERNAL_ERROR:
				$header->raw = 'HTTP/1.0 500 INTERNAL ERROR';
				$header->msg = 'Internal error';
				break;
			case self::RETURN_SERVICE_UNAVAILABLE:
				$header->raw = 'HTTP/1.0 503 SERVICE UNAVAILABLE';
				$header->msg = '<h1>Service unavailable</h1>';
				break;

			default:
				$header->raw = 'HTTP/1.0 ' . $code;
				$header->msg = $cause;
				break;
		}

		return $header;
	}

	public static function getReferrer()
	{
		static $referrer;

		if (is_null($referrer))
		{
			$referrer = Wb\arrayGet(
				$_SERVER,
				'HTTP_REFERER',
				''
			);
		}

		return $referrer;

	}

	public static function getAllHeaders()
	{
		static $headers = null;

		if (is_null($headers))
		{
			if (\function_exists('getallheaders'))
			{
				// If php is working under Apache, there is a special function
				$headers = getallheaders();
			}
			else
			{
				// Else we fill headers from $_SERVER variable
				$headers = [];

				foreach ($_SERVER as $name => $value)
				{
					if (substr($name, 0, 5) == 'HTTP_')
					{
						$headers[str_replace(' ', '-', strtolower(str_replace('_', ' ', substr($name, 5))))] = $value;
					}
				}
			}

			// normalize everything to lowercase.
			$headers = array_change_key_case($headers);
		}

		return $headers;
	}

	/**
	 * Get the value of a specific request header. Null if header is not set.
	 *
	 * @param string $header
	 *
	 * @return string |null
	 */
	public static function getRequestHeader($header)
	{
		$headers = static::getAllHeaders();

		return Wb\arrayIsSet($headers, $header) ? $headers[$header] : null;
	}

	/**
	 * Returns the requester IP address, trying to bypass
	 * proxies when possible. Can return IP v4 or v6.
	 * Also used by the getIp() method in Platform class
	 *
	 * @param bool $checkReverseProxies
	 *
	 * @return string
	 */
	public static function getIpAddress($checkReverseProxies = true)
	{
		if ($checkReverseProxies)
		{
			$extraHeaders = [
				'x-real-ip',
				'cf-connecting-ip',
				'cf-connecting-ipv6'
			];

			foreach ($extraHeaders as $extraHeader)
			{
				$headerContent = self::getRequestHeader($extraHeader);
				if (!empty($headerContent))
				{
					return $headerContent;
				}
			}
		}

		return IpHelper::getIp();
	}

	/**
	 * Finds a match in a list of IP addresses, allowing for {*} and {?} wildcard
	 * characters.
	 *
	 * @param string $ip
	 * @param array  $ipSpecs
	 *
	 * @return bool
	 */
	public static function findIpMatchInList($ip, $ipSpecs)
	{
		if (
			!empty($ip)
			&&
			!empty($ipSpecs)
		)
		{
			foreach ($ipSpecs as $ipSpec)
			{
				if (Route::matchUrlRule($ipSpec, $ip))
				{
					return true;
				}
			}
		}

		return false;
	}

	public static function getReverseDns($ip)
	{
		static $host = null;
		static $cache = null;

		if (is_null($host))
		{
			if (is_null($cache))
			{
				$cache = Factory::get()
								->getThe('platform')
								->getCache(
									'output',
									[
										'caching'      => 1,
										'lifetime'     => 10080,
										'defaultgroup' => 'wblib_reverse_dns'
									]
								);
			}

			$host = $cache->get(
				$ip
			);

			if (false === $host)
			{
				$host = @gethostbyaddr($ip);
				$host = false === $host
					? ''
					: strtolower($host);
				$cache->store(
					$host,
					$ip
				);
			}
		}

		return $host;
	}

	public static function getForwardDns($host)
	{
		return @gethostbyname(
			strtolower($host)
		);
	}

	public static function buildUri($url = '')
	{
		if (empty($url))
		{
			$url = Factory::get()->getThe('platform')->getCurrentUrl();
		}

		return new Uri\Uri($url);
	}

	public static function isError($status)
	{
		$status = (int)$status;

		return $status > 399;
	}

	public static function isRedirect($status)
	{
		$status = (int)$status;

		return $status > 299 and $status < 400;
	}

	public static function isSuccess($status)
	{
		$status = (int)$status;

		return $status > 199 and $status < 300;
	}

	/**
	 * Renders an http response and end processing of request
	 *
	 * @param int    $code       http status to use for response
	 * @param string $cause      Optional text to use as response body
	 * @param string $type
	 * @param array  $otherHeaders
	 * @param bool   $endRequest Whether to just flush the response without ending the request.
	 */
	public static function render($code = self::RETURN_NOT_FOUND, $cause = '', $type = 'text/html', $otherHeaders = array(), $endRequest = true)
	{
		$header = self::getHeader($code, $cause);

		// clean all buffers
		if (ob_get_length())
		{
			ob_end_clean();
		}

		// final version of the content output
		$msg = empty($cause) ? $header->msg : $cause;

		// Build up headers
		$otherHeaders['Content-type']   = $type;
		$otherHeaders['Content-Length'] = strlen($msg);

		if (!$endRequest)
		{
			// special processing if there's code to run after the response
			// has been sent.

			$otherHeaders['Connection']        = 'close';
			$otherHeaders['Content-Encoding']  = 'none';
			$otherHeaders['Cache-control']     = 'no-cache, must-revalidate';
			$otherHeaders['X-Accel-Buffering'] = 'no';
			$otherHeaders['Surrogate-Control'] = 'BigPipe/1.0';

			// turn off gzip compression: this must be ran before
			// headers are sent
			if (function_exists('apache_setenv'))
			{
				apache_setenv('no-gzip', 1);
			}

			ini_set('zlib.output_compression', 0);
		}

		// output headers
		if (!headers_sent())
		{
			header($header->raw);
		}
		self::outputHeaders(
			$otherHeaders,
			true
		);

		// Output content
		if ($endRequest)
		{
			if (self::RETURN_NO_CONTENT !== $code && !is_null($msg))
			{
				echo $msg;
			}
			die();
		}
		else
		{
			if (!is_null($msg))
			{
				echo $msg;
			}
			self::flushResponse();
		}
	}

	/**
	 * Alternative to render() that can compress content. Will eventually replace render when ability
	 * to not end request is added.
	 *
	 * @param string $content
	 * @param int    $status
	 * @param array  $options
	 */
	public static function send($content, $status = self::RETURN_OK, $options = [])
	{
		$defaultOptions = [
			'compress' => '', // (empty) | none | gzip
			'type'     => 'text/html',
			'headers'  => []
		];

		$options = array_merge(
			$defaultOptions,
			$options
		);

		$header = self::getHeader($status, $content);

		// final version of the content output
		$content = empty($content)
			? $header->msg
			: $content;

		$responseHeaders = Wb\arrayGet($options, 'headers');

		$compress = Wb\arrayGet($options, 'compress', 'none');
		if (
			empty($compress)
			||
			(
				'none' !== $compress
				&&
				!self::canCompress()
			)
		)
		{
			$compress = 'none';
		}

		if ('none' !== $compress)
		{
			$compressed        = self::compress($content, $compress);
			$compressionMethod = Wb\arrayGet($compressed, 'compressionMethod', 'none');
			if (
				!empty($compressionMethod)
				&&
				'none' !== $compressionMethod
			)
			{
				$content                                 = Wb\arrayGet($compressed, 'compressedContent', $content);
				$responseHeaders['Content-Encoding']     = $compressionMethod;
				$responseHeaders['Vary']                 = 'Accept-Encoding';
				$responseHeaders['X-Content-Encoded-By'] = 'Weeblr wbLib';
			}
		}

		$responseHeaders['Content-type']   = Wb\arrayGet($options, 'type');
		$responseHeaders['Content-Length'] = strlen($content);

		// clean previous output
		if (ob_get_length())
		{
			ob_end_clean();
		}

		// ouptut
		if (!headers_sent())
		{
			header($header->raw);
		}
		self::outputHeaders($responseHeaders);

		echo $content;
		die();
	}

	/**
	 * Whether the current request response can be compressed.
	 *
	 * @return bool
	 */
	public static function canCompress()
	{
		if (
			headers_sent()
			||
			connection_status() !== CONNECTION_NORMAL
		)
		{
			// too late for compression
			return false;
		}

		if (
			!\extension_loaded('zlib')
			||
			ini_get('zlib.output_compression')
			||
			ini_get('output_handler') === 'ob_gzhandler'
		)
		{
			return false;
		}

		return true;
	}

	/**
	 * Compress a piece of content.
	 *
	 * @param string $content
	 * @param string $method // gzip | deflate
	 *
	 * @return array
	 */
	public static function compress($content, $method = 'gzip')
	{
		$uncompressed = [
			'compressionMethod' => 'none',
			'compressedContent' => $content
		];

		if (!\extension_loaded('zlib'))
		{
			return $uncompressed;
		}

		$compressedContent = gzencode(
			$content,
			4,
			$method == 'gzip'
				? FORCE_GZIP
				: FORCE_DEFLATE
		);

		if (false === $compressedContent)
		{
			return $uncompressed;
		}

		return [
			'compressionMethod' => $method,
			'compressedContent' => $compressedContent
		];
	}

	/**
	 * Flush all buffers and send back response.
	 */
	public static function flushResponse()
	{
		if (is_callable('fastcgi_finish_request'))
		{
			if (session_id())
			{
				session_write_close();
			}
			fastcgi_finish_request();

			return;
		}

		if (Wb\contains(php_sapi_name(), 'fcgi'))
		{
			// try to flush the mod_fcgid buffer
			echo str_repeat(' ', 66000);
		}

		ignore_user_abort(true);
		$levels = ob_get_level();
		for ($i = 0; $i < $levels; $i++)
		{
			ob_end_flush();
		}
		flush();
		ob_start();
	}

	/**
	 * Output an array of headers.
	 *
	 * @param array $headers Key/value array of headers
	 * @param bool  $replace
	 */
	public static function outputHeaders($headers, $replace = false)
	{
		if (ob_get_length())
		{
			ob_end_clean();
		}
		if (!headers_sent())
		{
			foreach ($headers as $key => $value)
			{
				header($key . ': ' . $value, $replace);
			}
		}
	}

	/**
	 * Perform a server-side 301 redirect to the target URL.
	 *
	 * @param string $target
	 */
	public static function redirectPermanent($target)
	{
		if (ob_get_length())
		{
			ob_end_clean();
		}
		if (headers_sent())
		{
			echo '<html><head><meta http-equiv="content-type" content="text/html; charset="UTF-8"'
				 . '" /><script>document.location.href=\'' . $target . '\';</script></head><body></body></html>';
		}
		else
		{
			header('Cache-Control: no-cache'); // prevent Firefox5+ and IE9+ to consider this a cacheable redirect
			header('HTTP/1.1 301 Moved Permanently');
			header('Location: ' . $target);
		}
		exit();
	}

	/**
	 * Extract a header identified by its key from an array of response headers.
	 *
	 * @param array  $headers
	 * @param string $name
	 * @param bool   $asString
	 * @param bool   $caseInsensitive
	 *
	 * @return array|mixed|string|null
	 */
	public static function extractResponseHeader($headers, $name, $asString = false, $caseInsensitive = true)
	{
		if ($caseInsensitive)
		{
			$headers = array_change_key_case($headers);
			$name    = strtolower($name);
		}

		$header = Wb\arrayGet(
			$headers,
			$name
		);

		if (empty($header))
		{
			return $asString
				? ''
				: [];
		}

		if (is_array($header))
		{
			$header = array_shift($header);
		}

		return $header;
	}

	/**
	 * Returns the current request user agent.
	 *
	 * @return string
	 */
	public static function userAgent()
	{
		static $userAgent = null;

		if (is_null($userAgent))
		{
			$userAgent = empty($_SERVER['HTTP_USER_AGENT'])
				? ''
				: $_SERVER['HTTP_USER_AGENT'];
		}

		return $userAgent;
	}
}
