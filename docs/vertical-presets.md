# Site Types — Choosing the Right Schema Type for Your Site

AI Boost for Joomla emits Schema.org structured data tuned to your kind of website or business. You pick the type once, and the Schema.org page then shows exactly the fields that matter for that type — a hotel sees check-in times, a restaurant sees cuisine and menu fields, a clinic sees medical specialty, and so on.

---

## How to choose your type

1. Go to **SEO → Schema.org** and open the **Business** section.
2. Under **Business / Organization Type**, pick a **Category** (e.g. Food & Drink, Health & Medical, Professional Services) and then the specific **Schema Type**.
3. An info panel shows which extra fields the selected type unlocks; type-specific cards appear below.
4. Fill in the fields that apply to you and click **Save**.

> **Not a business?** Choose **Organization** (the safe general default), **Person** for a personal/portfolio site, or **NewsMediaOrganization** for a news/media site. For blogs and magazines, also enable **Article Schema** in the **Core** section so every article gets `Article`/`NewsArticle`/`BlogPosting` markup automatically.

There is no preset that bulk-changes other settings — selecting a type only controls the structured data output and which schema fields are shown. Your sitemap, social and analytics settings are configured on their own pages (the **Autopilot** checklist under SETUP walks you through the essentials).

---

## Available types

The Schema Type list covers, among others:

| Category | Types |
|----------|-------|
| Organization / Generic | Organization, LocalBusiness, NewsMediaOrganization |
| Food & Drink | Restaurant, CafeOrCoffeeShop, Bakery, BarOrPub, FoodEstablishment |
| Health & Medical | MedicalClinic, Dentist, Physician, Pharmacy, Hospital, VeterinaryCare |
| Lodging & Travel | LodgingBusiness (Hotel), BedAndBreakfast, Resort, TouristAttraction |
| Beauty & Fitness | BeautySalon, HairSalon, NailSalon, DaySpa, HealthClub, SportsActivityLocation |
| Professional Services | ProfessionalService, LegalService, AccountingService, RealEstateAgent |
| Retail & Automotive | Store, AutomotiveBusiness |
| Education & Childcare | EducationalOrganization, ChildCare |
| Finance | BankOrCreditUnion, FinancialService, InsuranceAgency |
| Person | Person |

---

## What each type unlocks

Depending on the selected type, the Schema.org page reveals matching cards. Examples:

| Type | Type-specific fields |
|------|----------------------|
| LodgingBusiness / Hotel | Star rating, check-in/check-out times, pets allowed |
| Restaurant and other food types | Cuisine types, menu URL, accepts reservations |
| Medical types | Medical/dental specialty, patient area served |
| LegalService | Jurisdiction / service area |
| Person | Job title, affiliation, topics & expertise |
| NewsMediaOrganization | Founding date, masthead URL, ethics policy URL |
| LocalBusiness types | Price range, payment accepted, amenity features, weekly opening hours (the **Hours** section) |

**Pro Upgrade adds** further detail cards for your type:

- **Services & Prices** — named services with optional prices and currency, translatable per language
- **More Details** — type-aware extras such as accepting new patients, number of rooms, credentials, languages spoken, dietary suitability, return policy, number of employees, target audience, slogan and awards

In the Free edition these appear as locked cards with an **Upgrade to Pro** button.

---

## Recommendations by site kind

| Your site | Suggested setup |
|-----------|-----------------|
| Hotel / accommodation | Type: LodgingBusiness (or BedAndBreakfast/Resort) + complete address and GPS in **SETUP → Site Identity** + Customer Rating (verifiable reviews only) |
| Restaurant / cafe | Type: Restaurant + opening hours + price range + GPS coordinates — Google's local pack relies heavily on structured location data |
| Blog / magazine | Type: Organization or NewsMediaOrganization + **Article Schema** enabled; Pro: Author Entity and Google News sitemap |
| Online shop | Type: Store + Organisation description. Note: AI Boost emits site-level schema, not per-product `Product` schema — use your shop extension's own product schema |
| Agency / services | Type: ProfessionalService (or the specific service type) + all social links in Site Identity; Pro: Services & Prices, manual FAQ |
| Personal / portfolio | Type: Person + job title, affiliation and expertise topics |

---

## After choosing a type

1. Complete **SETUP → Site Identity** — name, description, logo, address, GPS, social links. The schema for every type is built on this data.
2. Run the **Autopilot** checklist (SETUP → Autopilot) to cover sitemap and social meta.
3. Open **OVERVIEW → Health** and re-run the checks.
4. Validate a page with the [Google Rich Results Test](https://search.google.com/test/rich-results).

---

*← [Debug & Diagnostics](debug-performance.md) | [Documentation Index](index.md) | [Per-Article Overrides →](per-article-overrides.md)*

*AI Boost for Joomla — © 2025–2026 AI Boost ([aiboostnow.com](https://aiboostnow.com)).*
