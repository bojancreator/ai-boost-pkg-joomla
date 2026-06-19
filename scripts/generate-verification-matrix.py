#!/usr/bin/env python3
"""
AI Boost — verification-matrix generator (Plan 3, deliverable #1).

Produces Bojan's "list of every function": one row per settings key, with its
tab, tier/SKU, integration, whether it is actually writable (vs DB-only),
whether it is multilingual / conflict-scanned, and whether the front-end
verification harness covers it yet.

Key universe = three merged sources:
  1. Static manifest      — `php scripts/dump-manifest.php` (authoritative metadata).
  2. Save-surface consts  — `php scripts/dump-save-definition.php`
                            (legacy/compat whitelist + DB-only system-preserved keys).
  3. Runtime plugin fields — optional, live, via `--target` (settings.capabilities):
                            falang_*/yootheme_*/schema_pro fields registered at runtime.

The UNREGISTERED tripwire: keys that ride only on the legacy whitelist and are
absent from the manifest (GA4/GTM/Meta-Pixel/GSC) are flagged — a manifest-only
export would silently drop them.

Offline (no --target) still produces a full static+legacy matrix; runtime fields
are then listed as "live-only (run with --target to enumerate)".

Outputs:
  docs/internal/verification-matrix.md    — human report (Bojan reads this)
  docs/internal/verification-matrix.json  — machine mirror (harness consumes this)
  scripts/src/verification-coverage.json  — coverage map (seeded if absent; editable)

Usage:
  python scripts/generate-verification-matrix.py                 # offline
  python _creds_run.py scripts/generate-verification-matrix.py --target staging
"""

from __future__ import annotations

import argparse
import json
import os
import shutil
import subprocess
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
REPO = os.path.dirname(HERE)
DOCS_DIR = os.path.join(REPO, "docs", "internal")
SRC_DIR = os.path.join(HERE, "src")
COVERAGE_PATH = os.path.join(SRC_DIR, "verification-coverage.json")
MD_PATH = os.path.join(DOCS_DIR, "verification-matrix.md")
JSON_PATH = os.path.join(DOCS_DIR, "verification-matrix.json")

for _stream in (sys.stdout, sys.stderr):
    try:
        _stream.reconfigure(encoding="utf-8", line_buffering=True)  # type: ignore[attr-defined]
    except Exception:
        pass

# ── Check-group definitions (the verification harness's coverage contract) ────

GROUP_DOC = {
    "static": "Read-only page assertions: exactly one consolidated head block, "
              "fixed section order (Schema→Social→AEO→Analytics→Custom Code), JSON-LD "
              "parses with expected @types, OG/canonical completeness + uniqueness, GTM noscript.",
    "virtual": "Virtual-file assertions: robots.txt fence + scraper rules + Sitemap line; "
               "llms.txt sections; sitemap.xml validity, on-origin URLs, priority∈[0,1].",
    "toggles": "Dynamic: each master switch OFF → artifact disappears → restore → artifact returns "
               "(snapshot + import + restore).",
    "writes": "Mutating writes: redirect 301 + 404-log row (read via redirects.listJson); "
              "per-language translation reaches that language's JSON-LD.",
    "multilingual": "Per-language head hreflang + sitemap xhtml:link alternates + x-default; "
                    "translated Schema/OG; per-language llms-<sef>.txt (native + Falang).",
    "conflicts": "health.rerun (ConflictDetector + DuplicateTagScanner); foreign duplicate tag is "
                 "DETECTED but NEVER stripped (passes through, not deleted).",
}

# Curated seed: which keys each harness group exercises. Written to
# verification-coverage.json on first run; edit that file thereafter.
SEED_COVERAGE = {
    "static": [
        "enable_schema", "schema_type", "org_name", "website_schema_search_enabled",
        "article_schema_enabled", "enable_opengraph", "enable_twitter_cards",
        "enable_canonical", "aeo_ai_meta_enabled",
    ],
    "virtual": [
        "enable_sitemap", "enable_sitemap_index", "enable_image_sitemap", "enable_news_sitemap",
        "llmstxt_enabled", "llms_full_txt_enabled", "ai_crawlers_enabled", "enable_robots",
        "scraper_ahrefsbot", "scraper_semrushbot", "scraper_dotbot", "scraper_mj12bot",
        "scraper_blexbot", "scraper_rogerbot", "scraper_screamingfrog", "scraper_sitebulb",
        "scraper_siteauditor", "scraper_serpstatbot", "scraper_bytespider", "scraper_petalbot",
        "enable_x_robots_header", "markdown_pages_enabled",
    ],
    "toggles": [
        "enable_schema", "enable_opengraph", "enable_twitter_cards", "aeo_ai_meta_enabled",
        "llmstxt_enabled", "llms_full_txt_enabled", "enable_sitemap", "enable_canonical",
        "enable_hreflang", "falang_hreflang_head", "ai_crawlers_enabled", "hide_comments",
        "enable_custom_code", "custom_code_head", "custom_code_body", "custom_code_footer",
        "enable_ga4", "enable_gtm", "enable_meta_pixel", "enable_google_verification",
        "integration_falang_enabled", "integration_yootheme_enabled",
    ],
    "writes": [
        "redirect_enabled", "redirect_404_log_enabled", "org_name",
    ],
    "multilingual": [
        "enable_hreflang", "falang_hreflang_head", "falang_hreflang_mode",
        "falang_schema_translate", "falang_og_translate",
    ],
    "conflicts": [
        "enable_opengraph", "enable_schema", "custom_code_head", "enable_ga4", "enable_gtm",
        "enable_google_verification", "enable_meta_pixel",
    ],
}

