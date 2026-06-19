#!/usr/bin/env python3
"""
AI Boost — Joomla 5/6 test matrix: install + verify.

Usage:
  python3 scripts/test-matrix.py --install-base       # install Free on all 4 sites
  python3 scripts/test-matrix.py --install-pro        # install Pro on the 2 pro sites
  python3 scripts/test-matrix.py --verify             # verify all 4 sites (front + admin + health)
  python3 scripts/test-matrix.py --all                # run all steps in order

Credentials are read from environment variables — never hardcoded:
  TESTMYWEB_ADMIN_USER   (Joomla admin username)
  TESTMYWEB_ADMIN_PASS   (Joomla admin password)

Or create .local/test-matrix.env (gitignored) with:
  TESTMYWEB_ADMIN_USER=youruser
  TESTMYWEB_ADMIN_PASS=yourpass
"""

import argparse
import datetime
import importlib.util
import json
import os
import re
import subprocess
import sys
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent

# ---------------------------------------------------------------------------
# Credentials — loaded from env; never hardcoded.
# ---------------------------------------------------------------------------

def _load_env_file():
    """Load .local/test-matrix.env if it exists (gitignored local config)."""
    env_file = ROOT / ".local" / "test-matrix.env"
    if env_file.exists():
        for line in env_file.read_text().splitlines():
            line = line.strip()
            if line and not line.startswith("#") and "=" in line:
                k, _, v = line.partition("=")
                os.environ.setdefault(k.strip(), v.strip())


_load_env_file()


def _require_creds():
    user = os.environ.get("TESTMYWEB_ADMIN_USER", "")
    passwd = os.environ.get("TESTMYWEB_ADMIN_PASS", "")
    if not user or not passwd:
        sys.exit(
            "❌  TESTMYWEB_ADMIN_USER and TESTMYWEB_ADMIN_PASS must be set "
            "(env vars or .local/test-matrix.env)."
        )
    return user, passwd


# ---------------------------------------------------------------------------
# Site registry (no credentials here)
# ---------------------------------------------------------------------------

SITES: dict = {
    "joomla5-free": {
        "admin_url": "https://joomla5-free.testmyweb.info/administrator/",
        "front_url": "https://joomla5-free.testmyweb.info/",
        "is_pro": False,
    },
    "joomla5-pro": {
        "admin_url": "https://joomla5-pro.testmyweb.info/administrator/",
        "front_url": "https://joomla5-pro.testmyweb.info/",
        "is_pro": True,
    },
    "joomla6-free": {
        "admin_url": "https://joomla6-free.testmyweb.info/administrator/",
        "front_url": "https://joomla6-free.testmyweb.info/",
        "is_pro": False,
    },
    "joomla6-pro": {
        "admin_url": "https://joomla6-pro.testmyweb.info/administrator/",
        "front_url": "https://joomla6-pro.testmyweb.info/",
        "is_pro": True,
    },
}

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def _fss():
    """Load _fetch_site_state module fresh each call (avoids cached state)."""
    spec = importlib.util.spec_from_file_location(
        "_fss_" + str(time.monotonic_ns()),
        str(ROOT / "scripts" / "_fetch_site_state.py"),
    )
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


def make_env(admin_url: str, user: str, passwd: str) -> dict:
    env = dict(os.environ)
    env["STAGING_URL"]           = admin_url
    env["STAGING_ADMIN_USER"]    = user
    env["STAGING_ADMIN_PASS"]    = passwd
    env["AIBOOST_NO_SSL_VERIFY"] = "1"   # testmyweb.info uses self-signed / incomplete chain
    return env


def _extract_csrf(html: str) -> str | None:
    for p in [
        r'aiBoostToken\s*=\s*["\']?([a-f0-9]{32})',
        r'csrfToken["\']?\s*[:=]\s*["\']([a-f0-9]{32})',
        r'tokenName["\']?\s*[:=]\s*["\']([a-f0-9]{32})',
        r'name="([a-f0-9]{32})"[^>]*value="1"',
    ]:
        m = re.search(p, html)
        if m:
            return m.group(1)
    return None

# ---------------------------------------------------------------------------
# Install
# ---------------------------------------------------------------------------

