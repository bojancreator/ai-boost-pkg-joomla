#!/usr/bin/env python3
"""
Read-only helper: log into a Joomla site (staging or free) via the admin form and
dump (1) the live AI Boost settings export JSON and (2) the installed content
languages from com_languages. Used to gather ground truth before building a
comprehensive settings-import dataset. Does NOT modify the site.

Usage:
  python3 scripts/_fetch_site_state.py --target staging
  python3 scripts/_fetch_site_state.py --target free
"""

import argparse
import os
import re
import sys
import time
import json
import urllib.parse

import requests


def env(target: str):
    if target == "staging":
        return os.environ["STAGING_URL"], os.environ["STAGING_ADMIN_USER"], os.environ["STAGING_ADMIN_PASS"]
    if target == "free":
        return os.environ["FREE_URL"], os.environ["FREE_ADMIN_USER"], os.environ["FREE_ADMIN_PASS"]
    sys.exit(f"unknown target {target}")


def extract_csrf(html: str):
    for p in [r'name="([a-f0-9]{32})"[^>]*value="1"', r'value="1"[^>]*name="([a-f0-9]{32})"']:
        m = re.search(p, html)
        if m:
            return m.group(1)
    return None


def follow_js_redirect(session, response):
    m = re.search(r'document\.location\.href=["\']([^"\']+)["\']', response.text)
    if m:
        url = m.group(1).replace("\\/", "/")
        return session.get(url, allow_redirects=True, timeout=30)
    return response


def login(admin_url, user, passwd):
    parsed = urllib.parse.urlparse(admin_url)
    base = f"{parsed.scheme}://{parsed.netloc}"
    admin_php = f"{base}/administrator/index.php"
    s = requests.Session()
    s.headers.update({"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)"})
    if os.environ.get("AIBOOST_NO_SSL_VERIFY"):
        import urllib3; urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)
        s.verify = False
    r = s.get(admin_url, allow_redirects=True, timeout=30)
    r = follow_js_redirect(s, r)
    if not extract_csrf(r.text):
        r = s.get(admin_php, allow_redirects=True, timeout=30)
        r = follow_js_redirect(s, r)
    csrf = extract_csrf(r.text)
    if not csrf:
        print(f"❌ No CSRF (HTTP {r.status_code}) at {r.url[:80]}")
        sys.exit(1)
    time.sleep(2)
    s.post(r.url, data={"username": user, "passwd": passwd, "option": "com_login",
                        "task": "login", "return": "aW5kZXgucGhw", csrf: "1"},
           allow_redirects=True, timeout=30)
    time.sleep(2)
    return s, admin_php


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--target", required=True, choices=["staging", "free"])
    args = ap.parse_args()
    admin_url, user, passwd = env(args.target)

    print(f"🔐 Login → {args.target}")
    s, admin_php = login(admin_url, user, passwd)

    os.makedirs(".local/state", exist_ok=True)

    # 1) AI Boost settings export
    print("📤 settings.export")
    r = s.get(admin_php + "?option=com_aiboost&task=settings.export", timeout=60)
    txt = r.text.strip()
    out_settings = f".local/state/{args.target}-settings-export.json"
    try:
        data = json.loads(txt)
        with open(out_settings, "w", encoding="utf-8") as f:
            json.dump(data, f, ensure_ascii=False, indent=2)
        keys = list((data.get("params") or {}).keys())
        print(f"   ✓ {out_settings} — {len(keys)} params, meta={data.get('meta')}")
    except Exception:
        with open(out_settings + ".raw.html", "w", encoding="utf-8") as f:
            f.write(txt)
        print(f"   ⚠️ not JSON (HTTP {r.status_code}); raw saved → {out_settings}.raw.html ; snippet: {txt[:200]!r}")

    # 2) Installed content languages (#__languages) via com_languages
    print("🌐 content languages")
    r = s.get(admin_php + "?option=com_languages&view=languages", timeout=60)
    html = r.text
    # Joomla lists each row with a language tag like xx-XX; capture tag + nearby sef/title heuristically.
    tags = sorted(set(re.findall(r'\b([a-z]{2}-[A-Z]{2})\b', html)))
    out_html = f".local/state/{args.target}-languages.html"
    with open(out_html, "w", encoding="utf-8") as f:
        f.write(html)
    print(f"   ✓ candidate lang tags: {tags}")
    print(f"   (raw HTML saved → {out_html} for precise sef/title parsing)")


if __name__ == "__main__":
    main()
