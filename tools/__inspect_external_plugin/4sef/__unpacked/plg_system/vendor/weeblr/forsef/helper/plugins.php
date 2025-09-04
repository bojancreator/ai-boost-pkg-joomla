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

use Joomla\CMS\Uri;
use Joomla\CMS\Plugin\PluginHelper;

use Weeblr\Forsef\Platform;
use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\Wb;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Plugins extends Base\Base
{
	/**
	 * @var bool Flag to trigger loading plugins.
	 */
	static private $loaded = false;

	/**
	 * Build and memoize plugin to build the 4SEF SEF URL based on the component name.
	 *
	 * @param string $option
	 * @return Platform\Extensions
	 * @throws \Exception
	 */
	public function getPlugin($option)
	{
		$option = empty($option)
			? 'fallback'
			: $option;
		return $this->loadPlugins()
			->factory->getThis(
				'forsef.extensionPlugin',
				$option
			);
	}

	/**
	 * Build and memoize plugin to build the 4SEF SEF URL based on URI object.
	 *
	 * @param Uri\Uri $uri
	 * @return Platform\Extensions
	 * @throws \Exception
	 */
	public function getPluginFromUri($uri)
	{
		return $this->getPlugin(
			Wb\arrayGet(
				$uri->getQuery(true),
				'option'
			)
		);
	}

	/**
	 * Triggers an action to let 3rd-parties to load their custom plugins.
	 *
	 * @return Plugins
	 */
	private function loadPlugins()
	{
		if (!self::$loaded)
		{
			self::$loaded = true;

			PluginHelper::importPlugin('forsef');

			/**
			 * Run an action allowing 3rd-party plugins providers to load
			 * their plugins.
			 *
			 * @api     forsef
			 * @package 4SEF\action\plugins
			 * @var forsef_on_load_plugins
			 * @since   1.0.0
			 *
			 * @param \Exception $error
			 *
			 * @return void
			 *
			 */
			$this->factory->getThe('hook')->run(
				'forsef_on_load_plugins'
			);
		}

		return $this;
	}
}
