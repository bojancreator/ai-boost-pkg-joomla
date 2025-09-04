<?php
/**
 * @build_title_build       @
 *
 * @package                 4SEF
 * @copyright               Copyright Weeblr llc - 2022 -2025
 * @author                  Yannick Gaultier - Weeblr llc
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 2.6.2.644
 *
 * 2025-06-02
 */

namespace Weeblr\Wblib\Forsef\Platform;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * A thin interface class to the host system (CMS)
 * Performs page rendering essentially.
 *
 */
interface Platforminterface
{
	public function boot();

	public function detect();

	public function majorVersion();

	public function version();

	public function getName();

	public function getConfig();

	public function getAppParams();

	public function getRouter($name);

	public function getUniqueId();

	public function getUser($id = null);

	public function isGuest();

	public function verifyPassword($clearTextPassword);

	public function getUsersGroups();

	public function getIp();

	public function getBrowser();

	public function sanitizeInput($type, $input);

	public function getCSRFToken();

	public function checkCSRFToken($token);

	public function getCurrentUrl();

	public function getCurrentPath();

	public function getCurrentQuery($toArray = false);

	public function getCurrentContentType();

	public function getCurrentFormat();

	public function getCurrentRequestCategory();

	public function getSitename();

	public function getBaseUrl($pathOnly = true);

	public function getRootUrl($pathOnly = true);

	public function getHomeUrl();

	public function isHomePage();

//	public function isAnyHomePage();

	public function isHomepageFromVars($vars, $withoutPagination = true);

	public function isAnyHomepagePath($path);

	public function isAnyHomepageFromVars($vars, $withoutPagination = true);

	public function normalizeUrl($url);

	public function stripRewritePrefix($url);

	public function getUrlRewritingPrefix();

	public function getCanonicalRoot($isAdmin = null);

	public function getExtensions($type);

	public function saveExtensionParams($params, $options);

	public function getPlatformDb();

	public function getMenu($client = 'site');

	public function getMenuItems($options);

	public function isPluginEnabled($group, $name);

	public function getCategories($extensions = []);

	public function getHttpInput();

	public function getCookiesManager();

	public function getHttpClient($options = []);

	public function getHttpTransports();

	public function getMailer();

	public function getCache($type, $options = []);

	public function getMethod();

	public function getScheme();

	public function getHost();

	public function getRootPath();

	public function getLogsPath();

	public function getTempPath();

	public function getCachePath($type, $extension);

	public function getLayoutOverridesPath();

	public function getUserImagesPath($absolute = true);

	public function isMultilingual();

	public function hasFalang();

	public function getFrontendLanguages($enabledOnly = true);

	public function getInstalledLanguages();

	public function getDefaultLanguageTag($full = true, $app = 'site');

	public function getCurrentLanguageTag($full = true);

	public function setApplicationLanguage($languageTag);

	public function getCurrentLanguageDirection();

	public function setDocumentLanguage($tag);

	public function getLanguageDirection($lang);

	public function getLanguageUrlCode($lang);

	public function getLanguageTagFromUrlCode($urlCode);

	public function getLanguageAssociations($extension,
	                                        $tablename,
	                                        $context,
	                                        $id,
	                                        $pk = 'id',
	                                        $aliasField = 'alias',
	                                        $catField = 'catid',
	                                        $advClause = [],
	                                        $options = []);

	public function stripLangVarIfUseless($nonSefUrl, $checkCurrentLanguage = true);

	public function addLangCodeIfNeeded($path, $langTag);

	public function shouldAddLangCodeToSef($langTag);

	public function shouldInsertLangCodeInDefaultLanguage();

	public function getLanguageOverrides($extension);

	public function loadLanguageFile($name, $location = '');

	public function t($key, $options = array('js_safe' => false, 'lang' => ''));

	public function tprintf(...$args);

	public function getTimezone();

	// html operations
	public function setHttpStatus($code, $message);

	public function getHttpStatus();

	public function addScript($url, $options = [], $attribs = []);

	public function addScripts($scripts);

	public function addScriptDeclaration($content, $type = 'text/javascript');

