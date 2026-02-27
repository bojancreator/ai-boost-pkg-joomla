---
description: Before pushing to GitHub - run CI checks locally to prevent failures
---

## Pre-Push Checklist (OBAVEZNO)

Svaki put pre `git push origin main`, pokreni ove provjere:

// turbo-all

### 1. PHPCS (Code Style)
```
composer lint
```
Mora završiti sa: `Time: ...` bez ikakvih `ERROR` redova. Nema upozorenja, nema grešaka.

### 2. PHPStan (Static Analysis)
```
composer stan
```
Mora završiti sa: `[OK] No errors`

### 3. Tek onda push
```
git push origin main
```

---

## Poznati problemi i pravila

### PSR-12: Razmaci u header bloku (`SchemaService.php`)
**Problem:** PHPCS zahtijeva praznu liniju između `use` bloka i bilo kojeg koda koji slijedi (komentari, `if (!defined(...))`, itd.).

**❌ Pogrešno:**
```php
use Joomla\Registry\Registry;
// Make sure Joomla constants are available
if (!defined('JPATH_ROOT')) {
```

**✅ Ispravno:**
```php
use Joomla\Registry\Registry;

// Make sure Joomla constants are available
if (!defined('JPATH_ROOT')) {
```

---

### PHPStan: Field klase koje extenduju Joomla klase
**Problem:** `Field/MultiLangTextField` i `Field/MultiLangTextarea` extenduju `Joomla\CMS\Form\FormField` koji nije dostupan u PHPStan analizi bez Joomla vendora.

**Rješenje:** `Field/` direktorij je isključen iz PHPStan analize u `config/phpstan.neon`:
```yaml
excludePaths:
  - ../src/plugins/system/joomlaboost/src/Field
```
**Ne briši ovu liniju!**

---

### PHPStan: `EnvironmentType` enum mora imati sve metode
**Problem:** Klase `DomainDetectionService` i `RobotService` pozivaju metode na `EnvironmentType` enumu. Ako enum nema te metode, PHPStan pada.

**Obavezne metode u `EnvironmentType` enumu:**
- `detectFromDomain(string $domain): self`
- `allowSearchEngines(): bool`
- `isProduction(): bool`
- `getLabel(): string`
- `getRobotsRules(): array`

Ako dodaješ novu upotrebu enuma — dodaj i odgovarajuću metodu!

---

### PHPStan: Neiskorišćeni ignore patternu
**Problem:** PHPStan prijavljuje grešku ako u `phpstan.neon` postoji ignore pattern koji ne odgovara nijednoj grešci.

**Rješenje:** Nakon popravljanja grešaka, ako neka greška više ne postoji — ukloni i njen ignore pattern iz `config/phpstan.neon`.

---

### Autoloader: Novi fajlovi moraju biti registrovani
**Problem:** Svaki novi PHP fajl u `src/Services/` mora biti dodat u `ServiceAutoloader::$classMap`. Svaki novi fajl u `src/Enums/` automatski se učitava po imenu klase (dinamički) — nema potrebe za ručnim upis.

**Provjeri:**
- `src/Services/NovaKlasa.php` → dodaj `'NovaKlasa' => 'NovaKlasa.php'` u `ServiceAutoloader.php`
- `src/Enums/NoviEnum.php` → automatski, ništa ne trebaš dodavati
