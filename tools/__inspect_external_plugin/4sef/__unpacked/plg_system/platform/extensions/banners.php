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

use Joomla\CMS\Uri;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Banners extends Base
{
	/**
	 * Builds the SEF URL for a non-sef.
	 *
	 * @param Uri\Uri $uriToBuild
	 * @param Uri\Uri $platformUri
	 * @param Uri\Uri $originalUri
	 *
	 * @return \array
	 * @throws \Exception
	 */
	public function build($uriToBuild, $platformUri, $originalUri)
	{
		$sefSegments = parent::build($uriToBuild, $platformUri, $originalUri);

		if (empty($sefSegments))
		{
			$sefSegments[] = 'banners';
		}

		$slug = $uriToBuild->getVar('task', '') . $uriToBuild->getVar('id', '');

		$sefSegments[] = empty($slug)
			? '/'
			: $slug;

		return $sefSegments;
	}
}

