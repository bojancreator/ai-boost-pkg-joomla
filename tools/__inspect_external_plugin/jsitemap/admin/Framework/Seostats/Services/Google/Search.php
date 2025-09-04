<?php
namespace JExtstore\Component\JMap\Administrator\Framework\Seostats\Services\Google;
/**
 *
 * @package JMAP::SEOSTATS::administrator::components::com_jmap
 * @subpackage seostats
 * @subpackage services
 * @subpackage google
 * @author Joomla! Extensions Store
 * @copyright (C) 2021 - Joomla! Extensions Store
 * @license GNU/GPLv2 http://www.gnu.org/licenses/gpl-2.0.html
 */
defined ( '_JEXEC' ) or die ( 'Restricted access' );
use Joomla\CMS\Component\ComponentHelper;
use Joomla\String\StringHelper as JString;
use JExtstore\Component\JMap\Administrator\Framework\Seostats;
use JExtstore\Component\JMap\Administrator\Framework\Seostats\Services;
use JExtstore\Component\JMap\Administrator\Framework\Language\Multilang;

/**
 * Google stats service
 *
 * @package JMAP::SEOSTATS::administrator::components::com_jmap
 * @subpackage seostats
 * @subpackage services
 * @subpackage google
 * @since 3.3
 */
class Search extends Seostats {
	/**
	 * Store the number of curled SERP pages
	 *
	 * @access public
	 * @static
	 * @var string
	 */
	public static $numberIndexedPages;
	
	/**
	 * Store the number of curled SERP page
	 *
	 * @access public
	 * @static
	 * @var string
	 */
	public static $paginationNumber;
	
	/**
	 * Start the request
	 *
	 * @access protected
	 * @return boolean
	 */
	protected static function makeRequest($query, $pageNumber, $customHeaders, $onlyIndexedCount = false) {
		$curledSerp = static::gCurl ( $query, $pageNumber, $customHeaders );
		if(!$curledSerp || !isset($curledSerp['items'])) {
			return false;
		}

		// Get total number of indexed pages
		static::$numberIndexedPages = $curledSerp['searchInformation']['totalResults'];
		static::$paginationNumber = $pageNumber;
		if($onlyIndexedCount) {
			return static::$numberIndexedPages;
		}
		
		return $curledSerp['items'];
	}
	
	/**
	 * Perform the remote query to Google through CURL
	 * 
	 * @access protected
	 * @return string
	 */
	protected static function gCurl($query, $pageNumber, $customHeaders) {
		$cx = "b03fd2932090849f4";
		$apiKeysArray = [
				"AIzaSyCmfcK1G5bWEEhuwbGw5nxV8QLJtFWtoQQ",
				"AIzaSyBni3hjt6_cDxZ0Ox6EdSOGScae-zF0dmo",
				"AIzaSyDs0d8R857GdPGPajGLphVC0r9XCNa0vfk",
				"AIzaSyB5NhPs3Y1_72i54UK6bCdQoftW22zbFk0",
				"AIzaSyB6JF78LTCMWV-Tyz7ltx46lifZtiTJd-0",
				"AIzaSyBjpdL2QofU0kTDV3b1PpKUEv5uZt3BLQE",
				"AIzaSyAYDK3Lz9h5xxBv9YU4NBrsyCGuSdXk28U",
				"AIzaSyDP-sWtEQzVpKtDc-E4wILkUdScbadwpKA",
				"AIzaSyBnXf87wTtdIkIpC4Ohu-QxCFQ0xP3IWL8",
				"AIzaSyD5YYVEEj6jeuO9VSkws-Azo04lkWWcRX8",
				"AIzaSyAGqepHZbw-RfGkYhUcebBfsgdemokuJCA",
				"AIzaSyDowrlC3OOyT-VpsTbW3kIhxOBC5scrhoM",
				"AIzaSyAet1eW3SU6i3dsvqK2tieFHkl1yxjfj-8",
				"AIzaSyCqume3hipLFX6rb8maQ3jB605ufnZXS8Q",
				"AIzaSyD7oLzq1zL1jB3wN6tyRxw34lF0Yk__lo4",
				"AIzaSyCmh9VwlM_o5DjF2kT8kOhBWD7Rw3uBVg8",
				"AIzaSyDeLZ3faNcMbgTDYyF2LlW9aYl1zeOpwhI",
				"AIzaSyAj_PM9P3W7J-XbSmGndA90_kQiHHBTyOA",
				"AIzaSyBOHsH4WYnBsz457t886bbAk3UIFB23w80",
				"AIzaSyDR460_y8z0MJZ3Pi9y6fz1QCA-C0L2RVQ"
		];
		$apiKeyIndex = array_rand($apiKeysArray);
		$apiKey = $apiKeysArray[$apiKeyIndex];
		
		// Init variables for query string
		$start = $pageNumber + 1;
		$languageSef = '';
		$country = '';
		
		if(isset($customHeaders['acceptlanguage']) && $customHeaders['acceptlanguage']) {
			$languageSef = '&hl=' . Multilang::loadLanguageSEF($customHeaders['acceptlanguage']);
		}
		
		if(isset($customHeaders['countrytld']) && $customHeaders['countrytld']) {
			$country = '&gl=' . $customHeaders['countrytld'];
		}
		
		$url = "https://www.googleapis.com/customsearch/v1?key=$apiKey&cx=$cx&start=$start" . $languageSef . $country . "&q=" . urlencode($query);
		
		$ch = curl_init ( $url );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
		curl_setopt ( $ch, CURLOPT_HEADER, false);
		if(!ini_get('open_basedir')) {
			curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, 1 );
		}
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt ( $ch, CURLOPT_CONNECTTIMEOUT, 30 );
		
