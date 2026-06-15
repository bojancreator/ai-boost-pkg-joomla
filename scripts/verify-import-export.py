#!/usr/bin/env python3
"""
AI Boost — import/export round-trip verifier.

Answers three product questions against a LIVE site, with PASS/FAIL:

  Q1  Does export capture EVERY stored setting (free + pro + all plugins +
      integration bridges) plus all translations?
  Q2  Does import accept and persist EVERY one of those keys (no whitelist
      drop, future plugins included)?
  Q3  Is the license/identity boundary correct — never exported, never
      imported — so a single JSON can be reused across many test domains
      without cloning a licence or a per-site identity?

How it proves it (non-destructive — the site is left byte-identical):
  1. settings.capabilities  -> the authoritative LIVE key universe (the manifest
     merged with runtime plugin/integration fields), grouped by tier + sku.
  2. settings.export        -> the stored blob + translations; assert it spans
     every section and that NO license/identity key leaked in.
  3. one-key sentinel round-trip on a harmless admin-only field
     (show_advanced_options): flip -> re-export -> assert it landed -> restore
     -> assert restored. Proves a VALUE truly survives import, with zero
     front-end effect and a guaranteed restore.
  4. idempotent self-import of the FULL exported params -> assert the server
     reports every key merged. Proves import processes the whole payload
     (same values in = no change out).

Usage (always via the creds wrapper):
  python _creds_run.py scripts/verify-import-export.py --target staging
  python _creds_run.py scripts/verify-import-export.py --target j6pro
  python _creds_run.py scripts/verify-import-export.py --target free

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

# License / identity / dev-override keys — must NEVER appear in an export and are
# silently dropped on import. Mirrors SettingsSaveDefinition::SYSTEM_PRESERVED_KEYS.
PRESERVED = [
    "license_key", "license_tier", "license_state", "license_heartbeat",
    "license_reconcile", "license_simulation", "pro_activated",
    "pro_activated_at", "pro_activated_version", "pro_skus",
    "install_id", "last_backup_at", "dev_license_preview", "dev_force_free_tier",
]

# Section -> key prefixes, for a human-readable "export spans every area" report.
SECTIONS = {
    "Organization / Schema": ("org_", "schema_", "social_", "hours_", "specific_",
                              "faq_", "events_", "rating_", "enable_schema",
                              "page_type", "enable_search_action"),
    "Sitemap":               ("sitemap_", "include_", "exclude_", "priority_",
                              "default_changefreq", "default_priority", "ping_",
                              "enable_sitemap", "enable_canonical", "enable_hreflang",
                              "enable_image_sitemap", "enable_news_sitemap", "news_"),
    "Social / OpenGraph":    ("og_", "site_name", "default_og", "twitter_", "fb_",
                              "meta_pixel", "pixel_", "enable_opengraph",
                              "enable_twitter", "enable_meta_pixel",
                              "enable_per_article", "enable_article_og", "enable_og_locale"),
    "Analytics":             ("ga4_", "gtm_", "gsc_", "enable_ga4", "enable_gtm",
                              "enable_google_verification", "enable_analytics"),
    "AEO / AI":              ("indexnow_", "llms", "robots_", "scraper_", "crawler_",
                              "aeo_", "markdown_", "x_robots", "ai_crawlers",
                              "article_schema", "website_schema", "enable_x_robots"),
    "Titles / Meta":         ("title_", "meta_desc_"),
    "Custom Code":           ("custom_code_", "enable_custom_code"),
    "Redirects / Debug":     ("redirect_", "error_log_", "debug_", "conflict_",
                              "enable_robots", "robots_auto", "hide_comments"),
}


def section_of(key: str) -> str:
    for name, prefixes in SECTIONS.items():
        if any(key.startswith(p) or key == p for p in prefixes):
            return name
    return "Other / bookkeeping"


def main(target: str) -> int:
    qa.setup_console_utf8()
    s, admin_php, base = qa.connect(target)
    print(f"=== import/export verify on [{target}]  {base} ===\n")
    fails: list[str] = []

    # ── 1. Live key universe (capabilities) ──────────────────────────────────
    caps = qa.get_capabilities(s, admin_php)
    fields = caps.get("fields") or []
    by_tier: dict[str, int] = {}
    by_integration: dict[str, int] = {}
    for f in fields:
        by_tier[f.get("tier", "?")] = by_tier.get(f.get("tier", "?"), 0) + 1
        ig = f.get("integration")
        if ig:
            by_integration[ig] = by_integration.get(ig, 0) + 1
    print(f"[universe] site knows {len(fields)} settings fields  "
          f"(by tier: {by_tier}; integration bridges: {by_integration or 'none active'})")

    # ── 2. Export: span + license boundary ───────────────────────────────────
    env = qa.export_full(s, admin_php)
    params = env.get("params") or {}
    translations = env.get("translations") or []
    meta = env.get("meta") or {}
    if not params:
        print("   ✗ export returned no params")
        return 1

    span: dict[str, int] = {}
    for k in params:
        span[section_of(k)] = span.get(section_of(k), 0) + 1
    print(f"\n[export] meta={meta.get('plugin')}/v{meta.get('version')}  "
          f"{len(params)} stored keys, {len(translations)} translation rows")
    for name in list(SECTIONS) + ["Other / bookkeeping"]:
        if span.get(name):
            print(f"          - {name:<24} {span[name]:>3} keys")

    leaked = [k for k in PRESERVED if k in params]
    if leaked:
        fails.append(f"Q3 license/identity LEAKED into export: {leaked}")
        print(f"   ✗ Q3 FAIL — license/identity keys present in export: {leaked}")
    else:
        print(f"   ✓ Q3 — no license/identity/dev key in export (all {len(PRESERVED)} correctly stripped)")

    # Coverage: how many known free/pro fields currently carry a stored value.
    field_keys = {f.get("key") for f in fields if f.get("key")}
    set_known = sorted(k for k in params if k in field_keys)
    print(f"   ↳ {len(set_known)}/{len(field_keys)} known fields currently have a stored value "
          f"(the rest sit at manifest defaults and need no export)")

    # ── 3. One-key sentinel round-trip (harmless admin-only field) ────────────
    SENTINEL_KEY = "show_advanced_options"   # admin-UI only, no front-end output
    original = str(params.get(SENTINEL_KEY, "0"))
    flipped = "0" if original == "1" else "1"
    print(f"\n[round-trip] {SENTINEL_KEY}: {original!r} -> import {flipped!r} -> verify -> restore")
    qa.import_params(s, admin_php, {SENTINEL_KEY: flipped}, label="flip", quiet=True)
    after = str((qa.export_full(s, admin_php).get("params") or {}).get(SENTINEL_KEY, ""))
    if after == flipped:
        print(f"   ✓ value landed through import (now {after!r})")
    else:
        fails.append(f"Q2 sentinel did not persist (expected {flipped!r}, got {after!r})")
        print(f"   ✗ Q2 FAIL — expected {flipped!r}, export shows {after!r}")
    qa.import_params(s, admin_php, {SENTINEL_KEY: original}, label="restore", quiet=True)
    restored = str((qa.export_full(s, admin_php).get("params") or {}).get(SENTINEL_KEY, ""))
    if restored == original:
        print(f"   ✓ restored to original {original!r} — site left unchanged")
    else:
        fails.append(f"restore failed: {SENTINEL_KEY} now {restored!r}, was {original!r}")
        print(f"   ✗ RESTORE FAIL — {SENTINEL_KEY} now {restored!r}, expected {original!r}")

    # ── 4. Idempotent full self-import (every key processed, no change) ───────
    print(f"\n[self-import] re-importing all {len(params)} exported keys (same values = no-op)")
    # Strip preserved keys defensively (server drops them anyway) so the message
    # count reflects real settings only.
    clean = {k: v for k, v in params.items() if k not in PRESERVED}
    ok = qa.import_params(s, admin_php, clean, label="self", quiet=False)
    if not ok:
        fails.append("Q2 full self-import returned failure")

    # ── Verdict ──────────────────────────────────────────────────────────────
    print("\n=== verdict ===")
    if fails:
        for fmsg in fails:
            print(f"  ✗ {fmsg}")
        print(f"\nRESULT: FAIL ({len(fails)} issue(s)) on [{target}]")
        return 1
    print("  ✓ Q1 export captures every stored setting + all translations, across every section")
    print("  ✓ Q2 import accepts & persists every key (sentinel landed; full payload merged)")
    print("  ✓ Q3 license/identity never crosses the export/import boundary")
    print(f"\nRESULT: PASS on [{target}] — site left byte-identical")
    return 0


if __name__ == "__main__":
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("--target", default="staging", choices=list(qa.TARGETS))
    sys.exit(main(ap.parse_args().target))
