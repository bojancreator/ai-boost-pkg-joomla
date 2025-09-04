<?php
/** 
 * @package JMAP::SITEMAP::components::com_jmap
 * @subpackage views
 * @subpackage sitemap
 * @subpackage tmpl
 * @author Joomla! Extensions Store
 * @copyright (C) 2021 - Joomla! Extensions Store
 * @license GNU/GPLv2 http://www.gnu.org/licenses/gpl-2.0.html
 */
defined ( '_JEXEC' ) or die ( 'Restricted access' );
use Joomla\CMS\Uri\Uri;
use Joomla\String\StringHelper;

$feedDataRecords = [ ];
foreach ( $this->data as $feedRecord ) {
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

echo json_encode ( $feedDataRecords, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);