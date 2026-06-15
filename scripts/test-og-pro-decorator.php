<?php
/**
 * AI Boost — OG/Twitter Pro Enrichment Test (Task #551)
 *
 * Standalone CLI-runnable test. Does not require PHPUnit or a full Joomla
 * bootstrap. Covers the two pieces of the Pro Social plugin that Task #549
 * left untested:
 *
 *   1. OgTagProDecorator::decorate() — the actual enrichment logic:
 *        - og:locale auto from the active language (mapped + fallback)
 *        - fb:app_id
 *        - twitter:site handle normalisation (@ prefix)
 *        - per-language site_name / og_description_override / default_og_image
 *        - per-article custom fields (og:title/description/type/video/twitter:card)
 *        - article intro-image fallback (+ custom field override precedence)
 *        - og:type=article + article:published_time/modified_time/author/section
 *        - Free baseline keys preserved
 *
 *   2. AiBoostSocialPro::onAiBoostFilterSocialProps() — the hasPro('og')
 *      activation gate: with the Pro 'og' license inactive the listener must
 *      leave the Free baseline props completely untouched (Task #537).
 *
 * The real Service/Extension classes are exercised. Joomla framework classes
 * the decorator never reaches in these paths (Factory, JoomlaAppContext) are
 * stubbed; the host CMS application context, database and translation store
 * are replaced with deterministic in-memory fakes.
 *
 * Usage:
 *   php scripts/test-og-pro-decorator.php
 *
 * Exit code 0 = all tests passed. Exit code 1 = one or more tests failed.
 */

declare(strict_types=1);

// ── Minimal framework stubs ──────────────────────────────────────────────────
namespace Joomla\CMS\Plugin {
    if (!class_exists(CMSPlugin::class)) {
        class CMSPlugin
        {
        }
    }
}

namespace Joomla\Database {
    if (!interface_exists(DatabaseInterface::class)) {
        interface DatabaseInterface
        {
        }
    }
}

namespace {

use AiBoost\Lib\AppContextInterface;
use AiBoost\Lib\Integration\FilterResult;
use AiBoost\Lib\PluginRegistry;
use AiBoost\Lib\TranslationService;
use AiBoost\Plugin\System\AiBoostSocial\Service\OgTagProDecorator;
use Joomla\Database\DatabaseInterface;

\define('_JEXEC', 1);
\define('JPATH_ROOT', sys_get_temp_dir());
\define('JPATH_ADMINISTRATOR', '/nonexistent-aiboost-test-path');

$base = __DIR__ . '/../component';

require_once $base . '/lib/src/AppContextInterface.php';
require_once $base . '/lib/src/TranslationService.php';
require_once $base . '/lib/src/Integration/FilterResult.php';
require_once $base . '/lib/src/PluginRegistry.php';
require_once $base . '/plugins/system/aiboost_social/src/Service/OgTagBuilder.php';
require_once $base . '/plugins/system/aiboost_social/src/Service/CustomFieldReader.php';
require_once $base . '/plugins/system/aiboost_social/src/Service/OgTagProDecorator.php';

$passed = 0;
$failed = 0;

function assert_true(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        echo "  PASS  {$label}\n";
        $passed++;
    } else {
        echo "  FAIL  {$label}\n";
        $failed++;
    }
}

function assert_equals(mixed $expected, mixed $actual, string $label): void
{
    assert_true($expected === $actual, "{$label} (expected: " . var_export($expected, true) . ", got: " . var_export($actual, true) . ")");
}

function assert_missing(array $arr, string $key, string $label): void
{
    assert_true(!array_key_exists($key, $arr), $label);
}

// ── Deterministic fakes ──────────────────────────────────────────────────────

/** Chainable no-op query builder. */
class FakeQuery
{
    public function __call(string $name, array $args): self
    {
        return $this;
    }
}

/** In-memory DatabaseInterface stub feeding canned rows back to the decorator. */
class FakeDatabase implements DatabaseInterface
{
    public ?object $article = null;
    /** @var array<int,object> custom-field rows returned to CustomFieldReader */
    public array $customFieldRows = [];
    /** @var array<int,mixed> FIFO queue for loadResult() (author, then category) */
    public array $resultQueue = [];

