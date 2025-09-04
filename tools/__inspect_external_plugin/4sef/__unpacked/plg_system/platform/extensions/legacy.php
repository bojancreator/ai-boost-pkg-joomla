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

namespace Weeblr\Forsef\Platform\Extensions;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Factory;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Fallback to provide support for an extension: search for legacy plugins.
 */
class Legacy extends Base
{
	/**
	 * Stores factory instance.
	 *
	 * @param array $options Can inject custom factory and platform.
	 */
	public function __construct($options = [])
	{
		parent::__construct($options);

		$this->logger        = $this->factory->getThe('forsef.logger');
		$this->routingConfig = $this->factory->getThis('forsef.config', 'routing');
	}

	/**
	 * Builds the SEF URL for a non-sef, using a legacy sh404SEF plugin for the extension.
	 *
	 * @param string  $option
	 * @param Uri\Uri $uriToBuild
	 * @param Uri\Uri $originalUri
	 *
	 * @return string|null
	 * @throws \Exception
	 */
	public function build($option, $uriToBuild, $originalUri)
	{
		return ['4sef-unsupported-extension'];
		$vars = $uriToBuild->getQuery(true);
		\Getvarslist::init($vars);
		$pluginPath = $this->getPluginPath($option, $vars);
		if (empty($pluginPath))
		{
			return null;
		}

		$string = $this->nonSefHelper
			->buildNormalizedNonSef($vars);

		extract($vars);

		if (!isset($lang))
		{
			$lang = $this->platform->getLanguageUrlCode(
				$this->platform->getCurrentLanguageTag()
			);
		}

		// maybe one of them interfere with the variable holding our result?
		if (isset($title))
		{                              // protect against components using 'title' as GET vars
			$sh404SEF_title = $title;  // means that $sh404SEF_title has to be used in plugins or extensions
		}

		$title           = [];
		$activeMenu      = $this->platform->getMenu('site')->getActive();
		$shCurrentItemid = empty($activeMenu) ? '' : $activeMenu->id;
		$shAppendString  = '';

		include $pluginPath;

		/**
		 * Filter the SEF URL as built by the component-specific sh404SEF plugin.
		 *
		 * @api
		 * @package sh404SEF\filter\router
		 * @var sh404sef_after_plugin_build
		 * @since   4.9.2
		 *
		 * @param string $string        The computed SEF URL.
		 * @param array  $vars          Associate array of query vars used to build the SEF.
		 * @param int    $pluginType    Plugin type: native sh404SEF, Joomsef, Acesef,...
		 * @param string $extPluginPath The full path to the plugin used to build the URL.
		 *
		 * @return string
		 */
		//$string = ShlHook::filter('sh404sef_after_plugin_build', $string, $vars, $pluginType, $extPluginPath);

		// must manage vars in query, remove those that have been used.

		return $string;
	}

	/**
	 * Figure out the path to the plugin required to build SEF URLs for a given component.
	 * First look for a 3rd-party plugin offering a SEF plugin.
	 *
	 * If no result, looks up several location in order:
	 *
	 * - /components/{$option/sef_ext/{$option}.php
	 * - FORSEF_APP_PLATFORM_PATH/platform/sef_ext/{$option}.php
	 *
	 * Result is then filtered to let extension register their own.
	 *
	 * @param string $option
	 * @param array  $vars
	 * @return string|null
	 */
	private function getPluginPath($option, $vars)
	{
		// Check for a possible 3rd-party plugin supporting this extension
		$thirdPartyPluginPath = $this->get3rdPartyPluginPath(
			$option,
			$vars
		);

		if (!empty($thirdPartyPluginPath))
		{
			$this->logger->debug(__METHOD__ . ', 4SEF: found 3rd-party plugin for extension ' . $option . ' at ' . $thirdPartyPluginPath);

			return $thirdPartyPluginPath;
		}

		// if no 3rd-party found, use built-in
		$possiblePaths = [
			Wb\slashTrimJoin(
				JPATH_ROOT,
				'components/' . $option . '/sef_ext',
				$option . '.php'
			),
			Wb\slashTrimJoin(
				FORSEF_APP_PLATFORM_PATH,
				'platform/sef_ext',
				$option . '.php'
			)
		];

		foreach ($possiblePaths as $possiblePath)
		{
			if (file_exists($possiblePath))
			{
				return $possiblePath;
			}
		}

		$this->logger->debug(__METHOD__ . ', 4SEF: no plugin path found for extension ' . $option . ', falling back to Joomla SEF');

		return null;
	}

	/**
	 * Search and instantiate any 3rd-party plugin for the desired component.
	 *
	 * @param $option
	 * @param $vars
	 * @return string|null
	 */
	private function get3rdPartyPluginPath($option, $vars)
	{
		$extSupportPluginPath = $this->get3rdPartyPlugin($option);

		return empty($extSupportPluginPath)
			? null
			: $extSupportPluginPath->getSefPluginPath($vars);
	}

	/**
	 * Get a Extplugin object for the requested extension. If no specific plugin is found, the default, generic
	 * public is used instead
	 *
	 * @param string $option the Joomla! component name. Should begin with "com_"
	 *
	 * @return object|false \Sh404sefExtpluginBaseextplugin descendant
	 */
	public static function get3rdPartyPlugin($option)
	{
		static $_plugins = [];

		// plugin is cached, check if we already created the plugin for $option.
		if (!isset($_plugins[$option]))
		{
			$pluginPath = JPATH_ROOT . '/plugins/sh404sefextplugins/sh404sefextplugin' . strtolower($option);
			if (!file_exists($pluginPath))
			{
				$_plugins[$option] = false;

				return false;
			}

			include_once $pluginPath;

			// does this class exists?
			$sefConfig = \Sh404sefFactory::getConfig();
			$className = '\Sh404sefExtplugin' . ucfirst(strtolower($option));
			if (class_exists($className))
			{
				// instantiate plugin
				$_plugins[$option] = new $className($option, $sefConfig);
			}
		}

		// return cached plugin
		return $_plugins[$option];
	}

}
