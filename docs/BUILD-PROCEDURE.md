12122212# JoomlaBoost Plugin Build Procedure

## 🎯 OBAVEZNA PROCEDURA PRIJE BUILD-a

### ✅ Pre-Build Checklist

**1. XML Manifest Provjera (`joomlaboost.xml`):**

```xml
<files>
    <filename plugin="joomlaboost">joomlaboost.php</filename>
    <folder>language</folder>
    <folder>src</folder>         <!-- MORA postojati! -->
    <folder>media</folder>       <!-- MORA postojati! -->
</files>
```

**2. Syntax Check - Obavezni fajlovi:**

- `joomlaboost.php` - glavni plugin
- `joomlaboost-simple.php` - samo ako se koristi
- Svi PHP fajlovi u `src/Services/`

**3. Schema Service Requirements:**

```php
// U glavnom plugin fajlu MORA postojati:
require_once __DIR__ . '/src/Services/ServiceInterface.php';
require_once __DIR__ . '/src/Services/AbstractService.php';
require_once __DIR__ . '/src/Services/SchemaService.php';

use JoomlaBoost\Plugin\System\JoomlaBoost\Services\SchemaService;

// Property:
private ?SchemaService $schemaService = null;

// Methods:
public function onBeforeCompileHead(): void
private function addSchemaMarkup($document): void
```

### 🔍 Česti problemi i rešenja

#### Problem 1: "Failed opening required ServiceInterface.php"

**Uzrok:** `<folder>src</folder>` nedostaje u XML
**Rešenje:** Dodaj u `<files>` sekciju XML-a

#### Problem 2: Sintaksne greške u JSON-LD generaciji

**Uzrok:** Pogrešan operator `.=` umesto `$jsonLd .=`
**Rešenje:**

```php
$jsonLd = '<script type="application/ld+json">' . "\n";
$jsonLd .= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$jsonLd .= "\n" . '</script>';
```

#### Problem 3: Schema se ne generiše

**Uzrok:** `onBeforeCompileHead()` metoda ne postoji ili se ne poziva
**Rešenje:** Dodaj metodu u glavni plugin fajl

#### Problem 4: Verzija se ne update-uje

**Uzrok:** Build script čita verziju iz XML, ignoriše parametre
**Rešenje:** Prvo promeni verziju u XML, onda pokreni build

### 📋 Build Steps - REDOSLED OPERACIJA

**1. Update Version:**

```xml
<!-- U joomlaboost.xml -->
<version>X.Y.Z-description</version>
```

**🐛 Verzioniranje za Debug:**

- Mikro verzije (0.1.7 → 0.1.8) za debug info
- Dodavanje debug informacija u backend i frontend log
- Verzija se prikazuje u debug porukama i generated fajlovima
- Primer: `v0.1.8-version-debug` za dodavanje debug informacija

**2. Syntax Validation:**

```bash
# Provjeri sintaksu svih PHP fajlova
php -l joomlaboost.php
php -l joomlaboost-simple.php
php -l src/Services/*.php
```

**3. Build Command:**

```powershell
cd "c:\POSLOVI\__JoomlaBoost\tools"
pwsh -NoProfile -ExecutionPolicy Bypass -File ".\build_joomlaboost.ps1"
```

**4. Verification:**

- Verify ZIP is created in `tools/__build/`
- Extract and verify folder structure
- Test on staging before production

### 🏗️ Tipovi Build-ova

#### Standard Build (glavni joomlaboost.php)

- Koristi glavni `joomlaboost.php` fajl
- Za production use
- Kompatibilan sa postojećim instalacijama

#### Simple Build (joomlaboost-simple.php kao glavni)

- Koristi `build_joomlaboost_simple.ps1`
- Za testing alternative implementations
- Menja glavni fajl u XML na `joomlaboost-simple.php`

### ⚠️ Najčešće greške

1. **Zaboravio `<folder>src</folder>` u XML** ← TOP #1 greška
2. **Sintaksne greške u concatenation** (`.` vs `.=`)
3. **Nedostaju require_once statements**
4. **Version update samo u script-u, ne u XML**
5. **Testing na staging pre deploy na production**

### 🎯 Golden Rule

**NIKAD ne pravi build bez:**

1. ✅ XML manifest check
2. ✅ Syntax validation
3. ✅ Version update
4. ✅ Test na staging

### 📁 File Structure Check

Prije build-a, struktura MORA biti:

```text
src/plugins/system/joomlaboost/
├── joomlaboost.php               # Glavni fajl
├── joomlaboost.xml               # Manifest
├── joomlaboost-simple.php        # Alternative
├── language/
│   └── en-GB/
├── media/
│   └── admin.css
└── src/
    ├── Enums/
    └── Services/                 # KRITIČNO!
        ├── ServiceInterface.php
        ├── AbstractService.php
        ├── SchemaService.php
        └── ...
```

### 🚀 Build Script Usage

```powershell
# Standard build
.\build_joomlaboost.ps1

# Simple variant build
.\build_joomlaboost_simple.ps1 -Version "X.Y.Z"

# Check current build directory
ls .\__build\
```

---

## 💾 Save this checklist and follow it EVERY TIME
