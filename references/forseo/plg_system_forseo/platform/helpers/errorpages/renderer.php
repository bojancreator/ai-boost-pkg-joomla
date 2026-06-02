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

namespace Weeblr\Forseo\Platform\Helpers\Errorpages;

use Joomla\Event\Event;
use Weeblr\Forseo\Data;
use Weeblr\Forseo\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Menu;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Event\Application\AfterDispatchEvent;
use Joomla\CMS\Event\Application\BeforeCompileHeadEvent;
use Joomla\CMS\Event\Application\AfterRenderEvent;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Mvc;

abstract class Renderer extends Base\Base
{
	/**
	 * @var Menu\AbstractMenu Local copy of menu object.
	 */
	protected $menu = null;

	/**
	 * @var Application Local copy of Joomla application.
	 */
	protected $app = null;

	/**
	 * Route constructor. Stores a ref to platform app and menu object.
	 *
	 * @throws \Exception
	 */
	public function __construct()
	{
		parent::__construct();
		$this->app  = Factory::getApplication();
		$this->menu = $this->app->getMenu('site');
	}

	/**
	 * Render an html document in response to a request
	 * that generated an error
	 *
	 * @param Data\Rule  $rule
	 * @param \Exception $error an exception
	 * @param Data\Page  $page
	 * @throws \Exception
	 */
	public function render($rule, $error, $page)
	{
		$this->setLanguage($rule)
			 ->setItemid($rule);

		// kill the document, avoid Joomla fatal error
		// if request has format query var != 'html'
		Factory::$document = null;

		// no caching on this page
		Factory::getConfig()->set('caching', 0);

		// pretend routing happened normally
		$this->simulateRouting();

		// make sure we have a 404 header
		if (!headers_sent())
		{
			$this->platform->setHttpStatus(
				$error->getCode()
			);
		}

		if (!defined('JPATH_COMPONENT'))
		{
			define('JPATH_COMPONENT', JPATH_BASE . '/components/com_4seo');
		}

		if (!defined('JPATH_COMPONENT_SITE'))
		{
			define('JPATH_COMPONENT_SITE', JPATH_SITE . '/components/com_4seo');
		}

		if (!defined('JPATH_COMPONENT_ADMINISTRATOR'))
		{
			define('JPATH_COMPONENT_ADMINISTRATOR', JPATH_ADMINISTRATOR . '/components/com_4seo');
		}

		$this->renderDocument(
			$rule,
			$error,
			$this->getSuggested(
				$rule,
				$page
			)
		);
	}

	/**
	 * Pretend routing happened normally, plus set a few things
	 * up to avoid warnings in language filter plugin and others.
	 *
	 * @return void
	 */
	private function simulateRouting()
	{
		if (version_compare($this->platform->majorVersion(), '3', '>'))
		{
			$this->app->triggerEvent(
				'onAfterRoute',
				[
					'subject' => $this->app
				]
			);
		}
		else
		{
			$this->app->triggerEvent(
				'onAfterRoute'
			);

		}

		// warning if php 8+ in:
		// LanguageFilter plugin
		// ExtensionManagerTrait
		$this->platform->getHttpInput()->set('option', '');
	}

	/**
	 * Finds pages simlar to the current request and builds an array
	 * of titles indexed on URL.
	 *
	 * @param Data\Rule $rule
	 * @param Data\Page $page
	 * @return array
	 * @throws \Exception
	 *
	 */
	protected function getSuggested($rule, $page)
	{
		if (Wb\arrayIsFalsy(
			$rule->getRule(),
			'actionErrorSuggest'
		))
		{
			return [];
		}

		return $this->factory
			->getA(Model\Similar::class)
			->find(
				$page->get('full_url')
			);
	}

	/**
	 * Renders the main error page Layout.
	 *
	 * @param Data\Rule  $rule
	 * @param \Throwable $error
	 * @param array      $suggested
	 * @return string
	 */
	protected function getComponentOutput($rule, $error, $suggested)
	{
		return Mvc\LayoutHelper::render(
			'forseo.errors.default',
			[
				'error'     => $error,
				'rule'      => $rule,
				'suggested' => $suggested
			],
			FORSEO_LAYOUTS_PATH,
			'default'
		);
	}

