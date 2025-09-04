<?php
/**
 * @author Joomla! Extensions Store
 * @package JMAP::modules::mod_jmap
 * @copyright (C) 2021 - Joomla! Extensions Store
 * @license GNU/GPLv2 http://www.gnu.org/licenses/gpl-2.0.html
 */
defined ( '_JEXEC' ) or die ( 'Restricted access' );
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\String\StringHelper;
use JExtStore\Module\Jmap\Site\Helper\JmapHelper;
use JExtstore\Component\JMap\Administrator\Framework\Loader;

/**
 * Module for sitemap footer navigation
 *
 * @author Joomla! Extensions Store
 * @package JMAP::modules::mod_jmap
 * @since 3.0
 */
// Include the syndicate functions only once
if($params->get('height_auto', 1)) {
	JmapHelper::jmapInjectAutoHeightScript();
}

$scroll = htmlspecialchars($params->get('scrolling'));
$width	= htmlspecialchars($params->get('width'));
if(stripos($width, 'px') === false && stripos($width, '%') === false) {
	$width .= 'px';
}
$height = htmlspecialchars($params->get('height'));
$height = preg_replace('/(%|px)/i', '', $height);

$onLoad = $params->get('height_auto', 1) ? 'onload="jmapIFrameAutoHeight(\'jmap_sitemap_nav_' . $module->id . '\')"' : '';
$dataset = (int)$params->get('dataset', null);
$dataset = $dataset ? '&amp;dataset=' . $dataset : ''; 
$moduleclass_sfx = htmlspecialchars($params->get('moduleclass_sfx', ''));

// Check for multilanguage
$app = Factory::getApplication();
$currentLanguageQueryString = null;
$currentSefLanguage = null;
if ($app->isClient('site')) {
	$multilangEnabled = $app->getLanguageFilter();
	$currentSefLanguage = $multilangEnabled ?  $app->getLanguage()->getLocale() : null;
	if(is_array($currentSefLanguage)) {
		$partialSef = explode('_', $currentSefLanguage[2]);
		$sefLang = array_shift($partialSef);
		$currentLanguageQueryString = '&amp;lang=' . $sefLang;
		$currentSefLanguage = $sefLang . '/';
	}
}

// Standard routing, full raw query string
$targetIFrameUrl = Uri::base() . 'index.php?option=com_jmap&amp;view=sitemap&amp;tmpl=component&amp;jmap_module=' . $module->id . $dataset . $currentLanguageQueryString;

// Setup the lazy loading mode for the iframe
$iframeLazyLoading = $params->get('iframe_loading_mode', 'lazy') == 'lazy' ? 'lazy' : 'eager';

// Legacy routing /en, /de, etc
if($params->get('legacy_routing', 0)) {
	// Try to check for an active htaccess file
	$index = null;
	if(!$app->get ( 'sef_rewrite' )) {
		$index = 'index.php/';
	}
	$targetIFrameUrl = Uri::base() . $index . $currentSefLanguage . '?option=com_jmap&amp;view=sitemap&amp;tmpl=component&amp;jmap_module=' . $module->id . $dataset;
}

