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
     * Get all configured Pixel IDs (primary + optional secondary)
     *
     * @return string[]
     */
    public function getPixelIds(): array
    {
        $ids = [];

        $primary = trim((string) $this->params->get('meta_pixel_id', ''));
        if ($primary !== '') {
            $ids[] = $primary;
        }

        $secondary = trim((string) $this->params->get('meta_pixel_id_2', ''));
        if ($secondary !== '') {
            $ids[] = $secondary;
        }

        return $ids;
    }

    /**
     * Get primary Meta Pixel ID (legacy helper)
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
        return $this->isEnabled() && count($this->getPixelIds()) > 0;
    }

    /**
     * Inject Meta Pixel base code into document head
     */
    public function injectPixelCode(HtmlDocument $document): void
    {
        if (!$this->isConfigured()) {
            return;
        }

        $pixelIds = $this->getPixelIds();
        $version  = 'JoomlaBoost v' . $this->getPluginVersion();

        $pixelCode = $this->generatePixelCode($pixelIds, $version);
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
    /**
     * Generate Meta Pixel tracking code for one or more Pixel IDs.
     *
     * The fbq loader runs once. Each ID gets its own fbq('init') call.
     * PageView is tracked once (fires for all initialised pixels).
     *
     * @param string[] $pixelIds
     */
    private function generatePixelCode(array $pixelIds, string $version): string
    {
        $primaryId = $pixelIds[0] ?? '';
        $consentMode = $this->params->get('pixel_consent_mode', 'none');

        // Build init calls for every ID
        $initCalls = implode("\n", array_map(
            static fn(string $id) => "fbq('init', '{$id}');",
            $pixelIds
        ));

        $innerScript = sprintf(
            '!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version=\'2.0\';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,\'script\',
\'https://connect.facebook.net/en_US/fbevents.js\');
%s
fbq(\'track\', \'PageView\');',
            $initCalls
        );

        if ($consentMode === 'yootheme') {
            return sprintf(
                '<!-- Meta Pixel Code by %s (YooTheme Consent) -->
<!-- Step 1: Register meta-pixel in YooTheme consent categories -->
<script>
(function() {
  window.yootheme = window.yootheme || {};
  window.yootheme.consent = window.yootheme.consent || {};
  window.yootheme.consent.categories = window.yootheme.consent.categories || {};
  window.yootheme.consent.categories.marketing = window.yootheme.consent.categories.marketing || [];
  var m = window.yootheme.consent.categories.marketing;
  if (m.indexOf(\'meta-pixel\') === -1) m.push(\'meta-pixel\');
})();
</script>
<!-- Step 2: Blocked until user accepts Marketing cookies -->
<script type="text/plain" data-category="marketing.meta-pixel">
%s
</script>
<noscript>
<img height="1" width="1" style="display:none"
     src="https://www.facebook.com/tr?id=%s&ev=PageView&noscript=1"/>
</noscript>
<!-- End Meta Pixel Code -->',
                $version,
                $innerScript,
                $primaryId
            );
        }

        // Default: direct inject
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
            $primaryId
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
