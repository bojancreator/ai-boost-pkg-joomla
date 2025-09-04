<?php
/**
 * Project: 4SEF
 *
 * @package          4SEF
 * @copyright        Copyright Weeblr llc - 2022 -2025
 * @author           Yannick Gaultier - Weeblr llc
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          2.6.2.644
 * @date        2025-06-02
 *
 */

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

?>
<div id="forsef_app">
    <div style="margin-top: 5em; padding: 0 5em;">
        <div id="forsef_noscript" class="wb-container wb-leading-loose">
            <h2 class="m-5 font-weight-bold" style="font-size: 1.2rem;">Sorry, we can't continue :(</h2>
            <img class="m-5 mw-100" src="https://cdn.weeblr.net/dist/weeblr/img/4sef/undraw_bug_fixing_oc7a.svg"
                 width="350" alt="">
            <p class="m-5">4SEF requires javascript and it seems it's not
                enabled on this browser. Make sure it's
                enabled and reload the page to get started.</p>
        </div>
        <div id="forsef_internal_error" class="wb-container wb-leading-loose" style="display:none;">
            <h2 class="m-5 font-weight-bold" style="font-size: 1.2rem;">Something went wrong :(</h2>
            <img class="m-5 mw-100" src="https://cdn.weeblr.net/dist/weeblr/img/4sef/undraw_bug_fixing_oc7a.svg"
                 width="350" alt="">
            <p class="m-5" style="max-width: 50rem;">An internal error occured and this app cannot continue.
                Please contact <a href="https://weeblr.com" target="_blank" rel="noreferrer noopener">Weeblr</a> with as
                much information as possible on what happened. Sorry about the trouble!</p>
        </div>
    </div>
    <script>
        let ns = document.getElementById('forsef_noscript')
        ns.textContent = ''
        setTimeout(function () {
                let er = document.getElementById('forsef_internal_error');
                if (er) er.style.display = 'block'
            }, 2000
        )
    </script>
</div>