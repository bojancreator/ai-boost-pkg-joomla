# OpenGraph Intro Image Fix - Complete Session

**Datum**: 21-24. novembar 2025
**Problem**: OpenGraph ne prikazuje intro slike članaka, nego default logo
**Status**: ✅ **REŠENO** u v0.1.63, production release v0.1.64

---

## 🐛 Problem

### Simptomi

- **OpenGraph meta tag**: `<meta property="og:image" content="https://staging.offroadserbia.com/images/LOGO-SERBIA-CREW.png">` ❌ (prikazuje logo)
- **Schema.org JSON-LD**: `"thumbnailUrl":"images/intro-images/deliblato-mart2020-mala.jpg"` ✅ (prikazuje intro sliku)
- **Test URL**: https://staging.offroadserbia.com/clanstvo (Article ID 67)

### Dijagnoza

`SchemaService` **USPEŠNO** izvlači intro sliku iz baze, ali `OpenGraphService` **NE USPEVA**.

---

## 🔍 Debugging Hronologija

### v0.1.45-0.1.52: Početna istraživanja

**Hipoteza**: SQL query ne vraća podatke
**Akcije**:

- Dodavano `error_log()` debug
- Uklonjen `WHERE published = 1` uslov
- Dodato 6 varijanti imena `images` polja

**Rezultat**: Nisu pronađeni problemi u SQL query strukturi

---

### v0.1.53-0.1.55: Flow tracking

**Hipoteza**: Metoda `getArticleImage()` se ne izvršava
**Akcije**:

- Dodati HTML echo debug komentari kroz ceo execution flow
- Potvrđeno da se `onBeforeCompileHead` → `addOpenGraphTags` → `addArticleOpenGraphTags` → `getArticleImage()` pozivaju

**Debug output**:

```html
<!-- JB DEBUG OG: option=com_content, view=article, needsHeavy=YES -->
<!-- JB DEBUG: Entering addArticleOpenGraphTags -->
<!-- JB DEBUG: getArticleImage() called -->
<!-- JB DEBUG: articleId = 67 -->
<!-- JB DEBUG: Checking custom_og_image field... -->
<!-- JB DEBUG: No custom_og_image, proceeding to SQL query -->
```

**Rezultat**: Metoda SE izvršava, ali execution **ZAUSTAVLJA POSLE SQL query linije**

---

### v0.1.56-0.1.57: SQL Execution Debugging

**Hipoteza**: SQL query baca exception koji try-catch guta
**Akcije v0.1.56**:

- Dodati debug POSLE `$db->setQuery()` i `$db->loadObject()`
- Debug output: **ISTI** - zaustavlja se na istom mestu

**Akcije v0.1.57**:

- **STAVLJEN EKSPLICITNI TRY-CATCH** oko SQL query-a sa exception logging
- Dodati debug na svakom koraku:
  - "About to get DBO instance..."
  - "DBO instance obtained"
  - "Query built"
  - "SQL query: [tekst query-a]"
  - "loadObject() executed"

**Debug output v0.1.57**:

```html
<!-- JB DEBUG: No custom_og_image, proceeding to SQL query -->
<!-- JB DEBUG: About to get DBO instance... -->
<!-- JB DEBUG: DBO instance obtained -->
<!-- JB DEBUG: Query built -->
<!-- JB DEBUG: SQL EXCEPTION: You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near 'fulltext FROM d2nrb_content WHERE id = 67' at line 1 at line 138 -->
```

### 🎯 **PRONAĐEN UZROK!**

---

## ✅ Rešenje v0.1.58

### Problem

```sql
SELECT images, introtext, fulltext FROM d2nrb_content WHERE id = 67
```

**`fulltext` je rezervisana reč u MariaDB/MySQL!**
SQL parser interpretira `fulltext` kao SQL keyword umesto imena kolone.

### Fix

```php
// ❌ STARI KOD (v0.1.45-0.1.57)
$query = $db->getQuery(true)
    ->select('images, introtext, fulltext')
    ->from('#__content')
    ->where('id = ' . (int) $articleId);

// ✅ NOVI KOD (v0.1.58+)
$query = $db->getQuery(true)
    ->select($db->quoteName(['images', 'introtext', 'fulltext']))
    ->from($db->quoteName('#__content'))
    ->where($db->quoteName('id') . ' = ' . (int) $articleId);
```

**Generisani SQL sa backticks**:

```sql
SELECT `images`, `introtext`, `fulltext` FROM `d2nrb_content` WHERE `id` = 67
```

### Izmene u v0.1.58

1. **OpenGraphService.php (linija 327-333)**: Korišćenje `$db->quoteName()` za sve kolone i tabele
2. Uklonjeni svi debug HTML echo komentari (očišćen kod)
3. Ostavljeni samo `error_log()` debug pozivi (ne ometaju production)

**Build**: `joomlaboost-0.1.58.zip` (42.7 KB)

---

## ❓ v0.1.59: Verification Debug

**Status**: **NE TESTIRAN JOŠ**

### Zašto v0.1.59?

Nakon instalacije v0.1.58, OpenGraph **I DALJE prikazuje logo** umesto intro slike.
Schema.org thumbnailUrl **JE ISPRAVAN** (pokazuje intro sliku).

### Hipoteze

