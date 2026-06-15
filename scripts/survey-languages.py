#!/usr/bin/env python3
"""
AI Boost — content-language survey across the test/staging matrix (read-only).

Logs into every reachable site and reports the AI Boost content languages
(settings.getLanguages — the lang_codes the translation UI exposes and that the
import/export translation rows key on). Prints each site's set, then the UNION
and INTERSECTION so the matrix can be aligned before authoring multilingual
import fixtures.

Usage (via the creds wrapper; testmyweb needs TESTMYWEB_NO_SSL_VERIFY=1):
  python _creds_run.py scripts/survey-languages.py
  python _creds_run.py scripts/survey-languages.py --sites staging,free,j6pro

@copyright (C) 2025 AI Boost (aiboostnow.com). GPL v2 or later.
"""

from __future__ import annotations

import argparse
import importlib.util
import os
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
_spec = importlib.util.spec_from_file_location("_qa_common", os.path.join(HERE, "_qa_common.py"))
qa = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(qa)

DEFAULT_SITES = ["staging", "free", "j5pro", "j6pro", "j5free", "j6free"]


def survey_one(target: str) -> dict:
    try:
        s, admin_php, base = qa.connect(target)
    except SystemExit as e:
        return {"target": target, "error": f"creds/env: {e}"}
    except Exception as e:
        return {"target": target, "error": f"{type(e).__name__}: {str(e)[:120]}"}
    try:
        data = qa.get_languages(s, admin_php)
        langs = data.get("languages") or []
        codes = sorted({str(l.get("lang_code", "")).strip() for l in langs if l.get("lang_code")})
        return {
            "target": target, "base": base, "default": data.get("default_lang"),
            "codes": codes,
            "titles": {str(l.get("lang_code")): l.get("title") for l in langs},
        }
    except Exception as e:
        return {"target": target, "base": base, "error": f"{type(e).__name__}: {str(e)[:120]}"}


def main(sites: list[str]) -> int:
    qa.setup_console_utf8()
    results = [survey_one(t) for t in sites]
    print("\n=== content languages per site ===")
    ok_sets = []
    for r in results:
        if r.get("error"):
            print(f"  {r['target']:<8} ✗ {r['error']}")
            continue
        codes = r["codes"]
        ok_sets.append(set(codes))
        print(f"  {r['target']:<8} {r.get('base','')}")
        print(f"           default={r.get('default')!r}  ({len(codes)}): {codes}")

    if ok_sets:
        union = sorted(set().union(*ok_sets))
        inter = sorted(set.intersection(*ok_sets)) if ok_sets else []
        print(f"\n  UNION  ({len(union)}): {union}")
        print(f"  COMMON ({len(inter)}): {inter}")
        # Per-site gap vs the union.
        print("\n=== gap vs union (missing on each site) ===")
        for r in results:
            if r.get("error"):
                continue
            missing = sorted(set(union) - set(r["codes"]))
            print(f"  {r['target']:<8} missing: {missing or '(none — has all)'}")
    return 0


if __name__ == "__main__":
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--sites", default=",".join(DEFAULT_SITES))
    args = ap.parse_args()
    sys.exit(main([x.strip() for x in args.sites.split(",") if x.strip()]))
