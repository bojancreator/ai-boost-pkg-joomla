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

namespace Weeblr\Forseo\Helper;

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\Factory;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Config extends Base\Base
{
	/**
	 * Retrieve an API key, starting with user provided and defaulting
	 * to ours.
	 *
	 * @return string
	 */
	public function getMapsApiKey()
	{
		$key = $this->factory->getThis('forseo.config', 'sd')->get('googleMapsApiKey');

		return empty($key)
			? $this->factory->getThis('forseo.config', 'app')->get('apiKeys.googleMaps.1')
			: $key;
	}
}
