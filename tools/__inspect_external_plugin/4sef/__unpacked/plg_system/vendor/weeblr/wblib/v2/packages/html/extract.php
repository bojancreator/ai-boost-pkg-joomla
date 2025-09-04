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

namespace Weeblr\Wblib\Forsef\Html;

use Weeblr\Wblib\Forsef\Factory;
use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Joomla\StringHelper\StringHelper;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Symfony\Component\CssSelector;

defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * HTML content extractor helper
 *
 */
class Extract
{
	/**
	 * Extract all href links from an html text buffer.
	 *
	 * @param string $buffer  HTML content to scan for links.
	 *
	 * @param array  $options Set of options:
	 *
	 *      bool    normalize                  If true, links are normalized to root-relative links.
	 *      bool    removeLeadingSlash         If true, leading slash of normalized URLs are removed.
	 *      bool    removeQuery                If true, query string are removed from extracted links
	 *      bool    onlyInternal               If true, only internal links are returned.
	 *      bool    skipNonHttp                If true, non-http links are not collected.
	 *      bool    skipAnchors                If true, anchors links are not included.
	 *      bool    stripAnchors               If true, anchors are removed from extractec links.
	 *      bool    skipTargetBlank            If true, links with a target=_blank attribute are not extracted.
	 *      bool    skipNoFollow               If true, links with a nofollow robots atttribute are not extracted.
	 *      bool    skipRelative               If true, only fully qualified or root-relative links are extracted. The safest, enabled by default.
	 *      bool    skipHreflang               If true, links found in a link element with rel=alternate and a hreflgang attribute are not collected.
	 *      bool    skipJavascript             If true, links with href starting with 'javacript' are not collected.
	 *      bool    removeTrailingIndexPhp     If true, trailing /index.php string of normalized URLs is removed
	 *                                              (but not index.php?)
	 *      string  currentUrl                 The current URL, needed to normalize relative URLs
	 *      array   queryVarsToStrip           An array of query var names that should be removed from collected URLs if present.
	 *
	 * @return array
	 * @throws \Exception
	 */
	static public function extractLinks($buffer, $options = [])
	{
		$links = [];

		// save time
		if (empty($buffer))
		{
			return $links;
		}

		$normalize              = Wb\arrayGet($options, 'normalize', true);
		$removeLeadingSlash     = Wb\arrayGet($options, 'removeLeadingSlash', true);
		$removeQuery            = Wb\arrayGet($options, 'removeQuery', false);
		$onlyInternal           = Wb\arrayGet($options, 'onlyInternal', true);
		$skipNonHttp            = Wb\arrayGet($options, 'skipNonHttp', true);
		$skipAnchors            = Wb\arrayGet($options, 'skipAnchors', true);
		$stripAnchors           = Wb\arrayGet($options, 'stripAnchors', true);
		$skipTargetBlank        = Wb\arrayGet($options, 'skipTargetBlank', false);
		$skipNoFollow           = Wb\arrayGet($options, 'skipNoFollow', false);
		$skipRelative           = Wb\arrayGet($options, 'skipRelative', true);
		$skipHreflang           = Wb\arrayGet($options, 'skipHreflang', true);
		$skipJavascript         = Wb\arrayGet($options, 'skipJavascript', true);
		$removeTrailingIndexPhp = Wb\arrayGet($options, 'removeTrailingIndexPhp', true);
		$currentUrl             = Wb\arrayGet($options, 'currentUrl', '/');
		$queryVarsToStrip       = Wb\arrayGet($options, 'queryVarsToStrip', []);
		$rawUrlDecode           = Wb\arrayGet($options, 'rawUrlDecode', false);
		$encodeSpaces           = Wb\arrayGet($options, 'encodeSpaces', true);

		$dom   = self::domFromContent($buffer);
		$aTags = $dom->getElementsByTagName('a');
		foreach ($aTags as $aTag)
		{
			$href = StringHelper::trim(
				$aTag->getAttribute('href')
			);

			$href = $rawUrlDecode
				? rawurldecode($href)
				: $href;

			$href = $encodeSpaces
				? preg_replace('/\s/u', '%20', $href)
				: $href;

			if (empty($href))
			{
				continue;
			}

			if ($removeQuery)
			{
				$href = System\Route::trimQuery($href);
			}

			if (
				$skipJavascript
				&&
				Wb\startsWith($href, 'javascript:')
			)
			{
				continue;
			}

			if (
				$skipNonHttp
				&&
				System\Route::isNonHttp($href)
			)
			{
				continue;
			}

			if (
				$skipRelative
				&&
				!Wb\startsWith($href, '/') // root relatives are ok
				&&
				!Wb\startsWith($href, '#') // anchors are ok
				&&
				!System\Route::isFullyQualified($href)
			)
			{
				continue;
			}

			if ($skipTargetBlank)
			{
				$target = StringHelper::trim(
					$aTag->getAttribute('target')
				);
				if ('_blank' == strtolower($target))
				{
					continue;
				}
			}

			if ($skipNoFollow)
			{
				$rel = StringHelper::trim(
					$aTag->getAttribute('rel')
				);
				if ('nofollow' == strtolower($rel))
				{
					continue;
				}
			}

			if (Wb\contains($href, '#'))
			{
				if ($skipAnchors)
				{
					// simplest case
					if (Wb\startsWith($href, '#'))
					{
						continue;
					}
				}

				if ($stripAnchors)
				{
					$bits = explode('#', $href);
					$href = $bits[0];
				}

			}

			if (!empty($queryVarsToStrip))
			{
				$href = System\Route::removeQueryVarFromUrl(
					$href,
					$queryVarsToStrip
				);
			}

			if (!empty($str))
			{
				$href = System\Route::resolveRelativeUrl(
					$href,
					$currentUrl
				);
			}

			if (
				$onlyInternal
				&&
				!System\Route::isInternal($href)
			)
			{
				continue;
			}

			if (
				Wb\contains($href, '#')
				&&
				$skipAnchors
			)
			{
				// skip URLs which are anchors, that is on the exact same page. Leave others.
				if (Wb\startsWith($href, $currentUrl))
				{
					continue;
				}
			}

			if ($normalize)
			{
				$href = System\Route::makeRootRelative(
					$href,
					$removeLeadingSlash
				);
				$href = $removeTrailingIndexPhp
					? Wb\rTrim($href, 'index.php')
					: $href;
			}

			if (!in_array($href, $links))
			{
				$links[] = $href;
			}
		}

		$linkTags = $skipHreflang
			? []
			: $dom->getElementsByTagName('link');
		foreach ($linkTags as $aTag)
		{
			$hreflang = StringHelper::trim(
				$aTag->getAttribute('hreflang')
			);
			$hreflang = $rawUrlDecode
				? rawurldecode($hreflang)
				: $hreflang;
			$hreflang = $encodeSpaces
				? preg_replace('/\s/u', '%20', $hreflang)
				: $hreflang;

			$rel = strtolower(
				StringHelper::trim(
					$aTag->getAttribute('rel')
				)
			);

			if (
				empty($hreflang)
				||
				'alternate' != $rel
			)
			{
				continue;
			}

			$href = StringHelper::trim(
				$aTag->getAttribute('href')
			);

			if (empty($href))
			{
				continue;
			}

			if (Wb\contains($href, '#'))
			{
				// simplest case
				if (Wb\startsWith($href, '#'))
				{
					continue;
				}

				$bits = explode('#', $href);
				$href = $bits[0];
			}

			$href = System\Route::resolveRelativeUrl(
				$href,
				$currentUrl
			);

			if (
				$onlyInternal
				&&
				!System\Route::isInternal($href)
			)
			{
				continue;
			}

			if (
				Wb\contains($href, '#')
				&&
				$skipAnchors
			)
			{
				// skip URLs which are anchors, that is on the exact same page. Leave others.
				if (Wb\startsWith($href, $currentUrl))
				{
					continue;
				}
			}

			if ($normalize)
			{
				$href = System\Route::makeRootRelative(
					$href,
					$removeLeadingSlash
				);
				$href = $removeTrailingIndexPhp
					? Wb\rTrim($href, 'index.php')
					: $href;
			}

			if (!in_array($href, $links))
			{
				$links[] = $href;
			}
		}

		return $links;
	}

