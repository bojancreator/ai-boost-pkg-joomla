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


if (time() > 1719748800)
{
	// UGA stops operating July 1st, 2024
	return;
}

$bits           = [];
$createFragment = "\tga('create', '" . $this->getEscaped('actionAnalyticsUniversalgaId', '') . "'";
if ($this->isFalsy('actionAnalyticsGaCustomDomain'))
{
	$createFragment .= ", ''";
}
else
{
	$createFragment .= ", '" . $this->getEscaped('actionAnalyticsGaCustomDomain') . "'";
}
if ($this->isTruthy('actionAnalyticsGaOptions'))
{
	$createFragment .= ", '" . json_encode($this->get('actionAnalyticsGaOptions')) . "'";
}
$bits[] = $createFragment . ');';

if ($this->isTruthy('actionAnalyticsGaAnonymize'))
{
	$bits[] = "\tga('set', 'anonymizeIp', true);";
}

if ($this->isTruthy('actionAnalyticsGaDisplayFeatures'))
{
	$bits[] = "\tga('require', 'displayfeatures');";
}
if ($this->isFalsy('actionAnalyticsGaAdFeatures'))
{
	$bits[] = "\tga('set', 'allowAdFeatures', false);";
}

if ($this->isTruthy('actionAnalyticsGaLinkAttribution'))
{
	$bits[] = "\tga('require', 'linkid');";
}

if ($this->isTruthy('actionAnalyticsUniversalgaCustomCfId'))
{
	$bits[] = "\t" . $this->factory->getA(Helper\Customfields::class)->getFieldValueById(
			$this->get('actionAnalyticsUniversalgaCustomCfId')
		);
}
else if ($this->isTruthy('actionAnalyticsUniversalgaCustom'))
{
	$bits[] = "\t" . $this->get('actionAnalyticsUniversalgaCustom');
}

$sendFragment = "\tga('send', 'pageview'";
if ($this->isTruthy('custom_url'))
{
	$sendFragment .= ", '" . $this->getAsUrl('custom_url') . "'";
}
$bits[] = $sendFragment . ");\n";

?>
<script class="4SEO_analytics_rule_<?php echo $this->getAsInt('id'); ?>" <?php echo $this->platform->getDocumentNonce(true /* $sAttribute */); ?>>
    (function (i, s, o, g, r, a, m) {
        i['GoogleAnalyticsObject'] = r;
        i[r] = i[r] || function () {
            (i[r].q = i[r].q || []).push(arguments)
        }, i[r].l = 1 * new Date();
        a = s.createElement(o),
            m = s.getElementsByTagName(o)[0];
        a.async = 1;
        a.src = g;
        m.parentNode.insertBefore(a, m)
    })(window, document, 'script', '//www.google-analytics.com/analytics.js', 'ga');
	<?php echo implode("\n", $bits); ?>
</script>