    public function getQuery($new = true): FakeQuery
    {
        return new FakeQuery();
    }

    public function setQuery($query, $offset = 0, $limit = 0): self
    {
        return $this;
    }

    public function loadObject($class = 'stdClass')
    {
        return $this->article;
    }

    public function loadObjectList($key = '', $class = 'stdClass'): array
    {
        return $this->customFieldRows;
    }

    public function loadResult()
    {
        return array_shift($this->resultQueue);
    }

    public function getPrefix(): string
    {
        return 'jos_';
    }

    public function getTableList(): array
    {
        // Empty → Falang overlay path is skipped in CustomFieldReader.
        return [];
    }

    public function quote($text, $escape = true): string
    {
        return "'" . $text . "'";
    }

    public function quoteName($name, $as = null): string
    {
        return $name;
    }
}

/** Minimal AppContextInterface implementation. */
class FakeAppContext implements AppContextInterface
{
    public function __construct(
        private string $activeLanguage = 'en-GB',
        private string $siteName = 'Test Site',
        private string $baseUrl = 'https://site.test'
    ) {}

    public function getCurrentUrl(): string { return $this->baseUrl . '/'; }
    public function getBaseUrl(): string { return $this->baseUrl; }
    public function getSiteName(): string { return $this->siteName; }
    public function getActiveLanguage(): string { return $this->activeLanguage; }
    public function getDefaultLanguage(): string { return 'en-GB'; }
    public function isAdmin(): bool { return false; }
    public function isHomepage(): bool { return false; }
    public function getCurrentView(): string { return ''; }
    public function getCurrentOption(): string { return ''; }
    public function getCurrentId(): int { return 0; }
    public function getPageTitle(): string { return ''; }
    public function getPageDescription(): string { return ''; }
    public function translate(string $key): string { return $key; }
    public function getPathway(): array { return []; }
    public function getConfigValue(string $key, string $default = ''): string { return $default; }
    public function getUserTimezone(): string { return 'UTC'; }
}

/** TranslationService whose values are driven by an in-memory map. */
class FakeTranslations extends TranslationService
{
    /** @param array<string,array<string,string>> $map field => [lang => value] */
    public function __construct(private array $map = [])
    {
        // Intentionally does not call parent::__construct — no DB needed.
    }

    public function get(string $fieldKey, string $langCode = '', string $default = ''): string
    {
        $value = $this->map[$fieldKey][$langCode] ?? '';
        return $value !== '' ? $value : $default;
    }
}

/** Build a custom-field row the way CustomFieldReader's query returns it. */
function field_row(string $name, string $value, int $fieldId): \stdClass
{
    $row           = new \stdClass();
    $row->name     = $name;
    $row->value    = $value;
    $row->field_id = $fieldId;
    return $row;
}

/** @param array<string,mixed> $contextOverride */
function base_props(array $contextOverride = []): array
{
    return [
        'og' => [
            'og:title'       => 'Free Title',
            'og:description' => 'Free Description',
            'og:type'        => 'website',
            'og:url'         => 'https://site.test/',
        ],
        'tw' => [
            'twitter:card'        => 'summary',
            'twitter:title'       => 'Free Title',
            'twitter:description' => 'Free Description',
        ],
        'enable_twitter' => true,
        'context'        => array_merge(['option' => '', 'view' => '', 'id' => 0], $contextOverride),
    ];
}

function make_decorator(FakeAppContext $ctx, FakeDatabase $db, ?FakeTranslations $tr = null): OgTagProDecorator
{
    return new OgTagProDecorator($ctx, $db, $tr ?? new FakeTranslations());
}

echo "\nAI Boost — OG/Twitter Pro Enrichment Test\n";
echo str_repeat('=', 50) . "\n\n";

// ── Test 1: og:locale auto from a mapped active language ──────────────────────
echo "Test 1: og:locale mapped from active language\n";
$d   = make_decorator(new FakeAppContext('de-DE'), new FakeDatabase());
$out = $d->decorate(base_props(), ['enable_og_locale' => 1]);
assert_equals('de_DE', $out['og']['og:locale'] ?? null, 'de-DE → de_DE locale');

