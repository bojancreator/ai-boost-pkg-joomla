# JoomlaBoost AI Optimizacija – HG Budvanska Rivijera
## Plan implementacije | hgbudvanskarivijera.com

> **Datum plana:** 2026-03-17  
> **Plugin:** JoomlaBoost (sistem plugin)  
> **CMS:** Joomla + YooTheme Pro  
> **Van scope-a ovog plana:** Google Tag Manager, Google Analytics, Meta Pixel  
>   → Ovi alati se postavljaju direktno kroz YooTheme Pro builder.

---

## 📋 Pregled sajta

| Entitet | Tip | Lokacija |
|---|---|---|
| HG Budvanska Rivijera (korporacija) | TouristOrganization / Corporation | Budva, Crna Gora |
| TN Slovenska Plaža | LodgingBusiness (Resort – 10 vila) | Budva |
| Hotel Palas | Hotel (4★) | Petrovac |
| Hotel Palas Lux | Hotel (Lux aneks Palasa) | Petrovac |
| Hotel Aleksandar | Hotel (Family Club) | Budva |
| Hotel Castellastva | Hotel | Petrovac |
| Hotel Mogren | Hotel (**van funkcije** – renovacija) | Budva |

---

## 🏗️ Arhitektura sajta i pristup

Sajt ima **jedan Joomla instancu** sa jednim JoomlaBoost pluginom, ali servira sadržaj za više entiteta (korporacija + 5 hotela). Zbog toga pristupamo na sledeći način:

### Strategija: Jedan plugin, kontekstualni schema.org

JoomlaBoost generiše schema po stranici. Na **korporacijskim** stranicama ide `Organization` / `TouristOrganization` schema, a na **stranicama svakog hotela** ide `LodgingBusiness` / `Hotel` schema. Ovo se postiže kroz:

1. **Globalne parametre plugina** → korporacija (Organization)
2. **Article-level Custom Fields** → per-hotel override (Hotel/LodgingBusiness)
3. **Ručni FAQ po stranicama** → FAQ schema auto-detekcija iz HTML (`<dl><dt><dd>`)

---

## ⚙️ FAZA 1 – Instalacija i osnovna konfiguracija

### Korak 1.1 – Instalacija plugina

- Uploadovati JoomlaBoost ZIP u Joomla admin → Extensions → Install
- Aktivirati plugin: **System – JoomlaBoost**
- Proveriti debug log da nema grešaka pri aktivaciji

### Korak 1.2 – Globalna konfiguracija (korporacijski nivo)

Ovo su podešavanja za **ceo sajt / korporaciju**. Svaki hotel dobija override na nivou stranice.

#### Tab: Organization / Schema

| Parametar | Vrednost |
|---|---|
| `schema_type` | `organization` (korporacija na home i corporate stranicama) |
| `org_name` | `HG Budvanska Rivijera` |
| `org_name_en` | `HG Budvanska Rivijera` |
| `org_name_sr` | `HG Budvanska Rivijera` *(ako ima sr jezik)* |
| `schema_description` | Kratak opis hotelske grupe (do 160 karaktera) |
| `schema_address_country` | `ME` |
| `schema_address_locality` | `Budva` |
| `schema_address_street` | Adresa centrale (proveriti sa klijentom) |
| `schema_address_zip` | Poštanski broj |
| `schema_phone` | Centralni telefon |
| `schema_email` | Kontakt email |
| `schema_price_range` | `$$$` |
| `org_logo` | URL logoa HG Budvanska Rivijera |
| `og_image` | Fallback OG slika (header sajta) |

#### Tab: Social Media (sameAs za Organization schema)

| Parametar | Vrednost |
|---|---|
| `schema_social_facebook` | Facebook stranica (korporacijska) |
| `schema_social_instagram` | Instagram profil |
| `schema_social_youtube` | YouTube kanal (ako postoji) |

> **Napomena:** `sameAs` je kritičan za AI prepoznavanje entiteta (Google Knowledge Graph, Perplexity, ChatGPT). Uvek uneti sve dostupne socijalne mreže.

---

