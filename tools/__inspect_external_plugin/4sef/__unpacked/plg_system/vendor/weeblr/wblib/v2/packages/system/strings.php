<?php
/**
 * Project:                 4SEF
 *
 * @author           Yannick Gaultier - Weeblr llc
 * @copyright        Copyright Weeblr llc - 2022 -2025
 * @package          4SEF
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          @build_version_full_build@
 * @date                2025-06-02
 */

namespace Weeblr\Wblib\Forsef\System;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Joomla\StringHelper\StringHelper;

// no direct access
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * Provide a few string manipulation methods
 *
 * @since    0.2.1
 *
 */
class Strings
{

	const NONE = 'none';
	const LOWERCASE = 'lowercase';
	const UPPERCASE = 'uppercase';
	const UCFIRST = 'ucfirst';

	/**
	 * Performs a preg_replace, wrapping it to catch errors
	 * caused by bad characters or otherwise
	 *
	 * @param   string  $pattern  RegExp pattern
	 * @param   string  $replace  RegExp replacement
	 * @param   string  $subject  RegExp subject
	 * @param   string  $ref      Optional reference, to be logged in case of error
	 *
	 * @return    string    the result of preg_replace operation
	 */
	public static function pr($pattern, $replace, $subject, $ref = '')
	{
		static $pageUrl = null;

		$tmp = preg_replace($pattern, $replace, $subject);
		if (is_null($tmp))
		{
			$pageUrl = is_null($pageUrl) ? (empty($_SERVER['REQUEST_URI']) ? '' : ' on page ' . $_SERVER['REQUEST_URI']) : $pageUrl;
			Log::libraryError(
				sprintf('%s::%d: %s', __METHOD__, __LINE__,
					'RegExp failed: error code: ' . preg_last_error() . ', ' . $pageUrl . (empty($ref) ? '' : ' (' . $ref . ')')
				)
			);

			return $subject;
		}
		else
		{
			return $tmp;
		}
	}

	/**
	 * Format into K and M for large number
	 * 0 -> 9999 : literral
	 * 10 000 -> 999999 : 10K -> 999,9K (max one decimal)
	 * > 1000 000 : 1M -> 1,9M (max 1 decimals)
	 *
	 * @param $n
	 *
	 * @return string
	 */
	public static function formatIntForTitle($n)
	{

		if ($n < 10000)
		{
			return (int) $n;
		}
		else if ($n < 1000000)
		{
			$n = $n / 100.0;
			$n = floor($n) / 10;
			$n = sprintf('%.1f', $n) . 'K';
		}
		else
		{
			$n = $n / 100000;
			$n = floor($n) / 10;
			$n = sprintf('%.1f', $n) . 'M';
		}

		return $n;
	}

	/**
	 * Explode a string about a delimiter, then store each part
	 * into an array, after trimming characters at both ends.
	 * Only non-empty cleaned items are added to the returned array.
	 *
	 * @param   string  $string
	 * @param   string  $delimiter
	 * @param   string  $caseHandling  none | lowercase | uppercase | ufcirst
	 *
	 * @return array
	 */
	public static function stringToCleanedArray($string, $delimiter = ',', $caseHandling = self::NONE)
	{
		$output = [];
		if (empty($string))
		{
			return $output;
		}
		$bits = explode($delimiter, $string);
		if (!empty($bits))
		{
			foreach ($bits as $bit)
			{
				$cleaned = StringHelper::trim($bit);
				if (!empty($cleaned))
				{
					switch ($caseHandling)
					{
						case self::LOWERCASE:
							$output[] = StringHelper::strtolower($cleaned);
							break;
						case self::UPPERCASE:
							$output[] = StringHelper::strtoupper($cleaned);
							break;
						case self::UCFIRST:
							$output[] = StringHelper::ucfirst($cleaned);
							break;
						default:
							$output[] = $cleaned;
							break;
					}
				}
			}
		}

		return $output;
	}

