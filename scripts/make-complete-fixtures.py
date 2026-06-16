#!/usr/bin/env python3
"""
AI Boost — COMPLETE import fixtures (every option filled), mono + multilingual.

Builds, from the live settings catalog (deliverables/import-templates/
aiboost-settings-catalog.csv), import envelopes where EVERY one of the ~221
settings carries a realistic value (toggles on, selects set to a valid option,
numbers/text/json populated) so a single import exercises the whole product:

  complete-monolingual.json   All options filled, English base, no translations.
                              (A fully-configured single-language site.)
  complete-multilingual.json  Same params + a Russian (ru-RU) override for EVERY
                              translatable field (org identity, OG, FAQ items,
                              HowTo name/steps, llms description, …) so the
                              per-language path is fully exercised.
  complete-nonenglish-base.json  All options filled with the BASE values in
                              Russian (no translations) — import on a site whose
                              Joomla default language is ru-RU to verify the
                              non-English-default path (schema/OG/llms render the
                              Russian base; nothing hardcodes English).

Every key is validated against the catalog; unknown keys are dropped. Import on
TEST sites only — these set everything at once.

Usage:
  python scripts/make-complete-fixtures.py
  python scripts/make-complete-fixtures.py --catalog <path> --out <dir>

@copyright (C) 2025 AI Boost (aiboostnow.com). GPL v2 or later.
"""

from __future__ import annotations

import argparse
import csv
import json
import os
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
REPO = os.path.dirname(HERE)


def J(x) -> str:
    return json.dumps(x, ensure_ascii=False)


# Curated realistic values for keys that need a specific (non-type-derivable)
# value. Everything else is filled from its type (see value_for()).
OVERRIDES_EN = {
    "schema_type": "Restaurant",
    "org_name": "AI Boost Demo Bistro",
    "org_legal_name": "AI Boost Demo Bistro Ltd.",
    "org_description": "A friendly neighbourhood bistro serving seasonal food and great coffee.",
    "org_url": "https://example.com",
    "org_email": "hello@example.com",
    "org_phone": "+1 555 0100",
    "org_logo": "images/demo/logo.png",
    "org_logo_alt": "AI Boost Demo Bistro logo",
    "org_image": "images/demo/storefront.jpg",
    "org_address_street": "12 Market Street",
    "org_address_city": "Springfield",
    "org_address_state": "IL",
    "org_address_zip": "62701",
    "org_address_country": "US",
    "org_latitude": "39.7817",
    "org_longitude": "-89.6501",
    "org_founding_date": "2014-03-01",
    "org_vat_id": "US123456789",
    "org_map_url": "https://maps.example.com/demo-bistro",
    "social_facebook": "https://facebook.com/aiboostdemo",
    "social_instagram": "https://instagram.com/aiboostdemo",
    "social_youtube": "https://youtube.com/@aiboostdemo",
    "social_twitter": "https://x.com/aiboostdemo",
    "social_linkedin": "https://linkedin.com/company/aiboostdemo",
    "social_tiktok": "https://tiktok.com/@aiboostdemo",
    "rating_value": "4.7", "rating_count": "318", "rating_best": "5", "rating_worst": "1",
    "rating_source": "Google",
    "specific_serves_cuisine": "Italian, Mediterranean",
    "specific_price_range": "$$",
    "specific_accepts_reservations": "true",
    "specific_menu_url": "https://example.com/menu",
    "specific_payment_accepted": "Cash, Card",
    "specific_currencies_accepted": "USD, EUR",
    "specific_area_served": "Springfield and surrounding area",
    "specific_available_service": "Dine-in, Takeaway, Delivery",
    "schema_opening_hours": "Mo-Fr 09:00-22:00; Sa 10:00-23:00",
    "site_name": "AI Boost Demo Bistro",
    "default_og_image": "images/demo/og-default.jpg",
    "default_og_image_alt": "AI Boost Demo Bistro — seasonal food",
    "og_description_override": "Seasonal food and great coffee in Springfield.",
    "twitter_site_handle": "@aiboostdemo",
    "fb_app_id": "1234567890",
    "fb_domain_verification": "abc123domainverify",
    "ga4_measurement_id": "G-XXXXXXXXXX",
    "gtm_container_id": "GTM-XXXXXXX",
    "meta_pixel_id": "100000000000000",
    "gsc_verification_code": "google-site-verification-demo",
    "gsc_additional_html": "<meta name=\"demo\" content=\"1\">",
    "indexnow_api_key": "0123456789abcdef0123456789abcdef",
    "llmstxt_description": "AI Boost Demo Bistro — seasonal food and great coffee in Springfield.",
    "news_publication_name": "AI Boost Demo News",
    # Title/meta tokens are AI Boost's: {page_title} {site_name} {separator} {category} {year} {description}
    "title_separator": " | ",
    "title_template": "{page_title} {separator} {site_name}",
    "title_template_home": "{site_name} {separator} {page_title}",
    "title_template_article": "{page_title} {separator} {category} {separator} {site_name}",
    "title_template_category": "{category} {separator} {site_name}",
    "title_template_default": "{page_title} {separator} {site_name}",
    "meta_desc_template": "{description}",
    "meta_desc_template_article": "{description} {separator} {site_name}",
    "meta_desc_template_default": "{description}",
    "custom_code_head": "<!-- demo head code -->",
    "custom_code_body": "<!-- demo body code -->",
    "custom_code_footer": "<!-- demo footer code -->",
    "specific_slogan": "Fresh, local, every day.",
    "specific_award": "Best Bistro 2025",
    "specific_brand": "AI Boost Demo",
    "faq_items": J([
        {"question": "Do you take reservations?", "answer": "Yes, online or by phone."},
        {"question": "Do you offer vegan options?", "answer": "Yes, several daily."},
    ]),
    "schema_services": J(["Dine-in", "Takeaway", "Catering"]),
    "schema_howto": J({
        "enabled": "1", "name": "How to book a table", "description": "Reserve in three steps.",
        "totalTime": "PT2M",
        "steps": [
            {"name": "Open the booking page", "text": "Go to example.com/book."},
            {"name": "Pick a time", "text": "Choose your date and party size."},
        ],
    }),
}

