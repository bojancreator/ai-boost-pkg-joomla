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
<div id="forseo_app" class="wbl-j-<?php echo $this->platform->majorVersion();?>">
    <div style="margin-top: 5em; padding: 0 5em;">
        <div id="forseo_noscript" class="wb-container wb-leading-loose">
            <h2 class="m-5 font-weight-bold" style="font-size: 1.2rem;">Sorry, we can't continue :(</h2>
            <img class="m-5 mw-100" src="https://cdn.weeblr.net/dist/weeblr/img/4seo/undraw_bug_fixing_oc7a.svg"
                 width="350" alt="">
            <p class="m-5">4SEO requires javascript and it seems it's not
                enabled on this browser. Make sure it's
                enabled and reload the page to get started.</p>
        </div>
        <div id="forseo_internal_error" class="wb-container wb-leading-loose" style="display:none;">
            <h2 class="m-5 font-weight-bold" style="font-size: 1.2rem;">Something went wrong :(</h2>
            <img class="m-5 mw-100" src="https://cdn.weeblr.net/dist/weeblr/img/4seo/undraw_bug_fixing_oc7a.svg"
                 width="350" alt="">
            <p class="m-5" style="max-width: 50rem;">An internal error occured and this app cannot continue.
                Please contact <a href="https://weeblr.com" target="_blank" rel="noreferrer noopener">Weeblr</a> with as
                much information as possible on what happened. Sorry about the trouble!</p>
        </div>
    </div>
    <script <?php echo $this->platform->getDocumentNonce(true /* $sAttribute */); ?>>
        let ns = document.getElementById('forseo_noscript')
        ns.textContent = ''
        setTimeout(function () {
                let er = document.getElementById('forseo_internal_error');
                if (er) er.style.display = 'block'
            }, 2000
        )
    </script>
</div>