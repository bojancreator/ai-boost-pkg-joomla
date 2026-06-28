#!/usr/bin/env python3
"""
YOOtheme integration verification script — task #0001.

Steps:
1. Connect to staging, check current licence / integration state.
2. Activate int_yootheme licence if not already active.
3. Enable integration_yootheme_enabled toggle.
4. Confirm capabilities show yootheme_* fields live.
5. Discover YOOtheme-built pages (scan menu items for YOOtheme builder hint).
6. For each interesting page, fetch JSON-LD and inspect schema types.

Run:
  python _creds_run.py scripts/_yootheme_verify.py --target staging
"""

from __future__ import annotations

import argparse
import json
import re
import sys
import time

import _qa_common as qa

# ── helpers ──────────────────────────────────────────────────────────────────


def header(txt: str) -> None:
    print(f"\n{'=' * 60}")
    print(f"  {txt}")
    print(f"{'=' * 60}")


def ok(txt: str) -> None:
    print(f"  ✓  {txt}")


def warn(txt: str) -> None:
    print(f"  ⚠  {txt}")


def info(txt: str) -> None:
    print(f"  ℹ  {txt}")


def fail(txt: str) -> None:
    print(f"  ✗  {txt}")


# ── main ─────────────────────────────────────────────────────────────────────


