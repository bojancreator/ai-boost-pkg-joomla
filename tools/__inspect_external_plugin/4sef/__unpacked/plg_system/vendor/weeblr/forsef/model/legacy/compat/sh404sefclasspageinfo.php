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

use Joomla\CMS\Uri\Uri;

use Weeblr\Wblib\Forsef\Factory;
use Weeblr\Wblib\Forsef\Base;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Maintain data and handle requests about current
 * page. Accessed through factory:
 *
 */
class Sh404sefClassPageinfo extends Base\Base
{
	const LIVE_SITE_SECURE_IGNORE = 0;
	const LIVE_SITE_SECURE_YES    = 1;
	const LIVE_SITE_SECURE_NO     = -1;

	const LIVE_SITE_NOT_MOBILE = 0;
	const LIVE_SITE_MOBILE     = 1;

	public $shURL                   = '';
	public $currentNonSefUrl        = '';
	public $currentSefUrl           = '';
	public $originalUri             = '';
	public $baseUrl                 = '';
	public $currentLanguageTag      = '';
	public $currentLanguageShortTag = '';
	public $allLangHomeLink         = '';
	public $homeLink                = '';
	public $homeLinks               = array();
	public $homeItemid              = 0;
	public $isMobileRequest         = self::LIVE_SITE_NOT_MOBILE;
	public $httpStatus              = null;
	public $isMultilingual          = null;
	public $pageCanonicalUrl        = '';
	public $joomlaSuffixSetting     = 0;
	public $pageTitle               = '';
	public $pageTitlePr             = '';
	public $pageDescription         = '';

	// pagination management
	public $paginationPrevLink = '';
	public $paginationNextLink = '';

	// store our router instance
	public $router = null;

	// this will be filled up upon startup by system plugin
	// code with the current detected base site url for the request
	// ie: it can be secure, unsecure, for one language or another
	protected $_defaultLiveSite = '';

	public function setDefaultLiveSite($url)
	{
		$this->_defaultLiveSite = $url;
	}

	public function getDefaultLiveSite()
	{
		return $this->_defaultLiveSite;
	}

	public function getDefaultFrontLiveSite()
	{
		return Wb\rTrim(
			$this->_defaultLiveSite,
			'/administrator'
		);
	}

	public function setCurrentLanguage($languageTag)
	{
		$this->currentLanguageTag      = $languageTag;
		$this->currentLanguageShortTag = $this->platform->getLanguageUrlCode($languageTag);
	}

	public function init()
	{
		static $_initialized = false;

		if (!$_initialized)
		{
			$this->joomlaSuffixSetting = $this->platform->getConfig()->get('sef_suffix');
			$this->originalUri         = $this->getURI();
			$uri                       = URI::getInstance();
			$this->currentSefUrl       = $uri->toString();
			$site                      = $uri->toString(array('scheme', 'host', 'port'));
			$this->basePath            = Wb\rTrim(Wb\lTrim($uri->base(), $site), '/');
			$this->detectMultilingual();
			$this->setCurrentLanguage($this->platform->getDefaultLanguageTag());
			$this->_defaultLiveSite = Wb\rTrim($uri->base(), '/');
			$this->loadHomepages();
			$_initialized = true;
		}
	}

	public function detectMultilingual()
	{
		if ($this->platform->isFrontend())
		{
			$this->isMultilingual = $this->platform->isMultilingual()
									&&
									!$this->platform->hasFalang()
				? 'joomla'
				: false;
		}
	}