	/**
	 * @package     Joomla.Platform
	 * @subpackage  Utilities
	 *
	 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
	 * @license     GNU General Public License version 2 or later; see LICENSE
	 */

	/**
	 * Method to extract key/value pairs out of a string with XML style attributes
	 *
	 * @param   string  $string  String containing XML style attributes
	 *
	 * @return  array  Key/Value pairs for the attributes
	 *
	 * @since   11.1
	 */
	public static function parseAttributes($string)
	{

		$attr     = array();
		$retarray = array();

		// Let's grab all the key/value pairs using a regular expression
		preg_match_all('/([\w:-]+)[\s]?=[\s]?"([^"]*)"/i', $string, $attr);

		if (is_array($attr))
		{
			$numPairs = count($attr[1]);

			for ($i = 0; $i < $numPairs; $i++)
			{
				$retarray[$attr[1][$i]] = $attr[2][$i];
			}
		}

		return $retarray;
	}

	/**
	 * Convert a string to camelcase (javascript style) with the first
	 * letter in lowercase.
	 *
	 * MAIN_MENU becomes mainMenu
	 *
	 * @param   string  $source
	 * @param   string  $separator
	 *
	 * @return string
	 */
	public static function toCamelcase($source, $separator = ' ')
	{
		return \lcfirst(
			\implode('',
				\array_map(
					'ucfirst',
					\explode($separator, $source)
				)
			)
		);
	}

	public static function wrapJsonValue($value, $type = 's')
	{
		return '{{' . $type . '}}' . $value . '{{/' . $type . '}}';
	}

	/**
	 * Return passed string as a pretty-printed JSON string, if
	 * PHP version allows.
	 *
	 * @param   string  $json
	 * @param   bool    $humanReadable
	 * @param   int     $serializePrecision
	 * @param   array   $options
	 *
	 * @return false|string
	 */
	public static function jsonPrettyPrint($json, $humanReadable = false, $serializePrecision = -1, $options = ['JSON_NUMERIC_CHECK', 'JSON_UNESCAPED_SLASHES', 'JSON_UNESCAPED_UNICODE'])
	{
		static $displayOptions = null;
		if (is_null($displayOptions))
		{
			// display options based on PHP version
			$displayOptions = false;
			foreach ($options as $optionsKey)
			{
				$displayOptions = defined($optionsKey) ? $displayOptions | constant($optionsKey) : $displayOptions;
			}
		}

		// https://stackoverflow.com/questions/42981409/php7-1-json-encode-float-issue
		if (version_compare(phpversion(), '7.1', '>='))
		{
			ini_set('serialize_precision', $serializePrecision);
		}

		$encoded = \json_encode($json, $humanReadable ? $displayOptions | JSON_PRETTY_PRINT : $displayOptions);

		return preg_replace(
			'~{{s}}(.*){{/s}}~uUs',
			'$1',
			$encoded
		);
	}

