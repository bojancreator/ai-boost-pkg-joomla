#!/usr/bin/env python3
"""
AI Boost — test-matrix installer (Plan 3 ops).

Installs the COMPLETE current AI Boost set across every Joomla site in the test
matrix, picking the right packages per edition, with ONE admin login per site.

A complete install is more than the main package — the integration bridges ship
as their own ZIPs:
  Free edition:  pkg_aiboost (base component + free plugins)
               + plg_system_aiboost_int_falang   (free Multilang bridge)
               + plg_system_aiboost_int_yootheme (free YOOtheme bridge)
  Pro edition:   pkg_aiboost (base)               <-- Pro pkg has NO base, needs this first
               + pkg_aiboost_pro                  (Pro add-on plugins + health module)
               + plg_system_aiboost_int_falang_pro
               + plg_system_aiboost_int_yootheme_pro

Sites (public admin URLs hardcoded; only credentials come from env):
  free / staging                              -> FREE_* / STAGING_*  (offroadbalkans / offroadserbia)
  j5free / j5pro / j6free / j6pro             -> TESTMYWEB_*         (testmyweb.info; self-signed TLS)

Usage (always via the creds wrapper so secrets stay off the command line):
  python _creds_run.py scripts/install-matrix.py --check                 # read-only login + version
  python _creds_run.py scripts/install-matrix.py --sites j5pro,j6pro      # install subset
  python _creds_run.py scripts/install-matrix.py                          # install ALL sites
testmyweb needs TLS verification skipped — set TESTMYWEB_NO_SSL_VERIFY=1 (env or CREDENTIALS.local.md).

@copyright (C) 2025 AI Boost (aiboostnow.com). GPL v2 or later.
"""

from __future__ import annotations

import argparse
import glob
import html as html_lib
import importlib.util
import os
import re
import sys
import time
import urllib.parse

HERE = os.path.dirname(os.path.abspath(__file__))
REPO = os.path.dirname(HERE)
DELIVERABLES = os.path.join(REPO, "deliverables", "plugin")

for _s in (sys.stdout, sys.stderr):
    try:
        _s.reconfigure(encoding="utf-8", line_buffering=True)
    except Exception:
        pass

# key -> (admin_url_or_env, user_env, pass_env, edition, no_ssl_env)
SITES = {
    "free":    ("env:FREE_URL", "FREE_ADMIN_USER", "FREE_ADMIN_PASS", "free", "AIBOOST_NO_SSL_VERIFY"),
    "staging": ("env:STAGING_URL", "STAGING_ADMIN_USER", "STAGING_ADMIN_PASS", "pro", "AIBOOST_NO_SSL_VERIFY"),
    "j5free":  ("https://joomla5-free.testmyweb.info/administrator/", "TESTMYWEB_ADMIN_USER", "TESTMYWEB_ADMIN_PASS", "free", "TESTMYWEB_NO_SSL_VERIFY"),
    "j5pro":   ("https://joomla5-pro.testmyweb.info/administrator/",  "TESTMYWEB_ADMIN_USER", "TESTMYWEB_ADMIN_PASS", "pro",  "TESTMYWEB_NO_SSL_VERIFY"),
    "j6free":  ("https://joomla6-free.testmyweb.info/administrator/", "TESTMYWEB_ADMIN_USER", "TESTMYWEB_ADMIN_PASS", "free", "TESTMYWEB_NO_SSL_VERIFY"),
    "j6pro":   ("https://joomla6-pro.testmyweb.info/administrator/",  "TESTMYWEB_ADMIN_USER", "TESTMYWEB_ADMIN_PASS", "pro",  "TESTMYWEB_NO_SSL_VERIFY"),
}

_qa_spec = importlib.util.spec_from_file_location("_qa_common", os.path.join(HERE, "_qa_common.py"))
qa = importlib.util.module_from_spec(_qa_spec)
_qa_spec.loader.exec_module(qa)


def resolve_url(spec: str) -> str:
    return os.environ.get(spec[4:], "") if spec.startswith("env:") else spec


def _latest(pattern: str, exclude: str | None = None) -> str:
    cands = glob.glob(os.path.join(DELIVERABLES, pattern))
    if exclude:
        cands = [c for c in cands if exclude not in os.path.basename(c)]
    if not cands:
        sys.exit(f"❌ no ZIP matching {pattern}" + (f" (excl {exclude})" if exclude else "") + f" in {DELIVERABLES}")
    return max(cands, key=os.path.getmtime)


def edition_zips(edition: str, skip_base: bool = False) -> list[str]:
    """Ordered list of ZIPs to install for an edition (base first).

    skip_base drops the heavy 627 KB pkg_aiboost base — use it on sites that are
    already on the current base and only need the integration bridges.
    """
    base = _latest("pkg_aiboost-*.zip")  # '_pro-' does not match this glob
    if edition == "pro":
        # The combined Pro edition (pkg_aiboost_pro, packagename `aiboost`) IS the
        # base built full — installing it creates/upgrades the aiboost package in
        # place AND sweeps the legacy *_pro decorators. No separate Free base step.
        zips = [
            _latest("pkg_aiboost_pro-*.zip"),
            _latest("plg_system_aiboost_int_falang_pro-*.zip"),
            _latest("plg_system_aiboost_int_yootheme_pro-*.zip"),
        ]
    else:
        zips = [
            base,
            _latest("plg_system_aiboost_int_falang-*.zip", exclude="_pro-"),
            _latest("plg_system_aiboost_int_yootheme-*.zip", exclude="_pro-"),
        ]
    return [z for z in zips if not (skip_base and os.path.basename(z).startswith("pkg_aiboost-"))]