	public function loadHomepages()
	{
		$app = JFactory::getApplication();
		if ($app->isAdmin())
		{
			return;
		}

		// store default links in each language
		$defaultLanguage = $this->platform->getDefaultLanguageTag();
//		$languages       = Sh404sefHelperLanguage::getActiveLanguages();
		$languages = $this->platform->getFrontendLanguages();
		if ($this->isMultilingual === false || $this->isMultilingual == 'joomla')
		{
			$menu = JFactory::getApplication()->getMenu('site');
			foreach ($languages as $language)
			{
				$menuItem = $menu->getDefault($language->lang_code);
				if (!empty($menuItem))
				{
					$this->homeLinks[$language->lang_code] = $this->_prepareLink($menuItem);
					if ($language->lang_code == $defaultLanguage)
					{
						$this->homeLink   = $this->homeLinks[$language->lang_code];
						$this->homeItemid = $menuItem->id;
					}
				}
			}

			// find about the "All" languages home link
			$menuItem = $menu->getDefault('*');
			if (!empty($menuItem))
			{
				$this->allLangHomeLink = $this->_prepareLink($menuItem);
			}
		}
		else
		{
			// trouble starts
			$db    = \ShlDbHelper::getDb();
			$query = $db->getQuery(true);
			$query->select('id,language,link');
			$query->from('#__menu');
			$query->where('home <> 0');
			try
			{
				$db->setQuery($query);
				$items = $db->loadObjectList('language');
			}
			catch (\Exception $e)
			{
				\ShlSystem_Log::error('sh404sef', '%s::%s::%d: %s', __CLASS__, __METHOD__, __LINE__, $e->getMessage());
			}
			if (!empty($items))
			{
				if (count($items) == 1)
				{
					$tmp         = array_values($items);
					$defaultItem = $tmp[0];
				}
				if (empty($defaultItem))
				{
					$defaultItem = empty($items[$defaultLanguage]) ? null : $items[$defaultLanguage];
				}
				if (empty($defaultItem))
				{
					$defaultItem = empty($items['*']) ? null : $items['*'];
				}
				foreach ($languages as $language)
				{
					if (!empty($items[$language->lang_code]))
					{
						$this->homeLinks[$language->lang_code] = $this->_prepareLink($items[$language->lang_code]);
					}
					else
					{
						// no menu item for home link
						// let's try to  build one
						$this->homeLinks[$language->lang_code] = $this
							->_prepareLink($defaultItem, $this->platform->getLanguageUrlCode($language->lang_code));
					}

					if ($language->lang_code == $defaultLanguage)
					{
						$this->homeLink        = $this->homeLinks[$language->lang_code];
						$this->homeItemid      = $defaultItem->id;
						$this->allLangHomeLink = shCleanUpLang($this->homeLinks[$language->lang_code]);
					}
				}
			}
		}

		\ShlSystem_Log::debug('sh404sef', 'HomeLinks = %s', print_r($this->homeLinks, true));
	}

	protected function _prepareLink($menuItem, $forceLanguage = null)
	{
		$link     = Sh404sefHelperUrl::setUrlVar($menuItem->link, 'Itemid', $menuItem->id);
		$linkLang = Sh404sefHelperUrl::getUrlLang($link);
		if (empty($linkLang))
		{
			// if no language in link, use current, except if 'All', in which case use actual default
			if (empty($forceLanguage))
			{
				$itemLanguage = $menuItem->language == '*' ? Sh404sefHelperLanguage::getDefaultLanguageSef()
					: Sh404sefHelperLanguage::getUrlCodeFromTag($menuItem->language);
			}
			else
			{
				$itemLanguage = $forceLanguage;
			}
			$link = Sh404sefHelperUrl::setUrlVar($link, 'lang', $itemLanguage);
		}

		return $link;
	}

	protected function getURI()
	{
		// First we need to detect the URI scheme.
		if (isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) != 'off'))
		{
			$scheme = 'https://';
		}
		else
		{
			$scheme = 'http://';
		}

		/*
		 * There are some differences in the way that Apache and IIS populate server environment variables.  To
		 * properly detect the requested URI we need to adjust our algorithm based on whether or not we are getting
		 * information from Apache or IIS.
		 */
		// Define variable to return
		$uri = '';

		// If PHP_SELF and REQUEST_URI are both populated then we will assume "Apache Mode".
		if (!empty($_SERVER['PHP_SELF']) && !empty($_SERVER['REQUEST_URI']))
		{
			// The URI is built from the HTTP_HOST and REQUEST_URI environment variables in an Apache environment.
			$uri = $scheme . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		}
		// If not in "Apache Mode" we will assume that we are in an IIS environment and proceed.
		elseif (isset($_SERVER['HTTP_HOST']))
		{
			// IIS uses the SCRIPT_NAME variable instead of a REQUEST_URI variable... thanks, MS
			$uri = $scheme . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];

			// If the QUERY_STRING variable exists append it to the URI string.
			if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING']))
			{
				$uri .= '?' . $_SERVER['QUERY_STRING'];
			}
		}

		return JString::trim($uri);
	}
}
