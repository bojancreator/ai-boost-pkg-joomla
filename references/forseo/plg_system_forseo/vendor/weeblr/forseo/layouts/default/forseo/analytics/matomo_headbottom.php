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

use Weeblr\Forseo\Helper;

use Weeblr\Wblib\Forseo\Wb;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

$endpoint = Wb\rTrim(
		$this->getAsUrl('actionAnalyticsMatomoEndpoint'),
		'/')
	. '/';

$custom = '';
if ($this->isTruthy('actionAnalyticsMatomoCustomCfId'))
{
	$custom = $this->factory->getA(Helper\Customfields::class)->getFieldValueById(
		$this->get('actionAnalyticsMatomoCustomCfId')
	);
}
else if ($this->isTruthy('actionAnalyticsMatomoCustom'))
{
	$custom = $this->get('actionAnalyticsMatomoCustom');
}

?>
<script class="4SEO_analytics_rule_<?php echo $this->getAsInt('id'); ?>" <?php echo $this->platform->getDocumentNonce(true /* $sAttribute */); ?>>
    var _paq = window._paq = window._paq || [];
    /* tracker methods like "setCustomDimension" should be called before "trackPageView" */
    <?php if(!empty($custom))
    {
    echo $custom . "\n\t";
    } ?>_paq.push(['trackPageView']);
    _paq.push(['enableLinkTracking']);
    (function() {
        var u="<?php echo $endpoint;?>";
        _paq.push(['setTrackerUrl', u+'matomo.php']);
        _paq.push(['setSiteId', '<?php echo $this->getAsInt('actionAnalyticsMatomoId');?>']);
        var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
        g.type='text/javascript'; g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
    })();
</script>
<?php if ($this->isTruthy('actionAnalyticsMatomoTrackWithoutJs')): ?>
<noscript><p><img src="<?php echo $endpoint;?>matomo.php?idsite=<?php echo $this->getAsInt('actionAnalyticsMatomoId');?>&amp;rec=1" style="border:0;" alt="" /></p></noscript>
<?php endif; ?>