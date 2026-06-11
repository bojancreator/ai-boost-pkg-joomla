#!/usr/bin/env python3
"""
Staging QA for the Site-Type Schema fields slice (Task #609 + entity graph).

Non-destructive: snapshots the live AI Boost settings via settings.export,
applies a test configuration per schema type, reads the front-end JSON-LD, then
restores the original values for every key it touched.

Verifies on the live homepage JSON-LD:
  - Restaurant:   hasMenu, acceptsReservations (bool), currenciesAccepted
  - MedicalClinic: medicalSpecialty
  - Entity graph: Organization @id, WebSite @id + publisher → org @id

Run via the out-of-git creds wrapper:
  python _creds_run.py scripts/verify-schema-fields.py --target staging
"""

import argparse
import json
import os
import re
import sys
import time
import urllib.parse
import importlib.util

# UTF-8 console (Windows cp1252 safety — emojis below).
for _stream in (sys.stdout, sys.stderr):
    try:
        _stream.reconfigure(encoding="utf-8", line_buffering=True)
    except Exception:  # pragma: no cover
        pass

_spec = importlib.util.spec_from_file_location(
    "_fetch_site_state", os.path.join(os.path.dirname(__file__), "_fetch_site_state.py"))
_fss = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(_fss)

# Keys this QA mutates — restored verbatim from the snapshot at the end.
TOUCHED = [
    "schema_type", "org_name", "org_url",
    "specific_serves_cuisine", "specific_menu_url",
    "specific_accepts_reservations", "specific_currencies_accepted",
    "specific_medical_specialty",
]


def extract_csrf(html):
    # The AI Boost SPA exposes the Joomla form-token *name* (32-hex, value "1")
    # as window.aiBoostToken / csrfToken / tokenName, not always a hidden input.
    for p in (
        r'aiBoostToken\s*=\s*["\']?([a-f0-9]{32})',
        r'csrfToken["\']?\s*[:=]\s*["\']([a-f0-9]{32})',
        r'tokenName["\']?\s*[:=]\s*["\']([a-f0-9]{32})',
        r'name="([a-f0-9]{32})"[^>]*value="1"',
        r'value="1"[^>]*name="([a-f0-9]{32})"',
    ):
        m = re.search(p, html)
        if m:
            return m.group(1)
    return None


def export_settings(s, admin_php):
    r = s.get(admin_php + "?option=com_aiboost&task=settings.export", timeout=60)
    data = json.loads(r.text.strip())
    return data.get("params") or {}


def import_params(s, admin_php, params, label):
    # Fresh CSRF from an AI Boost admin view.
    csrf = None
    for view in ("?option=com_aiboost&view=import", "?option=com_aiboost",
                 "?option=com_aiboost&view=settings"):
        csrf = extract_csrf(s.get(admin_php + view, timeout=60).text)
        if csrf:
            break
    if not csrf:
        sys.exit("❌ No CSRF token for import")
    payload = json.dumps({"meta": {"plugin": "pkg_aiboost"}, "params": params}).encode("utf-8")
    r = s.post(
        admin_php + "?option=com_aiboost&task=import.upload",
        data={"option": "com_aiboost", "task": "import.upload", csrf: "1"},
        files={"ab_import_file": ("qa.json", payload, "application/json")},
        allow_redirects=True, timeout=120,
    )
    m = re.search(r'"success"\s*:\s*(true|false)', r.text)
    ok = bool(m and m.group(1) == "true")
    print(f"   {'✓' if ok else '✗'} import [{label}]: {'ok' if ok else r.text[:200]}")
    return ok


def fetch_jsonld(s, base):
    """Return a flat list of all JSON-LD objects on the homepage (cache-busted)."""
    url = base.rstrip("/") + "/?ab_qa=" + str(int(time.time()))
    html = s.get(url, timeout=60, headers={"Cache-Control": "no-cache", "Pragma": "no-cache"}).text
    objs = []
    for block in re.findall(
        r'<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>',
        html, re.DOTALL | re.IGNORECASE,
    ):
        try:
            data = json.loads(block.strip())
        except Exception:
            continue
        items = data.get("@graph") if isinstance(data, dict) and "@graph" in data else data
        objs.extend(items if isinstance(items, list) else [items])
    return objs, url


