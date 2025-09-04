<?php
/**
 * Project:                 4SEF
 *
 * @package          4SEF
 * @copyright        Copyright Weeblr llc - 2022 -2025
 * @author           Yannick Gaultier - Weeblr llc
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          2.6.2.644
 * @date                2025-06-02
 */

namespace Weeblr\Wblib\Forsef\System;

use Weeblr\Wblib\Forsef\Joomla\StringHelper\StringHelper;

defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * Simple external authentication system
 *
 */
class Auth
{
	const UUID4_WITH_DASHES = 1;
	const UUID4_NO_DASHES = 2;
	const UUID4_UPPERCASE = 1;
	const UUID4_LOWERCASE = 2;

	/**
	 * Time (in 100ns steps) between the start of the UTC and Unix epochs
	 * @var int
	 */
	const INTERVAL = 0x01b21dd213814000;

	/**
	 * 00001111  Clears all bits of version byte with AND
	 * @var int
	 */
	const CLEAR_VER = 15;

	/**
	 * 00111111  Clears all relevant bits of variant byte with AND
	 * @var int
	 */
	const CLEAR_VAR = 63;

	/**
	 * 10000000  The RFC 4122 variant (this variant)
	 * @var int
	 */
	const VAR_RFC = 128;

	/**
	 * 00010000
	 * @var int
	 */
	const VERSION_1 = 16;

	/**
	 * From http://stackoverflow.com/questions/2040240/php-function-to-generate-v4-uuid
	 *
	 * @param   int     $dashes  If true, dashes are removed from output (default is to keep them)
	 * @param   int     $case    If true, uuid is lowercased (default is to uppercase if)
	 * @param   string  $data    16 characters of random data. If not provided, openssl_random_pseudo_bytes(16) is used
	 *
	 * @return string
	 * @throws \Exception
	 */
	public static function uuidv4($dashes = self::UUID4_WITH_DASHES, $case = self::UUID4_UPPERCASE, $data = null)
	{
		if (is_null($data))
		{
			$data = self::randomBytes(16);
		}

		$data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
		$data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

		$template = self::UUID4_WITH_DASHES == $dashes ? '%s%s-%s-%s-%s-%s%s%s' : '%s%s%s%s%s%s%s%s';

		return self::UUID4_UPPERCASE == $case ? strtoupper(vsprintf($template, str_split(bin2hex($data), 4))) : strtolower(vsprintf($template, str_split(bin2hex($data), 4)));
	}

	/**
	 * Time-based uuid, usable in database indices.
	 *
	 * Based on https://github.com/webpatser/laravel-uuid/blob/master/src/Webpatser/Uuid/Uuid.php
	 */
	public static function uuidv1()
	{
		/** Get time since Gregorian calendar reform in 100ns intervals
		 * This is exceedingly difficult because of PHP's (and pack()'s)
		 * integer size limits.
		 * Note that this will never be more accurate than to the microsecond.
		 */
		$time = microtime(true) * 10000000 + static::INTERVAL;

		// Convert to a string representation
		$time = sprintf("%F", $time);

		//strip decimal point
		preg_match("/^\d+/", $time, $time);

		// And now to a 64-bit binary representation
		$time = base_convert($time[0], 10, 16);
		$time = pack("H*", str_pad($time, 16, "0", STR_PAD_LEFT));

		// Reorder bytes to their proper locations in the UUID
		$uuid = $time[4] . $time[5] . $time[6] . $time[7] . $time[2] . $time[3] . $time[0] . $time[1];

		// Generate a random clock sequence
		$uuid .= self::randomBytes(2);

		// set variant
		$uuid[8] = chr(ord($uuid[8]) & static::CLEAR_VAR | static::VAR_RFC);

		// set version
		$uuid[6] = chr(ord($uuid[6]) & static::CLEAR_VER | static::VERSION_1);

		// If no node was provided or if the node was invalid,
		//  generate a random MAC address and set the multicast bit
		$node    = self::randomBytes(6);
		$node[0] = pack("C", ord($node[0]) | 1);

		$uuid .= $node;

		return bin2hex($uuid);
	}

	public static function ulid($lowercase = true)
	{
		include_once __DIR__ . '/auth_ulid.php';

		return Ulid::generate($lowercase);
	}

	/**
	 * Generate a short id based on a random hash.
	 *
	 * @param   int  $length
	 *
	 * @return string
	 */
	public static function shortId($length = 10)
	{
		$hash = md5(self::uuidv4());

		return substr($hash, 0, $length);
	}

