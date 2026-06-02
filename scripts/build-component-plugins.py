#!/usr/bin/env python3
"""
AI Boost — Component Plugin Build Script
Builds the 6 AI Boost system plugins from component/plugins/system/.

Usage:
    python3 scripts/build-component-plugins.py --plugin aiboost_aeo
    python3 scripts/build-component-plugins.py --plugin aiboost_analytics
    python3 scripts/build-component-plugins.py --all
    python3 scripts/build-component-plugins.py --all --bump patch
    python3 scripts/build-component-plugins.py --all --bump minor

Output:
    deliverables/plugin/plg_system_aiboost_aeo-0.7.0.zip
    deliverables/plugin/plg_system_aiboost_schema-0.7.0.zip
    ...

Note: This script handles component/plugins/system/ plugins.
      For standalone/archived plugins, use scripts/build-plugin.py.
"""

import argparse
import os
import re
import shutil
import sys
import tempfile
import zipfile
from pathlib import Path

SCRIPT_DIR   = Path(__file__).resolve().parent
REPO_ROOT    = SCRIPT_DIR.parent
PLUGINS_DIR  = REPO_ROOT / "component" / "plugins" / "system"
OUT_DIR      = REPO_ROOT / "deliverables" / "plugin"

# The 6 core pkg_aiboost plugins
PLUGINS = [
    "aiboost_schema",
    "aiboost_sitemap",
    "aiboost_social",
    "aiboost_analytics",
    "aiboost_aeo",
    "aiboost_core",
]


def get_version_from_xml(plugin_dir: Path, slug: str) -> str:
    """Extract <version> from the plugin XML manifest."""
    xml_path = plugin_dir / f"{slug}.xml"
    if not xml_path.exists():
        print(f"  [WARN] XML not found: {xml_path}", file=sys.stderr)
        return "0.7.0"
    content = xml_path.read_text(encoding="utf-8")
    m = re.search(r"<version>\s*([\d.]+)\s*</version>", content)
    return m.group(1) if m else "0.7.0"


def bump_version(version: str, level: str) -> str:
    """Bump a semantic version string by patch or minor."""
    try:
        parts = [int(x) for x in version.split(".")]
        while len(parts) < 3:
            parts.append(0)
    except ValueError:
        return version

    if level == "patch":
        parts[2] += 1
    elif level == "minor":
        parts[1] += 1
        parts[2] = 0
    elif level == "major":
        parts[0] += 1
        parts[1] = 0
        parts[2] = 0

    return ".".join(str(p) for p in parts)


def set_version_in_xml(plugin_dir: Path, slug: str, new_version: str) -> None:
    """Write the new version back into the XML manifest."""
    xml_path = plugin_dir / f"{slug}.xml"
    content  = xml_path.read_text(encoding="utf-8")
    content  = re.sub(
        r"(<version>)\s*[\d.]+\s*(</version>)",
        rf"\g<1>{new_version}\g<2>",
        content,
    )
    xml_path.write_text(content, encoding="utf-8")


def build_plugin(slug: str, bump: str | None = None, dry_run: bool = False) -> Path | None:
    """Build a single plugin ZIP. Returns the output path or None on failure."""
    plugin_dir = PLUGINS_DIR / slug

    if not plugin_dir.exists():
        print(f"  [ERROR] Plugin directory not found: {plugin_dir}", file=sys.stderr)
        return None

    version = get_version_from_xml(plugin_dir, slug)

    if bump:
        new_version = bump_version(version, bump)
        if not dry_run:
            set_version_in_xml(plugin_dir, slug, new_version)
            print(f"  Bumped {slug}: {version} → {new_version}")
        else:
            print(f"  [DRY RUN] Would bump {slug}: {version} → {new_version}")
        version = new_version

    zip_name = f"plg_system_{slug}-{version}.zip"
    out_path = OUT_DIR / zip_name

    print(f"  Building {zip_name} from {plugin_dir.relative_to(REPO_ROOT)}")

    if dry_run:
        print(f"  [DRY RUN] Would write: {out_path}")
        return out_path

    OUT_DIR.mkdir(parents=True, exist_ok=True)

    with tempfile.TemporaryDirectory() as tmpdir:
        tmp        = Path(tmpdir)
        plugin_tmp = tmp / slug
        shutil.copytree(plugin_dir, plugin_tmp)

        # Bundle shared lib files so the plugin is truly standalone
        lib_src = REPO_ROOT / "component" / "lib" / "src"
        lib_dst = plugin_tmp / "lib" / "src"
        lib_dst.mkdir(parents=True, exist_ok=True)
        for cls in ["ProGate", "ConflictManager", "LicenseValidator"]:
            src_file = lib_src / f"{cls}.php"
            if src_file.exists():
                shutil.copy2(src_file, lib_dst / f"{cls}.php")

        # Remove cruft
        for junk in plugin_tmp.rglob("__pycache__"):
            shutil.rmtree(junk, ignore_errors=True)
        for junk in plugin_tmp.rglob(".DS_Store"):
            junk.unlink(missing_ok=True)
        for junk in plugin_tmp.rglob("*.pyc"):
            junk.unlink(missing_ok=True)

        with zipfile.ZipFile(out_path, "w", zipfile.ZIP_DEFLATED) as zf:
            for file in sorted(plugin_tmp.rglob("*")):
                if file.is_file():
                    arcname = file.relative_to(plugin_tmp)
                    zf.write(file, arcname)
                    print(f"    + {arcname}")

    size_kb = out_path.stat().st_size // 1024
    print(f"  \u2705  {out_path.relative_to(REPO_ROOT)}  ({size_kb} KB)")
    return out_path


def main() -> int:
    parser = argparse.ArgumentParser(
        description="AI Boost component plugin builder",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=f"Available plugins: {', '.join(PLUGINS)}",
    )
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument(
        "--plugin", choices=PLUGINS, metavar="SLUG",
        help=f"Build one plugin. Choices: {', '.join(PLUGINS)}",
    )
    group.add_argument(
        "--all", action="store_true",
        help="Build all 6 plugins",
    )
    parser.add_argument(
        "--bump", choices=["patch", "minor", "major"],
        help="Bump version before building (updates the XML manifest)",
    )
    parser.add_argument(
        "--dry-run", action="store_true",
        help="Print actions without writing files or modifying versions",
    )

    args = parser.parse_args()

    print(f"\nAI Boost Component Plugin Builder")
    print(f"{'=' * 50}")

    if args.plugin:
        result = build_plugin(args.plugin, bump=args.bump, dry_run=args.dry_run)
        return 0 if result else 1

    if args.all:
        errors = 0
        for slug in PLUGINS:
            print()
            result = build_plugin(slug, bump=args.bump, dry_run=args.dry_run)
            if not result:
                errors += 1
        print()
        if errors:
            print(f"[FAIL] {errors} plugin(s) failed to build")
            return 1
        print(f"All {len(PLUGINS)} plugins built successfully.")
        return 0

    return 0


if __name__ == "__main__":
    sys.exit(main())