def find_type(objs, type_name):
    for o in objs:
        if isinstance(o, dict) and o.get("@type") == type_name:
            return o
    return None


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--target", default="staging", choices=["staging", "free"])
    args = ap.parse_args()

    admin_url, user, passwd = _fss.env(args.target)
    parsed = urllib.parse.urlparse(admin_url)
    base = f"{parsed.scheme}://{parsed.netloc}"
    print(f"🟢 Target: {args.target} ({base})")
    s, admin_php = _fss.login(admin_url, user, passwd)

    print("📤 Snapshotting current settings (settings.export)…")
    snap = export_settings(s, admin_php)
    print(f"   ✓ {len(snap)} params captured; current schema_type={snap.get('schema_type')!r}")

    # Ensure the Organization block emits (needs a non-empty org_name).
    org_name = snap.get("org_name") or "AI Boost QA Org"
    org_url  = snap.get("org_url")  or (base + "/")

    results = []  # (label, ok, detail)

    try:
        # ── Pass 1: Restaurant — menu / reservations / currencies + @id graph ──
        print("\n── Pass 1: Restaurant ──")
        import_params(s, admin_php, {
            "schema_type": "Restaurant",
            "org_name": org_name,
            "org_url": org_url,
            "specific_serves_cuisine": "Mediterranean",
            "specific_menu_url": "/qa-menu.pdf",
            "specific_accepts_reservations": "true",
            "specific_currencies_accepted": "EUR, RSD",
        }, "restaurant")
        time.sleep(2)
        objs, url = fetch_jsonld(s, base)
        print(f"   fetched {len(objs)} JSON-LD node(s) from {url}")
        rest = find_type(objs, "Restaurant")
        site = find_type(objs, "WebSite")

        def check(label, cond, detail=""):
            results.append((label, bool(cond), detail))
            print(f"   {'✅' if cond else '❌'} {label}{(' — ' + detail) if detail else ''}")

        check("Restaurant block present", rest is not None)
        if rest:
            org_id = (org_url.rstrip("/") if org_url else base.rstrip("/")) + "/#organization"
            check("hasMenu emitted", rest.get("hasMenu", "").endswith("/qa-menu.pdf"), str(rest.get("hasMenu")))
            check("acceptsReservations is bool true", rest.get("acceptsReservations") is True, repr(rest.get("acceptsReservations")))
            check("currenciesAccepted emitted", rest.get("currenciesAccepted") == "EUR, RSD", str(rest.get("currenciesAccepted")))
            check("Organization @id present", str(rest.get("@id", "")).endswith("/#organization"), str(rest.get("@id")))
        if site:
            check("WebSite @id present", str(site.get("@id", "")).endswith("/#website"), str(site.get("@id")))
            pub = site.get("publisher") or {}
            check("WebSite publisher → org @id", str(pub.get("@id", "")).endswith("/#organization"), str(pub.get("@id")))
        else:
            check("WebSite block present (homepage)", False, "no WebSite node — is base URL the real homepage?")

        # ── Pass 2: MedicalClinic — medicalSpecialty ──
        print("\n── Pass 2: MedicalClinic ──")
        import_params(s, admin_php, {
            "schema_type": "MedicalClinic",
            "specific_medical_specialty": "Cardiology",
        }, "medical")
        time.sleep(2)
        objs2, _ = fetch_jsonld(s, base)
        clinic = find_type(objs2, "MedicalClinic")
        results.append(("MedicalClinic block present", clinic is not None, ""))
        print(f"   {'✅' if clinic else '❌'} MedicalClinic block present")
        ms_ok = bool(clinic and clinic.get("medicalSpecialty") == "Cardiology")
        results.append(("medicalSpecialty emitted", ms_ok, str(clinic.get("medicalSpecialty") if clinic else None)))
        print(f"   {'✅' if ms_ok else '❌'} medicalSpecialty emitted")

    finally:
        # ── Restore every touched key to its snapshot value ──
        print("\n♻️  Restoring original settings…")
        restore = {k: snap.get(k, "") for k in TOUCHED}
        # schema_type must never be left blank.
        if not restore.get("schema_type"):
            restore["schema_type"] = "Organization"
        import_params(s, admin_php, restore, "restore")
        after = export_settings(s, admin_php)
        same = after.get("schema_type") == (snap.get("schema_type") or "Organization") or after.get("schema_type") == snap.get("schema_type")
        print(f"   {'✓' if same else '⚠️'} schema_type now {after.get('schema_type')!r} (was {snap.get('schema_type')!r})")

    print("\n" + "═" * 56)
    passed = sum(1 for _, ok, _ in results if ok)
    total = len(results)
    overall = passed == total
    print(f"OVERALL: {'✅ PASS' if overall else '❌ FAIL'}  ({passed}/{total})")
    print("═" * 56)
    sys.exit(0 if overall else 1)


if __name__ == "__main__":
    main()