	public function addStyleSheet($url, $options = [], $attribs = []);

	public function addStyleSheets($stylesheets);

	public function addStyleDeclaration($content, $type = 'text/css');

	public function isMultipageContent($contentData);

	public function isShowAllEnabled();

	public function setTitle($title);

	public function getTitle();

	public function setAdminTitle($title);

	public function setDescription($description);

	public function getDescription();

	public function getCanonical();

	public function addHeadLink($href, $relation, $relType = 'rel', $attribs = []);

	public function removeHeadLink($href, $relation, $relType = 'rel');

	public function setMetaData($name, $content, $attribute = 'name');

	public function getMetaData($name, $attribute = 'name');

	public function addCustomTag($html);

	public function setHeader($name, $value);

	public function setResponseType($type = 'html', $filename = 'document');

	// workflow operations
	public function triggerEvent($event, $args = []);

	public function registerEventHandler($eventName, $eventHandler, $priority = 0);

	public function getEventData($eventOrEventData);

	public function isFrontend();

	public function isFrontendEditPage();

	public function isBackend();

	public function isOffline();

	public function disableOfflineMode();

	public function enableOfflineMode();

	public function persistOfflineMode($newState);

	public function cleanCache($group = null, $client_id = 0);

	public function isHtmlPage();

	public function isErrorPage();

	public function isFeedPage();

	public function isHtmlDocument();

	public function isFeedDocument();

	public function isDocumentType($expectedType);

	public function getDocumentNonce($asAttribute = false);

	public function isLikelyEditPage();

	public function canCompress();

	public function getDocument();

	public function getDocumentContent();

	public function setDocumentContent($body);

	public function isDebugEnabled();

	// hooks
	public function getHooksPath();

	public function addHook($id, $callback, $priority = 100);

	public function removeHook($id, $callback, $priority = null);

	public function executeHook($filter, $params);

	public function hasHook($id);

	// display, or handle error
	public function handleError($request);

	public function handleMessage($msg, $type = 'info');

	public function clearAppMessageQueue();

	// routing, redirect
	public function route($url, $xhtml = true, $ssl = null);

	public function relativeRoute($url, $xhtml = true, $relativeToSite = true);

	public function canRedirect($from, $to);

	public function redirectTo($redirectTo, $redirectMethod = 301);

	// users & authorization
	public function authorize($action, $subject, $userId = null);

	// filesystem
	public function createFolders($folders);

	public function deleteFolders($folders, $exclude = []);

	public function moveFile($src, $dest, $path = '');

	public function moveFiles($files, $path = '');

	public function moveToFolder($files, $src, $dest);

	public function deleteFile($file);

	public function deleteFiles($files);

	public function listFiles($path, $filter = '.', $recurse = false, $full = false, $exclude = array('.svn', 'CVS', '.DS_Store', '__MACOSX'),
	                          $excludeFilter = array('^\..*', '.*~'), $naturalSort = false);

	public function moveFolders($moves);

	public function listFolders($path, $filter = '.', $recurse = false, $full = false, $exclude = array('.svn', 'CVS', '.DS_Store', '__MACOSX'), $excludefilter = array('^\..*'));

	// Display
	public function defaultItemsPerPage();

	public function getUpdateId($extensionType, $extensionElement);

	public function setUpdateId($extensionType, $extensionElement, $id);

	// Extensions
	public function reconfigurePlugins($defs);

	public function disablePlugins($type, $pluginsNames);

	// Custom fields
	public function getCustomFieldsDefs($context = '');

	public function getCustomFieldsForContent($context, $item = null, $prepareValue = false, array $valuesToOverride = null);

	public function getCustomFieldsValue($customFieldId, $context, $item = null, $prepareValue = false, array $valuesToOverride = null);

	public function getFieldContextFromId($customFieldId);

	public function getCustomFieldById($customFieldId);

	public function hasImageLib($libName);

	// Logging
	public function logAction($extensionName, $extensionLink, $message, $messageLanguageKey, $context, $userId = null);

	// Custom db connection
	public function getExternalDatabaseConnection($id, $options = []);
}
