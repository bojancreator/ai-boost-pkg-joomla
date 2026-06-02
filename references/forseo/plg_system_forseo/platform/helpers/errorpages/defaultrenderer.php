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

use Joomla\CMS\Language\Text;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Mvc;

class Defaultrenderer extends Renderer
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
		$this->app->loadDocument();
		$document = $this->app->getDocument();

		// Get the current template from the application
		$template         = $this->app->getTemplate(true);
		$document->params = $template->params;

		// Add registry file for the template asset
		$wa = $document->getWebAssetManager()
					   ->getRegistry();

		$wa->addTemplateRegistryFile(
			$template->template,
			$this->app->getClientId()
		);

		if (!empty($template->parent))
		{
			$wa->addTemplateRegistryFile(
				$template->parent,
				$this->app->getClientId()
			);
		}

		$this->setPathway();

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

		$this->safeTriggerEvent('onAfterDispatch');

		$document->setTitle(
			Wb\arrayGet(
				$rule->getRule(),
				'actionErrorTitle',
				Text::_('PLG_SYSTEM_FORSEO_ERROR_PAGE_TITLE')
			)
		);

		$this->app->setHeader('Content-Type', 'text/html; charset=utf-8', true);
		$this->app->setHeader('Cache-Control', 'no-store, no-cache, no-transform', true);
		$this->app->setHeader('Expires', 'Wed, 17 Aug 2005 00:00:00 GMT', true);
		$this->app->setHeader('X-4seo-generator', '4SEO', true);

		$this->safeTriggerEvent(
			'onBeforeCompileHead',
			[
				'document' => $document
			]
		);

		$data = $document->render(
			false,
			[
				'template'         => $template->template,
				'file'             => 'index.php',
				'directory'        => \JPATH_THEMES,
				'debug'            => \JDEBUG,
				'csp_nonce'        => $this->app->get('csp_nonce'),
				'templateInherits' => $template->parent,
				'params'           => $template->params,
			]
		);

		$this->app->setBody($data);

		$this->safeTriggerEvent('onAfterRender');

		// Oddly, allowing cache prevents the CMSApplication to output a no-cache header which overrides ours
		// and does not include private and no-store
		$this->app->allowCache(true);

		echo $this->app->toString();
	}
}
