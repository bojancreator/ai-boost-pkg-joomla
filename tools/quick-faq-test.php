<?php

/**
 * Quick FAQ Schema Test - Standalone Version
 *
 * Usage: php tools/quick-faq-test.php
 *
 * Tests FAQ extraction patterns without Joomla dependency
 */

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║       JoomlaBoost - Quick FAQ Schema Test (Standalone)       ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

// Sample HTML content with various FAQ formats
$sampleContent = <<<'HTML'
<div class="article-content">
    <h2>Često Postavljena Pitanja o Off-Road Turама</h2>

    <!-- Method 1: Definition Lists -->
    <dl>
        <dt>Pitanje: Da li je potrebno iskustvo u vožnji 4x4 vozila?</dt>
        <dd>Ne, nije potrebno prethodno iskustvo. Naš tim iskusnih vodičeva će vas provesti kroz sve tehnike off-road vožnje i obezbediti potpunu sigurnost.</dd>

        <dt>Koliko traje prosečna tura?</dt>
        <dd>Prosečna tura traje između 4 i 8 sati, u zavisnosti od izabrane destinacije i nivoa težine. Nudimo i cele-dnevne ture za avanturističkije učesnike.</dd>
    </dl>

    <!-- Method 2: Headings with Keywords -->
    <h3>Kako rezervisati mesto na turi?</h3>
    <p>Rezervaciju možete izvršiti putem naše kontakt forme, telefonom ili direktno putem email-a.
    Preporučujemo da rezervišete najmanje nedelju dana unapred kako biste osigurali mesto,
    posebno tokom vikenda i praznika.</p>

    <h3>Šta treba poneti na off-road turu?</h3>
    <p>Preporučujemo sportsku odeću prilagođenu vremenskim uslovima, udobne cipele ili čizme,
    zaštitu od sunca (naočare i kremu), dovoljno vode za hidrataciju, i lični fotoaparat za nezaboravne trenutke.
    Sve ostalo (vozilo, gorivo, vodič) obezbedujemo mi.</p>

    <h3>Zašto izabrati OffRoad Serbia?</h3>
    <p>Jer imamo preko 10 godina iskustva u organizaciji off-road tura, profesionalne i sertifikovane vođe,
    najbolju i redovno servisiranu opremu, i poznajemo skrivene prirodne lepote Srbije koje drugi ne mogu da ponude.
    Plus, imamo više od 200 zadovoljnih klijenata godišnje!</p>

    <!-- Method 3: Bold Q&A Pattern -->
    <div class="faq-item">
        <strong>Q: Da li su deca dobrodošla na turama?</strong>
        <p>A: Apsolutno! Porodične ture su posebno dizajnirane sa sigurnošću dece na umu.
        Deca starija od 5 godina mogu učestvovati uz pratnju roditelja.
        Imamo specijalne sedišta i sigurnosne pojaseve za mlađu decu.</p>
    </div>

    <div class="faq-item">
        <strong>Pitanje: Koliko košta prosečna tura?</strong>
        <p>Odgovor: Cene variraju od 50€ do 150€ po osobi, u zavisnosti od destinacije, trajanja ture i broja učesnika.
        Porodične popuste možete dobiti za grupe veće od 4 osobe. Kontaktirajte nas za detaljan cenovnik.</p>
    </div>
</div>
HTML;

/**
 * Extract FAQ items from content using the same patterns as SchemaService
 */
function extractFAQItems(string $content): array
{
    $faqItems = [];

    echo "🔍 Testing FAQ Extraction Patterns...\n\n";

    // Method 1: Definition Lists (dt/dd)
    echo "📋 Method 1: Definition Lists (<dt>/<dd>)\n";
    $dlPattern = '/<dt[^>]*>(.*?)<\/dt>\s*<dd[^>]*>(.*?)<\/dd>/is';
    $dlCount = 0;
    if (preg_match_all($dlPattern, $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $question = strip_tags($match[1]);
            $answer = strip_tags($match[2]);

            if (strlen($question) > 5 && strlen($answer) > 10) {
                $faqItems[] = [
                    'method' => 'Definition List',
                    'question' => trim($question),
                    'answer' => trim($answer)
                ];
                $dlCount++;
            }
        }
        echo "  ✅ Found $dlCount items\n";
    } else {
        echo "  ❌ No definition lists found\n";
    }
    echo "\n";

    // Method 2: Headings + Paragraphs
    echo "📋 Method 2: Headings with Question Keywords\n";
    $qaPattern = '/<h[1-6][^>]*>(.*?(?:pitanje|question|Q:|kako|why|zašto|šta|when|where|who).*?)<\/h[1-6]>\s*(?:<[^>]+>)*(.*?)(?=<h[1-6]|<\/div>|<\/article>|$)/is';
    $headingCount = 0;
    if (preg_match_all($qaPattern, $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $question = strip_tags($match[1]);
            $answer = strip_tags($match[2]);
            $answer = preg_replace('/\s+/', ' ', $answer);
            $answer = trim($answer);

            if (strlen($question) > 5 && strlen($answer) > 20) {
                $faqItems[] = [
                    'method' => 'Heading Pattern',
                    'question' => trim($question),
                    'answer' => $answer
                ];
                $headingCount++;
            }
        }
        echo "  ✅ Found $headingCount items\n";
    } else {
        echo "  ❌ No heading patterns found\n";
    }
    echo "\n";

    // Method 3: Bold Q&A
    echo "📋 Method 3: Bold/Strong Q&A Patterns\n";
    $boldQAPattern = '/<(?:strong|b)[^>]*>(.*?(?:pitanje|question|Q:|kako|odgovor|answer|A:).*?)<\/(?:strong|b)>\s*[:\-\s]*([^<]*(?:<(?!(?:strong|b|h[1-6]))[^>]*>[^<]*<\/[^>]+>[^<]*)*)/is';
    $boldCount = 0;
    if (preg_match_all($boldQAPattern, $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $question = strip_tags($match[1]);
            $answer = strip_tags($match[2]);
            $answer = preg_replace('/\s+/', ' ', $answer);
            $answer = trim($answer);

            if (strlen($question) > 5 && strlen($answer) > 15) {
                $faqItems[] = [
                    'method' => 'Bold Pattern',
                    'question' => trim($question),
                    'answer' => $answer
                ];
                $boldCount++;
            }
        }
        echo "  ✅ Found $boldCount items\n";
    } else {
        echo "  ❌ No bold patterns found\n";
    }
    echo "\n";

    // Remove duplicates
    $uniqueFAQs = [];
    $seenQuestions = [];
    foreach ($faqItems as $item) {
        $questionKey = strtolower(trim($item['question']));
        if (!in_array($questionKey, $seenQuestions, true)) {
            $seenQuestions[] = $questionKey;
            $uniqueFAQs[] = $item;
        }
    }

    return $uniqueFAQs;
}

