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
if ($this->isTruthy('actionAnalyticsGlobalgaCustomCfId'))
{
	$customCode = $this->factory->getA(Helper\Customfields::class)->getFieldValueById(
		$this->get('actionAnalyticsGlobalgaCustomCfId')
	);
}
else if ($this->isTruthy('actionAnalyticsGlobalgaCustom'))
{
	$customCode = $this->get('actionAnalyticsGlobalgaCustom');
}

$customCode = empty($customCode)
	? $customCode
	: "\t" . $customCode . "\n"

?>
<script class="4SEO_analytics_rule_<?php echo $this->getAsInt('id'); ?>" async src="https://www.googletagmanager.com/gtag/js?id=<?php echo $this->getEscaped('actionAnalyticsGlobalgaId', ''); ?>" <?php echo $this->platform->getDocumentNonce(true /* $sAttribute */); ?>></script>
<script class="4SEO_analytics_rule_<?php echo $this->getAsInt('id'); ?>" <?php echo $this->platform->getDocumentNonce(true /* $sAttribute */); ?>>
    window.dataLayer = window.dataLayer || [];function gtag() {dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '<?php echo $this->getEscaped('actionAnalyticsGlobalgaId', ''); ?>');<?php if (!empty($customCode))
    {
    echo "\n" . $customCode;
    } ?>
</script>