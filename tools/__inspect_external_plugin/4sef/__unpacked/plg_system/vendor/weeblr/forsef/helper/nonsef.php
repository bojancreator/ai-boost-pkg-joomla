<?php
/**
 * Project: 4SEF
 *
 * @package          4SEF
 * @copyright        Copyright Weeblr llc - 2022 -2025
 * @author           Yannick Gaultier - Weeblr llc
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          2.6.2.644
 * @date        2025-06-02
 */

namespace Weeblr\Forsef\Helper;

use Joomla\CMS\Uri;

use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Joomla\StringHelper\StringHelper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Nonsef extends Base\Base
{
	/**
	 * Trim com_ prefix from a component name.
	 * @param string $option
	 * @return mixed
	 */
	public function optionToExtension($option)
	{
		return Wb\ltrim(
			strtolower($option ?? '')
			, 'com_'
		);
	}

	/**
	 * Adjust the query part of a URI to a normalized version.
	 *
	 * @param Uri\Uri $uri
	 *
	 * @return Uri\Uri
	 */
	public function normalizeUri($uri)
	{
		if ($this->platform->majorVersion() < 4)
		{
			$this->parseItemidOnlyNonSef($uri);
		}

		return $this->normalizeUriLanguageVars(
			$uri
		);
	}

	/**
	 * Adjust the language query variable to a standard format.
	 *
	 * @param Uri\Uri $uri
	 *
	 * @return Uri\Uri
	 */
	public function normalizeUriLanguageVars($uri)
	{
		$languageSpec = $this->getLanguageFromUri(
			$uri,
			false // $fallbackToDefault
		);

		if (empty($languageSpec))
		{
			return $uri;
		}

		if ($languageSpec['tag'] !== $this->platform->getDefaultLanguageTag())
		{
			// normalize to always using URL lang code, sometimes we get a full language Tag
			$uri->setVar(
				'lang',
				$languageSpec['sef']
			);
		}
		else if ($languageSpec['tag'] === $this->platform->getCurrentLanguageTag())
		{
			// remove language var, but only if it's the current language
			$uri->delVar('lang');
		}

		return $uri;
	}

	/**
	 * Extract language tag and URL code from a URI, applying appropriate
	 * default values when missing.
	 *
	 * @param Uri\Uri $uri
	 * @param bool    $fallbackToDefault
	 * @return array
	 */
	public function getLanguageFromUri($uri, $fallbackToDefault = true)
	{
		$lang = $this->platform->getLanguageTagFromUrlCode(
			$uri->getVar('lang')
		);

		if (
			empty($lang)
			&&
			!$fallbackToDefault
		)
		{
			return;
		}

		$tag = empty($lang)
			? $this->platform->getCurrentLanguageTag()
			: $lang;

		return [
			'tag' => $tag,
			'sef' => $this->platform->getLanguageUrlCode(
				$tag
			)
		];
	}

	/**
	 * Retrieve the full set of query vars for non-sef urls defined using
	 * only a menu item id (and optionally a language code).
	 *
	 * @param Uri\Uri $uri
	 *
	 * @return Nonsef
	 */
	public function parseItemidOnlyNonSef($uri)
	{
		static $menu;

		$vars = $uri->getQuery(true);

		// Make sure any menu vars are used if no others are specified
		// Extracted from Joomla 4 router code.
		if (
			isset($vars['Itemid'])
			&&
			(
				\count($vars) === 2
				||
				(
					\count($vars) === 3
					&&
					isset($vars['lang'])
				)
			)
		)
		{
			if (is_null($menu))
			{
				$menu = $this->platform->getMenu();
			}

			$menuItem     = $menu->getItem($vars['Itemid']);
			$menuItemVars = empty($menuItem) || empty($menuItem->query)
				? []
				: $menuItem->query;
			$vars         = array_merge(
				$menuItemVars,
				$vars
			);
		}

		$uri->setQuery($vars);

		return $this;
	}

	/**
	 * Builds a normalized set of non-sef variables, in preparation for building a
	 * normalized non-sef URLs.
	 *
	 * @param array $vars
	 * @return array
	 */
	public function buildNormalizedNonSefVars($vars)
	{
		if (empty($vars))
		{
			return $vars;
		}

		$originalVars = $vars;

		$vars = $this->stripSlugs($vars);
		$vars = $this->stripPrintVars($vars);
		$vars = $this->stripCommonTrackingVars($vars);

		$vars = $this->factory->getA(Plugins::class)
							  ->getPlugin(
								  Wb\arrayGet($vars, 'option')
							  )->buildNormalizedNonSef($vars);

		$vars = $this->sortVars($vars);

		/**
		 * Filters the list of non-sef variables associated with a SEF URL. Some global and plugin-based
		 * modifications to the variables list has already been done, but the full set of original variables
		 * are provided as a reference, as some may need to be put back in under some circumstances.
		 *
		 * @api     forsef
		 * @package 4SEF\filter\build
		 * @var forsef_normalize_non_sef
		 * @since   1.0.0
		 *
		 * @param array $vars
		 * @param array $originalVars
		 *
		 * @return void
		 *
		 */
		return $this->factory->getThe('hook')->filter(
			'forsef_normalize_non_sef',
			$vars,
			$originalVars
		);
	}

	/**
	 * Builds a normalized non-sef URL based on an incoming set of already normalized non-sef variables.
	 *
	 * @param array $vars
	 * @return string
	 */
	public function buildNormalizedNonSefFromVars($vars)
	{
		if (empty($vars))
		{
			return '';
		}

		return implode(
			'?',
			[
				'index.php',
				rawurldecode(
					http_build_query(
						$vars,
						'',
						'&',
						PHP_QUERY_RFC3986
					)
				)
			]
		);
	}

	/**
	 * Builds a normalized non-sef URL based on an incoming URI. Query vars values are URL-encoded.
	 *
	 * @param array $vars
	 * @return string
	 */
	public function buildNormalizedNonSef($vars)
	{
		if (empty($vars))
		{
			return '';
		}

		return $this->buildNormalizedNonSefFromVars(
			$this->buildNormalizedNonSefVars(
				$vars
			)
		);
	}

	/**
	 * Inject default language if none.
	 *
	 * @param array $vars
	 * @return array
	 */
	public function injectLanguage($vars)
	{
		static $defaultLanguageCode;

		if ($this->platform->majorVersion() > 3)
		{
			// J4+ router does that already
			return $vars;
		}

		if (!isset($vars['lang']))
		{
			if (is_null($defaultLanguageCode))
			{
				$defaultLanguageCode = $this->platform->getLanguageUrlCode(
					$this->platform->getDefaultLanguageTag()
				);
			}
			$vars['lang'] = $defaultLanguageCode;
		}

		return $vars;
	}

	/**
	 * Alpha-sort query variables, with the exception of option always
	 * being put in first place for legacy reasons.
	 *
	 * @param array $vars
	 * @return array
	 */
	public function sortVars($vars)
	{
		$option = Wb\arrayGet($vars, 'option');
		unset($vars['option']);
		ksort($vars);
		if (!empty($option))
		{
			$vars = ['option' => $option] + $vars;
		}

		return $vars;
	}

	/**
	 * Strip pagination related vars, as part of the normalization process.
	 *
	 * @param array $vars
	 * @return array
	 */
	public function stripPaginationVars($vars)
	{
		if (
			'com_content' === Wb\arrayGet($vars, 'option')
			&&
			'article' === Wb\arrayGet($vars, 'view')
		)
		{
			return $vars;
		}

		return array_diff_key(
			$vars,
			array_flip(
				[
					'limitstart',
					'start',
					'limit'
				]
			)
		);
	}

	/**
	 * Strip slugs from Joomla-generated incoming non-sef query, eg 3:article-alias.
	 *
	 * @param array $vars
	 * @return array
	 */
	public function stripSlugs($vars)
	{
		return array_map(
			function ($item) {
				if (!is_string($item))
				{
					return $item;
				}
				$value   = urldecode($item);
				$matched = preg_match('~^([0-9]+):.*~', $value, $matches);
				if ($matched)
				{
					$item = $matches[1];
				}
				return $item;
			},
			$vars
		);
	}

	/**
	 * Strip pagination related vars, as part of the normalization process.
	 *
	 * @param array $vars
	 * @return array
	 */
	public function stripFeedVars($vars)
	{
		$format = Wb\arrayGet($vars, 'format');
		if ('feed' !== $format)
		{
			return $vars;
		}

		return array_diff_key(
			$vars,
			array_flip(
				[
					'type',
					'format'
				]
			)
		);
	}

	/**
	 * Strip print related vars, as part of the normalization process.
	 *
	 * @param array $vars
	 * @return array
	 */
	public function stripPrintVars($vars)
	{
		if (Wb\arrayisFalsy($vars, 'print'))
		{
			return $vars;
		}

		return array_diff_key(
			$vars,
			array_flip(
				[
					'print',
					'layout',
					'tmpl'
				]
			)
		);
	}

	/**
	 * Strip common tracking vars from non-sef, as part of the normalization process.
	 *
	 * @param array $vars
	 * @return array
	 */
	public function stripCommonTrackingVars($vars)
	{
		static $varsToStrip;

		if (is_null($varsToStrip))
		{
			$appConfig   = $this->factory->getThis('forsef.config', 'app');
			$varsToStrip = array_flip(
				array_merge(
					$appConfig->get('commonTrackingVars'),
					$appConfig->get('queryVarsToStrip')
				)
			);
		}

		return array_diff_key(
			$vars,
			$varsToStrip
		);
	}
}
