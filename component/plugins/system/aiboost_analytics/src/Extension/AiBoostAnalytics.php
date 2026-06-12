<?php
/**
 * AI Boost — Analytics Plugin (namespace style)
 * GA4, GTM, Google Search Console, Meta Pixel.
 *
 * @package     AiBoost\Plugin\System\AiBoostAnalytics
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostAnalytics\Extension;

defined('_JEXEC') or die;

use AiBoost\Lib\BodyBlockBuilder;
use AiBoost\Lib\DocumentInspector;
use AiBoost\Lib\HeadBlockBuilder;
use AiBoost\Version;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;

class AiBoostAnalytics extends CMSPlugin
{
    protected $autoloadLanguage = true;

    /** Cached result of libReady() — null until first probed. */
    private ?bool $libReady = null;

    /**
     * Load all AI Boost settings from #__aiboost_settings (cached per request).
     *
     * @return array<string,mixed>
     */
    private function getAiBoostSettings(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName('settings_json'))
                ->from($db->quoteName('#__aiboost_settings'))
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'));
            $db->setQuery($query);
            $json  = $db->loadResult();
            $cache = $json ? (json_decode($json, true) ?? []) : [];
        } catch (\Throwable $e) {
            $cache = [];
        }
        return $cache;
    }

    public function onBeforeCompileHead(): void
    {
        if (!$this->libReady()) {
            return;
        }

        $app = Factory::getApplication();
        if (!$app->isClient('site')) {
            return;
        }
        $document = $app->getDocument();
        if (!$document || $document->getType() !== 'html') {
            return;
        }

        $settings = $this->getAiBoostSettings();
        if (empty($settings)) {
            return;
        }

        // Set hide-comments flag FIRST — before any early-return paths (#384).
        $debug = !empty($settings['debug_mode']);
        $hide  = !empty($settings['hide_comments']);
        HeadBlockBuilder::setHideComments($hide);
        BodyBlockBuilder::setHideComments($hide);

        // Skip in staging mode
        if (!empty($settings['staging_mode'])) {
            error_log('[AI Boost: aiboost_analytics] STAGING MODE ON — all analytics output suppressed (GA4, GTM, Meta Pixel, GSC/Facebook verification). Disable staging_mode in Debug tab to see output.');
            return;
        }

        if ($debug) {
            error_log('[AI Boost: aiboost_analytics] onBeforeCompileHead — injecting analytics scripts');
        }

        // Per task #380: all head output for this plugin flows through the
        // consolidated `Analytics` sub-section of the AI Boost head block.
        // Per-block START/END markers are gone from <head> — the outer block
        // header (HeadBlockBuilder) is the new debug anchor.
        $analyticsBodies = [];

        /* ── Google Search Console verification ─────────────────────────── */
        if (!empty($settings['enable_google_verification'])) {
            $tagsBuffer = [];
            $gscCodesJson = trim((string) ($settings['gsc_codes'] ?? ''));
            if ($gscCodesJson) {
                $codes = json_decode($gscCodesJson, true);
                if (is_array($codes)) {
                    foreach ($codes as $code) {
                        $code = trim((string) $code);
                        if ($code) {
                            $tagsBuffer[] = '<meta name="google-site-verification" content="'
                                . htmlspecialchars($code) . '">';
                        }
                    }
                }
            } else {
                $single = trim((string) ($settings['gsc_verification_code'] ?? ''));
                if ($single) {
                    $tagsBuffer[] = '<meta name="google-site-verification" content="'
                        . htmlspecialchars($single) . '">';
                }
            }
            $additionalHtml = trim((string) ($settings['gsc_additional_html'] ?? ''));
            if ($additionalHtml) {
                $tagsBuffer[] = $additionalHtml;
            }
            $body = trim(implode("\n", $tagsBuffer));
            if ($body !== '') {
                $analyticsBodies[] = $body;
            }
        }

        /* ── Facebook Domain Verification ────────────────────────────────── */
        $fbVerify = trim((string) ($settings['fb_domain_verification'] ?? ''));
        if ($fbVerify !== '') {
            $analyticsBodies[] = '<meta name="facebook-domain-verification" content="'
                . htmlspecialchars($fbVerify, ENT_QUOTES) . '">';
            if (!empty($settings['debug_mode'])) {
                error_log('[AI Boost: aiboost_analytics] facebook-domain-verification meta tag emitted');
            }
        }

        /* ── Google Tag Manager (GTM) ────────────────────────────────────── */
        if (!empty($settings['enable_gtm'])) {
            $gtmId = trim((string) ($settings['gtm_container_id'] ?? ''));
            if ($gtmId && DocumentInspector::shouldSkip($document, DocumentInspector::SIG_GTM, $settings)) {
                if (!empty($settings['debug_mode'])) {
                    error_log('[AI Boost: aiboost_analytics] Cooperative mode — GTM container already present, skipping injection (#362)');
                }
                HeadBlockBuilder::noteSkip(
                    HeadBlockBuilder::SECTION_ANALYTICS,
                    'GTM container already emitted by another extension'
                );
                $gtmId = '';
            }
            if ($gtmId) {
                $eid = htmlspecialchars($gtmId);

                // Official GTM snippet format (as provided by Google)
                $gtmTag  = ($hide ? '' : "<!-- Google Tag Manager -->\n");
                $gtmTag .= "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':\n";
                $gtmTag .= "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\n";
                $gtmTag .= "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\n";
                $gtmTag .= "'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);\n";
                $gtmTag .= "})(window,document,'script','dataLayer','" . $eid . "');</script>";
                $gtmTag .= ($hide ? '' : "\n<!-- End Google Tag Manager -->");

                $analyticsBodies[] = $gtmTag;

                // Queue the GTM <noscript> for body injection — Google spec
                // requires it immediately after <body>. BodyBlockBuilder
                // consolidates it with the Meta Pixel noscript + any custom
                // body code into a single AI Boost wrapper (#384).
                $noscript  = '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . $eid . '"' . "\n";
                $noscript .= 'height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';
                BodyBlockBuilder::pushBody('Google Tag Manager (noscript)', $noscript);
            }
        }

        /* ── GA4 ─────────────────────────────────────────────────────────── */
        if (!empty($settings['enable_ga4'])) {
            $ga4Id = trim((string) ($settings['ga4_measurement_id'] ?? ''));
            if ($ga4Id && DocumentInspector::shouldSkip($document, DocumentInspector::SIG_GA4, $settings)) {
                if (!empty($settings['debug_mode'])) {
                    error_log('[AI Boost: aiboost_analytics] Cooperative mode — GA4 gtag already present, skipping injection (#362)');
                }
                HeadBlockBuilder::noteSkip(
                    HeadBlockBuilder::SECTION_ANALYTICS,
                    'GA4 gtag already emitted by another extension'
                );
                $ga4Id = '';
            }
            if ($ga4Id) {
                $safeId  = htmlspecialchars($ga4Id);
                // UI options: none | yootheme | gtm | default_denied (legacy)
                $consent = (string) ($settings['ga4_consent_mode'] ?? 'none');

                // Build the entire GA4 block as a single string; an empty
                // body (e.g. consent === 'gtm', which intentionally emits
                // nothing because GTM handles GA4 itself) means the wrapper
                // helper returns '' — no orphan START/END pair.
                $body = '';

                if ($consent !== 'gtm') {
                    // Official Google tag (gtag.js) loader — always first
                    $body .= ($hide ? '' : '<!-- Google tag (gtag.js) -->' . "\n")
                          . '<script async src="https://www.googletagmanager.com/gtag/js?id=' . $safeId . '"></script>';

                    if ($consent === 'none' || $consent === '') {
                        // Direct inject — no consent management
                        $body .= ($hide ? "\n" : "\n<!-- Google tag (gtag.js) -->\n");
                        $body .= "<script>\n";
                        $body .= "  window.dataLayer = window.dataLayer || [];\n";
                        $body .= "  function gtag(){dataLayer.push(arguments);}\n";
                        $body .= "  gtag('js', new Date());\n";
                        $body .= "\n";
                        $body .= "  gtag('config', '" . $safeId . "');\n";
                        $body .= "</script>";

                    } elseif ($consent === 'yootheme') {
                        // YooTheme Pro 5 Consent Manager integration
                        $body .= ($hide ? "\n" : "\n<!-- Google tag (gtag.js) - Consent Mode (YooTheme) -->\n");
                        $body .= "<script>\n";
                        $body .= "  window.dataLayer = window.dataLayer || [];\n";
                        $body .= "  function gtag(){dataLayer.push(arguments);}\n";
                        $body .= "  gtag('js', new Date());\n";
                        $body .= "\n";
                        $body .= "  gtag('consent', 'default', {\n";
                        $body .= "    'ad_storage': 'denied',\n";
                        $body .= "    'analytics_storage': 'denied',\n";
                        $body .= "    'ad_user_data': 'denied',\n";
                        $body .= "    'ad_personalization': 'denied',\n";
                        $body .= "    'wait_for_update': 2000\n";
                        $body .= "  });\n";
                        $body .= "  gtag('config', '" . $safeId . "', {'send_page_view': false});\n";
                        $body .= "\n";
                        $body .= "  document.addEventListener('analyticsScriptsConsented', function() {\n";
                        $body .= "    gtag('consent', 'update', {\n";
                        $body .= "      'ad_storage': 'granted',\n";
                        $body .= "      'analytics_storage': 'granted',\n";
                        $body .= "      'ad_user_data': 'granted',\n";
                        $body .= "      'ad_personalization': 'granted'\n";
                        $body .= "    });\n";
                        $body .= "    gtag('event', 'page_view');\n";
                        $body .= "  });\n";
                        $body .= "\n";
                        $body .= "  document.addEventListener('UIkit:consent:analytics', function() {\n";
                        $body .= "    gtag('consent', 'update', {'analytics_storage': 'granted'});\n";
                        $body .= "    gtag('event', 'page_view');\n";
                        $body .= "  });\n";
                        $body .= "</script>";

                    } elseif ($consent === 'default_denied') {
                        // Legacy value — consent denied by default (custom CMP)
                        $body .= ($hide ? "\n" : "\n<!-- Google tag (gtag.js) - Consent Mode -->\n");
                        $body .= "<script>\n";
                        $body .= "  window.dataLayer = window.dataLayer || [];\n";
                        $body .= "  function gtag(){dataLayer.push(arguments);}\n";
                        $body .= "  gtag('js', new Date());\n";
                        $body .= "\n";
                        $body .= "  gtag('consent', 'default', {\n";
                        $body .= "    'ad_storage': 'denied',\n";
                        $body .= "    'analytics_storage': 'denied'\n";
                        $body .= "  });\n";
                        $body .= "  gtag('config', '" . $safeId . "');\n";
                        $body .= "</script>";
                    }
                }

                if (trim($body) !== '') {
                    $analyticsBodies[] = $body;
                }
            }
        }

        /* ── Meta Pixel ──────────────────────────────────────────────────── */
        if (!empty($settings['enable_meta_pixel'])) {
            $pixelIds = $this->getPixelIds($settings);
            if (!empty($pixelIds) && DocumentInspector::shouldSkip($document, DocumentInspector::SIG_META_PIXEL, $settings)) {
                if (!empty($settings['debug_mode'])) {
                    error_log('[AI Boost: aiboost_analytics] Cooperative mode — Meta Pixel already present, skipping injection (#362)');
                }
                HeadBlockBuilder::noteSkip(
                    HeadBlockBuilder::SECTION_ANALYTICS,
                    'Meta Pixel already emitted by another extension'
                );
                $pixelIds = [];
            }
            if (!empty($pixelIds)) {
                $consentMode = (string) ($settings['pixel_consent_mode'] ?? 'auto');

                // Official Meta Pixel base code format
                $pixelTag  = ($hide ? '' : "<!-- Meta Pixel Code -->\n");
                $pixelTag .= "<script>\n";
                $pixelTag .= "!function(f,b,e,v,n,t,s)\n";
                $pixelTag .= "{if(f.fbq)return;n=f.fbq=function(){n.callMethod?\n";
                $pixelTag .= "n.callMethod.apply(n,arguments):n.queue.push(arguments)};\n";
                $pixelTag .= "if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';\n";
                $pixelTag .= "n.queue=[];t=b.createElement(e);t.async=!0;\n";
                $pixelTag .= "t.src=v;s=b.getElementsByTagName(e)[0];\n";
                $pixelTag .= "s.parentNode.insertBefore(t,s)}(window, document,'script',\n";
                $pixelTag .= "'https://connect.facebook.net/en_US/fbevents.js');\n";

                if ($consentMode === 'consent_required') {
                    $pixelTag .= "fbq('consent', 'revoke');\n";
                }

                foreach ($pixelIds as $pid) {
                    $pixelTag .= "fbq('init', '" . htmlspecialchars(trim((string) $pid)) . "');\n";
                }

                $stdEventsJson = trim((string) ($settings['meta_pixel_standard_events'] ?? '{}'));
                $stdEvents     = json_decode($stdEventsJson, true) ?: [];
                if (!empty($stdEvents)) {
                    $eventNames = array_is_list($stdEvents) ? array_values($stdEvents) : array_keys($stdEvents);
                    $tracked = 0;
                    foreach ($eventNames as $ev) {
                        $ev = trim((string) $ev);
                        if ($ev !== '' && !is_numeric($ev) && $ev !== 'true' && $ev !== 'false') {
                            $pixelTag .= "fbq('track', '" . htmlspecialchars($ev) . "');\n";
                            $tracked++;
                        }
                    }
                    if ($tracked === 0) {
                        $pixelTag .= "fbq('track', 'PageView');\n";
                    }
                } else {
                    $pixelTag .= "fbq('track', 'PageView');\n";
                }

                $customJson = trim((string) ($settings['meta_custom_events'] ?? ''));
                if ($customJson) {
                    foreach ((json_decode($customJson, true) ?: []) as $ce) {
                        $ceName = trim((string) ($ce['name'] ?? ''));
                        $ceUrl  = trim((string) ($ce['url']  ?? ''));
                        if (!$ceName) {
                            continue;
                        }
                        $safeName = htmlspecialchars($ceName);
                        if ($ceUrl) {
                            $safeUrl   = htmlspecialchars($ceUrl);
                            $pixelTag .= "if (window.location.href.indexOf('" . $safeUrl . "') !== -1) {\n";
                            $pixelTag .= "  fbq('trackCustom', '" . $safeName . "');\n";
                            $pixelTag .= "}\n";
                        } else {
                            $pixelTag .= "fbq('trackCustom', '" . $safeName . "');\n";
                        }
                    }
                }

                $pixelTag .= "</script>";
                $pixelTag .= ($hide ? '' : "\n<!-- End Meta Pixel Code -->");

                $analyticsBodies[] = $pixelTag;

                // The Meta Pixel <noscript><img></noscript> fallback belongs in
                // <body>, not <head> (Facebook spec). Queue it via
                // BodyBlockBuilder so it lands inside the consolidated AI
                // Boost body wrapper alongside the GTM noscript (#384).
                $noscriptBody  = '<noscript><img height="1" width="1" style="display:none"' . "\n";
                $noscriptBody .= 'src="https://www.facebook.com/tr?id=' . htmlspecialchars($pixelIds[0]) . '&ev=PageView&noscript=1"' . "\n";
                $noscriptBody .= '/></noscript>';
                BodyBlockBuilder::pushBody('Meta Pixel (noscript)', $noscriptBody);
            }
        }

        /* ── Push the entire analytics chunk into the consolidated block ── */
        if (!empty($analyticsBodies)) {
            HeadBlockBuilder::pushSection(
                HeadBlockBuilder::SECTION_ANALYTICS,
                implode("\n", $analyticsBodies)
            );
        }
    }

    /**
     * Finalize the consolidated AI Boost head + body blocks. Idempotent —
     * the first AI Boost plugin to run wins; subsequent calls no-op. GTM
     * noscript + Meta Pixel noscript were queued in onBeforeCompileHead via
     * BodyBlockBuilder::pushBody() and land inside the single AI Boost body
     * wrapper (#384).
     */
    public function onAfterRender(): void
    {
        if (!$this->libReady()) {
            return;
        }

        $app = Factory::getApplication();
        HeadBlockBuilder::finalize($app, Version::VERSION);
        BodyBlockBuilder::finalize($app);
    }

    /**
     * Whether the shared AiBoost\Lib library is fully loadable.
     *
     * The plugin entry file only checks that lib/autoload.php exists — not
     * enough: a partial base-package uninstall can leave autoload.php on disk
     * while individual lib/src class files are gone, and the first lib
     * reference then fatals on every page. Probing two core lib classes
     * detects that state so every lib-touching event handler can no-op
     * instead. This is a tripwire, not an exhaustive integrity check. The
     * try/catch matters: under JDEBUG Joomla's debug class loader THROWS on
     * a missing class file instead of returning false.
     */
    private function libReady(): bool
    {
        if ($this->libReady !== null) {
            return $this->libReady;
        }
        try {
            $this->libReady = class_exists('AiBoost\\Lib\\PluginRegistry')
                && class_exists('AiBoost\\Lib\\Logger');
        } catch (\Throwable $e) {
            $this->libReady = false;
        }
        return $this->libReady;
    }

    /** @return string[] */
    private function getPixelIds(array $settings): array
    {
        $json = trim((string) ($settings['meta_pixel_ids'] ?? ''));
        if ($json) {
            $ids = json_decode($json, true);
            if (is_array($ids) && !empty($ids)) {
                return array_values(array_filter(array_map('trim', $ids)));
            }
        }
        $single = trim((string) ($settings['meta_pixel_id'] ?? ''));
        return $single ? [$single] : [];
    }
}
