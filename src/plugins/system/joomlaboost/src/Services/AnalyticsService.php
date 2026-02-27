<?php

/**
 * Analytics Service for JoomlaBoost
 *
 * @package     JoomlaBoost
 * @subpackage  Plugin.System.Services
 * @since       Joomla 4.0, PHP 8.1+
 * @author      JoomlaBoost Team
 * @copyright   (C) 2025 JoomlaBoost. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Services;

use Joomla\CMS\Document\HtmlDocument;

/**
 * Analytics Service
 */
class AnalyticsService extends AbstractService
{
    protected function getServiceKey(): string
    {
        return 'enable_analytics';
    }

    /**
     * Inject all enabled analytics scripts
     */
    public function injectAnalytics(HtmlDocument $document): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        if ($this->params->get('enable_ga4', 0)) {
            $this->injectGA4($document);
        }

        if ($this->params->get('enable_gtm', 0)) {
            $this->injectGTM($document);
        }
    }

    /**
     * Inject Google Analytics 4 (GA4)
     *
     * Supports 3 GDPR consent modes:
     * - 'none'      : Direct inject, no consent control (legacy)
     * - 'yootheme'  : Uses type="text/plain" + data-category for YooTheme Pro 5 consent manager
     * - 'gtm'       : Skip GA4 injection entirely, let GTM or YooTheme handle it
     *
     * IMPORTANT: If YooTheme Pro 5 has GA4 configured in its own settings (Customizer > Integrations),
     * use mode 'gtm' or disable GA4 here to avoid duplicate tracking!
     */
    private function injectGA4(HtmlDocument $document): void
    {
        $measurementId = $this->params->get('ga4_measurement_id', '');
        if (empty($measurementId)) {
            $this->logDebug('GA4: No measurement ID provided');
            return;
        }

        $consentMode = $this->params->get('ga4_consent_mode', 'none');

        // GTM mode: skip direct GA4, let GTM/YooTheme handle consent
        if ($consentMode === 'gtm') {
            $this->logDebug('GA4: Skipping direct inject - managed by GTM/YooTheme');
            return;
        }

        // YooTheme Pro 5 consent mode
        // CRITICAL: Two things are required for YooTheme consent to work:
        // 1. type="text/plain" + data-category on the scripts (blocks execution until consent)
        // 2. Registration of categories in yootheme.consent.categories (shows them in the banner)
        // The registration script MUST run before consent.js module (which is deferred as type=module).
        if ($consentMode === 'yootheme') {
            $ga4Script = "\n" .
                '<!-- Google Analytics 4 (JoomlaBoost - YooTheme Consent) -->' . "\n" .
                '<!-- Step 1: Register categories so YooTheme consent banner shows them -->' . "\n" .
                '<script>' . "\n" .
                '(function() {' . "\n" .
                '  window.yootheme = window.yootheme || {};' . "\n" .
                '  window.yootheme.consent = window.yootheme.consent || {};' . "\n" .
                '  window.yootheme.consent.categories = window.yootheme.consent.categories || {};' . "\n" .
                '  var cats = window.yootheme.consent.categories;' . "\n" .
                '  cats.statistics = cats.statistics || [];' . "\n" .
                '  if (cats.statistics.indexOf(\'google_analytics\') === -1) cats.statistics.push(\'google_analytics\');' . "\n" .
                '  cats.marketing = cats.marketing || [];' . "\n" .
                '  if (cats.marketing.indexOf(\'google_ads\') === -1) cats.marketing.push(\'google_ads\');' . "\n" .
                '})();' . "\n" .
                '</script>' . "\n" .
                '<!-- Step 2: Blocked scripts - activated by consent.js after user accepts -->' . "\n" .
                '<script type="text/plain" data-category="statistics.google_analytics marketing.google_ads" async src="https://www.googletagmanager.com/gtag/js?id=' . $measurementId . '"></script>' . "\n" .
                '<script type="text/plain" data-category="statistics.google_analytics marketing.google_ads">' . "\n" .
                '  window.dataLayer = window.dataLayer || [];' . "\n" .
                '  function gtag(){dataLayer.push(arguments);}' . "\n" .
                '  gtag(\'js\', new Date());' . "\n" .
                '  gtag(\'config\', \'' . $measurementId . '\');' . "\n" .
                '</script>';

            $document->addCustomTag($ga4Script);
            $this->logDebug('GA4: Registered in yootheme.consent.categories + injected with type=text/plain for: ' . $measurementId);
            return;
        }

        // Default: direct inject (no consent, legacy behavior)
        $ga4Script = "\n" .
            '<!-- Google Analytics 4 -->' . "\n" .
            '<script async src="https://www.googletagmanager.com/gtag/js?id=' . $measurementId . '"></script>' . "\n" .
            '<script>' . "\n" .
            '  window.dataLayer = window.dataLayer || [];' . "\n" .
            '  function gtag(){dataLayer.push(arguments);}' . "\n" .
            '  gtag(\'js\', new Date());' . "\n" .
            '  gtag(\'config\', \'' . $measurementId . '\');' . "\n" .
            '</script>';

        $document->addCustomTag($ga4Script);
        $this->logDebug('GA4: Injected directly (no GDPR consent) for: ' . $measurementId);
    }


    /**
     * Inject Google Tag Manager (GTM)
     */
    private function injectGTM(HtmlDocument $document): void
    {
        $containerId = $this->params->get('gtm_container_id', '');
        if (empty($containerId)) {
            $this->logDebug('GTM: No container ID provided');
            return;
        }

        $gtmHead = "\n" .
            '<!-- Google Tag Manager -->' . "\n" .
            '<script>' . "\n" .
            '(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({\'gtm.start\':' . "\n" .
            'new Date().getTime(),event:\'gtm.js\'});var f=d.getElementsByTagName(s)[0],' . "\n" .
            'j=d.createElement(s),dl=l!=\'dataLayer\'?\'&l=\'+l:\'\';j.async=true;j.src=' . "\n" .
            '\'https://www.googletagmanager.com/gtm.js?id=\'+i+dl;f.parentNode.insertBefore(j,f);' . "\n" .
            '})(window,document,\'script\',\'dataLayer\',\'' . $containerId . '\');' . "\n" .
            '</script>' . "\n" .
            '<!-- End Google Tag Manager -->';

        $document->addCustomTag($gtmHead);
        $this->logDebug('Added Google Tag Manager tracking for: ' . $containerId);
    }
}