# Russian base values (for the non-English-default fixture) + the multilingual
# ru-RU translation overrides for translatable fields.
RU = {
    "org_name": "AI Boost Демо Бистро",
    "org_legal_name": "ООО «AI Boost Демо Бистро»",
    "org_description": "Уютное районное бистро: сезонная кухня и отличный кофе.",
    "org_logo_alt": "Логотип AI Boost Демо Бистро",
    "org_address_street": "ул. Рыночная, 12",
    "org_address_city": "Спрингфилд",
    "site_name": "AI Boost Демо Бистро",
    "default_og_image_alt": "AI Boost Демо Бистро — сезонная кухня",
    "og_description_override": "Сезонная кухня и отличный кофе в Спрингфилде.",
    "news_publication_name": "AI Boost Демо Новости",
    "llmstxt_description": "AI Boost Демо Бистро — сезонная кухня и отличный кофе в Спрингфилде.",
    "specific_slogan": "Свежее и местное каждый день.",
    "specific_award": "Лучшее бистро 2025",
    # Per-language images (URLs differ per language).
    "org_logo": "images/demo/logo-ru.png",
    "default_og_image": "images/demo/og-default-ru.jpg",
    # Dynamic FAQ + HowTo translation keys.
    "faq_0_q": "Можно ли забронировать столик?",
    "faq_0_a": "Да, онлайн или по телефону.",
    "faq_1_q": "Есть ли веганские блюда?",
    "faq_1_a": "Да, несколько каждый день.",
    "howto_name": "Как забронировать столик",
    "howto_desc": "Бронируйте в три шага.",
    "howto_step_0_name": "Откройте страницу бронирования",
    "howto_step_0_text": "Перейдите на example.com/book.",
    "howto_step_1_name": "Выберите время",
    "howto_step_1_text": "Укажите дату и число гостей.",
}

# Translatable field keys (mirror vue-admin/src/translatable-fields.js) that get
# a ru-RU row in the multilingual fixture. Dynamic FAQ/HowTo keys added from RU.
TRANSLATABLE = [
    "org_name", "org_description", "org_logo", "org_address_street", "org_address_city",
    "org_logo_alt", "site_name", "default_og_image", "default_og_image_alt",
    "og_description_override", "news_publication_name", "llmstxt_description",
    "specific_slogan", "specific_award",
]
TRANSLATABLE_DYNAMIC = [
    "faq_0_q", "faq_0_a", "faq_1_q", "faq_1_a",
    "howto_name", "howto_desc",
    "howto_step_0_name", "howto_step_0_text", "howto_step_1_name", "howto_step_1_text",
]

