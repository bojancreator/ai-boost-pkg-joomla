<?php
/**
 * Project:                 4SEF
 *
 * @author           Yannick Gaultier - Weeblr llc
 * @copyright        Copyright Weeblr llc - 2022 -2025
 * @package          4SEF
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          @build_version_full_build@
 * @date         2025-06-02
 */

namespace Weeblr\Wblib\Forsef\Html;

use Weeblr\Wblib\Forsef\Wb;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * HTML output helper.
 *
 */
class Helper
{
	private static $escapeAttrMapKeys = array(
		'`'
	);
	private static $escapeAttrMapValues = array(
		'&#x60;'
	);

	/**
	 * Escape a string before use as an HTML attribute.
	 *
	 * NB: added $flags but default to ENT_QUOTES instead of ENT_COMPAT
	 * for B/C
	 *
	 * @param   string  $string
	 * @param   int     $flags
	 * @param   string  $encoding
	 *
	 * @return string
	 */
	public static function escapeAttr($string, $flags = ENT_QUOTES, $encoding = 'UTF-8')
	{
		return self::escape(
			str_replace(
				self::$escapeAttrMapKeys,
				self::$escapeAttrMapValues,
				$string
			),
			$flags,
			$encoding
		);
	}

	/**
	 * Escape a string before display. Wrapper around htmlspecialchars.
	 *
	 * @param   string  $string
	 * @param   int     $flags
	 * @param   string  $encoding
	 *
	 * @return string
	 */
	public static function escape($string, $flags = ENT_COMPAT, $encoding = 'UTF-8')
	{
		return htmlspecialchars(
			$string,
			$flags,
			$encoding
		);
	}

	/**
	 * Expand an associative array into an html string of attributes
	 *
	 * @param   array  $attributes
	 *
	 * @return string
	 */
	public static function attrToHtml($attributes)
	{
		$output = '';
		if (!is_array($attributes))
		{
			return $output;
		}

		foreach ($attributes as $key => $value)
		{
			$output .= ' ' . $key . '="' . self::escapeAttr($value) . '"';
		}

		return $output;
	}

	/**
	 * Wraps a list of items in an unordered list
	 *
	 * @param   array   $items  list of strings
	 * @param   string  $ulClass
	 * @param   string  $liClass
	 *
	 * @return string
	 */
	public static function makeList($items, $ulClass = '', $liClass = '')
	{
		if (!empty($ulClass))
		{
			$ulClass = self::attrToHtml(array('class' => $ulClass));
		}
		if (!empty($liClass))
		{
			$liClass = self::attrToHtml(array('class' => $liClass));
		}
		$items  = is_array($items) ? $items : (array) $items;
		$output = "<ul{$ulClass}><li{$liClass}>" . implode("</li><li{$liClass}>", $items) . '</li></ul>';

		return $output;
	}

	/**
	 * Builds an html tag.
	 *
	 * @param   string   $tag
	 * @param   array    $attributes
	 * @param   string   $content
	 * @param   false[]  $options
	 *
	 * @return string
	 */
	public function makeTag($tag, $attributes, $content = '', $options = ['close' => false, 'escapeAttr' => true, 'escapeAttrFlags' => ENT_QUOTES])
	{
		$renderedTag     = '<' . $tag;
		$escapeAttr      = Wb\arrayIsTruthy($options, 'escapeAttr', true);
		$escapeAttrFlags = Wb\arrayGet($options, 'escapeAttrFlags', ENT_QUOTES);
		$attrs           = [];
		foreach ($attributes as $name => $value)
		{
			$attrs[] = $name . '="' . ($escapeAttr ? self::escapeAttr($value, $escapeAttrFlags) : $value) . '"';
		}
		$renderedTag .= ' ' . implode(' ', $attrs);
		$shouldClose = Wb\arrayGet($options, 'close', false);
		if (empty($content) && !$shouldClose)
		{
			$renderedTag .= '>';
		}
		else if (empty($content))
		{
			$renderedTag .= '/>';
		}
		else
		{
			$renderedTag .= '>' . $content . '</' . $tag . '>';
		}

		return $renderedTag;
	}

	/**
	 * Returns and optionally echo a block of HTML, surrounded by comments
	 * built with provided title.
	 *
	 * @param   string  $html
	 * @param   string  $title
	 * @param   bool    $echo
	 *
	 * @return string
	 */
	public static function printHtmlBlock($html, $title, $echo = false)
	{
		$printedBlock = "\t" . '<!-- ' . $title . ' -->';
		$printedBlock .= "\n" . $html;
		$printedBlock .= "\t" . '<!-- ' . $title . ' -->' . "\n";

		if ($echo)
		{
			echo $printedBlock;
		}

		return $printedBlock;
	}

	/**
	 * Returns and optionally echo a block of script, surrounded by comments
	 * built with provided title.
	 *
	 * @param   string  $script
	 * @param   string  $title
	 * @param   bool    $echo
	 *
	 * @return string
	 */
	public static function printScriptBlock(string $script, string $title, $echo = false)
	{
		$printedBlock = "\n" . '/* ' . $title . ' */';
		$printedBlock .= "\n" . $script;
		$printedBlock .= "\n" . '/* ' . $title . ' */' . "\n";

		if ($echo)
		{
			echo $printedBlock;
		}

		return $printedBlock;
	}
}
