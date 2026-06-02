#!/usr/bin/env python3
"""
AI Boost admin operations over HTTP (reuses the read-only login from
_fetch_site_state.py):

  import   — upload a settings+translations JSON via com_aiboost import.upload
  modules  — audit administrator modules titled "AI Boost Health"
             (count / position / published / ordering) to confirm the
             install-time publish+dedupe and the Free-side removal.

Usage:
  python3 scripts/site-admin.py --target staging --action import --file deliverables/data/aiboost-settings-staging.json
  python3 scripts/site-admin.py --target staging --action modules
  python3 scripts/site-admin.py --target free --action modules
"""

import argparse
import os
import re
import sys

import importlib.util

# Load helpers from the sibling _fetch_site_state.py
_spec = importlib.util.spec_from_file_location(
    "_fetch_site_state", os.path.join(os.path.dirname(__file__), "_fetch_site_state.py"))
_fss = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(_fss)


def extract_csrf(html: str):
    # The AI Boost SPA exposes the Joomla form-token *name* (32-hex, value "1")
    # as window.aiBoostToken / csrfToken / tokenName, not always a hidden input.
    for p in [
        r'aiBoostToken\s*=\s*["\']?([a-f0-9]{32})',
        r'csrfToken["\']?\s*[:=]\s*["\']([a-f0-9]{32})',
        r'tokenName["\']?\s*[:=]\s*["\']([a-f0-9]{32})',
        r'name="([a-f0-9]{32})"[^>]*value="1"',
        r'value="1"[^>]*name="([a-f0-9]{32})"',
    ]:
        m = re.search(p, html)
        if m:
            return m.group(1)
    return None


def do_import(s, admin_php, path):
    # Get a CSRF token from any AI Boost admin page.
    csrf = None
    for view in ("?option=com_aiboost&view=import", "?option=com_aiboost",
                 "?option=com_aiboost&view=settings"):
        r = s.get(admin_php + view, timeout=60)
        csrf = extract_csrf(r.text)
        if csrf:
            break
    if not csrf:
        print("❌ No CSRF token for import")
        sys.exit(1)
    with open(path, "rb") as f:
        data = f.read()
    print(f"📥 Importing {os.path.basename(path)} ({len(data)//1024} KB) …")
    r = s.post(
        admin_php + "?option=com_aiboost&task=import.upload",
        data={"option": "com_aiboost", "task": "import.upload", csrf: "1"},
        files={"ab_import_file": (os.path.basename(path), data, "application/json")},
        allow_redirects=True, timeout=120,
    )
    txt = r.text.strip()
    m = re.search(r'\{.*"(?:message|success)".*\}', txt, re.DOTALL)
    print("   response:", (m.group(0) if m else txt[:400]))


def do_modules(s, admin_php):
    r = s.get(admin_php + "?option=com_modules&client_id=1&list[limit]=200", timeout=60)
    html = r.text
    # Each module row carries an edit link: ...task=module.edit&id=NNN... followed
    # (within the row) by the module title. Collect (id -> row-block) and keep the
    # rows whose block mentions "AI Boost Health".
    edits = list(re.finditer(r'task=module\.edit&(?:amp;)?id=(\d+)', html))
    ids = {}
    for i, m in enumerate(edits):
        start = m.start()
        end = edits[i + 1].start() if i + 1 < len(edits) else min(len(html), start + 2000)
        block = html[start:end]
        mid = m.group(1)
        # Match on the *module-type* friendly label (muted span), exactly
        # "AI Boost Health" — NOT a loose substring, so the unrelated
        # third-party "Health Checker" (mod_healthchecker) module is ignored.
        labels = [s.strip() for s in re.findall(r'class="small[^"]*"[^>]*>\s*([^<]+)<', block)]
        if "AI Boost Health" in labels and mid not in ids:
            ids[mid] = block
    print(f"🔎 mod_aiboost_health admin instances: {len(ids)}  (ids={sorted(ids, key=int)})")
    for mid, block in sorted(ids.items(), key=lambda kv: int(kv[0])):
        published = not bool(re.search(r'aria-label="[^"]*Unpublished|tip="[^"]*Unpublished', block, re.I))
        print(f"   id={mid} published={published}")
    return len(ids)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--target", required=True, choices=["staging", "free"])
    ap.add_argument("--action", required=True, choices=["import", "modules"])
    ap.add_argument("--file")
    args = ap.parse_args()

    admin_url, user, passwd = _fss.env(args.target)
    print(f"🔐 Login → {args.target}")
    s, admin_php = _fss.login(admin_url, user, passwd)

    if args.action == "import":
        if not args.file:
            sys.exit("--file required for import")
        do_import(s, admin_php, args.file)
    else:
        do_modules(s, admin_php)


if __name__ == "__main__":
    main()