## 🏨 FAZA 2 – Hotel Schema po entitetu

### Strategija: Joomla Custom Fields + Article Schema Override

Za svaki hotel postoji poseban deo sajta (kategorija ili poseban meni). Na tim stranicama koristimo **Joomla Custom Fields** za override schema podataka, ili se oslanjamo na Article schema koji JoomlaBoost generiše iz sadržaja članka.

> **Trenutno stanje plugina:** Plugin generiše `LodgingBusiness` schema kada je `schema_type = hotel` u globalnim parametrima. Za multi-hotel sajt, preporučen pristup je korišćenje **globalnog Organization**, a Hotel schema se ubacuje kroz per-page JSON-LD dodato u YooTheme Pro komponentu (Custom Code modul) na hotelskim stranicama.

### Alternativa (preporučena za ovaj sajt):

Pošto YooTheme Pro Builder kontroliše izgled stranica, dodati **ručni JSON-LD blok** po hotelu direktno u YooTheme template (Custom Code sekcija), a JoomlaBoost da se konfiguriše za **Organization + WebSite + Breadcrumb + FAQ** na globalnom nivou.

---

## 📊 FAZA 2A – Hotel JSON-LD blokovi (po entitetu)

Svaki blok se dodaje u YooTheme Pro kao **Custom HTML/Code** element na odgovarajućoj stranici hotela, u `<head>` sekciji.

### 🏖️ TN Slovenska Plaža (Resort)

```json
{
  "@context": "https://schema.org",
  "@type": "Resort",
  "name": "TN Slovenska Plaža",
  "alternateName": "Turistično Naselje Slovenska Plaža",
  "url": "https://hgbudvanskarivijera.com/en/slovenska-plaza",
  "description": "Resort comprising 10 villas with direct beach access in Budva, Montenegro.",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "Slovenska Plaža bb",
    "addressLocality": "Budva",
    "addressCountry": "ME",
    "postalCode": "85310"
  },
  "telephone": "+382-XX-XXXXXX",
  "geo": {
    "@type": "GeoCoordinates",
    "latitude": 42.275,
    "longitude": 18.851
  },
  "checkInTime": "14:00",
  "checkOutTime": "11:00",
  "amenityFeature": [
    {"@type": "LocationFeatureSpecification", "name": "Beach Access", "value": true},
    {"@type": "LocationFeatureSpecification", "name": "Swimming Pool", "value": true},
    {"@type": "LocationFeatureSpecification", "name": "Restaurant", "value": true},
    {"@type": "LocationFeatureSpecification", "name": "WiFi", "value": true}
  ],
  "containsPlace": [
    {"@type": "LodgingBusiness", "name": "Vila 1 – Slovenska Plaža"},
    {"@type": "LodgingBusiness", "name": "Vila 2 – Slovenska Plaža"}
  ],
  "parentOrganization": {
    "@type": "Organization",
    "name": "HG Budvanska Rivijera",
    "url": "https://hgbudvanskarivijera.com"
  }
}
```

### 🏛️ Hotel Palas + Palas Lux (Petrovac)

```json
{
  "@context": "https://schema.org",
  "@type": "Hotel",
  "name": "Hotel Palas",
  "url": "https://hgbudvanskarivijera.com/en/hotel-palas",
  "description": "4-star hotel in Petrovac, Montenegro, with sea views and conference facilities.",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "Obala bb",
    "addressLocality": "Petrovac",
    "addressCountry": "ME"
  },
  "telephone": "+382-XX-XXXXXX",
  "starRating": {
    "@type": "Rating",
    "ratingValue": 4,
    "bestRating": 5
  },
  "checkInTime": "14:00",
  "checkOutTime": "11:00",
  "amenityFeature": [
    {"@type": "LocationFeatureSpecification", "name": "Swimming Pool", "value": true},
    {"@type": "LocationFeatureSpecification", "name": "Conference Hall", "value": true},
    {"@type": "LocationFeatureSpecification", "name": "Restaurant", "value": true}
  ],
  "containsPlace": {
    "@type": "Hotel",
    "name": "Hotel Palas Lux",
    "description": "Luxury annex of Hotel Palas with premium accommodation."
  },
  "parentOrganization": {
    "@type": "Organization",
    "name": "HG Budvanska Rivijera",
    "url": "https://hgbudvanskarivijera.com"
  }
}
```