	/**
	 * Generate a short hash of a string.
	 *
	 * @param   string  $src
	 * @param   int     $length
	 *
	 * @return string
	 */
	public static function shortHash($src, $length = 10)
	{
		$hash = md5($src);

		return substr($hash, 0, $length);
	}

	/**
	 * Generate a given number of as random as possible bytes.
	 *
	 * @param   int  $bytesCount
	 *
	 * @return string
	 * @throws \Exception
	 */
	private static function randomBytes($bytesCount)
	{
		return phpversion() > 7 ? random_bytes($bytesCount) : openssl_random_pseudo_bytes($bytesCount);
	}

	/**
	 * Sign an outgoing request with (our) standard headers
	 *
	 * Note that they query should be used to send the request without being modified or added to, at least if the
	 * signature is being checked by the receiving end.
	 *
	 * Also, the query variables are alphabetically sorted on the array key (ie the query variable name)
	 * prior to signature being computed so as to normalize the input and insure repeatability on both ends
	 *
	 * @param   array   $query    Key/value array of query variables
	 * @param   string  $authKey  A secret key shared between emitter and receiver
	 * @param   string  $origin   Optional. the origin making the request. Formatted as scheme://full.host.tld[/path] No
	 *                            trailing slash
	 * @param   string  $extra    Optional. A string passed  as-is (and signed) with the request as x-wblr-auth-extra header
	 *
	 * @return Object  'query' => built query string (ie p1=123&p2=456...), 'urlEncodedQuery' => same as query but url
	 *     encoded, 'headers' => key/value array of headers to be sent
	 */
	public static function signRequest(
		$query,
		$authKey,
		$origin = '',
		$extra = ''
	)
	{
		$accessKey = self::splitAuthKey($authKey);
		$origin    = StringHelper::rtrim($origin, '/');
		$extra     = is_string($extra) ? StringHelper::trim($extra) : 'n/a';

		$request = new stdClass();
		$headers = array(
			'x-wblr-auth-ts'     => time(),
			'x-wblr-auth-id'     => $accessKey['key'],
			'x-wblr-auth-token'  => self::uuidv4(self::UUID4_NO_DASHES),
			'x-wblr-auth-origin' => empty($origin) ? '' : hash('sha256', $origin),
			'x-wblr-auth-extra'  => empty($extra) ? '' : $extra
		);

		// normalize
		ksort($query);

		// build the request, to be signed
		$queryString     = array();
		$queryUrlEncoded = array();
		foreach ($query as $key => $value)
		{
			$queryString[]     = $key . '=' . $value;
			$queryUrlEncoded[] = $key . '=' . urlencode($value);
		}
		$queryString     = implode('&', $queryString);
		$queryUrlEncoded = implode('&', $queryUrlEncoded);

		$base                       = $headers['x-wblr-auth-ts']
			. $headers['x-wblr-auth-id']
			. $headers['x-wblr-auth-token']
			. $headers['x-wblr-auth-origin']
			. $headers['x-wblr-auth-extra']
			. $accessKey['secret']
			. $queryString;
		$headers['x-wblr-auth-sig'] = hash('sha256', $base);
		$request->query             = $queryString;
		$request->urlEncodedQuery   = $queryUrlEncoded;
		$request->headers           = $headers;

		return $request;
	}

	/**
	 * Verify the integrity of an incoming request
	 *
	 * @param   string  $secretKey              the user secret key
	 * @param   array   $query                  Key/value array of query variables
	 * @param   array   $incomingHeaders
	 * @param           $allowedTimeSkew
	 * @param   bool    $urlDecodeBeforeVerify  Whether query string should be urldecode-d before auth is verified
	 *
	 * @return Object  'status' => HTTP status code, 'message' => Description of the response status
	 */
	public static function verifyRequest(
		$secretKey,
		$query,
		$incomingHeaders,
		$allowedTimeSkew,
		$urlDecodeBeforeVerify = true
	)
	{
		$verifiedRequest          = new stdClass();
		$verifiedRequest->code    = 200;
		$verifiedRequest->message = 'OK';

		$headers = array_merge(
			array(
				'x-wblr-auth-ts'     => '',
				'x-wblr-auth-id'     => '',
				'x-wblr-auth-token'  => '',
				'x-wblr-auth-origin' => '',
				'x-wblr-auth-extra'  => '',
				'x-wblr-auth-sig'    => '',
			),
			$incomingHeaders
		);

		// prevent edge cases when values are not supplied
		// NB: origin and extra are optional, depends on use case
		if (
			empty($headers['x-wblr-auth-ts'])
			||
			empty($headers['x-wblr-auth-id'])
			||
			empty($headers['x-wblr-auth-token'])
			||
			empty($headers['x-wblr-auth-sig'])
		)
		{
			$verifiedRequest->message = 'Not authorized (invalid headers).';
			$verifiedRequest->code    = 403;
		}

		if (!self::hasValidTimeSkew(
			$headers['x-wblr-auth-ts'],
			$allowedTimeSkew
		)
		)
		{
			$verifiedRequest->message = 'Not authorized (invalid timestamp).';
			$verifiedRequest->code    = 403;
		}

		if (!self::hasValidSignature($secretKey, $query, $headers, $urlDecodeBeforeVerify))
		{
			$verifiedRequest->message = 'Not authorized (invalid signature).';
			$verifiedRequest->code    = 403;
		}

		return $verifiedRequest;
	}

