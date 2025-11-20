# Debug Mode Guide - JoomlaBoost v0.1.39+

## Šta je Debug Mode?

Debug Mode omogućava **detaljno praćenje** rada JoomlaBoost plugina kroz **vizuelne poruke** u Joomla admin interfejsu. Koristan za:
- Dijagnostiku problema
- Proveru da li plugin pravilno detektuje sadržaj
- Praćenje generisanja meta tagova, Schema.org, OpenGraph
- Verifikaciju konfiguracije

---

## Kako aktivirati Debug Mode?

### 1. Idi u Plugin Settings
```
Joomla Admin → Extensions → Plugins → System - JoomlaBoost → Debug Settings
```

### 2. Omogući debug_mode
- **Debug Mode**: YES
- **Debug Wrap Markers** (opciono): YES (dodaje HTML komentare oko generisanog sadržaja)

### 3. Sačuvaj i primeni
Klikni **Save & Close**

---

## Gde se prikazuju debug poruke?

### Lokacija: Joomla Admin Dashboard
Po **osvežavanju bilo koje stranice** (frontend ili admin), debug poruke se pojavljuju kao:
- **Info notifikacije** na vrhu Joomla admin panela
- Plave ili zelene poruke sa prefiksom `[JoomlaBoost]`

**VAŽNO**: Poruke se pojavljuju **nakon akcije** - potrebno je da:
1. Obaviš radnju (npr. poseta artikla)
2. Odeš u admin panel
3. Tamo ćeš videti debug output

---

## Primeri debug poruka

### Primer 1: OpenGraph generisanje
```
[JoomlaBoost] OpenGraph meta tags added | {"title":"Test Article","image":"https://domain.com/images/test.jpg","type":"article"}
```

### Primer 2: Schema.org detektovanje FAQ-a
```
[JoomlaBoost] FAQ Schema generated from Accordions & Collapses | {"questions":3,"source":"auto-detection"}
```

### Primer 3: Domain detekcija
```
[JoomlaBoost] Domain detected | {"detected":"staging.offroadserbia.com","environment":"staging","active_match":true}
```

---

## Debug poruke po servisima

### SchemaService (JSON-LD structured data)
- `Schema markup added for: Article`
- `FAQ Schema generated from Accordions & Collapses`
- `Organization Schema with logo injected`

### OpenGraphService (Facebook/Twitter meta tags)
- `OpenGraph meta tags added`
- `Custom OpenGraph fields used from article`
- `Image normalized: removed Joomla fragments`

### RobotService (robots.txt)
- `Robots.txt generated for: production`
- `Disallow rules: staging environment detected`

### SitemapService (XML sitemaps)
- `Sitemap index generated with 2 sub-sitemaps`
- `Articles sitemap: 25 items`

### DomainDetectionService (environment detection)
- `Domain detected: staging.offroadserbia.com`
- `Environment: STAGING`
- `Active match: true`

---

## Troubleshooting

### Problem: Ne vidim debug poruke
**Rešenje**:
1. Proveri da li je `debug_mode` postavljen na **YES** u plugin settings
2. **Obavezno osveži stranicu** nakon omogućavanja
3. **Poseti admin panel** - poruke se ne prikazuju na frontendu
4. Proveri da li plugin ima `active_domain` podešen na trenutni domain

### Problem: Poruke se prikazuju samo jednom
**Objašnjenje**: 
- Joomla message queue pokazuje poruke **samo nakon akcije**
- Poruke nestaju nakon što ih pročitaš
- **Normalno ponašanje** - ne bug

**Rešenje**:
- Ponovi akciju (npr. osveži artikal)
- Vrati se u admin - videćeš nove poruke

### Problem: Previše poruka
**Rešenje**:
1. Ostavi `debug_mode` na **YES**
2. Postavi `debug_wrap_markers` na **NO** (smanjuje broj poruka)
3. Debug je namenjen za development - isključi ga u produkciji

---

## Debug Wrap Markers

### Šta su Wrap Markers?
HTML komentari koji okružuju generisani sadržaj, olakšavaju lociranje u source kodu.

### Primer (debug_wrap_markers = YES):
```html
<!-- [JoomlaBoost] OpenGraph Start -->
<meta property="og:title" content="Test Article" />
<meta property="og:type" content="article" />
<!-- [JoomlaBoost] OpenGraph End -->

<!-- [JoomlaBoost] Schema.org Start -->
<script type="application/ld+json">
{"@context":"https://schema.org",...}
</script>
<!-- [JoomlaBoost] Schema.org End -->
```

