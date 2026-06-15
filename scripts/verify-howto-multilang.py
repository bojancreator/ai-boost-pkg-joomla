#!/usr/bin/env python3
"""
AI Boost — live proof that HowTo Schema is multilingual.

Sets a global HowTo (renders on every article view) plus Russian translations for
its name + first step, then fetches one article in en-GB vs ru-RU and asserts the
HowTo JSON-LD name/step come back English (base) vs Russian (translated). Restores
the original schema_howto and neutralises the test translations afterwards.

Usage:
  python _creds_run.py scripts/verify-howto-multilang.py --target staging --article 216

@copyright (C) 2025 AI Boost (aiboostnow.com). GPL v2 or later.
"""
from __future__ import annotations
import argparse, importlib.util, json, os, re, sys

HERE = os.path.dirname(os.path.abspath(__file__))
_spec = importlib.util.spec_from_file_location("_qa_common", os.path.join(HERE, "_qa_common.py"))
qa = importlib.util.module_from_spec(_spec); _spec.loader.exec_module(qa)

EN_NAME = "AIBOOST HowTo TEST (English base)"
EN_STEP_NAME = "English step name"
EN_STEP_TEXT = "Do the thing, described in English."
RU_NAME = "ТЕСТ Как сделать (русский)"
RU_STEP_NAME = "Русское название шага"
RU_STEP_TEXT = "Сделайте это, описано по-русски."


def import_envelope(s, admin_php, params: dict, translations: list, csrf: str) -> bool:
    payload = json.dumps({"meta": {"plugin": "pkg_aiboost"}, "params": params,
                          "translations": translations}).encode("utf-8")
    r = s.post(admin_php + "?option=com_aiboost&task=import.upload",
               data={"option": "com_aiboost", "task": "import.upload", csrf: "1"},
               files={"ab_import_file": ("howto.json", payload, "application/json")},
               allow_redirects=True, timeout=120)
    return '"success":true' in r.text.replace(" ", "")


def howto_on(s, base, article: int, lang: str) -> dict | None:
    url = f"{base}/index.php?option=com_content&view=article&id={article}&lang={lang}"
    html = qa.fetch_html(s, url, cache_bust=True)
    objs = qa.jsonld_from_html(html)
    return qa.find_type(objs, "HowTo")


def first_step(howto: dict) -> dict:
    steps = howto.get("step") or []
    return steps[0] if steps and isinstance(steps[0], dict) else {}


def main(target: str, article: int) -> int:
    qa.setup_console_utf8()
    s, admin_php, base = qa.connect(target)
    csrf = qa.get_csrf(s, admin_php)
    print(f"=== HowTo multilingual proof on [{target}] article {article} ===")

    snap = (qa.export_full(s, admin_php).get("params") or {}).get("schema_howto", "")
    print(f"  snapshot schema_howto: {len(str(snap))} chars")

    test_howto = json.dumps({
        "enabled": "1", "name": EN_NAME, "description": "",
        "steps": [{"name": EN_STEP_NAME, "text": EN_STEP_TEXT}],
    })
    translations = [
        {"field_key": "howto_name", "lang_code": "ru-RU", "field_value": RU_NAME},
        {"field_key": "howto_step_0_name", "lang_code": "ru-RU", "field_value": RU_STEP_NAME},
        {"field_key": "howto_step_0_text", "lang_code": "ru-RU", "field_value": RU_STEP_TEXT},
    ]
    ok = import_envelope(s, admin_php, {"schema_howto": test_howto, "schema_howto_enabled": "1"},
                         translations, csrf)
    print(f"  import HowTo + ru translations: {'ok' if ok else 'FAILED'}")

    fails = []
    en = howto_on(s, base, article, "en-GB")
    ru = howto_on(s, base, article, "ru-RU")
    if not en:
        fails.append("no HowTo JSON-LD on en-GB fetch")
    if not ru:
        fails.append("no HowTo JSON-LD on ru-RU fetch")

    if en:
        es = first_step(en)
        print(f"  [en-GB] name={en.get('name')!r}")
        print(f"          step.name={es.get('name')!r} step.text={es.get('text')!r}")
        if en.get("name") != EN_NAME:
            fails.append(f"en name expected base {EN_NAME!r}, got {en.get('name')!r}")
    if ru:
        rs = first_step(ru)
        print(f"  [ru-RU] name={ru.get('name')!r}")
        print(f"          step.name={rs.get('name')!r} step.text={rs.get('text')!r}")
        if ru.get("name") != RU_NAME:
            fails.append(f"ru name expected {RU_NAME!r}, got {ru.get('name')!r}")
        if rs.get("name") != RU_STEP_NAME:
            fails.append(f"ru step.name expected {RU_STEP_NAME!r}, got {rs.get('name')!r}")
        if rs.get("text") != RU_STEP_TEXT:
            fails.append(f"ru step.text expected {RU_STEP_TEXT!r}, got {rs.get('text')!r}")

    # Restore: original schema_howto + neutralise test translations (empty value).
    neutral = [{"field_key": t["field_key"], "lang_code": "ru-RU", "field_value": ""} for t in translations]
    import_envelope(s, admin_php, {"schema_howto": snap}, neutral, csrf)
    print("  restored schema_howto + cleared test translations")

    print("\n=== verdict ===")
    if fails:
        for f in fails:
            print(f"  ✗ {f}")
        print(f"\nRESULT: FAIL ({len(fails)} issue(s))")
        return 1
    print("  ✓ en-GB HowTo renders the English base; ru-RU HowTo renders the Russian translation")
    print("  ✓ name + step name + step text all translate")
    print("\nRESULT: PASS — HowTo Schema is multilingual")
    return 0


if __name__ == "__main__":
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--target", default="staging", choices=list(qa.TARGETS))
    ap.add_argument("--article", type=int, default=216)
    a = ap.parse_args()
    sys.exit(main(a.target, a.article))