def main(target: str) -> int:
    qa.setup_console_utf8()

    header(f"YOOtheme integration verification — {target}")

    s, admin_php, base = qa.connect(target)
    ok(f"Connected: {base}  (SSL verify={qa.ssl_verify_for(target)})")

    # ── Step 1: current state ─────────────────────────────────────────────

    header("Step 1 — Current licence + integration state")

    lic = qa.license_state_get(s, admin_php)
    states = lic.get("states") or {}
    yt_state = states.get("int_yootheme") or {}
    info(f"int_yootheme licence state: {yt_state}")

    params = qa.export_settings(s, admin_php)
    yt_toggle = params.get("integration_yootheme_enabled", "0")
    info(f"integration_yootheme_enabled = {yt_toggle!r}")

    is_pro = qa.is_pro_active(s, admin_php)
    info(f"Site Pro-active: {is_pro}")

    # ── Step 2: activate int_yootheme licence ─────────────────────────────

    header("Step 2 — Activate int_yootheme licence")

    key = qa.qa_license_key("int_yootheme")
    if not key:
        fail("AIBOOST_QA_KEY_INT_YOOTHEME not set in environment — cannot activate.")
        return 1

    yt_status = yt_state.get("status", "")
    if yt_status in ("active", "valid"):
        ok(f"Licence already active (status={yt_status!r}) — skipping activation.")
    else:
        info(f"Activating int_yootheme (current status={yt_status!r})…")
        result = qa.activate_real_license(s, admin_php, "int_yootheme", key)
        if result.get("success"):
            ok(f"Activation succeeded: {result.get('state') or result.get('message', 'ok')}")
        else:
            fail(f"Activation failed: {result.get('message') or result}")
            return 1
        time.sleep(2)

    # ── Step 3: enable integration toggle ────────────────────────────────

    header("Step 3 — Enable integration_yootheme_enabled")

    if yt_toggle == "1":
        ok("Toggle already ON — skipping.")
    else:
        r = qa.integrations_save_toggle(s, admin_php, "yootheme", True)
        if r.get("success"):
            ok("Toggle set to ON.")
        else:
            fail(f"saveToggle failed: {r}")
            return 1
        time.sleep(2)

    # ── Step 4: confirm capabilities ─────────────────────────────────────

    header("Step 4 — Verify capabilities show yootheme_* fields")

    caps = qa.get_capabilities(s, admin_php)
    fields = caps.get("fields") or []
    yt_fields = [f for f in fields if str(f.get("key", "")).startswith("yootheme_")]
    if yt_fields:
        ok(f"Found {len(yt_fields)} yootheme_* fields live:")
        for f in yt_fields:
            info(f"  {f.get('key')} tier={f.get('tier')} locked={f.get('locked')}")
    else:
        fail("No yootheme_* fields in capabilities — integration not registering properly.")
        warn("Possible causes: plugin not enabled in #__extensions, Pro not active, or lib not loaded.")

    # Re-check toggle
    params2 = qa.export_settings(s, admin_php)
    yt_toggle2 = params2.get("integration_yootheme_enabled", "0")
    yt_meta_override = params2.get("yootheme_meta_override", "?")
    yt_faq = params2.get("yootheme_faq_enabled", "?")
    yt_gallery = params2.get("yootheme_gallery_enabled", "?")
    yt_schema_mapping = params2.get("yootheme_schema_mapping", "?")
    yt_sitemap_exclude = params2.get("yootheme_sitemap_exclude_builder", "?")

    ok(f"integration_yootheme_enabled = {yt_toggle2!r}")
    info(f"yootheme_meta_override = {yt_meta_override!r}")
    info(f"yootheme_faq_enabled = {yt_faq!r}")
    info(f"yootheme_gallery_enabled = {yt_gallery!r}")
    info(f"yootheme_schema_mapping = {yt_schema_mapping!r}")
    info(f"yootheme_sitemap_exclude_builder = {yt_sitemap_exclude!r}")

    # ── Step 5: discover YOOtheme pages ──────────────────────────────────

    header("Step 5 — Discover pages with YOOtheme builder content")

    # Fetch the front-end homepage first (to get the menu links)
    home_html = qa.fetch_html(s, base + "/en/", cache_bust=True)
    # Extract all href links from nav/menu
    links = re.findall(r'href=["\']([^"\']+)["\']', home_html)
    # Filter to same-domain links under /en/ /ru/ /sr/
    site_links: list[str] = []
    for link in links:
        if link.startswith("/") and not link.startswith("//"):
            full = base + link
        elif link.startswith(base):
            full = link
        else:
            continue
        # Only front-end pages (not /administrator/)
        if "/administrator" in full:
            continue
        if full not in site_links:
            site_links.append(full)

    info(f"Found {len(site_links)} unique links on homepage")

    # Also probe the admin menu to find YOOtheme-type pages
    # Check /en/ for a page list via sitemap
    sitemap_txt, sitemap_status, sitemap_url = qa.fetch_sitemap(s, base)
    sitemap_urls: list[str] = []
    if sitemap_txt:
        sitemap_urls = re.findall(r"<loc>([^<]+)</loc>", sitemap_txt)
        info(f"Sitemap has {len(sitemap_urls)} URLs")
    else:
        warn(f"Sitemap returned HTTP {sitemap_status}")

    # We'll check: homepage, a few article pages, and whatever we find
    # Prioritise /en/ language prefix pages for JSON-LD check
    candidate_urls: list[str] = []

    # Add homepage variants
    for lang in ("en", "ru", "sr"):
        u = f"{base}/{lang}/"
        if u not in candidate_urls:
            candidate_urls.append(u)

    # Add sitemap URLs (first 30 unique, prefer /en/)
    en_urls = [u for u in sitemap_urls if f"{base}/en/" in u]
    other_urls = [u for u in sitemap_urls if f"{base}/en/" not in u and u not in candidate_urls]
    candidate_urls.extend(en_urls[:15])
    candidate_urls.extend(other_urls[:5])

    info(f"Candidate pages to scan: {len(candidate_urls)}")

    # ── Step 6: fetch JSON-LD and look for YOOtheme types ────────────────

    header("Step 6 — Scan pages for YOOtheme schema types")

    YOOTHEME_TYPES = {"FAQPage", "ImageGallery", "Product", "Event"}
    found_types: dict[str, list[str]] = {}  # type -> [url]
    pages_with_accordion: list[str] = []
    pages_with_gallery: list[str] = []
    pages_scanned = 0

    for url in candidate_urls[:20]:
        try:
            html = qa.fetch_html(s, url, cache_bust=True)
            objs, _ = qa.fetch_jsonld(s, url)
            pages_scanned += 1

            # Check for YOOtheme types in JSON-LD
            for obj in objs:
                t = obj.get("@type")
                if isinstance(t, list):
                    ts = t
                else:
                    ts = [t] if t else []
                for typ in ts:
                    if typ in YOOTHEME_TYPES:
                        found_types.setdefault(typ, [])
                        if url not in found_types[typ]:
                            found_types[typ].append(url)
                            ok(f"Found @type={typ!r} on {url}")

            # Check for YOOtheme accordion markup
            if "uk-accordion" in html or "uk-accordion" in html.lower():
                if url not in pages_with_accordion:
                    pages_with_accordion.append(url)
                    info(f"Accordion HTML found on {url}")

            # Check for gallery markup
            if "uk-gallery" in html.lower() or ("data-caption" in html and "<figure" in html):
                if url not in pages_with_gallery:
                    pages_with_gallery.append(url)
                    info(f"Gallery HTML found on {url}")

        except Exception as e:
            warn(f"Error fetching {url}: {e}")

    info(f"Pages scanned: {pages_scanned}")

    # ── Step 7: Summary ───────────────────────────────────────────────────

    header("Summary")

    results: dict = {
        "integration_active": yt_toggle2 == "1",
        "yootheme_fields_count": len(yt_fields),
        "yootheme_faq_enabled": yt_faq,
        "yootheme_gallery_enabled": yt_gallery,
        "yootheme_schema_mapping": yt_schema_mapping,
        "pages_scanned": pages_scanned,
        "types_found": {k: v for k, v in found_types.items()},
        "pages_with_accordion": pages_with_accordion,
        "pages_with_gallery": pages_with_gallery,
        "missing_types": [t for t in YOOTHEME_TYPES if t not in found_types],
    }

    print(json.dumps(results, indent=2, ensure_ascii=False))

    if results["integration_active"]:
        ok("YOOtheme integration ACTIVE")
    else:
        fail("YOOtheme integration NOT active")

    if results["types_found"]:
        ok(f"YOOtheme schema types found: {list(results['types_found'].keys())}")
    else:
        warn("No YOOtheme-specific schema types found in any page JSON-LD")
        warn("This is expected if no YOOtheme builder pages exist on this staging site.")

    if results["pages_with_accordion"]:
        ok(f"Accordion content found on {len(results['pages_with_accordion'])} page(s)")
    else:
        warn("No UIkit accordion (.uk-accordion) found in any scanned page")

    if results["pages_with_gallery"]:
        ok(f"Gallery content found on {len(results['pages_with_gallery'])} page(s)")
    else:
        warn("No YOOtheme gallery found in any scanned page")

    missing = results["missing_types"]
    if missing:
        warn(f"Types NOT found (need YOOtheme content): {missing}")

    return 0


if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("--target", default="staging")
    sys.exit(main(ap.parse_args().target))