	/**
	 * Return passed string as a pretty-printed JSON string, if
	 * PHP version allows.
	 *
	 * @param   string  $json
	 * @param   int     $serializePrecision
	 * @param   int     $options
	 *
	 * @return false|string
	 */
	public static function toJson($json, $serializePrecision = -1, $options = JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
	{
		// https://stackoverflow.com/questions/42981409/php7-1-json-encode-float-issue
		if (version_compare(phpversion(), '7.1', '>='))
		{
			ini_set('serialize_precision', $serializePrecision);
		}

		$encoded = \json_encode($json, $options);

		return preg_replace(
			'~{{s}}(.*){{/s}}~uUs',
			'$1',
			$encoded
		);
	}

	/**
	 * Return passed string as a pretty-printed JSON string, if
	 * PHP version allows. Also UNESCAPE all slashes, as slashes
	 * escaping will always happen on PHP less than 5.4
	 * While valid json, escaping slashes can sometimes cause issues
	 * and is a waste anyway.
	 *
	 * Meant to unescape URLs in json fields. Do not use if regular content
	 * may have escaped slashes that should stay escaped.
	 *
	 * @param   string  $json
	 *
	 * @return mixed|string|void
	 */
	public static function jsonPrettyPrintAndUnescapeSlashes($json)
	{
		static $shouldUnescapeSlashes = null;

		if (is_null($shouldUnescapeSlashes))
		{
			$shouldUnescapeSlashes = !defined('JSON_UNESCAPED_SLASHES');
		}

		$encodedJson = self::jsonPrettyPrint($json);
		if ($shouldUnescapeSlashes)
		{
			$encodedJson = str_replace('\\/', '/', $encodedJson);
		}

		return $encodedJson;
	}

	/**
	 * Make a string safe to use as an HTML id attribute
	 *
	 * @param   string  $id
	 *
	 * @return string
	 */
	public static function asHtmlId($id)
	{
		$id = str_replace(
			array('[', ']'),
			'',
			htmlspecialchars(
				$id,
				ENT_QUOTES,
				'UTF-8'
			)
		);
		$id = str_replace(array('/', '\\', '.'), '_', $id);

		return $id;
	}

	/**
	 * Extract the content of an HTML tag from a string. Typically, body, head, html.
	 * Alway return the first instance.
	 *
	 * @param   string  $buffer
	 * @param   string  $tag
	 *
	 * @return mixed
	 */
	public static function getTagInBuffer($buffer, $tag)
	{
		if (!$buffer || !$tag)
		{
			return $buffer;
		}

		$pattern = '~<' . $tag . '[^>]*>.*</' . $tag . '[^>]*>~iUsu';

		$matched = preg_match(
			$pattern,
			$buffer,
			$matches
		);

		return $matched
			? $matches[0]
			: '';
	}

	// utility function to insert data into an html buffer, after, instead or before
	// one or more instances of a tag. If last parameter is 'first', then only the
	// first occurence of the tag is replaced, or the new value is inserted only
	// after or before the first occurence of the tag
	public static function tagInBuffer($buffer, $tag, $value, $options = [])
	{
		if (!$buffer || !$tag)
		{
			return $buffer;
		}
		$bits = explode($tag, $buffer);
		if (count($bits) < 2)
		{
			return $buffer;
		}

		$firstOnly = Wb\arrayGet($options, 'firstOnly', false);
		$lastOnly  = Wb\arrayGet($options, 'lastOnly', false);
		$where     = Wb\arrayGet($options, 'where', 'instead');

		$result   = $bits[0];
		$maxCount = count($bits) - 1;
		switch ($where)
		{
			case 'instead':
				for ($i = 0; $i < $maxCount; $i++)
				{
					$replacement = $value;
					if ($firstOnly && $i !== 0)
					{
						$replacement = $tag;
					}
					if ($lastOnly && $i !== $maxCount - 1)
					{
						$replacement = $tag;
					}

					$result .= $replacement;
					$result .= $bits[$i + 1];
				}
				break;
			case 'after':
				for ($i = 0; $i < $maxCount; $i++)
				{
					$result .= $tag;


					$replacement = $value;
					if ($firstOnly && $i !== 0)
					{
						$replacement = '';
					}
					if ($lastOnly && $i !== $maxCount - 1)
					{
						$replacement = '';
					}
					$result .= $replacement;

					$result .= $bits[$i + 1];
				}
				break;
			default:
				// before
				for ($i = 0; $i < $maxCount; $i++)
				{
					$replacement = $value;
					if ($firstOnly && $i !== 0)
					{
						$replacement = '';
					}
					if ($lastOnly && $i !== $maxCount - 1)
					{
						$replacement = '';
					}
					$result .= $replacement;

					$result .= $tag . $bits[$i + 1];
				}
				break;
		}

		return $result;
	}

	public static function pregTagInBuffer($buffer, $tag, $value, $options = [])
	{
		$counter = 0;

		return self::pregInBuffer($buffer, $tag, $value, $counter, $options);
	}

	public static function pregInBuffer($buffer, $tag, $value, &$counter, $options = [])
	{
		if (!$buffer || !$tag)
		{
			return $buffer;
		}

		$isRegExp        = Wb\arrayGet($options, 'isRegExp', false);
		$wholeWordsOnly  = Wb\arrayGet($options, 'wholeWordsOnly', false);
		$firstOnly       = Wb\arrayGet($options, 'firstOnly', false);
		$maxReplacements = Wb\arrayGet($options, 'maxReplacements', -1);
		if ($firstOnly)
		{
			// legacy interface, no need for firstOnly now.
			$maxReplacements = 1;
		}
		$where           = Wb\arrayGet($options, 'where', 'instead');
		$isCaseSensitive = Wb\arrayGet($options, 'isCaseSensitive', false);
		$protectLinks    = Wb\arrayGet($options, 'protectLinks', false);
		$protectHtmlTags = Wb\arrayGet($options, 'protectHtmlTags', false);
		$protectHnTags   = Wb\arrayGet($options, 'protectHnTags', false);

		if ($isRegExp)
		{
			$pattern = $tag;
		}
		else
		{
			$protectedPattern = preg_quote(
				$tag,
				'~'
			);
			$subPattern       = $wholeWordsOnly
				? '(?<!\pL)(' . $protectedPattern . ')(?!\pL)'
				: '(' . $protectedPattern . ')';
			$pattern          = '~' . $subPattern . '~'
				. ($isCaseSensitive ? '' : 'i')
				. 'Usu';
		}

		switch ($where)
		{
			case 'instead':
				$replacement = $value;
				break;
			case 'after':
				$replacement = '$1' . $value;
				break;
			default:
				$replacement = $value . '$1';
				break;
		}

		$protectedBuffer = $buffer;

		$replacedLinkAnchorsCount = 0;
		$linkAnchorsReplacements  = [];
		if ($protectLinks)
		{
			// if protecting links, we must also protect anchors in links
			$protectionPattern = '~<a\s[^>]*>(.*)</a>~iuUs';
			$protectedBuffer   = preg_replace_callback(
				$protectionPattern,
				function ($matches) use (&$linkAnchorsReplacements) {
					$hash                           = md5($matches[1]);
					$linkAnchorsReplacements[$hash] = $matches[1];

					return str_replace(
						'>' . $matches[1] . '<',
						'>' . $hash . '<',
						$matches[0]
					);
				},
				$protectedBuffer,
				-1,
				$replacedLinkAnchorsCount
			);
		}

		$replacedHnTagsCount = 0;
		$hnTagsReplacements  = [];
		if ($protectHnTags)
		{
			$protectionPattern = '~(<h(\d)[^>]*>.*</h\\2>)~iuUs';
			$protectedBuffer   = preg_replace_callback(
				$protectionPattern,
				function ($matches) use (&$hnTagsReplacements) {
					// store placeholder and original value
					$hash                      = md5($matches[1]);
					$hnTagsReplacements[$hash] = $matches[1];

					return $hash;
				},
				$protectedBuffer,
				-1,
				$replacedHnTagsCount
			);
		}

		$replacedHtmlTagsCount = 0;
		$htmlTagsReplacements  = [];
		if ($protectHtmlTags)
		{
			$protectionPattern = '~(<[a-z][^>]*>)~iuUs';
			$protectedBuffer   = preg_replace_callback(
				$protectionPattern,
				function ($matches) use (&$htmlTagsReplacements) {
					// store placeholder and original value
					$hash                        = md5($matches[1]);
					$htmlTagsReplacements[$hash] = $matches[1];

					return $hash;
				},
				$protectedBuffer,
				-1,
				$replacedHtmlTagsCount
			);
		}

		$replacedLinksCount = 0;
		$linksReplacements  = [];
		if (
			!$protectHtmlTags // no need to protect links if we already protected all HTML tags.
			&&
			$protectLinks
		)
		{
			$protectionPattern = '~ (src|href)=([^ >]+)([ >])~iuUs';
			$protectedBuffer   = preg_replace_callback(
				$protectionPattern,
				function ($matches) use (&$linksReplacements) {
					// store placeholder and original value
					$hash                     = md5($matches[2]);
					$linksReplacements[$hash] = $matches[2];

					return ' ' . $matches[1] . '=' . $hash . $matches[3];
				},
				$protectedBuffer,
				-1,
				$replacedLinksCount
			);
		}

		$counter = 0;
		$result  = preg_replace($pattern, $replacement, $protectedBuffer, $maxReplacements, $counter);
		if (empty($result))
		{
			Log::libraryError(
				'wbLib', '%s::%s::%d: %s', __CLASS__, __METHOD__, __LINE__,
				'RegExp failed: invalid character in regular expression.'
			);

			return $buffer;
		}

		if (
			$protectHtmlTags
			&&
			!empty($replacedHtmlTagsCount)
		)
		{
			// inject back html tags
			foreach ($htmlTagsReplacements as $hash => $tag)
			{
				$result = str_replace(
					$hash,
					$tag,
					$result
				);
			}
		}

		if (
			$protectHnTags
			&&
			!empty($replacedHnTagsCount)
		)
		{
			// inject back html tags
			foreach ($hnTagsReplacements as $hash => $tag)
			{
				$result = str_replace(
					$hash,
					$tag,
					$result
				);
			}
		}

		if (
			!$protectHtmlTags
			&&
			$protectLinks
			&&
			!empty($replacedLinksCount)
		)
		{
			// inject back links
			foreach ($linksReplacements as $hash => $tag)
			{
				$result = str_replace(
					$hash,
					$tag,
					$result
				);
			}
		}

		if (
			$protectLinks
			&&
			!empty($replacedLinkAnchorsCount)
		)
		{
			// inject back anchors
			foreach ($linkAnchorsReplacements as $hash => $tag)
			{
				$result = str_replace(
					$hash,
					$tag,
					$result
				);
			}
		}

		return $result;
	}

	/**
	 * Exclude invalid characters from a string for use in an XML file.
	 *
	 * Ref: https://www.w3.org/TR/REC-xml/#charsets
	 *
	 * @TODO: maybe exclude these characters as well?
	 *
	 *      Document authors are encouraged to avoid "compatibility characters"...
	 *
	 * [#x7F-#x84], [#x86-#x9F], [#xFDD0-#xFDEF],
	 * [#x1FFFE-#x1FFFF], [#x2FFFE-#x2FFFF], [#x3FFFE-#x3FFFF],
	 * [#x4FFFE-#x4FFFF], [#x5FFFE-#x5FFFF], [#x6FFFE-#x6FFFF],
	 * [#x7FFFE-#x7FFFF], [#x8FFFE-#x8FFFF], [#x9FFFE-#x9FFFF],
	 * [#xAFFFE-#xAFFFF], [#xBFFFE-#xBFFFF], [#xCFFFE-#xCFFFF],
	 * [#xDFFFE-#xDFFFF], [#xEFFFE-#xEFFFF], [#xFFFFE-#xFFFFF],
	 * [#x10FFFE-#x10FFFF]
	 *
	 * @param   string  $string
	 * @param   string  $replaceWith  A string used to replace invalid characters, defaults to a single space.
	 *
	 * @return array|string|string[]|null
	 */
	public static function stripNonXmlCharSet($string, $replaceWith = ' ')
	{
		return preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]+/u', $replaceWith, $string);
	}

	/**
	 * Check if a string only contains valid UTF8 characters.
	 *
	 * @param $string
	 *
	 * @return bool
	 */
	public static function isValidUtf8($string)
	{
		if (strlen($string) == 0)
		{
			return true;
		}

		return (preg_match('/^.{1}/us', $string, $matches) == 1);
	}
}