		// Check for proxy settings
		$cParams = ComponentHelper::getParams('com_jmap');
		if ($cParams->get('enable_proxy', 0)) {
			$proxyServer = $cParams->get('proxy_server_ipaddress', '');
			$proxyPort = $cParams->get('proxy_server_port', '');
			$proxyUsername = $cParams->get('proxy_server_username', '');
			$proxyPassword = $cParams->get('proxy_server_password', '');
			if (!empty($proxyServer)) curl_setopt($ch, CURLOPT_PROXY, $proxyServer);
			if (!empty($proxyPort)) curl_setopt($ch, CURLOPT_PROXYPORT, $proxyPort);
			if (!empty($proxyUsername) && !empty($proxyPassword)) curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyUsername . ':' . $proxyPassword);
		}
		
		$result = curl_exec ( $ch );
		
		$info = curl_getinfo ( $ch );
		$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close ( $ch );
		
		// Try to decode API response
		if($result) {
			$data = json_decode($result, true);
			if (json_last_error() !== JSON_ERROR_NONE) {
				$data = false;	
			}
		}
		
		return ($info ['http_code'] != 200) ? false : $data;
	}
	
	/**
	 * Returns integer, the number of aestimated indexed links
	 *
	 * @access public
	 * @param string $query The containing the search query.
	 * @return array $customHeaders The custom headers for country and language to get SERP for
	 */
	public static function getSerpsIndexedLinks($query) {
		return static::makeRequest ( $query, 0, [], true);
	}
	
	/**
	 * Returns array, containing detailed results parsed and formatted for any Google search SERP
	 *
	 * @access public
	 * @param string $query The containing the search query.
	 * @param int $pageNumber The SERP page number requested
	 * @return array $customHeaders The custom headers for country and language to get SERP for
	 */
	public static function getSerps($query, $pageNumber = 0, $customHeaders = []) {
		$result = static::makeRequest ( $query, $pageNumber, $customHeaders);
		return $result;
	}
	
	/**
	 * Returns integer, the number of aestimated indexed links
	 *
	 * @access public
	 * @param string $query The containing the search query.
	 * @return int The number of the page where the keyword for a given domain is found
	 */
	public static function getRankedPageKeyword($query, $domain, $customHeaders = []) {
		$fullFirst30Results = [];
		
		$curledSerpPage1 = static::gCurl ( $query, 0, $customHeaders );
		if($curledSerpPage1 && isset($curledSerpPage1['items'])) {
			$fullFirst30Results = array_merge($fullFirst30Results, $curledSerpPage1['items']);
		}
		
		$curledSerpPage2 = static::gCurl ( $query, 10, $customHeaders );
		if($curledSerpPage2 && isset($curledSerpPage2['items'])) {
			$fullFirst30Results = array_merge($fullFirst30Results, $curledSerpPage2['items']);
		}
		
		$curledSerpPage3 = static::gCurl ( $query, 20, $customHeaders );
		if($curledSerpPage3 && isset($curledSerpPage3['items'])) {
			$fullFirst30Results = array_merge($fullFirst30Results, $curledSerpPage3['items']);
		}
		
		$pageSerpIndex = null;
		foreach ( $fullFirst30Results as $indexResult=>$item ) {
			// Found a match in a SERP for this domain?
			if(stripos($item['link'], $domain) !== false) {
				$pageSerpIndex = $indexResult + 1;
				break;
			}
		}
		
		return $pageSerpIndex;
	}
}