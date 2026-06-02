#!/usr/bin/env python3
"""
AI Boost — Plugin Build Script
Builds plugin ZIPs for both standalone plugins (plugins/) and component plugins
(component/plugins/system/).

STANDALONE PLUGINS (plugins/ directory):
    python3 scripts/build-plugin.py --plugin aeo
    python3 scripts/build-plugin.py --plugin opengraph
    python3 scripts/build-plugin.py --plugin codemanager
    python3 scripts/build-plugin.py --plugin schema
    python3 scripts/build-plugin.py --plugin hreflang
    python3 scripts/build-plugin.py --all
    python3 scripts/build-plugin.py --bundle

COMPONENT PLUGINS (component/plugins/system/ directory):
    python3 scripts/build-plugin.py --component aiboost_aeo
    python3 scripts/build-plugin.py --component aiboost_seo
    python3 scripts/build-plugin.py --component-all
    python3 scripts/build-plugin.py --component-all --bump patch

    Note: --component and --component-all delegate to build-component-plugins.py.

Output:
    deliverables/plugins/plg_system_aiboost_aeo-1.0.0.zip    (standalone)
    deliverables/plugin/plg_system_aiboost_aeo-0.7.0.zip     (component)
    ...

Version note (v0.7.0 milestone):
    component/Version.php was already at 0.7.6 at the time the v0.7.0 foundation
    was implemented. The file was intentionally left at 0.7.6 — downgrading would
    break staging installations. All 6 component plugin XMLs were set to 0.7.0.
"""

import argparse
import os
import re
import shutil
import subprocess
import sys
import tempfile
import zipfile
from pathlib import Path

SCRIPT_DIR   = Path(__file__).resolve().parent
REPO_ROOT    = SCRIPT_DIR.parent
PLUGINS_DIR  = REPO_ROOT / "plugins"
OUT_DIR      = REPO_ROOT / "deliverables" / "plugins"

# Map short name → folder name in plugins/
PLUGIN_MAP = {
    "aeo":         "aiboost-aeo",
    "opengraph":   "aiboost-opengraph",
    "codemanager": "aiboost-codemanager",
    "schema":      "aiboost-schema",
    "hreflang":    "aiboost-hreflang",
}

# Slug used inside the ZIP (matches XML manifest filename root)
PLUGIN_SLUG = {
    "aeo":         "aiboost_aeo",
    "opengraph":   "aiboost_opengraph",
    "codemanager": "aiboost_codemanager",
    "schema":      "aiboost_schema",
    "hreflang":    "aiboost_hreflang",
}


def get_version_from_xml(plugin_folder: Path, slug: str) -> str:
    """Extract version from the plugin XML manifest."""
    xml_path = plugin_folder / f"{slug}.xml"
    if not xml_path.exists():
        print(f"  [WARN] XML not found: {xml_path}", file=sys.stderr)
        return "1.0.0"
    content = xml_path.read_text(encoding="utf-8")
    m = re.search(r"<version>\s*([\d.]+)\s*</version>", content)
    return m.group(1) if m else "1.0.0"


def build_plugin(short_name: str, dry_run: bool = False) -> Path | None:
    """Build a single plugin ZIP. Returns the output path."""
    if short_name not in PLUGIN_MAP:
        print(f"  [ERROR] Unknown plugin: {short_name}. Valid: {list(PLUGIN_MAP)}", file=sys.stderr)
        return None

    folder = PLUGINS_DIR / PLUGIN_MAP[short_name]
    slug   = PLUGIN_SLUG[short_name]

    if not folder.exists():
        print(f"  [ERROR] Plugin directory not found: {folder}", file=sys.stderr)
        return None

    version  = get_version_from_xml(folder, slug)
    zip_name = f"plg_system_{slug}-{version}.zip"
    out_path = OUT_DIR / zip_name

    print(f"  Building {zip_name} from {folder.relative_to(REPO_ROOT)}")

    if dry_run:
        print(f"  [DRY RUN] Would write: {out_path}")
        return out_path

    OUT_DIR.mkdir(parents=True, exist_ok=True)

    with tempfile.TemporaryDirectory() as tmpdir:
        tmp = Path(tmpdir)

        # Copy all plugin files into a temp folder
        plugin_tmp = tmp / slug
        shutil.copytree(folder, plugin_tmp)

        # Remove any __pycache__ or .DS_Store etc.
        for junk in plugin_tmp.rglob("__pycache__"):
            shutil.rmtree(junk, ignore_errors=True)
        for junk in plugin_tmp.rglob(".DS_Store"):
            junk.unlink(missing_ok=True)

        # Create ZIP
        with zipfile.ZipFile(out_path, "w", zipfile.ZIP_DEFLATED) as zf:
            for file in sorted(plugin_tmp.rglob("*")):
                if file.is_file():
                    arcname = file.relative_to(plugin_tmp)
                    zf.write(file, arcname)
                    print(f"    + {arcname}")

    size_kb = out_path.stat().st_size // 1024
    print(f"  ✅  {out_path.relative_to(REPO_ROOT)}  ({size_kb} KB)")
    return out_path


