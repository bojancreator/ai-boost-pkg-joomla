#!/usr/bin/env python3
"""
AI Boost — Update-Server XML Generator (v1 manual channel)

Generates deliverables/updates/pkg_aiboost.xml — the STATIC Joomla
update-server XML for the FREE package (pkg_aiboost). There is no dynamic
update server in v1: this file is uploaded by hand to

    https://aiboostnow.com/updates/pkg_aiboost.xml

which is the URL referenced by <updateservers> in
component/package/pkg_aiboost.xml. Pro deliberately has NO update server in
v1 — Pro ZIPs ship through the Lemon Squeezy "My Orders" portal plus e-mail
notifications (see the comment in component/package/pkg_aiboost_pro.xml).

Two <update> entries are emitted — one targeting Joomla 5.x and one targeting
Joomla 6.x — following the Joomla update-server convention of one entry per
supported major platform, each with a <targetplatform> version regex
("5\\." / "6\\.").

Usage:
    python3 scripts/generate-update-xml.py
    python3 scripts/generate-update-xml.py --version 1.0.0
    python3 scripts/generate-update-xml.py --download-url https://example.com/pkg_aiboost-1.0.0.zip
"""

import argparse
import re
import sys
import xml.dom.minidom
from pathlib import Path
from xml.sax.saxutils import escape, quoteattr

# Force UTF-8 stdout/stderr so the ✓/✗ chars in output don't raise
# UnicodeEncodeError on a default Windows console (cp1252).
for _stream in (sys.stdout, sys.stderr):
    try:
        _stream.reconfigure(encoding="utf-8")  # type: ignore[attr-defined]
    except (AttributeError, ValueError):
        pass

# ── Paths ──────────────────────────────────────────────────────────────────
WORKSPACE = Path(__file__).resolve().parent.parent
VERSION_FILE = WORKSPACE / "component" / "Version.php"
OUTPUT_FILE = WORKSPACE / "deliverables" / "updates" / "pkg_aiboost.xml"

# ── Constants ──────────────────────────────────────────────────────────────
PRODUCT_NAME = "AI Boost for Joomla"
ELEMENT = "pkg_aiboost"
EXTENSION_TYPE = "package"
INFO_URL = "https://aiboostnow.com"
DOWNLOAD_URL_TEMPLATE = "https://aiboostnow.com/downloads/pkg_aiboost-{version}.zip"
UPLOAD_TARGET = "https://aiboostnow.com/updates/pkg_aiboost.xml"
PHP_MINIMUM = "8.1"
DESCRIPTION = (
    "AI-search and SEO toolkit for Joomla: Schema.org JSON-LD, XML sitemap, "
    "OpenGraph and social tags, robots.txt, llms.txt and AEO signals."
)
MAINTAINER = "AI Boost Team"

# One <update> entry per supported Joomla major version, each with a
# <targetplatform> version regex as Joomla expects.
JOOMLA_TARGETS = (r"5\.", r"6\.")

VERSION_RE = re.compile(
    r"public\s+const\s+VERSION\s*=\s*'([\d]+\.[\d]+\.[\d]+(?:-[\w.]+)?)'"
)


def read_version() -> str:
    """Parse the VERSION constant from component/Version.php."""
    if not VERSION_FILE.is_file():
        sys.exit(f"ERROR: {VERSION_FILE} not found.")
    content = VERSION_FILE.read_text(encoding="utf-8")
    match = VERSION_RE.search(content)
    if not match:
        sys.exit("ERROR: Could not find VERSION constant in component/Version.php")
    return match.group(1)


def build_update_entry(version: str, download_url: str, platform_regex: str) -> str:
    """Render one <update> entry for a single Joomla target platform."""
    return f"""    <update>
        <name>{escape(PRODUCT_NAME)}</name>
        <description>{escape(DESCRIPTION)}</description>
        <element>{escape(ELEMENT)}</element>
        <type>{escape(EXTENSION_TYPE)}</type>
        <version>{escape(version)}</version>
        <infourl title={quoteattr(PRODUCT_NAME)}>{escape(INFO_URL)}</infourl>
        <downloads>
            <downloadurl type="full" format="zip">{escape(download_url)}</downloadurl>
        </downloads>
        <maintainer>{escape(MAINTAINER)}</maintainer>
        <maintainerurl>{escape(INFO_URL)}</maintainerurl>
        <targetplatform name="joomla" version={quoteattr(platform_regex)}/>
        <php_minimum>{escape(PHP_MINIMUM)}</php_minimum>
        <tags>
            <tag>stable</tag>
        </tags>
    </update>"""


def build_xml(version: str, download_url: str) -> str:
    entries = "\n".join(
        build_update_entry(version, download_url, target) for target in JOOMLA_TARGETS
    )
    return (
        '<?xml version="1.0" encoding="utf-8"?>\n'
        "<updates>\n"
        f"{entries}\n"
        "</updates>\n"
    )


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Generate the static Joomla update-server XML for pkg_aiboost (Free)."
    )
    parser.add_argument(
        "--version",
        help="Override version (default: VERSION constant in component/Version.php)",
    )
    parser.add_argument(
        "--download-url",
        help=f"Override download URL (default: {DOWNLOAD_URL_TEMPLATE})",
    )
    args = parser.parse_args()

    version = args.version or read_version()
    download_url = args.download_url or DOWNLOAD_URL_TEMPLATE.format(version=version)

    xml_content = build_xml(version, download_url)

    OUTPUT_FILE.parent.mkdir(parents=True, exist_ok=True)
    OUTPUT_FILE.write_text(xml_content, encoding="utf-8")

    # Validate the written file is well-formed XML — fail loudly if not.
    try:
        xml.dom.minidom.parse(str(OUTPUT_FILE))
    except Exception as exc:  # noqa: BLE001 — any parse failure is fatal here
        sys.exit(f"ERROR: Generated XML failed validation: {exc}")

    print(f"✓ Update XML generated: {OUTPUT_FILE}")
    print(f"  Version:      {version}")
    print(f"  Download URL: {download_url}")
    targets = ", ".join(t.replace("\\.", ".x") for t in JOOMLA_TARGETS)
    print(f"  Targets:      Joomla {targets} · PHP >= {PHP_MINIMUM}")
    print()
    print("Next step — upload this file (exact URL matters, it is referenced by")
    print("<updateservers> in component/package/pkg_aiboost.xml):")
    print(f"  {UPLOAD_TARGET}")


if __name__ == "__main__":
    main()
