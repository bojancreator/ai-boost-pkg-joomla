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

namespace Weeblr\Wblib\Forsef\System;

use Weeblr\Wblib\Forsef\Factory;
use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Base;

// no direct access
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

class Language extends Base\Base
{
	/**
	 * Builds a record with the current language code to use and the corresponding strings.
	 *
	 * 1. Load default language file {APP_ROOT}/locales/en/{file_name}.json
	 * 2. Look for a json file with strings in {APP_ROOT}/locales/{languageCode}/{file_name}.json
	 * 3/ Look for overrides in {SITE_ROOT}/libraries/weeblr/{appId}/locales/{languageCode}/{file_name}.json
	 * 3. Merge the translated strings over the original EN language as our translation system only
	 *    include translated strings
	 * 3. Get language strings overrides from the platform for {current_language}
	 * 4. Merge overrides with built-in strings
	 * 4. Returns an array with strings
	 *
	 * @param   string  $appId                       Short name used to identify the app. Similar to "forseo", "forsef".
	 *                                               Used to identify override folder as in /libraries/weeblr/{appId}/locales/{languageCode}/{file_name}.json
	 * @param   string  $appRootPath
	 * @param   string  $platformOverridesKey        Component key for which overrides may be found. Similar to "COM_FORSEO"
	 * @param []|string $files
	 * @param   string  $languageTag
	 *
	 * @return array
	 */

	public function getJsLanguageStrings($appId, $appRootPath, $platformOverridesKey, $files, $languageTag)
	{
		$languageStrings = [];

		foreach (Wb\arrayEnsure($files) as $file)
		{
			$languageStrings = array_replace_recursive(
				$languageStrings,
				$this->doGetJsLanguageStrings($appId, $appRootPath, $platformOverridesKey, $file, $languageTag)
			);
		}

		return $languageStrings;
	}

	/**
	 * Builds a record with the language code to use and the corresponding strings.
	 *
	 * 1. Load default language file {APP_ROOT}/locales/en/{file_name}.json
	 * 2. Look for a json file with strings in:
	 *   {APP_ROOT}/locales/{language_tag}/{file_name}.json
	 *   {APP_ROOT}/locales/{language_family_code}/{file_name}.json
	 *   /libraries/weeblr/{appId}/locales/{language_tag}/{file_name}.json
	 *   /libraries/weeblr/{appId}/locales/{language_family_code}/{file_name}.json
	 * 3. Merge the translated strings over the original EN language as our translation system only
	 *    include translated strings
	 * 3. Get language strings overrides from the platform for {current_language}
	 * 4. Merge overrides with built-in strings
	 * 4. Returns and array with tag and strings
	 *
	 * @param   string  $appId                       Short name used to identify the app. Similar to "forseo", "forsef".
	 *                                               Used to identify override folder as in /libraries/weeblr/{appId}/locales/{languageCode}/{file_name}.json
	 * @param   string  $appRootPath
	 * @param   string  $platformOverridesKey        Component key for which overrides may be found. Similar to "COM_FORSEO"
	 * @param   string  $file
	 * @param   string  $languageTag
	 *
	 * @return array
	 */
	private function doGetJsLanguageStrings($appId, $appRootPath, $platformOverridesKey, $file, $languageTag)
	{
		$hook = Factory::get()->getThe('hook');

		// 0 - Filter possible rootPath
		$possibleRootPaths = $hook->filter(
			$appId . '_languages_overrides_root_path',
			[
				'app'       => $appRootPath,
				'libraries' => Wb\slashTrimJoin(
					[
						$this->platform->getRootPath(),
						'libraries/weeblr',
						$appId
					]
				)
			]
		);

		$strings = [];

		// 1 - Source
		$localeFile         = $appRootPath . '/locales/en/' . $file . '.json';
		$rawlanguageStrings = \file_get_contents($localeFile);
		$strings['source']  = \json_decode($rawlanguageStrings, true);

		// 2 - Translations from the various possible sources
		$bits           = \explode('-', $languageTag);
		$languageFamily = $bits[0];
		foreach ($possibleRootPaths as $locationId => $possibleRootPath)
		{
			$possibleFilesFullPathNames = [
				$possibleRootPath . '/locales/' . $languageTag . '/' . $file . '.json',
				$possibleRootPath . '/locales/' . $languageFamily . '/' . $file . '.json'
			];

			foreach ($possibleFilesFullPathNames as $possibleFileFullPathName)
			{
				if (\file_exists($possibleFileFullPathName))
				{
					$rawlanguageStrings = \file_get_contents($possibleFileFullPathName);
					break;
				}
			}

			$strings[$locationId] = \json_decode($rawlanguageStrings, true);
		}

		// 3 - Possible user overrides from Joomla language feature.
		$platformOverrides = $this->platform->getLanguageOverrides($platformOverridesKey);

		// ready to inject in page
		return \array_replace_recursive(
			Wb\arrayEnsure($strings['source']),
			Wb\arrayEnsure($strings['app']),
			Wb\arrayEnsure($strings['libraries']),
			$platformOverrides
		);
	}
}