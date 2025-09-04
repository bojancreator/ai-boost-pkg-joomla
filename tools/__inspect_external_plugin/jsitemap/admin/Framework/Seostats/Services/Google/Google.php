<?php
namespace JExtstore\Component\JMap\Administrator\Framework\Seostats\Services;
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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Component\ComponentHelper;
use JExtstore\Component\JMap\Administrator\Framework\Seostats;

/**
 * Google stats service
 *
 * @package JMAP::SEOSTATS::administrator::components::com_jmap
 * @subpackage seostats
 * @subpackage services
 * @subpackage google
 * @since 3.0
 */
class Google extends Seostats {
	/**
	 * Returns the total amount of results for a Google 'site:'-search for the object URL.
	 *
	 * @param string $url
	 *        	String, containing the query URL.
	 * @return integer Returns the total site-search result count.
	 */
	public static function getSiteindexTotal($url = false) {
		$url = parent::getUrl ( $url );
		$siteQuery = ComponentHelper::getParams('com_jmap')->get('seostats_site_query', 1) ? 'site:' : null;
		$query = $siteQuery . $url;
		
		$numericValue = Google\Search::getSerpsIndexedLinks ( $query );
		
		if(!$numericValue) {
			$numericValue = Text::_ ( 'COM_JMAP_NA' );
		}
		
		return $numericValue;
	}
	
	/**
	 * Public interface to get containing detailed results parsed and formatted for any Google search SERP
	 *
	 * @access public
	 * @param string $query The containing the search query.
	 * @param int $pageNumber The SERP page number requested
	 * @return array $customHeaders The custom headers for country and language to get SERP for
	 */
	public static function getSerps($query, $pageNumber = 0, $customHeaders = array()) {
		return Google\Search::getSerps ( $query, $pageNumber, $customHeaders );
	}
	
	/**
	 * Public interface to get the ranked page for a given keyword and website domain for any Google search SERP
	 *
	 * @access public
	 * @param string $query The containing the search query.
	 * @param int $pageNumber The SERP page number requested
	 * @return array $customHeaders The custom headers for country and language to get SERP for
	 */
	public static function getRankedPageKeyword($query, $domain, $customHeaders = array()) {
		return Google\Search::getRankedPageKeyword ( $query, $domain, $customHeaders );
	}
}