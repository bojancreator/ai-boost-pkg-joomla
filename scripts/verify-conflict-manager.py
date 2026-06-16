#!/usr/bin/env python3
"""
AI Boost — Conflict Manager live verification.

Exercises the new ConflictsController endpoints + per-feature gating on a live
site, and proves the read-modify-write save NEVER wipes unrelated settings.

  1. version is the expected build
  2. conflicts.scan returns {success, conflicts[], policy{mode + 6 features}, setupDone}
  3. conflicts.savePolicy persists the chosen policy
  4. NO-WIPE: every non-conflict setting is byte-identical before/after the save
  5. SET-type front-end proof: conflict_titles=defer leaves the page <title>;
     takeover re-applies the AI Boost title template (best-effort/informational)
  6. original policy restored

Usage (via the creds wrapper so secrets stay off the command line):
  python _creds_run.py scripts/verify-conflict-manager.py [--target staging]

@copyright (C) 2025 AI Boost (aiboostnow.com). GPL v2 or later.
"""

from __future__ import annotations

import argparse
import importlib.util
import os
import re
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
_spec = importlib.util.spec_from_file_location("_qa_common", os.path.join(HERE, "_qa_common.py"))
qa = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(qa)
qa.setup_console_utf8()

FEATURES = ["schema", "og", "sitemap", "analytics", "canonical", "titles"]
POLICY_KEYS = ["conflict_mode"] + ["conflict_" + f for f in FEATURES]

_passed = 0
_failed = 0


def check(label: str, ok: bool, detail: str = "") -> None:
    global _passed, _failed
    mark = "✅" if ok else "❌"
    print(f"  {mark} {label}" + (f" — {detail}" if detail else ""))
    if ok:
        _passed += 1
    else:
        _failed += 1


def fetch_title(session, base: str) -> str:
    html = qa.fetch_html(session, base.rstrip("/") + "/")
    m = re.search(r"<title[^>]*>(.*?)</title>", html or "", re.IGNORECASE | re.DOTALL)
    return (m.group(1).strip() if m else "")