### 👨‍👩‍👧 Hotel Aleksandar – Family Club (Budva)

```json
{
  "@context": "https://schema.org",
  "@type": "Hotel",
  "name": "Hotel Aleksandar",
  "url": "https://hgbudvanskarivijera.com/en/hotel-aleksandar",
  "description": "Family Club Hotel Aleksandar in Budva, Montenegro. Family-friendly with animation programs and beach access.",
  "address": {
    "@type": "PostalAddress",
    "addressLocality": "Budva",
    "addressCountry": "ME"
  },
  "petsAllowed": false,
  "checkInTime": "14:00",
  "checkOutTime": "11:00",
  "amenityFeature": [
    {"@type": "LocationFeatureSpecification", "name": "Kids Club", "value": true},
    {"@type": "LocationFeatureSpecification", "name": "Animation Program", "value": true},
    {"@type": "LocationFeatureSpecification", "name": "Beach Access", "value": true}
  ],
  "parentOrganization": {
    "@type": "Organization",
    "name": "HG Budvanska Rivijera",
    "url": "https://hgbudvanskarivijera.com"
  }
}
```

### 🏰 Hotel Castellastva (Petrovac)

```json
{
  "@context": "https://schema.org",
  "@type": "Hotel",
  "name": "Hotel Castellastva",
  "url": "https://hgbudvanskarivijera.com/en/hotel-castellastva",
  "description": "Hotel Castellastva in Petrovac, Montenegro, surrounded by Mediterranean greenery.",
  "address": {
    "@type": "PostalAddress",
    "addressLocality": "Petrovac",
    "addressCountry": "ME"
  },
  "checkInTime": "14:00",
  "checkOutTime": "11:00",
  "parentOrganization": {
    "@type": "Organization",
    "name": "HG Budvanska Rivijera",
    "url": "https://hgbudvanskarivijera.com"
  }
}
```

### 🚧 Hotel Mogren (TRENUTNO VAN FUNKCIJE)

> **Napomena:** Hotel Mogren je u renovaciji. Schema se dodaje, ali sa oznakom da nije dostupan.

```json
{
  "@context": "https://schema.org",
  "@type": "Hotel",
  "name": "Hotel Mogren",
  "url": "https://hgbudvanskarivijera.com/en/hotel-mogren",
  "description": "Hotel Mogren in Budva, Montenegro. Currently undergoing renovation.",
  "address": {
    "@type": "PostalAddress",
    "addressLocality": "Budva",
    "addressCountry": "ME"
  },
  "tourBookingPage": "",
  "parentOrganization": {
    "@type": "Organization",
    "name": "HG Budvanska Rivijera",
    "url": "https://hgbudvanskarivijera.com"
  }
}
```

> ⚠️ **Za Hotel Mogren:** Ne dodavati `checkInTime`, `checkOutTime`, priceRange niti booking linkove. Dodati samo osnovne identifikacione podatke dok je u renovaciji.

---

## 🔍 FAZA 3 – JoomlaBoost Konfiguracija (detaljna lista)

### 3.1 Schema.org (plugin parametri)

| Sekcija | Parametar | Vrednost | Napomena |
|---|---|---|---|
| Schema | `enable_schema` | `1` | Obavezno |
| Schema | `schema_type` | `organization` | Korporacija globalno |
| Schema | `schema_latitude` | `42.2855` | Geo koordinate Budve |
| Schema | `schema_longitude` | `18.8440` | Geo koordinate Budve |
| FAQ | `faq_schema_enabled` | `1` | Globalni FAQ |
| FAQ | `enable_manual_faqs` | `1` | Uneti FAQ JSON (vidi niže) |
| FAQ | `enable_content_faq_auto_detect` | `1` | Auto-detekcija FAQ iz članaka |

### 3.2 OpenGraph (plugin parametri)

