#!/usr/bin/env python3
"""
AI Boost — Add-on Bridge Plugin Build Script
Builds installable package ZIPs for AI Boost add-on bridge plugins:
  - pkg_aiboost_yootheme-{version}.zip
  - pkg_aiboost_falang-{version}.zip

Each outer ZIP is a standard Joomla Package (pkg_*) installable via the
Joomla Extension Manager. It contains:
  pkg_aiboost_{addon}.xml          — package manifest
  packages/
    plg_system_aiboost_{addon}-{version}.zip  — inner plugin ZIP

Add-ons are sold separately from the main pkg_aiboost package.

Usage:
    python3 scripts/build-addon-zip.py
    python3 scripts/build-addon-zip.py --addon yootheme
    python3 scripts/build-addon-zip.py --addon falang
    python3 scripts/build-addon-zip.py --version 1.1.0
    python3 scripts/build-addon-zip.py --dry-run
"""

import argparse
import os
import re
import sys
import zipfile
from pathlib import Path

# ── Paths ──────────────────────────────────────────────────────────────────
WORKSPACE    = Path(__file__).resolve().parent.parent
PLUGINS_DIR  = WORKSPACE / "component" / "plugins" / "system"
ADDONS_DIR   = WORKSPACE / "component" / "addons"
DELIVERABLES = WORKSPACE / "deliverables" / "addons"

# Add-on versions are independent of the main pkg_aiboost version
ADDON_VERSIONS = {
    "aiboost_yootheme": "1.0.0",
    "aiboost_falang":   "1.0.0",
}

ALL_ADDONS = list(ADDON_VERSIONS.keys())


def build_plugin_zip(addon_name: str, version: str, tmp_dir: Path) -> Path:
    """Build the inner plg_system_{addon_name}-{version}.zip."""
    plugin_dir = PLUGINS_DIR / addon_name

    if not plugin_dir.exists():
        sys.exit(f"ERROR: Plugin directory not found: {plugin_dir}")

    zip_name = f"plg_system_{addon_name}-{version}.zip"
    zip_path = tmp_dir / zip_name

    with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as zf:
        for fpath in sorted(plugin_dir.rglob("*")):
            if not fpath.is_file():
                continue
            # Inject version into XML manifest
            if fpath.suffix == ".xml" and fpath.stem == addon_name:
                content = fpath.read_text(encoding="utf-8")
                content = re.sub(r"<version>[^<]+</version>", f"<version>{version}</version>", content)
                zf.writestr(fpath.relative_to(plugin_dir).as_posix(), content)
            else:
                zf.write(fpath, fpath.relative_to(plugin_dir).as_posix())

    return zip_path


def build_addon_zip(addon_name: str, version: str, dry_run: bool = False) -> Path:
    """Build the outer pkg_aiboost_{addon_name}-{version}.zip package."""
    import tempfile

    # Derive the package name: e.g. "aiboost_yootheme" → "pkg_aiboost_yootheme"
    pkg_name = f"pkg_{addon_name}"
    pkg_xml_dir = ADDONS_DIR / pkg_name

    if not pkg_xml_dir.exists():
        sys.exit(f"ERROR: Package manifest directory not found: {pkg_xml_dir}")

    pkg_xml_src = pkg_xml_dir / f"{pkg_name}.xml"
    if not pkg_xml_src.exists():
        sys.exit(f"ERROR: Package manifest not found: {pkg_xml_src}")

    DELIVERABLES.mkdir(parents=True, exist_ok=True)

    output_name = f"{pkg_name}-{version}.zip"
    output_path = DELIVERABLES / output_name

    print(f"  → Building {output_name} ...")

    if dry_run:
        print(f"    [DRY-RUN] Would write: {output_path}")
        return output_path

    with tempfile.TemporaryDirectory(prefix="aiboost_addon_build_") as tmp_str:
        tmp_dir = Path(tmp_str)

        # Build inner plugin ZIP
        inner_zip = build_plugin_zip(addon_name, version, tmp_dir)
        inner_name = inner_zip.name  # e.g. plg_system_aiboost_yootheme-1.0.0.zip

        # Build outer package ZIP
        with zipfile.ZipFile(output_path, "w", zipfile.ZIP_DEFLATED) as zf:
            # Package manifest — inject version + inner ZIP filename
            xml_content = pkg_xml_src.read_text(encoding="utf-8")
            xml_content = re.sub(r"<version>[^<]+</version>", f"<version>{version}</version>", xml_content)
            xml_content = re.sub(
                r"plg_system_" + re.escape(addon_name) + r"-[\d.]+\.zip",
                inner_name,
                xml_content,
            )
            zf.writestr(f"{pkg_name}.xml", xml_content)

            # Inner plugin ZIP inside packages/ folder
            zf.write(inner_zip, f"packages/{inner_name}")

    size_kb = output_path.stat().st_size // 1024
    print(f"    ✓ {output_name} ({size_kb} KB)")
    return output_path


def main() -> None:
    parser = argparse.ArgumentParser(description="Build AI Boost add-on package ZIPs")
    parser.add_argument(
        "--addon",
        choices=ALL_ADDONS + ["all"],
        default="all",
        help="Which add-on to build (default: all)",
    )
    parser.add_argument("--version", help="Override version for selected add-on(s)")
    parser.add_argument("--dry-run", action="store_true", help="Show what would be built")
    parser.add_argument(
        "--force",
        action="store_true",
        help="Override the deprecation guard (legacy outer-pkg format only)",
    )
    args = parser.parse_args()

    # DEPRECATED (Plan 1, 2026-06). Integration bridges now ship as individual
    # plg_system_aiboost_int_* ZIPs built by build-package-zip.py (they are
    # listed in INTEGRATION_PLUGIN_NAMES there). The old outer "pkg_aiboost_*"
    # wrapper format this script produces is no longer part of the release
    # pipeline. Kept behind --force only for one-off legacy rebuilds.
    if not args.force:
        sys.exit(
            "build-addon-zip.py is DEPRECATED.\n"
            "  Integration bridges are built by build-package-zip.py --target=all\n"
            "  (see INTEGRATION_PLUGIN_NAMES there). Re-run that instead.\n"
            "  Pass --force only if you truly need the legacy outer-pkg wrapper."
        )

    addons_to_build = ALL_ADDONS if args.addon == "all" else [args.addon]

    print(f"\n{'='*60}")
    print("  AI Boost — Add-on Bridge Plugin Build")
    print(f"{'='*60}\n")

    built = []
    for addon in addons_to_build:
        version = args.version or ADDON_VERSIONS[addon]
        path = build_addon_zip(addon, version, dry_run=args.dry_run)
        built.append((addon, version, path))

    print(f"\n{'='*60}")
    print("  ✅ BUILD COMPLETE")
    for addon, version, path in built:
        if not args.dry_run:
            print(f"  {path.name}  ({path.stat().st_size // 1024} KB)")
        else:
            print(f"  [DRY-RUN] {path.name}")
    print(f"{'='*60}")

    if not args.dry_run:
        print(f"\n  Output: {DELIVERABLES}\n")
        print("  Install via Joomla Extension Manager → Upload Package File")
        print(f"  (select the pkg_* ZIPs from {DELIVERABLES.relative_to(WORKSPACE)})\n")


if __name__ == "__main__":
    main()