# Keys never worth turning on in a fixture (would change site behaviour oddly).
SKIP_ENABLE = {"staging_mode", "dev_license_preview", "dev_force_free_tier"}


def value_for(row: dict) -> str:
    key = row["key"]
    if key in OVERRIDES_EN:
        return OVERRIDES_EN[key]
    typ = row.get("type", "")
    default = row.get("default", "")
    options = [o.strip() for o in (row.get("options") or "").split("|") if o.strip()]
    if typ == "toggle":
        return "0" if key in SKIP_ENABLE else "1"
    if typ == "select":
        # Prefer a non-empty, non-"disabled"/"off"/"none" option to exercise the feature.
        for o in options:
            if o.lower() not in ("", "none", "off", "disabled"):
                return o
        return options[0] if options else (default or "")
    if typ == "number":
        return default if default not in ("", None) else "1"
    if typ == "json":
        return default if default not in ("", None) else "[]"
    if typ == "media":
        return "images/demo/" + key + ".jpg"
    # Title/meta template fields not explicitly overridden: leave EMPTY so AI Boost
    # uses its built-in default. A literal "AI Boost demo …" would be a broken
    # template (no {page_title}/{site_name}/{separator} tokens) and render verbatim.
    if key.startswith(("title_template", "meta_desc_template")):
        return ""
    # text / textarea
    return default if default not in ("", None) else ("AI Boost demo " + key.replace("_", " "))


def build_params(rows: list[dict], base_ru: bool) -> dict:
    params = {}
    for row in rows:
        params[row["key"]] = value_for(row)
    if base_ru:
        # Swap the curated English base text for Russian where we have it.
        for k, v in RU.items():
            if k in params and not k.startswith(("faq_", "howto_")):
                params[k] = v
    return params


def ru_translations(rows_keys: set) -> list:
    trans = []
    for k in TRANSLATABLE + TRANSLATABLE_DYNAMIC:
        if k in RU:
            # FAQ/HowTo dynamic keys are not catalog columns but are valid translation keys.
            trans.append({"field_key": k, "lang_code": "ru-RU", "field_value": RU[k]})
    return trans


def load_catalog(path: str) -> list[dict]:
    with open(path, encoding="utf-8") as fh:
        return [r for r in csv.DictReader(fh) if (r.get("key") or "").strip()]


def main(catalog: str, out_dir: str) -> int:
    if not os.path.exists(catalog):
        print(f"❌ catalog not found: {catalog}\n   run: python _creds_run.py scripts/make-import-template.py --target staging")
        return 1
    rows = load_catalog(catalog)
    keys = {r["key"] for r in rows}
    os.makedirs(out_dir, exist_ok=True)

    fixtures = {
        "complete-monolingual": {
            "note": "Every option filled, English base, no translations.",
            "params": build_params(rows, base_ru=False), "translations": [],
        },
        "complete-multilingual": {
            "note": "Every option filled (English base) + ru-RU translation for every translatable field.",
            "params": build_params(rows, base_ru=False), "translations": ru_translations(keys),
        },
        "complete-nonenglish-base": {
            "note": "Every option filled, BASE values in Russian (no translations). Import on a ru-RU-default Joomla site to verify the non-English-default path.",
            "params": build_params(rows, base_ru=True), "translations": [],
        },
    }

    for name, spec in fixtures.items():
        env = {
            "meta": {"plugin": "pkg_aiboost", "version": "1.0", "note": spec["note"]},
            "params": spec["params"], "translations": spec["translations"],
        }
        path = os.path.join(out_dir, name + ".json")
        with open(path, "w", encoding="utf-8") as fh:
            json.dump(env, fh, ensure_ascii=False, indent=2)
        filled = sum(1 for v in spec["params"].values() if str(v) not in ("", "0", "[]", "{}"))
        print(f"  ✓ {name:<26} {len(spec['params'])} params ({filled} non-empty), "
              f"{len(spec['translations'])} ru translations  -> {os.path.relpath(path, REPO)}")
    print(f"\n{len(fixtures)} complete fixtures written to {out_dir}")
    return 0


if __name__ == "__main__":
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--catalog", default=os.path.join(REPO, "deliverables", "import-templates",
                                                      "aiboost-settings-catalog.csv"))
    ap.add_argument("--out", default=os.path.join(REPO, "deliverables", "import-templates", "complete"))
    args = ap.parse_args()
    sys.exit(main(args.catalog, args.out))
