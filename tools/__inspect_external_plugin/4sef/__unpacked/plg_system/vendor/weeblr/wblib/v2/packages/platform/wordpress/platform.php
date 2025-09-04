<?php
/**
 * Project:                 4SEF
 *
 * @author                  Yannick Gaultier - Weeblr llc
 * @copyright               Copyright Weeblr llc - 2022 -2025
 * @package                 4SEF
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 @build_version_full_build@
 *
 * 2025-06-02
 */

namespace Weeblr\Wblib\Forsef\Platform;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 */
abstract class WordpressPlatform extends Platform implements Platforminterface
{
	protected $_name = 'wordpress';

	protected $_inputChangeKey = array('format' => '_f');
	protected $_inputChangeKeyValue = array('_f' => array('rawjson' => 'json'));

	/**
	 * Run any initialization code.
	 */
	public function boot()
	{

	}

	/**
	 * Detects whether running on WordPress.
	 *
	 * @return bool
	 */
	public function detect()
	{
		$detected =
			defined('ABSPATH')
			&&
			defined('DB_NAME')
			&&
			defined('WPINC');

		return $detected;
	}

	/**
	 * Returns major version of the running platform
	 */
	public function majorVersion()
	{
		global $wp_version;

		$bits = explode('.', $wp_version);

		return is_array($bits) ? $bits[0] : '';
	}

	/**
	 * Return full version of the running platform
	 */
	public function version()
	{
		global $wp_version;

		return $wp_version;
	}

	public function getLayoutOverridePath()
	{
		$paths = array();
		if ($this->isFrontend())
		{
			// child theme is highest priority override source
			$childThemeRoot = get_stylesheet_directory();
			$themePaths     = array($childThemeRoot . '/weeblramp/layouts');

			// then current theme
			$themeRoot  = get_template_directory();
			$themePaths = array_unique(array_merge($themePaths, array($themeRoot . '/weeblramp/layouts')));

			// then add other overrides, from plugins
			/**
			 * Filter a list of supplemental directories where weeblrStories should look for possible layouts files
			 *
			 * Provide full absolute directories path.
			 *
			 * @api
			 * @package weeblrStories\filter\output
			 * @var weeblrstories_template_dir
			 * @since   1.0.0
			 *
			 * @param   array  $supplementalBasePaths  Array of supplemental full path
			 *
			 * @return array
			 */
			$supplementalBasePaths = apply_filters(
				'wblstories_template_dir',
				array()
			);
			$paths                 = array_merge(
				$themePaths,
				$supplementalBasePaths,
				array()
			);
		}

		return $paths;
	}

	public function getUniqueId()
	{
		return \JFactory::getConfig()->get('secret');
	}

	public function getUser($id = null)
	{
		return get_current_user();
	}

	public function sanitizeInput($type, $input)
	{
		switch ($type)
		{
			case 'string':
			case 'html':
				$output = null;
				break;
			default:
				$output = $input;
				break;
		}

		return $output;
	}

	public function getCSRFToken($forceNew = false)
	{
		return \JSession::getFormToken($forceNew);
	}

	public function checkCSRFToken($token)
	{
	}

	public function getCurrentUrl()
	{
		return \JFactory::getUri()->toString();
	}

	public function getSitename()
	{
		Factory::getApplication()->get('sitename');
	}

	public function getBaseUrl($pathOnly = true)
	{
		return \JUri::base($pathOnly);
	}

	public function getRootUrl($pathOnly = true)
	{
		return \JUri::root($pathOnly);
	}

	public function getHttpInput()
	{
		return null;
	}

	public function getHttpClient($options = array())
	{
		return null;
	}

	public function getCache($type, $options = array())
	{
		return null;
	}

	public function getMethod()
	{
		return \JFactory::getApplication()->input->getMethod();
	}

	public function getRootPath()
	{
		return JPATH_ROOT;
	}

	public function getLogsPath()
	{
		return $this->getRootPath() . '/logs';
	}

	public function getLayoutOverridesPath()
	{
		return array();
	}

	public function getCurrentLanguageTag($full = true)
	{
		$langTag = \JFactory::getLanguage()->getTag();

		return $langTag;
	}

	public function getCurrentLanguageDirection()
	{
		$langDir = \JFactory::getLanguage()->isRtl() ? 'rtl' : 'ltr';

		return $langDir;
	}

