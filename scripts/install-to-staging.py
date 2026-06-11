#!/usr/bin/env python3
"""
AI Boost for Joomla — Staging Installer
Automatski instalira ZIP(ove) na staging Joomla instancu.

Korišćenje:
  python3 scripts/install-to-staging.py                        # auto-detect latest pkg_aiboost
  python3 scripts/install-to-staging.py --zip path/to/x.zip   # jedan ZIP
  python3 scripts/install-to-staging.py --all-plugins          # svih 6 plugina (jedna sesija)

Env varijable (moraju biti postavljene):
  STAGING_URL          — Admin Tools secret URL (login forma)
  STAGING_ADMIN_USER   — admin korisničko ime
  STAGING_ADMIN_PASS   — admin lozinka
"""

import argparse
import os
import re
import glob
import sys
import time
import urllib.parse
import requests

# UTF-8 console (Windows cp1252 default chokes on the emoji status lines below).
for _stream in (sys.stdout, sys.stderr):
    try:
        _stream.reconfigure(encoding="utf-8", line_buffering=True)
    except Exception:
        pass

ADMIN_URL  = os.environ["STAGING_URL"]
_parsed    = urllib.parse.urlparse(ADMIN_URL)
_base      = f"{_parsed.scheme}://{_parsed.netloc}"
ADMIN_PHP  = f"{_base}/administrator/index.php"
USER       = os.environ["STAGING_ADMIN_USER"]
PASS       = os.environ["STAGING_ADMIN_PASS"]

DELIVERABLES_DIR = os.path.join(os.path.dirname(__file__), "..", "deliverables", "plugin")

PLUGIN_SLUGS = [
    "aiboost_schema",
    "aiboost_sitemap",
    "aiboost_social",
    "aiboost_analytics",
    "aiboost_aeo",
    "aiboost_core",
]


def find_latest_zip() -> str:
    pkg_zips = glob.glob(os.path.join(DELIVERABLES_DIR, "pkg_aiboost-*.zip"))
    if pkg_zips:
        return max(pkg_zips, key=os.path.getmtime)
    legacy_zips = glob.glob(os.path.join(DELIVERABLES_DIR, "plg_system_joomlaboost-*.zip"))
    if legacy_zips:
        return max(legacy_zips, key=os.path.getmtime)
    print("❌ Nema ZIP fajla u deliverables/plugin/")
    sys.exit(1)


def find_plugin_zips() -> list[str]:
    zips = []
    for slug in PLUGIN_SLUGS:
        matches = sorted(
            glob.glob(os.path.join(DELIVERABLES_DIR, f"plg_system_{slug}-*.zip")),
            key=os.path.getmtime,
        )
        if matches:
            zips.append(matches[-1])
        else:
            print(f"  ⚠️  ZIP nije pronađen za: {slug}")
    return zips


def extract_csrf(html: str) -> str | None:
    for p in [r'name="([a-f0-9]{32})"[^>]*value="1"',
              r'value="1"[^>]*name="([a-f0-9]{32})"']:
        m = re.search(p, html)
        if m:
            return m.group(1)
    return None


def follow_js_redirect(session: requests.Session, response: requests.Response) -> requests.Response:
    """Follow a JavaScript document.location.href redirect if present."""
    m = re.search(r'document\.location\.href=["\']([^"\']+)["\']', response.text)
    if m:
        url = m.group(1).replace("\\/", "/")
        print(f"   ↪ JS redirect → {url[:70]}")
        return session.get(url, allow_redirects=True, timeout=30)
    return response


ADMIN_FALLBACK = ADMIN_PHP


def get_login_page(session: requests.Session) -> requests.Response:
    """Fetch login page, trying ADMIN_URL first and ADMIN_FALLBACK if needed."""
    candidates = [ADMIN_URL]
    if ADMIN_FALLBACK not in candidates:
        candidates.append(ADMIN_FALLBACK)
    r = None
    for url in candidates:
        r = session.get(url, allow_redirects=True, timeout=30)
        r = follow_js_redirect(session, r)
        if extract_csrf(r.text):
            if url != ADMIN_URL:
                print(f"   ℹ️  Primary URL nije imao login formu — koristim {url[:70]}")
            return r
        print(f"   ⚠️  {url[:70]} — nema login forme (HTTP {r.status_code})")
    return r  # last response so error reporting can print it


