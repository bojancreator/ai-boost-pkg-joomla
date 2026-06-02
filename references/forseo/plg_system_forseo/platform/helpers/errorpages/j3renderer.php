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

use Joomla\CMS\Factory;
use Joomla\Registry\Registry;
use Joomla\CMS\Language\Text;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Mvc;

class J3renderer extends Renderer
{
	/**
	 * Render a regular html document.
	 *
	 * @param Data\Rule  $rule
	 * @param \Throwable $error
	 * @param array      $suggested
	 */
	protected function renderDocument($rule, $error, $suggested)
	{
		$document = $this->app->loadDocument()->getDocument();
		$template = $this->app->getTemplate(true);

		// which menu item to use
		$item = $this->menu->getActive();
		if (!empty($item))
		{
			// force the template associated with the menu item selected for our error page
			$styleId = $item->template_style_id;

			// Load styles
			$db    = $this->getPlatformDb();
			$query = $db->getQuery(true)
						->select('id, home, template, s.params')
						->from('#__template_styles as s')
						->where('s.client_id = 0')
						->where('e.enabled = 1')
						->join('LEFT', '#__extensions as e ON e.element=s.template AND e.type=' . $db->quote('template') . ' AND e.client_id=s.client_id');

			$db->setQuery($query);
			$templates = $db->loadObjectList('id');
			if (!empty($templates[$styleId]))
			{
				$registry = new Registry;
				$registry->loadString($templates[$styleId]->params);
				$template         = $templates[$styleId];
				$template->params = $registry;
			}
		}

		// Store the template and its params to the config
		$this->app->setTemplate(
			$template->template,
			$template->params
		);

		if (ob_get_contents())
		{
			ob_end_clean();
		}

		$document->setBuffer(
			$this->getComponentOutput(
				$rule,
				$error,
				$suggested
			),
			'component'
		);

		$document->setTitle(
			Wb\arrayGet(
				$rule->getRule(),
				'actionErrorTitle',
				Text::_('PLG_SYSTEM_FORSEO_ERROR_PAGE_TITLE')
			)
		);

		try
		{
			// some document types may not allow these methods
			$document->setDescription('');
			$document->addHeadLink('', 'canonical');
			$document->setMetaData('robots', 'noindex,follow');
		}
		catch (\Throwable $e)
		{
		}


		// Setup the document rendering options.
		$document->setBase(
			htmlspecialchars(
				\JUri::current()
			)
		);
		$this->setPathway();

		$this->safeTriggerEvent('onAfterDispatch');

		$docOptions['template'] = $template->template;
		$docOptions['file']     = 'index.php';
		$docOptions['params']   = $this->app->get('themeParams');

		if ($this->app->get('themes.base'))
		{
			$docOptions['directory'] = $this->app->get('themes.base');
		}
		else
		{
			$docOptions['directory'] = defined('JPATH_THEMES') ? JPATH_THEMES : (defined('JPATH_BASE') ? JPATH_BASE : __DIR__) . '/themes';
		}

		$document->parse($docOptions);

		$this->safeTriggerEvent('onBeforeCompileHead');

		// Render the document.
		$data = $document->render(
			false,
			$docOptions
		);

		$this->app->setHeader('Content-Type', 'text/html; charset=utf-8', true);
		$this->app->setHeader('Cache-Control', 'no-store, no-cache, no-transform', true);
		$this->app->setHeader('X-4seo-generator', '4SEO', true);

		// Failsafe to get the error displayed.
		if (empty($data))
		{
			$this->safeEcho(
				$error->getMessage()
			);
		}
		else
		{

			$this->app->setBody($data);
			$this->safeTriggerEvent('onAfterRender');

			// Oddly, allowing cache prevents the CMSApplication to output a no-cache header which overrides ours
			// and does not include private and no-store
			$this->app->allowCache(true);

			echo $this->app->toString();
		}
	}

	/**
	 * Wrapper to get the platform DB object regardless of platform version.
	 *
	 * @return mixed
	 */
	private function getPlatformDb()
	{
		return version_compare(\JVERSION, '4.0', '<')
			? Factory::getDbo()
			: Factory::getContainer()->get('db');
	}
}
