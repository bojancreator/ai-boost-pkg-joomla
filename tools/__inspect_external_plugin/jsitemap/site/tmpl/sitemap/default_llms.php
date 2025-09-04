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
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;
use Joomla\String\StringHelper;

$siteNameParam = trim($this->cparams->get('ai_indexing_llms_sitename', ''));
$siteDescriptionParam = trim($this->cparams->get('ai_indexing_llms_sitedescription', ''));

// Fallback to Joomla global config if empty
$siteName = $siteNameParam ?: $this->application->get('sitename');
$siteDescription = $siteDescriptionParam ?: $this->application->get('MetaDesc');

$todayDate = date('Y-m-d');

// Start building llms.txt content
$txtContent = "# " . Text::_($siteName) . "\n\n";
$txtContent .= "<!-- " . Text::_('COM_JMAP_AI_INDEXING_LLMS_GUIDELINES') . " -->\n";
$txtContent .= "<!-- " . Text::sprintf('COM_JMAP_AI_INDEXING_LLMS_LATESTUPDATE', $todayDate) . " -->\n";
$txtContent .= "<!-- " . Text::_('COM_JMAP_AI_INDEXING_LLMS_GENERATED_BY') . " -->\n\n\n";
$txtContent .= "> " . Text::_('COM_JMAP_AI_INDEXING_LLMS_SITE_DESCRIPTION') . "\n\n";
$txtContent .= Text::_($siteDescription) . "\n\n\n";
$txtContent .= "## " . Text::_('COM_JMAP_AI_INDEXING_LLMS_SITE_CONTENTS') . "\n\n";

// Process records
foreach ($this->data as $feedRecord) {
	$title = trim($feedRecord->meta_title);
	$url = trim($feedRecord->linkurl);
	$description = trim($feedRecord->meta_desc);
	
	if ($title && $url) {
		$txtContent .= "[{$title}]({$url}): {$description}\n\n";
	}
}

echo "\xEF\xBB\xBF"; // Add BOM UTF-8
echo $txtContent;