def site_creds(key: str):
    url_spec, user_env, pass_env, edition, ssl_env = SITES[key]
    return (resolve_url(url_spec), os.environ.get(user_env, ""),
            os.environ.get(pass_env, ""), edition, not bool(os.environ.get(ssl_env)))


def install_zip(session, admin_php: str, zip_path: str, timeout: int = 300) -> str:
    """Upload + install one ZIP through com_installer.

    Returns 'ok' | 'uncertain' | 'fail'. NEVER raises — a slow upload on one
    package must not abort the whole matrix. A read-timeout is reported as
    'uncertain' (the server often finished anyway); the end-of-site version
    check and the verification harness catch any real damage.
    """
    name = os.path.basename(zip_path)
    try:
        r = session.get(admin_php + "?option=com_installer&view=install", timeout=60)
        csrf = qa.extract_csrf(r.text)
        if not csrf:
            print(f"     ❌ {name}: no CSRF on installer page (HTTP {r.status_code})")
            return "fail"
        with open(zip_path, "rb") as f:
            data = f.read()
        r = session.post(
            admin_php + "?option=com_installer&view=install",
            data={"option": "com_installer", "task": "install.install",
                  "installtype": "upload", "return": "", csrf: "1"},
            files={"install_package": (name, data, "application/zip")},
            allow_redirects=True, timeout=timeout,
        )
    except requests.exceptions.RequestException as e:
        print(f"     ⚠️ {name}: {type(e).__name__} — may have installed server-side; will verify at end")
        return "uncertain"
    body = r.text
    errs = re.findall(r'class="[^"]*alert-(?:danger|error)[^"]*"[^>]*>(.*?)</div>', body, re.DOTALL)
    msgs = []
    for e in errs[:3]:
        m = html_lib.unescape(re.sub(r"<[^>]+>", "", e)).strip()
        if m and "javascript" not in m.lower() and len(m) > 10:
            msgs.append(m)
    if "successful" in body.lower() and r.status_code == 200 and not msgs:
        print(f"     ✅ {name} ({len(data)//1024} KB)")
        return "ok"
    for m in msgs:
        print(f"     ❌ {name}: {m[:200]}")
    if not msgs:
        print(f"     ❌ {name}: unexpected response HTTP {r.status_code}")
    return "fail"


def detect_version(session, admin_php) -> str:
    try:
        caps = qa.get_capabilities(session, admin_php)
        v = caps.get("version") or (caps.get("meta") or {}).get("version")
        return str(v) if v else f"{len(caps.get('fields') or [])} fields"
    except Exception as e:
        return f"?({e})"


def check(keys: list[str]) -> int:
    rc = 0
    print("🔎 Read-only access check:")
    for key in keys:
        url, user, passwd, edition, verify = site_creds(key)
        host = urllib.parse.urlparse(url).netloc or "(no url)"
        if not url or not user or not passwd:
            print(f"  ⚠️ {key}: missing url/creds")
            rc = 1
            continue
        try:
            s, admin_php = qa.login(url, user, passwd, verify=verify)
            print(f"  ✅ {key} [{edition}] {host} — {detect_version(s, admin_php)}")
        except Exception as e:
            print(f"  ❌ {key} [{edition}] {host} — {e}")
            rc = 1
    return rc


def install(keys: list[str], skip_base: bool = False) -> int:
    rc = 0
    for key in keys:
        url, user, passwd, edition, verify = site_creds(key)
        host = urllib.parse.urlparse(url).netloc or "(no url)"
        zips = edition_zips(edition, skip_base=skip_base)
        print(f"\n=== {key} [{edition}] {host} ({len(zips)} packages) ===")
        if not url or not user or not passwd:
            print("  ⚠️ skipped — missing url/creds")
            rc = 1
            continue
        try:
            s, admin_php = qa.login(url, user, passwd, verify=verify)
        except Exception as e:
            print(f"  ❌ login failed: {e}")
            rc = 1
            continue
        ok = unc = bad = 0
        for zp in zips:
            status = install_zip(s, admin_php, zp)
            ok += status == "ok"
            unc += status == "uncertain"
            bad += status == "fail"
            time.sleep(1)
        print(f"  → {ok} ok, {unc} uncertain, {bad} failed of {len(zips)}; now: {detect_version(s, admin_php)}")
        if bad:
            rc = 1
    return rc


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--sites", default="all", help="comma list of site keys or 'all'")
    ap.add_argument("--check", action="store_true", help="read-only: log in and report version")
    ap.add_argument("--skip-base", action="store_true",
                    help="don't reinstall the pkg_aiboost base (use when it's already current)")
    args = ap.parse_args()

    keys = list(SITES) if args.sites == "all" else [k.strip() for k in args.sites.split(",") if k.strip()]
    bad = [k for k in keys if k not in SITES]
    if bad:
        sys.exit(f"unknown site(s): {bad} (known: {', '.join(SITES)})")

    if args.check:
        return check(keys)
    print(f"📦 Free set: {[os.path.basename(z) for z in edition_zips('free', args.skip_base)]}")
    print(f"📦 Pro set:  {[os.path.basename(z) for z in edition_zips('pro', args.skip_base)]}")
    return install(keys, skip_base=args.skip_base)


if __name__ == "__main__":
    sys.exit(main())
