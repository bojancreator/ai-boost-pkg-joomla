# JoomlaBoost - Universal Joomla Plugin

🚀 **Universal SEO & Performance Plugin for Joomla 4/5/6**

[![Version](https://img.shields.io/badge/version-0.1.17-blue.svg)](https://github.com/bojancreator/JoomlaBoost/releases)
[![CI](https://github.com/bojancreator/JoomlaBoost/actions/workflows/ci.yml/badge.svg)](https://github.com/bojancreator/JoomlaBoost/actions/workflows/ci.yml)
[![Release](https://img.shields.io/github/v/release/bojancreator/JoomlaBoost?include_prereleases)](https://github.com/bojancreator/JoomlaBoost/releases)
[![Joomla](https://img.shields.io/badge/Joomla-4.0%20%7C%205.x%20%7C%206.x-green.svg)](https://www.joomla.org/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-lightgrey.svg)](LICENSE)

## 📋 Pregled

JoomlaBoost je univerzalni SEO i performance plugin koji se automatski prilagođava bilo kom Joomla sajtu. Naslednik je prethodnih rešenja, potpuno refaktorisan za moderne Joomla verzije i PHP 8.1+.

### ✨ Ključne karakteristike

- 🌐 **Domain-agnostic** - Automatski se prilagođava bilo kom domenu
- 🤖 **Smart robots.txt** - Dinamička generacija sa pametnim pravilima
- 🗺️ **Sitemap.xml** - Automatska generacija mape sajta
- 🔍 **SEO optimizacija** - Meta tagovi, Open Graph, canonical URL-ovi
- ⚡ **Performance** - Optimizovano za brzinu i cache-iranje
- 🛠️ **Modern PHP** - PHP 8.1+ sa strict typing i enum podrškom

## 🚀 Instalacija

### Brza instalacija

1. **Preuzmite najnoviju verziju:**

   ```bash
   # Kloniraj repozitorijum
   git clone https://github.com/bojancreator/JoomlaBoost.git
   cd JoomlaBoost

   # Napravi ZIP paket
   .\tools\build_joomlaboost.ps1
   ```

2. **Instaliraj u Joomla:**
   - Idite na `Extensions > Manage > Install`
   - Upload `tools/__build/joomlaboost-0.1.17.zip`
   - Aktiviraj plugin u `System Plugins`

### Sistemski zahtevi
## Promene (kratko)

```text
- **Memorija:** Minimum 64MB PHP memorije
- **Disk:** ~50KB slobodnog prostora

## 📖 Dokumentacija

### Osnovne funkcionalnosti

| Feature          | Opis                                  | Status |
| ---------------- | ------------------------------------- | ------ |
| `robots.txt`     | Dinamička generacija robots.txt fajla | ✅     |
| `sitemap.xml`    | Osnovna sitemap generacija            | ✅     |
| SEO Meta Tags    | Canonical, Open Graph, Viewport       | ✅     |
| Domain Detection | Automatsko prepoznavanje domena       | ✅     |
| Admin Config     | Konfiguracija kroz Joomla admin       | ✅     |

### Pristup endpointovima

```bash
# Robots.txt
https://example.com/index.php?option=com_joomlaboost&task=robots

# Sitemap.xml
https://example.com/index.php?option=com_joomlaboost&task=sitemap
```

### Konfiguracija

Plugin se konfiguriše kroz Joomla admin panel:

1. Idite na `Extensions > Plugins`
2. Pronađite "JoomlaBoost - Universal SEO & Performance Plugin"
3. Kliknite za editovanje parametara

**Dostupne opcije:**

- ✅ Auto domain detection
- 🤖 Enable robots.txt
- 🗺️ Enable sitemap.xml
- 🔍 Enable SEO meta tags
- 📊 Analytics integration
- 🐛 Debug mode

## 🏗️ Razvoj

### Struktura projekta

```
JoomlaBoost/
├── src/plugins/system/joomlaboost/    # Main plugin files
│   ├── joomlaboost.php               # Entry point
│   ├── joomlaboost.xml               # Manifest
│   └── language/                     # Translations
├── tools/                            # Build scripts
│   ├── build_joomlaboost.ps1         # PowerShell builder
│   └── __build/                      # Generated packages
├── docs/                             # Documentation
└── archive/                          # Legacy code backup
```

### Build proces

```powershell
# Napravi ZIP paket
.\tools\build_joomlaboost.ps1

# Debug build
.\tools\build_joomlaboost_debug.ps1

# Testiraj lokalno
php tools/test-joomlaboost.php
```

### Komande (Composer)

- `composer lint` – PHPCS (PSR-12)
- `composer stan` – PHPStan (level 6)

## 🔄 Migracija

Ako migrirate sa starijih rešenja, preporuka je:

1. Backup postojećeg plugina
2. Deinstalacija starog plugina
3. Instalacija JoomlaBoost
4. Konfiguracija parametara
5. Test funkcionalnosti

## 🧪 Testiranje

### Staging checklist

- [ ] Install plugin na staging sajtu
- [ ] Enable plugin i konfiguriši settings
- [ ] Test robots.txt endpoint
- [ ] Test sitemap.xml endpoint
- [ ] Verify domain detection
- [ ] Check SEO meta tags
- [ ] Test na različitim environment-ima

### Debugging

```php
// Enable debug mode u plugin parametrima
$debug = $this->params->get('debug_mode', 0);

// Check logs
tail -f logs/joomla_error.log
```

## 📝 Changelog

### v0.1.17 (September 2025)

- 🚀 Initial release
- ✨ Universal domain support
- 🤖 Smart robots.txt generation
- 🗺️ Basic sitemap.xml
- 🔍 SEO meta tags optimization
- ⚙️ Admin configuration panel
- 📚 Comprehensive documentation

## 🤝 Doprinos

Pozivamo vas da doprinesete razvoju JoomlaBoost plugina!

### Kako pomoći

1. **🐛 Prijavite bugove** - Koristite GitHub Issues
2. **💡 Predložite funkcionalnosti** - Otvorite Feature Request
3. **🔧 Pošaljite kod** - Napravite Pull Request
4. **📚 Poboljšajte dokumentaciju** - Editujte README ili docs/
5. **🧪 Testirajte** - Pomoć sa QA testing-om

### Development setup

```bash
# Kloniraj ovaj repo i pripremi lokalni Joomla dev sajt
git clone https://github.com/bojancreator/JoomlaBoost.git
cd JoomlaBoost
# Napravi build pa instaliraj ZIP kroz Joomla admin
pwsh -NoProfile -ExecutionPolicy Bypass -File .\tools\build_joomlaboost.ps1
```

## 📞 Podrška

- **GitHub Issues:** [Prijavite problem](https://github.com/bojancreator/JoomlaBoost/issues)
- **📖 Dokumentacija:** [docs/](docs/)

## 📄 Licenca

Ovaj projekt je licenciran pod [GNU General Public License v2 or later](LICENSE).

```text
Copyright (C) 2025 JoomlaBoost.
JoomlaBoost Plugin - Universal SEO & Performance optimization for Joomla
```

---

### 🏆 Napravljeno sa ❤️ za Joomla zajednicu

JoomlaBoost - Univerzalni SEO plugin koji radi na bilo kom Joomla sajtu! 🚀

## Struktura

- `src/` – izvorni kod ekstenzija (pluginovi/moduli/template overrides).
- `joomla/` – gotovi Joomla pluginovi, moduli i template override-i.
- `tools/` – skripte za build/deploy i search indexer.
- `docs/` – dokumentacija (SEO, AI pretraga, arhitektura).
- `.github/workflows/` – CI konfiguracija.

### 🔌 Komponente

**Plugin-ovi:**

- `src/plugins/system/joomlaboost/` - Glavni univerzalni system plugin

**Tools:**

- `tools/indexer.php` - CLI za generiranje search indeksa
- `tools/build.sh` - Build skripta za ZIP pakete

## Komande (Composer) – dodatno

- `composer lint` – PHPCS (PSR-12).
- `composer stan` – PHPStan (level 6, podesivo).

## Build

- `tools/build.sh` – kreira ZIP pakete za Joomla komponente.
- `tools/indexer.php` – generiše search indeks iz Joomla baze.

## Contributing

Pogledaj `CONTRIBUTING.md` za grananje, commit stil i PR pravila.

## Licenca

MIT – vidi `LICENSE`.

## Deploy na staging (opciono)

U ovom repozitorijumu ne postoji obavezna staging integracija. Ako želite automatski deploy, dodajte sopstveni GitHub Actions workflow i SFTP/SSH tajne za VAŠ staging domen (npr. staging.example.com). Ovaj projekat je domen-agnostičan i ne koristi fiksne staging URL-ove.

## Promene (kratko)

- Vidi detaljno: `docs/RELEASE-NOTES.md`

## Bezbednost i revizija istorije (08/2025)

- Uočen je procureli GitHub PAT u `docs/Untitled-1.txt` (obrisano).
- Izvršen je rewrite GIT istorije da bi se taj fajl uklonio iz svih commit-a.
- Svi saradnici moraju da urade fresh `git clone` posle 2025-09-01.
- GitHub Actions su pinovani na tačne commit SHA.
- Dodata `.eslintignore` kako bi se izbegao lint šum iz `vendor/`.
