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

namespace Weeblr\Forsef\Model;

use Weeblr\Forsef\Data;
use Weeblr\Forsef\Helper;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Db;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Extensionsconfig extends Config
{
	const EXTENSIONS_USING_PLATFORM_ROUTER = [
//		'tags'
	];

	/**
	 * Get a specific configuration item through nested keys.
	 *
	 * @param array $keys    An array of nested keys to get to the desired config item
	 * @param mixed $default Optional default value if config not set
	 *
	 * @return mixed
	 */
	public function get($keys, $default = null)
	{
		switch (true)
		{
			case is_string($keys) && Wb\endsWith($keys, 'ProcessMode'):
				$value = Wb\startsWith($keys, self::EXTENSIONS_USING_PLATFORM_ROUTER)
					? Data\Config::PROCESS_USE_JOOMLA
					: parent::get($keys, $default);
				break;
			case is_string($keys) && Wb\endsWith($keys, 'ProcessModeJoomlaSefWithMenu'):
				$value = Wb\startsWith($keys, self::EXTENSIONS_USING_PLATFORM_ROUTER)
					? true
					: parent::get($keys, $default);
				break;
			default:
				$value = parent::get($keys, $default);
		}

		return $value;
	}

	/**
	 * Loads hardcoded default configuration value from /config/{scope}.php.
	 *
	 * @return $this
	 */
	public function loadDefaults()
	{
		$file = FORSEF_APP_PATH . '/config/' . $this->scope . '.php';
		if (file_exists($file))
		{
			$defaults            = include $file;
			$this->defaults      = Wb\arrayGet($defaults, 'config', []);
			$this->doNotStore    = Wb\arrayGet($defaults, 'doNotStore', []);
			$this->persist       = Wb\arrayGet($defaults, 'persist', true);
			$this->enforcedTypes = Wb\arrayGet($defaults, 'enforcedTypes', []);
			$this->config        = empty($this->defaults) || !is_array($this->defaults) ? $this->config : $this->defaults;
		}

		// now create dynamic sections
		$filteredInstalledExtensions = $this->getFilteredInstalledExtensions();
		$helper                      = $this->factory->getA(Helper\Nonsef::class);
		$this->config['available']   = [];
		foreach ($filteredInstalledExtensions as $componentName => $installedExtension)
		{
			$extension                             = $helper->optionToExtension($installedExtension->element);
			$this->config['available'][$extension] = ucfirst($helper->optionToExtension($componentName));

			$perExtensionOptionsMethod = [
				$this,
				$extension . 'Options'
			];
			if (is_callable($perExtensionOptionsMethod))
			{
				call_user_func($perExtensionOptionsMethod);
			}

			// append shared options
			$this->appendSharedOptions($extension);
		}

		return $this;
	}

	/**
	 * Build a list of installed extensons and filter out some of them based on configuraton.
	 *
	 * @return array
	 */
	public function getFilteredInstalledExtensions()
	{
		$bypassedExtensions = $this->get('ignored');
		return array_filter(
			$this->platform->getExtensions('components'),
			function ($extension) use ($bypassedExtensions) {

				return !in_array(
					Wb\lTrim(
						strtolower($extension->element),
						'com_'
					),
					$bypassedExtensions
				);
			}
		);
	}

	private function contentOptions()
	{
		$this->config = array_merge(
			$this->config,
			[
				'contentUseTitleAlias'                 => true,
				'contentUseCategoryAlias'              => true,
				'contentIncludeContentCat'             => Data\Config::CAT_LAST,
				'contentIncludeContentCatCategories'   => Data\Config::CAT_LAST_TWO,
				'contentContentCategoriesSuffix'       => 'all',
				'contentSlugForUncategorizedContent'   => Data\Config::UNCAT_SLUG_ITEM_TITLE,
				'contentInsertContentTableName'        => true,
				'contentContentTableName'              => 'Table',
				'contentInsertContentBlogName'         => false,
				'contentContentBlogName'               => '',
				'contentMultipagesTitle'               => true,
				'contentContentTitleInsertArticleId'   => Data\Config::CONTENT_INSERT_ARTICLE_ID_NONE,
				'contentInsertContentArticleIdCatList' => [],  // list of category ids
				'contentInsertNumericalId'             => false,
				'contentInsertNumericalIdCatList'      => [],
				'contentInsertDate'                    => false,
				'contentInsertDateCatList'             => []
			]
		);
	}

	private function contactOptions()
	{
		$this->config = array_merge(
			$this->config,
			[
				'contactUseContactCatAlias'          => false,
				'contactIncludeContactCat'           => Data\Config::CAT_NONE,
				'contactIncludeContactCatCategories' => Data\Config::CAT_LAST,
				'contactContactCategoriesSuffix'     => 'all',
				'contactSlugForUncategorizedContact' => Data\Config::UNCAT_SLUG_ITEM_TITLE,
			]
		);
	}

	private function weblinksOptions()
	{
		$this->config = array_merge(
			$this->config,
			[
				'weblinksUseWeblinksCatAlias'          => false,
				'weblinksIncludeWeblinksCat'           => Data\Config::CAT_LAST,
				'weblinksIncludeWeblinksCatCategories' => Data\Config::CAT_LAST,
				'weblinksWeblinksCategoriesSuffix'     => 'all',
				'weblinksSlugForUncategorizedWeblinks' => Data\Config::UNCAT_SLUG_ITEM_TITLE,
			]
		);
	}

	private function virtuemartOptions()
	{
		$this->config = array_merge(
			$this->config,
			[
				'virtuemartInsertShopName'         => false,
				'virtuemartUseMenuItems'           => true,
				'virtuemartWhichProductDetailsCat' => Data\Config::CAT_ALL_NESTED,
			]
		);
	}

	private function appendSharedOptions($extension)
	{
		$sharedConfig = in_array(
			$extension,
			self::EXTENSIONS_USING_PLATFORM_ROUTER
		)
			? [
				$extension . 'ProcessMode'                  => Data\Config::PROCESS_USE_JOOMLA,
				$extension . 'ProcessModeJoomlaSefWithMenu' => true,
				$extension . 'Prefix'                       => ''
			]
			: [
				$extension . 'ProcessMode'                  => Data\Config::PROCESS_NORMAL,
				$extension . 'ProcessModeJoomlaSefWithMenu' => false,
				$extension . 'Prefix'                       => ''
			];

		$this->config = array_merge(
			$this->config,
			$sharedConfig
		);
	}
}
