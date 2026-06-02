#!/usr/bin/env python3
"""
Build comprehensive AI Boost settings-import datasets for the two test sites.

Seeds from the real offroadserbia export (real data where it exists), modernizes
it for the current version (drops retired/denylisted keys, fixes the placeholder
NYC address, fills empty options, adds the manifest keys Bojan never set), and
emits one import file per site with per-language translations for the languages
that site actually has installed:

  staging  (Pro,  offroadserbia)  → en-GB, sr-YU, ru-RU
  free     (Free, offroadbalkans) → en-GB, ru-RU, de-DE

Output (import via admin → AI Boost → Import):
  deliverables/data/aiboost-settings-staging.json
  deliverables/data/aiboost-settings-free.json
"""

import json
import os
import datetime

SRC = "attached_assets/aiboost-settings-export-2026-05-30_1780388950482.json"
OUT_DIR = "deliverables/data"

# Keys the importer strips anyway (license/identity/dev). Dropped for a clean file.
DENYLIST = [
    "license_key", "license_tier", "license_state", "license_simulation",
    "pro_skus", "dev_license_preview", "dev_force_free_tier",
    "install_id", "staging_mode",
]

# ── Translatable text content, English canonical first then translations ──────
# field_key -> { 'en-GB':..., 'sr-YU':..., 'ru-RU':..., 'de-DE':... }
TXT = {
    "org_description": {
        "en-GB": "4x4 Serbia Crew — off-road adventures across Serbia and the Balkans, dedicated to nature, overlanding and the 4x4 community.",
        "sr-YU": "4x4 Serbia Crew — off-road avanture širom Srbije i Balkana, posvećeni prirodi, overlandingu i 4x4 zajednici.",
        "ru-RU": "4x4 Serbia Crew — внедорожные приключения по Сербии и Балканам, посвящённые природе, оверлендингу и сообществу 4x4.",
        "de-DE": "4x4 Serbia Crew — Offroad-Abenteuer in Serbien und auf dem Balkan, der Natur, dem Overlanding und der 4x4-Community gewidmet.",
    },
    "og_description_override": {
        "en-GB": "Off-road tours, 4x4 expeditions and overlanding across Serbia and the Balkans with 4x4 Serbia Crew.",
        "sr-YU": "Off-road ture, 4x4 ekspedicije i overlanding širom Srbije i Balkana sa 4x4 Serbia Crew.",
        "ru-RU": "Внедорожные туры, 4x4-экспедиции и оверлендинг по Сербии и Балканам вместе с 4x4 Serbia Crew.",
        "de-DE": "Offroad-Touren, 4x4-Expeditionen und Overlanding in Serbien und auf dem Balkan mit 4x4 Serbia Crew.",
    },
    "llmstxt_description": {
        "en-GB": "4x4 Serbia Crew is an off-road community and tour operator in Serbia offering 4x4 expeditions, overlanding routes and nature adventures across the Balkans.",
        "sr-YU": "4x4 Serbia Crew je off-road zajednica i organizator tura u Srbiji koja nudi 4x4 ekspedicije, overlanding rute i avanture u prirodi širom Balkana.",
        "ru-RU": "4x4 Serbia Crew — это внедорожное сообщество и туроператор в Сербии, предлагающий 4x4-экспедиции, маршруты оверлендинга и приключения на природе по Балканам.",
        "de-DE": "4x4 Serbia Crew ist eine Offroad-Community und ein Tourenanbieter in Serbien, der 4x4-Expeditionen, Overlanding-Routen und Naturabenteuer auf dem Balkan anbietet.",
    },
    "org_address_street": {
        "en-GB": "Bulevar oslobodjenja 1",
        "sr-YU": "Bulevar oslobođenja 1",
        "ru-RU": "Бульвар Ослобоженья 1",
        "de-DE": "Bulevar oslobodjenja 1",
    },
    "org_address_city": {
        "en-GB": "Belgrade",
        "sr-YU": "Beograd",
        "ru-RU": "Белград",
        "de-DE": "Belgrad",
    },
}

# Brand fields — identical across languages (proper nouns / URLs) but still
# populated per language so every translatable field is covered.
BRAND = {
    "org_name": "4x4 Serbia Crew",
    "site_name": "4x4 Serbia Crew",
    "org_logo": "/images/LOGO-SERBIA-CREW.png",
    "default_og_image": "/images/LOGO-SERBIA-CREW.png",
}

