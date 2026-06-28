#!/usr/bin/env python3
"""
Targeted inspection of specific accordion/gallery pages.
Checks JSON-LD output and HTML structure to see why YOOtheme types don't emit.

Run:
  python _creds_run.py scripts/_yootheme_targeted.py --target staging
"""

from __future__ import annotations

import argparse
import re
import sys

import _qa_common as qa


def header(txt: str) -> None:
    print(f"\n{'=' * 60}\n  {txt}\n{'=' * 60}")


def main(target: str) -> int:
    qa.setup_console_utf8()

    s, admin_php, base = qa.connect(target)
    print(f"Connected: {base}")

    # Known accordion pages from deep scan
    ACCORDION_PAGES = [
        base + "/petrus-2025",
        base + "/oprema-i-saveti",
        base + "/samo-za-clanove",
    ]

    # Known gallery pages (pick a few article ones)
    GALLERY_PAGES = [
        base + "/?view=article&id=216:povlen-jablanik-medvednik-2025&catid=9",
        base + "/petrus-2025",
    ]

    # ── Check accordion pages ─────────────────────────────────────────────

    header("Accordion pages — JSON-LD + HTML structure")

    for url in ACCORDION_PAGES:
        print(f"\n--- {url} ---")
        html = qa.fetch_html(s, url, cache_bust=True)
        objs = qa.jsonld_from_html(html)

        types_found = [o.get("@type") for o in objs if o.get("@type")]
        print(f"  JSON-LD @types: {types_found}")

        # Show accordion HTML context
        acc_matches = re.findall(
            r'(<[^>]*uk-accordion[^>]*>[\s\S]{0,500})',
            html, re.IGNORECASE
        )
        if acc_matches:
            print(f"  Accordion markup ({len(acc_matches)} match(es)):")
            for m in acc_matches[:2]:
                print(f"    {m[:300].strip()!r}")
        else:
            print("  No accordion markup found.")

        # Show first 100 chars of each accordion item (li>a + div)
        acc_items = re.findall(
            r'<li[^>]*>.*?<a[^>]*>(.*?)</a>.*?<div[^>]*>([\s\S]*?)</div>',
            html, re.IGNORECASE
        )
        if acc_items:
            print(f"  Accordion items (first {min(3, len(acc_items))}):")
            for q, a in acc_items[:3]:
                print(f"    Q: {re.sub(r'<[^>]+>', '', q).strip()[:80]!r}")
                print(f"    A: {re.sub(r'<[^>]+>', '', a).strip()[:80]!r}")

    # ── Check gallery pages ───────────────────────────────────────────────

    header("Gallery pages — JSON-LD + HTML structure")

    for url in GALLERY_PAGES:
        print(f"\n--- {url} ---")
        html = qa.fetch_html(s, url, cache_bust=True)
        objs = qa.jsonld_from_html(html)

        types_found = [o.get("@type") for o in objs if o.get("@type")]
        print(f"  JSON-LD @types: {types_found}")

        # Look for gallery indicators
        lightbox = re.findall(r'<[^>]*uk-lightbox[^>]*>', html, re.IGNORECASE)
        gallery = re.findall(r'<[^>]*uk-gallery[^>]*>', html, re.IGNORECASE)
        figures = re.findall(r'<figure[^>]*>[\s\S]{0,200}', html, re.IGNORECASE)
        data_cap = re.findall(r'data-caption=["\'][^"\']{0,100}["\']', html)

        print(f"  uk-lightbox elements: {len(lightbox)}")
        print(f"  uk-gallery elements: {len(gallery)}")
        print(f"  <figure> elements: {len(figures)}")
        print(f"  data-caption attrs: {len(data_cap)}")

        if data_cap:
            print(f"  Sample data-caption: {data_cap[0]!r}")
        if lightbox:
            print(f"  Sample lightbox: {lightbox[0][:150]!r}")

    # ── Check yootheme_accordion_selector setting ─────────────────────────

    header("YOOtheme settings check")

    params = qa.export_settings(s, admin_php)
    print(f"  yootheme_accordion_selector = {params.get('yootheme_accordion_selector', '(not set)')!r}")
    print(f"  yootheme_faq_enabled = {params.get('yootheme_faq_enabled')!r}")
    print(f"  yootheme_gallery_enabled = {params.get('yootheme_gallery_enabled')!r}")
    print(f"  yootheme_schema_mapping = {params.get('yootheme_schema_mapping')!r}")
    print(f"  integration_yootheme_enabled = {params.get('integration_yootheme_enabled')!r}")

    # ── Check if plugin reads correct selector ────────────────────────────

    header("Check plugin source for accordion selector logic")
    # This is read-only — we just report what selector is in use
    selector = params.get("yootheme_accordion_selector", ".uk-accordion")
    print(f"  Active accordion CSS selector: {selector!r}")
    print(f"  Default is '.uk-accordion' per manifest.")
    print()
    print("  The plugin's buildAccordionFaqSchema() looks for <li> items inside")
    print("  the accordion container. The question is the first <a> or heading,")
    print("  and the answer is the first div inside. If the selector doesn't match")
    print("  what's in the HTML, no FAQ schema is built.")

    return 0


if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("--target", default="staging")
    sys.exit(main(ap.parse_args().target))