| Parametar | Vrednost |
|---|---|
| `enable_opengraph` | `1` |
| `og_site_name` | `HG Budvanska Rivijera` |
| `og_image` | URL fallback slike (npr. header logo) |
| `og_override` | `0` (ne prepisivati ako YooTheme već ima OG tagove) |
| `twitter_site` | `@HGBudvanskaRivijera` *(ako postoji Twitter/X)* |

> **Napomena o og_override:** Pošto YooTheme Pro može da generiše OG tagove, testirati najpre sa `og_override = 0`. Ako se duplikati javljaju, proveriti koji sistem dominira i ili ostaviti samo JoomlaBoost ili samo YooTheme.

### 3.3 Sitemap

| Parametar | Vrednost |
|---|---|
| `enable_sitemap` | `1` |
| `sitemap_include_articles` | `1` |
| `sitemap_include_categories` | `1` |
| `sitemap_include_menu` | `1` |
| `sitemap_priority_articles` | `0.8` |
| `sitemap_priority_categories` | `0.7` |
| `sitemap_priority_menu` | `0.6` |
| `sitemap_hreflang` | `1` (ako sajt ima više jezika) |
| `sitemap_changefreq_articles` | `weekly` |

**Nakon konfiguracije:** Proveriti `https://hgbudvanskarivijera.com/sitemap.xml`

### 3.4 Robots.txt

| Parametar | Vrednost |
|---|---|
| `enable_robots` | `1` |
| Sitemap linija | `Sitemap: https://hgbudvanskarivijera.com/sitemap.xml` |
| Disallow | `/administrator/`, `/tmp/`, `/cache/` |

**Proveriti:** `https://hgbudvanskarivijera.com/robots.txt`

### 3.5 Hreflang (ako sajt ima više jezičnih verzija)

| Parametar | Vrednost |
|---|---|
| `enable_hreflang` | `1` |
| Konfiguracija jezika | Prema aktivnim jezicima u Joomla (ME / EN) |

---

## 📝 FAZA 4 – FAQ Schema (kritično za AI)

FAQ schema je jedan od **najvažnijih signala za AI pretraživače** (ChatGPT, Perplexity, Google AI Overviews). Treba pokriti česta pitanja o grupi i svakom hotelu.

### 4.1 Globalni FAQ (unosi se u plugin parametar `manual_faqs`)

#### Format za unos u plugin:

```json
[
  {
    "question": "Which hotels does HG Budvanska Rivijera operate?",
    "answer": "HG Budvanska Rivijera operates five properties in Montenegro: TN Slovenska Plaža (resort in Budva), Hotel Palas and Hotel Palas Lux (in Petrovac), Hotel Aleksandar Family Club (Budva), and Hotel Castellastva (Petrovac). Hotel Mogren in Budva is currently undergoing renovation."
  },
  {
    "question": "Where are HG Budvanska Rivijera hotels located?",
    "answer": "Our hotels are located in two Montenegrin towns: Budva (TN Slovenska Plaža, Hotel Aleksandar, Hotel Mogren) and Petrovac (Hotel Palas, Hotel Palas Lux, Hotel Castellastva)."
  },
  {
    "question": "Does HG Budvanska Rivijera offer direct booking?",
    "answer": "Yes, HG Budvanska Rivijera offers direct booking on hgbudvanskarivijera.com with a guaranteed lowest rate and special promotions for direct bookers."
  },
  {
    "question": "Are the hotels family-friendly?",
    "answer": "Yes, especially Hotel Aleksandar which is a dedicated Family Club Hotel with animation programs and facilities for children. TN Slovenska Plaža resort also offers family-oriented accommodation."
  },
  {
    "question": "Is Hotel Mogren open?",
    "answer": "Hotel Mogren in Budva is currently closed for renovation. It will reopen after the renovation works are completed."
  },
  {
    "question": "What is TN Slovenska Plaža?",
    "answer": "TN Slovenska Plaža (Turističko Naselje Slovenska Plaža) is a villa resort in Budva consisting of 10 villas with direct access to Slovenska Plaža beach."
  },
  {
    "question": "What facilities does Hotel Palas have?",
    "answer": "Hotel Palas in Petrovac offers sea view rooms, a swimming pool, restaurant, conference facilities, and direct access to the beach in Petrovac."
  },
  {
    "question": "What is Hotel Palas Lux?",
    "answer": "Hotel Palas Lux is the premium annex of Hotel Palas in Petrovac, offering luxury accommodation with enhanced amenities and services."
  },
  {
    "question": "Can I hold a conference or event at HG Budvanska Rivijera hotels?",
    "answer": "Yes, Hotel Palas in Petrovac has conference hall facilities suitable for business events, seminars, and corporate gatherings."
  },
  {
    "question": "What is the check-in and check-out time at HG Budvanska Rivijera hotels?",
    "answer": "Standard check-in time is 14:00 and check-out time is 11:00 across HG Budvanska Rivijera properties. Early check-in or late check-out may be arranged upon request."
  }
]
```

