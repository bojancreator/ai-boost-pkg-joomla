# 🤖 FAQ Schema - AI Optimization Guide

## 📋 Šta je FAQ Schema?

FAQ Schema je **Schema.org FAQPage markup** koji pomaže AI sistemima (ChatGPT, Perplexity, Google AI) da **lakše razumeju i citiraju** tvoj sadržaj u svojim odgovorima.

### Zašto je Važan za AI?

✅ **AI sistemi obožavaju strukturirane Q&A podatke**
✅ **Često prikazuju FAQ-ove direktno u odgovorima**
✅ **Povećava šanse da te AI preporuči**
✅ **Poboljšava trust score tvog sadržaja**

---

## 🎯 Kako Plugin Automatski Detektuje FAQ-ove

JoomlaBoost plugin **automatski skenira** tvoj sadržaj i prepoznaje FAQ pattern na **3 načina**:

### **Metod 1: Definition Liste** (`<dt>/<dd>`)

```html
<dl>
  <dt>Pitanje: Da li je potrebno iskustvo u vožnji 4x4?</dt>
  <dd>Ne, nije potrebno prethodno iskustvo. Naš tim će vas obučiti.</dd>

  <dt>Koliko traje prosečna tura?</dt>
  <dd>Prosečna tura traje između 4 i 8 sati.</dd>
</dl>
```

**Rezultat:**

```json
{
  "@type": "Question",
  "name": "Pitanje: Da li je potrebno iskustvo u vožnji 4x4?",
  "acceptedAnswer": {
    "@type": "Answer",
    "text": "Ne, nije potrebno prethodno iskustvo. Naš tim će vas obučiti."
  }
}
```

---

### **Metod 2: Heading + Paragraf Pattern**

```html
<h3>Kako rezervisati mesto na turi?</h3>
<p>
  Rezervaciju možete izvršiti putem naše kontakt forme, telefonom ili direktno
  putem email-a.
</p>

<h3>Šta treba poneti na off-road turu?</h3>
<p>
  Preporučujemo sportsku odeću, udobne cipele, zaštitu od sunca, vodu i
  fotoaparat.
</p>
```

**Plugin prepoznaje keywords:**

- `pitanje`, `question`, `Q:`
- `kako`, `why`, `zašto`, `šta`
- `when`, `where`, `who`

---

### **Metod 3: Bold/Strong Q&A Pattern**

```html
<strong>Q: Da li su deca dobrodošla na turama?</strong>
<p>A: Apsolutno! Porodične ture su posebno dizajnirane za sigurnost dece.</p>

<strong>Pitanje: Koliko košta tura?</strong>
<p>Cene variraju od 50€ do 150€ po osobi, u zavisnosti od destinacije.</p>
```

---

## ⚙️ Konfiguracija u Plugin Parametrima

### Trenutno Dostupno

FAQ Schema je **automatski omogućen** u SchemaService-u:

```php
// U SchemaService.php
private function shouldGenerateFAQSchema(): bool
{
    // Check if FAQ generation is enabled
    if (!$this->params->get('faq_schema_enabled', true)) {
        return false;
    }

    // Only for content pages (articles & categories)
    return in_array($view, ['article', 'category'], true);
}
```

### Kako Omogućiti/Onemogućiti

Trenutno nema GUI opcije, ali možeš dodati u `joomlaboost.xml`:

```xml
<field
    name="faq_schema_enabled"
    type="radio"
    label="FAQ Schema Enabled"
    description="Automatically generate FAQ schema from Q&A content"
    default="1"
    class="btn-group btn-group-yesno"
>
    <option value="1">JYES</option>
    <option value="0">JNO</option>
</field>
```

---

## 🧪 Testiranje FAQ Schema

### **Lokalno Testiranje (Content Mockup)**

```bash
# Test sa sample content
php tools/test-faq-schema.php
```

### **Live Testiranje (Stvarni Article)**

```bash
# Test sa konkretnim article ID-jem
php tools/test-faq-schema.php --article-id=123
```

### **Staging Site Provera**

```bash
# Proveri da li FAQ schema radi na staging sajtu
php tools/test-faq-schema.php
```

**Output će ti pokazati:**

- ✅ Koliko FAQ-ova je pronađeno
- ✅ Koju metodu je koristio za extraction
- ✅ Generisani Schema.org JSON-LD markup
- ✅ Link za validaciju

---

## ✅ Validacija Schema Markup-a

### **Google Rich Results Test**

1. Otvori: https://search.google.com/test/rich-results
2. Unesi URL tvog članka sa FAQ sadržajem
3. Proveri da li je `FAQPage` prepoznat
4. Proveri da li su sva pitanja vidljiva

### **Schema.org Validator**

1. Otvori: https://validator.schema.org/
2. Kopiraj generisani JSON-LD kod
3. Paste u validator
4. Proveri validnost

---

## 📝 Best Practices za FAQ Sadržaj

### **1. Koristi Jasne Naslove Pitanja**

❌ **Loše:**

```html
<h3>Info o cenama</h3>
```

✅ **Dobro:**

