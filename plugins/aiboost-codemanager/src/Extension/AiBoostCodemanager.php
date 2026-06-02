<?php
/**
 * AI Boost — Code Manager Plugin (standalone, Joomla 4/5/6)
 *
 * Handles: GA4, Google Tag Manager, Meta Pixel (multiple IDs),
 *          site verification codes (GSC multiple, Bing, Yandex, Pinterest, Norton,
 *          Facebook Domain Verification), custom head/body/footer code injection.
 * Standalone: reads all settings from Joomla-native plugin params ($this->params).
 *
 * @package     AiBoost\Plugin\System\AiBoostCodemanager
 * @version     1.2.0
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostCodemanager\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;

class AiBoostCodemanager extends CMSPlugin
{
    protected $autoloadLanguage = true;

    /** @var string|null GTM noscript for <body> injection */
    private ?string $gtmNoscript = null;
    /** @var string|null Body-start code */
    private ?string $bodyStartCode = null;
    /** @var string|null Footer code (before </body>) */
    private ?string $footerCode = null;

    public function onBeforeCompileHead(): void
    {
        $app = Factory::getApplication();
        if (!$app->isClient('site')) {
            return;
        }
        $document = $app->getDocument();
        if (!$document || $document->getType() !== 'html') {
            return;
        }
        if ((int) $this->params->get('staging_mode', 0)) {
            return;
        }

        // Conditional page loading
        if (!$this->shouldLoadOnCurrentPage($app)) {
            return;
        }

        // Verification codes (including Facebook Domain Verification)
        $this->injectVerificationCodes($document);

        // GTM
        if ((int) $this->params->get('gtm_enabled', 0)) {
            $this->injectGtm($document);
        }

        // GA4
        if ((int) $this->params->get('ga4_enabled', 0)) {
            $this->injectGa4($document);
        }

        // Meta Pixel (supports multiple IDs)
        if ((int) $this->params->get('meta_pixel_enabled', 0)) {
            $this->injectMetaPixel($document);
        }

        // Custom head code — optionally add async/defer to inline <script src=...> tags
        if ((int) $this->params->get('custom_head_enabled', 0)) {
            $headCode = trim((string) $this->params->get('custom_head_code', ''));
            if ($headCode) {
                $customScriptLoad = trim((string) $this->params->get('custom_script_load', 'none'));
                if ($customScriptLoad !== 'none') {
                    $headCode = preg_replace_callback(
                        '/<script([^>]+src=[^>]+)>/i',
                        static function (array $m) use ($customScriptLoad): string {
                            $attrs = $m[1];
                            // Do not add duplicate async/defer if already present
                            if (!preg_match('/\b(async|defer)\b/i', $attrs)) {
                                $attrs .= ' ' . $customScriptLoad;
                            }
                            return '<script' . $attrs . '>';
                        },
                        $headCode
                    ) ?? $headCode;
                }
                $document->addCustomTag($headCode);
            }
        }

        // Store body/footer code for onAfterRender
        if ((int) $this->params->get('custom_body_enabled', 0)) {
            $this->bodyStartCode = trim((string) $this->params->get('custom_body_code', '')) ?: null;
        }
        if ((int) $this->params->get('custom_footer_enabled', 0)) {
            $this->footerCode = trim((string) $this->params->get('custom_footer_code', '')) ?: null;
        }
    }

    public function onAfterRender(): void
    {
        $app = Factory::getApplication();
        if (!$app->isClient('site')) {
            return;
        }

        if (!$this->gtmNoscript && !$this->bodyStartCode && !$this->footerCode) {
            return;
        }

        $body = $app->getBody();
        if (empty($body)) {
            return;
        }

        // Body-start injections (after <body> tag)
        $afterBody = '';
        if ($this->gtmNoscript) {
            $afterBody .= "\n" . $this->gtmNoscript;
        }
        if ($this->bodyStartCode) {
            $afterBody .= "\n" . $this->bodyStartCode;
        }
        if ($afterBody) {
            $body = preg_replace('/<body([^>]*)>/i', '<body$1>' . $afterBody, $body, 1);
        }

        // Footer injection (before </body>)
        if ($this->footerCode) {
            $body = str_ireplace('</body>', "\n" . $this->footerCode . "\n</body>", $body);
        }

        $app->setBody($body);
    }

    // ── Conditional page loading ────────────────────────────────────────────

    private function shouldLoadOnCurrentPage($app): bool
    {
        $input  = $app->getInput();
        $option = $input->get('option', '');
        $view   = $input->get('view', '');
        $id     = (int) $input->get('id', 0);

        $mode = trim((string) $this->params->get('load_on_pages', 'all'));

        if ($mode === 'home') {
            // Homepage = featured view or blank option or root path
            if ($option === '' || ($option === 'com_content' && $view === 'featured')) {
                return !$this->hasExcludeCustomField($option, $view, $id);
            }
            $path = ltrim(\Joomla\CMS\Uri\Uri::getInstance()->getPath(), '/');
            if ($path !== '' && $path !== 'index.php') {
                return false;
            }
            return !$this->hasExcludeCustomField($option, $view, $id);
        }

        if ($mode === 'notadmin') {
            return !$this->hasExcludeCustomField($option, $view, $id);
        }

        // 'all' — load on every site page, respect per-page exclusion field
        return !$this->hasExcludeCustomField($option, $view, $id);
    }

    /**
     * Returns true if a Joomla custom field named "aiboost_disable_tracking"
     * is set to "1" on the current article or category, allowing per-page opt-out.
     */
    private function hasExcludeCustomField(string $option, string $view, int $id): bool
    {
        if (!$id || $option !== 'com_content') {
            return false;
        }
        $context = ($view === 'article') ? 'com_content.article' : 'com_content.category';
        try {
            $db = Factory::getDbo();
            $q  = $db->getQuery(true)
                ->select($db->quoteName('fv.value'))
                ->from($db->quoteName('#__fields_values', 'fv'))
                ->join('INNER', $db->quoteName('#__fields', 'f') . ' ON f.id = fv.field_id')
                ->where($db->quoteName('fv.item_id') . ' = ' . $id)
                ->where($db->quoteName('f.name') . ' = ' . $db->quote('aiboost_disable_tracking'))
                ->where($db->quoteName('f.context') . ' = ' . $db->quote($context))
                ->where($db->quoteName('f.state') . ' = 1');
            $db->setQuery($q, 0, 1);
            return trim((string) ($db->loadResult() ?? '')) === '1';
        } catch (\Throwable $e) {
            error_log('[AI Boost CodeManager] Custom field exclusion check error: ' . $e->getMessage());
            return false;
        }
    }

    // ── Verification codes ──────────────────────────────────────────────────

    private function injectVerificationCodes($document): void
    {
        // Google Search Console — supports multiple codes, one per line
        // New param: gsc_verification_codes (textarea); falls back to legacy gsc_verification_code
        $gscRaw = trim((string) $this->params->get('gsc_verification_codes', ''));
        if (!$gscRaw) {
            // Legacy single-value param (v1.0.0 backwards compat)
            $gscRaw = trim((string) $this->params->get('gsc_verification_code', ''));
        }
        foreach ($this->splitLines($gscRaw) as $code) {
            $document->addCustomTag(
                '<meta name="google-site-verification" content="' . htmlspecialchars($code) . '">'
            );
        }

        // Bing Webmaster
        $bing = trim((string) $this->params->get('bing_verification_code', ''));
        if ($bing) {
            $document->addCustomTag(
                '<meta name="msvalidate.01" content="' . htmlspecialchars($bing) . '">'
            );
        }

        // Yandex
        $yandex = trim((string) $this->params->get('yandex_verification_code', ''));
        if ($yandex) {
            $document->addCustomTag(
                '<meta name="yandex-verification" content="' . htmlspecialchars($yandex) . '">'
            );
        }

        // Pinterest
        $pinterest = trim((string) $this->params->get('pinterest_verification_code', ''));
        if ($pinterest) {
            $document->addCustomTag(
                '<meta name="p:domain_verify" content="' . htmlspecialchars($pinterest) . '">'
            );
        }

        // Norton Safe Web
        $norton = trim((string) $this->params->get('norton_verification_code', ''));
        if ($norton) {
            $document->addCustomTag(
                '<meta name="norton-safeweb-site-verification" content="' . htmlspecialchars($norton) . '">'
            );
        }

        // Facebook Domain Verification (moved from OpenGraph plugin)
        $fbVerify = trim((string) $this->params->get('fb_domain_verification', ''));
        if ($fbVerify) {
            $document->addCustomTag(
                '<meta name="facebook-domain-verification" content="' . htmlspecialchars($fbVerify) . '">'
            );
        }

        // GSC Additional HTML — raw HTML paste (e.g. full <meta> verification file tag)
        $gscHtml = trim((string) $this->params->get('gsc_additional_html', ''));
        if ($gscHtml) {
            $document->addCustomTag($gscHtml);
        }
    }

    // ── Google Tag Manager ──────────────────────────────────────────────────

    private function injectGtm($document): void
    {
        $gtmId = trim((string) $this->params->get('gtm_container_id', ''));
        if (!$gtmId) {
            return;
        }
        $eid  = htmlspecialchars($gtmId);

        $tag  = "<!-- Google Tag Manager -->\n";
        $tag .= "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':\n";
        $tag .= "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\n";
        $tag .= "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\n";
        $tag .= "'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);\n";
        $tag .= "})(window,document,'script','dataLayer','" . $eid . "');</script>\n";
        $tag .= "<!-- End Google Tag Manager -->";
        $document->addCustomTag($tag);

        $this->gtmNoscript  = "<!-- Google Tag Manager (noscript) -->\n";
        $this->gtmNoscript .= '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . $eid . '"' . "\n";
        $this->gtmNoscript .= 'height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n";
        $this->gtmNoscript .= "<!-- End Google Tag Manager (noscript) -->";
    }

    // ── Google Analytics 4 ──────────────────────────────────────────────────

    private function injectGa4($document): void
    {
        $ga4Id = trim((string) $this->params->get('ga4_measurement_id', ''));
        if (!$ga4Id) {
            return;
        }
        $safeId  = htmlspecialchars($ga4Id);
        $consent = trim((string) $this->params->get('ga4_consent_mode', 'none'));

        if ($consent === 'gtm') {
            // GTM mode: skip — GTM container handles GA4 directly
            return;
        }

        $loadMode  = trim((string) $this->params->get('ga4_script_load', 'async'));
        $loadAttr  = $loadMode === 'defer' ? 'defer' : ($loadMode === 'normal' ? '' : 'async');
        $scriptTag = '<script' . ($loadAttr ? ' ' . $loadAttr : '') . ' src="https://www.googletagmanager.com/gtag/js?id=' . $safeId . '"></script>';
        $document->addCustomTag("<!-- Google tag (gtag.js) -->\n" . $scriptTag);

        if ($consent === 'none' || $consent === '') {
            $inline  = "<script>\n";
            $inline .= "  window.dataLayer = window.dataLayer || [];\n";
            $inline .= "  function gtag(){dataLayer.push(arguments);}\n";
            $inline .= "  gtag('js', new Date());\n";
            $inline .= "  gtag('config', '" . $safeId . "');\n";
            $inline .= "</script>";
            $document->addCustomTag($inline);

        } elseif ($consent === 'yootheme') {
            // YooTheme Pro 5 Consent Manager — fires 'analyticsScriptsConsented' on accept
            $inline  = "<!-- Google tag (gtag.js) - Consent Mode (YooTheme) -->\n";
            $inline .= "<script>\n";
            $inline .= "  window.dataLayer = window.dataLayer || [];\n";
            $inline .= "  function gtag(){dataLayer.push(arguments);}\n";
            $inline .= "  gtag('js', new Date());\n";
            $inline .= "\n";
            $inline .= "  gtag('consent', 'default', {\n";
            $inline .= "    'ad_storage': 'denied',\n";
            $inline .= "    'analytics_storage': 'denied',\n";
            $inline .= "    'ad_user_data': 'denied',\n";
            $inline .= "    'ad_personalization': 'denied',\n";
            $inline .= "    'wait_for_update': 2000\n";
            $inline .= "  });\n";
            $inline .= "  gtag('config', '" . $safeId . "', {'send_page_view': false});\n";
            $inline .= "\n";
            $inline .= "  document.addEventListener('analyticsScriptsConsented', function() {\n";
            $inline .= "    gtag('consent', 'update', {\n";
            $inline .= "      'ad_storage': 'granted',\n";
            $inline .= "      'analytics_storage': 'granted',\n";
            $inline .= "      'ad_user_data': 'granted',\n";
            $inline .= "      'ad_personalization': 'granted'\n";
            $inline .= "    });\n";
            $inline .= "    gtag('event', 'page_view');\n";
            $inline .= "  });\n";
            $inline .= "\n";
            $inline .= "  document.addEventListener('UIkit:consent:analytics', function() {\n";
            $inline .= "    gtag('consent', 'update', {'analytics_storage': 'granted'});\n";
            $inline .= "    gtag('event', 'page_view');\n";
            $inline .= "  });\n";
            $inline .= "</script>";
            $document->addCustomTag($inline);

        } elseif ($consent === 'default_denied') {
            // Consent denied by default — custom CMP must call gtag('consent', 'update', ...)
            $inline  = "<!-- Google tag (gtag.js) - Consent Mode -->\n";
            $inline .= "<script>\n";
            $inline .= "  window.dataLayer = window.dataLayer || [];\n";
            $inline .= "  function gtag(){dataLayer.push(arguments);}\n";
            $inline .= "  gtag('js', new Date());\n";
            $inline .= "\n";
            $inline .= "  gtag('consent', 'default', {\n";
            $inline .= "    'ad_storage': 'denied',\n";
            $inline .= "    'analytics_storage': 'denied'\n";
            $inline .= "  });\n";
            $inline .= "  gtag('config', '" . $safeId . "');\n";
            $inline .= "</script>";
            $document->addCustomTag($inline);
        }
    }

    // ── Meta Pixel — supports multiple Pixel IDs, one per line ─────────────

    private function injectMetaPixel($document): void
    {
        // meta_pixel_ids: textarea, multiple IDs one per line
        // Falls back to legacy meta_pixel_id (single text field, v1.0.0)
        $rawIds = trim((string) $this->params->get('meta_pixel_ids', ''));
        if (!$rawIds) {
            $rawIds = trim((string) $this->params->get('meta_pixel_id', ''));
        }

        $pixelIds = $this->splitLines($rawIds);
        if (empty($pixelIds)) {
            return;
        }

        $consentMode = trim((string) $this->params->get('pixel_consent_mode', 'auto'));

        // Base pixel snippet
        $script  = "<!-- Meta Pixel Code -->\n";
        $script .= "<script>\n";
        $script .= "!function(f,b,e,v,n,t,s)\n";
        $script .= "{if(f.fbq)return;n=f.fbq=function(){n.callMethod?\n";
        $script .= "n.callMethod.apply(n,arguments):n.queue.push(arguments)};\n";
        $script .= "if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';\n";
        $script .= "n.queue=[];t=b.createElement(e);t.async=!0;\n";
        $script .= "t.src=v;s=b.getElementsByTagName(e)[0];\n";
        $script .= "s.parentNode.insertBefore(t,s)}(window, document,'script',\n";
        $script .= "'https://connect.facebook.net/en_US/fbevents.js');\n";

        if ($consentMode === 'consent_required') {
            $script .= "fbq('consent', 'revoke');\n";
        }

        foreach ($pixelIds as $pixelId) {
            $script .= "fbq('init', '" . htmlspecialchars($pixelId) . "');\n";
        }

        // Standard events (Joomla checkboxes field stores selected values as array)
        $stdEventsRaw = $this->params->get('meta_pixel_standard_events', []);
        if (!is_array($stdEventsRaw)) {
            // Might be a JSON string from old storage
            $stdEventsRaw = json_decode((string) $stdEventsRaw, true) ?: [];
        }
        // Support both indexed ["PageView"] and associative {"PageView":true} formats
        if (!empty($stdEventsRaw)) {
            $eventNames = array_is_list($stdEventsRaw)
                ? array_values($stdEventsRaw)
                : array_keys($stdEventsRaw);
            $tracked = 0;
            foreach ($eventNames as $ev) {
                $ev = trim((string) $ev);
                if ($ev !== '' && !is_numeric($ev) && $ev !== 'true' && $ev !== 'false') {
                    $script .= "fbq('track', '" . htmlspecialchars($ev) . "');\n";
                    $tracked++;
                }
            }
            if ($tracked === 0) {
                $script .= "fbq('track', 'PageView');\n";
            }
        } else {
            $script .= "fbq('track', 'PageView');\n";
        }

        // Custom events (JSON array: [{"name":"Purchase","url":"/thank-you"}])
        $customJson = trim((string) $this->params->get('meta_custom_events', ''));
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
                    $script .= "if (window.location.href.indexOf('" . $safeUrl . "') !== -1) {\n";
                    $script .= "  fbq('trackCustom', '" . $safeName . "');\n";
                    $script .= "}\n";
                } else {
                    $script .= "fbq('trackCustom', '" . $safeName . "');\n";
                }
            }
        }

        $script .= "</script>\n";

        // noscript fallback — first pixel ID only (Meta recommendation)
        $firstSafe = htmlspecialchars($pixelIds[0]);
        $script .= '<noscript><img height="1" width="1" style="display:none"' . "\n";
        $script .= 'src="https://www.facebook.com/tr?id=' . $firstSafe . '&ev=PageView&noscript=1"' . "\n";
        $script .= '/></noscript>' . "\n";
        $script .= "<!-- End Meta Pixel Code -->";

        $document->addCustomTag($script);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Split a multi-line string into a list of non-empty trimmed values.
     * Used for GSC codes and Pixel IDs (one per line).
     *
     * @param  string $raw  Raw textarea value.
     * @return string[]     Array of non-empty trimmed strings.
     */
    private function splitLines(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', $raw)))));
    }
}