> 🔄 Isti FAQ se može napraviti i na srpskom/crnogorskom jeziku za jezik-specifičnu verziju.

### 4.2 Per-stranica FAQ (auto-detekcija)

Plugin automatski detektuje FAQ iz HTML sadržaja stranice ako je sadržaj strukturiran sa `<dl><dt><dd>` tagovima. **Preporuka:** Na YooTheme Pro stranicama svakog hotela dodati FAQ sekciju u formatu:

```html
<dl>
  <dt>Gde se nalazi Hotel Palas?</dt>
  <dd>Hotel Palas se nalazi u Petrovcu na Moru, na Crnogorskom primorju.</dd>
  
  <dt>Da li Hotel Palas ima bazen?</dt>
  <dd>Da, Hotel Palas ima outdoor bazen sa pogledom na more.</dd>
  
  <!-- ... više pitanja ... -->
</dl>
```

Plugin će ovo automatski prepoznati i generisati `FAQPage` schema na toj strani.

---

## 🌐 FAZA 5 – Višejezičnost i hreflang

### Preporučena konfiguracija za EN i ME (crnogorski) jezik:

| Hreflang tag | URL |
|---|---|
| `en` | `https://hgbudvanskarivijera.com/en/` |
| `sr-ME` ili `cnr` | `https://hgbudvanskarivijera.com/me/` |
| `x-default` | `https://hgbudvanskarivijera.com/en/` *(engleski kao default)* |

Plugin `HreflangService` automatski generiše ove tagove za sve stranice ako je aktiviran.

---

## 🤖 FAZA 6 – AI-specifična optimizacija (beyond schema)

Ovo su dodatne preporuke koje su bitne za vidljivost u AI pretraživačima (ChatGPT browsing, Perplexity, Google AI Overviews, Claude):

### 6.1 robots.txt za AI botove

Proveriti da AI botovi nisu blokirani. U robots.txt sekciji plugina **NE blokirati:**

```
User-agent: GPTBot
Allow: /

User-agent: PerplexityBot
Allow: /

User-agent: ClaudeBot
Allow: /

User-agent: Googlebot
Allow: /
```

> JoomlaBoost robots.txt generator → proveriti da nema `Disallow: /` za ove botove.

### 6.2 Meta Description kvalitet

Svaki hotel mora imati jedinstvenu meta description (140-160 karaktera) koja:
- Pominje lokaciju (Budva / Petrovac, Montenegro)
- Pominje ključni USP hotela (family, luxury, beach access, resort...)
- Sadrži poziv na akciju ("Book directly", "Reserve now")

### 6.3 WebSite schema SearchAction

JoomlaBoost automatski generiše `SearchAction` u WebSite schema. Proveriti da Joomla search radi ispravno na sajtu.

### 6.4 BreadcrumbList schema

JoomlaBoost automatski generiše Breadcrumb schema iz Joomla Pathway. Proveriti da sve stranice hotela imaju ispravnu breadcrumb navigaciju u Joomla meniju.

### 6.5 Structured Data za Sobe (napredna faza)

U narednoj fazi (nije u scope ovog plana) razmotriti dodavanje `Accommodation` / `HotelRoom` schema za svaku kategoriju soba. Ovo se može postići kroz Joomla com_fields i custom schema blokove u YooTheme Pro.

