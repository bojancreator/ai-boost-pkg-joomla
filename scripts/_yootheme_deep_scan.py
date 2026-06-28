#!/usr/bin/env python3
"""
Deep scan of YOOtheme schema types — task #0001 phase 2.

1. Determine if FAQPage on homepage comes from YOOtheme accordion or core FAQ.
2. Scan ALL sitemap pages for YOOtheme content (accordion, gallery, product, event).
3. Check for yootheme-specific indicators in page HTML.

Run:
  python _creds_run.py scripts/_yootheme_deep_scan.py --target staging
"""

from __future__ import annotations

import argparse
import json
import re
import sys

import _qa_common as qa


def header(txt: str) -> None:
    print(f"\n{'=' * 60}\n  {txt}\n{'=' * 60}")


def ok(txt: str) -> None:
    print(f"  ✓  {txt}")


def warn(txt: str) -> None:
    print(f"  ⚠  {txt}")


def info(txt: str) -> None:
    print(f"  ℹ  {txt}")


def fail(txt: str) -> None:
    print(f"  ✗  {txt}")


def is_yootheme_page(html: str) -> bool:
    """Does this page use YOOtheme builder markup?"""
    indicators = [
        "uk-section",
        "uk-container",
        "tm-grid",
        "yoo-",
        "uk-accordion",
        "uk-slideshow",
        "uk-gallery",
        "tm-section",
        'data-src="@',       # YOO lazy loading pattern
        "uk-lightbox",
    ]
    html_lower = html.lower()
    return any(ind in html_lower for ind in indicators)


def has_accordion(html: str) -> bool:
    return "uk-accordion" in html.lower()


def has_gallery(html: str) -> bool:
    return (
        "uk-gallery" in html.lower()
        or "uk-lightbox" in html.lower()
        or ("data-caption" in html and "<figure" in html.lower())
        or "uk-grid" in html.lower() and "<figure" in html.lower()
    )


def check_faqpage_source(objs: list) -> str:
    """Try to distinguish YOOtheme accordion FAQ from core FAQ."""
    for obj in objs:
        if obj.get("@type") == "FAQPage":
            items = obj.get("mainEntity") or []
            # YOOtheme accordion FAQs typically have very short/specific questions
            # Core FAQ comes from manually-set faq_items (our test data has specific keys)
            return f"FAQPage found, {len(items)} question(s); first_q={items[0].get('name', '?')[:80] if items else 'none'!r}"
    return "no FAQPage"