	public function loadLanguageFile($name, $location = '')
	{
		// noop
	}

	public function t($key, $options = array('js_safe' => false, 'lang' => ''))
	{
		$options['jsSafe'] = !empty($options['js_safe']);

		return \JText::_($key, $options);
	}

	public function tprintf(...$args)
	{
		return call_user_func_array('\JText::sprintf', $args);
	}

	// html operations
	public function setHttpStatus($code, $message)
	{
		return $this;
	}

	public function addScript($url, $type = "text/javascript", $defer = false, $async = false)
	{
		\JFactory::getDocument()->addScript($url, $type, $defer, $async);

		return $this;
	}

	public function addScriptDeclaration($content, $type = 'text/javascript')
	{
		\JFactory::getDocument()->addScriptDeclaration($content, $type);

		return $this;
	}

	public function addStyleSheet($url, $type = 'text/css', $media = null, $attribs = array())
	{
		\JFactory::getDocument()->addStyleSheet($url, $type, $media, $attribs);

		return $this;
	}

	public function addStyleDeclaration($content, $type = 'text/css')
	{
		\JFactory::getDocument()->addStyleDeclaration($content, $type);

		return $this;
	}

	public function setTitle($title)
	{
		return $this;
	}

	public function setAdminTitle($title)
	{
		return $this;
	}

	public function setDescription($description)
	{
		\JFactory::getDocument()->setDescription($description);

		return $this;
	}

	public function addHeadLink($href, $relation, $relType = 'rel', $attribs = array())
	{
		\JFactory::getDocument()->addHeadLink($href, $relation, $relType, $attribs);

		return $this;
	}

	public function addCustomTag($html)
	{
		\JFactory::getDocument()->addCustomTag($html);

		return $this;
	}

	public function setHeader($name, $value, $override = false)
	{
		\JFactory::getApplication()->setHeader($name, $value, $override);

		return $this;
	}

	public function setResponseType($type = 'html', $filename = 'document')
	{
		switch ($type)
		{
			case 'json':
				\JFactory::getDocument()
					->setType('json')
					->setMimeEncoding('application/json');
				break;
			case 'js':
				\JFactory::getDocument()
					->setType('text/html')
					->setMimeEncoding('application/javascript');
				break;
			case 'css':
				\JFactory::getDocument()
					->setType('text/css')
					->setMimeEncoding('text/css');
				break;
			case 'raw':
				break;
			case 'html':
			default:
				break;
		}

		return $this;
	}

	// workflow operations
	public function triggerEvent($event)
	{
		return $this;
	}

	public function isFrontend()
	{
		return is_site();
	}

	public function isBackend()
	{
		return is_admin();
	}

	public function isDebugEnabled()
	{
		return defined('WP_DEBUG') && WP_DEBUG;
	}

	/**
	 * @TODO Implement getHookspath
	 *
	 * @return string
	 */
	public function getHooksPath()
	{
		return '';
	}

	public function addHook($id, $callback, $priority = 100)
	{
		return add_filter($id, $callback, $priority);
	}

	public function removeHook($id, $callback, $priority = null)
	{
		return remove_filter($id, $callback, $priority);
	}

	public function executeHook($filter, $params)
	{
		if ($filter)
		{
			return call_user_func_array('apply_filter', $params);
		}
		else
		{
			call_user_func_array('do_action', $params);
		}
	}

	public function hasHook($id)
	{
		return has_filter($id);
	}

	// display, or handle error
	public function handleError($request)
	{
		return $this;
	}

	public function handleMessage($msg, $type = 'info')
	{
		Factory::getApplication()->enqueueMessage($msg, $type);

		return $this;
	}

	// routing, redirect
	public function route($url, $xhtml = true, $ssl = null)
	{
		return JRoute::_($url, $xhtml, $ssl);
	}

	public function redirectTo($redirectTo, $redirectMethod = 301)
	{
		if (!empty($redirectTo) && WblRequest::REQUEST_ROOT == $this->requestType)
		{
			// redirect to target $redirectTo
			Factory::getApplication()->redirect($redirectTo, $redirectMethod == 301);
		}
	}

	// API
	public function registerApi($definition)
	{

	}

	// authorization
	public function authorize($action, $subject, $userId = null)
	{
		return false;
	}

	// filesystem
	public function createFolders($folders)
	{
		return wp_mkdir_p($folders);
	}
}