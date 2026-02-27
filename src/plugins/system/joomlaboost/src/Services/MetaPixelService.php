<?php

declare(strict_types=1);

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Services;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\Registry\Registry;

/**
 * Meta Pixel (Facebook Pixel) Service
 *
 * Handles Meta Pixel tracking code injection and event tracking
 */
final class MetaPixelService
{
    /**
     * Plugin parameters
     */
    private Registry $params;

    /**
     * Constructor
     */
    public function __construct(Registry $params)
    {
        $this->params = $params;
    }

    /**
     * Get plugin version from XML manifest
     */
    private function getPluginVersion(): string
    {
        static $version = null;

        if ($version === null) {
            $xmlPath = dirname(__DIR__, 2) . '/joomlaboost.xml';
            if (file_exists($xmlPath)) {
                $xmlContent = file_get_contents($xmlPath);
                if (preg_match('/<version>([^<]+)<\/version>/', $xmlContent, $matches)) {
                    $version = $matches[1];
                } else {
                    $version = 'unknown';
                }
            } else {
                $version = 'unknown';
            }
        }

        return $version;
    }

    /**
     * Check if Meta Pixel is enabled
     */
    public function isEnabled(): bool
    {
        return (bool) $this->params->get('enable_meta_pixel', false);
    }

    /**
     * Get Meta Pixel ID
     */
    public function getPixelId(): string
    {
        return (string) $this->params->get('meta_pixel_id', '');
    }

    /**
     * Check if Meta Pixel is properly configured
     */
    public function isConfigured(): bool
    {
        return $this->isEnabled() && !empty($this->getPixelId());
    }

    /**
     * Inject Meta Pixel base code into document head
     */
    public function injectPixelCode(HtmlDocument $document): void
    {
        if (!$this->isConfigured()) {
            return;
        }

        $pixelId = $this->getPixelId();
        $version = 'JoomlaBoost v' . $this->getPluginVersion();

        $pixelCode = $this->generatePixelCode($pixelId, $version);    // Add to document head
        $document->addCustomTag($pixelCode);
    }

    /**
     * Generate Meta Pixel tracking code
     *
     * Supports GDPR consent modes:
     * - 'none'      : Direct inject (legacy, no consent control)
     * - 'yootheme'  : Uses type="text/plain" + data-category="marketing.Meta Pixel"
     *                 for YooTheme Pro 5 consent manager.
     *
     * CRITICAL: type="text/plain" is REQUIRED. YooTheme consent.js only blocks scripts
     * that have type="text/plain" + data-category. Without it, script runs immediately!
     *
     * The category name "marketing.Meta Pixel" must match the name defined in:
     * YooTheme Customizer > Theme > Consent > Marketing > item name
     * Default YooTheme config: yootheme.consent.categories.marketing = ["Meta Pixel", "google_ads"]
     */
    private function generatePixelCode(string $pixelId, string $version): string
    {
        $consentMode = $this->params->get('pixel_consent_mode', 'none');

        $innerScript = sprintf(
            '!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version=\'2.0\';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,\'script\',
\'https://connect.facebook.net/en_US/fbevents.js\');
fbq(\'init\', \'%s\');
fbq(\'track\', \'PageView\');',
            $pixelId
        );

        if ($consentMode === 'yootheme') {
            // type="text/plain" prevents browser from executing the script.
            // IMPORTANT: No spaces in category name!
            // consent.js splits data-category by whitespace (/\s+/)
            // so 'marketing.Meta Pixel' would be parsed as 'marketing.Meta' + 'Pixel' (wrong!)
            // Using 'meta_pixel' (no space) to avoid this bug.
            return sprintf(
                '<!-- Meta Pixel Code by %s (YooTheme Consent) -->
<!-- Step 1: Register meta_pixel in YooTheme consent categories -->
<script>
(function() {
  window.yootheme = window.yootheme || {};
  window.yootheme.consent = window.yootheme.consent || {};
  window.yootheme.consent.categories = window.yootheme.consent.categories || {};
  window.yootheme.consent.categories.marketing = window.yootheme.consent.categories.marketing || [];
  var m = window.yootheme.consent.categories.marketing;
  if (m.indexOf(\'meta_pixel\') === -1) m.push(\'meta_pixel\');
})();
</script>
<!-- Step 2: Blocked until user accepts Marketing cookies -->
<script type="text/plain" data-category="marketing.meta_pixel">
%s
</script>
<noscript>
<img height="1" width="1" style="display:none"
     src="https://www.facebook.com/tr?id=%s&ev=PageView&noscript=1"/>
</noscript>
<!-- End Meta Pixel Code -->',
                $version,
                $innerScript,
                $pixelId
            );
        }



        // Default: direct inject (legacy, no consent)
        return sprintf(
            '<!-- Meta Pixel Code by %s -->
<script>
%s
</script>
<noscript>
<img height="1" width="1" style="display:none"
     src="https://www.facebook.com/tr?id=%s&ev=PageView&noscript=1"/>
</noscript>
<!-- End Meta Pixel Code -->',
            $version,
            $innerScript,
            $pixelId
        );
    }



    /**
     * Generate custom event tracking code
     */
    public function generateCustomEventCode(): string
    {
        if (!$this->isConfigured()) {
            return '';
        }

        $events = [];
        $version = 'JoomlaBoost v' . $this->getPluginVersion();

        // Purchase event
        if ($this->params->get('meta_pixel_track_purchase', false)) {
            $events[] = $this->generateEventScript('Purchase');
        }

        // Add to Cart event
        if ($this->params->get('meta_pixel_track_add_to_cart', false)) {
            $events[] = $this->generateEventScript('AddToCart');
        }

        // Contact event
        if ($this->params->get('meta_pixel_track_contact', false)) {
            $events[] = $this->generateEventScript('Contact');
        }

        // Lead event
        if ($this->params->get('meta_pixel_track_lead', false)) {
            $events[] = $this->generateEventScript('Lead');
        }

        if (empty($events)) {
            return '';
        }

        return sprintf(
            '<!-- Meta Pixel Custom Events by %s -->
<script>
%s
</script>
<!-- End Meta Pixel Custom Events -->',
            $version,
            implode("\n", $events)
        );
    }

    /**
     * Generate individual event tracking script
     */
    private function generateEventScript(string $eventName): string
    {
        return sprintf(
            '// %s Event Tracking
function joomlaBoostTrack%s(value, currency) {
    if (typeof fbq !== "undefined") {
        var eventData = {};
        if (value) eventData.value = value;
        if (currency) eventData.currency = currency;
        fbq("track", "%s", eventData);
        console.log("Meta Pixel: %s event tracked", eventData);
    }
}',
            $eventName,
            $eventName,
            $eventName,
            $eventName
        );
    }

    /**
     * Inject custom events code into document
     */
    public function injectCustomEvents(HtmlDocument $document): void
    {
        $customEventsCode = $this->generateCustomEventCode();

        if (!empty($customEventsCode)) {
            $document->addCustomTag($customEventsCode);
        }
    }

    /**
     * Get debug information
     */
    /**
     * @return array<string, mixed>
     */
    public function getDebugInfo(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'configured' => $this->isConfigured(),
            'pixel_id' => $this->getPixelId(),
            'events' => [
                'purchase' => $this->params->get('meta_pixel_track_purchase', false),
                'add_to_cart' => $this->params->get('meta_pixel_track_add_to_cart', false),
                'contact' => $this->params->get('meta_pixel_track_contact', false),
                'lead' => $this->params->get('meta_pixel_track_lead', false),
            ],
        ];
    }
}
