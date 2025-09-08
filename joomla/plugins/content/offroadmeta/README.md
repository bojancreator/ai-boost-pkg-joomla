# OffRoad Meta Plugin

Plugin za automatsko dodavanje meta tagova, OpenGraph i Schema.org markup-a na OffRoad Serbia sajtu.

## 🎯 Funkcionalnosti

1. **Automatski OpenGraph tagovi** - za bolje deljenje na društvenim mrežama
2. **Schema.org JSON-LD** - za bolje SEO i AI prepoznavanje sadržaja
3. **Twitter Card support** - optimizovano za Twitter
4. **Event detection** - automatski prepoznaje ekspedicije kao events

## 📦 Instalacija

1. Idi u Joomla Admin → Extensions → Install
2. Upload `plg_content_offroadmeta.zip`
3. Plugin Manager → Content - OffRoad Meta → Publish
4. Podesi opcije po potrebi

## ⚙️ Konfiguracija

- **Automatski OpenGraph**: Da/Ne - dodaje og:title, og:description, og:image
- **Automatski Schema.org**: Da/Ne - dodaje strukturirane podatke

## 🧠 Kako radi

Plugin se aktivira kada se prikazuje pojedinačni članak (`onContentAfterDisplay`):

1. **OpenGraph tagovi**:

   - `og:title` - naslov članka
   - `og:description` - meta opis ili početak teksta
   - `og:image` - prva slika iz članka
   - `og:type` - "article"

2. **Schema.org markup**:
   - Osnovno Article schema
   - Ako kategorija sadrži "ekspedicij" → Event schema
   - Publisher i author informacije

## 🔧 Development

Plugin koristi Joomla 4+ Event sistem:

- `SubscriberInterface` za moderne event handlers
- PSR-12 coding standard
- Namespaced klase

## 📝 Changelog

### 1.0.0

- Početna verzija
- OpenGraph meta tagovi
- Schema.org JSON-LD
- Event detection za ekspedicije