	/**
	 * Sets the app pathway in case the template includes a breadcrumb.
	 *
	 * @return $this;
	 */
	protected function setPathway()
	{
		// kill pathway
		$newPathWay       = new \stdClass();
		$newPathWay->name = Text::_('COM_FORSEO_PAGE_NOT_FOUND_PATHWAY');
		$newPathWay->link = '';
		$this->app
			->getPathway()
			->setPathway(
				[$newPathWay]
			);

		return $this;
	}

	/**
	 * Set the display language based on rule and defaults values.
	 *
	 * @param Data\Rule $rule
	 *
	 * @return $this
	 */
	protected function setLanguage($rule)
	{
		$languageTag = $this->platform->getCurrentLanguageTag();
		if (empty($languageTag))
		{
			$languageTag = $this->platform->getHttpInput()->getString(
				ApplicationHelper::getHash('language'), null, 'cookie'
			);
		}

		if (empty($languageTag))
		{
			$languageTag = $this->platform->getDefaultLanguageTag();
		}

		if (!empty($languageTag))
		{
			$this->platform->getHttpInput()->set('lang', $languageTag);
		}

		return $this;
	}

	/**
	 * Finds the user set Itemid to use for an error page
	 * (per language). Defaults to home page (per language)
	 * if none set.
	 *
	 * @param Data\Rule $rule
	 *
	 * @return $this
	 * @throws Exception
	 */
	protected function setItemid($rule)
	{
		$ruleOptions = $rule->getRule();
		$Itemid      = Wb\arrayGet($ruleOptions, 'actionErrorMenu');
		if (empty($Itemid))
		{
			$Itemid = $this->menu->getDefault()->id;
		}
		else
		{
			$Itemid = $Itemid[0];
		}

		$this->platform->getHttpInput()->set('Itemid', $Itemid);
		$menuItem = $this->menu->getItem($Itemid);
		if (!empty($menuItem))
		{
			$this->menu->setActive($Itemid);
			$this->app->getParams()
					  ->set(
						  'pageclass_sfx',
						  $menuItem->getParams()->get('pageclass_sfx')
					  );
		}

		return $this;
	}

	/**
	 * Render a regular html document.
	 *
	 * @param Data\Rule  $rule
	 * @param \Throwable $error
	 * @param array      $suggested
	 */
	abstract protected function renderDocument($rule, $error, $suggested);

	/**
	 * Trigger an application event, wrapped in a try/catch to avoid
	 * 3rd-party extensions failing and breaking the page.
	 *
	 * @param string $eventName
	 * @param array  $args
	 * @return void
	 */
	protected function safeTriggerEvent($eventName, $args = [])
	{
		try
		{
			if (Wb\arrayIsFalsy($args, 'subject'))
			{
				$args['subject'] = $this->app;
			}

			if (version_compare($this->platform->majorVersion(), '4', '>'))
			{
				$event = $this->buildEvent($eventName, $args);
				$this->app->getDispatcher()->dispatch(
					$eventName,
					$event
				);
			}
			else if (version_compare($this->platform->majorVersion(), '3', '>'))
			{
				$this->app->triggerEvent(
					$eventName,
					$args
				);
			}
			else
			{
				$this->app->triggerEvent(
					$eventName
				);
			}
		}
		catch (\Throwable $e)
		{
			// catch any exception 3rd-party plugins can throw when running
			// their event handlers under not so usual circumstances
		}
	}

	/**
	 * Builds an event object for Joomla 5+.
	 *
	 * @param string $eventName
	 * @param array  $args
	 * @return Event
	 */
	private function buildEvent($eventName, $args)
	{
		$className = '\Joomla\CMS\Event\Application\\' . Wb\lTrim($eventName, 'on') . 'Event';

		return new $className($eventName, $args);
	}
}