# ── Modernized FAQ (current perpetual €45 one-license model) — English base ───
FAQ_EN = [
    {"question": "What is AI Boost for Joomla?",
     "answer": "AI Boost is an all-in-one SEO and AEO package for Joomla 5 and 6 that generates Schema.org, an XML sitemap, OpenGraph, robots.txt and llms.txt so AI engines such as ChatGPT, Perplexity, Google AI Overview and Bing Copilot can find and recommend your site."},
    {"question": "Which Joomla and PHP versions are supported?",
     "answer": "AI Boost supports Joomla 5.x and 6.x on PHP 8.1 to 8.5."},
    {"question": "How much does AI Boost cost?",
     "answer": "There is a free edition with the core SEO features. AI Boost Pro unlocks the entire Pro component for a single price of €45 per year."},
    {"question": "Can I use it on more than one site?",
     "answer": "Each licence covers one domain. Additional sites need additional licences."},
    {"question": "What is llms.txt?",
     "answer": "llms.txt is a standard file that gives AI engines a structured index of your site — like sitemap.xml but aimed at large language models. AI Boost generates it automatically at /llms.txt."},
]

# Serbian / Russian / German FAQ (questions + answers), same 5 entries.
FAQ_TR = {
    "sr-YU": [
        ("Šta je AI Boost za Joomla?",
         "AI Boost je all-in-one SEO i AEO paket za Joomla 5 i 6 koji generiše Schema.org, XML sitemap, OpenGraph, robots.txt i llms.txt kako bi AI pretraživači poput ChatGPT, Perplexity, Google AI Overview i Bing Copilot pronašli i preporučili vaš sajt."),
        ("Koje verzije Joomla i PHP su podržane?",
         "AI Boost podržava Joomla 5.x i 6.x na PHP verzijama od 8.1 do 8.5."),
        ("Koliko košta AI Boost?",
         "Postoji besplatna verzija sa osnovnim SEO funkcijama. AI Boost Pro otključava kompletnu Pro komponentu za jednu cijenu od €45 godišnje."),
        ("Mogu li ga koristiti na više sajtova?",
         "Svaka licenca pokriva jedan domen. Za dodatne sajtove potrebne su dodatne licence."),
        ("Šta je llms.txt?",
         "llms.txt je standardni fajl koji AI pretraživačima daje strukturirani indeks sajta — poput sitemap.xml ali namijenjen velikim jezičkim modelima. AI Boost ga generiše automatski na /llms.txt."),
    ],
    "ru-RU": [
        ("Что такое AI Boost для Joomla?",
         "AI Boost — это комплексный пакет SEO и AEO для Joomla 5 и 6, который генерирует Schema.org, XML-карту сайта, OpenGraph, robots.txt и llms.txt, чтобы ИИ-системы, такие как ChatGPT, Perplexity, Google AI Overview и Bing Copilot, находили и рекомендовали ваш сайт."),
        ("Какие версии Joomla и PHP поддерживаются?",
         "AI Boost поддерживает Joomla 5.x и 6.x на PHP от 8.1 до 8.5."),
        ("Сколько стоит AI Boost?",
         "Есть бесплатная версия с основными функциями SEO. AI Boost Pro открывает весь Pro-компонент за единую цену 45 € в год."),
        ("Можно ли использовать его на нескольких сайтах?",
         "Каждая лицензия распространяется на один домен. Для дополнительных сайтов нужны дополнительные лицензии."),
        ("Что такое llms.txt?",
         "llms.txt — это стандартный файл, который даёт ИИ-системам структурированный индекс сайта — как sitemap.xml, но предназначенный для больших языковых моделей. AI Boost создаёт его автоматически по адресу /llms.txt."),
    ],
    "de-DE": [
        ("Was ist AI Boost für Joomla?",
         "AI Boost ist ein All-in-One-SEO- und AEO-Paket für Joomla 5 und 6, das Schema.org, eine XML-Sitemap, OpenGraph, robots.txt und llms.txt erzeugt, damit KI-Systeme wie ChatGPT, Perplexity, Google AI Overview und Bing Copilot Ihre Website finden und empfehlen können."),
        ("Welche Joomla- und PHP-Versionen werden unterstützt?",
         "AI Boost unterstützt Joomla 5.x und 6.x mit PHP 8.1 bis 8.5."),
        ("Was kostet AI Boost?",
         "Es gibt eine kostenlose Version mit den grundlegenden SEO-Funktionen. AI Boost Pro schaltet die gesamte Pro-Komponente zum Einzelpreis von 45 € pro Jahr frei."),
        ("Kann ich es auf mehreren Websites verwenden?",
         "Jede Lizenz gilt für eine Domain. Für weitere Websites sind weitere Lizenzen erforderlich."),
        ("Was ist llms.txt?",
         "llms.txt ist eine Standarddatei, die KI-Systemen einen strukturierten Index der Website liefert — wie sitemap.xml, aber für große Sprachmodelle gedacht. AI Boost erzeugt sie automatisch unter /llms.txt."),
    ],
}