// ── Test 2: og:locale fallback for an unmapped language ──────────────────────
echo "\nTest 2: og:locale fallback for unmapped language\n";
$d   = make_decorator(new FakeAppContext('sr-RS'), new FakeDatabase());
$out = $d->decorate(base_props(), ['enable_og_locale' => 1]);
assert_equals('sr_RS', $out['og']['og:locale'] ?? null, 'sr-RS → sr_RS via str_replace fallback');

// ── Test 3: og:locale disabled ───────────────────────────────────────────────
echo "\nTest 3: og:locale omitted when disabled\n";
$d   = make_decorator(new FakeAppContext('de-DE'), new FakeDatabase());
$out = $d->decorate(base_props(), ['enable_og_locale' => 0]);
assert_missing($out['og'], 'og:locale', 'no og:locale when enable_og_locale=0');

// ── Test 4: fb:app_id ────────────────────────────────────────────────────────
echo "\nTest 4: fb:app_id passthrough\n";
$d   = make_decorator(new FakeAppContext(), new FakeDatabase());
$out = $d->decorate(base_props(), ['fb_app_id' => '1234567890']);
assert_equals('1234567890', $out['og']['fb:app_id'] ?? null, 'fb:app_id set when provided');

$out = $d->decorate(base_props(), ['fb_app_id' => '']);
assert_missing($out['og'], 'fb:app_id', 'no fb:app_id when blank');

// ── Test 5: twitter:site handle normalisation ────────────────────────────────
echo "\nTest 5: twitter:site handle gets @ prefix\n";
$d   = make_decorator(new FakeAppContext(), new FakeDatabase());
$out = $d->decorate(base_props(), ['twitter_site_handle' => 'aiboost']);
assert_equals('@aiboost', $out['tw']['twitter:site'] ?? null, 'bare handle gets @ prefix');

$out = $d->decorate(base_props(), ['twitter_site_handle' => '@aiboost']);
assert_equals('@aiboost', $out['tw']['twitter:site'] ?? null, 'existing @ preserved');

// ── Test 6: Free baseline keys preserved through decoration ───────────────────
echo "\nTest 6: Free baseline keys preserved\n";
$d   = make_decorator(new FakeAppContext(), new FakeDatabase());
$out = $d->decorate(base_props(), ['enable_og_locale' => 0]);
assert_equals('Free Title', $out['og']['og:title'] ?? null, 'og:title untouched');
assert_equals('Free Description', $out['og']['og:description'] ?? null, 'og:description untouched');
assert_equals('summary', $out['tw']['twitter:card'] ?? null, 'twitter:card untouched');
assert_true(($out['enable_twitter'] ?? null) === true, 'enable_twitter preserved');

// ── Test 7: per-language site_name + description override translations ─────────
echo "\nTest 7: per-language translations override og fields\n";
$tr  = new FakeTranslations([
    'site_name'               => ['de-DE' => 'AI Boost DE'],
    'og_description_override'  => ['de-DE' => 'Beschreibung DE'],
]);
$d   = make_decorator(new FakeAppContext('de-DE'), new FakeDatabase(), $tr);
$out = $d->decorate(base_props(), ['enable_og_locale' => 0]);
assert_equals('AI Boost DE', $out['og']['og:site_name'] ?? null, 'og:site_name from translation');
assert_equals('Beschreibung DE', $out['og']['og:description'] ?? null, 'og:description from translation');
assert_equals('Beschreibung DE', $out['tw']['twitter:description'] ?? null, 'twitter:description mirrors override');

// ── Test 8: per-language default_og_image translation ────────────────────────
echo "\nTest 8: per-language default_og_image becomes absolute og/twitter image\n";
$tr  = new FakeTranslations(['default_og_image' => ['de-DE' => 'images/de-hero.jpg']]);
$d   = make_decorator(new FakeAppContext('de-DE'), new FakeDatabase(), $tr);
$out = $d->decorate(base_props(), ['enable_og_locale' => 0]);
assert_equals('https://site.test/images/de-hero.jpg', $out['og']['og:image'] ?? null, 'og:image from translated default');
assert_equals('https://site.test/images/de-hero.jpg', $out['tw']['twitter:image'] ?? null, 'twitter:image mirrors translated default');