```html
<h3>Koliko košta off-road tura?</h3>
```

### **2. Odgovori Treba Da Budu Kompletni**

❌ **Loše:**

```html
<p>Zavisi.</p>
```

✅ **Dobro:**

```html
<p>
  Cene variraju od 50€ do 150€ po osobi, u zavisnosti od destinacije, trajanja
  ture i broja učesnika. Porodične popuste možete dobiti za grupe veće od 4
  osobe.
</p>
```

### **3. Minimum 3-5 FAQ-ova po Stranici**

- Premalo pitanja → slabiji signal za AI
- Previše pitanja (>20) → plugin automatski limitira

### **4. Koristi Prirodan Jezik**

Piši kao da razgovaraš sa kupcem:

- "Kako mogu rezervisati?"
- "Šta je uključeno u cenu?"
- "Da li je potrebno iskustvo?"

---

## 🚀 Advanced: Ručno Dodavanje FAQ Schema

Ako želiš **punu kontrolu**, možeš i ručno dodati schema u article:

```html
<script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "FAQPage",
    "mainEntity": [
      {
        "@type": "Question",
        "name": "Koliko traje off-road tura?",
        "acceptedAnswer": {
          "@type": "Answer",
          "text": "Prosečna tura traje između 4 i 8 sati, u zavisnosti od izabrane destinacije i nivoa težine."
        }
      },
      {
        "@type": "Question",
        "name": "Da li je potrebno iskustvo?",
        "acceptedAnswer": {
          "@type": "Answer",
          "text": "Ne, nije potrebno prethodno iskustvo. Naš tim iskusnih vodičeva će vas provesti kroz sve tehnike."
        }
      }
    ]
  }
</script>
```

**Ali to NE treba** - plugin to radi automatski! 😎

---

## 📊 Performance Optimizacije

Plugin koristi **request-level caching** za FAQ schema:

```php
// Schema se generiše samo jednom po request-u
$cacheKey = 'schema_' . $perfService->getPageCacheKey();
if ($perfService->cacheHas($cacheKey)) {
    return $perfService->cacheGet($cacheKey);
}
```

**Benefits:**

- ✅ Nema duplicate database queries
- ✅ Brže generisanje stranice
- ✅ Manji memory footprint

---

## 🎯 AI Optimization Tips

### **Što Više Strukture, To Bolje**

AI sistemi preferiraju:

1. **Definition lists** - najlakše za parsiranje
2. **Semantic HTML** - `<article>`, `<section>`, `<aside>`
3. **Clear headings** - H2/H3 sa pitanjima

### **Koristi Long-Tail Keywords**

AI često traži specifične odgovore:

❌ Generičko: "Ture"
✅ Specifično: "Kakve vrste off-road tura nudite u Srbiji?"

### **Update Često**

AI preferira **fresh content**:

- Dodaj novi FAQ jednom mesečno
- Update postojeće odgovore sa novim info
- Plugin automatski detektuje `dateModified`

---

## 🐛 Troubleshooting

### **FAQ Schema Se Ne Generiše**

**Proveri:**

1. Da li je `faq_schema_enabled = true` u params?
2. Da li je sadržaj article/category?
3. Da li ima keywords (`kako`, `pitanje`, itd.)?
4. Da li su pitanja i odgovori dovoljno dugi?

### **Samo Neki FAQ-ovi Se Ekstraktuju**

**Razlozi:**

- Previše kratka pitanja (<5 chars)
- Previše kratki odgovori (<10-20 chars)
- Nisu prepoznati keywords
- Limit od 20 FAQ-ova

### **Validacija Ne Prolazi**

**Česte greške:**

- Missing `acceptedAnswer`
- Empty question `name`
- Invalid JSON escaping

**Proveri output:**

```bash
php tools/test-faq-schema.php --article-id=YOUR_ID
```

---

## 📈 Monitoring & Analytics

### **Kako Pratiti AI Impact**

1. **Google Search Console**

   - Prati "FAQ" rich results
   - Gledaj impressions za FAQ featured snippets

2. **Traffic Analytics**

   - Prati organic traffic na FAQ stranice
   - Gledaj bounce rate (niži = bolje)

3. **AI Citations**
   - Manuelno testiraj: "best off-road tours serbia" u ChatGPT
   - Proveri da li te ChatGPT/Perplexity spominju

---

## 🎓 Resources

- **Schema.org FAQPage**: https://schema.org/FAQPage
- **Google Guidelines**: https://developers.google.com/search/docs/appearance/structured-data/faqpage
- **Rich Results Test**: https://search.google.com/test/rich-results

---

## ✨ Zaključak

FAQ Schema je **brz i efikasan način** da boost-uješ AI vidljivost. Plugin **automatski radi sve** - ti samo napiši dobar Q&A sadržaj! 🚀

**Sledeći koraci:**

1. ✅ Napiši FAQ sadržaj na bitnim stranicama
2. ✅ Testiraj sa `test-faq-schema.php`
3. ✅ Validiraj markup
4. ✅ Prati AI impact

**Happy FAQ optimizing!** 🎯