	/**
	 * Extract all href links from an html text buffer.
	 *
	 * @param string $buffer  HTML content to scan for links.
	 *
	 * @param array  $options Set of options:
	 *
	 *      bool    normalize                  If true, links are normalized to root-relative links.
	 *      bool    removeLeadingSlash         If true, leading slash of normalized URLs are removed.
	 *      bool    removeQuery                If true, query string are removed from extracted links
	 *      bool    onlyInternal               If true, only internal links are returned.
	 *      bool    stripAnchors               If true, anchors are removed from extractec links.
	 *      bool    skipRelative               If true, only fully qualified or root-relative links are extracted. The safest, enabled by default.
	 *                                              (but not index.php?)
	 *      string  currentUrl                 The current URL, needed to normalize relative URLs
	 *      array   queryVarsToStrip           An array of query var names that should be removed from collected URLs if present.
	 *
	 * @return array
	 * @throws \Exception
	 */
	static public function extractImages($buffer, $options = [])
	{
		$images = [];

		// save time
		if (empty($buffer))
		{
			return $images;
		}

		$normalize            = Wb\arrayGet($options, 'normalize', true);
		$removeLeadingSlash   = Wb\arrayGet($options, 'removeLeadingSlash', true);
		$removeQuery          = Wb\arrayGet($options, 'removeQuery', false);
		$onlyInternal         = Wb\arrayGet($options, 'onlyInternal', true);
		$stripAnchors         = Wb\arrayGet($options, 'stripAnchors', true);
		$skipDataImage        = Wb\arrayGet($options, 'skipDataImage', true);
		$skipRelative         = Wb\arrayGet($options, 'skipRelative', true);
		$currentUrl           = Wb\arrayGet($options, 'currentUrl', '/');
		$queryVarsToStrip     = Wb\arrayGet($options, 'queryVarsToStrip', []);
		$filter               = Wb\arrayGet($options, 'filter', '');
		$filterParams         = Wb\arrayGet($options, 'filterParams', null);
		$excludeUrls          = Wb\arrayGet($options, 'excludeUrls', []);
		$rawUrlDecode         = Wb\arrayGet($options, 'rawUrlDecode', false);
		$encodeSpaces         = Wb\arrayGet($options, 'encodeSpaces', true);
		$wrapContentInHtmlDoc = Wb\arrayGet($options, 'wrapContentInHtmlDoc', true);
		$dataAttrToReadFrom   = Wb\arrayGet($options, 'dataAttrToReadFrom', []);

		// Although not used by this method, the thorough parameter can/is used by
		// extensions and 3rd-party and should NOT be removed from the options array.
		$thorough = Wb\arrayGet($options, 'thorough', false);

		/**
		 * @var \DOMDocument $dom
		 */
		$dom = self::domFromContent($buffer, $wrapContentInHtmlDoc);
		/**
		 * @var \DOMNodeList
		 */
		$imgTags = $dom->getElementsByTagName('img');

		// if a filter is specified, we run it, potentially letting others
		// do the extraction process. Used for pages such as image galleries
		// where the desired image may not be rendered as img tags.
		// Handlers should return an array of images records to be used as is
		// (hence possibly empty) or null to indicate they have no opinion on the matter
		if (!empty($filter))
		{
			$filteredImages = Factory::get()->getThe('hook')->filter(
				$filter,
				null,
				$buffer,
				$dom,
				$imgTags,
				$options,
				$filterParams
			);

			if (null !== $filteredImages)
			{
				return $filteredImages;
			}
		}

		/**
		 * @var \DOMElement $imgTag
		 */
		foreach ($imgTags as $imgTag)
		{
			try
			{
				$href = StringHelper::trim(
					$imgTag->getAttribute('src')
				);

				if (!empty($dataAttrToReadFrom))
				{
					foreach ($dataAttrToReadFrom as $dataAttr)
					{
						$hrefOverride = StringHelper::trim(
							$imgTag->getAttribute($dataAttr)
						);
						if (!empty($hrefOverride))
						{
							if (
								!System\Route::isFullyQualified($hrefOverride)
								&&
								!Wb\startsWith($hrefOverride, '/')
							)
							{
								$hrefOverride = '/' . $hrefOverride;
							}

							$href = $hrefOverride;
							break;
						}
					}
				}

				$href = $rawUrlDecode
					? rawurldecode($href)
					: $href;

				$href = $encodeSpaces
					? preg_replace('/\s/u', '%20', $href)
					: $href;

				if (empty($href))
				{
					continue;
				}

				if (
					!empty($excludeUrls)
					&&
					Wb\contains(
						$href,
						$excludeUrls
					)
				)
				{
					continue;
				}

				if (
					$skipDataImage
					&&
					Wb\startsWith($href, 'data:image')
				)
				{
					continue;
				}

				if ($removeQuery)
				{
					$href = System\Route::trimQuery($href);
				}

				if (
					$skipRelative
					&&
					!Wb\startsWith($href, '/') // root relatives and protocol-relative are ok
					&&
					!Wb\startsWith($href, '#') // anchors are ok
					&&
					!System\Route::isFullyQualified($href)
				)
				{
					continue;
				}

				if (Wb\contains($href, '#'))
				{
					if ($stripAnchors)
					{
						$bits = explode('#', $href);
						$href = $bits[0];
					}

				}

				if (!empty($queryVarsToStrip))
				{
					$href = System\Route::removeQueryVarFromUrl(
						$href,
						$queryVarsToStrip
					);
				}

				if (
					!empty($href)
					&&
					!Wb\startsWith($href, '//')
				)
				{
					$href = System\Route::resolveRelativeUrl(
						$href,
						$currentUrl,
						true
					);
				}

				if (
					$onlyInternal
					&&
					!System\Route::isInternal($href)
				)
				{
					continue;
				}

				if ($normalize)
				{
					$href = System\Route::makeRootRelative(
						$href,
						$removeLeadingSlash,
						$currentUrl,
						true
					);
				}

				if (!array_key_exists($href, $images))
				{
					$dataAttributes = [];
					foreach ($imgTag->attributes as $attribute)
					{
						if (Wb\startsWith($attribute->nodeName, 'data-'))
						{
							$dataAttributes[Wb\lTrim($attribute->nodeName, 'data-')] = $attribute->nodeValue;
						}
					}

					$images[$href] = [
						'url'   => $href,
						'title' => $imgTag->getAttribute('title'),
						'alt'   => $imgTag->getAttribute('alt'),
						'data'  => $dataAttributes
					];

					$widthAttr = $imgTag->getAttribute('width');
					if ((int)$widthAttr === $widthAttr)
					{
						$images[$href]['el_width'] = $widthAttr;
					}

					$heightAttr = $imgTag->getAttribute('height');
					if ((int)$heightAttr === $heightAttr)
					{
						$images[$href]['el_height'] = $heightAttr;
					}


				}
			}
				// odd data in content can trigger exceptions when being parsed by some utilities.
				// Catch exception and just move on to next.
			catch (\Throwable $e)
			{
				System\Log::libraryDebug('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
			}
			catch (\Exception $e)
			{
				System\Log::libraryDebug('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
			}
		}

		return $images;
	}

	/**
	 * Extract meta tags by name from a string.
	 *
	 * @param string $buffer
	 * @param string $name
	 * @param array  $options
	 *                          bool $parsed If true, tag attributes are parsed and a key/value array is returned instead of a raw string
	 *
	 * @return array
	 */
	public static function extractMetaTags($buffer, $name = '', $options = [])
	{
		$parsed = Wb\arrayGet($options, 'parsed', true);
		$tags   = [];

		$dom      = self::domFromContent($buffer, false);
		$metaTags = $dom->getElementsByTagName('meta');
		if (empty($metaTags))
		{
			return $tags;
		}

		foreach ($metaTags as $tag)
		{
			if (!empty($name))
			{
				$metaName = $tag->getAttribute('name');
				if (
					empty($metaName)
					||
					$name != $metaName
				)
				{
					continue;
				}
			}

			if ($parsed)
			{
				$nodeValue = [
					'element'    => $tag->nodeValue,
					'attributes' => []

				];
				foreach ($tag->attributes as $attribute)
				{
					if (!array_key_exists($attribute->name, $nodeValue['attributes'])) // drop additional attributes if already seen
					{
						$nodeValue['attributes'][$attribute->name] = $attribute->value;
					}
				}
			}
			else
			{
				$nodeValue = $tag->nodeValue;
			}

			$tags[] = $nodeValue;
		}

		return $tags;
	}

	/**
	 * Search for the 1st video in an src attribute.
	 *
	 * @param string $content
	 * @param string $extension
	 *
	 * @return string|null
	 * @throws \Exception
	 */
	public static function extractVideo($content, $extension)
	{
		$url = '';

		if (
			empty($content)
			||
			strpos($content, '.' . $extension) === false
		)
		{
			return $url;
		}

		$urls = [];
		preg_match(
			'~src=["\'](.+\.' . $extension . ')["\']~iuUs',
			$content,
			$urls
		);

		if (!empty($urls) && !empty($urls[1]))
		{
			$url = System\Route::absolutify($urls[1], true);
		}

		return $url;
	}

	/**
	 * Build a DOMDocument from a string representing an HTML fragment.
	 *
	 * @param string $content     The HTML content.
	 *
	 * @param bool   $wrap        If true, content is wrapped inside a dummy HTML document before loading it into the
	 *                            DOMDocument object. Not needed if content is a full HTML doc.
	 *
	 * @return false|\DOMDocument
	 */
	public static function domFromContent($content, $wrap = true)
	{

		$libxml_previous_state = libxml_use_internal_errors(true);

		$dom = new \DOMDocument;
		// Wrap in dummy tags, since XML needs one parent node.
		// It also makes it easier to loop through nodes.
		// We can later use this to extract our nodes.
		// Add utf-8 charset so loadHTML does not have problems parsing it. See: http://php.net/manual/en/domdocument.loadhtml.php#78243
		$content = $wrap
			? '<html lang=""><head><title></title><meta http-equiv="content-type" content="text/html; charset=utf-8"></head><body>' . $content . '</body></html>'
			: $content;
		$result  = $dom->loadHTML($content);

		libxml_clear_errors();
		libxml_use_internal_errors($libxml_previous_state);

		if (!$result)
		{
			return false;
		}

		return $dom;
	}

	public static function cssSelectorToXPath($selector)
	{
		static $converter;

		if (is_null($converter))
		{
			$converter = new CssSelector\CssSelectorConverter();
		}

		return $converter->toXPath($selector);
	}
}