def _zip_path(is_pro: bool) -> str:
    """Return the latest ZIP for Free or Pro."""
    folder = ROOT / "deliverables" / "plugin"
    pattern = "pkg_aiboost_pro-*.zip" if is_pro else "pkg_aiboost-*.zip"
    candidates = sorted(folder.glob(pattern), reverse=True)
    if not candidates:
        sys.exit(f"❌  No ZIP matching {pattern} in {folder}")
    return str(candidates[0])


def run_install(admin_url: str, zip_path: str, label: str, user: str, passwd: str) -> bool:
    print(f"\n{'='*60}")
    print(f"  INSTALL → {label}  ({Path(zip_path).name})")
    print(f"  {admin_url}")
    print(f"{'='*60}")
    r = subprocess.run(
        [sys.executable, "scripts/install-to-staging.py", "--zip", zip_path],
        env=make_env(admin_url, user, passwd),
        timeout=120,
    )
    return r.returncode == 0

# ---------------------------------------------------------------------------
# Settings import helper (admin import endpoint)
# ---------------------------------------------------------------------------

def import_settings(admin_url: str, payload: dict, label: str, user: str, passwd: str) -> bool:
    """POST a tiny settings JSON to the AI Boost import endpoint."""
    fss = _fss()
    try:
        s, admin_php = fss.login(admin_url, user, passwd)
    except Exception as e:
        print(f"  ❌ login failed for {label}: {e}")
        return False

    csrf = None
    for view in ("?option=com_aiboost", "?option=com_aiboost&view=settings"):
        r = s.get(admin_php + view, timeout=30)
        csrf = _extract_csrf(r.text)
        if csrf:
            break

    if not csrf:
        print(f"  ❌ no CSRF token for {label}")
        return False

    body = json.dumps({"format": "v1.0", "params": payload, "translations": []}).encode()
    r2 = s.post(
        admin_php + "?option=com_aiboost&task=import.upload",
        data={"option": "com_aiboost", "task": "import.upload", csrf: "1"},
        files={"ab_import_file": ("settings.json", body, "application/json")},
        allow_redirects=True,
        timeout=60,
    )
    txt = r2.text.strip()
    ok = '"success":true' in txt
    msg = "ok" if ok else (re.search(r'"message"\s*:\s*"([^"]+)"', txt) or [None, txt[:100]])[1]
    print(f"  {'✅' if ok else '⚠️ '} import on {label}: {msg}")
    return ok

# ---------------------------------------------------------------------------
# Verify
# ---------------------------------------------------------------------------

# Known Pro-only JSON-LD @type values — any of these on a Free site = gating leak.
_PRO_SCHEMA_TYPES = {
    "FAQPage", "HowTo", "HowToStep", "Event", "Recipe", "JobPosting",
    "Course", "SoftwareApplication", "VideoObject", "Speakable",
    "BreadcrumbList",  # breadcrumb is Pro-gated in AI Boost
}