/**
 * Generate Schema.org FAQPage markup
 */
function generateFAQSchema(array $faqItems): array
{
    $schemaItems = [];

    foreach ($faqItems as $item) {
        $schemaItems[] = [
            '@type' => 'Question',
            'name' => $item['question'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $item['answer']
            ]
        ];
    }

    return [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $schemaItems
    ];
}

// Extract FAQ items
$faqItems = extractFAQItems($sampleContent);

// Display results
echo str_repeat("═", 70) . "\n";
echo "📊 EXTRACTION RESULTS\n";
echo str_repeat("═", 70) . "\n\n";

if (empty($faqItems)) {
    echo "❌ No FAQ items found!\n\n";
    exit(1);
}

echo "✅ Found " . count($faqItems) . " FAQ items total\n\n";

foreach ($faqItems as $i => $item) {
    $answerPreview = strlen($item['answer']) > 80
        ? substr($item['answer'], 0, 80) . '...'
        : $item['answer'];

    echo "FAQ #" . ($i + 1) . " [" . $item['method'] . "]\n";
    echo "Q: " . $item['question'] . "\n";
    echo "A: " . $answerPreview . "\n";
    echo str_repeat("─", 70) . "\n";
}

// Generate and display Schema.org markup
echo "\n";
echo str_repeat("═", 70) . "\n";
echo "🔗 GENERATED SCHEMA.ORG MARKUP\n";
echo str_repeat("═", 70) . "\n\n";

$schema = generateFAQSchema($faqItems);
$json = json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

echo $json . "\n\n";

// Statistics
echo str_repeat("═", 70) . "\n";
echo "📈 STATISTICS\n";
echo str_repeat("═", 70) . "\n\n";

$methods = array_count_values(array_column($faqItems, 'method'));
foreach ($methods as $method => $count) {
    echo "  • $method: $count item(s)\n";
}

echo "\n";
echo "✅ Total Questions: " . count($faqItems) . "\n";
echo "✅ Schema Size: " . number_format(strlen($json)) . " bytes\n";

// Validation instructions
echo "\n";
echo str_repeat("═", 70) . "\n";
echo "🔧 VALIDATION\n";
echo str_repeat("═", 70) . "\n\n";

echo "Test your schema at:\n";
echo "  • Google Rich Results: https://search.google.com/test/rich-results\n";
echo "  • Schema.org Validator: https://validator.schema.org/\n\n";

echo "Copy the JSON above and paste it into the validator.\n\n";

// Tips
echo str_repeat("═", 70) . "\n";
echo "💡 TIPS FOR BETTER FAQ CONTENT\n";
echo str_repeat("═", 70) . "\n\n";

echo "1. Use clear question headings with keywords:\n";
echo "   ✅ 'Kako rezervisati?', 'Šta je potrebno?', 'Zašto izabrati nas?'\n";
echo "   ❌ 'Info', 'Detalji', 'Više'\n\n";

echo "2. Provide complete answers (minimum 20-30 characters)\n";
echo "   ✅ Full explanation with context\n";
echo "   ❌ One-word answers\n\n";

echo "3. Aim for 5-10 FAQ items per page\n";
echo "   Too few = weak AI signal\n";
echo "   Too many = performance impact\n\n";

echo "4. Use natural language that people actually search for\n";
echo "   ✅ 'Da li je potrebno iskustvo?'\n";
echo "   ❌ 'Prerequisites for participation'\n\n";

echo "✨ FAQ Schema is ready for AI boost! 🚀\n\n";