// ── Test 9: article meta + og:type=article (no fields, no intro image) ────────
echo "\nTest 9: og:type=article + article:* meta\n";
$db          = new FakeDatabase();
$db->article = (object) [
    'id'         => 10,
    'title'      => 'My Article',
    'metadesc'   => '',
    'images'     => '{}',
    'publish_up' => '2026-01-15 10:00:00',
    'modified'   => '2026-02-20 12:30:00',
    'created_by' => 42,
    'catid'      => 7,
];
$db->resultQueue = ['Jane Doe', 'News'];
$d   = make_decorator(new FakeAppContext('en-GB'), $db);
$out = $d->decorate(
    base_props(['option' => 'com_content', 'view' => 'article', 'id' => 10]),
    ['enable_per_article_fields' => 0, 'enable_article_og_type' => 1, 'enable_og_locale' => 0]
);
$expPub = (new \DateTime('2026-01-15 10:00:00'))->format(\DateTime::ATOM);
$expMod = (new \DateTime('2026-02-20 12:30:00'))->format(\DateTime::ATOM);
assert_equals('article', $out['og']['og:type'] ?? null, 'og:type forced to article');
assert_equals($expPub, $out['og']['article:published_time'] ?? null, 'article:published_time set');
assert_equals($expMod, $out['og']['article:modified_time'] ?? null, 'article:modified_time set');
assert_equals('Jane Doe', $out['og']['article:author'] ?? null, 'article:author from users table');
assert_equals('News', $out['og']['article:section'] ?? null, 'article:section from categories table');

// ── Test 10: custom fields override title/description/type ─────────────────────
echo "\nTest 10: per-article custom fields override og:title/description/type\n";
$db          = new FakeDatabase();
$db->article = (object) [
    'images' => '{}', 'publish_up' => '', 'modified' => '', 'created_by' => 0, 'catid' => 0,
];
$db->customFieldRows = [
    field_row('aiboost_og_title', 'Custom OG Title', 1),
    field_row('aiboost_og_description', 'Custom OG Desc', 2),
    field_row('aiboost_og_type', 'website', 3),
];
$db->resultQueue = ['', ''];
$d   = make_decorator(new FakeAppContext('en-GB'), $db);
$out = $d->decorate(
    base_props(['option' => 'com_content', 'view' => 'article', 'id' => 5]),
    ['enable_per_article_fields' => 1, 'enable_article_og_type' => 1, 'enable_og_locale' => 0]
);
assert_equals('Custom OG Title', $out['og']['og:title'] ?? null, 'og:title from custom field');
assert_equals('Custom OG Title', $out['tw']['twitter:title'] ?? null, 'twitter:title mirrors custom field');
assert_equals('Custom OG Desc', $out['og']['og:description'] ?? null, 'og:description from custom field');
assert_equals('website', $out['og']['og:type'] ?? null, 'custom og:type wins over forced article');

// ── Test 11: og:video custom field → absolute URL ────────────────────────────
echo "\nTest 11: og:video from custom field becomes absolute\n";
$db          = new FakeDatabase();
$db->article = (object) ['images' => '{}', 'publish_up' => '', 'modified' => '', 'created_by' => 0, 'catid' => 0];
$db->customFieldRows = [field_row('aiboost_og_video', 'videos/promo.mp4', 9)];
$d   = make_decorator(new FakeAppContext('en-GB'), $db);
$out = $d->decorate(
    base_props(['option' => 'com_content', 'view' => 'article', 'id' => 5]),
    ['enable_per_article_fields' => 1, 'enable_article_og_type' => 0, 'enable_og_locale' => 0]
);
assert_equals('https://site.test/videos/promo.mp4', $out['og']['og:video'] ?? null, 'og:video resolved to absolute URL');

// ── Test 12: twitter:card override custom field ──────────────────────────────
echo "\nTest 12: twitter:card override from custom field\n";
$db          = new FakeDatabase();
$db->article = (object) ['images' => '{}', 'publish_up' => '', 'modified' => '', 'created_by' => 0, 'catid' => 0];
$db->customFieldRows = [field_row('aiboost_twitter_card', 'summary_large_image', 11)];
$d   = make_decorator(new FakeAppContext('en-GB'), $db);
$out = $d->decorate(
    base_props(['option' => 'com_content', 'view' => 'article', 'id' => 5]),
    ['enable_per_article_fields' => 1, 'enable_article_og_type' => 0, 'enable_og_locale' => 0]
);
assert_equals('summary_large_image', $out['tw']['twitter:card'] ?? null, 'twitter:card overridden by custom field');

