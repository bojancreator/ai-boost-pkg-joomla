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

use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Joomla\StringHelper\StringHelper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Dynamicsegments extends Base\Base
{
	/**
	 * Returns an array with the following parts:
	 *
	 * - vars => array of pagination variables, if any
	 * - suffix => the optional page suffix, typically .html
	 * - appended_segment => everything that was added dynamically to base path: feed format, print suffix
	 * - extra_path =>  extra_path is everything that was added to base path: pagination + suffix
	 * - base_path: the remaining path after the extra path has been removed.
	 *
	 * NB: extra_path and appended_segment are mutually exclusive
	 *
	 * NB: the base_path KEEPS its trailing slash, as we have no way to know if one is needed (category) or not (single article). This will have to be sorted
	 * out by the calling party.
	 *
	 * IMPORTANT: initially, the parsing result in $suffixDetails[$sef . $languageTag] was memoized with early returns if already checked. That works usually but will fail
	 * under some unusual but possible configurations, such as multiple content languages enabled on monolingual sites. So caching removed for now.
	 *
	 * @param string $sef
	 * @param string $languageTag
	 *
	 * @return array
	 */
	public function parse($sef, $languageTag = '')
	{
		static $suffixDetails = [];

		$routingConfig      = $this->factory->getThis('forsef.config', 'routing');
		$paginationConfig   = $this->factory->getThis('forsef.config', 'pagination');
		$extensionsConfig   = $this->factory->getThis('forsef.config', 'extensions');
		$showAllSlugPattern = $this->factory
			->getA(Sef::class)
			->conformSegment(
				$extensionsConfig->get('showallSlug')
			);

		$paginationSlugsPatterns = $paginationConfig->get('slugsPatterns', []);
		$paginationSpacer        = $paginationConfig->get('spacer');
		if ($routingConfig->isTruthy('lowerCase', true))
		{
			$paginationSpacer = StringHelper::strtolower($paginationSpacer);
			foreach ($paginationSlugsPatterns as $key => $value)
			{
				$paginationSlugsPatterns[$key] = StringHelper::strtolower($value);
			}
		}

		$languageList = empty($languageTag)
			? self::getLanguagesList()
			: [$languageTag];

		if (empty($languageList))
		{
			$suffixDetails[$sef . $languageTag] = [
				'vars'             => [],
				'suffix'           => '',
				'extra_path'       => '',
				'appended_segment' => '',
				'base_path'        => $sef
			];

			return $suffixDetails[$sef . $languageTag];
		}

		$suffix = $routingConfig->get('suffix');

		// extract showall
		if (!empty($suffix))
		{
			$showAllSlugPattern .= '(' . preg_quote($suffix) . ')?';
		}

		// build pattern with that
		$pattern = '~/' . $showAllSlugPattern . '$~';
		preg_match(
			$pattern,
			$sef,
			$matches
		);

		if (!empty($matches))
		{
			$suffixDetails[$sef . $languageTag]['appended_segment'] = '';
			$suffixDetails[$sef . $languageTag]['suffix']           = $suffix ?? '';
			$suffixDetails[$sef . $languageTag]['extra_path']       = $showAllSlugPattern;

			$suffixDetails[$sef . $languageTag]['base_path'] = Wb\rTrim(
				$sef,
				Wb\lTrim($matches[0], '/')
			);

			$suffixDetails[$sef . $languageTag];
		}

		foreach ($languageList as $possibleLanguageTag)
		{
			$languageTag = $possibleLanguageTag;

			$suffixDetails[$sef . $languageTag] = [
				'vars'             => [],
				'suffix'           => '',
				'extra_path'       => '',
				'appended_segment' => '',
				'base_path'        => $sef
			];

			// Extract pagination.
			$paginationSlugsPattern = empty($paginationSlugsPatterns[$languageTag])
				? 'Page-%s'
				: $paginationSlugsPatterns[$languageTag];

			$slug = '(/' . str_replace(
					'%s',
					'[0-9]+)?',
					preg_quote(
						$paginationSlugsPattern
					)
				);

			// optional number of items per page
			$slug .= '(' . preg_quote($paginationSpacer) . '[0-9]+)?';

			if (!empty($suffix))
			{
				$slug .= '(' . preg_quote($suffix) . ')?';
			}

			// build pattern with that
			$pattern = '~' . $slug . '$~';

			preg_match(
				$pattern,
				$sef,
				$matches
			);

			if (
				!empty($matches)
				&&
				!empty($matches[0])
				&&
				(
					empty($matches[2])  // limit is empty
					||
					!empty($matches[1]) // or if limit is not empty, we must also have limitstart
				)
			)
			{
				$suffixDetails[$sef . $languageTag]['appended_segment'] = '';
				$suffixDetails[$sef . $languageTag]['suffix']           = $matches[3] ?? '';
				$suffixDetails[$sef . $languageTag]['extra_path']       = Wb\lTrim($matches[0], '/');

				$suffixDetails[$sef . $languageTag]['base_path'] = Wb\rTrim(
					$sef,
					Wb\lTrim($matches[0], '/')
				);

				break;
			}

		}

		// Feeds: tolerate missing trailing slash
		$pattern = '~/?feed/(rss|atom)(/)?$~';

		preg_match(
			$pattern,
			$sef,
			$matches
		);

		if (
			!empty($matches)
			&&
			!empty($matches[0])
		)
		{
			$feedType = $matches[1];
			if (!empty($feedType))
			{
				$suffixDetails[$sef . $languageTag]['appended_segment'] = 'feed/' . $feedType . (empty($matches[2]) ? '' : '/');
				$suffixDetails[$sef . $languageTag]['extra_path']       = '';
				$suffixDetails[$sef . $languageTag]['base_path']        = Wb\rTrim(
					$sef,
					$suffixDetails[$sef . $languageTag]['appended_segment']
				);
				$suffixDetails[$sef . $languageTag]['vars']['format']   = 'feed';
				$suffixDetails[$sef . $languageTag]['vars']['type']     = $feedType;
			}
		}

		// Print items
		if (
			$this->platform->majorVersion() < 4
			&&
			Wb\endsWith($sef, 'print'))
		{
			$suffixDetails[$sef . $languageTag]['appended_segment'] = '/print';
			$suffixDetails[$sef . $languageTag]['extra_path']       = '';
			$suffixDetails[$sef . $languageTag]['base_path']        = Wb\rTrim(
				$sef,
				$suffixDetails[$sef . $languageTag]['appended_segment']
			);
			$suffixDetails[$sef . $languageTag]['vars']['print']    = 1;
		}

		return $suffixDetails[$sef . $languageTag];
	}

	/**
	 * Build a list of languages on the site, preferring content languages
	 * and falling back to installed languages if none found.
	 *
	 * @return array
	 */
	private function getLanguagesList()
	{
		static $languagesList;

		if (is_null($languagesList))
		{
			$key               = 'lang_code';
			$frontendLanguages = $this->platform->getFrontendLanguages();
			if (empty($frontendLanguages))
			{
				$frontendLanguages = $this->platform->getInstalledLanguages();
				$key               = 'sef';
			}

			$frontendLanguages = empty($frontendLanguages)
				? []
				: $frontendLanguages;

			$languagesList = array_map(
				function ($language) use ($key) {
					return $language->{$key};
				},
				$frontendLanguages
			);
		}

		return $languagesList;
	}
}