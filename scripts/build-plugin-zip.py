#!/usr/bin/env python3
"""
Build script for AI Boost for Joomla plugin ZIP.

Usage:
    python3 scripts/build-plugin-zip.py

Output:
    deliverables/plugin/plg_system_joomlaboost-{version}.zip

ZIP structure is flat (no subfolder) as required by Joomla installer.

⚠️  Nakon build-a UVIJEK pokreni i installer:
    python3 scripts/install-to-staging.py
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
VERSION_PHP = PLUGIN_SRC / "src" / "Version.php"


def get_version() -> str:
    content = MANIFEST.read_text(encoding="utf-8")
    match = re.search(r"<version>([\d.]+(?:-\w+)?)</version>", content)
    if not match:
        print("ERROR: Could not find <version> in joomlaboost.xml", file=sys.stderr)
        sys.exit(1)
    return match.group(1)


def sync_version_php(version: str) -> None:
    if not VERSION_PHP.exists():
        print(f"WARNING: Version.php not found at {VERSION_PHP}, skipping sync.", file=sys.stderr)
        return
    content = VERSION_PHP.read_text(encoding="utf-8")
    updated, count = re.subn(
        r"(public const VERSION = ')[^']+';",
        rf"\g<1>{version}';",
        content,
    )
    if count == 0:
        print(
            "ERROR: Could not find VERSION constant in Version.php — "
            "sync skipped. Check that the constant format matches: "
            "public const VERSION = '...';",
            file=sys.stderr,
        )
        sys.exit(1)
    if updated == content:
        print(f"Version.php already at {version}, no change needed.")
    else:
        VERSION_PHP.write_text(updated, encoding="utf-8")
        print(f"Version.php updated → {version}")


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
    import argparse
    parser = argparse.ArgumentParser(
        description="Build AI Boost plugin ZIP."
    )
    parser.add_argument(
        "--legacy",
        action="store_true",
        help="Build the legacy plg_system_joomlaboost ZIP (archived v0.40.x). "
             "Default (without flag) builds the new pkg_aiboost package via build-package-zip.py.",
    )
    args = parser.parse_args()

    if not args.legacy:
        # Default: build the new pkg_aiboost package (delegates to build-package-zip.py)
        import importlib.util, runpy
        pkg_script = Path(__file__).parent / "build-package-zip.py"
        if not pkg_script.exists():
            print(f"ERROR: build-package-zip.py not found at {pkg_script}", file=sys.stderr)
            sys.exit(1)
        # runpy executes the module in-process without spawning a subprocess
        runpy.run_path(str(pkg_script), run_name="__main__")
        return

    if not PLUGIN_SRC.exists():
        print(f"ERROR: Plugin source not found: {PLUGIN_SRC}", file=sys.stderr)
        sys.exit(1)

    version = get_version()
    print(f"Building legacy AI Boost plugin v{version} (plg_system_joomlaboost)...")

    sync_version_php(version)
    zip_path = build_zip(version)
    prune_old_zips(keep=zip_path)
    size_kb = zip_path.stat().st_size // 1024

    print(f"Done: {zip_path.relative_to(WORKSPACE_ROOT)}  ({size_kb} KB)")


if __name__ == "__main__":
    main()