// ── Test 13: article intro-image fallback ────────────────────────────────────
echo "\nTest 13: intro-image fallback when no custom image field\n";
$db          = new FakeDatabase();
$db->article = (object) [
    'images'     => json_encode(['image_intro' => 'images/intro.jpg']),
    'publish_up' => '', 'modified' => '', 'created_by' => 0, 'catid' => 0,
];
$db->customFieldRows = [];
$d   = make_decorator(new FakeAppContext('en-GB'), $db);
$out = $d->decorate(
    base_props(['option' => 'com_content', 'view' => 'article', 'id' => 5]),
    ['enable_per_article_fields' => 1, 'enable_article_og_type' => 0, 'enable_og_locale' => 0]
);
assert_equals('https://site.test/images/intro.jpg', $out['og']['og:image'] ?? null, 'og:image from article intro image');
assert_equals('https://site.test/images/intro.jpg', $out['tw']['twitter:image'] ?? null, 'twitter:image mirrors intro image');

// ── Test 14: custom og:image overrides intro image ───────────────────────────
echo "\nTest 14: custom og:image field overrides intro image\n";
$db          = new FakeDatabase();
$db->article = (object) [
    'images'     => json_encode(['image_intro' => 'images/intro.jpg']),
    'publish_up' => '', 'modified' => '', 'created_by' => 0, 'catid' => 0,
];
$db->customFieldRows = [field_row('aiboost_og_image', 'images/custom.jpg', 4)];
$d   = make_decorator(new FakeAppContext('en-GB'), $db);
$out = $d->decorate(
    base_props(['option' => 'com_content', 'view' => 'article', 'id' => 5]),
    ['enable_per_article_fields' => 1, 'enable_article_og_type' => 0, 'enable_og_locale' => 0]
);
assert_equals('https://site.test/images/custom.jpg', $out['og']['og:image'] ?? null, 'custom og:image wins over intro image');

// ── Test 15: enable_article_og_type=0 suppresses article meta ────────────────
echo "\nTest 15: article:* meta suppressed when enable_article_og_type=0\n";
$db          = new FakeDatabase();
$db->article = (object) [
    'images'     => '{}',
    'publish_up' => '2026-01-15 10:00:00', 'modified' => '2026-02-20 12:30:00',
    'created_by' => 42, 'catid' => 7,
];
$d   = make_decorator(new FakeAppContext('en-GB'), $db);
$out = $d->decorate(
    base_props(['option' => 'com_content', 'view' => 'article', 'id' => 5]),
    ['enable_per_article_fields' => 0, 'enable_article_og_type' => 0, 'enable_og_locale' => 0]
);
assert_equals('website', $out['og']['og:type'] ?? null, 'og:type stays Free baseline (website)');
assert_missing($out['og'], 'article:published_time', 'no article:published_time when disabled');
assert_missing($out['og'], 'article:author', 'no article:author when disabled');

// Test 16 (activation gate) RETIRED in the Pro-replaces-Free collapse: the
// inactive-Pro no-op gate moved OFF the (now-neutered) aiboost_social_pro
// decorator and INTO the free aiboost_social plugin's onBeforeCompileHead,
// where OgTagProDecorator is invoked only behind
// `class_exists(OgTagProDecorator::class) && PluginRegistry::isProActive()`.
// That gate (isProActive) is unit-covered by PluginRegistryIsProActiveTest;
// the decorator itself no longer self-gates, so a standalone listener test is
// no longer meaningful. The enrichment-logic tests above remain the coverage
// for OgTagProDecorator::decorate().

// ── Summary ──────────────────────────────────────────────────────────────────
echo "\n" . str_repeat('-', 50) . "\n";
echo "Results: {$passed} passed, {$failed} failed\n\n";

if ($failed > 0) {
    echo "[FAIL] Some tests failed.\n";
    exit(1);
}

echo "[PASS] All tests passed.\n";
exit(0);

}