# Manifest keys Bojan never set — populate so we can see everything the plugin does.
ADDED_MANIFEST_KEYS = {
    "enable_twitter_cards": "1",
    "og_description_override": TXT["og_description_override"]["en-GB"],
    "error_log_enabled": "1",
    "error_log_min_severity": "warning",
    "crawler_rules": "",
    "hreflang_enabled": "1",
    "hreflang_primary_language": "en",
    "hreflang_sitemap": "1",
    "llmstxt_faq_auto_detect": "1",
    "meta_custom_events": "[]",
    "schema_breadcrumb_pro": "1",
    "schema_howto_enabled": "1",
    "translation_source_priority": "joomla_native",
}


def build_params() -> dict:
    exp = json.load(open(SRC, encoding="utf-8"))
    p = dict(exp["params"])

    for k in DENYLIST:
        p.pop(k, None)

    # Real organisation identity (fix placeholder NYC address → Belgrade, RS).
    p.update({
        "org_name": BRAND["org_name"],
        "org_url": "https://offroadserbia.com",
        "org_description": TXT["org_description"]["en-GB"],
        "org_logo": BRAND["org_logo"],
        "org_email": "4x4@offroadserbia.com",
        "org_phone": "+381 64 140 37 90",
        "org_address_street": TXT["org_address_street"]["en-GB"],
        "org_address_city": TXT["org_address_city"]["en-GB"],
        "org_address_state": "",
        "org_address_zip": "11000",
        "org_address_country": "RS",
        "org_latitude": "44.7866",
        "org_longitude": "20.4489",
        "site_name": BRAND["site_name"],
        "default_og_image": BRAND["default_og_image"],
        "llmstxt_description": TXT["llmstxt_description"]["en-GB"],
        "faq_items": json.dumps(FAQ_EN, ensure_ascii=False),
    })

    p.update(ADDED_MANIFEST_KEYS)
    return p


def build_translations(lang_codes: list[str]) -> list[dict]:
    rows = []
    for lc in lang_codes:
        # Plain translatable text fields.
        for key, langs in TXT.items():
            rows.append({"field_key": key, "lang_code": lc, "field_value": langs[lc]})
        # Brand fields — same value in every language (still covered).
        for key, val in BRAND.items():
            rows.append({"field_key": key, "lang_code": lc, "field_value": val})
        # FAQ per-item q/a.
        if lc == "en-GB":
            faqs = [(f["question"], f["answer"]) for f in FAQ_EN]
        else:
            faqs = FAQ_TR[lc]
        for i, (q, a) in enumerate(faqs):
            rows.append({"field_key": f"faq_{i}_q", "lang_code": lc, "field_value": q})
            rows.append({"field_key": f"faq_{i}_a", "lang_code": lc, "field_value": a})
    return rows


def emit(name: str, lang_codes: list[str], params: dict):
    doc = {
        "meta": {
            "version": "1.0",
            "plugin": "pkg_aiboost",
            "exported_at": datetime.datetime.now(datetime.timezone.utc).isoformat(),
            "joomla": "6.1.1",
        },
        "params": params,
        "translations": build_translations(lang_codes),
    }
    os.makedirs(OUT_DIR, exist_ok=True)
    path = os.path.join(OUT_DIR, name)
    with open(path, "w", encoding="utf-8") as f:
        json.dump(doc, f, ensure_ascii=False, indent=2)
    print(f"  ✓ {path} — {len(params)} params, {len(doc['translations'])} translations "
          f"({', '.join(lang_codes)})")


def main():
    params = build_params()
    print("Building settings datasets:")
    emit("aiboost-settings-staging.json", ["en-GB", "sr-YU", "ru-RU"], params)
    emit("aiboost-settings-free.json", ["en-GB", "ru-RU", "de-DE"], params)


if __name__ == "__main__":
    main()
