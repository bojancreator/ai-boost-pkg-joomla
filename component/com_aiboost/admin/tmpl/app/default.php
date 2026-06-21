<?php
/**
 * AI Boost — SPA Shell Template
 *
 * Loads admin.css + admin-vue.js bundle, injects the bootstrap blob and
 * renders the single SPA mount point. Vue (AppShell.vue + vue-router) takes
 * over from there.
 *
 * @package     AiBoost\Component\AiBoost
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;

// IBM Plex Sans + Mono — loaded only on AI Boost screens (this template)
echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap">';

HTMLHelper::_('stylesheet', 'com_aiboost/ab-tokens.css',     ['relative' => true, 'version' => 'auto']);
HTMLHelper::_('stylesheet', 'com_aiboost/ab-components.css', ['relative' => true, 'version' => 'auto']);
HTMLHelper::_('stylesheet', 'com_aiboost/admin.css',    ['relative' => true, 'version' => 'auto']);
HTMLHelper::_('script',     'com_aiboost/admin-vue.js', ['relative' => true, 'version' => 'auto']);
?>
<script>
window.aiBoostBootstrap = <?php echo $this->bootstrapJson; ?>;
</script>

<div id="ab-app" class="ab-spa-root"></div>
