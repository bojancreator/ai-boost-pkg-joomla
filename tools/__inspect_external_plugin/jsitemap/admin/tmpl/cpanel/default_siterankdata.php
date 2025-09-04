<?php 
/** 
 * @package JMAP::CPANEL::administrator::components::com_jmap
 * @subpackage views
 * @subpackage cpanel
 * @subpackage tmpl
 * @author Joomla! Extensions Store
 * @copyright (C) 2021 - Joomla! Extensions Store
 * @license GNU/GPLv2 http://www.gnu.org/licenses/gpl-2.0.html  
 */
defined ( '_JEXEC' ) or die ( 'Restricted access' );
use Joomla\CMS\Language\Text;
?>
<!-- SITERANKDATA SEOSTATS -->
<div class="single_stat_container">
	<div class="statcircle">
		<span class="fas fa-chart-line fa-icon-large" aria-hidden="true"></span>
	</div>
	<ul class="subdescription_stats">
		<li data-bind="{siterankdata_rank}" class="es-stat-no"></li>
		<li class="es-stat-title"><?php echo Text::_('COM_JMAP_SITERANKDATA_PAGE_RANK');?></li>
	</ul>
</div>

<div class="single_stat_container">
	<div class="statcircle">
		<span class="fas fa-user fa-icon-large" aria-hidden="true"></span>
	</div>
	<ul class="subdescription_stats">
		<li data-bind="{daily_unique_visitors}" class="es-stat-no"></li>
		<li class="es-stat-title"><?php echo Text::_('COM_JMAP_DAILY_UNIQUE_VISITORS');?></li>
	</ul>
</div>

<div class="single_stat_container">
	<div class="statcircle">
		<span class="fas fa-tag fa-icon-large" aria-hidden="true"></span>
	</div>
	<ul class="subdescription_stats">
		<li data-bind="{keywords_number}" class="es-stat-no"></li>
		<li class="es-stat-title"><?php echo Text::_('COM_JMAP_SITERANKDATA_KEYWORDS_NUMBER');?></li>
	</ul>
</div>

<div class="single_stat_container">
	<div class="statcircle">
		<span class="fas fa-arrow-left fa-icon-large" aria-hidden="true"></span>
	</div>
	<ul class="subdescription_stats">
		<li data-bind="{backlinks_number}" class="es-stat-no"></li>
		<li class="es-stat-title"><?php echo Text::_('COM_JMAP_SITERANKDATA_BACKLINKS_NUMBER');?></li>
	</ul>
</div>

<div class="single_stat_container">
	<div class="statcircle">
		<span class="fas fa-link fa-icon-large" aria-hidden="true"></span>
	</div>
	<ul class="subdescription_stats">
		<li data-bind="{totaluniqueurls}" class="es-stat-no"></li>
		<li class="es-stat-title"><?php echo Text::_('COM_JMAP_SITERANKDATA_TOTALUNIQUEURLS');?></li>
	</ul>
</div>

<div class="single_stat_container">
	<div class="statcircle">
		<span class="fas fa-compress-alt fa-icon-large" aria-hidden="true"></span>
	</div>
	<ul class="subdescription_stats">
		<li data-bind="{pageviews}" class="es-stat-no"></li>
		<li class="es-stat-title"><?php echo Text::_('COM_JMAP_SITERANKDATA_PAGEVIEWS');?></li>
	</ul>
</div>