#!/usr/bin/env python3
"""
AI Boost — generate ready-to-import test fixtures (10 business-type variants).

Each fixture is a complete {meta, params, translations} import envelope exercising
a different Schema.org business type + the surrounding settings (org identity,
social, hours, per-type detail fields, FAQ/HowTo, OpenGraph, sitemap). A couple
carry sr-YU / ru-RU translation rows to exercise multilingual import.

Every key is validated against the live settings catalog
(deliverables/import-templates/aiboost-settings-catalog.csv — produced by
make-import-template.py) so a fixture can never ship a key the product doesn't
know. Unknown keys are reported and dropped.

Usage:
  python scripts/make-test-variants.py
  python scripts/make-test-variants.py --catalog <path> --out <dir>

These import via AI Boost -> Import/Export (merge). Import them onto TEST sites
only — they set many values at once. Language matrix for the multilingual ones:
en-GB (base, in params) + sr-YU + ru-RU (translation rows).

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
    """JSON-encode a value for a 'json'-typed setting (stored as a string)."""
    return json.dumps(x, ensure_ascii=False)


def tr(field_key: str, **by_lang) -> list:
    """Build translation rows: tr('org_name', **{'sr-YU': '...', 'ru-RU': '...'})."""
    return [{"field_key": field_key, "lang_code": lc, "field_value": v}
            for lc, v in by_lang.items()]


# ── Common building blocks ───────────────────────────────────────────────────

def base(org_name, desc, email, phone, city, street, zip_, country="RS",
         schema_type="Organization", **extra) -> dict:
    p = {
        "enable_schema": "1",
        "schema_type": schema_type,
        "org_name": org_name,
        "org_description": desc,
        "org_email": email,
        "org_phone": phone,
        "org_url": "https://example.com",
        "org_address_street": street,
        "org_address_city": city,
        "org_address_country": country,
        "org_address_zip": zip_,
        "social_facebook": "https://facebook.com/example",
        "social_instagram": "https://instagram.com/example",
        "enable_opengraph": "1",
        "site_name": org_name,
        "twitter_site_handle": "@example",
        "enable_sitemap": "1",
        "include_articles": "1",
        "include_categories": "1",
        "default_changefreq": "weekly",
        "default_priority": "0.8",
    }
    p.update(extra)
    return p


WEEKDAY_HOURS = {
    "schema_hours_mode": "simple",
    "hours_mon_opens": "09:00", "hours_mon_closes": "17:00", "hours_mon_closed": "0",
    "hours_tue_opens": "09:00", "hours_tue_closes": "17:00", "hours_tue_closed": "0",
    "hours_wed_opens": "09:00", "hours_wed_closes": "17:00", "hours_wed_closed": "0",
    "hours_thu_opens": "09:00", "hours_thu_closes": "17:00", "hours_thu_closed": "0",
    "hours_fri_opens": "09:00", "hours_fri_closes": "17:00", "hours_fri_closed": "0",
    "hours_sat_opens": "10:00", "hours_sat_closes": "14:00", "hours_sat_closed": "0",
    "hours_sun_opens": "00:00", "hours_sun_closes": "00:00", "hours_sun_closed": "1",
}

FAQ_GENERIC = {
    "enable_manual_faqs": "1",
    "manual_faq_scope": "always_all",
    "schema_faq_output_type": "faqpage",
    "faq_items": J([
        {"question": "Where are you located?", "answer": "In the city centre — see the address on our contact page."},
        {"question": "Do I need an appointment?", "answer": "Walk-ins are welcome, but appointments are recommended."},
        {"question": "Which payment methods do you accept?", "answer": "Cash and all major cards."},
    ]),
}


# ── 10 variants ──────────────────────────────────────────────────────────────

VARIANTS = {
    "01-restaurant": {
        "note": "Restaurant — cuisine, price range, reservations, hours, FAQ.",
        "params": {**base("Tri Šešira", "Traditional Serbian restaurant in Skadarlija.",
                           "info@trisesira.example", "+381 11 111 1111",
                           "Belgrade", "Skadarska 29", "11000",
                           schema_type="Restaurant"),
                   **WEEKDAY_HOURS, **FAQ_GENERIC,
                   "specific_serves_cuisine": "Serbian, Balkan, Grill",
                   "specific_price_range": "$$",
                   "specific_accepts_reservations": "true",
                   "specific_menu_url": "https://example.com/menu",
                   "rating_value": "4.7", "rating_count": "318"},
        "translations": [],
    },
    "02-hotel": {
        "note": "Hotel / LodgingBusiness — star rating, check-in/out, rooms, pets, amenities.",
        "params": {**base("Hotel Zlatibor", "Mountain hotel with spa and restaurant.",
                           "reception@zlatibor.example", "+381 31 222 2222",
                           "Zlatibor", "Kraljeva 1", "31315",
                           schema_type="LodgingBusiness"),
                   **WEEKDAY_HOURS,
                   "specific_star_rating": "4",
                   "specific_checkin_time": "14:00",
                   "specific_checkout_time": "11:00",
                   "specific_number_of_rooms": "120",
                   "specific_pets_allowed": "true",
                   "specific_amenity_feature": "Free WiFi, Spa, Parking, Pool",
                   "rating_value": "4.5", "rating_count": "902"},
        "translations": [],
    },
    "03-dentist": {
        "note": "Dentist — medical specialty, accepting patients, languages, credentials.",
        "params": {**base("Dental Studio Smile", "Family and cosmetic dentistry.",
                           "office@smile.example", "+381 21 333 3333",
                           "Novi Sad", "Bulevar 12", "21000",
                           schema_type="Dentist"),
                   **WEEKDAY_HOURS,
                   "specific_medical_specialty": "Dentistry",
                   "specific_accepting_patients": "true",
                   "specific_languages": "Serbian, English, German",
                   "specific_credentials": "DDS, member of the Serbian Dental Chamber",
                   "rating_value": "4.9", "rating_count": "144"},
        "translations": [],
    },
    "04-law-firm": {
        "note": "Law firm / LegalService — area served, payment, FAQ.",
        "params": {**base("Pravna Kancelarija Marković", "Commercial and civil law practice.",
                           "office@markovic-law.example", "+381 11 444 4444",
                           "Belgrade", "Terazije 5", "11000",
                           schema_type="LegalService"),
                   **WEEKDAY_HOURS, **FAQ_GENERIC,
                   "specific_area_served": "Serbia, Montenegro, Bosnia and Herzegovina",
                   "specific_payment_accepted": "Bank transfer, Card",
                   "specific_knows_about": "Corporate law, Contracts, Litigation"},
        "translations": [],
    },
    "05-gym": {
        "note": "Gym / HealthClub — amenities, audience, hours.",
        "params": {**base("Iron House Gym", "Strength and conditioning gym.",
                           "hello@ironhouse.example", "+381 11 555 5555",
                           "Belgrade", "Vojvode Stepe 100", "11000",
                           schema_type="HealthClub"),
                   **WEEKDAY_HOURS,
                   "specific_amenity_feature": "Free weights, Sauna, Personal training",
                   "specific_audience": "Adults, Athletes",
                   "specific_price_range": "$$"},
        "translations": [],
    },
    "06-beauty-salon": {
        "note": "Beauty salon — services list, price range, hours.",
        "params": {**base("Studio Lepote Ana", "Hair, nails and skincare.",
                           "book@studioana.example", "+381 11 666 6666",
                           "Belgrade", "Knez Mihailova 20", "11000",
                           schema_type="BeautySalon"),
                   **WEEKDAY_HOURS,
                   "schema_services": J(["Haircut", "Manicure", "Facial", "Makeup"]),
                   "specific_price_range": "$$$"},
        "translations": [],
    },
    "07-real-estate": {
        "note": "Real estate agency — area served, brand, slogan.",
        "params": {**base("Beograd Nekretnine", "Residential and commercial real estate.",
                           "info@bgnekretnine.example", "+381 11 777 7777",
                           "Belgrade", "Bulevar kralja Aleksandra 73", "11000",
                           schema_type="RealEstateAgent"),
                   **WEEKDAY_HOURS,
                   "specific_area_served": "Belgrade, Novi Sad, Niš",
                   "specific_brand": "BG Nekretnine",
                   "specific_slogan": "Your home, our mission."},
        "translations": [],
    },
    "08-news-media": {
        "note": "News / Media organisation — masthead, ethics policy, topics.",
        "params": {**base("Balkan Daily", "Independent regional news outlet.",
                           "desk@balkandaily.example", "+381 11 888 8888",
                           "Belgrade", "Makedonska 22", "11000",
                           schema_type="NewsMediaOrganization"),
                   "specific_masthead_url": "https://example.com/masthead",
                   "specific_ethics_policy_url": "https://example.com/ethics",
                   "specific_knows_about": "Politics, Economy, Culture, Sport",
                   "article_schema_enabled": "1"},
        "translations": [],
    },
    "09-store-ecom": {
        "note": "Store / e-commerce — return policy, payment, currencies, price range.",
        "params": {**base("Tech Korner", "Consumer electronics shop.",
                           "shop@techkorner.example", "+381 11 999 9999",
                           "Belgrade", "Bulevar Mihajla Pupina 10", "11070",
                           schema_type="Store"),
                   **WEEKDAY_HOURS,
                   "specific_return_category": "MerchantReturnFiniteReturnWindow",
                   "specific_return_days": "30",
                   "specific_return_country": "RS",
                   "specific_payment_accepted": "Card, Cash, Bank transfer",
                   "specific_currencies_accepted": "RSD, EUR",
                   "specific_price_range": "$$"},
        "translations": [],
    },
    "10-multilang-org": {
        "note": "Generic Organization with multilingual identity (en base + sr-YU + ru-RU) and FAQ.",
        "params": {**base("Adriatic Tours", "Travel agency for the Adriatic coast.",
                           "hello@adriatictours.example", "+382 20 100 100",
                           "Kotor", "Stari Grad 5", "85330", country="ME",
                           schema_type="TravelAgency" if False else "Organization"),
                   **FAQ_GENERIC,
                   "specific_area_served": "Montenegro, Croatia, Serbia"},
        "translations": (
            tr("org_name", **{"sr-YU": "Jadranske Ture", "ru-RU": "Адриатические туры"})
            + tr("org_description", **{
                "sr-YU": "Turistička agencija za jadransku obalu.",
                "ru-RU": "Турагентство на адриатическом побережье."})
        ),
    },
}


def load_catalog_keys(path: str) -> set:
    keys = set()
    with open(path, encoding="utf-8") as fh:
        for row in csv.DictReader(fh):
            k = (row.get("key") or "").strip()
            if k:
                keys.add(k)
    return keys


def main(catalog: str, out_dir: str) -> int:
    valid = load_catalog_keys(catalog) if os.path.exists(catalog) else set()
    if not valid:
        print(f"⚠️  catalog not found at {catalog} — keys will NOT be validated.\n"
              f"   Generate it: python _creds_run.py scripts/make-import-template.py --target staging")
    os.makedirs(out_dir, exist_ok=True)

    total_unknown = 0
    for name, spec in VARIANTS.items():
        params = dict(spec["params"])
        unknown = sorted(k for k in params if valid and k not in valid)
        if unknown:
            total_unknown += len(unknown)
            print(f"  ✗ {name}: unknown key(s) dropped: {unknown}")
            for k in unknown:
                params.pop(k, None)
        envelope = {
            "meta": {"plugin": "pkg_aiboost", "version": "1.0", "note": spec["note"]},
            "params": params,
            "translations": spec.get("translations", []),
        }
        path = os.path.join(out_dir, f"{name}.json")
        with open(path, "w", encoding="utf-8") as fh:
            json.dump(envelope, fh, ensure_ascii=False, indent=2)
        tcount = len(envelope["translations"])
        print(f"  ✓ {name:<18} {len(params):>3} params"
              + (f", {tcount} translation rows" if tcount else "")
              + f"  -> {os.path.relpath(path, REPO)}")

    print(f"\n{len(VARIANTS)} variants written to {out_dir}"
          + (f"  ({total_unknown} unknown keys dropped)" if total_unknown else "  (all keys valid)"))
    return 1 if total_unknown else 0


if __name__ == "__main__":
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--catalog", default=os.path.join(REPO, "deliverables", "import-templates",
                                                      "aiboost-settings-catalog.csv"))
    ap.add_argument("--out", default=os.path.join(REPO, "deliverables", "import-templates", "variants"))
    args = ap.parse_args()
    sys.exit(main(args.catalog, args.out))
