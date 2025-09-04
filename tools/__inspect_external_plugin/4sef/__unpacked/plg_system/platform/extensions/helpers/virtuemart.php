<?php
/**
 * @ant_title_ant@
 *
 * @author       @ant_author_ant@
 * @copyright    @ant_copyright_ant@
 * @package      @ant_package_ant@
 * @license      @ant_license_ant@
 * @version      @ant_version_ant@
 * @date        @ant_current_date_ant@
 */

namespace Weeblr\Forsef\Platform\Extensions\Helpers;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * A class to handle compatibility issues between VM version 3 and 4, where the routing helper
 * has been changed from an object to a fully static class.
 */
class Virtuemart
{
	static private $instance;
	static private $legacyHelper;
	static private $vmVersion;
	static private $useLegacy = false;

	const MIN_NON_LEGACY_VERSION = '4.0';

	public static function getInstance(&$query)
	{
		if (empty(self::$instance))
		{
			self::$instance = new self($query);
		}

		return self::$instance;
	}

	public function __construct(&$query)
	{
		if (!class_exists('\vmVersion'))
		{
			$versionFilePath = JPATH_ROOT . '/administrator/components/com_virtuemart/version.php';
			if (!file_exists($versionFilePath))
			{
				throw new \Exception('Trying to use the Virtuemart routing helper, but Virtuemart is not available.');
			}
			include_once $versionFilePath;
		}

		self::$vmVersion = \vmVersion::$RELEASE;
		self::$useLegacy = self::isLegacy();

		if (self::$useLegacy)
		{
			// VM 3, directly use the VM helper
			self::$legacyHelper = \vmrouterHelper::getInstance($query);
		}

		// VM version 4: helper has been turned into a fully static class (WTF?)
		// We'll mimic accessing the version 3 helper. getInstance has been turned into
		// some sort of init routine.
		\vmrouterHelper::getInstance($query);
	}

	public function getVersion()
	{
		return self::$vmVersion;
	}

	public function isLegacy($minVersion = self::MIN_NON_LEGACY_VERSION)
	{
		return version_compare(
			self::$vmVersion,
			$minVersion,
			'lt'
		);
	}

	public function getParentProductcategory($productId)
	{
		return self::$useLegacy
			? self::$legacyHelper->getParentProductcategory($productId)
			: \vmrouterHelper::getParentProductcategory($productId);

	}

	public function getCategoryNames($catId)
	{
		if (self::$useLegacy)
		{
			$currentFullSetting       = self::$legacyHelper->full;
			self::$legacyHelper->full = true;
			$names                    = self::$legacyHelper->getCategoryNames($catId);
			self::$legacyHelper->full = $currentFullSetting;
		}
		else
		{
			$currentFullSetting    = \vmrouterHelper::$full;
			\vmrouterHelper::$full = true;
			$names                 = \vmrouterHelper::getCategoryNames($catId);
			\vmrouterHelper::$full = $currentFullSetting;
		}

		return $names;
	}

	public function getProductName($virtuemart_product_id)
	{
		return self::$useLegacy
			? self::$legacyHelper->getProductName($virtuemart_product_id)
			: \vmrouterHelper::getProductName($virtuemart_product_id);
	}

	public function lang($item)
	{
		return self::$useLegacy
			? self::$legacyHelper->lang($item)
			: \vmrouterHelper::lang($item);
	}

	public function getCategoryRoute($catId, $manId)
	{
		return self::$useLegacy
			? self::$legacyHelper->getCategoryRoute($catId, $manId)
			: \vmrouterHelper::getCategoryRoute($catId, $manId);
	}

	public function menu($itemName)
	{
		return self::$useLegacy
			? self::$legacyHelper->menu[$itemName]
			: \vmrouterHelper::$menu[$itemName];
	}

	public function limit()
	{
		return self::$useLegacy
			? self::$legacyHelper::$limit
			: \vmrouterHelper::$limit;
	}

	public function buildRoute($query)
	{
		return self::$useLegacy
			? self::$legacyHelper->buildRoute($query)
			: \vmrouterHelper::buildRoute($query);
	}

	public function preprocess(&$query)
	{
		if (self::$useLegacy)
		{
			$query = self::$legacyHelper->preprocess($query);
		}

		if (is_callable([
			'\vmrouterHelper',
			'preprocess'
		]))
		{
			\vmrouterHelper::preprocess($query);
		}
	}
}
