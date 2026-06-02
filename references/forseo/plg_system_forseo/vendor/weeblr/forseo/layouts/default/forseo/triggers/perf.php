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
<script class="4SEO_performance_probe" data-speed-no-transform <?php echo $this->platform->getDocumentNonce(true /* $sAttribute */); ?>>
    var forseoPerfProbeEndpoint = '<?php echo $this->get('triggerUrl'); ?>&u=<?php echo urlencode($this->get('url')); ?>&f=<?php echo urlencode($this->get('fullUrl')); ?>'
	<?php include __DIR__ . '/perf-probe.js'; ?>
</script>