---

## ✅ FAZA 7 – Verifikacija i testiranje

### Checklist po etapama:

#### Po instalaciji plugina:
- [ ] `https://hgbudvanskarivijera.com/robots.txt` → proveriti sadržaj
- [ ] `https://hgbudvanskarivijera.com/sitemap.xml` → proveriti da se generiše
- [ ] Google Rich Results Test na home stranici → `Organization` schema
- [ ] Schema Markup Validator (validator.schema.org) na home stranici

#### Po dodavanju Hotel JSON-LD blokova:
- [ ] Google Rich Results Test na stranici TN Slovenska Plaža
- [ ] Google Rich Results Test na stranici Hotel Palas
- [ ] Google Rich Results Test na stranici Hotel Aleksandar
- [ ] Google Rich Results Test na stranici Hotel Castellastva
- [ ] Proveriti da Hotel Mogren stranica nema booking informacije u schema

#### Po dodavanju FAQ:
- [ ] Google Rich Results Test → FAQPage schema na home ili contact stranici
- [ ] Proveriti FAQ auto-detekciju na hotelskim stranicama

#### OpenGraph verifikacija:
- [ ] Facebook Sharing Debugger → svaka hotel stranica
- [ ] Twitter Card Validator → proveriti og:image prikaz
- [ ] Proveriti da nema duplikata OG tagova (YooTheme Pro vs JoomlaBoost)

#### Hreflang verifikacija:
- [ ] Google Search Console → International Targeting → proveriti hreflang tagove
- [ ] www.hreflang.org validator → uneti URL-ove

---

## 📌 NAPOMENE I POSEBNI SLUČAJEVI

### Hotel Mogren (renovacija)
- Schema se dodaje radi dosljednosti entiteta i Knowledge Graph prisutnosti
- **NE** dodavati priceRange, checkInTime, booking URL
- Dodati u description: "Currently undergoing renovation"
- Razmotriti `temporarilyClosed: true` property

### Hotel Palas Lux
- Nije zasebna URL stranica nego deo Palas stranice
- U schema se tretira kao `containsPlace` unutar Hotel Palas schema
- NE pravi se zasebna `@type: Hotel` za Palas Lux na posebnoj stranici

### Koordinate (GeoCoordinates)
Za tačne koordinate koristiti Google Maps za svaki hotel:
- **Budva oblast:** approx 42.275°N, 18.840°E
- **Petrovac oblast:** approx 42.207°N, 18.941°E
- Proveriti tačne koordinate za svaki hotel posebno!

### Rezervacija URL-ovi
Ako postoji centralni booking engine ili redirect (npr. booking.com white-label), dodati `hasOfferCatalog` i `reservationUrl` u schema.

---

## 🗓️ Redosled implementacije

| Redosled | Zadatak | Prioritet |
|---|---|---|
| 1 | Instalacija JoomlaBoost plugina | 🔴 Kritično |
| 2 | Globalna konfiguracija (Organization schema, robots, sitemap) | 🔴 Kritično |
| 3 | FAQ JSON unos u plugin (globalni) | 🔴 Kritično |
| 4 | Hotel JSON-LD blokovi u YooTheme Pro (svaki hotel) | 🟡 Visoko |
| 5 | OpenGraph konfiguracija + verifikacija | 🟡 Visoko |
| 6 | Hreflang konfiguracija (ako je višejezičan sajt) | 🟡 Visoko |
| 7 | Per-stranica FAQ (`<dl>` format u YooTheme Pro sadržaju) | 🟢 Srednje |
| 8 | Koordinate i adrese svakog hotela (tačna verifikacija) | 🟢 Srednje |
| 9 | Verifikacija kroz Google Rich Results i Search Console | 🔴 Kritično |
| 10 | Monitoring kroz Google Search Console posle indeksiranja | 🟢 Ongoing |

---

*Plan kreirao: JoomlaBoost Dev tim*  
*Sajt: hgbudvanskarivijera.com*  
*Sledeći korak: Instalacija plugina i konfiguracija Faze 1 i Faze 3*
