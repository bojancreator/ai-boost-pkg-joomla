<?php
/**
 * Project:                 4SEF
 *
 * @package                 4SEF
 * @copyright               Copyright Weeblr llc - 2022 -2025
 * @author                  Yannick Gaultier - Weeblr llc
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 @build_version_full_build@
 *
 * 2025-06-02
 */

namespace Weeblr\Wblib\Forsef\Platform;

use Weeblr\Wblib\Forsef\Factory as wblFactory;
use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Joomla\Uri\Uri as wblUri;
use Weeblr\Wblib\Forsef\Joomla\Utilities\ArrayHelper;

use Joomla\CMS\Table\Table;
use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserHelper;
use Joomla\CMS\Helper\UserGroupsHelper;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Cache\Cache;
use Joomla\Registry\Registry;
use Joomla\CMS\Plugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Router\SiteRouter;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Filter;
use Joomla\String\StringHelper;
use Joomla\CMS\Language;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Language\LanguageFactoryInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Component\Fields\Administrator\Helper;
use Joomla\Utilities\IpHelper;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Component\Menus\Administrator\Helper as J4MenuHelper;
use Joomla\Database\DatabaseFactory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

use Joomla\CMS\Environment\Browser;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 */
class JoomlaPlatform extends Platform implements Platforminterface
{
	protected $_name = 'joomla';

	protected $app         = null;
	protected $diContainer = null;

	private static $hooks = [];

	private static $hooksStack = [];

	private static $hooksRuns = [];

	private static $canonicalRoot = null;

	private static $majorVersion = null;
	private static $version      = null;
	private static $isJ3         = false;

	public function __construct()
	{
		$this->app = Factory::getApplication();
		if (is_null(self::$majorVersion))
		{
			if (version_compare(\JVERSION, '4.0', '<'))
			{
				$versionClassName = '\JVersion';
				self::$isJ3       = true;
			}
			else
			{
				$versionClassName = \Joomla\CMS\Version::class;
			}
			$versionObject      = new $versionClassName;
			self::$majorVersion = $versionObject::MAJOR_VERSION;
			self::$version      = $versionObject->getShortVersion();
		}

		if (!self::$isJ3)
		{
			$this->diContainer = \Joomla\CMS\Factory::getContainer();
		}
	}

	/**
	 * Run any initialization code.
	 */
	public function boot()
	{
		if ($this->isFrontend())
		{
			$this->getRouter('site')->attachParseRule(
				[
					$this,
					'parseRuleDropApiPrefix'
				],
				SiteRouter::PROCESS_BEFORE
			);
		}
	}

	/**
	 * Get the app router using Joomla-version dependent-method.
	 *
	 * @param string $name
	 *
	 * @return \Joomla\CMS\Router\SiteRouter
	 */
	public function getRouter($name)
	{
		return self::$isJ3
			? $this->app->getRouter($name)
			: Factory::getContainer()->get(SiteRouter::class);
	}

	/**
	 * When a request is made to the API, with a non-empty api slug,
	 * this will cause a 404,
	 * so we must remove that prefix from the request.
	 *
	 * @return void
	 */
	public function parseRuleDropApiPrefix(&$router, &$uri)
	{
		$factory = wblFactory::get();
		$prefix  = $factory->getThe('api')->getSlug();
		if (
			$factory->getThe('hook')->filter(
				'wblib_enable_api',
				true
			)
			&&
			$prefix === $uri->getPath()
		)
		{
			$uri->setPath('');
		}
	}

	/**
	 * Detects whether running on Joomla.
	 *
	 * @return bool
	 */
	public function detect()
	{
		$detected =
			defined('_JEXEC')
			&&
			defined('JPATH_BASE');

		return $detected;
	}

	/**
	 * Returns major version of the running platform
	 */
	public function majorVersion()
	{
		return self::$majorVersion;
	}

	/**
	 * Return full version of the running platform
	 */
	public function version()
	{
		return self::$version;
	}

	public function getLayoutOverridePath()
	{
		return [];
	}

	public function getUserImagesPath($absolute = true)
	{
		$prefix = $absolute
			? \JPATH_ROOT
			: '';

		// user can have a custom path set in media manager
		return $prefix . '/' . ComponentHelper::getParams('com_media')->get('image_path', 'images');
	}

	public function getConfig()
	{
		return Factory::getConfig();
	}

	public function getAppParams()
	{
		return $this->app->getParams();
	}

	public function getUniqueId()
	{
		return Factory::getConfig()->get('secret');
	}

	public function getUser($id = null)
	{
		if (empty($id))
		{
			return $this->getCurrentUser();
		}

		if ($this->majorVersion() < 4)
		{
			return Factory::getUser($id);
		}

		$instance = Factory::getApplication()->getSession()->get('user');

		if (\is_null($id))
		{
			if (!($instance instanceof User))
			{
				$instance = User::getInstance();
			}
		}
		elseif (!($instance instanceof User) || \is_string($id) || $instance->id !== $id)
		{
			// Check if we have a string as the id or if the numeric id is the current instance
			$instance = User::getInstance($id);
		}

		return $instance;

	}

	private function getCurrentUser()
	{
		return $this->majorVersion() < 4
			? Factory::getUser()
			: Factory::getApplication()->getIdentity();
	}

	public function isGuest()
	{
		return (bool)$this->getUser()->guest;
	}

	public function verifyPassword($clearTextPassword)
	{
		if (empty($clearTextPassword))
		{
			return false;
		}

		if ($this->isGuest())
		{
			return false;
		}

		$user           = $this->getCurrentUser();
		$hashedPassword = $user->password;
		if (empty($hashedPassword))
		{
			return false;
		}

		return UserHelper::verifyPassword(
			$clearTextPassword,
			$hashedPassword
		);
	}

	public function getUsersGroups()
	{
		$options = array_values(UserGroupsHelper::getInstance()->getAll());

		for ($i = 0, $n = \count($options); $i < $n; $i++)
		{
			$options[$i]->value = (int)$options[$i]->id;
			$options[$i]->text  = str_repeat('- ', $options[$i]->level) . $options[$i]->title;
		}

		return $options;
	}

	public function getIp()
	{
		return System\Http::getIpAddress();
	}

	public function getBrowser()
	{
		return Browser::getInstance();
	}

	public function sanitizeInput($type, $input)
	{
		switch ($type)
		{
			case 'string':
				$output = Filter\InputFilter::getInstance()->clean($input, $type);
				break;
			case 'html':
				$output = Filter\InputFilter::getInstance(null, null, 1, 1)->clean($input, $type);
				break;
			default:
				$output = $input;
				break;
		}

		return $output;
	}

	public function getCSRFToken($forceNew = false)
	{
		return Session::getFormToken($forceNew);
	}

	public function checkCSRFToken($method = 'post')
	{
		$method = strtolower($method);
		$token  = $this->getCSRFToken();

		// Check from header first
		if ($token === System\Http::getRequestHeader('x-wblr-csrf-token'))
		{
			return true;
		}

		// Then fallback to HTTP query
		if (!$this->getHttpInput()->$method->get($token, '', 'alnum'))
		{
			return false;
		}

		return true;
	}

	public function getCurrentUrl()
	{
		return Uri::getInstance()->toString();
	}

	public function getCurrentPath()
	{
		return $this->normalizeUrl(
			Uri::getInstance()->getPath()
		);
	}

	public function getCurrentQuery($toArray = false)
	{
		return Uri::getInstance()->getQuery($toArray);
	}

	public function getCurrentContentType()
	{
		return $this->getHttpInput()->getCmd('option');
	}

	public function getCurrentFormat()
	{
		return $this->getHttpInput()->getCmd('format');
	}

	public function getCurrentRequestCategory()
	{
		$input       = $this->getHttpInput();
		$contentType = $input->getCmd('option');
		$view        = $input->getCmd('view');
		$layout      = $input->getCmd('layout');
		$id          = $input->getInt('id');
		$catid       = $input->getInt('catid');

		if ('category' == $view)
		{
			$catid = $id;
		}

		if (empty($catid))
		{
			switch (true)
			{
				case 'com_content' === $contentType && 'article' === $view:
					$table = Table::getInstance('Content');
					$table->load($id);
					$catid = $table->catid;
					break;
				case 'com_contact' === $contentType && 'contact' === $view:
					if ($this->majorVersion() < 4)
					{
						Table::addIncludePath(\JPATH_ROOT . '/administrator/components/com_contact/tables');
						$table = Table::getInstance('Contact', 'ContactTable');
					}
					else
					{
						$table = Table::getInstance('ContactTable', '\\Joomla\\Component\\Contact\\Administrator\\Table\\');
					}
					$table->load($id);
					$catid = $table->catid;
					break;
				case 'com_newsfeeds' === $contentType && 'newsfeed' === $view:
					if ($this->majorVersion() < 4)
					{
						Table::addIncludePath(\JPATH_ROOT . '/administrator/components/com_newsfeeds/tables');
						$table = Table::getInstance('Newsfeed', 'NewsfeedsTable');
					}
					else
					{
						$table = Table::getInstance('NewsfeedTable', '\\Joomla\\Component\\Newsfeeds\\Administrator\\Table\\');
					}
					$table->load($id);
					$catid = $table->catid;
					break;
			}
		}

		if (!empty($catid))
		{
			$allCategories = $this->getCategories($contentType);
			foreach ($allCategories as $category)
			{
				if ($category->id == $catid)
				{
					return $category;
				}
			}
		}

		return false;
	}

