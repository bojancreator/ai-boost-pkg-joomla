<?php
/**
 * AI Boost — OG Custom Field NULL/empty Guard Test (Task #549)
 *
 * Standalone CLI-runnable test. Does not require PHPUnit or a full Joomla
 * bootstrap. Proves that the defensive guard added in Task #548
 * (AiBoostSocialPro::onCustomFieldsPrepareField) coerces NULL / '' values on
 * the six aiboost_og_* / aiboost_twitter_card custom fields into safe,
 * non-null defaults, so an older or third-party field renderer can never trip
 * a PHP 8.1+ json_decode(null) / DOMCdataSection(null) deprecation on an
 * article page.
 *
 * The listener method uses no $this state and touches no Joomla services, so
 * we stub the CMSPlugin parent and instantiate the real Extension class via
 * reflection (without invoking its constructor).
 *
 * Usage:
 *   php scripts/test-og-field-guard.php
 *
 * Exit code 0 = all tests passed. Exit code 1 = one or more tests failed.
 */

declare(strict_types=1);

// ── Minimal stub for the CMSPlugin parent ────────────────────────────────────
// The Extension class declaration `extends CMSPlugin` requires the parent to
// exist at load time. The guard method itself never calls any parent method.
namespace Joomla\CMS\Plugin {
    if (!class_exists(CMSPlugin::class)) {
        class CMSPlugin
        {
        }
    }
}

