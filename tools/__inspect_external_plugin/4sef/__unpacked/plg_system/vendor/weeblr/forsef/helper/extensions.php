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

use Weeblr\Forsef\Data;

use Joomla\CMS\Uri;
use Joomla\CMS\Router\SiteRouter;

use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\Wb;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Extensions extends Base\Base
{
	/**
	 * @var array List of extensions that are set to be processed by the platform router instead of 4SEF.
	 */
	static private $platformRoutableExtensions;

	/**
	 * @var Model\Config
	 */
	private $extensionsConfig;

	/**
	 * @var Nonsef
	 */
	private $nonSefHelper;

	/**
	 * Store a sitemap config for convenience.
	 */
	public function __construct($options = [])
	{
		parent::__construct();

		$this->extensionsConfig = $this->factory->getThis('forsef.config', 'extensions');
		$this->nonSefHelper     = $this->factory->getA(Nonsef::class);
	}

	/**
	 * Whether the extension used in the passed URI should use the native 4SEF URL building process.
	 *
	 * @param Uri\Uri $uri
	 * @return bool
	 */
	public function shouldUseForsef($uri)
	{
		return Data\Config::PROCESS_NORMAL === $this->getProcessModeFromUri($uri);
	}

	/**
	 * Whether the extension used in the passed URI should be left as non-SEF
	 *
	 * @param Uri\Uri $uri
	 * @return bool
	 */
	public function shouldLeaveNonSef($uri)
	{
		return Data\Config::PROCESS_NON_SEF === $this->getProcessModeFromUri($uri);
	}

	/**
	 * Whether the extension used in the passed URI should use the Joomla-generated SEF
	 * URL.
	 *
	 * @param Uri\Uri $uri
	 * @return bool
	 */
	public function shouldUseJoomlaSef($uri)
	{
		return Data\Config::PROCESS_USE_JOOMLA === $this->getProcessModeFromUri($uri);
	}

	/**
	 * Whether the extension used in the passed URI should use the Joomla-generated SEF
	 * URL but with or without prepending it with the menu item alias.
	 *
	 * @param Uri\Uri $uri
	 * @return bool
	 */
	public function shouldUseJoomlaSefWithMenuItem($uri)
	{
		$extension = $this->nonSefHelper
			->optionToExtension(
				$uri->getVar('option')
			);

		return Data\Config::PROCESS_USE_JOOMLA === $this->getProcessModeFromUri($uri)
			   &&
			   $this->extensionsConfig->isTruthy($extension . 'ProcessModeJoomlaSefWithMenu');
	}

	/**
	 * Whether the extension used in the passed URI should be left as non-SEF
	 *
	 * @param Uri\Uri $uri
	 * @return bool
	 */
	public function shouldBypass($uri)
	{
		return Data\Config::PROCESS_BYPASS === $this->getProcessModeFromUri($uri);
	}

	/**
	 * Get the process mode for a given extension.
	 *
	 * @param Uri\Uri $uri
	 * @return int
	 */
	public function getProcessModeFromUri($uri)
	{
		$extension = $this->nonSefHelper
			->optionToExtension(
				$uri->getVar('option')
			);

		$configPropertyName = $extension . 'ProcessMode';

		return $this->extensionsConfig->get(
			$configPropertyName
		);
	}

	/**
	 * Test whether any extension is set to use the Joomla router, which means that
	 * if no parsing solution is found in the DB, we'll return early and let the
	 * platform router do its thing.
	 *
	 * @return bool
	 */
	public function shouldUsePlatformRouter()
	{
		static $shouldUse;

		if (is_null($shouldUse))
		{

			$shouldUse = !empty($this->getPlatformRoutableExtensions());
		}

		return $shouldUse;
	}

	/**
	 * Whether we should use the Joomla router for a specific extension.
	 * @param $option
	 * @return bool|mixed
	 */
	public function shouldUsePlatformRouterForOption($option)
	{
		static $shouldUse = [];

		if (!Wb\arrayIsset($shouldUse, $option))
		{
			$extension = $this->nonSefHelper
				->optionToExtension(
					$option
				);

			$shouldUse[$option] = in_array(
				$extension,
				$this->getPlatformRoutableExtensions()
			);
		}

		return $shouldUse[$option];
	}

	/**
	 * Lookup enabled extensions and create a list of those set to be
	 * routed by the platform router.
	 *
	 * @return array
	 */
	public function getPlatformRoutableExtensions()
	{
		if (is_null(self::$platformRoutableExtensions))
		{
			self::$platformRoutableExtensions = [];

			foreach ($this->extensionsConfig->get('available') as $extension => $extensionName)
			{
				if (Data\Config::PROCESS_BYPASS === $this->extensionsConfig->get($extension . 'ProcessMode'))
				{
					self::$platformRoutableExtensions[] = $extension;
				}
			}
		}

		return self::$platformRoutableExtensions;
	}

	/**
	 * Build the format of the request
	 *
	 * @param SiteRouter $router Router object
	 * @param Uri\Uri    $uri    URI object to process
	 *
	 * @return  void
	 *
	 * @since   4.0.0
	 */
	public function buildFormat(&$router, &$uri)
	{
		// we can't test bypass option from $uri, as all variables have been removed
		// already at the end of the build process
		$originalUri = $this->factory->getThe('forsef.builder')
									 ->getOriginalUri();
		// Important: we let Joomla add it's own suffix only when entirely bypassing
		// 4SEF. We cannot do the same when a component is set to use the Joomla SEF URL
		// because in that case, the URL is stored to the database and the user may
		// want to customize it, including touching the suffix. As 4SEF suffix may differ
		// from Joomla suffix, that would cause the suffix to be not recognized by 4SEF
		// and problems will ensue.
		// To avoid that, we add the suffix ourselves for these extensions set to use Joomla SEF
		// It may in some rare cases force a different suffix than what Joomla would have used
		// if the user is nto using the standard suffix .html, but not much to do about it,
		// the opposite would be far worse.
		if (!$this->shouldBypass($originalUri))
		{
			// any configured suffix was added by 4SEF.
			return;
		}

		$route = $uri->getPath();

		// Identify format
		if (!(substr($route, -9) === 'index.php' || substr($route, -1) === '/') && $format = $uri->getVar('format', 'html'))
		{
			$route .= '.' . $format;
			$uri->setPath($route);
			$uri->delVar('format');
		}
	}
}
