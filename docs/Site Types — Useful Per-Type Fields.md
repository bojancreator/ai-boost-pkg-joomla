# Site Types — Useful Per-Type Fields (Task #609)

## What & Why
Right now choosing a Schema "Site Type" barely changes the output — almost every type
emits the same handful of fields, so the type selector feels pointless. There are two
root causes, confirmed in code:

1. **Key mismatch breaks type-specific output.** The admin dropdown saves values like
   `LodgingBusiness` / `FoodEstablishment` / `Dentist`, but the preset map and the
   emit guards key off lowercase aliases (`hotel`, `restaurant`, …). Several dropdown
   values (`Dentist`, `Person`, `NewsMediaOrganization`, `SportsActivityLocation`,
   `EducationalOrganization`) have **no matching preset key at all**, and the emit
   guards (`$typeKey === 'hotel'`, `in_array($typeKey, ['restaurant',…])`) never fire
   for the capitalised values the UI actually saves. Result: for most types the
   type-specific fields silently don't render in the JSON-LD.
2. **Thin field set.** Even when a type does fire, it exposes only 1–2 fields. The
   properties Google and AI engines (ChatGPT/Perplexity/AI Overviews) actually use —
   restaurant menu/reservations, hotel amenities, medical specialty, payment methods,
   geo coordinates — are missing or collected-but-not-emitted.

This task makes each Site Type carry a real reason to exist: the selected type reliably
drives the right `@type` **and** unlocks a focused, useful set of fields that map to
current Google rich-result + AI-search signals.

Research basis (2025/2026): Google recommends the most specific subtype; restaurant
`hasMenu`/`acceptsReservations` and hotel `amenityFeature` drive visible rich-result
features; `paymentAccepted`, `geo`, `sameAs` strengthen the entity for AI engines.
Google retired 7 rich-result types in June 2025 (Course, ClaimReview, Estimated Salary,
Learning Video, Special Announcement, Book Actions, most FAQ) — do NOT invest in those.

## Done looks like
- Selecting any Site Type in the admin reliably outputs the correct schema.org `@type`
  and shows exactly the field cards relevant to that type — no orphaned types, no cards
  that render but never reach the JSON-LD.
- Local business types emit `geo` (from the existing latitude/longitude fields),
  `paymentAccepted`, and `currenciesAccepted` when filled.
- Restaurant exposes and emits a Menu URL (`hasMenu`) and Accepts Reservations
  (`acceptsReservations`).
- Hotel exposes and emits Amenities (`amenityFeature`), plus its existing
  pets-allowed / cuisine fields now actually reach the output.
- Medical clinic and Dentist expose and emit a Medical Specialty (`medicalSpecialty`);
  Dentist, Gym and Education actually emit their "available service" field (today only
  Medical/Legal do).
- Legal service emits Area Served.
- Each new/fixed field has a matching Health-tab check that confirms the artifact in the
  page source, with a fix-action link to its tab+field.
- Verified on staging: the JSON-LD in the page body contains the new properties for at
  least one representative type per group (local, restaurant, hotel, medical).

## Out of scope
- No Free/Pro gating, no per-feature SKUs — single non-gated product. The legacy
  `'pro' => true` flags in the preset map are not a feature gate; treat every type as
  available. (Leftover "pro" naming cleanup is tracked separately and not required here.)
- No deprecated Google rich-result types (Course, ClaimReview, etc.).
- No marketing-website changes.
- No new Site Types beyond reconciling the ones that already exist in the dropdown and
  preset map (do not invent verticals).

## Steps
1. **Reconcile type keys end to end.** Make the admin dropdown values, the preset map,
   and the emit guards agree on one canonical key per type (normalise on read so a
   saved value always resolves), and give every dropdown type a preset entry with the
   correct schema.org `@type` and `local` flag. Verify no selectable type falls through
   to the generic Organization fallback unintentionally.
2. **Consolidate emit into the active builder.** Ensure the geo block (from the existing
   latitude/longitude fields) and all type-specific blocks live in the builder that
   actually runs, so output doesn't depend on which of the two builders is invoked.
3. **Wire up fields that are collected but not emitted.** Hotel pets-allowed and cuisine;
   the shared "available service" field for Dentist, Gym and Education; area-served for
   Legal — all should reach the JSON-LD when filled.
4. **Add universal local-business fields.** Add Payment Accepted and Currencies Accepted
   (manifest-driven, since they are simple global fields) and emit them for local types.
5. **Add restaurant fields.** Menu URL and an Accepts-Reservations toggle, shown only for
   the restaurant type and emitted as `hasMenu` / `acceptsReservations`.
6. **Add hotel fields.** An Amenities control (multi-select or comma list) emitted as
   `amenityFeature`, shown only for the hotel type.
7. **Add medical/dental field.** A Medical Specialty field shown for Medical Clinic and
   Dentist, emitted as `medicalSpecialty`.
8. **Health registry coverage.** Add a Health check per new/fixed field (id, category,
   label+message, expected HTML artifact, fix-action link to tab+field) on both the
   server registry and the Health Vue app.
9. **i18n + codegen.** Run the manifest codegen for any manifest-added fields, and add
   en-GB `.ini` strings for every new label/help/Health string (English only).
10. **Build, install, verify on staging.** Bump version (minor — new fields), build,
    install to staging, and confirm via the page body that the JSON-LD contains the new
    properties for a representative type in each group. Update the Health registry rule
    is satisfied. Architectural note: type-specific conditional fields are an existing
    hand-written exception (Schema tab + builder), while simple global fields go through
    the manifest — follow each existing convention rather than forcing one path.

## Relevant files
- `component/com_aiboost/vue-admin/src/tabs/SchemaTab.vue`
- `component/com_aiboost/vue-admin/src/tabs/OrgTab.vue`
- `component/plugins/system/aiboost_schema/src/Service/SchemaProBuilder.php`
- `component/plugins/system/aiboost_schema/src/Service/SchemaBuilder.php`
- `component/plugins/system/aiboost_schema/src/Service/SiteTypePresetService.php`
- `component/plugins/system/aiboost_schema/src/Service/BusinessHoursBuilder.php`
- `component/plugins/system/aiboost_schema/src/Extension/AiBoostSchema.php`
- `component/lib/src/Manifest/schema.php`
- `component/lib/src/HealthCheckService.php`
- `component/com_aiboost/vue-admin/src/HealthApp.vue`
- `component/plugins/system/aiboost_schema/language/en-GB/plg_system_aiboost_schema.ini`
- `scripts/codegen-from-manifest.py`
- `scripts/build-package-zip.py`
- `scripts/install-to-staging.py`
