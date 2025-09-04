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

namespace Weeblr\Forsef\Model;

use Joomla\CMS\Factory as JoomlaFactory;
use Joomla\CMS\Router\Router as JoomlaRouter;
use Joomla\CMS\Uri\Uri;

use Weeblr\Forsef\Config;
use Weeblr\Forsef\Helper;

use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Wb;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Wrapper class for handling build and parse operations, as done in
 * separate classes Builder and Parser.
 * The only goal of the present class is to allow routing bypass (parsing in particular)
 * while building is in progress (ie a router.php file calls parse while building a URL).
 */
class Router extends Base\Base
{
	/**
	 * @var array Platform router build and parse rules obtained through Reflection.
	 */
	static private $reflectedPlatformRules;

	/**
	 * @var System\Logger Convenience logger instance.
	 */
	private $logger = null;

	/**
	 * @var bool A flag to bypass our routing in some specific circumstances, typically when using the Joomla router to parse.
	 */
	private $bypassOwnRouting = false;

	/**
	 * @param array $options Can inject custom factory and platform.
	 */
	public function __construct($options = [])
	{
		parent::__construct();

		$this->logger = $this->factory->getThe('forsef.logger');

		// this takes care of parsing
		if ($this->platform->majorVersion() >= '5.1.0')
		{
			$plugin = JoomlaFactory::getApplication()->bootPlugin('sef', 'system');
			$plugin->params->set('indexphp', false);
			$plugin->params->set('trailingslash', 0);
		}

		// but buildRules have already been added, need to skip them
	}

	/**
	 * Disable any routing/URL building done by 4SEF, allowing
	 * getting the original platform SEF URL.
	 *
	 * @return $this
	 */
	public function disable4SefBuilding()
	{
		$this->bypassOwnRouting = true;

		return $this;
	}

	/**
	 * Enables any routing/URL building done by 4SEF, allowing
	 * getting the original platform SEF URL.
	 *
	 * @return $this
	 */
	public function enable4SefBuilding()
	{
		$this->bypassOwnRouting = false;

		return $this;
	}

	/*
	 * Creates and store a ReflectionClass for the platform router and set it to accessible.
	 */
	private function getReflectedPlatformRules()
	{
		if (empty(self::$reflectedPlatformRules))
		{
			$reflectionClass              = new \ReflectionClass('\Joomla\CMS\Router\SiteRouter');
			self::$reflectedPlatformRules = $reflectionClass->getProperty('rules');
			self::$reflectedPlatformRules->setAccessible(true);
		}

		return self::$reflectedPlatformRules;
	}

