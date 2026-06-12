<?php
/**
 * AI Boost — Integrations Template
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
HTMLHelper::_('stylesheet', 'com_aiboost/admin.css', ['relative' => true, 'version' => 'auto']);
HTMLHelper::_('script',     'com_aiboost/admin-vue.js', ['relative' => true, 'version' => 'auto']);

// Inject integration detection data for Vue
$integrationsJson = json_encode(array_values($this->integrations), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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
      <a class="nav-link" href="<?php echo Route::_('index.php?option=com_aiboost&view=health'); ?>">
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
      <a class="nav-link active" href="<?php echo Route::_('index.php?option=com_aiboost&view=integrations'); ?>">
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
  <script>
    window.aiBoostIntegrations = <?php echo $integrationsJson; ?>;
    // CSRF token for the integration master-toggle endpoint. The SPA shell
    // (view=app) carries this in window.aiBoostBootstrap; this legacy thin
    // shell has no bootstrap, so expose it the same way urlchecker does.
    window.aiBoostToken = <?php echo json_encode(Session::getFormToken()); ?>;
  </script>

  <!-- Vue mounts here -->
  <div id="ab-vue-integrations"></div>

</div>
