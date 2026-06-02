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

use Weeblr\Forseo\Model\Extensions;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Sh404sefconfig extends Config
{
	/**
	 * Load configuration from database.
	 *
	 * @return $this
	 */
	public function load()
	{
		parent::load();

		foreach ($this->detectSh404sefData() as $key => $value)
		{
			$this->set(
				$key,
				$value
			);
		}

		return $this;
	}

	/**
	 * Look for sh404SEF URLs table with custom URLs to import.
	 *
	 * @param bool $customOnly
	 * @return array
	 */
	private function detectSh404sefData($customOnly = true)
	{
		try
		{
			$sh404sefAvailable = defined('SH404SEF_IS_RUNNING');
			$forsefAvailable   = defined('4SEF_IS_RUNNING')
								 &&
								 \Forsef::isEnabled();

			if (
				!$sh404sefAvailable
				&&
				!$forsefAvailable
			) {
				// we require either sh404SEF or 4SEF to be running,
				// in order to convert non-SEF URLs to SEF as data
				// is stored against non-SEF in sh404SEF and against
				// SEF in 4SEO.
				return [
					'canImportMetaFromSh404sef'    => 0,
					'canImportAliasesFromSh404sef' => 0,
				];
			}

			$db = $this->factory->getThe('db');

			$metaCount = $db->count('#__sh404sef_metas');

			$aliasesCount = $db->count(
				'#__sh404sef_aliases',
				'*',
				$db->qn('target_type') . ' = ' . $db->q(Extensions\Sh404sef::ALIAS_REDIRECT)
				. ' or '
				. $db->qn('target_type') . ' = ' . $db->q(Extensions\Sh404sef::ALIAS_CANONICAL)
			);
		}
		catch (\Throwable $e)
		{
			$metaCount    = 0;
			$aliasesCount = 0;
		}

		return [
			'canImportMetaFromSh404sef'    => $metaCount,
			'canImportAliasesFromSh404sef' => $aliasesCount,
		];
	}
}
