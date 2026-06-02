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

$datalayer = $this->isTruthy('actionAnalyticsGtmDatalayer')
	? $this->get('actionAnalyticsGtmDatalayer')
	: ''
?>
<script class="4SEO_analytics_rule_<?php echo $this->getEscaped('id'); ?>" <?php echo $this->platform->getDocumentNonce(true /* $sAttribute */); ?>>
    <?php echo empty($datalayer)?'': $datalayer; ?>
    (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?php echo $this->getEscaped('actionAnalyticsGtmId', ''); ?>');
</script>