# ── Heuristics for legacy keys lacking manifest metadata ─────────────────────

TAB_HINTS = [
    (("ga4", "gtm", "pixel", "gsc", "analytics", "google_verification", "fb_app", "fb_domain",
      "meta_custom", "indexnow", "ping_"), "analytics"),
    (("scraper_", "robots", "crawler", "ai_crawlers", "x_robots", "markdown_pages",
      "aeo_", "llms"), "aeo"),
    (("sitemap", "priority_", "default_changefreq", "default_priority", "news_", "include_",
      "exclude_"), "sitemap"),
    (("og_", "twitter_", "social_", "site_name", "default_og_image"), "og"),
    (("schema_", "faq", "specific_", "hours_", "events_", "rating_", "org_", "manual_faq",
      "page_type"), "schema"),
    (("custom_code", "debug_mode", "hide_comments", "error_log", "staging_mode", "dev_"), "code"),
    (("title_", "meta_desc", "redirect", "404", "canonical", "conflict_", "domain"), "core"),
    (("falang", "hreflang", "translation", "license"), "core"),
]

# Keys VERIFIED to be consumed at runtime yet absent from the static manifest
# (aiboost_analytics reads these; an export built only from the manifest would
# silently drop them). The tripwire flags any of these whose manifest column is
# MISSING — self-correcting if one later moves into the manifest.
UNREGISTERED_CONSUMED = {
    "enable_ga4", "ga4_measurement_id", "ga4_consent_mode",
    "enable_gtm", "gtm_container_id",
    "enable_meta_pixel", "meta_pixel_id", "meta_pixel_ids", "pixel_consent_mode",
    "fb_domain_verification", "meta_pixel_standard_events", "meta_custom_events",
    "enable_google_verification", "gsc_codes", "gsc_verification_code", "gsc_additional_html",
    "indexnow_enabled", "indexnow_api_key", "indexnow_auto_submit",
}

ML_HINT = ("falang", "hreflang", "translation", "_en", "lang", "locale")
CONFLICT_HINT = ("enable_opengraph", "og_", "twitter_", "enable_schema", "custom_code",
                 "robots", "scraper", "canonical", "ga4", "gtm", "pixel", "gsc",
                 "google_verification", "markdown_pages")


def guess_tab(key: str) -> str:
    for needles, tab in TAB_HINTS:
        if any(n in key for n in needles):
            return tab
    return "core"


def ml_note(key: str, integration, sku: str) -> str:
    if integration == "falang" or sku in ("int_falang", "hreflang"):
        return "per-language (Multilang Pro)"
    if key.endswith("_en") or any(h in key for h in ML_HINT):
        return "translatable"
    return ""


def conflict_note(key: str) -> str:
    return "conflict-scanned" if any(h in key for h in CONFLICT_HINT) else ""


# ── Source dumps ─────────────────────────────────────────────────────────────

def _php_json(script: str):
    php = shutil.which("php")
    if not php:
        sys.exit("❌ php executable not found on PATH")
    cmd = [php, os.path.join("scripts", script)]
    try:
        out = subprocess.run(cmd, cwd=REPO, capture_output=True, text=True,
                             encoding="utf-8", check=False)
    except PermissionError:
        # Windows + WinGet php.EXE reparse point — re-run through cmd.exe.
        if sys.platform != "win32":
            raise
        out = subprocess.run(["cmd.exe", "/d", "/s", "/c", subprocess.list2cmdline(cmd)],
                             cwd=REPO, capture_output=True, text=True,
                             encoding="utf-8", check=False)
    if out.returncode != 0:
        sys.exit(f"❌ php scripts/{script} failed (exit {out.returncode}):\n{out.stderr[:600]}")
    try:
        return json.loads(out.stdout)
    except json.JSONDecodeError as e:
        sys.exit(f"❌ scripts/{script} did not emit clean JSON: {e}\n{out.stdout[:300]}")