	public function getSitename()
	{
		return $this->app->get('sitename');
	}

	/**
	 * Maybe enforce presence of www prefix.
	 *
	 * @param           $url
	 * @param bool      $enforce
	 * @param string    $prefix
	 *
	 * @return mixed|string
	 */
	public function enforceUrlPrefix($url, $enforce = false, $prefix = 'www')
	{
		if (!empty($url) && !is_null($enforce))
		{
			$tls    = Wb\startsWith($url, 'https://');
			$url    = Wb\lTrim(
				$url,
				[
					'https://',
					'http://'
				]
			);
			$url    = Wb\lTrim($url, 'www.'); // make sure no prefix
			$prefix = empty($prefix) ? '' : $prefix . '.';
			$url    = 'http' . ($tls ? 's' : '') . '://' . $prefix . $url;
		}

		return $url;
	}

	public function getBaseUrl($pathOnly = true)
	{
		return Uri::base($pathOnly);
	}

	public function getRootUrl($pathOnly = true)
	{
		return Uri::root($pathOnly);
	}

	public function getHomeUrl($normalized = false)
	{
		static $homeUrl = null;
		static $normalizedHomeUrl = null;

		if (is_null($homeUrl))
		{
			$lang_code = $this->getDefaultLanguageTag();
			$item      = $this->app->getMenu('site')->getDefault($lang_code);
			$homeUrl   = StringHelper::trim(
				$this->route('index.php?Itemid=' . $item->id),
				'/'
			);
			$homeUrl   = Wb\rTrim($homeUrl, '/index.php');

			// suppress the base path
			$normalizedHomeUrl = $this->normalizeUrl($homeUrl);
		}

		return $normalized
			? $normalizedHomeUrl
			: $homeUrl;
	}

	public function isHomePage()
	{
		static $isHomePage = null;

		if (is_null($isHomePage))
		{
			$currentUrl = Uri::getInstance()->toString(
				[
					'path',
					'query'
				]
			);

			$currentUrl = $this->normalizeUrl(
				StringHelper::trim($currentUrl, '/')
			);
			$isHomePage = $this->getHomeUrl(true) == $currentUrl;
		}

		return $isHomePage;
	}

	/**
	 * Builds the array of variables associated with the home page menu item in each language.
	 *
	 * @param string $langTag
	 *
	 * @return mixed
	 */
	private function getHomeLinksVars($langTag = null)
	{
		static $defaultLanguageTag;
		static $homeLinksVars;

		if (is_null($defaultLanguageTag))
		{
			$defaultLanguageTag = $this->getDefaultLanguageTag();

			$menus                          = $this->getMenu('site');
			$homeLinks                      = [];
			$defaultMenuItem                = $menus->getDefault($defaultLanguageTag);
			$homeLinks[$defaultLanguageTag] = System\Route::appendVarToQueryString(
				$defaultMenuItem->link,
				'Itemid',
				$defaultMenuItem->id
			);
			$frontendLanguages              = $this->getFrontendLanguages();
			foreach ($frontendLanguages as $frontendLanguage)
			{
				$languageTag = $frontendLanguage->lang_code;
				if ($defaultLanguageTag !== $languageTag)
				{
					$menuItem                = $menus->getDefault($languageTag);
					$homeLinks[$languageTag] = empty($menuItem)
						? $homeLinks[$defaultLanguageTag]
						: System\Route::appendVarToQueryString(
							$menuItem->link,
							'Itemid',
							$menuItem->id
						);
				}
			}

			foreach ($homeLinks as $languageTag => $homeLink)
			{
				parse_str(
					Wb\lTrim($homeLink, 'index.php?'),
					$homeLinksVars[$languageTag]
				);

				$homeLinksVars[$languageTag] = empty($homeLinksVars[$languageTag])
					? []
					: $homeLinksVars[$languageTag];
			}
		}

		if (is_null($langTag))
		{
			return $homeLinksVars;
		}

		if (
			empty($langTag)
			||
			empty($homeLinksVars[$langTag])
		)
		{
			return $homeLinksVars[$defaultLanguageTag];
		}

		return $homeLinksVars[$langTag];
	}

	/**
	 * Whether the page described by an array of non-sef variables is the home page.
	 *
	 * @param array $vars
	 * @param bool  $withoutPagination
	 *
	 * @return bool|mixed
	 */
	public function isHomepageFromVars($vars, $withoutPagination = true)
	{
		if (empty($vars))
		{
			return false;
		}

		$homeLinkVars = $this->getHomeLinksVars();
		if ($withoutPagination)
		{
			unset($homeLinkVars['limit']);
			unset($homeLinkVars['limitstart']);
			unset($vars['limit']);
			unset($vars['limitstart']);
		}

		return empty(array_diff_assoc(
			$vars,
			$homeLinkVars
		));
	}