def main(target: str) -> int:
    qa.setup_console_utf8()

    header(f"YOOtheme deep scan — {target}")

    s, admin_php, base = qa.connect(target)
    ok(f"Connected: {base}")

    # ── Get all sitemap URLs ──────────────────────────────────────────────

    header("Fetch sitemap")

    sitemap_txt, status, _ = qa.fetch_sitemap(s, base)
    if not sitemap_txt:
        fail(f"Sitemap returned HTTP {status}")
        return 1

    all_urls = re.findall(r"<loc>([^<]+)</loc>", sitemap_txt)
    # Focus on /en/ pages for initial scan
    en_urls = [u for u in all_urls if "/en/" in u]
    other_urls = [u for u in all_urls if "/en/" not in u]
    # Deduplicate keeping /en/ first
    scan_urls = en_urls + [u for u in other_urls if u not in en_urls]

    ok(f"Sitemap has {len(all_urls)} URLs total, {len(en_urls)} /en/ URLs")

    # ── Check FAQPage source on homepage ─────────────────────────────────

    header("Check FAQPage source on /en/")

    en_home = base + "/en/"
    html_home = qa.fetch_html(s, en_home, cache_bust=True)
    objs_home, _ = qa.fetch_jsonld(s, en_home)

    info(f"Homepage JSON-LD objects: {len(objs_home)}")
    faq_source = check_faqpage_source(objs_home)
    info(f"FAQPage analysis: {faq_source}")
    info(f"Homepage is YOOtheme page: {is_yootheme_page(html_home)}")
    info(f"Homepage has accordion: {has_accordion(html_home)}")
    info(f"Homepage has gallery markup: {has_gallery(html_home)}")

    # Check ALL JSON-LD types on homepage
    home_types = []
    for obj in objs_home:
        t = obj.get("@type")
        home_types.extend(t if isinstance(t, list) else [t] if t else [])
    info(f"Homepage @types: {home_types}")

    # ── Scan all sitemap pages ────────────────────────────────────────────

    header(f"Deep scan — {len(scan_urls[:60])} pages")

    results = {
        "yootheme_pages": [],
        "accordion_pages": [],
        "gallery_pages": [],
        "faqpage_from_accordion": [],   # FAQPage found AND accordion markup present
        "faqpage_from_core": [],        # FAQPage found but no accordion
        "imagegallery_pages": [],
        "product_pages": [],
        "event_pages": [],
        "org_supplement_pages": [],     # Organization supplement from YOOtheme
        "errors": [],
    }

    YOOTHEME_SCHEMA_TYPES = {"FAQPage", "ImageGallery", "Product", "Event"}

    for i, url in enumerate(scan_urls[:60]):
        try:
            html = qa.fetch_html(s, url, cache_bust=True)
            objs = qa.jsonld_from_html(html)

            is_yt = is_yootheme_page(html)
            has_acc = has_accordion(html)
            has_gal = has_gallery(html)

            if is_yt:
                results["yootheme_pages"].append(url)
            if has_acc:
                results["accordion_pages"].append(url)
            if has_gal:
                results["gallery_pages"].append(url)

            # Check schema types
            for obj in objs:
                t = obj.get("@type")
                types = t if isinstance(t, list) else [t] if t else []
                for typ in types:
                    if typ == "FAQPage":
                        if has_acc:
                            if url not in results["faqpage_from_accordion"]:
                                results["faqpage_from_accordion"].append(url)
                                ok(f"FAQPage (accordion source?) on {url}")
                        else:
                            if url not in results["faqpage_from_core"]:
                                results["faqpage_from_core"].append(url)
                    elif typ == "ImageGallery":
                        if url not in results["imagegallery_pages"]:
                            results["imagegallery_pages"].append(url)
                            ok(f"ImageGallery on {url}")
                    elif typ == "Product":
                        if url not in results["product_pages"]:
                            results["product_pages"].append(url)
                            ok(f"Product on {url}")
                    elif typ == "Event":
                        if url not in results["event_pages"]:
                            results["event_pages"].append(url)
                            ok(f"Event on {url}")

        except Exception as e:
            results["errors"].append(f"{url}: {e}")
            warn(f"Error on {url}: {e}")

        if (i + 1) % 10 == 0:
            info(f"Progress: {i+1}/{min(60, len(scan_urls))} pages scanned")

    # ── Print results ─────────────────────────────────────────────────────

    header("Results")

    total_scanned = len(scan_urls[:60]) - len(results["errors"])
    ok(f"Pages scanned: {total_scanned}")
    ok(f"YOOtheme-built pages: {len(results['yootheme_pages'])}")
    info(f"  Pages with accordion: {len(results['accordion_pages'])}")
    info(f"  Pages with gallery markup: {len(results['gallery_pages'])}")

    print()
    print("  Schema type results:")
    if results["faqpage_from_accordion"]:
        ok(f"  FAQPage from YOOtheme accordion: {results['faqpage_from_accordion']}")
    else:
        warn("  FAQPage from YOOtheme accordion: NONE (no accordion content on site)")

    if results["faqpage_from_core"]:
        info(f"  FAQPage from core (no accordion): {len(results['faqpage_from_core'])} pages")

    if results["imagegallery_pages"]:
        ok(f"  ImageGallery: {results['imagegallery_pages']}")
    else:
        warn("  ImageGallery: NONE found")

    if results["product_pages"]:
        ok(f"  Product: {results['product_pages']}")
    else:
        warn("  Product: NONE found")

    if results["event_pages"]:
        ok(f"  Event (YOOtheme): {results['event_pages']}")
    else:
        warn("  Event (YOOtheme): NONE found")

    print()
    print("  YOOtheme pages found:")
    for u in results["yootheme_pages"][:20]:
        print(f"    {u}")

    print()

    # Full JSON output for logging
    print(json.dumps(results, indent=2, ensure_ascii=False))

    return 0


if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("--target", default="staging")
    sys.exit(main(ap.parse_args().target))