def load_runtime_fields(target: str) -> list:
    """Live runtime fields via settings.capabilities (needs creds + staging)."""
    spec_path = os.path.join(HERE, "_qa_common.py")
    import importlib.util
    spec = importlib.util.spec_from_file_location("_qa_common", spec_path)
    qa = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(qa)
    qa.setup_console_utf8()
    s, admin_php, base = qa.connect(target)
    caps = qa.get_capabilities(s, admin_php)
    fields = caps.get("fields") or []
    print(f"   ✓ live capabilities: {len(fields)} fields from {base}")
    return fields


# ── Matrix assembly ──────────────────────────────────────────────────────────

def build_matrix(target: str | None) -> dict:
    static = _php_json("dump-manifest.php")
    save_def = _php_json("dump-save-definition.php")

    static_map = {f["key"]: f for f in static if f.get("key")}
    legacy = set(save_def["legacy"])
    system_preserved = set(save_def["system_preserved"])
    save_only = set(save_def["save_only"])

    runtime_map: dict = {}
    if target:
        for f in load_runtime_fields(target):
            k = f.get("key")
            if k and k not in static_map:
                runtime_map[k] = f

    coverage = load_or_seed_coverage()
    key_to_groups: dict[str, list] = {}
    for grp, keys in coverage.get("coverage", {}).items():
        for k in keys:
            key_to_groups.setdefault(k, []).append(grp)

    all_keys = sorted(set(static_map) | legacy | set(runtime_map) | system_preserved)
    rows = []
    for key in all_keys:
        meta = static_map.get(key) or runtime_map.get(key) or {}
        if key in static_map:
            source = "static"
        elif key in runtime_map:
            source = "runtime"
        elif key in system_preserved:
            source = "system-preserved"
        else:
            source = "legacy-compat"

        tier = meta.get("tier") or "free"
        sku = meta.get("sku") or "core"
        integration = meta.get("integration")
        tab = meta.get("tab") or guess_tab(key)
        # import.upload accepts all keys except IMPORT_DENYLIST (= SYSTEM_PRESERVED_KEYS);
        # writable iff not system-preserved.
        writable = key not in system_preserved
        unregistered = key in UNREGISTERED_CONSUMED and key not in static_map

        rows.append({
            "key": key,
            "tab": tab,
            "tier": tier,
            "sku": sku,
            "integration": integration,
            "type": meta.get("type") or "",
            "default": meta.get("default", ""),
            "source": source,
            "manifest": "present" if key in static_map else "MISSING",
            "writable": "save+import" if writable else "DB-only",
            "save_only": key in save_only,
            "unregistered": unregistered,
            "multilingual": ml_note(key, integration, sku),
            "conflict": conflict_note(key),
            "groups": key_to_groups.get(key, []),
            "covered": bool(key_to_groups.get(key)),
        })

    return {
        "target": target or "(offline)",
        "counts": {
            "total": len(rows),
            "static": len(static_map),
            "runtime": len(runtime_map),
            "legacy_compat": sum(1 for r in rows if r["source"] == "legacy-compat"),
            "system_preserved": len(system_preserved),
            "unregistered": sum(1 for r in rows if r["unregistered"]),
            "db_only": sum(1 for r in rows if r["writable"] == "DB-only"),
            "pro": sum(1 for r in rows if r["tier"] == "pro"),
            "integration": sum(1 for r in rows if r["integration"]),
            "covered": sum(1 for r in rows if r["covered"]),
            "uncovered": sum(1 for r in rows if not r["covered"]),
        },
        "rows": rows,
        "groups": GROUP_DOC,
    }


def load_or_seed_coverage() -> dict:
    if os.path.exists(COVERAGE_PATH):
        with open(COVERAGE_PATH, encoding="utf-8") as f:
            return json.load(f)
    os.makedirs(SRC_DIR, exist_ok=True)
    seed = {
        "_comment": "Coverage map for verify-frontend-emission.py. Edit freely; "
                    "generate-verification-matrix.py reads this to flag UNCOVERED keys.",
        "groups": GROUP_DOC,
        "coverage": SEED_COVERAGE,
    }
    with open(COVERAGE_PATH, "w", encoding="utf-8") as f:
        json.dump(seed, f, ensure_ascii=False, indent=2)
    print(f"   ✓ seeded coverage map → {os.path.relpath(COVERAGE_PATH, REPO)}")
    return seed


# ── Markdown rendering ───────────────────────────────────────────────────────

