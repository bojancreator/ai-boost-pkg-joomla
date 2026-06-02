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

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Joomla\Uri;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;

use Weeblr\Forseo\Data;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Rules extends Base\Base
{
	/**
	 * If there are less placeholders in alias target than in alias source, in which direction
	 * replacement should take place?
	 */
	public const REPLACEMENT_DIRECTION_END_TO_START = 0;
	public const REPLACEMENT_DIRECTION_START_TO_END = 1;

	/**
	 * @param int $type Compute text name of a rule type.
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function ruleNameFromType($type)
	{
		switch ($type)
		{
			case Data\Rule::TYPE_REPLACER:
				return 'replacer';
			case Data\Rule::TYPE_REDIRECT:
				return 'redirect';
			case Data\Rule::TYPE_META:
				return 'meta';
			case Data\Rule::TYPE_WAF:
				return 'waf';
			case Data\Rule::TYPE_RAW_CONTENT:
				return 'rawcontent';
			// Not implemented
			case Data\Rule::TYPE_ROBOTS:
				return 'robots';
			case Data\Rule::TYPE_SOCIAL:
				return 'social';
			case Data\Rule::TYPE_ERROR_PAGE:
				return 'error_page';
			default:
				$message = __METHOD__ . ': Invalid rule type: ' . $type;
				$this->factory->getThe('forseo.logger')->error($message);
				throw new \Exception($message, 500);
		}
	}

	/**
	 * Find the target URL based on current request and a rule specification.
	 *
	 * @param array  $ruleSpec
	 * @param string $currentUrl
	 * @param string $targetPropertyName
	 * @param array  $options
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function buildTarget(array $ruleSpec, string $currentUrl, string $targetPropertyName, array $options = ['targetProperty' => 'actionRedirectTarget', 'wildChar' => '{*}', 'singleChar' => '{?}', 'regexpChar' => '~', 'targetTypeProperty' => 'actionRedirectType'])
	{
		if (empty($targetPropertyName))
		{
			throw new \Exception('No targetPropertyName passed to buildTarget rules helper.');
		}

		$targetTypeProperty = Wb\arrayGet(
			$options,
			'targetTypeProperty',
			'actionRedirectType'
		);

		$targetType = Wb\arrayGet(
			$ruleSpec,
			$targetTypeProperty,
			''
		);

		$target = Wb\arrayGet(
			$ruleSpec,
			$targetPropertyName,
			''
		);

		$uri = $this->factory->getA(
			Uri\Uri::class,
			$currentUrl
		);

		if (Data\Rule::REDIRECT_TYPE_TO_SEF === $targetType)
		{
			$incomingNonSef = System\Route::makeRootRelative(
				$currentUrl,
				true // removeLeadingSlash
			);

			if (!$this->canRedirectNonSef($incomingNonSef))
			{
				return $currentUrl;
			}

			// build the sef
			$target = $this->platform->relativeRoute(
				$incomingNonSef,
				false // $xhtml
			);
		}

		if (
			// not a redirect to SEF
			Data\Rule::REDIRECT_TYPE_TO_SEF !== $targetType
			&&
			(
				// a simple redirect with wildcards
				Wb\contains($target, [Wb\arrayGet($options, 'wildChar'), Wb\arrayGet($options, 'singleChar')])
				||
				// target has numbered placeholders
				!empty(preg_match('~\{\$[0-9]+\}~', $target))
				||
				// a regexp
				Wb\startsWith(Wb\arrayGet($ruleSpec, 'urlSpec'), '~')
			)
		) {
			// target expansion needed
			$target = $this->expandUrl(
				$ruleSpec,
				$target,
				$uri,
				$currentUrl,
				$options
			);
		}

		$absolutify = Wb\arrayGet($options, 'absolutify', true);
		$target     = System\Route::makeRootRelative($target);

		if (Data\Rule::REDIRECT_TYPE_TO_SEF !== $targetType)
		{
			$target = $this->reappendQuery(
				$ruleSpec,
				$target,
				$uri
			);
		}

		return $absolutify
			? System\Route::absolutify($target, true)
			: $target;
	}

	/**
	 * Expand a URL spec that may contain wildchar chars into an actual URL.
	 *
	 * @param array  $ruleSpec
	 * @param string $expandableTarget
	 * @param URI    $uri
	 * @param string $currentUrl
	 * @param array  $options
	 *
	 * @return string
	 */
	private function expandUrl(array $ruleSpec, string $expandableTarget, $uri, string $currentUrl, array $options)
	{
		$disregardQueryString = Wb\arrayGet(
			$ruleSpec,
			'disregardQuery',
			true
		);

		$requestedpath         = System\Route::makeRootRelative(
			$uri->getPath()
		);
		$requestedPathAndQuery = System\Route::makeRootRelative(
			$uri->toString(
				['path', 'query']
			)
		);

		$usablePath = $disregardQueryString
			? $requestedpath
			: $requestedPathAndQuery;

		$matches = System\Route::findUrlRuleMatch(
			Wb\arrayGet($ruleSpec, 'urlSpec'),
			$usablePath,
			Wb\arrayGet($options, 'wildChar'),
			Wb\arrayGet($options, 'singleChar'),
			Wb\arrayGet($options, 'regexpChar')
		);

		$expandedTarget = $this->buildExpandedTarget(
			$ruleSpec,
			$expandableTarget,
			$usablePath,
			$matches,
			$options
		);

		return Wb\lTrim($expandedTarget, '//');
	}

	/**
	 * Reappend the incoming query string through a redirect, if configured to do so.
	 *
	 * @param array  $ruleSpec
	 * @param string $target
	 * @param URI    $uri
	 * @return string
	 */
	private function reappendQuery($ruleSpec, $target, $uri)
	{
		$disregardQueryString = Wb\arrayGet(
			$ruleSpec,
			'disregardQuery',
			true
		);

		$reappendQuery = Wb\arrayGet(
			$ruleSpec,
			'actionReappendQuery',
			false
		);

		if (
			$disregardQueryString
			&&
			$reappendQuery
		) {
			$queryString = $uri->getQuery();
			$target      = System\Route::appendQuery(
				$target,
				$queryString
			);
		}

		return $target;
	}

	/**
	 * Expand the alias record newurl that can have wildcard into its final form.
	 *
	 * sample-{*}-more-{*}-end -> sample-{*}-new-{*}-end
	 *
	 * @param array  $ruleSpec
	 * @param string $expandableTarget
	 * @param string $currentUrl
	 * @param array  $matches Result of matching the incoming URL with the rule
	 *
	 * @param array  $options
	 *
	 * @return string
	 */
	private function buildExpandedTarget($ruleSpec, $expandableTarget, $currentUrl, $matches, $options)
	{
		$urlSpec = Wb\arrayGet(
			$ruleSpec,
			'urlSpec'
		);

		if (Wb\startsWith($urlSpec, '~'))
		{
			// a full regexp
			return preg_replace(
				$urlSpec,
				$expandableTarget,
				$currentUrl
			);
		}

		/**
		 * Filter replacement direction of wildcard characters if there are less placeholders in expansion target than in expansion source.
		 *
		 * @api
		 * @package 4SEO\filter\routing
		 * @var forseo_rule_expansion_replacement_direction
		 * @since   1.0.0
		 *
		 * @param string $replacementDirection The direction to use when doing wildcard replacement.
		 * @param array  $ruleSpec             The rule definition that triggered the expansion.
		 * @param array  $matches              Result of running the rule urlSpecifiction against the current requested URL.
		 *
		 * @return int
		 */
		$replacementDirection = $this->factory->getThe('hook')->filter(
			'forseo_rule_expansion_replacement_direction',
			self::REPLACEMENT_DIRECTION_END_TO_START,
			$ruleSpec,
			$matches
		);

		return $this->replacePlaceholders(
			$expandableTarget,
			$matches,
			$replacementDirection
		);
	}

	/**
	 * Replaces placeholder in a target URL string for redirects, canonical with values
	 * taken from matching the URL of the current request.
	 *
	 * @param string $expandableTarget
	 * @param array  $matches
	 * @param int    $replacementDirection
	 *
	 * @return string
	 */
	private function replacePlaceholders(string $expandableTarget, array $matches, int $replacementDirection)
	{
		$expandedTarget = $expandableTarget;

		// using a regular expression, targets are {$1}, {$2}
		$hasNumericPlaceHolders = preg_match('~\{\$[0-9]+\}~', $expandableTarget);

		// if the incoming request URL has some matches
		// we can inject them in the redirect target
		if (count($matches) > 1)
		{
			switch (true)
			{
				case $hasNumericPlaceHolders:
					// replacement is based on numeric placeholders, {$1}, {$2},etc
					array_shift($matches);
					if (!empty($matches))
					{
						foreach ($matches as $index => $match)
						{
							$expandedTarget = str_replace(
								'{$' . ($index + 1) . '}',
								$match,
								$expandedTarget
							);
						}
					}
					break;
				case self::REPLACEMENT_DIRECTION_START_TO_END == $replacementDirection:
					// inject back the matching elements in the same order
					// until none is available
					array_shift($matches);
					if (!empty($matches))
					{
						$expandedTarget = preg_replace_callback(
							'~\{([?|*])\}~',
							function ($targetMatches) use (&$matches)
							{

								return empty($matches) ? '' : array_shift($matches);
							},
							$expandedTarget
						);
					}
					break;
				default:
					// inject back matching elements, in reverse order, starting from the end of the URL.
					$expandedTarget = StringHelper::strrev($expandedTarget);
					$matches        = array_reverse($matches);
					$expandedTarget = preg_replace_callback(
						'~\}([?|*])\{~',
						function ($targetMatches) use (&$matches)
						{

							$value = empty($matches) ? '' : array_shift($matches);

							$value = StringHelper::strrev($value);

							return $value;
						},
						$expandedTarget
					);

					$expandedTarget = StringHelper::strrev($expandedTarget);
					break;
			}
		}

		return $expandedTarget;
	}

	/**
	 * Inject raw HTML content into various parts of an HTML document.
	 *
	 * @param string $id
	 * @param string $body
	 * @param string $toInject
	 * @param string $location
	 * @param bool   $wrap
	 *
	 * @return mixed
	 */
	public function injectRawContent(string $id, string $body, string $toInject, string $location, $wrap = true)
	{
		switch ($location)
		{
			case Data\Rule::RAW_CONTENT_LOCATION_HEAD_TOP:
				$snippet = $wrap
					? "\n<!-- 4SEO: rule #" . $id . " -->\n" . $toInject . "\n<!-- /4SEO -->\n"
					: "\n" . $toInject . "\n";
				$body    = System\Strings::pregTagInBuffer($body, '~(<\s*head[^>]*>)~', $snippet, ['isRegExp' => true, 'firstOnly' => true, 'where' => 'after']);
				break;
			case Data\Rule::RAW_CONTENT_LOCATION_HEAD_BOTTOM:
				$snippet = $wrap
					? "\n<!-- 4SEO: rule #" . $id . " -->\n" . $toInject . "\n<!-- /4SEO -->\n"
					: "\n" . $toInject . "\n";
				$body    = System\Strings::tagInBuffer($body, '</head>', $snippet, ['firstOnly' => true, 'where' => 'before']);
				break;
			case Data\Rule::RAW_CONTENT_LOCATION_BODY_TOP:
				$snippet = $wrap
					? "\n<!-- 4SEO: rule #" . $id . " -->\n" . $toInject . "\n<!-- /4SEO -->\n"
					: "\n" . $toInject . "\n";
				$body    = System\Strings::pregTagInBuffer($body, '~(<\s*body[^>]*>)~', $snippet, ['isRegExp' => true, 'firstOnly' => true, 'where' => 'after']);
				break;
			case Data\Rule::RAW_CONTENT_LOCATION_BODY_BOTTOM:
				$snippet = $wrap
					? "\n<!-- 4SEO: rule #" . $id . " -->\n" . $toInject . "\n<!-- /4SEO -->\n"
					: "\n" . $toInject . "\n";
				$body    = System\Strings::tagInBuffer($body, '</body>', $snippet, ['lastOnly' => true, 'where' => 'before']);
				break;
		}

		return $body;
	}

	/**
	 * Build a replacement strings to be used in preg_replace
	 *
	 * @param array     $ruleSpec
	 * @param bool      $isRegExp
	 * @param Data\Rule $rule
	 *
	 * @return string
	 */
	public function buildReplacementTarget(array $ruleSpec, bool $isRegExp, $rule)
	{
		$replaceWith = Wb\arrayGet($ruleSpec, 'actionReplacerTarget', '');
		$replaceType = Wb\arrayGet($ruleSpec, 'actionReplacerType', Data\Rule::REPLACE_TYPE_TEXT);
		if (
			empty($replaceType)
			||
			Data\Rule::REPLACE_TYPE_TEXT == $replaceType
		) {
			return $replaceWith;
		}

		// build a link
		$noFollow    = Wb\arrayGet($ruleSpec, 'actionReplacerNoFollow', false);
		$targetBlank = Wb\arrayGet($ruleSpec, 'actionReplacerTargetBlank', false);

		$rel = [];
		if ($noFollow)
		{
			$rel[] = 'nofollow';
		}
		if ($targetBlank)
		{
			$rel[] = 'noopener';
		}

		return '<a href="' . htmlspecialchars($replaceWith, ENT_COMPAT, 'UTF-8') . '"'
			   . (!empty($rel) ? ' rel="' . implode(' ', $rel) . '"' : '')
			   . ($targetBlank ? ' target="_blank"' : '')
			   . ' class="4seo_replacer 4seo_rule_' . $rule->getId() . '">$1</a>';
	}

	/**
	 * Apply sanity check to a requested non-SEF URL before possibly redirecting it.
	 *
	 * @param string $incomingUrl
	 *
	 * @return bool
	 */
	private function canRedirectNonSef($incomingUrl)
	{
		if (!Wb\startsWith($incomingUrl, 'index.php?option=com_'))
		{
			// not even a non-sef at all
			return false;
		}

		$option = $this->platform->getCurrentContentType();
		if (empty($option))
		{
			// No option, invalid non-sef
			return false;
		}

		$format = $this->platform->getCurrentFormat();
		if (
			!empty($format)
			&&
			'html' !== $format
		) {
			// not an html format, play it safe, don't redirect
			return false;
		}

		return !in_array(
			$option,
			$this->factory->getThis('forseo.config', 'rules')
						  ->get('noNonSefRedirect', [])
		);
	}
}