### Kada koristiti?
- Development: **YES** (olakšava debugging)
- Production: **NO** (čisti HTML output)

---

## Best Practices

### 1. Razvoj (Development)
```
debug_mode = YES
debug_wrap_markers = YES
```
- Omogući detaljno logovanje
- Koristi wrap markers za identifikaciju
- Proveravaj admin panel nakon svake akcije

### 2. Staging (Testing)
```
debug_mode = YES
debug_wrap_markers = NO
```
- Omogući debug za testiranje
- Bez wrap markers (testing real output)
- Testiraj pre deploy-a u produkciju

### 3. Produkcija (Production)
```
debug_mode = NO
debug_wrap_markers = NO
```
- **OBAVEZNO isključi debug** u produkciji
- Debug poruke troše resurse
- Nepotrebne informacije za end-usere

---

## Changelog v0.1.39 - Debug Unification

### Problem u v0.1.38
- **Dual logging sistem**: Plugin koristio `enqueueMessage()`, servisi `JLog::add()`
- Debug poruke završavale u **log fajlovima** umesto UI-u
- Korisnici nisu znali **gde da traže** debug output

### Rešenje u v0.1.39
- **Unifikovan sistem**: Svi servisi koriste `enqueueMessage()`
- **Konzistentna vidljivost**: Sve debug poruke u Joomla admin message area
- **Method safety**: Dodata provera `method_exists()` za CLI compatibility

### Tehnički detalji
**File**: `plugin/src/Services/AbstractService.php`

**Izmena**:
```php
// BEFORE (v0.1.38) - Log file
\JLog::add($logMessage, \JLog::DEBUG, 'joomlaboost');

// AFTER (v0.1.39) - UI messages
$app = Factory::getApplication();
if (method_exists($app, 'enqueueMessage')) {
    $app->enqueueMessage($logMessage, 'info');
}
```

**Dodati import**:
```php
use Joomla\CMS\Factory;
```

---

## Napredne tehnike

### 1. Filtriranje poruka po servisu
Debug poruke sadrže **service identifikator** u JSON context-u:

```
[JoomlaBoost] Action completed | {"service":"OpenGraphService","items":5}
```

### 2. Parsiranje JSON context-a
Svaka poruka ima **Context JSON** sa detaljima:
```json
{
  "service": "SchemaService",
  "type": "Article",
  "properties": ["headline", "author", "datePublished"]
}
```

### 3. Diagnostic Endpoint
Kombinuj debug sa diagnostics endpoint-om:
```
https://staging.offroadserbia.com/index.php?jb_diag=1
```

Pokazuje:
- Plugin status
- Active domain match
- Enabled services
- Configuration overview

---

## Reference

**Docs**:
- `docs/ENDPOINTS.md` - Diagnostic endpoint spec
- `docs/TROUBLESHOOTING.md` - General troubleshooting

**Config Files**:
- `plugin/joomlaboost.xml` (lines 391-413) - Debug field definitions
- `plugin/src/Services/AbstractService.php` (lines 188-212) - Debug implementation

**Git**:
- Commit `f43488f` - v0.1.39 debug unification fix
- Previous: `657ff48` - v0.1.38 validation testing

---

## FAQ

**Q: Zašto se poruke ne prikazuju u real-time?**  
A: Joomla message queue radi **nakon akcije** - refresh page → go to admin → see messages.

**Q: Mogu li debug koristiti u CLI?**  
A: Ne, `enqueueMessage()` radi samo u web context-u. CLI ignoriše debug poruke (safety check).

**Q: Kako resetovati debug poruke?**  
A: Poruke automatski nestaju nakon čitanja. Za nove poruke - osveži stranicu.

**Q: Debug utiče na performance?**  
A: Da, minimalno. Debug proverava `debug_mode` parametar i formatira JSON. **Isključi u produkciji**.

**Q: Mogu li eksportovati debug log?**  
A: Ne direktno. Debug poruke nisu persistent. Za trajno logovanje, koristi Joomla System Log ili install monitoring tool.

---

## Support

Prijavite problem:
- **GitHub**: https://github.com/bojancreator/JoomlaBoost/issues
- **Email**: info@joomlaboost.com
- **Docs**: `docs/TROUBLESHOOTING.md`

---

**Verzija**: JoomlaBoost v0.1.39+  
**Datum**: November 2025  
**Autor**: JoomlaBoost Team
