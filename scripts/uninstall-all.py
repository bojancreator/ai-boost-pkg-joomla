#!/usr/bin/env python3
"""
AI Boost — full extension wipe across the test sites (HTTP, no DB).

Removes EVERY AI Boost (and legacy JoomlaBoost) extension from each target via
com_installer's manage view: the package(s) (Joomla cascades the component + core
plugins), the standalone paid bridges (int_falang / int_yootheme, free+pro), the
admin module, and the legacy plg_system_joomlaboost. The base package is always
removed LAST (removing it first would strip the shared AiBoost\\Lib out from under
still-installed plugins and 500 the site mid-uninstall).

This does NOT touch the database — the #__aiboost_* tables survive a Joomla
uninstall by contract. Drop them separately (the QA-cycle plan's SQL gate 1).

Usage (via the creds wrapper; testmyweb needs TESTMYWEB_NO_SSL_VERIFY=1):
  python _creds_run.py scripts/uninstall-all.py --check                 # list only
  python _creds_run.py scripts/uninstall-all.py --sites free,j6pro      # wipe subset
  python _creds_run.py scripts/uninstall-all.py                         # wipe ALL 5

@copyright (C) 2025 AI Boost (aiboostnow.com). GPL v2 or later.
"""
from __future__ import annotations
import argparse, importlib.util, os, re, sys, time, urllib.parse

HERE = os.path.dirname(os.path.abspath(__file__))
_spec = importlib.util.spec_from_file_location("_qa_common", os.path.join(HERE, "_qa_common.py"))
qa = importlib.util.module_from_spec(_spec); _spec.loader.exec_module(qa)

DEFAULT_SITES = ["free", "j6pro", "staging", "j5pro", "j6free"]  # j5free = WAF-blocked, skip
_TYPE_LABELS = {"package", "component", "plugin", "module", "language", "library", "file", "template"}


def _row_type(row_html: str) -> str:
    for cell in re.finditer(r"<t[dh][^>]*>(.*?)</t[dh]>", row_html, re.DOTALL | re.IGNORECASE):
        text = re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", cell.group(1))).strip().lower()
        if text in _TYPE_LABELS:
            return text
    return ""


def find_rows(s, admin_php) -> list[tuple[int, str, str]] | None:
    """Scrape com_installer manage view → [(ext_id, label, type)] for our rows.
    Returns None on a request error (caller treats as 'unknown, retry')."""
    url = (admin_php + "?option=com_installer&view=manage&list[limit]=0&filter[search]="
           + urllib.parse.quote("Boost"))
    try:
        html = s.get(url, timeout=60).text
    except Exception:
        return None
    rows = []
    for m in re.finditer(r"<tr[^>]*>(.*?)</tr>", html, re.DOTALL | re.IGNORECASE):
        rh = m.group(1)
        cb = re.search(r'name="cid\[\]"\s+value="(\d+)"', rh)
        if not cb:
            continue
        low = rh.lower()
        if not any(k in low for k in ("aiboost", "ai boost", "joomlaboost", "joomla boost")):
            continue
        nm = re.search(r'<span tabindex="0">\s*(.*?)\s*</span>', rh, re.DOTALL)
        label = re.sub(r"\s+", " ", nm.group(1)).strip() if nm else f"ext#{cb.group(1)}"
        rows.append((int(cb.group(1)), label, _row_type(rh)))
    return rows


def _sort_key(row: tuple[int, str, str]) -> tuple[int, str, int]:
    """Base package LAST; non-base package next; component; everything else first."""
    ext_id, label, etype = row
    lab = label.lower()
    if etype == "package":
        rank = 2 if ("pro" in lab or "add-on" in lab or "upgrade" in lab) else 3
    elif etype == "component":
        rank = 1
    else:
        rank = 0
    return (rank, lab, ext_id)


def installer_token(s, admin_php) -> str | None:
    try:
        return qa.extract_csrf(s.get(admin_php + "?option=com_installer&view=manage", timeout=60).text)
    except Exception:
        return None


def uninstall_one(s, admin_php, ext_id: int, label: str, token: str) -> bool | None:
    try:
        r = s.post(admin_php + "?option=com_installer&view=manage",
                   data={"option": "com_installer", "task": "manage.remove",
                         "boxchecked": "1", "cid[]": str(ext_id), token: "1"},
                   allow_redirects=True, timeout=120)
    except Exception:
        return None  # trickling/timeout — re-list next pass to confirm
    body = r.text.lower()
    if r.status_code != 200:
        return False
    return "alert-danger" not in body or "success" in body


def wipe_site(target: str, check_only: bool) -> int:
    try:
        s, admin_php, base = qa.connect(target)
    except Exception as e:
        print(f"  ✗ {target}: login failed: {e}")
        return 1
    rows = find_rows(s, admin_php)
    print(f"\n=== {target} ({base}) — {0 if rows is None else len(rows)} AI Boost row(s) ===")
    for r in (rows or []):
        print(f"    [{r[2] or '?':<9}] id={r[0]:<6} {r[1]}")
    if check_only:
        return 0
    if not rows:
        print("  ✓ already clean")
        return 0

    for pass_n in range(1, 9):
        rows = find_rows(s, admin_php)
        if rows is None:
            time.sleep(3); continue
        if not rows:
            print(f"  ✓ clean after pass {pass_n - 1}")
            return 0
        pkgs = [r for r in rows if r[2] == "package"]
        # Remove packages first (each cascades its members); once no package rows
        # remain, sweep the standalone bridges / module / legacy plugin / orphans.
        targets = sorted(pkgs, key=_sort_key) if pkgs else sorted(rows, key=_sort_key)
        token = installer_token(s, admin_php)
        if not token:
            print("  ✗ could not get installer CSRF token"); return 1
        print(f"  pass {pass_n}: removing {len(targets)} of {len(rows)} row(s)")
        for ext_id, label, _t in targets:
            res = uninstall_one(s, admin_php, ext_id, label, token)
            print(f"    {'✓' if res else ('?' if res is None else '✗')} {label}")
            time.sleep(1.5)
        time.sleep(2)

    rows = find_rows(s, admin_php)
    if rows:
        print(f"  ⚠️ {len(rows)} row(s) still present after 8 passes: {[r[1] for r in rows]}")
        return 1
    print("  ✓ clean")
    return 0


def main(sites: list[str], check_only: bool) -> int:
    qa.setup_console_utf8()
    rc = 0
    print(f"{'CHECK' if check_only else 'WIPE'} AI Boost extensions on: {sites}")
    for t in sites:
        rc |= wipe_site(t, check_only)
    print("\n" + ("done" if rc == 0 else "FINISHED WITH ISSUES — see above"))
    return rc


if __name__ == "__main__":
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--sites", default=",".join(DEFAULT_SITES))
    ap.add_argument("--check", action="store_true", help="list AI Boost extensions; do not remove")
    a = ap.parse_args()
    sys.exit(main([x.strip() for x in a.sites.split(",") if x.strip()], a.check))
