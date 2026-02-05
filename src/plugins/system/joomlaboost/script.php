<?php

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class plgSystemJoomlaboostInstallerScript
{
    public function postflight($type, $adapter)
    {
        // Show modern installation message
        if ($type === 'install' || $type === 'update') {
            echo '
            <div style="padding: 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 12px; font-family: system-ui, -apple-system, sans-serif; margin: 20px 0; box-shadow: 0 10px 40px rgba(0,0,0,0.2);">
                <h2 style="margin: 0 0 20px 0; font-size: 28px; font-weight: 700;">🚀 JoomlaBoost Successfully ' . ($type === 'install' ? 'Installed' : 'Updated') . '!</h2>
                
                <div style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: 8px; margin-bottom: 20px; backdrop-filter: blur(10px);">
                    <p style="margin: 0 0 10px 0; font-size: 16px; line-height: 1.6;">Universal SEO & Performance plugin that automatically optimizes your Joomla site for search engines and social media.</p>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
                    <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px;">
                        <div style="font-size: 24px; margin-bottom: 8px;">✨</div>
                        <div style="font-weight: 600; margin-bottom: 5px;">Smart SEO</div>
                        <div style="font-size: 13px; opacity: 0.9;">Schema.org, OpenGraph, Meta Tags</div>
                    </div>
                    <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px;">
                        <div style="font-size: 24px; margin-bottom: 8px;">📊</div>
                        <div style="font-weight: 600; margin-bottom: 5px;">Analytics</div>
                        <div style="font-size: 13px; opacity: 0.9;">GA4, GTM, Meta Pixel</div>
                    </div>
                    <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px;">
                        <div style="font-size: 24px; margin-bottom: 8px;">⚡</div>
                        <div style="font-weight: 600; margin-bottom: 5px;">Performance</div>
                        <div style="font-size: 13px; opacity: 0.9;">Caching & Optimization</div>
                    </div>
                    <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px;">
                        <div style="font-size: 24px; margin-bottom: 8px;">🌐</div>
                        <div style="font-weight: 600; margin-bottom: 5px;">Multi-Environment</div>
                        <div style="font-size: 13px; opacity: 0.9;">Production, Staging, Dev</div>
                    </div>
                </div>
                
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.2);">
                    <p style="margin: 0 0 15px 0; font-size: 14px; opacity: 0.95;">📋 <strong>Next Steps:</strong></p>
                    <ol style="margin: 0; padding-left: 20px; font-size: 14px; line-height: 1.8;">
                        <li>Enable the plugin in <strong>System → Plugins</strong></li>
                        <li>Configure your settings in plugin options</li>
                        <li>Add Google Analytics, GTM, or Meta Pixel IDs (optional)</li>
                        <li>Check your site source code for SEO tags</li>
                    </ol>
                </div>
                
                <div style="margin-top: 20px; font-size: 12px; opacity: 0.8; text-align: center;">
                    <strong>Version 0.2.5</strong> | Joomla 4.0+ / 5.0+ / 6.0+ | PHP 8.1+ | Built with ❤️ by JoomlaBoost Team
                </div>
            </div>
            ';
        }

        // Silent logging without user-facing messages
        $log = JPATH_ROOT . '/joomlaboost_install.log';
        file_put_contents($log, "\n" . str_repeat('=', 60) . "\n", FILE_APPEND);
        file_put_contents($log, date('Y-m-d H:i:s') . " - v0.2.5 Installation Complete\n", FILE_APPEND);
        file_put_contents($log, str_repeat('=', 60) . "\n", FILE_APPEND);

        return true;
    }
}