	/**
	 * Disable or reconfigure one or more plugins from a given plugins group as set in Pages configuration.
	 *
	 * @return void
	 */
	public function preProcessBuildRule(&$router, &$uri)
	{
		try
		{
			if (
				$this->bypassOwnRouting
				||
				!$this->isSefMode()
			)
			{
				return;
			}

			if (
				$this->platform->isOffline()
				&&
				$this->platform->isGuest()
			)
			{
				// can be difficult to determine correct URL on multilingual sites
				// better bypass
				return;
			}

			$this->factory
				->getThe('forsef.builder')
				->storeBuildRequest(
					$router,
					$uri
				);

			$this->reorderBuildRules($router);
		}
		catch (\Throwable $e)
		{
			$this->logger->error(__METHOD__ . ', %s::%d %s %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
	}

	/**
	 * Reorder build rules stored in the platform router so that the 4SEF one is placed
	 * after other rules except the "format" rule, which must always be last so that the
	 * format suffix (html, json, etc) is indeed last in the URL.
	 *
	 * @param \Joomla\CMS\Router\SiteRouter $router
	 * @return void
	 */
	private function reorderBuildRules(&$router)
	{
		static $reordered = false;

		if (!$reordered)
		{
			$reordered       = true;
			$platformVersion = $this->platform->majorVersion() < 4
				? 'J3'
				: 'Default';
			$methodName      = 'reorderBuildRules' . $platformVersion;

			if (is_callable([$this, $methodName]))
			{
				$this->{$methodName}($router);
			}
		}
	}

	/**
	 * Implements router build rules re-ordering for Joomla 4.
	 * We inject
	 *
	 * @param \Joomla\CMS\Router\SiteRouter $router
	 * @return void
	 */
	private function reorderBuildRulesDefault(&$router)
	{
		$reflectionProperty    = $this->getReflectedPlatformRules();
		$rules                 = $reflectionProperty->getValue($router);
		$postProcessBuildRules = Wb\arrayGet($rules, 'buildpostprocess', []);
		$ordered               = [];
		$forsefRule            = null;
		$rewritePrefixRule     = null;
		$buildBaseRule         = null;
		foreach ($postProcessBuildRules as $rule)
		{
			if (!empty($rule[1]) && 'buildFormat' === $rule[1])
			{
				// replace the format rule, as usually as 4SEF builds its own suffix.
				// Exception is when a component is set to ByPass or Use Joomla SEF
				$ordered[] = [
					$this->factory->getA(Helper\Extensions::class),
					'buildFormat'
				];
			}
			else if (!empty($rule[1]) && 'buildBase' === $rule[1])
			{
				$buildBaseRule = $rule;
			}
			else if (!empty($rule[1]) && 'buildRewrite' === $rule[1])
			{
				$rewritePrefixRule = $rule;
			}
			else if (
				!empty($rule[1])
				&&
				in_array(
					$rule[1],
					['removeTrailingSlash', 'addTrailingSlash'] // J 5.1
				)
			)
			{
				// remove
			}
			else if (!empty($rule[0]) && ($rule[0] instanceof \Weeblr\Forsef\Model\Router))
			{
				$forsefRule = $rule;
			}
			else
			{
				$ordered[] = $rule;
			}
		}
		if (!empty($forsefRule))
		{
			$ordered[] = $forsefRule;
		}
		if (!empty($rewritePrefixRule))
		{
			$ordered[] = $rewritePrefixRule;
		}
		if (!empty($buildBaseRule))
		{
			$ordered[] = $buildBaseRule;
		}
		$rules['buildpostprocess'] = $ordered;
		$reflectionProperty->setValue(
			$router,
			$rules
		);
	}

	/**
	 * Ran at PROCESS_DURING stage: searches if we've already built this SEF, to return early.
	 *
	 * @return void
	 */
	public function buildRule(&$router, &$uri)
	{
		if (!$this->isSefMode())
		{
			return;
		}

		try
		{
			$this->factory
				->getThe('forsef.builder')
				->searchSef(
					$router,
					$uri
				);
		}
		catch (\Throwable $e)
		{
			$this->logger->error(__METHOD__ . ', %s::%d %s %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
	}

	/**
	 * Ran at PROCESS_AFTER: actually build a SEF URL, as we've not found it at the PROCESS_DURING phase.
	 *
	 * @return void
	 */
	public function postProcessBuildRule(&$router, &$uri)
	{
		if (
			$this->bypassOwnRouting
			||
			!$this->isSefMode()
		)
		{
			return;
		}

		try
		{
			$this->factory
				->getThe('forsef.builder')
				->buildSef(
					$router,
					$uri
				);
		}
		catch (\Throwable $e)
		{
			$eMessage = $e->getMessage();
			if (!Wb\contains($eMessage, '4SEF: no option value set in URI'))
			{
				$this->logger->error(__METHOD__ . ', %s::%d %s %s', $e->getFile(), $e->getLine(), $eMessage, $e->getTraceAsString());
			}

		}
	}

	/**
	 * Runs before the actual parsing. We use it to re-order the router rules so that ours comes first.
	 * Joomla router is not extensible and so we must make sure our rule comes first else some parts of the
	 * requested URL will already have been processed (ie have disappeared when we get our hands on it).
	 *
	 * @param JoomlaRouter $router
	 *
	 */
	public function preprocessParseRule(&$router)
	{
		try
		{
			if (
				$this->bypassOwnRouting
				||
				!$this->isSefMode()
			)
			{
				return;
			}

			$this->reorderParseRules($router);
		}
		catch (\Throwable $e)
		{
			$this->logger->error(__METHOD__ . ', %s::%d %s %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
	}

	/**
	 * Reorder parse rules stored in the platform router so that the 4SEF one is placed
	 * before any other. This is required as the Joomla router can be somewhat extended but does not
	 * allow an extension to parse the full URL.
	 *
	 * If the 4SEF parse rule, is not first, one if not all parts of the SEF will already have been removed
	 * by the other rules.
	 *
	 * @param \Joomla\CMS\Router\SiteRouter $router
	 * @return void
	 */
	private function reorderParseRules(&$router)
	{
		static $reordered = false;

		if (!$reordered)
		{
			$reordered       = true;
			$platformVersion = $this->platform->majorVersion() < 4
				? 'J3'
				: 'Default';
			$methodName      = 'reorderParseRules' . $platformVersion;

			if (is_callable([$this, $methodName]))
			{
				$this->{$methodName}($router);
			}
		}
	}

	/**
	 * Implements router parse rules re-ordering for Joomla 4.
	 *
	 * @param \Joomla\CMS\Router\SiteRouter $router
	 * @return void
	 */
	private function reorderParseRulesDefault(&$router)
	{
		$reflectionProperty = $this->getReflectedPlatformRules();
		$rules              = $reflectionProperty->getValue($router);
		$parseRules         = Wb\arrayGet($rules, 'parse', []);
		$ordered            = [];
		$forsefRule         = null;
		foreach ($parseRules as $rule)
		{
			if (!empty($rule[0]) && ($rule[0] instanceof \Weeblr\Forsef\Model\Router))
			{
				$forsefRule = $rule;
			}
			else
			{
				$ordered[] = $rule;
			}
		}
		if (!empty($forsefRule))
		{
			array_unshift($ordered, $forsefRule);
		}

		$rules['parse'] = $ordered;
		$reflectionProperty->setValue(
			$router,
			$rules
		);
	}

	public function filterParseRules(&$router)
	{
		$reflectionProperty      = $this->getReflectedPlatformRules();
		$rules                   = $reflectionProperty->getValue($router);
		$preprocessParseRules    = Wb\arrayGet($rules, 'parsepreprocess', []);
		$filteredPreprocessRules = [];
		$rulesToExclude          = [];
		foreach ($preprocessParseRules as $rule)
		{
			if (!empty($rule[0]) && !in_array($rule[1], $rulesToExclude))
			{
				$filteredPreprocessRules[] = $rule;
			}

			if (!empty($rule[0]) && $rule[1] === 'parseInit')
			{
				// parseInit trims out the trailing slash in incoming URLs
				// this breaks us and also causes incorrect behavior of
				// parseFormat when URLs have dot somewhere.
				$filteredPreprocessRules[] = [
					$this->factory->getThe('forsef.parser'),
					'restoreTrailingSlash'
				];
			}
		}

		$rules['parsepreprocess'] = $filteredPreprocessRules;
		$reflectionProperty->setValue(
			$router,
			$rules
		);
	}

	/**
	 * PROCESS_DURING parse rule for Joomla router.
	 *
	 * @param JoomlaRouter $router
	 * @param Uri          $uri
	 * @return array
	 * @throws \Exception
	 */
	public function parseRule(&$router, &$uri)
	{
		if (
			$this->bypassOwnRouting
			||
			!$this->isSefMode()
		)
		{
			return [];
		}

		// Must happen at PROCESS_DURING, not pre-process as the language has not been parsed yet by language filter.
		$this->factory
			->getThe('forsef.requestInfo')
			->parseDynamicSegments();

		// at this stage, the language filter may have already parsed the URL
		// It would have set the lang variable and removed any language URL prefix corresponding to that language variable.
		// meaning that whatever language we have stored in the non-sef should be used but should not override
		// the lang var set by the language filter.
		try
		{
			$parsedVars = $this->factory
				->getThe('forsef.parser')
				->parseUri(
					$router,
					$uri
				);
		}
		catch (\Exception $e)
		{
			$parsedVars = [];
			if (System\Http::RETURN_NOT_FOUND === $e->getCode())
			{
				$parsedVars = $this->parseWithJoomlaRouter($uri);
			}

			if (
				empty($parsedVars)
				&&
				System\Http::RETURN_NOT_FOUND === $e->getCode()
			)
			{
				throw $e;
			}

			if (
				empty($parsedVars)
				&&
				$this->platform->majorVersion() >= 4
			)
			{
				// only re-throw on Joomla 4, Joomla 3 would not handle an Exception here.
				throw $e;
			}
		}

		return $parsedVars;
	}

	/**
	 * If configured so, ask the Joomla router to parse the incoming URI and check
	 * the result for plausibility.
	 *
	 * @param URI\URI $uri
	 * @return array
	 * @throws \Exception
	 */
	private function parseWithJoomlaRouter($uri)
	{
		$parsedVars      = [];
		$extensionHelper = $this->factory->getA(Helper\Extensions::class);
		if ($extensionHelper->shouldUsePlatformRouter())
		{
			$clonedUri              = clone($uri);
			$this->bypassOwnRouting = true;
			$platformVars           = $this->platform->getRouter('site')->parse($clonedUri);
			$this->bypassOwnRouting = false;
			if (
				!empty($platformVars)
				&&
				$extensionHelper->shouldUsePlatformRouterForOption(
					Wb\arrayGet($platformVars, 'option')
				)
			)
			{
				$parsedVars = $platformVars;
			}
		}

		return $parsedVars;
	}

	/**
	 * Whether Joomla is configured with sef URL.
	 *
	 * @return bool
	 */
	private function isSefMode()
	{
		return !empty($this->platform->getConfig()->get('sef'));
	}
}