def render_md(matrix: dict) -> str:
    c = matrix["counts"]
    lines = []
    lines.append("# AI Boost — Verification Matrix")
    lines.append("")
    lines.append("> Auto-generated by `scripts/generate-verification-matrix.py`. Do not hand-edit — "
                 "re-run after any manifest / save-definition change. The **Visual** column is "
                 "Bojan's manual sign-off box.")
    lines.append("")
    lines.append(f"- **Source target:** `{matrix['target']}`")
    lines.append(f"- **Total keys:** {c['total']}  "
                 f"(static manifest {c['static']} · runtime {c['runtime']} · "
                 f"legacy-compat {c['legacy_compat']} · system-preserved {c['system_preserved']})")
    lines.append(f"- **Pro-tier keys:** {c['pro']}  · **integration keys:** {c['integration']}")
    lines.append(f"- **DB-only (never save/import writable):** {c['db_only']}")
    lines.append(f"- **⚠️ UNREGISTERED (whitelist-only, absent from manifest):** {c['unregistered']} "
                 "— a manifest-only export drops these; harness must prove export→import round-trip.")
    lines.append(f"- **Coverage:** {c['covered']} covered · **{c['uncovered']} UNCOVERED** by the "
                 "front-end harness.")
    lines.append("")

    if matrix["target"] == "(offline)":
        lines.append("> ℹ️ Generated **offline** — runtime plugin fields "
                     "(`falang_*` / `yootheme_*` / `schema_pro`) are not enumerated here. "
                     "Re-run with `--target staging` (Pro) to include them.")
        lines.append("")

    # Check groups
    lines.append("## Verification groups")
    lines.append("")
    for g, doc in matrix["groups"].items():
        lines.append(f"- **{g}** — {doc}")
    lines.append("")

    # Unregistered tripwire detail
    unreg = [r for r in matrix["rows"] if r["unregistered"]]
    if unreg:
        lines.append("## ⚠️ Unregistered keys (manifest-missing, whitelist-only)")
        lines.append("")
        lines.append("| Key | Guessed tab | Covered by |")
        lines.append("|---|---|---|")
        for r in unreg:
            cov = ", ".join(r["groups"]) or "—"
            lines.append(f"| `{r['key']}` | {r['tab']} | {cov} |")
        lines.append("")

    # Full matrix grouped by tab
    lines.append("## Full matrix")
    lines.append("")
    by_tab: dict[str, list] = {}
    for r in matrix["rows"]:
        by_tab.setdefault(r["tab"], []).append(r)
    for tab in sorted(by_tab):
        rows = by_tab[tab]
        lines.append(f"### Tab: `{tab}` ({len(rows)} keys)")
        lines.append("")
        lines.append("| Key | Tier | SKU | Integ | Writable | Source | ML | Conflict | Groups | Visual |")
        lines.append("|---|---|---|---|---|---|---|---|---|---|")
        for r in sorted(rows, key=lambda x: x["key"]):
            groups = ", ".join(r["groups"]) if r["groups"] else "**UNCOVERED**"
            integ = r["integration"] or ""
            man = "" if r["manifest"] == "present" else " ⚠️"
            lines.append(
                f"| `{r['key']}`{man} | {r['tier']} | {r['sku']} | {integ} | {r['writable']} | "
                f"{r['source']} | {r['multilingual']} | {r['conflict']} | {groups} | ☐ |"
            )
        lines.append("")

    lines.append("---")
    lines.append("")
    lines.append("**Legend:** Writable `save+import` = settable through the admin save/import path; "
                 "`DB-only` = system-preserved (licence/identity), never client-writable — set via a "
                 "real licence activation or direct DB SQL. `⚠️` on a key = absent from the static manifest.")
    return "\n".join(lines) + "\n"


# ── Main ─────────────────────────────────────────────────────────────────────

def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--target", choices=["staging", "free", "ml"],
                    help="Fetch live runtime fields from this site (needs creds via _creds_run.py).")
    args = ap.parse_args()

    print("🧮 Building verification matrix…")
    matrix = build_matrix(args.target)
    os.makedirs(DOCS_DIR, exist_ok=True)
    with open(JSON_PATH, "w", encoding="utf-8") as f:
        json.dump(matrix, f, ensure_ascii=False, indent=2)
    with open(MD_PATH, "w", encoding="utf-8") as f:
        f.write(render_md(matrix))

    c = matrix["counts"]
    print(f"   ✓ {c['total']} keys → {os.path.relpath(MD_PATH, REPO)}")
    print(f"   ✓ machine mirror → {os.path.relpath(JSON_PATH, REPO)}")
    print(f"   ⚠️ {c['unregistered']} unregistered · {c['uncovered']} uncovered · {c['db_only']} DB-only")
    return 0


if __name__ == "__main__":
    sys.exit(main())