1. **Cache**: Joomla/plugin keširaju staru vrednost OG image meta taga
2. **Priority logic**: `addFallbackOpenGraphImage()` dodaje logo POSLE što `getArticleImage()` vrati prazan string
3. **JSON decode fail**: `$article->images` polje možda nije pravilno dekodirano
4. **Execution path**: `getArticleImage()` možda UOPŠTE NE VRAĆA vrednost do `addArticleOpenGraphTags()`

### Dodati debug u v0.1.59

```php
echo "<!-- DEBUG: getArticleImage() START -->\n";
echo "<!-- DEBUG: articleId = $articleId -->\n";
echo "<!-- DEBUG: SQL query built and set -->\n";
echo "<!-- DEBUG: Article loaded: " . ($article ? 'YES' : 'NULL') . " -->\n";
echo "<!-- DEBUG: article->images value: " . htmlspecialchars($article->images ?? 'NULL') . " -->\n";
```

**Build**: `joomlaboost-0.1.59.zip` (42.9 KB) - **SPREMNA ZA TEST**

---

## 📊 Comparison: SchemaService vs OpenGraphService

### SchemaService (RADI) - extractImagesOptimized()

```php
if (!empty($article->images)) {
    $articleImages = json_decode($article->images, true);
    if (is_array($articleImages)) {
        if (!empty($articleImages['image_intro'])) {
            $images[] = $this->normalizeImageUrl($articleImages['image_intro']);
        }
    }
}
```

**Rezultat**: `"thumbnailUrl":"images/intro-images/deliblato-mart2020-mala.jpg"` ✅

### OpenGraphService (NE RADI) - getArticleImage()

```php
$db = Factory::getDbo();
$query = $db->getQuery(true)
    ->select($db->quoteName(['images', 'introtext', 'fulltext']))  // v0.1.58 fix
    ->from($db->quoteName('#__content'))
    ->where($db->quoteName('id') . ' = ' . (int) $articleId);

$db->setQuery($query);
$article = $db->loadObject();

if (!$article) {
    return '';
}

// Try to extract from images JSON (Priority 2)
error_log("JB DEBUG - Article $articleId images raw: " . var_export($article->images, true));

if (!empty($article->images)) {
    $images = json_decode($article->images, true);
    // ... field checking logic ...
}
```

**Rezultat**: `<meta property="og:image" content=".../LOGO-SERBIA-CREW.png">` ❌

---

## 🔧 Next Steps

### Testiranje v0.1.59

1. Instaliraj `joomlaboost-0.1.59.zip` na staging
2. **OBAVEZNO**: System → Clear Cache
3. **OBAVEZNO**: Hard refresh u browseru (Ctrl+Shift+R)
4. Proveri Extensions → Plugins → JoomlaBoost - verzija **0.1.59**
5. Idi na: https://staging.offroadserbia.com/clanstvo
6. View page source (Ctrl+U)
7. Traži `<!-- DEBUG:` komentare
8. Kopiraj SVE debug linije i OG image meta tag

### Očekivani debug output

```html
<!-- DEBUG: getArticleImage() START -->
<!-- DEBUG: articleId = 67 -->
<!-- DEBUG: SQL query built and set -->
<!-- DEBUG: Article loaded: YES -->
<!-- DEBUG: article->images value: {"image_intro":"images/intro-images/deliblato-mart2020-mala.jpg",...} -->
```

**Ako nema debug-a**: Cache problem ili stara verzija instalirana
**Ako debug pokazuje prazan images**: Treba proveriti drugi article
**Ako debug pokazuje podatke**: Problem je u JSON decode ili field extraction logici

---

## 📝 Files Modified

### v0.1.45 → v0.1.58

- `src/plugins/system/joomlaboost/src/Services/OpenGraphService.php`
  - Linija 327-333: SQL query sa `quoteName()`
  - Uklonjen debug (sve echo linije)

### v0.1.59 (current)

- `src/plugins/system/joomlaboost/src/Services/OpenGraphService.php`
  - Linija 307-342: Debug echo linije dodane ponovo
- `src/plugins/system/joomlaboost/joomlaboost.xml`
  - Linija 10: Version 0.1.59

---

## 🚀 Git Commit Plan

```bash
git add docs/OPENGRAPH-FIX-SESSION.md
git add src/plugins/system/joomlaboost/src/Services/OpenGraphService.php
git add src/plugins/system/joomlaboost/joomlaboost.xml
git commit -m "fix(opengraph): resolve SQL syntax error with fulltext reserved word (v0.1.58-0.1.59)

- Fixed SQL query in OpenGraphService::getArticleImage() using quoteName()
- Resolved MariaDB/MySQL reserved word conflict with 'fulltext' column
- Added debug output in v0.1.59 to trace article image extraction
- SchemaService successfully extracts intro images, OpenGraph still needs verification
- Test article: https://staging.offroadserbia.com/clanstvo (ID 67)

Related: #opengraph-intro-image-bug
"
```

---

## 📚 Reference

- **Test Article**: https://staging.offroadserbia.com/clanstvo (ID 67)
- **Expected Intro Image**: `images/intro-images/deliblato-mart2020-mala.jpg`
- **Current OG Image**: `https://staging.offroadserbia.com/images/LOGO-SERBIA-CREW.png` (logo - NETAČNO)
- **MariaDB Reserved Words**: https://mariadb.com/kb/en/reserved-words/
- **Joomla quoteName() docs**: https://docs.joomla.org/Using_the_query_API

---

**VAŽNO**: Nemoj zaboraviti da **očistiš Joomla cache** i **hard refresh browser** posle instalacije v0.1.59!
