<?php
/**
 * AI Boost — AEO / AI Signals Plugin (Free)
 *
 * Free-tier orchestrator. Serves /llms.txt and /robots.txt and finalises
 * the shared head + body blocks. Every Pro-only feature has been
 * physically extracted to aiboost_aeo_pro:
 *
 *   - /llms-full.txt + /llms-{sef}.txt routing → AiBoostAeoPro::onAfterInitialise
 *   - IndexNow key file + auto-submit         → AiBoostAeoPro
 *   - Markdown page conversion                → AiBoostAeoPro
 *   - X-Robots-Tag header + AI meta tags      → AiBoostAeoPro::onBeforeCompileHead
 *   - Per-bot crawler rules                   → AiBoostAeoPro listener for
 *                                                EVENT_FILTER_ROBOTS_RULES
 *   - Per-language llms.txt translations +    → AiBoostAeoPro listener for
 *     "Full Index" reference                    EVENT_FILTER_LLMS_TXT
 *
 * Removing the Pro plugin removes the entire Pro code path — no setting,
 * no license-tier flag, no runtime patch can re-enable Pro behaviour
 * from the Free package.
 *
 * @package     AiBoost\Plugin\System\AiBoostAeo
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostAeo\Extension;

defined('_JEXEC') or die;

use AiBoost\Lib\BodyBlockBuilder;
use AiBoost\Lib\HeadBlockBuilder;
use AiBoost\Lib\Integration\FilterDispatcher;
use AiBoost\Lib\Integration\Sdk;
use AiBoost\Lib\JoomlaAppContext;
use AiBoost\Plugin\System\AiBoostAeo\Service\LlmsTxtGenerator;
use AiBoost\Version;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;

class AiBoostAeo extends CMSPlugin
{
    protected $autoloadLanguage = true;

    /**
     * onAfterInitialise — Free-tier virtual file routing.
     *
     * Handles /llms.txt (Free baseline) and /robots.txt. Pro routes
     * (/llms-full.txt, /llms-{sef}.txt, /{indexnow_key}.txt, /*.md)
     * are handled by aiboost_aeo_pro on the same event.
     */
    public function onAfterInitialise(): void
    {
        $uri  = $_SERVER['REQUEST_URI'] ?? '';
        $path = ltrim((string) parse_url($uri, PHP_URL_PATH), '/');

        if ($path === '') {
            return;
        }

        // robots.txt is a PHYSICAL file managed via a fenced block on disk
        // (Task #566) — never served virtually. We only handle llms.txt here.
        if ($path !== 'llms.txt') {
            return;
        }

        $settings = $this->getAiBoostSettings();

        $ctx = new JoomlaAppContext();
        $db  = Factory::getDbo();

        if ((int) ($settings['llmstxt_enabled'] ?? 1)) {
            $text = (new LlmsTxtGenerator($settings, $ctx, $db))->generate();

            // Pro decorator hook — aiboost_aeo_pro listens here and can
            // rebuild the response with per-language translations and
            // append the "Full Index" reference when llms-full is enabled.
            if (class_exists(FilterDispatcher::class)) {
                $filtered = FilterDispatcher::dispatch(
                    Sdk::EVENT_FILTER_LLMS_TXT,
                    [
                        'text'     => $text,
                        'settings' => $settings,
                        'kind'     => 'llms.txt',
                        'langCode' => '',
                    ]
                );
                if (isset($filtered['text']) && is_string($filtered['text']) && $filtered['text'] !== '') {
                    $text = $filtered['text'];
                }
            }

            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: public, max-age=86400');
            echo $text;
            Factory::getApplication()->close();
            return;
        }
    }

    /**
     * onBeforeCompileHead — share `hide_comments` flag with HeadBlockBuilder.
     *
     * X-Robots-Tag, AI meta tags, and Markdown discovery <link> are emitted
     * by aiboost_aeo_pro on the same event.
     */
    public function onBeforeCompileHead(): void
    {
        $settings = $this->getAiBoostSettings();
        $hide     = !empty($settings['hide_comments']);
        HeadBlockBuilder::setHideComments($hide);
        BodyBlockBuilder::setHideComments($hide);
    }

    /**
     * Idempotent finalize — see HeadBlockBuilder::finalize().
     */
    public function onAfterRender(): void
    {
        $app = Factory::getApplication();
        HeadBlockBuilder::finalize($app, Version::VERSION);
        BodyBlockBuilder::finalize($app);
    }

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
}