namespace {

use AiBoost\Plugin\System\AiBoostSocialPro\Extension\AiBoostSocialPro;

\define('_JEXEC', 1);

require_once __DIR__ . '/../component/plugins/system/aiboost_social_pro/src/Extension/AiBoostSocialPro.php';

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

/**
 * Build a field object the way Joomla's custom-fields layer would pass it.
 */
function make_field(string $name, string $type, mixed $value, mixed $rawvalue): \stdClass
{
    $field           = new \stdClass();
    $field->name     = $name;
    $field->type     = $type;
    $field->value    = $value;
    $field->rawvalue = $rawvalue;

    return $field;
}

echo "\nAI Boost — OG Custom Field NULL/empty Guard Test\n";
echo str_repeat('=', 50) . "\n\n";

// Instantiate the real listener without invoking the CMSPlugin constructor.
$ref      = new \ReflectionClass(AiBoostSocialPro::class);
$listener = $ref->newInstanceWithoutConstructor();

$article = 'com_content.article';

// Field name → type, matching the installer's re-introduced fields.
// aiboost_og_image is the Media field (JSON); the rest are text-like (CDATA).
$mediaField = 'aiboost_og_image';
$textFields = [
    'aiboost_og_title',
    'aiboost_og_description',
    'aiboost_og_type',
    'aiboost_og_video',
    'aiboost_twitter_card',
];

$safeJson = '{"imagefile":""}';

// ── Test 1: Media field — NULL value/rawvalue becomes safe JSON ───────────────
echo "Test 1: Media field with NULL value/rawvalue → safe JSON\n";
$f = make_field($mediaField, 'media', null, null);
$listener->onCustomFieldsPrepareField($article, new \stdClass(), $f);
assert_equals($safeJson, $f->value, "{$mediaField} NULL value coerced to safe JSON");
assert_equals($safeJson, $f->rawvalue, "{$mediaField} NULL rawvalue coerced to safe JSON");

// ── Test 2: Media field — '' value/rawvalue becomes safe JSON ─────────────────
echo "\nTest 2: Media field with '' value/rawvalue → safe JSON\n";
$f = make_field($mediaField, 'media', '', '');
$listener->onCustomFieldsPrepareField($article, new \stdClass(), $f);
assert_equals($safeJson, $f->value, "{$mediaField} '' value coerced to safe JSON");
assert_equals($safeJson, $f->rawvalue, "{$mediaField} '' rawvalue coerced to safe JSON");

// ── Test 3: Text-like fields — NULL becomes '' ───────────────────────────────
echo "\nTest 3: Text-like fields with NULL → ''\n";
foreach ($textFields as $name) {
    $f = make_field($name, 'text', null, null);
    $listener->onCustomFieldsPrepareField($article, new \stdClass(), $f);
    assert_equals('', $f->value, "{$name} NULL value coerced to ''");
    assert_equals('', $f->rawvalue, "{$name} NULL rawvalue coerced to ''");
}

// ── Test 4: Text-like fields — '' stays '' ───────────────────────────────────
echo "\nTest 4: Text-like fields with '' → '' (idempotent)\n";
foreach ($textFields as $name) {
    $f = make_field($name, 'text', '', '');
    $listener->onCustomFieldsPrepareField($article, new \stdClass(), $f);
    assert_equals('', $f->value, "{$name} '' value stays ''");
    assert_equals('', $f->rawvalue, "{$name} '' rawvalue stays ''");
}

// ── Test 5: Non-empty values are left untouched (idempotent) ──────────────────
echo "\nTest 5: Non-empty values are left untouched\n";
$mediaVal = '{"imagefile":"images/hero.jpg"}';
$f = make_field($mediaField, 'media', $mediaVal, $mediaVal);
$listener->onCustomFieldsPrepareField($article, new \stdClass(), $f);
assert_equals($mediaVal, $f->value, "{$mediaField} non-empty value untouched");
assert_equals($mediaVal, $f->rawvalue, "{$mediaField} non-empty rawvalue untouched");

foreach ($textFields as $name) {
    $f = make_field($name, 'text', 'Hello world', 'Hello world');
    $listener->onCustomFieldsPrepareField($article, new \stdClass(), $f);
    assert_equals('Hello world', $f->value, "{$name} non-empty value untouched");
    assert_equals('Hello world', $f->rawvalue, "{$name} non-empty rawvalue untouched");
}

// ── Test 6: Running the guard twice is stable (idempotent) ────────────────────
echo "\nTest 6: Running the guard twice is stable\n";
$f = make_field($mediaField, 'media', null, null);
$listener->onCustomFieldsPrepareField($article, new \stdClass(), $f);
$listener->onCustomFieldsPrepareField($article, new \stdClass(), $f);
assert_equals($safeJson, $f->value, "{$mediaField} stable after double run");

// ── Test 7: Non-OG fields on an article are ignored ──────────────────────────
echo "\nTest 7: Non-OG fields are ignored\n";
$f = make_field('some_other_field', 'media', null, null);
$listener->onCustomFieldsPrepareField($article, new \stdClass(), $f);
assert_true($f->value === null, "non-OG media field left as NULL");
assert_true($f->rawvalue === null, "non-OG media field rawvalue left as NULL");

// ── Test 8: OG field outside the article context is ignored ───────────────────
echo "\nTest 8: OG field outside com_content.article is ignored\n";
$f = make_field($mediaField, 'media', null, null);
$listener->onCustomFieldsPrepareField('com_contact.contact', new \stdClass(), $f);
assert_true($f->value === null, "OG field in non-article context left as NULL");

$f = make_field($mediaField, 'media', null, null);
$listener->onCustomFieldsPrepareField('com_content.category', new \stdClass(), $f);
assert_true($f->value === null, "OG field in category context left as NULL");

// ── Test 9: Field without a rawvalue property only mutates value ──────────────
echo "\nTest 9: Field without rawvalue property → only value mutated\n";
$f        = new \stdClass();
$f->name  = $mediaField;
$f->type  = 'media';
$f->value = null;
$listener->onCustomFieldsPrepareField($article, new \stdClass(), $f);
assert_equals($safeJson, $f->value, "{$mediaField} value coerced when rawvalue absent");
assert_true(!property_exists($f, 'rawvalue'), "no rawvalue property added");

// ── Test 10: Non-object / nameless field is ignored without error ─────────────
echo "\nTest 10: Malformed field objects are ignored safely\n";
$nameless = new \stdClass();
$nameless->type  = 'media';
$nameless->value = null;
$listener->onCustomFieldsPrepareField($article, new \stdClass(), $nameless);
assert_true($nameless->value === null, "field without ->name left untouched");

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