def _collect_plugin_versions() -> dict[str, str]:
    """Return {short_name: version} for all plugins."""
    versions = {}
    for short_name, folder_name in PLUGIN_MAP.items():
        folder = PLUGINS_DIR / folder_name
        slug   = PLUGIN_SLUG[short_name]
        versions[short_name] = get_version_from_xml(folder, slug)
    return versions


def _bundle_version(versions: dict[str, str]) -> str:
    """Derive bundle version: use the common version, or the highest semantic version."""
    unique = set(versions.values())
    if len(unique) == 1:
        return unique.pop()
    # Semantic version sort: compare as tuples of ints
    def semver_key(v: str) -> tuple[int, ...]:
        try:
            return tuple(int(x) for x in v.split("."))
        except ValueError:
            return (0,)
    return max(versions.values(), key=semver_key)


def _generate_pkg_manifest(bundle_ver: str, plugin_zips: list[Path]) -> str:
    """Generate pkg_aiboost_standalone.xml content for the bundle."""
    file_entries = ""
    for zip_path in plugin_zips:
        # Derive plugin id and group from the ZIP filename
        # e.g. plg_system_aiboost_aeo-1.0.0.zip  →  id=aiboost_aeo  group=system
        stem = zip_path.stem  # plg_system_aiboost_aeo-1.0.0
        base = stem.split("-")[0]  # plg_system_aiboost_aeo
        parts = base.split("_", 2)  # ['plg', 'system', 'aiboost_aeo']
        plugin_id    = parts[2] if len(parts) >= 3 else base
        plugin_group = parts[1] if len(parts) >= 2 else "system"
        file_entries += (
            f'        <file type="plugin" id="{plugin_id}"'
            f' group="{plugin_group}">{zip_path.name}</file>\n'
        )

    return f"""<?xml version="1.0" encoding="utf-8"?>
<extension type="package" version="3.9" method="upgrade">
    <name>AI Boost Plugin Bundle</name>
    <packagename>aiboost_standalone</packagename>
    <author>AI Boost Team</author>
    <creationDate>May 2026</creationDate>
    <copyright>(C) 2025 AI Boost (aiboostnow.com). All rights reserved.</copyright>
    <license>GNU General Public License version 2 or later</license>
    <authorEmail>info@aiboostnow.com</authorEmail>
    <authorUrl>https://aiboostnow.com</authorUrl>
    <version>{bundle_ver}</version>
    <description>AI Boost Plugin Bundle — installs all 5 standalone plugins in one step:
Schema.org, OpenGraph, Hreflang, Code Manager, AEO/llms.txt.</description>
    <php_minimum>8.1.0</php_minimum>
    <joomla_minimum>5.0.0</joomla_minimum>
    <url>https://aiboostnow.com</url>
    <files folder="packages">
{file_entries}    </files>
</extension>
"""


