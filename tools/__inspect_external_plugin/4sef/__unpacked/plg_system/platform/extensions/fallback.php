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

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Fallback to provide support for an extension.
 */
class Fallback extends Base
{
	/**
	 * Fallback plugin to build the SEF URL for a non-sef, used when no specific plugin is available for the
	 * corresponding extension.
	 *
	 * @param Uri\Uri $uriToBuild
	 * @param Uri\Uri $platformUri
	 * @param Uri\Uri $originalUri
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function build($uriToBuild, $platformUri, $originalUri)
	{
		$platformSef = $this->urlHelper->getSefFromUri($platformUri);
		if (empty($platformSef))
		{
			return [];
		}

		// suppress rewrite prefix as all of 4SEF works without it
		// but this fallback plugin starts with the Joomla-built SEF
		// which has been fully built and aleady includes that prefix
		$sef = Wb\lTrim(
			$platformSef,
			'index.php/'
		);

		// suppress language code, as we'll put it back later.
		$langTag = $originalUri->getVar('lang');
		if ($this->platform->shouldAddLangCodeToSef($langTag))
		{
			$langCode = $this->platform->getLanguageUrlCode($langTag);
			$sef      = Wb\lTrim(
				$sef,
				$langCode . '/'
			);
		}

		// Possibly strip the menu item from the start of the URL, if configured to
		// usually for backward compatibility with sh404SEF URL options.
		$menuItemId = $uriToBuild->getVar('Itemid');
		if (!empty($menuItemId))
		{
			$sef = $this->menuHelper->stripMenuItem(
				$sef,
				$menuItemId
			);
		}

		// if using platform SEF, we must also use the same non-sef variables
		$uriToBuild->setQuery(
			array_diff_key(
				$uriToBuild->getQuery(true),
				$platformUri->getQuery(true)
			)
		);

		return explode(
			'/',
			$sef
		);

	}
}