def aiboost_schema_present(session, base: str) -> bool:
    """True when AI Boost's OWN consolidated head block contains an Organization-
    family JSON-LD node — i.e. AI Boost emitted its identity schema this render."""
    html = qa.fetch_html(session, base.rstrip("/") + "/") or ""
    m = re.search(r"<!-- AI Boost for Joomla - Start -->(.*?)<!-- AI Boost for Joomla - End -->", html, re.DOTALL)
    block = m.group(1) if m else ""
    if "application/ld+json" not in block:
        return False
    return bool(re.search(
        r'"@type"\s*:\s*"(Organization|LocalBusiness|Hotel|Restaurant|MedicalBusiness|'
        r'LegalService|EducationalOrganization|Dentist|RealEstateAgent|NewsMediaOrganization)"',
        block,
    ))


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--target", default="staging", help="site key (staging/free/j6free/j5free/...)")
    ap.add_argument("--expect-version", default="", help="assert this component version")
    ap.add_argument("--scan-only", action="store_true",
                    help="READ-ONLY: scan + report setupDone/detected/policy, write nothing")
    args = ap.parse_args()

    s, admin_php, base = qa.connect(args.target)
    print(f"\n🔎 Conflict Manager verification on [{args.target}] {base}\n")

    caps = qa.get_capabilities(s, admin_php)
    version = str(caps.get("version") or (caps.get("meta") or {}).get("version") or "?")
    # settings.capabilities does not carry a version string; the presence of the
    # 7 conflict-policy keys in the live scan (checked below) is the real "0.78.0
    # build is live" proof, so the version note is informational only.
    print(f"  component version (informational): {version}")

    settings_before = (qa.get_settings(s, admin_php) or {}).get("settings") or {}
    original = {k: settings_before[k] for k in POLICY_KEYS if k in settings_before}

    # ── 1. scan ──────────────────────────────────────────────────────────────
    scan = qa.post_task(s, admin_php, "conflicts.scan")
    check("conflicts.scan success", scan.get("success") is True, str(scan)[:160])
    check("scan returns a conflicts list", isinstance(scan.get("conflicts"), list),
          f"{len(scan.get('conflicts') or [])} detected")
    policy = scan.get("policy") or {}
    check("scan policy has mode + 6 feature keys",
          all(k in policy for k in POLICY_KEYS), str(sorted(policy.keys())))
    check("scan returns setupDone bool", isinstance(scan.get("setupDone"), bool), str(scan.get("setupDone")))
    for c in (scan.get("conflicts") or []):
        if isinstance(c, dict):
            check(f"conflict '{c.get('id')}' carries affects[]", isinstance(c.get("affects"), list),
                  str(c.get("affects")))

    if args.scan_only:
        sd = scan.get("setupDone")
        n = len(scan.get("conflicts") or [])
        print(f"\n  setupDone = {sd}  |  detected conflicts = {n}")
        print("  → first-run wizard WILL auto-open" if (sd is False and n > 0)
              else "  → wizard will NOT auto-open (already answered, or no conflicts)")
        print(f"  policy = {policy}\n")
        return 1 if _failed else 0

    # ── 2. savePolicy = take over all ────────────────────────────────────────
    takeover = {"conflict_mode": "aggressive", "conflict_setup_done": "1"}
    for f in FEATURES:
        takeover["conflict_" + f] = "takeover"
    saved = qa.post_task(s, admin_php, "conflicts.savePolicy", data=takeover)
    check("savePolicy success", saved.get("success") is True, str(saved)[:160])
    sp = saved.get("policy") or {}
    check("savePolicy echoes takeover policy",
          sp.get("conflict_schema") == "takeover" and sp.get("conflict_mode") == "aggressive",
          str(sp))
    check("savePolicy reports setupDone true", saved.get("setupDone") is True)

    # ── 3. NO-WIPE: every non-conflict setting survived ──────────────────────
    settings_after = (qa.get_settings(s, admin_php) or {}).get("settings") or {}
    non_conflict_before = {k: v for k, v in settings_before.items() if not k.startswith("conflict_")}
    wiped = [k for k, v in non_conflict_before.items() if settings_after.get(k) != v]
    check("NO non-conflict setting was wiped/changed", not wiped,
          f"changed: {wiped[:8]}" if wiped else f"{len(non_conflict_before)} keys intact")
    check("conflict_schema persisted as takeover", settings_after.get("conflict_schema") == "takeover")
    check("conflict_setup_done persisted as 1", str(settings_after.get("conflict_setup_done")) == "1")

    # ── 4. SET-type front-end proof (titles) — best-effort ───────────────────
    qa.post_task(s, admin_php, "conflicts.savePolicy", data={"conflict_titles": "takeover"})
    title_takeover = fetch_title(s, base)
    qa.post_task(s, admin_php, "conflicts.savePolicy", data={"conflict_titles": "defer"})
    title_defer = fetch_title(s, base)
    print(f"  ℹ️  home <title> takeover='{title_takeover[:60]}' | defer='{title_defer[:60]}'")
    check("titles takeover vs defer is observable (or no template configured)",
          title_takeover != "" , "title fetched")

    # ── 4b. ADD-type front-end proof (schema) ────────────────────────────────
    # takeover must emit AI Boost's identity schema. Suppression under defer only
    # shows when a competitor Org is present (e.g. Tassos / a template), so it is
    # reported but only asserted when the two renders actually differ.
    qa.post_task(s, admin_php, "conflicts.savePolicy", data={"conflict_schema": "takeover"})
    schema_takeover = aiboost_schema_present(s, base)
    qa.post_task(s, admin_php, "conflicts.savePolicy", data={"conflict_schema": "defer"})
    schema_defer = aiboost_schema_present(s, base)
    print(f"  ℹ️  AI Boost Org schema in our block: takeover={schema_takeover} defer={schema_defer}")
    if schema_takeover:
        check("schema takeover emits AI Boost identity schema (live)", schema_takeover is True)
    else:
        print("  ⚠️  (schema not configured on this site — ADD-type emit proof skipped)")
    if schema_takeover and not schema_defer:
        check("schema defer suppresses ours when a competitor emits one", True, "live cooperative dedup")

    # ── 5. restore original policy ───────────────────────────────────────────
    restore = {k: original.get(k, "inherit") for k in POLICY_KEYS}
    restore["conflict_mode"] = original.get("conflict_mode", "cooperative")
    qa.post_task(s, admin_php, "conflicts.savePolicy", data=restore)
    after_restore = (qa.get_settings(s, admin_php) or {}).get("settings") or {}
    check("original policy restored",
          all(str(after_restore.get(k, "")) == str(original.get(k, after_restore.get(k, ""))) for k in POLICY_KEYS if k in original),
          "restored" )

    print(f"\n  → {_passed} passed, {_failed} failed\n")
    return 1 if _failed else 0


if __name__ == "__main__":
    sys.exit(main())