	/**
	 * Whether the page described by an array of non-sef variables is the default page
	 * in any active frontend language.
	 *
	 * @param array $vars
	 * @param bool  $withoutPagination
	 *
	 * @return bool|mixed
	 */
	public function isAnyHomepageFromVars($vars, $withoutPagination = true)
	{
		$vars = empty($vars)
			? []
			: $vars;

		// filter out incoming vars, array_diff_keys cannot handle array vars
		foreach ($vars as $value)
		{
			if (is_array($value))
			{
				return false;
			}
		}

		$homeLinkVars = $this->getHomeLinksVars();
		if ($withoutPagination)
		{
			unset($homeLinkVars['limit']);
			unset($homeLinkVars['limitstart']);
			unset($vars['limit']);
			unset($vars['limitstart']);
		}

		foreach ($homeLinkVars as $homeLinkVar)
		{
			if (empty(array_diff_assoc(
				$vars,
				$homeLinkVar
			)))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the passed path matches one of the home pages URLs.
	 * Can be empty for default language or - typycally, a language code for other languages.
	 *
	 * @param $path
	 *
	 * @return bool
	 */
	public function isAnyHomepagePath($path)
	{
		static $homePagesSlugs;

		if (is_null($homePagesSlugs))
		{
			$homePagesSlugs = [''];
			if ($this->isMultilingual())
			{
				$languages = $this->getFrontendLanguages();
				foreach ($languages as $language)
				{
					$homePagesSlugs[] = $language->sef;
				}
			}
		}

		return in_array(
			Wb\rTrim($path, '/'),
			$homePagesSlugs
		);
	}

	public function normalizeUrl($url, $removeLeadingSlash = true)
	{
		static $baseUrl = null;
		static $fqdnBaseUrl = null;

		if (is_null($baseUrl))
		{
			$baseUrl = Wb\rTrim(
				$this->getBaseUrl(true),
				'/'
			);
			$baseUrl = StringHelper::trim(
				Wb\rTrim(
					$baseUrl,
					'administrator'
				),
				'/'
			);

			$fqdnBaseUrl = StringHelper::trim(
				$this->getBaseUrl(false),
				'/'
			);
			$fqdnBaseUrl = Wb\rTrim(
				$fqdnBaseUrl,
				'administrator'
			);
		}

		$url = Wb\lTrim(
			$url,
			$fqdnBaseUrl
		);

		$url = Wb\lTrim(
			$removeLeadingSlash
				? Wb\lTrim($url, '/')
				: $url,
			$baseUrl
		);

		$url = $this->stripRewritePrefix($url);

		return $removeLeadingSlash
			? Wb\lTrim($url, '/')
			: $url;
	}

	public function stripRewritePrefix($url)
	{
		static $prefix = null;

		if (is_null($prefix))
		{
			$prefix = Factory::getApplication()->get('sef_rewrite');
		}
		if (empty($prefix))
		{
			$url = Wb\lTrim(
				Wb\lTrim($url, '/'),
				'index.php'
			);
		}

		return $url;
	}

	public function getUrlRewritingPrefix()
	{
		static $sefRewritePrefix = null;

		if (is_null($sefRewritePrefix))
		{
			$sefRewrite       = $this->app->get('sef_rewrite');
			$sefRewritePrefix = empty($sefRewrite)
				? '/index.php'
				: '';
		}

		return $sefRewritePrefix;
	}

	/**
	 * Builds and return the canonical domain of the page, taking into account
	 * the optional canonical domain in SEF plugin, and including the base path, if any
	 * Can be called from admin side, to get front end links, by passing true as param
	 *
	 * @param null $isAdmin If === true or === false, disable using JApplication::isAdmin, for testing
	 *
	 * @return null|string
	 */
	public function getCanonicalRoot($isAdmin = null)
	{
		if (is_null(self::$canonicalRoot))
		{
			$sefPlugin      = Plugin\PluginHelper::getPlugin('system', 'sef');
			$sefPlgParams   = new Registry($sefPlugin->params);
			$canonicalParam = StringHelper::trim($sefPlgParams->get('domain', ''));
			if (empty($canonicalParam))
			{
				$base = $this->getRootUrl(false);
				if ($isAdmin === true || ($isAdmin !== false && $this->isBackend()))
				{
					$base = Wb\lTrim(
						$base,
						[
							'administrator/',
							'administrator',
						]
					);
				}
				self::$canonicalRoot = $base;
			}
			else
			{
				self::$canonicalRoot = $canonicalParam;
			}
			self::$canonicalRoot = StringHelper::rtrim(self::$canonicalRoot, '/') . '/';
		}

		return self::$canonicalRoot;
	}

	/**
	 * Wrapper to get the platform DB object regardless of platform version.
	 *
	 * @return mixed
	 */
	public function getPlatformDb()
	{
		return self::$isJ3
			? Factory::getDbo()
			: Factory::getContainer()->get('db');
	}

	public function getExtensions($type)
	{
		static $extensions = [];

		if (!isset($extensions[$type]))
		{
			switch ($type)
			{
				case 'components':
					$db    = $this->getPlatformDb();
					$query = $db->getQuery(true)
								->select($db->quoteName(['extension_id', 'name', 'type', 'element', 'folder', 'client_id', 'enabled', 'params'], ['id', null, null, null, null, null, null, null]))
								->from($db->quoteName('#__extensions'))
								->where($db->quoteName('type') . ' = ' . $db->quote('component'))
								->where($db->quoteName('state') . ' = 0');
					$db->setQuery($query);

					$extensionsList = $db->loadObjectList('name');
					break;
			}

			$extensions[$type] = empty($extensionsList)
				? []
				: $extensionsList;
		}

		return $extensions[$type];
	}

	/**
	 * Save a joomla parameters object to the #__extensions table.
	 *
	 * @param Registry $params
	 * @param array    $options
	 *
	 * @return bool
	 */
	public function saveExtensionParams($params, $options)
	{
		$db    = $this->getPlatformDb();
		$query = $db->getQuery(true)
					->update($db->quoteName('#__extensions'))
					->set($db->quoteName('params') . ' = ' . $db->quote((string)$params));
		foreach ($options as $key => $value)
		{
			$query->where($db->quoteName($key) . ' = ' . $db->quote($value));
		}
		$db->setQuery($query);
		$db->execute();

		return true;
	}

	public function getMenu($client = 'site')
	{
		return $this->app->getMenu('site');
	}

	public function getMenuItems($options = [])
	{
		static $groups = null;

		if (is_null($groups))
		{
			if ($this->majorVersion() < 4)
			{
				\JLoader::register('MenusHelper', \JPATH_ADMINISTRATOR . '/components/com_menus/helpers/menus.php');
				$menuItems = \MenusHelper::getMenuLinks();
			}
			else
			{
				$menuItems = J4MenuHelper\MenusHelper::getMenuLinks();
			}

			$groups  = [];
			$disable = [];
			foreach ($menuItems as $menu)
			{
				// Initialize the group.
				$groups[$menu->title] = [];

				// Build the options array.
				foreach ($menu->links as $link)
				{
					$levelPrefix = str_repeat('- ', max(0, $link->level - 1));

					// Displays language code if not set to All
					if ($link->language !== '*')
					{
						$lang = ' (' . $link->language . ')';
					}
					else
					{
						$lang = '';
					}

					$groups[$menu->title][] = HTMLHelper::_('select.option',
						$link->value, $levelPrefix . $link->text . $lang,
						'value',
						'text',
						\in_array($link->type, $disable)
					);
				}
			}
		}

		return $groups;
	}

	public function isPluginEnabled($group, $name)
	{
		return Plugin\PluginHelper::isEnabled($group, $name);
	}

	public function getCategories($extensions = [], $language = '')
	{
		static $allCategories = null;

		if (is_null($allCategories))
		{
			$db    = $this->getPlatformDb();
			$query = $db->getQuery(true)
						->select($db->quoteName(['id', 'parent_id', 'lft', 'rgt', 'level', 'path', 'extension', 'title', 'alias', 'published', 'metadesc', 'language']))
						->where($db->quoteName('extension') . ' != ' . $db->quote('system'))
						->order($db->quoteName('lft'))
						->from($db->quoteName('#__categories'));
			$db->setQuery($query);
			$allCategories = $db->loadObjectList();
		}

		$extensions = Wb\arrayEnsure($extensions);

		// filter out by extension
		if (!empty($extensions))
		{
			$categories = array_values(
				array_filter(
					$allCategories,
					function ($category) use ($extensions) {
						return in_array(
							$category->extension,
							$extensions
						);
					}
				)
			);
		}
		else
		{
			$categories = $allCategories;
		};

		if (!empty($language))
		{
			$categories = array_values(
				array_filter(
					$categories,
					function ($category) use ($language) {
						return $category->language == $language;
					}
				)
			);
		}

		return empty($categories)
			? []
			: $categories;
	}

	public function getHttpInput()
	{
		return self::$isJ3
			? $this->app->input
			: $this->app->getInput();
	}

	public function getCookiesManager()
	{
		return $this->getHttpInput()->cookie;
	}

	public function getHttpClient($options = [])
	{
		return HttpFactory::getHttp(
			new Registry(
				$options
			)
		);
	}

	public function getHttpTransports()
	{
		return HttpFactory::getHttpTransports();
	}

	public function getMailer()
	{
		return Factory::getMailer();
	}


	public function getCache($type, $options = [])
	{
		return self::$isJ3
			? Cache::getInstance(
				$type,
				$options
			)
			: Factory::getContainer()
					 ->get(\Joomla\CMS\Cache\CacheControllerFactoryInterface::class)
					 ->createCacheController($type, $options);
	}

	/**
	 * Get current request http method in uppercase.
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function getMethod()
	{
		return strtoupper($this->getHttpInput()->getMethod());
	}

	public function getHost()
	{
		return Uri::getInstance()->getHost();
	}

	public function getScheme()
	{
		return Uri::getInstance()->getScheme();
	}

	public function getRootPath()
	{
		return \JPATH_ROOT;
	}

	public function getLogsPath()
	{
		return Factory::getConfig()->get('log_path');
	}

	public function getTempPath()
	{
		return Factory::getConfig()->get('tmp_path');
	}

	/**
	 * Builds the full path to a standard cache folder.
	 * Cached content is stored in:
	 *
	 * {platform_root}/media/{$extension}/cache/{$type}/{secret_key}
	 *
	 * @param string $type
	 * @param string $extension
	 *
	 * @return string
	 */
	public function getCachePath($type, $extension)
	{
		return Wb\slashTrimJoin(
			$this->getRootPath(),
			'media/' . $extension . '/cache',
			strtolower($type),
			$this->getUniqueId()
		);
	}

	/**
	 * Figures out path where to look for layouts overrides. In main template on Joomla 3
	 * and in main and child templates on Joomla 4.
	 *
	 * On CLI, returns an empty array as no template exists.
	 *
	 * @return array
	 */
	public function getLayoutOverridesPath()
	{
		static $paths = null;

		if (is_null($paths))
		{
			$paths = [];

			// Try to get a default template
			$template = $this->isFrontend() || $this->isBackend()
				? $this->app->getTemplate(true)
				: false;

			if (!empty($template))
			{
				// (2) Component template overrides path
				$path = \JPATH_THEMES . '/' . $template->template . '/html/layouts';
				if (file_exists($path))
				{
					$paths[] = $path;
				}

				if (!empty($template->parent))
				{
					// (2.a) Component template overrides path for an inherited template using the parent
					$path = \JPATH_THEMES . '/' . $template->parent . '/html/layouts';
					if (file_exists($path))
					{
						$paths[] = $path;
					}
				}
			}
		}

		return $paths;
	}

	/**
	 * Figure out if the current site has multilingual support enabled,
	 * including using Falang on Joomla 3.
	 *
	 * @return bool
	 */
	public function isMultilingual()
	{
		static $isMultilingual = null;

		if (is_null($isMultilingual))
		{
			$isMultilingual =
				$this->hasFalang()
				||
				PluginHelper::isEnabled('system', 'languagefilter');
		}

		return $isMultilingual;
	}

	/**
	 * Tries to detect if Falang is running.
	 *
	 * @return bool
	 */
	public function hasFalang()
	{
		static $hasFalang = null;

		if (is_null($hasFalang))
		{
			$hasFalang = is_callable(
				[
					'FalangManager',
					'getInstance'
				]
			);
		}

		return $hasFalang;
	}

	/**
	 * Create an associative array of site content languages with the
	 * full language tag being the key.
	 *
	 * @param bool $enabledOnly
	 */
	public function getFrontendLanguages($enabledOnly = true)
	{
		return Language\LanguageHelper::getLanguages();
	}

	/**
	 * Create an associative array of site installed languages with the
	 * full language tag being the key.
	 */
	public function getInstalledLanguages()
	{
		return Language\LanguageHelper::getLanguages();
	}

	public function getDefaultLanguageTag($full = true, $app = 'site')
	{
		static $default;

		if (is_null($default))
		{
			$params  = ComponentHelper::getParams('com_languages');
			$default = $params->get($app, 'en-GB');
		}

		return $default;
	}

	public function getCurrentLanguageTag($full = true)
	{
		return $this->app->getLanguage()->getTag();
	}

	/**
	 * Sets the app language tag.
	 *
	 * @param string $languageTag
	 * @param array  $filesToLoad
	 *
	 * @return void
	 */
	public function setApplicationLanguage($languageTag, $filesToLoad = [])
	{
		// create language object from tag
		if ($this->diContainer)
		{
			$language = $this->diContainer
				->get(LanguageFactoryInterface::class)
				->createLanguage($languageTag);
		}

		if (!$this->diContainer)
		{
			$language = Language\Language::getInstance(
				$languageTag
			);
		}

		foreach ($filesToLoad as $file => $path)
		{
			$language->load(
				$file,
				\JPATH_SITE . $path,
				$languageTag,
				true
			);
		}

		Factory::$language = $language;
	}

	public function setDocumentLanguage($tag)
	{
		return $this->getDocument()->setLanguage($tag);
	}

	public function getCurrentLanguageDirection()
	{
		return $this->app->getLanguage()->isRtl() ? 'rtl' : 'ltr';
	}

	public function getLanguageDirection($lang)
	{
		static $directions;

		if (is_null($directions))
		{
			$languages = Language\LanguageHelper::getLanguages('lang_code');
			foreach ($languages as $lang => $def)
			{
				$languageObject    = Language\Language::getInstance($lang);
				$directions[$lang] = $languageObject->isRTL()
					? 'rtl'
					: 'ltr';
			}
		}

		return empty($directions[$lang]) ? 'ltr' : $directions[$lang];
	}

	public function getLanguageUrlCode($langTag)
	{
		static $codes;

		if (is_null($codes))
		{
			$languages = Language\LanguageHelper::getLanguages('lang_code');
			foreach ($languages as $tag => $def)
			{
				$codes[$tag] = $def->sef;
			}
		}

		return empty($codes[$langTag]) ? $langTag : $codes[$langTag];
	}

	public function getLanguageTagFromUrlCode($urlCode)
	{
		static $tags;

		if (is_null($tags))
		{
			$languages = Language\LanguageHelper::getLanguages('lang_code');
			foreach ($languages as $lang => $def)
			{
				$tags[$def->sef] = $lang;
			}
		}

		return empty($tags[$urlCode]) ? $urlCode : $tags[$urlCode];
	}

	public function getLanguageAssociations($extension,
											$tablename,
											$context,
											$id,
											$pk = 'id',
											$aliasField = 'alias',
											$catField = 'catid',
											$advClause = [],
											$options = [])
	{
		$associations = $this->majorVersion() < 4
			? \JLanguageAssociations::getAssociations($extension,
				$tablename,
				$context,
				$id,
				$pk,
				$aliasField,
				$catField,
				$advClause)
			: Associations::getAssociations($extension,
				$tablename,
				$context,
				$id,
				$pk,
				$aliasField,
				$catField,
				$advClause);

		if (empty($aliasField))
		{
			foreach ($associations as $language => $association)
			{
				$associations[$language]->id = (int)$associations[$language]->id;
			}
		}

		if (
			!empty($options['withTitle'])
			&&
			!empty($associations)
		)
		{
			$db      = $this->getPlatformDb();
			$itemIds = [];
			foreach ($associations as $language => $association)
			{
				$itemIds[] = $associations[$language]->id;
			}

			$itemIds = array_unique($itemIds);
			$itemIds = array_map(
				function ($id) use ($db) {
					return $db->quote($id);
				},
				$itemIds
			);

			$query = $db->getQuery(true)
						->select($db->quoteName('id'))
						->select($db->quoteName('title'))
						->from($db->quoteName($tablename))
						->where($db->quoteName('id') . ' in (' . implode(',', $itemIds)) . ')';
			$db->setQuery($query);
			$titles = $db->loadAssocList('id', 'title');

			foreach ($associations as $language => $association)
			{
				$associations[$language]->title = empty($titles[$associations[$language]->id])
					? ''
					: $titles[$associations[$language]->id];
			}
		}

		return $associations;
	}

	/**
	 * Strip the lang variable from a string representing a non-SEF URL if:
	 *
	 * - the site is monolingual
	 * - OR site is ML and lang value same as default (else we get ?lang=en in SEF)
	 * - OR site is ML but lang value is same as current language
	 *
	 * @param string $nonSefUrl
	 * @param bool   $checkCurrentLanguage
	 */
	public function stripLangVarIfUseless($nonSefUrl, $checkCurrentLanguage = true)
	{
		$uri     = new wblUri($nonSefUrl);
		$langVar = $uri->getVar('lang');
		if (empty($langVar))
		{
			return $nonSefUrl;
		}

		if (!$this->isMultilingual())
		{
			$uri->delVar('lang');

			return $uri->toString();
		}

		$defaultLanguageCode = $this->getLanguageUrlCode(
			$this->getDefaultLanguageTag()
		);

		if ($defaultLanguageCode === $langVar)
		{
			$uri->delVar('lang');

			return $uri->toString();
		}

		if (!$checkCurrentLanguage)
		{
			return $nonSefUrl;
		}

		$currentLanguage = empty($currentLanguage)
			? $this->getCurrentLanguageTag()
			: $currentLanguage;

		$currentLangCode = $this->getLanguageUrlCode($currentLanguage);
		if ($currentLangCode === $langVar)
		{
			$uri->delVar('lang');

			return $uri->toString();
		}

		return $nonSefUrl;
	}

	/**
	 * Prepend a language code to a sef URL if needed by the site settings.
	 *
	 * - must be multilingual
	 * - language tag must be present in the query vars (as decoded by the language filter)
	 * - if default language, check whether language filter is set to include it or not in URLs.
	 *
	 * @param string $path
	 * @param string $langTag
	 *
	 * @return string
	 */
	public function addLangCodeIfNeeded($path, $langTag)
	{
		if ($this->shouldInsertLangCodeInDefaultLanguage($langTag))
		{
			return $path;
		}

		return Wb\slashTrimJoin(
			$this->getLanguageUrlCode(
				$langTag
			),
			$path
		);
	}

	public function shouldAddLangCodeToSef($langTag)
	{
		if (!$this->isMultilingual())
		{
			return false;
		}

		$defaultLanguageTag = $this->getDefaultLanguageTag();
		$langTag            = empty($langTag)
			? $defaultLanguageTag
			: $langTag;

		return $defaultLanguageTag !== $langTag
			   ||
			   $this->shouldInsertLangCodeInDefaultLanguage();
	}

	/**
	 * Figures out if a language code should be inserted
	 * into urls for default language
	 */
	public function shouldInsertLangCodeInDefaultLanguage()
	{
		static $shouldInsert = null;

		if (is_null($shouldInsert))
		{
			$shouldInsert = false;

			$plugin = PluginHelper::getPlugin(
				'system',
				'languagefilter'
			);
			if (!empty($plugin))
			{
				$params = new Registry();
				$params->loadString(
					$plugin->params
				);
				$shouldInsert = empty($params->get(
					'remove_default_prefix', 0
				));
			}
		}

		return $shouldInsert;
	}

	public function getLanguageOverrides($extension)
	{
		if (empty($extension))
		{
			return [];
		}
		$extension = strtoupper($extension . '_');
		try
		{
			$language      = $this->app->getLanguage();
			$r             = new \ReflectionClass('\Joomla\CMS\Language\Language');
			$overridesProp = $r->getProperty('override');
			$overridesProp->setAccessible(true);
			$overrides = $overridesProp->getValue($language);
			// only keep our overrides
			$mergedOverrides = [];
			// Keys can have a sub-level. In Overrides this is marked with a double underscore:
			// COM_FORSEO_MAIN_MENU__DASHBOARD
			foreach ($overrides as $key => $langString)
			{
				if (Wb\startsWith($key, $extension))
				{
					$keys = explode('__', strtolower(Wb\lTrim($key, $extension)));
					if (count($keys) == 1)
					{
						$mergedOverrides[] = $langString;
					}
					else
					{
						$topLevelKey                            = System\Strings::toCamelCase($keys[0], '_');
						$subKey                                 = System\Strings::toCamelCase($keys[1], '_');
						$mergedOverrides[$topLevelKey]          = $mergedOverrides[$topLevelKey] ?? [];
						$mergedOverrides[$topLevelKey][$subKey] = $langString;
					}
				}
			}

			return $mergedOverrides;
		}
		catch (\Throwable $e)
		{
			return [];
		}
		catch (\Exception $e)
		{
			return [];
		}
	}

	public function loadLanguageFile($name, $location = '')
	{
		$language = $this->app->getLanguage();
		$location = 'admin' == $location ? \JPATH_ADMINISTRATOR : '';
		$language->load($name, $location);
	}

	public function t($key, $options = array('js_safe' => false, 'lang' => ''))
	{
		$options['jsSafe'] = !empty($options['js_safe']);

		return Language\Text::_($key, $options);
	}

	public function tprintf(...$args)
	{
		return call_user_func_array('\Joomla\CMS\Language\Text::sprintf', $args);
	}

	public function getTimezone()
	{
		return $this->app->get('offset');
	}

	// html operations
	public function setHttpStatus($code, $override = true)
	{
		$this->app->setHeader('status', $code, $override);

		return $this;
	}

	public function getHttpStatus()
	{
		$status  = System\Http::RETURN_OK;
		$headers = $this->app->getHeaders();
		foreach ($headers as $header)
		{
			if ('status' == strtolower($header['name'] ?? ''))
			{
				$status = (int)$header['value'];
			}
		}

		return empty($status)
			? System\Http::RETURN_OK
			: $status;
	}

	//public function addScript($url, $type = "text/javascript", $defer = false, $async = false);
	public function addScript($url, $options = [], $attribs = [])
	{
		if ($this->isHtmlDocument())
		{
			$this->getDocument()->addScript($url, $options, $attribs);
		}

		return $this;
	}

	public function addScripts($scripts)
	{
		if (!is_array($scripts))
		{
			return $this;
		}

		foreach ($scripts as $script)
		{
			$this->addScript(
				Wb\arrayGet($script, 'url', ''),
				Wb\arrayGet($script, 'options', []),
				Wb\arrayGet($script, 'attr', [])
			);
		}

		return $this;
	}

	public function addScriptDeclaration($content, $type = 'text/javascript')
	{
		$this->getDocument()->addScriptDeclaration($content, $type);

		return $this;
	}

	//public function addStyleSheet($url, $type = 'text/css', $media = null, $attribs = []);
	public function addStyleSheet($url, $options = [], $attribs = [])
	{
		if ($this->isHtmlDocument())
		{
			$this->getDocument()->addStyleSheet($url, $options, $attribs);
		}

		return $this;
	}

	public function addStyleSheets($stylesheets)
	{
		if (!is_array($stylesheets))
		{
			return $this;
		}

		foreach ($stylesheets as $stylesheet)
		{
			$this->addStyleSheet(
				Wb\arrayGet($stylesheet, 'url', ''),
				Wb\arrayGet($stylesheet, 'options', []),
				Wb\arrayGet($stylesheet, 'attr', [])
			);
		}

		return $this;
	}

	public function addStyleDeclaration($content, $type = 'text/css')
	{
		if ($this->isHtmlDocument())
		{
			$this->getDocument()->addStyleDeclaration($content, $type);
		}

		return $this;
	}

	public function isMultipageContent($contentData)
	{
		$context = Wb\arrayGet($contentData, 'context', '');

		if ('com_content.article' != $context)
		{
			return false;
		}

		$content = Wb\arrayGet($contentData, 'content');
		if (empty($content) || empty($content->text))
		{
			return false;
		}

		$limitStart = $this->getHttpInput()->getInt('limitstart', 0);

		return $limitStart > 0
			   || Wb\contains(
				   $content->text,
				   'class="system-pagebreak'
			   );

	}

	public function isShowAllEnabled()
	{
		static $hasShowAll;

		if (is_null($hasShowAll))
		{
			$hasShowAll = $this->isPluginEnabled('content', 'pagebreak');
			$plugin     = Plugin\PluginHelper::getPlugin('content', 'pagebreak');
			if (
				empty($plugin)
				||
				is_array($plugin)
			)
			{
				$hasShowAll = false;
			}

			if ($hasShowAll)
			{
				$params     = new Registry($plugin->params);
				$style      = $params->get('style', 'pages');
				$hasShowAll = $hasShowAll && !empty($params->get('showall', true)) && 'pages' === $style;
			}
		}

		return $hasShowAll;
	}

	public function setTitle($title)
	{
		$this->getDocument()->setTitle($title);

		return $this;
	}

	public function getTitle()
	{
		$title = $this->getDocument()->getTitle();

		return empty($title)
			? ''
			: $title;
	}

	public function setAdminTitle($title)
	{
		ToolbarHelper::title($title);

		return $this;
	}

	public function setDescription($description)
	{
		$this->getDocument()->setDescription($description);

		return $this;
	}

	public function getDescription()
	{
		$desc = $this->getDocument()->getDescription();

		return empty($desc)
			? ''
			: $desc;
	}

	public function getCanonical()
	{
		if (!$this->isHtmlDocument())
		{
			return '';
		}

		$headLinks = Wb\arrayGet(
			$this->getDocument()->getHeadData(),
			[
				'links',
			],
			[]
		);
		foreach ($headLinks as $href => $headLink)
		{
			if (Wb\arrayGet($headLink, 'relation', '') == 'canonical')
			{
				return System\Strings::pr('~([^:])(/{2,})~', '$1/', $href);
			}
		}

		return '';
	}

	public function addHeadLink($href, $relation, $relType = 'rel', $attribs = [], $replace = true)
	{
		if (!$this->isHtmlDocument())
		{
			return $this;
		}

		$document = $this->getDocument();
		if ($replace)
		{
			$links = Wb\arrayGet(
				$document->getHeadData(),
				'links',
				[]
			);
			$links = array_filter(
				$links,
				function ($link) use ($relation) {
					return Wb\arrayGet($link, 'relation', '') != $relation;
				}
			);
			$document->setHeadData(
				[
					'links' => $links
				]
			);
		}

		$document->addHeadLink($href, $relation, $relType, $attribs);

		return $this;
	}

	public function removeHeadLink($href, $relation, $relType = 'rel')
	{
		if (!$this->isHtmlDocument())
		{
			return $this;
		}

		$document = $this->getDocument();
		$links    = Wb\arrayGet(
			$document->getHeadData(),
			'links',
			[]
		);

		$links = array_filter(
			$links,
			function ($link, $linkHref) use ($href, $relation, $relType) {
				return $linkHref !== $href
					   ||
					   Wb\arrayGet($link, 'relType', '') !== $relType
					   ||
					   Wb\arrayGet($link, 'relation', '') !== $relation;
			},
			\ARRAY_FILTER_USE_BOTH
		);

		$document->setHeadData(
			[
				'links' => $links
			]
		);

		return $this;
	}

	public function setMetaData($name, $content, $attribute = 'name')
	{
		$this->getDocument()->setMetaData($name, $content, $attribute);

		return $this;
	}

	public function getMetaData($name, $attribute = 'name')
	{
		return $this->getDocument()->getMetaData($name, $attribute);
	}

	public function addCustomTag($html)
	{
		if ($this->isHtmlDocument())
		{
			$this->getDocument()->addCustomTag($html);
		}

		return $this;
	}

	public function setHeader($name, $value, $override = false)
	{
		$this->app->setHeader($name, $value, $override);

		return $this;
	}

	public function setResponseType($type = 'html', $filename = 'document')
	{
		switch ($type)
		{
			case 'json':
				$this->getDocument()
					 ->setType('json')
					 ->setMimeEncoding('application/json');
				break;
			case 'js':
				$this->getDocument()
					 ->setType('text/html')
					 ->setMimeEncoding('application/javascript');
				break;
			case 'css':
				$this->getDocument()
					 ->setType('text/css')
					 ->setMimeEncoding('text/css');
				break;
			case 'raw':
			case 'html':
			default:
				break;
		}

		return $this;
	}

	// workflow operations
	public function triggerEvent($event, $args = [])
	{
		if (self::$isJ3)
		{
			$dispatcher = \JEventDispatcher::getInstance();
			$dispatcher->trigger(
				$event,
				$args
			);
		}
		else
		{
			Factory::getApplication()->triggerEvent(
				$event,
				$args
			);
		}

		return $this;
	}

	/**
	 * Register an event handler with the application.
	 *
	 * @param string    $eventName
	 * @param \callable $eventHandler
	 * @param int       $priority
	 *
	 * @return bool
	 */
	public function registerEventHandler($eventName, $eventHandler, $priority = 0)
	{
		if (self::$isJ3)
		{
			\JEventDispatcher::getInstance()->register(
				$eventName,
				$eventHandler
			);
		}
		else
		{
			Factory::getApplication()
				   ->getDispatcher()
				   ->addListener(
					   $eventName,
					   $eventHandler,
					   $priority
				   );
		}
	}

	/**
	 * Handles changes in event parameters handling between J3 and J4.
	 *
	 * @param mixed $eventOrEventData
	 *
	 * @return mixed|null
	 */
	public function getEventData($eventOrEventData)
	{
		if (is_object($eventOrEventData) && method_exists($eventOrEventData, 'getArguments'))
		{
			$arguments = $eventOrEventData->getArguments();

			return is_array($arguments)
				? array_shift($arguments)
				: $arguments;
		}
		else
		{
			return $eventOrEventData;
		}
	}

	public function isFrontend()
	{
		return $this->app->isClient('site');
	}

	public function isBackend()
	{
		return $this->app->isClient('administrator');
	}

	/**
	 * Whether the current page is an edit page.
	 * Note that we don't memoize the value in case this is called prior to
	 * onAfterRoute and the request has not been parsed yet. We should not do
	 * that but we may do it anyway.
	 *
	 * @return bool
	 */
	public function isFrontendEditPage()
	{
		// unlikely to be editing without being logged in
		// also rules out admin
		if ($this->isGuest())
		{
			return false;
		}

		$input  = $this->getHttpInput();
		$option = $input->getCmd('option');
		$view   = $input->get('view');
		if (Wb\contains($view, '.'))
		{
			$view = explode('.', $view);
			$view = array_pop($view);
		}
		$task = $input->getCmd('task');
		if (Wb\contains($task, '.'))
		{
			$task = explode('.', $task);
			$task = array_pop($task);
		}

		$layout = $input->getCmd('layout');

		$isEditPage =
			in_array($option, ['com_config', 'com_contentsubmit', 'com_cckjseblod'])
			||
			($option == 'com_comprofiler' && in_array($task, ['', 'userdetails']))
			||
			in_array($task, ['edit', 'form', 'submission'])
			||
			in_array($view, ['edit', 'form'])
			||
			in_array($layout, ['edit', 'form', 'write']);

		return
			$this->app->isClient('site')
			&&
			$isEditPage;
	}

	public function isOffline()
	{
		return $this->app->get('offline');
	}

	public function enableOfflineMode()
	{
		return $this->app->set('offline', 1);
	}

	/**
	 * Set Joomla offline and persist that.
	 */
	public function persistOfflineMode($newState)
	{
		// store online state
		$currentOfflineState = $this->app->get('offline');

		// Get the previous configuration
		$prev = new \JConfig;
		$prev = ArrayHelper::fromObject($prev);

		// Create the new configuration
		unset($prev['root_user']);
		$config = new Registry($prev);

		$config->set('offline', 1);

		// Clear cache of com_config component.
		$this->cleanCache('_system', 0);
		$this->cleanCache('_system', 1);

		// Attempt to write the configuration file as a PHP class named JConfig.
		$configuration = $config->toString('PHP', array('class' => 'JConfig', 'closingtag' => false));

		// Write the config to disk
		$file = \JPATH_CONFIGURATION . '/configuration.php';
		Path::isOwner($file) && Path::setPermissions($file, '0644');
		if (!File::write($file, $configuration))
		{
			throw new \Exception('Unable to store confguration, permission error.');
		}
		Path::isOwner($file) && Path::setPermissions($file, '0444');

		return $this;
	}

	/**
	 * Copied from Joomla com_config
	 *
	 * @param null $group
	 * @param int  $client_id
	 */
	public function cleanCache($group = null, $client_id = 0)
	{
		$conf = $this->getConfig();

		$options = array(
			'defaultgroup' => $group ? '' : 'com_config',
			'cachebase'    => $client_id ? \JPATH_ADMINISTRATOR . '/cache' : $conf->get('cache_path', \JPATH_SITE . '/cache'));

		$cache = $this->getCache('callback', $options);
		$cache->clean();

		// also stored in session!
		$this->app->setUserState('com_config.config.global.data', null);
	}

	public function disableOfflineMode()
	{
		return $this->app->set('offline', 0);
	}

	public function isHtmlPage()
	{
		return $this->isDocumentType('html')
			   &&
			   $this->getHttpInput()->getCmd('format') != 'raw';
	}

	public function isErrorPage()
	{
		return $this->isDocumentType('error')
			   &&
			   $this->getHttpInput()->getCmd('format') != 'raw';
	}

	public function isFeedPage()
	{
		return $this->isDocumentType('feed')
			   &&
			   $this->getHttpInput()->getCmd('format') != 'raw';
	}

	public function isHtmlDocument()
	{
		return 'html' === $this->getDocument()->getType();
	}

	public function isFeedDocument()
	{
		return 'feed' === $this->getDocument()->getType();
	}

	public function isDocumentType($expectedType)
	{
		return $expectedType === $this->getDocument()->getType();
	}

	public function getDocumentNonce($asAttribute = false)
	{
		if (!$this->isDocumentType('html'))
		{
			return '';
		}

		$document = $this->getDocument();
		$nonce    = '';
		if (
			is_object($document)
			&&
			!empty($document->cspNonce)
		)
		{
			$nonce = $document->cspNonce;
		}

		if (empty($nonce))
		{
			return '';
		}

		return $asAttribute
			? 'nonce="' . $nonce . '"'
			: $nonce;
	}

	public function isLikelyEditPage()
	{
		$view = strtolower($this->getHttpInput()->getCmd('view', ''));
		if (in_array($view, ['form', 'edit']))
		{
			return true;
		}
		$layout = strtolower($this->getHttpInput()->getCmd('layout', ''));
		if (in_array($layout, ['form', 'edit']))
		{
			return true;
		}

		$option = strtolower($this->getHttpInput()->getCmd('option', ''));
		if (
			'com_config' === $option
			&&
			'modules' === $view
		)
		{
			// HTML module
			return true;
		}

		return false;
	}

	public function canCompress()
	{
		return $this->app->get('gzip')
			   && !ini_get('zlib.output_compression')
			   && (ini_get('output_handler') != 'ob_gzhandler');
	}

	public function getDocument()
	{
		$document = $this->app->getDocument();

		return empty($document)
			? Factory::getDocument()
			: $document;
	}

	public function getDocumentContent()
	{
		return $this->app->getBody();
	}

	public function setDocumentContent($body)
	{
		return $this->app->setBody($body);
	}

	public function isDebugEnabled()
	{
		return (bool)$this->app->get('debug');
	}

	// hooks
	public function getHooksPath()
	{
		return \JPATH_ROOT . '/libraries/weeblr';
	}

	public function addHook($id, $callback, $priority = 100)
	{
		$added = false;

		if (!empty($id) && is_string($id) && is_callable($callback))
		{
			$priority                      = (int)$priority;
			self::$hooks[$id]              = empty(self::$hooks[$id]) ? [] : self::$hooks[$id];
			self::$hooks[$id][$priority]   = empty(self::$hooks[$id][$priority]) ? [] : self::$hooks[$id][$priority];
			self::$hooks[$id][$priority][] = array(
				'callback' => $callback,
				'hash'     => System\Auth::callbackUniqueId($callback)
			);

			// re-order by priority
			ksort(self::$hooks[$id]);

			$added = true;
		}

		return $added;
	}

	//
	public function removeHook($id, $callback, $priority = null)
	{
		$removed = false;

		// cannot remove, hook does not exist
		if (!array_key_exists($id, self::$hooks))
		{
			return $removed;
		}

		// do not remove a hook that is being executed
		if (in_array($id, self::$hooksStack))
		{
			return $removed;
		}

		$hash = $this->callbackUniqueId($callback);
		if (is_null($priority))
		{
			// if no priority was specified, we remove the hook
			// callback from all priority levels
			foreach (self::$hooks[$id] as $priority => $hookRecord)
			{
				$removed = $this->removeHookCallback($id, $priority, $hash);
				if ($removed)
				{
					break;
				}
			}
		}
		else
		{
			// a priority was specified, we only remove the callback
			// from that priority
			$removed = $this->removeHookCallback($id, $priority, $hash);
		}

		return $removed;
	}

	/**
	 * @param string $id       Dot-joined unique identifier for the hook
	 * @param int    $priority Restrict removal to a given priority level
	 * @param string $hash
	 *
	 * @return bool true if callback was remo
	 */
	private function removeCallback($id, $priority, $hash)
	{
		$removed = false;
		foreach (self::$hooks[$id][$priority] as $index => $hookRecord)
		{
			if ($hash == $hookRecord['hash'])
			{
				$removed = true;
				unset(self::$hooks[$id][$priority][$index]);
			}
		}

		return $removed;
	}

	public function executeHook($filter, $params)
	{
		// remove the filter id from params array
		$id = array_shift($params);

		// default returned value
		$currentValue = null;
		if (count($params) > 0)
		{
			$currentValue = $params[0];
		}

		// invalid hook id
		if (!is_string($id))
		{
			return $currentValue;
		}

		// no hook registered
		if (empty(self::$hooks[$id]))
		{
			return $currentValue;
		}

		// already running. We don't allow nesting
		self::$hooksStack[] = $id;

		// increase run counter
		self::$hooksRuns[$id] = isset(self::$hooksRuns[$id]) ? self::$hooksRuns[$id]++ : 1;

		// iterate over registered hook handlers
		foreach (self::$hooks[$id] as $priority => $callbackList)
		{
			foreach ($callbackList as $callbackRecord)
			{
				if ($filter)
				{
					$params[0] = call_user_func_array($callbackRecord['callback'], $params);
				}
				else
				{
					call_user_func_array($callbackRecord['callback'], $params);
				}
			}
		}

		$newValue = null;
		if ($filter)
		{
			$newValue = isset($params[0]) ? $params[0] : null;
		}

		array_pop(self::$hooksStack);

		return $newValue;
	}

	public function hasHook($id)
	{
		$hasHook = false;
		if (!empty($id) && is_string($id))
		{
			$hasHook = !empty(self::$hooks[$id]);
		}

		return $hasHook;
	}

	// display, or handle error
	public function handleError($request)
	{
		return $this;
	}

	public function handleMessage($msg, $type = 'info')
	{
		$this->app->enqueueMessage($msg, $type);

		return $this;
	}

	public function clearAppMessageQueue()
	{
		$this->app->getMessageQueue(true);

		return $this;
	}

	// routing, redirect
	public function route($url, $xhtml = true, $ssl = null, $absolute = false)
	{
		return Route::link('site', $url, $xhtml, $ssl, $absolute);
	}

	public function relativeRoute($url, $xhtml = true, $relativeToSite = true)
	{
		static $trimmedBase;

		if (is_null($trimmedBase))
		{
			$trimmedBase = Wb\rTrim(
				Uri::base(true),
				'/administrator'
			);
		}

		if (
			$this->isBackend()
			&&
			defined('SH404SEF_IS_RUNNING')
			&&
			\Sh404sefFactory::getConfig()->Enabled
		)
		{
			// is sh404SEF used on the site?
			$absRoute = \Sh404sefHelperGeneral::getSefFromNonSef(
				$url,
				false, // $fullyQualified,
				$xhtml
			);
		}
		else
		{
			$absRoute = Route::link('site', $url, $xhtml);
		}


		return $relativeToSite
			? Wb\lTrim($absRoute, $trimmedBase . '/')
			: $absRoute;
	}

	/**
	 * Whether we can redirect from a URL to another.
	 *
	 * @param string $from
	 * @param string $to
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function canRedirect($from, $to)
	{
		// normalize
		$from = System\Route::absolutify($from);
		$to   = System\Route::absolutify($to);

		$canRedirect = (empty($_SERVER['HTTP_X_REQUESTED_WITH'])
						||
						(
							!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
							&&
							strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest'
						)
		);

		return $canRedirect
			   &&
			   $to != $from
			   &&
			   !in_array(
				   $this->getMethod(),
				   ['POST', 'PUT', 'PATCH', 'DELETE']
			   );
	}

	public function redirectTo($redirectTo, $redirectMethod = 301)
	{
		if (!empty($redirectTo))
		{
			// redirect to target $redirectTo
			$this->app->redirect(
				$redirectTo,
				$redirectMethod);
		}
	}

	// authorization
	public function authorize($action, $subject, $userId = null)
	{
		return Factory::getUser($userId)->authorise($action, $subject);
	}

	// filesystem

	public function moveFile($src, $dest, $path = '')
	{
		return File::move($src, $dest);
	}

	public function moveFiles($files, $path = '')
	{
		$files = Wb\arrayEnsure($files);
		$moved = [];
		foreach ($files as $src => $dest)
		{
			$moved[$src] = $this->moveFile($src, $dest, $path);
		}

		return $moved;
	}

	public function moveToFolder($files, $src, $dest)
	{
		$files = Wb\arrayEnsure($files);
		$moved = [];
		foreach ($files as $file)
		{
			$moved[$file] = $this->moveFile(
				Path::clean($src . '/' . $file),
				Path::clean($dest . '/' . $file)
			);
		}

		return $moved;
	}

	public function deleteFile($file)
	{
		if (is_file(Path::clean($file)))
		{
			return File::delete($file);
		}

		return true;
	}

	public function deleteFiles($files)
	{
		$files   = Wb\arrayEnsure($files);
		$deleted = [];
		foreach ($files as $file)
		{
			$deleted[$file] = $this->deleteFile($file);
		}

		return $deleted;
	}

	public function listFiles($path, $filter = '.', $recurse = false, $full = false, $exclude = array('.svn', 'CVS', '.DS_Store', '__MACOSX'),
							  $excludeFilter = array('^\..*', '.*~'), $naturalSort = false)
	{
		if (!is_dir(Path::clean($path)))
		{
			return [];
		}

		return Folder::files($path, $filter, $recurse, $full, $exclude, $excludeFilter, $naturalSort);
	}

	public function createFolders($folders)
	{
		$folders = Wb\arrayEnsure($folders);
		foreach ($folders as $folder)
		{
			Folder::create($folder);
		}
	}

	public function deleteFolders($folders, $exclude = [])
	{
		$folders = Wb\arrayEnsure($folders);
		$folders = array_diff(
			$folders,
			$exclude
		);
		foreach ($folders as $folder)
		{
			if (is_dir(Path::clean($folder)))
			{
				Folder::delete($folder);
			}
		}
	}

	public function moveFolders($moves)
	{
		$moves = Wb\arrayEnsure($moves);
		foreach ($moves as $source => $destination)
		{
			Folder::move($source, $destination);
		}
	}

	public function listFolders($path, $filter = '.', $recurse = false, $full = false, $exclude = array('.svn', 'CVS', '.DS_Store', '__MACOSX'), $excludefilter = array('^\..*'))
	{
		if (!is_dir(Path::clean($path)))
		{
			return [];
		}

		return Folder::folders($path, $filter, $recurse, $full, $exclude, $excludefilter);
	}

	// Display
	public function defaultItemsPerPage()
	{
		return $this->app->get('list_limit');
	}

	/**
	 * Joomla 4 and up only. Get update credentials
	 *
	 * @param $extensionType
	 * @param $extensionElement
	 *
	 * @return string
	 * @throws \Exception
	 */
	public function getUpdateId($extensionType, $extensionElement)
	{
		$updateSiteId = $this->getUpdateSiteId($extensionType, $extensionElement);

		$updateSite = Table::getInstance('UpdateSite');
		$updateSite->load($updateSiteId);

		if (empty($updateSite) || empty($updateSite->extra_query))
		{
			// no update id recorded yet.
			return '';
		}

		// extract download id
		parse_str($updateSite->extra_query, $parsedBits);
		$parsedBits = Wb\arrayEnsure($parsedBits);

		return Wb\arrayGet($parsedBits, 'wblr_k', '');
	}

	/**
	 * Joomla 4 and up only. Set dlid.
	 *
	 * @param string $id
	 *
	 * @throws \Exception
	 */
	public function setUpdateId($extensionType, $extensionElement, $id)
	{
		$updateSiteId  = $this->getUpdateSiteId($extensionType, $extensionElement);
		$newExtraQuery = empty($id)
			? ''
			: 'wblr_k=' . $id . '&provider=weeblr.zip';
		$updateSite    = Table::getInstance('UpdateSite');
		$updateSite->load($updateSiteId);
		$updateSite->bind(
			[
				'extra_query' => $newExtraQuery
			]
		);
		$updateSite->store();
	}

	private function getUpdateSiteId($extensionType, $extensionElement)
	{
		static $updateSiteId = null;

		if (is_null($updateSiteId))
		{
			$db    = $this->getPlatformDb();
			$query = $db->getQuery(true)
						->select($db->quoteName('extension_id'))
						->from($db->quoteName('#__extensions'))
						->where($db->quoteName('type') . ' = ' . $db->quote($extensionType))
						->where($db->quoteName('element') . ' = ' . $db->quote($extensionElement));
			$db->setQuery($query);
			$extensionId = $db->loadResult();

			if (empty($extensionId))
			{
				$msg = 'Joomla platform, getUpdateSiteId: ' . $extensionType . '::' . $extensionElement . ' not found in Extensions table.';
				System\Log::libraryError($msg);

				throw  new \Exception($msg, 500);
			}

			$query = $db->getQuery(true)
						->select($db->quoteName('update_site_id'))
						->from($db->quoteName('#__update_sites_extensions'))
						->where($db->quoteName('extension_id') . ' = ' . $db->quote($extensionId));
			$db->setQuery($query);
			$updateSiteId = $db->loadResult();

			if (empty($updateSiteId))
			{
				$msg = 'Joomla platform, getUpdateSiteId: ' . $extensionType . '::' . $extensionElement . ' has not registered any update site.';
				System\Log::libraryError($msg);

				throw  new \Exception($msg, 500);
			}
		}

		return $updateSiteId;
	}

	/**
	 * Change options of one or more plugins.
	 *
	 * $def is an array defining which plugins and what options should be modified:
	 *
	 * [
	 *   'PlgSystemCache' => [
	 *      'key_1' => $value1,
	 *      'key_2' => $value2,
	 *      'key_3' => $value3
	 *   ]
	 * ]
	 *
	 * NB: there should not be any leading backslash on the class name.
	 *
	 * @param array $defs
	 */
	public function reconfigurePlugins($defs)
	{
		foreach (array_keys($defs) as $class)
		{
			if (!class_exists('\\' . ltrim($class, '\\')))
			{
				unset($defs[$class]);
			}
		}
		$defs = array_filter($defs);
		if (empty($defs))
		{
			return;
		}

		$platformVersion = $this->majorVersion();
		$platformVersion = $platformVersion < 4
			? 'J' . $platformVersion
			: 'Default';
		$methodName      = 'reconfigurePlugins' . $platformVersion;

		if (is_callable([$this, $methodName]))
		{
			$this->{$methodName}($defs);
		}
	}

	/**
	 * Implement plugin reconfiguration for Joomla 3.
	 *
	 * @param array $defs
	 *
	 * @throws \ReflectionException
	 */
	private function reconfigurePluginsJ3($defs)
	{
		$dispatcher = \JEventDispatcher::getInstance();

		$reflectionClass    = new \ReflectionClass('\JEventDispatcher');
		$reflectionProperty = $reflectionClass->getProperty('_observers');
		$reflectionProperty->setAccessible(true);
		$observers = $reflectionProperty->getValue($dispatcher);
		foreach ($observers as $observer)
		{
			if (!is_object($observer))
			{
				continue;
			}
			$observerClass = ltrim(get_class($observer), '\\');
			if (array_key_exists($observerClass, $defs))
			{
				foreach ($defs[$observerClass] as $key => $value)
				{
					$observer->params->set($key, $value);
				}
				unset($defs[$observerClass]);
				$defs = array_filter($defs);
				if (empty($defs))
				{
					return;
				}
			}
		}
	}

	/**
	 * Implement plugin reconfiguration for Joomla 4.
	 *
	 * @param array $defs
	 *
	 * @throws \ReflectionException
	 */
	private function reconfigurePluginsDefault($defs)
	{
		$dispatcher    = Factory::getContainer()->get('dispatcher');
		$listenersList = $dispatcher->getListeners();
		foreach ($listenersList as $eventName => $listeners)
		{
			foreach ($listeners as $listener)
			{
				if (is_array($listener) && !empty($listener[0]) && is_array($listener[0]) && is_callable($listener[0]))
				{
					// 0: object 1: method
					$listenerObject = $listener[0];
					$listenerClass  = ltrim(get_class($listenerObject), '\\');
					if (array_key_exists($listenerClass, $defs))
					{
						foreach ($defs[$listenerClass] as $key => $value)
						{
							$listenerObject->params->set($key, $value);
						}
						unset($defs[$listenerClass]);
						$defs = array_filter($defs);
						if (empty($defs))
						{
							return;
						}
					}
				}
				// J5+ native
				if (is_array($listener) && !empty($listener[0]) && is_object($listener[0]) && is_callable([$listener[0], $listener[1]]))
				{
					// 0: object 1: method
					$listenerObject = $listener[0];
					$listenerClass  = ltrim(get_class($listenerObject), '\\');
					if (array_key_exists($listenerClass, $defs))
					{
						foreach ($defs[$listenerClass] as $key => $value)
						{
							$listenerObject->params->set($key, $value);
						}
						unset($defs[$listenerClass]);
						$defs = array_filter($defs);
						if (empty($defs))
						{
							return;
						}
					}
				}

				if (is_object($listener) && is_callable($listener)) // closure
				{
					$listenerClass = ltrim(get_class($listener), '\\');
					if ('Closure' == $listenerClass)
					{
						// container / method / serviceId
						$function       = new \ReflectionFunction($listener);
						$listenerObject = $function->getClosureThis();
						if (!empty($listenerObject))
						{
							$listenerObjectClass = get_class($listenerObject);
							if (array_key_exists($listenerObjectClass, $defs))
							{
								foreach ($defs[$listenerObjectClass] as $key => $value)
								{
									$listenerObject->params->set($key, $value);
								}
								unset($defs[$listenerObjectClass]);
								$defs = array_filter($defs);
								if (empty($defs))
								{
									return;
								}
							}
						}
					}
				}
				if (is_object($listener))
				{
					// container / method / serviceId
				}
			}
		}
	}

	/**
	 * Disable plugins execution, by detaching them from the Joomla! dispatcher
	 *
	 * @param string       $type
	 * @param string|array $pluginsNames
	 */
	public function disablePlugins($type, $pluginsNames)
	{
		if (empty($type) || empty($pluginsNames))
		{
			return;
		}

		if (!is_array($pluginsNames))
		{
			$pluginsNames = [$pluginsNames];
		}

		// load the plugins of this type
		Plugin\PluginHelper::importPlugin($type);

		$platformVersion = $this->majorVersion();
		$platformVersion = $platformVersion < 4
			? 'J' . $platformVersion
			: 'Default';
		$methodName      = 'disablePluginsJ' . $platformVersion;

		if (is_callable([$this, $methodName]))
		{
			$this->{$methodName}($type, $pluginsNames);
		}
	}

	private function disablePluginsDefault($type, $pluginsNames)
	{
		$dispatcher = Factory::getContainer()->get('dispatcher');

		// iterate over plugins named in the list
		foreach ($pluginsNames as $pluginName)
		{
			// disable this plugin
			$className = 'Plg' . ucfirst($type) . ucfirst($pluginName);
			if (class_exists($className))
			{
				$listenersList = $dispatcher->getListeners();
				foreach ($listenersList as $event => $listeners)
				{
					foreach ($listeners as $index => $listener)
					{
						if (is_array($listener) && !empty($listener[0]) && is_array($listener[0]) && is_callable($listener[0]))
						{
							// 0: object 1: method
							$object = $listener[0];
							if ($object instanceof $className)
							{
								$dispatcher->removeListener(
									$event,
									$listener
								);
							}
						}
						// J5+ native
						if (is_array($listener) && !empty($listener[0]) && is_object($listener[0]) && is_callable([$listener[0], $listener[1]]))
						{
							// 0: object 1: method
							$object = $listener[0];
							if ($object instanceof $className)
							{
								$dispatcher->removeListener(
									$event,
									$listener
								);
							}
						}
						if (is_object($listener) && is_callable($listener)) // closure
						{
							$class = get_class($listener);
							if ('Closure' == $class)
							{
								// container / method / serviceId
								$function = new \ReflectionFunction($listener);
								$object   = $function->getClosureThis();
								if ($object instanceof $className)
								{
									$dispatcher->removeListener(
										$event,
										$listener
									);
								}
							}
						}
						if (is_object($listener))
						{
							// container / method / serviceId
						}
					}
				}
			}
		}
	}

	private function disablePluginsJ3($type, $pluginsNames)
	{
		$dispatcher = \JEventDispatcher::getInstance();

		// iterate over plugins named in the list
		foreach ($pluginsNames as $pluginName)
		{
			// disable this plugin
			$className = 'Plg' . ucfirst($type) . ucfirst($pluginName);
			if (class_exists($className))
			{
				$pluginDetails  = Plugin\PluginHelper::getPlugin(
					$type,
					strtolower($pluginName)
				);
				$pluginInstance = new $className(
					$dispatcher,
					(array)$pluginDetails
				);
				$dispatcher->detach($pluginInstance);
			}
		}
	}

	public function getCustomFieldsDefs($context = '')
	{
		if ($this->majorVersion() >= 4)
		{
			$fields = Helper\FieldsHelper::getFields($context);
		}
		else
		{
			\JLoader::register(\FieldsHelper::class, \JPATH_ADMINISTRATOR . '/components/com_fields/helpers/fields.php');
			$fields = \FieldsHelper::getFields($context);
		}

		return $fields;
	}

	public function getCustomFieldsForContent($context, $item = null, $prepareValue = false, array $valuesToOverride = null)
	{
		try
		{
			if ($this->majorVersion() >= 4)
			{
				$fields = Helper\FieldsHelper::getFields($context, $item, $prepareValue, $valuesToOverride);
			}
			else
			{
				\JLoader::register(\FieldsHelper::class, \JPATH_ADMINISTRATOR . '/components/com_fields/helpers/fields.php');
				$fields = \FieldsHelper::getFields($context, $item, $prepareValue, $valuesToOverride);
			}

			return $fields;
		}
		catch (\Throwable $e)
		{

		}

		return [];
	}

	public function getCustomFieldsValue($customFieldId, $context, $item = null, $prepareValue = false, array $valuesToOverride = null)
	{
		$fields = $this->getCustomFieldsForContent($context, $item, $prepareValue, $valuesToOverride);
		foreach ($fields as $field)
		{
			if ($customFieldId !== $field->id)
			{
				continue;
			}

			// @TODO: revert to ?? op after last PHP 5.6 compatible version (of sh404SEF) has been released.
			return [
				'rawvalue' => isset($field->rawvalue) ? $field->rawvalue : null,
				'value'    => isset($field->value) ? $field->value : null
			];
		}

		return null;
	}

	public function getFieldContextFromId($customFieldId)
	{
		$field = $this->getCustomFieldById($customFieldId);

		return empty($field)
			? ''
			: $field->context;
	}

	public function getCustomFieldById($customFieldId)
	{
		if (is_array($customFieldId))
		{
			$customFieldId = array_shift($customFieldId);
		}

		$fields = $this->getCustomFieldsDefs();
		foreach ($fields as $field)
		{
			if ((int)$field->id === $customFieldId)
			{
				return $field;
			}
		}

		return '';
	}

	public function hasImageLib($libName)
	{

		if ('gd' === $libName)
		{
			return function_exists('gd_info') && !empty(\gd_info());
		}

		return false;
	}

	public function logAction($extensionName, $extensionLink, $message, $messageLanguageKey, $context, $userId = null)
	{
		if (\version_compare(\JVERSION, '3.9', '<'))
		{
			return;
		}

		$message['weeblr_extension_name'] = $extensionName;
		if (!empty($extensionLink))
		{
			$message['weeblr_extension_link'] = $extensionLink;
		}

		$user = $this->getUser();

		if (!\array_key_exists('userid', $message))
		{
			$message['userid'] = $user->id;
		}

		if (!\array_key_exists('username', $message))
		{
			$message['username'] = $user->username;
		}

		if (!\array_key_exists('accountlink', $message))
		{
			$message['accountlink'] = 'index.php?option=com_users&task=user.edit&id=' . $user->id;
		}

		if (\array_key_exists('type', $message))
		{
			$message['type'] = strtoupper($message['type']);
		}

		if (\array_key_exists('app', $message))
		{
			$message['app'] = strtoupper($message['app']);
		}

		if (\version_compare(\JVERSION, '4.0', '<'))
		{
			\JLoader::register('ActionlogsModelActionlog', JPATH_ADMINISTRATOR . '/components/com_actionlogs/models/actionlog.php');
			$model = BaseDatabaseModel::getInstance('Actionlog', 'ActionlogsModel');
		}
		else
		{
			/** @var \Joomla\Component\Actionlogs\Administrator\Model\ActionlogModel $model */
			$model = $this->app->bootComponent('com_actionlogs')
							   ->getMVCFactory()->createModel('Actionlog', 'Administrator', ['ignore_request' => true]);

		}

		$model->addLog([$message], strtoupper($messageLanguageKey), $context, $userId);
	}

	/**
	 * Builds a Joomla database object for an external database.
	 *
	 * @param string $id
	 * @param array  $options
	 * @return Joomla\Dbconnection
	 */
	public function getExternalDatabaseConnection($id, $options = [])
	{
		if (self::$isJ3)
		{
			$dbObject = \JDatabaseDriver::getInstance(
				$options
			);
		}

		if (!self::$isJ3)
		{
			// can't get the factory from the container, it's not
			// registered soon enough in some cases (our ajax requests
			// for instance
			$dbFactory = new DatabaseFactory();
			$dbObject  = $dbFactory->getDriver(
				$options['driver'],
				$options
			);
		}

		return new Joomla\Dbconnection(
			[
				'unique_id' => $id,
				'db'        => $dbObject
			]
		);
	}
}
