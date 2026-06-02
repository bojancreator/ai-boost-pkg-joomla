<?php
/**
 * Project: 4SEO
 *
 * @package          4SEO
 * @copyright        Copyright Weeblr llc - 2020 - 2026
 * @author           Yannick Gaultier - Weeblr llc
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          6.10.1.2660
 * @date        2026-01-30
 *
 */

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

$nonce = $this->platform->getDocumentNonce(true /* $sAttribute */);

?>
<div id="forseo_app"
     style="position:fixed;left:0;right:0;top:0;bottom:0;z-index:999999;background:transparent;pointer-events: none"></div>
<script class="4SEO_fe_editing" <?php echo $this->platform->getDocumentNonce(true /* $sAttribute */); ?>>setTimeout(function () {
        var e = document.createElement('link');
        e.setAttribute('href', forSeoConfig.cssFile);
        e.setAttribute('rel', 'stylesheet');
        document.head.appendChild(e);
        e = document.createElement('script');
        e.setAttribute('src', forSeoConfig.bundleFile);
        forSeoConfig.isDev ? e.setAttribute('type', 'module') : e.setAttribute('async', '');
        document.body.appendChild(e);
    }, <?php echo $this->getAsInt('showAfter'); ?>);
</script>