	/**
	 * Split user-provided weeblrpress.com access key in
	 * 2 parts: public and private
	 *
	 * @param $authKey
	 *
	 * @return array
	 */
	private static function splitAuthKey($authKey)
	{
		$authKey  = StringHelper::trim($authKey);
		$splitKey = array('key' => '', 'secret' => '');
		if (64 != strlen($authKey))
		{
			return $splitKey;
		}

		$splitKey['key']    = substr($authKey, 0, 32);
		$splitKey['secret'] = substr($authKey, 32);

		return $splitKey;
	}

	/**
	 * Check whether the request time stamp is older than a given threshold
	 *
	 * An allowedTimeStamp value of 0 disables the test
	 *
	 * @param   int  $requestTimeStamp
	 * @param   int  $allowedTimeSkew
	 *
	 * @return bool
	 */
	private static function hasValidTimeSkew($requestTimeStamp, $allowedTimeSkew)
	{
		$skew = time() - (int) $requestTimeStamp;
		if (!empty($allowedTimeSkew) && abs($skew) > $allowedTimeSkew)
		{
			return false;
		}

		return true;
	}

	/**
	 * Run signing method on query to verify it matches the passed signature
	 *
	 * @param   string  $secretKey              the user secret key
	 * @param   array   $query
	 * @param   array   $headers
	 * @param   bool    $urlDecodeBeforeVerify  Whether query string should be urldecode-d before auth is verified
	 *
	 * @return bool
	 */
	private static function hasValidSignature($secretKey, $query, $headers, $urlDecodeBeforeVerify = false)
	{
		$base = $headers['x-wblr-auth-ts']
			. $headers['x-wblr-auth-id']
			. $headers['x-wblr-auth-token']
			. $headers['x-wblr-auth-origin']
			. $headers['x-wblr-auth-extra']
			. $secretKey;

		// sort query array by key, to normalize hash building
		ksort($query);

		// build up query string
		$queryString = array();
		foreach ($query as $key => $value)
		{
			$queryString[] = $key . '=' . ($urlDecodeBeforeVerify ? urldecode($value) : $value);
		}
		$base .= implode('&', $queryString);

		// now verify signature against the one passed in the request
		$computedSignature = hash('sha256', $base);
		if ($computedSignature != $headers['x-wblr-auth-sig'])
		{
			return false;
		}

		return true;
	}

	/**
	 * Builds a unique hash for a callable callback.
	 *
	 * Taken from Wordpress 5.4
	 *
	 * @param   Callable  $callback
	 *
	 * @return string
	 */
	public static function callbackUniqueId($callback)
	{
		if (is_string($callback))
		{
			return $callback;
		}

		if (is_object($callback))
		{
			// Closures are currently implemented as objects.
			$callback = array($callback, '');
		}
		else
		{
			$callback = (array) $callback;
		}

		if (is_object($callback[0]))
		{
			// Object class calling.
			return spl_object_hash($callback[0]) . $callback[1];
		}
		elseif (is_string($callback[0]))
		{
			// Static calling.
			return $callback[0] . '::' . $callback[1];
		}
	}

	/**
	 * Produces a unique hash of a piece of data.
	 *
	 * @param   mixed  $content
	 *
	 * @return string
	 */
	public static function hashContent($content)
	{
		try
		{
			return sha1(
				serialize($content)
			);
		}
		catch (\Throwable $e)
		{
			return '';
		}
		catch (\Exception $e)
		{
			return '';
		}
	}

}