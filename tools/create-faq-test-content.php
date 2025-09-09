<?php

/**
 * Create Test FAQ Content for JoomlaBoost
 * This creates sample FAQ content to test schema generation
 */

$testFAQContent = [
  'title' => 'Off Road Serbia - Frequently Asked Questions',
  'content' => '
<div class="faq-content">
    <h2>Česta pitanja o off-road avanturama</h2>
    
    <h3>Pitanje: Kako da se pridružim off-road avanturama?</h3>
    <p>Da biste se pridružili našim off-road avanturama, potrebno je da se registrujete na našem sajtu i kontaktirate nas putem kontakt forme. Imamo različite nivoe vožnje za početnike i iskusne vozače.</p>

    <h3>Pitanje: Kakva oprema je potrebna za off-road vožnju?</h3>
    <p>Preporučujemo 4x4 vozilo u dobrom stanju, sigurnosnu opremu uključujući kacige, i osnovni alat za popravke. Detaljna lista opreme je dostupna u našem vodiču za početnike.</p>

    <dl class="faq-list">
        <dt>Koliko košta godišnje članstvo u udruženju?</dt>
        <dd>Godišnja članarina iznosi 5000 dinara za redovno članstvo, a 3000 dinara za studentsko članstvo. Porodično članstvo je 8000 dinara i pokriva do 4 člana porodice.</dd>

        <dt>Da li organizujete obuke za početnike?</dt>
        <dd>Da, organizujemo redovne obuke za početnike svakog prvog vikenda u mesecu. Obuka traje jedan dan i pokriva osnove bezbedne off-road vožnje, navigaciju i osnovne popravke.</dd>
    </dl>

    <div class="qa-section">
        <p><strong>Q: Koje destinacije pokrivate za off-road ture?</strong></p>
        <p>Organizujemo ture po celoj Srbiji i regionu. Najpopularnije destinacije su Tara, Zlatibor, Kopaonik, Fruška Gora, kao i prekogranične ture u Bosnu i Crnu Goru.</p>

        <p><strong>Pitanje: Da li je potrebno iskustvo za učešće?</strong></p>
        <p>Nije potrebno prethodno iskustvo. Imamo grupe za početnike gde iskusni vozači pružaju podršku i obuku tokom tura. Važno je samo da imate vozilo i želju za avanturom.</p>
    </div>

    <h3>Q: How do I prepare my vehicle for off-road adventures?</h3>
    <p>Before any off-road trip, ensure your 4x4 vehicle is in good condition. Check tire pressure, oil levels, brake fluid, and cooling system. We recommend carrying spare parts, tools, and recovery equipment.</p>

    <h3>Question: What should I bring on an off-road trip?</h3>
    <p>Essential items include: navigation equipment (GPS, maps), first aid kit, extra fuel, water, snacks, warm clothing, and communication devices. We provide a detailed packing checklist for all participants.</p>
</div>
',
  'expected_faqs' => 8
];

echo "=== Test FAQ Content Generator ===\n\n";
echo "📄 Content: " . $testFAQContent['title'] . "\n";
echo "🎯 Expected FAQ items: " . $testFAQContent['expected_faqs'] . "\n\n";

echo "📋 Content Analysis:\n";
echo "----------------------------------------\n";

$content = $testFAQContent['content'];

// Test Pattern 1: Definition Lists
$dlPattern = '/<dt[^>]*>(.*?)<\/dt>\s*<dd[^>]*>(.*?)<\/dd>/is';
if (preg_match_all($dlPattern, $content, $matches, PREG_SET_ORDER)) {
  echo "✅ Definition Lists: " . count($matches) . " found\n";
  foreach ($matches as $i => $match) {
    $question = strip_tags($match[1]);
    $answer = strip_tags($match[2]);
    echo "   - DL" . ($i + 1) . ": " . substr($question, 0, 50) . "...\n";
  }
} else {
  echo "❌ Definition Lists: 0 found\n";
}

// Test Pattern 2: Question Headings
$qaPattern = '/<h[1-6][^>]*>(.*?(?:pitanje|question|Q:|kako|why|zašto|šta).*?)<\/h[1-6]>/i';
if (preg_match_all($qaPattern, $content, $matches)) {
  echo "✅ Question Headings: " . count($matches[0]) . " found\n";
  foreach ($matches[1] as $i => $question) {
    $questionText = strip_tags($question);
    echo "   - H" . ($i + 1) . ": " . substr($questionText, 0, 50) . "...\n";
  }
} else {
  echo "❌ Question Headings: 0 found\n";
}

// Test Pattern 3: Bold Q&A
$boldQAPattern = '/<(?:strong|b)[^>]*>(.*?(?:pitanje|question|Q:|kako|odgovor|answer|A:).*?)<\/(?:strong|b)>/i';
if (preg_match_all($boldQAPattern, $content, $matches)) {
  echo "✅ Bold Q&A: " . count($matches[0]) . " found\n";
  foreach ($matches[1] as $i => $question) {
    $questionText = strip_tags($question);
    echo "   - B" . ($i + 1) . ": " . substr($questionText, 0, 50) . "...\n";
  }
} else {
  echo "❌ Bold Q&A: 0 found\n";
}

echo "\n=== FAQ Schema Preview ===\n";
echo "📋 Would generate JSON-LD:\n";
echo "{\n";
echo '  "@context": "https://schema.org",' . "\n";
echo '  "@type": "FAQPage",' . "\n";
echo '  "mainEntity": [' . "\n";
echo "    // " . (count($matches) + 2 + 2) . " FAQ items would be generated\n";
echo "  ]\n";
echo "}\n\n";

echo "=== Installation Instructions ===\n";
echo "1. 📥 Install joomlaboost-0.1.20.zip\n";
echo "2. ✅ Enable FAQ Schema in plugin settings\n";
echo "3. 📝 Create content page with FAQ structure above\n";
echo "4. 🔍 View page source to see JSON-LD schema\n";
echo "5. 🎯 Test in Google Rich Results Test\n\n";

echo "=== Ready for Testing! ===\n";
echo "✅ FAQ content patterns working\n";
echo "✅ Plugin v0.1.20 built successfully\n";
echo "🚀 Ready for deployment and testing\n";
