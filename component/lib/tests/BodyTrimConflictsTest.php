<?php
/**
 * Unit tests for AiBoost\Lib\BodyBlockBuilder::trimBodyConflicts() — the body
 * <noscript> analytics dedup (GTM iframe / Meta Pixel img). No Joomla/DB needed.
 *
 * @package     AiBoost\Lib\Tests
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Tests;

use AiBoost\Lib\BodyBlockBuilder;
use PHPUnit\Framework\TestCase;

if (!defined('_JEXEC')) {
    define('_JEXEC', 1);
}

require_once dirname(__DIR__) . '/src/BodyBlockBuilder.php';

final class BodyTrimConflictsTest extends TestCase
{
    private function block(): string
    {
        return implode("\n", [
            '<!-- AI Boost for Joomla - Start -->',
            '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-OURS" height="0" width="0"></iframe></noscript>',
            '<noscript><img height="1" width="1" src="https://www.facebook.com/tr?id=PIXOURS&ev=PageView&noscript=1"/></noscript>',
            '<!-- AI Boost for Joomla - End -->',
        ]);
    }

    protected function setUp(): void
    {
        BodyBlockBuilder::reset();
    }

    public function testGtmNoscriptRemovedKeepsPixel(): void
    {
        $theirs = '<body><noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-THEIRS"></iframe></noscript></body>';
        $out = BodyBlockBuilder::trimBodyConflicts($this->block(), $theirs, 'cooperative');
        $this->assertStringNotContainsString('GTM-OURS', $out);
        $this->assertStringNotContainsString('ns.html', $out, 'our GTM noscript must go');
        $this->assertStringContainsString('PIXOURS', $out, 'Pixel noscript kept — only GTM competed');
    }

    public function testPixelNoscriptRemovedKeepsGtm(): void
    {
        $theirs = '<body><noscript><img src="https://www.facebook.com/tr?id=PIXTHEIRS&ev=PageView&noscript=1"/></noscript></body>';
        $out = BodyBlockBuilder::trimBodyConflicts($this->block(), $theirs, 'cooperative');
        $this->assertStringNotContainsString('PIXOURS', $out);
        $this->assertStringContainsString('GTM-OURS', $out, 'GTM noscript kept — only Pixel competed');
    }

    public function testKeptWhenNoCompetitor(): void
    {
        $this->assertSame($this->block(), BodyBlockBuilder::trimBodyConflicts($this->block(), '<body></body>', 'cooperative'));
    }

    public function testAggressiveAndOffNeverTrim(): void
    {
        $theirs = '<body><noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-T"></iframe></noscript></body>';
        $this->assertSame($this->block(), BodyBlockBuilder::trimBodyConflicts($this->block(), $theirs, 'aggressive'));
        $this->assertSame($this->block(), BodyBlockBuilder::trimBodyConflicts($this->block(), $theirs, 'off'));
    }

    public function testNeverTouchesTheirs(): void
    {
        $theirs = '<body><noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-FOREIGN"></iframe></noscript></body>';
        $orig = $theirs;
        BodyBlockBuilder::trimBodyConflicts($this->block(), $theirs, 'cooperative');
        $this->assertSame($orig, $theirs);
    }

    /** Page PROSE mentioning the body URLs (not inside a <noscript>) must NOT trigger a trim. */
    public function testProseDoesNotTrigger(): void
    {
        $theirs = '<body><p>The pixel hits https://www.facebook.com/tr?id=123 and the GTM noscript loads '
                . 'https://www.googletagmanager.com/ns.html?id=GTM-X on no-JS browsers.</p></body>';
        $out = BodyBlockBuilder::trimBodyConflicts($this->block(), $theirs, 'cooperative');
        $this->assertSame($this->block(), $out, 'body prose must not trigger a noscript trim');
    }
}