def build_bundle(dry_run: bool = False) -> Path | None:
    """Build a valid Joomla pkg_ bundle ZIP containing all 5 plugin ZIPs."""
    versions    = _collect_plugin_versions()
    bundle_ver  = _bundle_version(versions)

    print(f"\nBuilding bundle: aiboost-plugin-bundle-{bundle_ver}.zip")

    # Build all individual plugin ZIPs first
    plugin_zips: list[Path] = []
    for short_name in PLUGIN_MAP:
        zip_path = build_plugin(short_name, dry_run=dry_run)
        if zip_path:
            plugin_zips.append(zip_path)

    bundle_path = OUT_DIR / f"aiboost-plugin-bundle-{bundle_ver}.zip"

    if dry_run:
        print(f"  [DRY RUN] Would write bundle: {bundle_path}")
        return bundle_path

    expected = len(PLUGIN_MAP)
    if len(plugin_zips) != expected:
        failed = expected - len(plugin_zips)
        print(
            f"  [ERROR] Only {len(plugin_zips)}/{expected} plugins built successfully "
            f"({failed} failed). Bundle creation aborted — all {expected} plugins are required.",
            file=sys.stderr,
        )
        return None

    manifest_xml = _generate_pkg_manifest(bundle_ver, plugin_zips)

    OUT_DIR.mkdir(parents=True, exist_ok=True)

    with zipfile.ZipFile(bundle_path, "w", zipfile.ZIP_DEFLATED) as zf:
        # 1. Package manifest at ZIP root
        zf.writestr("pkg_aiboost_standalone.xml", manifest_xml)
        print("  + pkg_aiboost_standalone.xml  (generated)")

        # 2. Plugin ZIPs under packages/ sub-folder
        for zip_path in plugin_zips:
            arcname = f"packages/{zip_path.name}"
            zf.write(zip_path, arcname)
            print(f"  + {arcname}")

    size_kb = bundle_path.stat().st_size // 1024
    print(f"  ✅  {bundle_path.relative_to(REPO_ROOT)}  ({size_kb} KB)")
    return bundle_path


def main() -> int:
    parser = argparse.ArgumentParser(
        description="AI Boost plugin builder — handles both standalone (plugins/) and component (component/plugins/system/) plugins",
        formatter_class=argparse.RawDescriptionHelpFormatter,
    )
    group  = parser.add_mutually_exclusive_group(required=True)
    group.add_argument("--plugin",         choices=list(PLUGIN_MAP), metavar="SHORT_NAME",
                       help=f"Build a standalone plugin. Choices: {', '.join(PLUGIN_MAP)}")
    group.add_argument("--all",            action="store_true",
                       help="Build all 5 standalone plugins")
    group.add_argument("--bundle",         action="store_true",
                       help="Build all standalone plugins + bundle ZIP")
    group.add_argument("--component",      metavar="SLUG",
                       help="Build a single component plugin (e.g. aiboost_aeo). Delegates to build-component-plugins.py")
    group.add_argument("--component-all",  action="store_true",
                       help="Build all 6 component plugins. Delegates to build-component-plugins.py")
    parser.add_argument("--bump", choices=["patch", "minor", "major"],
                        help="Bump version before building (component plugins only)")
    parser.add_argument("--dry-run", action="store_true", help="Print actions without writing files")

    args = parser.parse_args()

    print(f"\nAI Boost Plugin Builder")
    print(f"{'=' * 50}")

    if args.plugin:
        result = build_plugin(args.plugin, dry_run=args.dry_run)
        return 0 if result else 1

    if args.all:
        errors = 0
        for short_name in PLUGIN_MAP:
            print()
            result = build_plugin(short_name, dry_run=args.dry_run)
            if not result:
                errors += 1
        if errors:
            print(f"\n[FAIL] {errors} plugin(s) failed to build")
            return 1
        print(f"\nAll {len(PLUGIN_MAP)} plugins built successfully.")
        return 0

    if args.bundle:
        result = build_bundle(dry_run=args.dry_run)
        return 0 if result else 1

    # ── Component plugin delegation ──────────────────────────────────────────
    if args.component or args.component_all:
        comp_script = SCRIPT_DIR / "build-component-plugins.py"
        cmd = [sys.executable, str(comp_script)]
        if args.component:
            cmd.extend(["--plugin", args.component])
        else:
            cmd.append("--all")
        if getattr(args, "bump", None):
            cmd.extend(["--bump", args.bump])
        if args.dry_run:
            cmd.append("--dry-run")
        return subprocess.run(cmd).returncode

    return 0


if __name__ == "__main__":
    sys.exit(main())