if($params->get('module_type', 'sitemap') == 'sitemap') {
	if($params->get('module_rendering_mode', 'iframe') == 'iframe') {
		// Module iframe rendering
		require ModuleHelper::getLayoutPath('mod_jmap', $params->get('layout', 'default'));
	} else {
		/**
		 * Component execute and fetch
		 * Load language files
		 * Auto loader setup
		 * Register autoloader prefix
		 */
		// Manage partial language translations
		$jLang = $app->getLanguage ();
		$jLang->load ( 'com_jmap', JPATH_ROOT . '/components/com_jmap', 'en-GB', true, true );
		if ($jLang->getTag () != 'en-GB') {
			$jLang->load ( 'com_jmap', JPATH_SITE, null, true, false );
			$jLang->load ( 'com_jmap', JPATH_SITE . '/components/com_jmap', null, true, false );
		}
		
		require_once JPATH_ADMINISTRATOR . '/components/com_jmap/Framework/Loader.php';
		Loader::setup();
		Loader::registerNamespacePsr4 ( 'JExtstore\\Component\\JMap\\Administrator', JPATH_ADMINISTRATOR . '/components/com_jmap' );
		
		// Class aliasing
		if(!class_exists('JMapRoute')) {
			class_alias('\\JExtstore\\Component\\JMap\\Administrator\\Framework\\Helpers\\Route', 'JMapRoute');
		}
		
		// Instantiate model
		$extensionMVCFactory = $app->bootComponent('com_jmap')->getMVCFactory();
		$sitemapModel = $extensionMVCFactory->createModel('Sitemap', 'Site', ['document_format'=>'html', 'jmap_module'=>$module->id]);
		$sitemapModel->setState('format', 'html');
		$cparams = $sitemapModel->getComponentParams();
		$cparams->set('show_title', 0);
		
		$view = $extensionMVCFactory->createView('Sitemap', 'Site', 'Html');
		$view->setModel($sitemapModel, true);
		$view->addTemplatePath(JPATH_ROOT . '/components/com_jmap/tmpl/sitemap');
		$contents = $view->display();
		
		echo $contents;
	}
} else {
	// Module Ai Knowledge Graph Rendering
	require_once JPATH_ADMINISTRATOR . '/components/com_jmap/Framework/Loader.php';
	Loader::setup();
	Loader::registerNamespacePsr4 ( 'JExtstore\\Component\\JMap\\Administrator', JPATH_ADMINISTRATOR . '/components/com_jmap' );
	
	// Class aliasing
	if(!class_exists('JMapRoute')) {
		class_alias('\\JExtstore\\Component\\JMap\\Administrator\\Framework\\Helpers\\Route', 'JMapRoute');
	}
	
	// Instantiate model
	$extensionMVCFactory = $app->bootComponent('com_jmap')->getMVCFactory();
	$sitemapModel = $extensionMVCFactory->createModel('Sitemap', 'Site', ['document_format'=>'html', 'jmap_module'=>$module->id]);
	$data = $sitemapModel->getFeedData();
	
	$feedDataRecords = [ ];
	foreach ( $data as $feedRecord ) {
		$recordObject = new \stdClass ();
		$recordObject->question = $feedRecord->meta_title;
		$recordObject->answer = $feedRecord->meta_desc;
		$recordObject->url = $feedRecord->linkurl;
		
		// Check if also an image is available
		if($feedRecord->meta_image) {
			$imageAILink = preg_match('/http/i', $feedRecord->meta_image) ? $feedRecord->meta_image : Uri::base() . ltrim($feedRecord->meta_image, '/');
			// For J4 query string is needed to remove it
			$imageAILink = StringHelper::substr($imageAILink, 0, StringHelper::strpos($imageAILink, '#'));
			
			$recordObject->image = $imageAILink;
		}
		
		$feedDataRecords [] = $recordObject;
	}
	
	$encodedData = json_encode ( $feedDataRecords, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	
	$document = $app->getDocument();
	$wa = $document->getWebAssetmanager();
	
	$wa->addInlineStyle('div.jmap-module-aiknowledgegraph svg { width: 100%; height: 600px; border: 1px solid #ccc; }
        div.jmap-module-aiknowledgegraph .node text { font-size: 12px; pointer-events: none; text-anchor: middle; }
        div.jmap-module-aiknowledgegraph .node circle { stroke: #333; stroke-width: 1.5px; cursor: pointer; }
        div.jmap-module-aiknowledgegraph .link { stroke: #999; stroke-opacity: 0.6; stroke-width: 2px; }
        div.jmap-module-aiknowledgegraph div.jmap-module-aiknowledgegraph-tooltip {
            position: absolute;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 5px;
            border-radius: 5px;
            font-size: 12px;
            visibility: hidden;
            pointer-events: none;
	}');
	
	$wa->addInlineScript('var jmapNodesLength = ' . $params->get('nodes_length', 160) . ';');
	$wa->addInlineScript('var jmapKnowledgeGraphData = ' . $encodedData  . ';');
	$wa->registerAndUseScript('jmap.d3v7lib', 'modules/mod_jmap/tmpl/d3.v7.min.js', [], ['defer'=>true]);
	
	require ModuleHelper::getLayoutPath('mod_jmap', $params->get('layout', 'aiknowledgegraph'));
}