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

use Weeblr\Forseo\Data;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;

class Errorpage extends Base\Base
{
	/**
	 * Render an html document in response to a request
	 * that generated a 404 error
	 *
	 * @param [Data\Rule]  $rules
	 * @param \Throwable $error an exception.
	 * @param Data\Page  $page
	 */
	public function render($rules, $error, $page)
	{
		$applicableRule = null;
		foreach ($rules as $rule)
		{
			if ($this->ruleAppliesToError($rule, $error))
			{
				$applicableRule = $rule;
				break;
			}
		}

		if (empty($applicableRule))
		{
			return;

		}

		$applicableRule->timestamp('last_hit')
					   ->increment('hits')
					   ->store();

		$platformType = $this->platform->majorVersion() > 3
			? 'Default'
			: 'J3';

		$renderer = $this->factory->getA(
			'Weeblr\Forseo\Platform\Helpers\Errorpages\\' . $platformType . 'renderer'
		);

		$renderer->render(
			$applicableRule,
			$error,
			$page
		);

		die();
	}

	/**
	 * Figure out if the rule applies to the current error
	 * based on the error code.
	 *
	 * @param Data\Rule  $rule
	 * @param \Throwable $error
	 *
	 * @return bool
	 */
	public function ruleAppliesToError($rule, $error)
	{
		$ruleDef       = $rule->getRule();
		$ruleErrorCode = Wb\arrayGet($ruleDef, 'actionErrorCode', 0);
		if (empty($ruleErrorCode))
		{
			// all
			return true;
		}

		$errorCode = $error->getCode();
		if (empty($errorCode))
		{
			// no code, no choice
			return false;
		}

		return $errorCode === (int)$ruleErrorCode;
	}

	/**
	 * Displays a message to screen or whatever is available
	 * in a way that should work in most situations
	 *
	 * @param $message string to display
	 */
	public function safeEcho($message)
	{

		if (isset($_SERVER['HTTP_HOST']))
		{
			// Output as html
			echo "<br /><b>Error:</b>: $message<br />\n";
		}
		else
		{
			// Output as simple text
			if (defined('STDERR'))
			{
				fwrite(STDERR, "$message\n");
			}
			else
			{
				echo "$message\n";
			}
		}
	}
}