def verify_site(name: str, cfg: dict, user: str, passwd: str) -> dict:
    """
    Return a results dict with keys:
      homepage_200, has_jsonld, has_og_type,
      sitemap, robots, llms,
      admin_loads, health_ran,
      pro_leak (True = Pro schema found on Free — BAD; False on Free = good),
      errors: list[str]
    """
    import requests
    import urllib3
    urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

    front = cfg["front_url"]
    is_pro = cfg["is_pro"]
    result: dict = {"errors": [], "is_pro": is_pro}

    sess = requests.Session()
    sess.headers["User-Agent"] = "Mozilla/5.0"
    sess.verify = False

    # --- Front-end homepage ---
    try:
        r = sess.get(front, timeout=20, allow_redirects=True)
        result["homepage_200"] = r.status_code == 200
        result["has_jsonld"]   = '<script type="application/ld+json">' in r.text
        result["has_og_type"]  = "og:type" in r.text
        # Pro-schema-type leakage check
        if result["has_jsonld"]:
            found_pro_types = set()
            for block in re.findall(r'<script[^>]+application/ld\+json[^>]*>(.*?)</script>',
                                    r.text, re.DOTALL):
                try:
                    obj = json.loads(block)
                    types_in_block = set()
                    if isinstance(obj, dict):
                        types_in_block.add(obj.get("@type", ""))
                        for item in obj.get("@graph", []):
                            types_in_block.add(item.get("@type", ""))
                    elif isinstance(obj, list):
                        for item in obj:
                            types_in_block.add(item.get("@type", ""))
                    found_pro_types |= types_in_block & _PRO_SCHEMA_TYPES
                except Exception:
                    pass
            result["pro_schema_types_found"] = sorted(found_pro_types)
            # On Free sites: any Pro type = leak; on Pro sites: Pro types expected
            result["pro_leak"] = bool(found_pro_types) and not is_pro
        else:
            result["pro_schema_types_found"] = []
            result["pro_leak"] = False
    except Exception as e:
        result.update(homepage_200=False, has_jsonld=False, has_og_type=False,
                      pro_schema_types_found=[], pro_leak=False)
        result["errors"].append(f"front: {e!s:.80}")

    # --- sitemap / robots / llms ---
    for path, key in [("/sitemap.xml", "sitemap"), ("/robots.txt", "robots"), ("/llms.txt", "llms")]:
        try:
            r2 = sess.get(front.rstrip("/") + path, timeout=15, allow_redirects=True)
            body = r2.text
            if key == "sitemap":
                result[key] = r2.status_code == 200 and ("<urlset" in body or "<sitemapindex" in body)
            elif key == "robots":
                result[key] = r2.status_code == 200 and "user-agent" in body.lower()
            else:
                result[key] = r2.status_code == 200 and len(body.strip()) > 20
        except Exception as e:
            result[key] = False
            result["errors"].append(f"{key}: {e!s:.60}")

    # --- Admin: component loads + Health panel ---
    try:
        fss = _fss()
        sa, aphp = fss.login(cfg["admin_url"], user, passwd)
        r3 = sa.get(aphp + "?option=com_aiboost", timeout=20)
        result["admin_loads"] = ("AI Boost" in r3.text or "aiboost" in r3.text.lower()) \
                                 and r3.status_code == 200

        # Health panel: just confirm the health API responds (even if some items fail)
        r4 = sa.get(aphp + "?option=com_aiboost&view=health&format=json", timeout=25)
        body4 = r4.text
        if r4.status_code == 200 and ('"checks"' in body4 or '"items"' in body4
                                       or '"health"' in body4 or "critical" in body4.lower()
                                       or '"status"' in body4):
            result["health_ran"] = True
        else:
            # Fallback: health view may be HTML SPA
            r4b = sa.get(aphp + "?option=com_aiboost&view=health", timeout=25)
            result["health_ran"] = (r4b.status_code == 200
                                    and ("aiboost" in r4b.text.lower() or "health" in r4b.text.lower()))
    except Exception as e:
        result["admin_loads"] = False
        result["health_ran"]  = False
        result["errors"].append(f"admin: {e!s:.80}")

    return result


def print_result(name: str, r: dict) -> None:
    ck = lambda v: "✅" if v else "❌"
    is_pro = r.get("is_pro", False)
    print(f"\n  {name} ({'Pro' if is_pro else 'Free'})")
    print(f"    Homepage 200   : {ck(r.get('homepage_200'))}")
    print(f"    JSON-LD        : {ck(r.get('has_jsonld'))}")
    print(f"    OG tags        : {ck(r.get('has_og_type'))}")
    print(f"    sitemap.xml    : {ck(r.get('sitemap'))}")
    print(f"    robots.txt     : {ck(r.get('robots'))}")
    print(f"    llms.txt       : {ck(r.get('llms'))}")
    print(f"    Admin loads    : {ck(r.get('admin_loads'))}")
    print(f"    Health panel   : {ck(r.get('health_ran'))}")
    if not is_pro:
        leak = r.get("pro_leak", False)
        types = r.get("pro_schema_types_found", [])
        print(f"    Pro leak (Free): {ck(not leak)}  {('['+','.join(types)+']') if types else ''}")
    for e in r.get("errors", []):
        print(f"    ⚠️  {e}")


# ---------------------------------------------------------------------------
# Serbian report
# ---------------------------------------------------------------------------