def login() -> requests.Session:
    session = requests.Session()
    session.headers.update({"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)"})
    if os.environ.get("AIBOOST_NO_SSL_VERIFY"):
        import urllib3; urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)
        session.verify = False

    print("🔐 Dohvatam login formu...")
    r_login = get_login_page(session)
    csrf = extract_csrf(r_login.text)
    if not csrf:
        print(f"❌ Nema CSRF token (HTTP {r_login.status_code})")
        print(f"   URL: {r_login.url[:80]}")
        print(f"   Odgovor (prvih 300 znakova): {r_login.text[:300]}")
        sys.exit(1)
    print(f"   CSRF: {csrf[:8]}...")

    login_post_url = r_login.url  # use the final URL after any JS redirect

    time.sleep(2)
    print("🔑 Login...")
    r_auth = session.post(
        login_post_url,
        data={"username": USER, "passwd": PASS, "option": "com_login",
              "task": "login", "return": "aW5kZXgucGhw", csrf: "1"},
        allow_redirects=True,
        timeout=30,
    )
    print(f"   → {r_auth.url[:70]}")
    time.sleep(2)
    return session


def install_zip(session: requests.Session, zip_path: str) -> bool:
    zip_name = os.path.basename(zip_path)
    print(f"\n📦 {zip_name} ({os.path.getsize(zip_path) // 1024} KB)")

    r_inst = session.get(ADMIN_PHP + "?option=com_installer&view=install", timeout=30)
    csrf2 = extract_csrf(r_inst.text)
    if not csrf2:
        print(f"   ❌ Nema CSRF na installer stranici (HTTP {r_inst.status_code})")
        return False

    time.sleep(1)
    with open(zip_path, "rb") as f:
        zip_data = f.read()

    r_up = session.post(
        ADMIN_PHP + "?option=com_installer&view=install",
        data={"option": "com_installer", "task": "install.install",
              "installtype": "upload", "return": "", csrf2: "1"},
        files={"install_package": (zip_name, zip_data, "application/zip")},
        allow_redirects=True,
        timeout=120,
    )

    import html as html_lib

    html = r_up.text

    # Detect real errors: alert-danger as an HTML element class (not CSS rule)
    # Joomla's admin CSS always contains "alert-danger" as a CSS selector — ignore those.
    real_errors = re.findall(r'class="[^"]*alert-danger[^"]*"[^>]*>(.*?)</div>', html, re.DOTALL)
    real_errors += re.findall(r'class="[^"]*alert-error[^"]*"[^>]*>(.*?)</div>', html, re.DOTALL)
    error_msgs = []
    for e in real_errors[:3]:
        msg = html_lib.unescape(re.sub(r'<[^>]+>', '', e)).strip()
        if msg and 'javascript' not in msg.lower() and len(msg) > 10:
            error_msgs.append(msg)

    # Detect success: Joomla 4/5/6 all say "successful" in the install result message
    is_success = 'successful' in html.lower() and r_up.status_code == 200

    if is_success and not error_msgs:
        print(f"   ✅ Uspješno")
        return True

    if error_msgs:
        for msg in error_msgs:
            print(f"   ❌ {msg[:300]}")
        return False

    # Fallback: staging alive but unexpected response
    body_text = re.sub(r'<[^>]+>', ' ', html)
    body_text = re.sub(r'\s+', ' ', body_text).strip()
    print(f"   ❌ Neočekivan odgovor. HTTP {r_up.status_code}. Snippet: {body_text[100:400]}")
    return False


def main() -> None:
    parser = argparse.ArgumentParser(description="Install AI Boost ZIP(ove) to staging")
    group = parser.add_mutually_exclusive_group()
    group.add_argument("--zip", help="Jedan specifičan ZIP fajl")
    group.add_argument("--all-plugins", action="store_true",
                       help="Instaliraj svih 6 plugina u jednoj sesiji")
    args = parser.parse_args()

    if args.all_plugins:
        zips = find_plugin_zips()
        if not zips:
            print("❌ Nema plugin ZIPova")
            sys.exit(1)
        print(f"🔧 Instalacija {len(zips)} plugina u jednoj sesiji...")
        session = login()
        ok = 0
        for zp in zips:
            if install_zip(session, zp):
                ok += 1
            time.sleep(2)
        print(f"\n{'✅' if ok == len(zips) else '⚠️ '} {ok}/{len(zips)} instaliranih")
        print(f"🌐 {_base}/administrator/")
    else:
        zip_path = args.zip if args.zip else find_latest_zip()
        if not os.path.isfile(zip_path):
            print(f"❌ ZIP nije pronađen: {zip_path}")
            sys.exit(1)
        print(f"📦 ZIP: {os.path.basename(zip_path)} ({os.path.getsize(zip_path) // 1024} KB)")
        session = login()
        ok = install_zip(session, zip_path)
        if ok:
            print(f"🌐 {_base}/administrator/")
        else:
            sys.exit(1)


if __name__ == "__main__":
    main()
