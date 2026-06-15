<?php

namespace AiBoost\Tests\Lib;

use AiBoost\Lib\TranslationService;
use Joomla\Database\DatabaseInterface;
use PHPUnit\Framework\TestCase;

/**
 * Regression cover for the multilingual-schema suppression bug.
 *
 * On a multilingual Joomla front-end the System - Language Filter plugin
 * overwrites $app->get('language') with the ACTIVE request language, so the
 * value the schema plugin passes as TranslationService's $defaultLangCode can
 * equal the active language. The previous `if ($effectiveLang === defaultLang)
 * return $default;` short-circuit then suppressed EVERY translation (FAQ, HowTo,
 * org identity). get() must instead always look up the per-language row and fall
 * back to the base, which is correct because #__aiboost_translations only ever
 * holds non-default-language rows.
 */
final class TranslationServiceTest extends TestCase
{
    /** @param array<int,object> $rows */
    private function service(array $rows, string $defaultLang): TranslationService
    {
        // Minimal fluent query stub: TranslationService only chains ->select()->from().
        $query = new class {
            public function select($columns) { return $this; }
            public function from($table) { return $this; }
        };

        $db = $this->createMock(DatabaseInterface::class);
        $db->method('getQuery')->willReturn($query);
        $db->method('quoteName')->willReturnArgument(0);
        $db->method('setQuery')->willReturnSelf();
        $db->method('loadObjectList')->willReturn($rows);

        return new TranslationService($db, $defaultLang);
    }

    private function rows(): array
    {
        return [
            (object) ['field_key' => 'howto_name', 'lang_code' => 'ru-RU', 'field_value' => 'Как сделать'],
            (object) ['field_key' => 'howto_step_0_text', 'lang_code' => 'ru-RU', 'field_value' => 'Шаг'],
            (object) ['field_key' => 'faq_0_q', 'lang_code' => 'ru-RU', 'field_value' => 'Вопрос'],
        ];
    }

    /**
     * THE regression: even when $defaultLangCode == the looked-up language (the
     * languagefilter front-end case), an existing ru-RU row must be returned.
     */
    public function testTranslationResolvesEvenWhenDefaultEqualsActiveLanguage(): void
    {
        $ts = $this->service($this->rows(), 'ru-RU'); // default mis-set to the active lang
        $this->assertSame('Как сделать', $ts->get('howto_name', 'ru-RU', 'How To'));
        $this->assertSame('Шаг', $ts->get('howto_step_0_text', 'ru-RU', 'Step'));
        $this->assertSame('Вопрос', $ts->get('faq_0_q', 'ru-RU', 'Question'));
    }

    /** A correctly-set default language still resolves a non-default override. */
    public function testTranslationResolvesWithProperDefault(): void
    {
        $ts = $this->service($this->rows(), 'en-GB');
        $this->assertSame('Как сделать', $ts->get('howto_name', 'ru-RU', 'How To'));
    }

    /** A field/language with no stored row falls back to the base value. */
    public function testFallsBackToBaseWhenNoRow(): void
    {
        $ts = $this->service($this->rows(), 'en-GB');
        $this->assertSame('How To', $ts->get('howto_name', 'en-GB', 'How To'), 'default language → base');
        $this->assertSame('How To', $ts->get('howto_name', 'de-DE', 'How To'), 'untranslated language → base');
        $this->assertSame('Step 2', $ts->get('howto_step_1_text', 'ru-RU', 'Step 2'), 'absent key → base');
    }

    /** An empty stored value must not blank out the base. */
    public function testEmptyStoredValueFallsBackToBase(): void
    {
        $ts = $this->service(
            [(object) ['field_key' => 'howto_name', 'lang_code' => 'ru-RU', 'field_value' => '']],
            'en-GB'
        );
        $this->assertSame('How To', $ts->get('howto_name', 'ru-RU', 'How To'));
    }
}
