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

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

$customCode = '';
if ($this->isTruthy('actionAnalyticsFbpixelCustomCfId'))
{
	$customCode = $this->factory->getA(Helper\Customfields::class)->getFieldValueById(
		$this->get('actionAnalyticsFbpixelCustomCfId')
	);
}
else if ($this->isTruthy('actionAnalyticsFbpixelCustom'))
{
	$customCode = $this->get('actionAnalyticsFbpixelCustom');
}

$customCode = empty($customCode)
	? $customCode
	: "\n\t" . $customCode . "\n"

?>
<script class="4SEO_analytics_rule_<?php echo $this->getAsInt('id'); ?>" <?php echo $this->platform->getDocumentNonce(true /* $sAttribute */); ?>>
    !function(f,b,e,v,n,t,s)
    {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/<?php echo $this->getEscaped('page_language');?>/fbevents.js');
    fbq('init', '<?php echo $this->getAsInt('actionAnalyticsFbpixelId');?>');
    fbq('track', 'PageView');<?php echo $customCode;?>
</script>
<noscript class="4SEO_analytics_rule_<?php echo $this->getAsInt('id'); ?>"><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?php echo $this->getAsInt('actionAnalyticsFbpixelId');?>&ev=PageView&noscript=1"/></noscript>
