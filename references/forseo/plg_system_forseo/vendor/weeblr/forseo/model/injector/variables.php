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

namespace Weeblr\Forseo\Model\Injector;

use Weeblr\Forseo\Data\Requestinfo;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Variables extends Base\Base
{
	/**
	 * @var Requestinfo Convenience instance of the current request details.
	 */
	private $requestInfo = null;

	public function __construct()
	{
		parent::__construct();

		$this->requestInfo = $this->factory->getThe('forseo.requestInfo');
	}

	/**
	 * Expand variables if found inside a text buffer.
	 *
	 * @param null|string $buffer
	 * @param null|int    $expansionCount
	 * @param array       $variablesList Optional restricted list of variables to replace.
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function expand($buffer, &$expansionCount = null, $variablesList = [])
	{
		$expansionCount  = 0;
		$activeVariables = $this->prepareVariables(
			$this->requestInfo->get(),
			$variablesList
		);

		if (empty($activeVariables))
		{
			return [$buffer, $expansionCount];
		}

		$pattern  = '~{4seo_([a-zA-Z0-9-/_]+)}~iuUs';
		$expanded = preg_replace_callback(
			$pattern,
			function ($matches) use ($activeVariables)
			{
				$variableName = $matches[1];
				if (
					!array_key_exists($variableName, $activeVariables)
					||
					is_null($activeVariables[$variableName])
				) {
					// keep original if unknown variable or null value
					return $matches[0];
				}

				return $activeVariables[$variableName];
			},
			$buffer,
			-1,
			$expansionCount
		);

		return empty($expansionCount)
			? [$buffer, $expansionCount]
			: [$expanded, $expansionCount];
	}

	/**
	 * Prepare the list of expandable variables, posisbly removing some
	 * based on passed restricted list and then filtering it.
	 *
	 * @param array $variables
	 * @param array $restrictedList
	 *
	 * @return array
	 */
	private function prepareVariables($variables, $restrictedList = [])
	{
		if (!empty($restrictedList))
		{
			$variables = array_intersect_key(
				$variables,
				array_values(
					$restrictedList
				)
			);
		}

		if (empty($variables))
		{
			return $variables;
		}

		/**
		 * Filter the list of dynamic variables used in content replacement.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\content
		 * @var forseo_expandable_variables
		 * @since   1.0.0
		 *
		 * @param array     $expandedVariables Array of variable names/variable values to use in expansions.
		 * @param Data\page $pageData          Data object with current request details.
		 *
		 * @return array
		 *
		 */
		$variables = $this->factory
			->getThe('hook')
			->filter(
				'forseo_expandable_variables',
				$variables,
				$this->factory->getThe('forseo.pageDataCollector')->get()
			);

		// safety net
		return (
			empty($variables)
			||
			!is_array($variables)
		)
			? []
			: $variables;
	}

	/**
	 * Clean up all remaining variable tags that we may not have been able
	 * to expand prior.
	 *
	 * @param null|string $buffer
	 *
	 * @return string
	 */
	public function cleanVariablesTags($buffer)
	{
		$pattern = '~{4seo_([a-zA-Z0-9-/_]+)}~iUs';

		$buffer = System\Strings::pr(
			$pattern,
			'',
			$buffer
		);

		return $buffer;
	}
}