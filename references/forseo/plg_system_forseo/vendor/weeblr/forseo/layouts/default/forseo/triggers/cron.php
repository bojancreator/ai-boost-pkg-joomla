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

?>
<noscript class="4SEO_cron">
    <img aria-hidden="true" alt="" style="position:absolute;bottom:0;left:0;z-index:-99999;" src="<?php echo $this->get('triggerCronUrl'); ?>" data-pagespeed-no-transform data-speed-no-transform />
</noscript>
<script class="4SEO_cron" data-speed-no-transform <?php echo $this->platform->getDocumentNonce(true /* $sAttribute */); ?>>setTimeout(function () {
        var e = document.createElement('img');
        e.setAttribute('style', 'position:absolute;bottom:0;right:0;z-index:-99999');
        e.setAttribute('aria-hidden', 'true');
        e.setAttribute('src', '<?php echo $this->get('triggerCronUrl'); ?>' + Math.random().toString().substring(2) + Math.random().toString().substring(2)  + '.svg');
        document.body.appendChild(e);
        setTimeout(function () {
            document.body.removeChild(e)
        }, <?php echo $this->getAsInt('softCronRemoveAfter'); ?>)
    }, <?php echo $this->getAsInt('softCronTriggerAfter'); ?>);
</script>
