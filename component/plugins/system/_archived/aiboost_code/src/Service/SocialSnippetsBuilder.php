<?php
/**
 * AI Boost — SocialSnippetsBuilder
 *
 * Generates complete, production-ready JavaScript tracking snippets for the
 * major advertising and analytics platforms, following each platform's official
 * implementation exactly. The admin provides only the account/pixel ID; this
 * class builds the full snippet.
 *
 * Supported platforms:
 *   - Facebook Pixel (Meta Pixel) — fbevents.js loader + configurable standard events
 *   - Google Ads Global Site Tag (gtag.js)
 *   - LinkedIn Insight Tag
 *   - TikTok Pixel
 *   - Pinterest Base Tag
 *
 * Usage:
 *   $builder = new SocialSnippetsBuilder($params);
 *   $headSnippets = $builder->buildHeadSnippets();  // → string[]
 *
 * @package     AiBoost\Plugin\System\AiBoostCode
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostCode\Service;

defined('_JEXEC') or die;

use Joomla\Registry\Registry;

class SocialSnippetsBuilder
{
    /** @var Registry Plugin params */
    private Registry $params;

    /**
     * Allowed Facebook standard event names (allowlist prevents XSS if params are tampered).
     */
    private const FB_ALLOWED_EVENTS = [
        'PageView', 'Purchase', 'Lead', 'AddToCart', 'ViewContent',
    ];

    public function __construct(Registry $params)
    {
        $this->params = $params;
    }

    /**
     * Build all enabled social snippets for injection into <head>.
     *
     * Returns an array of raw HTML/JS strings, one per enabled platform.
     * Caller concatenates and injects before </head>.
     *
     * @return string[]
     */
    public function buildHeadSnippets(): array
    {
        $snippets = [];

        if ((int) $this->params->get('enable_fb_pixel', 0)) {
            $id = trim((string) $this->params->get('fb_pixel_id', ''));
            if ($id !== '') {
                $snippets[] = $this->buildFacebookPixel($id);
            }
        }

        if ((int) $this->params->get('enable_google_ads', 0)) {
            $id = trim((string) $this->params->get('google_ads_id', ''));
            if ($id !== '') {
                $snippets[] = $this->buildGoogleAds($id);
            }
        }

        if ((int) $this->params->get('enable_linkedin', 0)) {
            $id = trim((string) $this->params->get('linkedin_partner_id', ''));
            if ($id !== '') {
                $snippets[] = $this->buildLinkedIn($id);
            }
        }

        if ((int) $this->params->get('enable_tiktok', 0)) {
            $id = trim((string) $this->params->get('tiktok_pixel_id', ''));
            if ($id !== '') {
                $snippets[] = $this->buildTikTok($id);
            }
        }

        if ((int) $this->params->get('enable_pinterest', 0)) {
            $id = trim((string) $this->params->get('pinterest_tag_id', ''));
            if ($id !== '') {
                $snippets[] = $this->buildPinterest($id);
            }
        }

        return $snippets;
    }

    // ── Platform builders ─────────────────────────────────────────────────────

    /**
     * Facebook Pixel (Meta Pixel) — official implementation.
     *
     * Loads fbevents.js, calls fbq('init', pixelId), fires fbq('track', 'PageView')
     * and any additional standard events selected in plugin settings.
     * Includes noscript <img> fallback as per Meta documentation.
     */
    private function buildFacebookPixel(string $pixelId): string
    {
        $safeId     = htmlspecialchars($pixelId, ENT_QUOTES, 'UTF-8');
        $jsId       = json_encode($pixelId); // safely-quoted for JS context

        // Collect enabled events (PageView is always fired; others are additive).
        // Joomla checkboxes field may return: JSON string, indexed array of values,
        // or an associative array/object where keys are values and values are booleans/1/0.
        $rawEvents = $this->params->get('fb_events', []);
        if (is_string($rawEvents)) {
            $rawEvents = json_decode($rawEvents, true) ?: [];
        }
        if (is_object($rawEvents)) {
            $rawEvents = (array) $rawEvents;
        }
        $events = is_array($rawEvents) ? $rawEvents : [];

        // Determine selected event names regardless of storage shape
        $selectedEvents = [];
        foreach ($events as $key => $value) {
            // Indexed array: value is the event name (e.g. ['Purchase', 'Lead'])
            if (is_int($key) && is_string($value)) {
                $selectedEvents[] = $value;
            }
            // Associative map: key is event name, value is truthy flag (e.g. {'Purchase':1})
            elseif (is_string($key) && $value) {
                $selectedEvents[] = $key;
            }
        }

        $extraEvents = '';
        foreach ($selectedEvents as $event) {
            $event = (string) $event;
            if (in_array($event, self::FB_ALLOWED_EVENTS, true) && $event !== 'PageView') {
                $extraEvents .= "\n  fbq('track', " . json_encode($event) . ');';
            }
        }

        return <<<HTML
<!-- Facebook Pixel (AI Boost Code Manager) -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', {$jsId});
fbq('track', 'PageView');{$extraEvents}
</script>
<noscript><img height="1" width="1" style="display:none"
  src="https://www.facebook.com/tr?id={$safeId}&ev=PageView&noscript=1"
/></noscript>
<!-- End Facebook Pixel -->
HTML;
    }

    /**
     * Google Ads Global Site Tag (gtag.js) — official implementation.
     *
     * Loads gtag.js asynchronously and initialises the conversion account.
     * The account ID format is AW-XXXXXXXXXX.
     */
    private function buildGoogleAds(string $adsId): string
    {
        $safeId = htmlspecialchars($adsId, ENT_QUOTES, 'UTF-8');
        $jsId   = json_encode($adsId);

        return <<<HTML
<!-- Google Ads Conversion Tag (AI Boost Code Manager) -->
<script async src="https://www.googletagmanager.com/gtag/js?id={$safeId}"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', {$jsId});
</script>
<!-- End Google Ads Conversion Tag -->
HTML;
    }

    /**
     * LinkedIn Insight Tag — official implementation.
     *
     * Loads insight.min.js via a self-invoking function, sets the partner ID.
     * Includes <noscript> image fallback.
     */
    private function buildLinkedIn(string $partnerId): string
    {
        $safeId = htmlspecialchars($partnerId, ENT_QUOTES, 'UTF-8');
        $jsId   = json_encode($partnerId);

        return <<<HTML
<!-- LinkedIn Insight Tag (AI Boost Code Manager) -->
<script type="text/javascript">
_linkedin_partner_id = {$jsId};
window._linkedin_data_partner_ids = window._linkedin_data_partner_ids || [];
window._linkedin_data_partner_ids.push(_linkedin_partner_id);
</script>
<script type="text/javascript">
(function(l) {
if (!l){window.lintrk = function(a,b){window.lintrk.q.push([a,b])};
window.lintrk.q=[]}
var s = document.getElementsByTagName("script")[0];
var b = document.createElement("script");
b.type = "text/javascript";b.async = true;
b.src = "https://snap.licdn.com/li.lms-analytics/insight.min.js";
s.parentNode.insertBefore(b, s);})(window.lintrk);
</script>
<noscript>
<img height="1" width="1" style="display:none;" alt=""
  src="https://px.ads.linkedin.com/collect/?pid={$safeId}&fmt=gif" />
</noscript>
<!-- End LinkedIn Insight Tag -->
HTML;
    }

    /**
     * TikTok Pixel — official implementation (ttq snippet).
     */
    private function buildTikTok(string $pixelId): string
    {
        $jsId = json_encode($pixelId);

        return <<<HTML
<!-- TikTok Pixel (AI Boost Code Manager) -->
<script>
!function (w, d, t) {
  w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=document.createElement("script");o.type="text/javascript",o.async=!0,o.src=i+"?sdkid="+e+"&lib="+t;var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};
  ttq.load({$jsId});
  ttq.page();
}(window, document, 'ttq');
</script>
<!-- End TikTok Pixel -->
HTML;
    }

    /**
     * Pinterest Base Tag — official implementation.
     *
     * Loads pintrk.js and fires a page view event.
     */
    private function buildPinterest(string $tagId): string
    {
        $safeId = htmlspecialchars($tagId, ENT_QUOTES, 'UTF-8');
        $jsId   = json_encode($tagId);

        return <<<HTML
<!-- Pinterest Base Tag (AI Boost Code Manager) -->
<script>
!function(e){if(!window.pintrk){window.pintrk=function(){window.pintrk.queue.push(
Array.prototype.slice.call(arguments))};var n=window.pintrk;n.queue=[],n.version="3.0";
var t=document.createElement("script");t.async=!0,t.src=e;var r=document.getElementsByTagName("script")[0];
r.parentNode.insertBefore(t,r)}}("https://s.pinimg.com/ct/core.js");
pintrk('load', {$jsId});
pintrk('page');
</script>
<noscript>
<img height="1" width="1" style="display:none;" alt=""
  src="https://ct.pinterest.com/v3/?event=init&tid={$safeId}&noscript=1" />
</noscript>
<!-- End Pinterest Base Tag -->
HTML;
    }
}
