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

namespace Weeblr\Forseo\Model;

use Weeblr\Wblib\Forseo\Wb;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Integrationsconfig extends Config
{
	/**
	 * Helper to figure out if access to Google Search console is enabled and
	 * properly configured.
	 *
	 * @return bool
	 */
	public function isGoogleSearchConsoleActive()
	{
		$property = Wb\arrayEnsure(
			$this->get('gscProperty', [])
		);

		return $this->isTruthy('gscEnabled')
			   &&
			   !empty($property)
			   &&
			   count($property) > 0
			   &&
			   'ready' === $this->get('gscConfigStep', 'notStarted');
	}
}
