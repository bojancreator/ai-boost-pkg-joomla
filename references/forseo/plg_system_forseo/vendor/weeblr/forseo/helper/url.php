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

namespace Weeblr\Forseo\Helper;

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Url extends Base\Base
{
	/**
	 * Max index: 191. MD5 = 32, Separator =1, Safety = 1, Cutoff = 191-32-1-1 = 157.
	 *
	 * @var int Character count at which we hash the remainder of URL.
	 */
	private $cutoffLength = 157;

	/**
	 * Computes a possibly shortened version of a URL to be stored
	 * in a database field and indexed.
	 *
	 * @param string $url Original, full length URL
	 *
	 * @return string
	 */
	public function storageSafe($url)
	{
		$urlLength = StringHelper::strlen($url);
		if ($urlLength <= $this->cutoffLength)
		{
			// short enough, nothing to do
			return $url;
		}

		// split at cutoff point
		$main      = StringHelper::substr($url, 0, $this->cutoffLength);
		$remainder = StringHelper::substr($url, $this->cutoffLength);

		return $main . '_' . strtolower(md5($remainder));
	}

	/**
	 * Compare 2 or more URLs and decide whether they are identical.
	 *
	 * @param array $urls
	 *
	 * @return bool
	 */
	public function areSameUrl($urls = [])
	{
		if (empty($urls) || count($urls) == 1)
		{
			return true;
		}

		$previousUrl = null;
		foreach ($urls as $url)
		{
			$absUrl = System\Route::absolutify(
				$url,
				true
			);

			if (
				!is_null($previousUrl)
				&&
				$absUrl !== $previousUrl)
			{
				return false;
			}

			$previousUrl = $absUrl;
		}

		return true;
	}

	/**
	 * Apply a list of exclusion rules to a URL. Rules can have wildcard caracters.
	 * Exclusion rules are applied first, then inclusion rules may "bring back" an excluded
	 * link.
	 *
	 * @param string $link
	 * @param array  $exclusionRules
	 * @param array  $inclusionRules
	 *
	 * @return bool
	 */
	public function passExclusionRules($link, $exclusionRules = [], $inclusionRules = [])
	{
		$pass = true;
		foreach ($exclusionRules as $rule)
		{
			if (System\Route::matchUrlRule(
				Wb\lTrim($rule, '/'),
				Wb\lTrim($link, '/'),
				'{*}', // $wildChar
				'{?}'  // $singleChar
			))
			{
				$pass = false;
				break;
			}
		}

		if ($pass)
		{
			return true;
		}

		// if an exclusion rule was triggered, check if the inclusion rules
		// bring back the URL.
		foreach ($inclusionRules as $rule)
		{
			if (System\Route::matchUrlRule(
				Wb\lTrim($rule, '/'),
				Wb\lTrim($link, '/'),
				'{*}', // $wildChar
				'{?}'  // $singleChar
			))
			{
				$pass = true;
				break;
			}
		}

		return $pass;
	}
}