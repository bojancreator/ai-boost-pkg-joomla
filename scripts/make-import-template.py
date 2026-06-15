#!/usr/bin/env python3
"""
AI Boost — import-template + settings-catalog generator.

Pulls the LIVE merged field universe from a site (settings.capabilities — the
manifest plus any runtime plugin/integration fields) and writes two artifacts so
test variants can be authored as JSON and imported, with NO manual backend entry:

  1. aiboost-import-template.json   A ready-to-import file in the exact import
     envelope {meta, params, translations}, with EVERY setting key present at
     its manifest default. Copy it, change the values you want to test, import
     via AI Boost -> Import/Export. Import MERGES, so you may also delete keys
     you don't care about and only ship the ones you change.

  2. aiboost-settings-catalog.csv   One row per key — tab, tier, sku, type,
     default, allowed options — so you know which values are valid (especially
     for dropdowns/enums) without digging through the UI.

Because the field list is read live and is manifest-driven, any plugin we add
later shows up here automatically — no edit to this script needed.

Usage (via the creds wrapper):
  python _creds_run.py scripts/make-import-template.py --target staging
  python _creds_run.py scripts/make-import-template.py --target j6pro --out deliverables/import-templates
  # Free-only universe (Pro keys omitted): add --tier free

@copyright (C) 2025 AI Boost (aiboostnow.com). GPL v2 or later.
"""

from __future__ import annotations

import argparse
import csv
import importlib.util
import json
import os
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
REPO = os.path.dirname(HERE)
_spec = importlib.util.spec_from_file_location("_qa_common", os.path.join(HERE, "_qa_common.py"))
qa = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(qa)


def _default_for(field: dict):
    """Pick a sensible, import-safe default value for a field."""
    if "default" in field and field["default"] is not None:
        d = field["default"]
        if isinstance(d, bool):
            return "1" if d else "0"
        return d
    # No declared default: empty string is always import-safe.
    return ""


def _options_str(field: dict) -> str:
    opts = field.get("options")
    if not opts:
        return ""
    vals = []
    for o in opts:
        if isinstance(o, dict):
            vals.append(str(o.get("value", o.get("key", ""))))
        else:
            vals.append(str(o))
    return " | ".join(v for v in vals if v != "")


def main(target: str, out_dir: str, tier_filter: str | None) -> int:
    qa.setup_console_utf8()
    s, admin_php, base = qa.connect(target)
    caps = qa.get_capabilities(s, admin_php)
    fields = caps.get("fields") or []
    if not fields:
        print(f"❌ no fields returned by capabilities on [{target}]")
        return 1

    # Keep deterministic order: tab, then key.
    fields = sorted(fields, key=lambda f: (str(f.get("tab", "")), str(f.get("key", ""))))

    os.makedirs(out_dir, exist_ok=True)
    params: dict = {}
    rows = []
    skipped_tier = 0
    for f in fields:
        key = str(f.get("key", "")).strip()
        if not key:
            continue
        if tier_filter and str(f.get("tier", "free")) != tier_filter:
            skipped_tier += 1
            continue
        params[key] = _default_for(f)
        rows.append({
            "key": key,
            "tab": f.get("tab", ""),
            "tier": f.get("tier", "free"),
            "sku": f.get("sku", "core"),
            "integration": f.get("integration") or "",
            "type": f.get("type", ""),
            "default": params[key],
            "options": _options_str(f),
        })

    template = {
        "meta": {
            "plugin": "pkg_aiboost",
            "version": "1.0",
            "note": f"All-keys import template generated from [{target}]. "
                    "Edit values and import via AI Boost -> Import/Export. "
                    "Import merges; license/identity keys are never imported.",
        },
        "params": params,
        "translations": [],
    }

    json_path = os.path.join(out_dir, "aiboost-import-template.json")
    with open(json_path, "w", encoding="utf-8") as fh:
        json.dump(template, fh, ensure_ascii=False, indent=2)

    csv_path = os.path.join(out_dir, "aiboost-settings-catalog.csv")
    with open(csv_path, "w", encoding="utf-8", newline="") as fh:
        w = csv.DictWriter(fh, fieldnames=["key", "tab", "tier", "sku", "integration",
                                           "type", "default", "options"])
        w.writeheader()
        w.writerows(rows)

    by_tier: dict[str, int] = {}
    for r in rows:
        by_tier[r["tier"]] = by_tier.get(r["tier"], 0) + 1
    print(f"=== template from [{target}] {base} ===")
    print(f"  {len(params)} keys written (by tier: {by_tier}"
          + (f"; {skipped_tier} skipped by --tier {tier_filter}" if tier_filter else "") + ")")
    print(f"  -> {json_path}")
    print(f"  -> {csv_path}")
    print("\nFill values in the JSON, then import via AI Boost -> Import/Export "
          "(or scripts/verify-import-export.py's harness).")
    return 0


if __name__ == "__main__":
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--target", default="staging", choices=list(qa.TARGETS))
    ap.add_argument("--out", default=os.path.join(REPO, "deliverables", "import-templates"),
                    help="output directory (default: deliverables/import-templates)")
    ap.add_argument("--tier", choices=["free", "pro"], default=None,
                    help="restrict to one tier (default: all keys)")
    args = ap.parse_args()
    sys.exit(main(args.target, args.out, args.tier))
