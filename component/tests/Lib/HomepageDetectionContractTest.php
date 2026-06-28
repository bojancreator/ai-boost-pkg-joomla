<?php

declare(strict_types=1);

namespace AiBoost\Tests\Lib;

use PHPUnit\Framework\TestCase;

/**
 * B2 (order 0006) — homepage-only schema (WebSite + SearchAction) was emitting on
 * EVERY page because JoomlaAppContext::isHomepage() used a fragile heuristic
 * (Uri path === '' / 'index.php', or option=com_content & view=featured) that
 * never consulted the authoritative `#__menu.home` flag of the active menu item.
 * On a site whose home is a Featured / Single-Article menu item (and on non-SEF
 * routes where getPath() resolves to 'index.php'), it returned true site-wide.
 *
 * Fix: isHomepage() resolves home from the ACTIVE menu item's `home` flag
 * (`$app->getMenu()->getActive()->home === 1`), with a bare-root path check kept
 * only as a fallback for routes with no active menu item.
 *
 * WHY a SOURCE guard: isHomepage() reads the live Joomla application via
 * Factory::getApplication() (no DI seam), so the standalone/PHPUnit suite cannot
 * exercise it with a real menu. The runtime-independent invariant — that the
 * method consults the menu home flag and no longer treats a featured view as
 * "home" — is therefore asserted over the SOURCE. (Behaviour is verified live on
 * staging per OPERATING.md: WebSite appears only on the real homepage.)
 *
 * Red-green: revert isHomepage() to the old heuristic (drop getActive()/home,
 * restore the view==='featured' return) and both assertions below go red.
 */
final class HomepageDetectionContractTest extends TestCase
{
    private function isHomepageBody(): string
    {
        $file = __DIR__ . '/../../lib/src/JoomlaAppContext.php';
        $src  = (string) file_get_contents($file);
        // Capture the isHomepage() body up to the first method-level closing brace
        // (a `}` at 4-space indentation on its own line).
        if (!preg_match('/function isHomepage\(\): bool\s*\{(.*?)\n    \}/s', $src, $m)) {
            self::fail('Could not locate isHomepage() in JoomlaAppContext.php');
        }
        return $m[1];
    }

    public function testIsHomepageConsultsActiveMenuHomeFlag(): void
    {
        $body = $this->isHomepageBody();

        self::assertStringContainsString(
            'getActive(',
            $body,
            'isHomepage() must resolve the active menu item (authoritative home detection).'
        );
        self::assertMatchesRegularExpression(
            '/->\s*home\b/',
            $body,
            'isHomepage() must read the active menu item\'s `home` flag.'
        );
    }

    public function testIsHomepageNoLongerTreatsFeaturedViewAsHome(): void
    {
        $body = $this->isHomepageBody();

        self::assertStringNotContainsString(
            "'featured'",
            $body,
            'isHomepage() must NOT treat any com_content featured/blog view as the homepage — '
            . 'that heuristic mis-fired site-wide. Use the active menu item home flag instead.'
        );
    }
}