def write_report(all_results: dict[str, dict]) -> Path:
    ts = datetime.datetime.now().strftime("%Y-%m-%d %H:%M")
    lines = [
        f"# AI Boost — Test Matrix izvještaj",
        f"",
        f"**Datum:** {ts}  ",
        f"**Verzija:** 0.73.1  ",
        f"",
        "## Rezultati po sajtu",
        "",
    ]
    ck = lambda v: "✅" if v else "❌"
    bugs = []

    for name, r in all_results.items():
        is_pro = r.get("is_pro", False)
        tier = "Pro" if is_pro else "Free"
        lines.append(f"### {name} ({tier})")
        lines.append("")
        lines.append(f"| Provjera | Rezultat |")
        lines.append(f"|---|---|")
        lines.append(f"| Homepage 200 | {ck(r.get('homepage_200'))} |")
        lines.append(f"| JSON-LD blok | {ck(r.get('has_jsonld'))} |")
        lines.append(f"| OG tagovi | {ck(r.get('has_og_type'))} |")
        lines.append(f"| sitemap.xml | {ck(r.get('sitemap'))} |")
        lines.append(f"| robots.txt | {ck(r.get('robots'))} |")
        lines.append(f"| llms.txt | {ck(r.get('llms'))} |")
        lines.append(f"| Admin učitava | {ck(r.get('admin_loads'))} |")
        lines.append(f"| Health panel | {ck(r.get('health_ran'))} |")
        if not is_pro:
            leak = r.get("pro_leak", False)
            types = r.get("pro_schema_types_found", [])
            lines.append(f"| Pro curenje (Free) | {ck(not leak)} {('['+','.join(types)+']') if types else ''} |")
        if r.get("errors"):
            lines.append(f"| Greške | {'; '.join(r['errors'][:3])} |")
        lines.append("")

        # Collect failures for bug section
        for k, v in r.items():
            # pro_leak=False is the GOOD state (no leakage) — skip it here.
            if isinstance(v, bool) and not v and k not in ("is_pro", "pro_leak"):
                bugs.append(f"- `{name}` — {k} je pao")
        if r.get("pro_leak"):
            bugs.append(f"- `{name}` — Pro schema curenje na Free sajtu: {r.get('pro_schema_types_found')}")

    if bugs:
        lines += ["## ⚠️ Potencijalni bugovi (za BACKLOG)", ""]
        lines += bugs
        lines.append("")
    else:
        lines += ["## Zaključak", "", "Sve provjere prošle. Nema pronađenih bugova.", ""]

    report_dir = ROOT / ".local" / "state"
    report_dir.mkdir(parents=True, exist_ok=True)
    out = report_dir / "test-matrix-report.md"
    out.write_text("\n".join(lines), encoding="utf-8")
    return out


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

def main():
    os.environ["AIBOOST_NO_SSL_VERIFY"] = "1"   # testmyweb.info uses unverified certs

    ap = argparse.ArgumentParser(description="AI Boost Joomla 5/6 test matrix")
    ap.add_argument("--install-base",      action="store_true", help="Install Free base on all 4 sites")
    ap.add_argument("--install-pro",       action="store_true", help="Install Pro on the 2 pro sites")
    ap.add_argument("--verify",            action="store_true", help="Verify all 4 sites")
    ap.add_argument("--all",               action="store_true", help="Run all steps (base+pro install, verify)")
    args = ap.parse_args()

    if args.all:
        args.install_base = args.install_pro = args.verify = True

    user, passwd = _require_creds()

    if args.install_base:
        free_zip = _zip_path(is_pro=False)
        print(f"📦 Installing Free ({Path(free_zip).name}) on all 4 sites …")
        for name, cfg in SITES.items():
            run_install(cfg["admin_url"], free_zip, name, user, passwd)
            time.sleep(2)

    if args.install_pro:
        pro_zip = _zip_path(is_pro=True)
        print(f"📦 Installing Pro ({Path(pro_zip).name}) on pro sites …")
        for name, cfg in SITES.items():
            if cfg["is_pro"]:
                run_install(cfg["admin_url"], pro_zip, name, user, passwd)
                time.sleep(2)

    if args.verify:
        print("\n🔍 Verifying all 4 sites (front-end + admin + health + Pro/Free gating) …")
        all_results: dict[str, dict] = {}
        for name, cfg in SITES.items():
            print(f"  → {name} …")
            all_results[name] = verify_site(name, cfg, user, passwd)

        print("\n" + "=" * 60)
        print("  VERIFICATION RESULTS")
        print("=" * 60)
        for name, r in all_results.items():
            print_result(name, r)

        report_path = write_report(all_results)
        print(f"\n📄 Serbian report written → {report_path.relative_to(ROOT)}")


if __name__ == "__main__":
    main()
