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
use Weeblr\Wblib\Forseo\Html;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Robots extends Base\Base
{
	/**
	 * @var Requestinfo Convenience instance of the current request details.
	 */
	private $requestInfo = null;

	public function __construct()
	{
		parent::__construct();

		$this->requestInfo = $this->factory
			->getThe('forseo.requestInfo');
	}

	/**
	 * Inject the robots tag;
	 *
	 * 1. Ran at onAfterRenderComplete_body
	 * 2. Search document head for any existing robots
	 * 3. Search Request info for any custom robots
	 * 4. If custom robots, remove existing robots and inject custom one
	 * 5. Else is no robots at all, inject default robots
	 *
	 * NB: Provide for future addition of a Robots SEO rule. Custom, per URL, robots has
	 * priority over SEO Rule
	 *
	 * @param string body
	 *
	 * @return string
	 */
	public function robots($body)
	{
		$originalBody = $body;

		// extract head
		$parts = explode('</head>', $body);
		if (count($parts) <= 1)
		{
			return $body;
		}

		$head      = array_shift($parts);
		$body      = implode('', $parts);
		$headParts = explode('<head', $head);
		if (count($headParts) <= 1)
		{
			return $body;
		}

		$beforeHead = array_shift($headParts);
		$head       = implode('', $headParts);
		$pattern    = '~<meta\s[^>]*name=[\'"]+robots[\'"]+\s[^>]+>~iuUs';

		$customRobots = $this->requestInfo->get(
			'page_custom_robots'
		);

		$hasRobotsTag = 0 !== preg_match(
				$pattern,
				$head
			);

		$updatedRobots = '';
		if (!empty($customRobots))
		{
			$updatedRobots = $customRobots;
		}

		if (
			empty($updatedRobots)
			&&
			!$hasRobotsTag
		) {
			// no pre-existing robots and no preg_match error, use default
			$updatedRobots = $this->factory
				->getThis('forseo.config', 'app')
				->get('defaultRobots', '');
		}

		if (!empty($updatedRobots))
		{
			// clear any existing one override anything
			$robotsTag = $this->factory
				->getA(Html\Helper::class)
				->makeTag(
					'meta',
					[
						'name'    => 'robots',
						'content' => $updatedRobots,
						'class'   => '4SEO_robots_tag',
					],
					''
				);

			if ($hasRobotsTag)
			{
				// replace existing
				$modifiedHead = System\Strings::pr(
					$pattern,
					$robotsTag,
					$head
				);
			}
			else
			{
				// insert new
				$modifiedHead = rtrim($head) . "\n\t" . $robotsTag . "\n";
			}

			$head = empty($modifiedHead)
				? $head
				: $modifiedHead;
		}

		return $beforeHead . '<head' . $head . '</head>' . $body;
	}
}