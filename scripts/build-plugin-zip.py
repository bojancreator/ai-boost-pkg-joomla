#!/usr/bin/env python3
"""
Build script for AI Boost for Joomla plugin ZIP.

Usage:
    python3 scripts/build-plugin-zip.py

Output:
    deliverables/plugin/plg_system_joomlaboost-{version}.zip

ZIP structure is flat (no subfolder) as required by Joomla installer.
"""

import os
import re
import sys
import zipfile
from pathlib import Path

WORKSPACE_ROOT = Path(__file__).parent.parent
PLUGIN_SRC = WORKSPACE_ROOT / "plugin" / "src" / "plugins" / "system" / "joomlaboost"
OUTPUT_DIR = WORKSPACE_ROOT / "deliverables" / "plugin"
MANIFEST = PLUGIN_SRC / "joomlaboost.xml"


def get_version() -> str:
    content = MANIFEST.read_text(encoding="utf-8")
    match = re.search(r"<version>([\d.]+(?:-\w+)?)</version>", content)
    if not match:
        print("ERROR: Could not find <version> in joomlaboost.xml", file=sys.stderr)
        sys.exit(1)
    return match.group(1)


def build_zip(version: str) -> Path:
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)
    zip_name = f"plg_system_joomlaboost-{version}.zip"
    zip_path = OUTPUT_DIR / zip_name

    with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as zf:
        for file_path in sorted(PLUGIN_SRC.rglob("*")):
            if file_path.is_file():
                arcname = file_path.relative_to(PLUGIN_SRC)
                zf.write(file_path, arcname)

    return zip_path


def prune_old_zips(keep: Path) -> None:
    for old_zip in OUTPUT_DIR.glob("plg_system_joomlaboost-*.zip"):
        if old_zip != keep:
            old_zip.unlink()
            print(f"Removed old ZIP: {old_zip.name}")


def main() -> None:
    if not PLUGIN_SRC.exists():
        print(f"ERROR: Plugin source not found: {PLUGIN_SRC}", file=sys.stderr)
        sys.exit(1)

    version = get_version()
    print(f"Building AI Boost for Joomla v{version}...")

    zip_path = build_zip(version)
    prune_old_zips(keep=zip_path)
    size_kb = zip_path.stat().st_size // 1024

    print(f"Done: {zip_path.relative_to(WORKSPACE_ROOT)}  ({size_kb} KB)")


if __name__ == "__main__":
    main()
