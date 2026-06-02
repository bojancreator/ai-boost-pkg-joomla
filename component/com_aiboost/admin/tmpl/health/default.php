<?php
/**
 * AI Boost — Health Checker Template (Vue)
 * Injects check data as window.aiBoostHealth and mounts HealthApp.vue.
 *
 * @package     AiBoost\Component\AiBoost
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

HTMLHelper::_('stylesheet', 'com_aiboost/ab-tokens.css',     ['relative' => true, 'version' => 'auto']);
HTMLHelper::_('stylesheet', 'com_aiboost/ab-components.css', ['relative' => true, 'version' => 'auto']);
HTMLHelper::_('stylesheet', 'com_aiboost/admin.css',    ['relative' => true, 'version' => 'auto']);
HTMLHelper::_('script',     'com_aiboost/admin-vue.js', ['relative' => true, 'version' => 'auto']);

$tokenName = Session::getFormToken();

$healthData = [
    'score'     => (int) $this->score,
    'checks'    => $this->checks,
    'tokenName' => $tokenName,
    'urls'      => [
        'rerun'      => 'index.php?option=com_aiboost&task=health.rerun&format=json',
        'dismiss'    => 'index.php?option=com_aiboost&task=health.dismiss&format=json',
        'urlchecker' => Route::_('index.php?option=com_aiboost&view=urlchecker', false),
        'analyzer'   => Route::_('index.php?option=com_aiboost&view=analyzer',   false),
        'settings'   => Route::_('index.php?option=com_aiboost&view=settings',   false),
        'import'     => Route::_('index.php?option=com_aiboost&view=import',      false),
        'redirects'  => Route::_('index.php?option=com_aiboost&view=redirects',  false),
    ],
];
?>
<div class="container-fluid mt-3">

  <?php /* ── View navigation (horizontal) ── */ ?>
  <ul class="nav ab-view-nav mb-4">
    <li class="nav-item">
      <a class="nav-link" href="<?php echo Route::_('index.php?option=com_aiboost&view=dashboard'); ?>">
        <span class="icon-home me-1" aria-hidden="true"></span> <?php echo Text::_('COM_AIBOOST_NAV_DASHBOARD'); ?>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="<?php echo Route::_('index.php?option=com_aiboost&view=settings'); ?>">
        <span class="icon-cog me-1" aria-hidden="true"></span> <?php echo Text::_('COM_AIBOOST_NAV_SETTINGS'); ?>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link active" href="<?php echo Route::_('index.php?option=com_aiboost&view=health'); ?>">
        <span class="icon-heart me-1" aria-hidden="true"></span> <?php echo Text::_('COM_AIBOOST_NAV_HEALTH'); ?>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="<?php echo Route::_('index.php?option=com_aiboost&view=redirects'); ?>">
        <span class="icon-arrow-right me-1" aria-hidden="true"></span> <?php echo Text::_('COM_AIBOOST_NAV_REDIRECTS'); ?>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="<?php echo Route::_('index.php?option=com_aiboost&view=urlchecker'); ?>">
        <span class="icon-link me-1" aria-hidden="true"></span> <?php echo Text::_('COM_AIBOOST_NAV_URLCHECKER'); ?>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="<?php echo Route::_('index.php?option=com_aiboost&view=import'); ?>">
        <span class="icon-upload me-1" aria-hidden="true"></span> <?php echo Text::_('COM_AIBOOST_NAV_IMPORT'); ?>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="<?php echo Route::_('index.php?option=com_aiboost&view=integrations'); ?>">
        <span class="icon-puzzle-piece me-1" aria-hidden="true"></span> <?php echo Text::_('COM_AIBOOST_NAV_INTEGRATIONS'); ?>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="<?php echo Route::_('index.php?option=com_aiboost&view=analyzer'); ?>">
        <span class="icon-search me-1" aria-hidden="true"></span> <?php echo Text::_('COM_AIBOOST_NAV_ANALYZERS'); ?>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" href="<?php echo Route::_('index.php?option=com_aiboost&view=help'); ?>">
        <span class="icon-question me-1" aria-hidden="true"></span> <?php echo Text::_('COM_AIBOOST_NAV_HELP'); ?>
      </a>
    </li>
  </ul>
  <?php /* ── Inject data for Vue HealthApp ── */ ?>
  <script>
  window.aiBoostHealth = <?php echo json_encode($healthData, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}'; ?>;
  </script>

  <?php /* ── Vue mount point ── */ ?>
  <div id="ab-vue-health"></div>

</div>
