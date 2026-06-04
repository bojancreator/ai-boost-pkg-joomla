#!/usr/bin/env python3
"""
AI Boost — Package Build Script
Builds pkg_aiboost-{version}.zip (Joomla installable package).

Structure inside outer ZIP:
  pkg_aiboost.xml
  pkg_script.php
  packages/
    com_aiboost-{version}.zip
    plg_system_aiboost_schema-{version}.zip
    plg_system_aiboost_sitemap-{version}.zip
    plg_system_aiboost_social-{version}.zip
    plg_system_aiboost_analytics-{version}.zip
    plg_system_aiboost_aeo-{version}.zip
    plg_system_aiboost_core-{version}.zip
    plg_system_aiboost_code-{version}.zip
    mod_aiboost_health-{version}.zip

Usage:
    python3 scripts/build-package-zip.py
    python3 scripts/build-package-zip.py --dry-run
    python3 scripts/build-package-zip.py --version 0.7.0
"""

import argparse
import os
import re
import shutil
import sys
import tempfile
import zipfile
from pathlib import Path

# ── Paths ──────────────────────────────────────────────────────────────────
WORKSPACE    = Path(__file__).resolve().parent.parent
COMPONENT    = WORKSPACE / "component"
COM_DIR      = COMPONENT / "com_aiboost"
LIB_DIR      = COMPONENT / "lib"
PLUGINS_DIR  = COMPONENT / "plugins" / "system"
MODULES_DIR  = COMPONENT / "modules"
PKG_DIR      = COMPONENT / "package"
DELIVERABLES = WORKSPACE / "deliverables" / "plugin"

PLUGIN_NAMES = [
    "aiboost_schema",
    "aiboost_sitemap",
    "aiboost_social",
    "aiboost_analytics",
    "aiboost_aeo",
    "aiboost_core",
    "aiboost_code",
]

MODULE_NAMES = [
    "mod_aiboost_health",
]

# Task #428 — Pro plugins (closed-source upgrade plugins).
# Physical extraction is staged as follow-up tasks; manifest already
# describes them. Listing here so --target=pro becomes a future no-op
# build once the plugin directories exist.
PRO_PLUGIN_NAMES: list[str] = [
    "aiboost_schema_pro",
    "aiboost_aeo_pro",
    "aiboost_social_pro",   # SKU: og
    "aiboost_hreflang_pro",
    "aiboost_code_pro",
]

# Task #428 — Integration plugins (closed-source bridges per third-party).
INTEGRATION_PLUGIN_NAMES: list[str] = [
    "aiboost_int_falang",
    # "aiboost_int_yootheme",  # follow-up task
]


def read_version() -> str:
    """Read version from component/Version.php."""
    version_file = COMPONENT / "Version.php"
    if not version_file.exists():
        sys.exit(f"ERROR: Version.php not found at {version_file}")

    content = version_file.read_text(encoding="utf-8")
    m = re.search(r"VERSION\s*=\s*'([^']+)'", content)
    if not m:
        sys.exit("ERROR: Could not parse VERSION from Version.php")

    return m.group(1)


# Task #462 — strip every `// @pro:start ... // @pro:end` block from a PHP
# source file. Used to guarantee the FREE package physically cannot contain
# Pro logic, manifest entries, or markers. The pair must be balanced; an
# unbalanced opener is treated as "strip from opener to EOF" so a half-typed
# marker fails loud during the verifier step.
_PRO_BLOCK_RE = re.compile(
    r"^[ \t]*//[ \t]*@pro:start.*?^[ \t]*//[ \t]*@pro:end[^\n]*\n?",
    re.DOTALL | re.MULTILINE,
)
_PRO_OPEN_TO_EOF_RE = re.compile(
    r"^[ \t]*//[ \t]*@pro:start.*\Z",
    re.DOTALL | re.MULTILINE,
)


def strip_pro_blocks(text: str) -> str:
    """Remove every @pro:start ... @pro:end block from a PHP source string.

    Defensive fallback: any unbalanced opener that survives the paired-block
    pass causes everything from that opener to EOF to be dropped. Better to
    truncate a single file (and have the verifier flag it loudly) than to
    silently ship half-stripped Pro logic.
    """
    out = _PRO_BLOCK_RE.sub("", text)
    out = _PRO_OPEN_TO_EOF_RE.sub("", out)
    return out


def add_dir_to_zip(
    zf: zipfile.ZipFile,
    src_dir: Path,
    arc_prefix: str = "",
    strip_pro: bool = False,
    manifest_version: str | None = None,
) -> None:
    """Recursively add all files in src_dir to zf under arc_prefix.

    When `strip_pro=True`, *.php files have @pro:start/@pro:end blocks
    removed before being added (Task #462 build-time stripping).
    """
    for fpath in sorted(src_dir.rglob("*")):
        if fpath.is_file():
            rel_path = fpath.relative_to(src_dir)
            arcname = arc_prefix + "/" + rel_path.as_posix() if arc_prefix else rel_path.as_posix()
            if manifest_version and len(rel_path.parts) == 1 and fpath.suffix.lower() == ".xml":
                content = fpath.read_text(encoding="utf-8", errors="replace")
                content = re.sub(r"<version>[^<]+</version>", f"<version>{manifest_version}</version>", content)
                zf.writestr(arcname, content)
                continue
            if strip_pro and fpath.suffix.lower() == ".php":
                content = fpath.read_text(encoding="utf-8", errors="replace")
                stripped = strip_pro_blocks(content)
                if stripped != content:
                    zf.writestr(arcname, stripped)
                    continue
            zf.write(fpath, arcname)


def build_component_zip(version: str, tmp_dir: Path) -> Path:
    """Build com_aiboost-{version}.zip."""
    zip_path = tmp_dir / f"com_aiboost-{version}.zip"

    print(f"  → Building {zip_path.name} ...")

    with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as zf:
        # Add the component XML manifest at root of ZIP (version injected from Version.php)
        xml_src = COM_DIR / "com_aiboost.xml"
        if xml_src.exists():
            xml_content = xml_src.read_text(encoding="utf-8")
            xml_content = re.sub(r"<version>[^<]+</version>", f"<version>{version}</version>", xml_content)
            zf.writestr("com_aiboost.xml", xml_content)

        # Add installer script at ROOT of ZIP — version constant synced from Version.php
        # (Joomla requires <scriptfile> at root, not inside admin/)
        script_src = COM_DIR / "admin" / "script.php"
        if script_src.exists():
            script_content = script_src.read_text(encoding="utf-8")
            script_content = re.sub(
                r"(public const VERSION\s*=\s*')[^']*(')",
                f"\\g<1>{version}\\g<2>",
                script_content,
            )
            zf.writestr("script.php", script_content)

        # Package Version.php at admin/Version.php so AiBoost\Version is
        # autoloadable at component runtime (autoload.php resolves it from there)
        version_src = COMPONENT / "Version.php"
        if version_src.exists():
            zf.write(version_src, "admin/Version.php")

        # Add all admin/ contents (EXCLUDING script.php — placed at root above).
        # services/provider.php IS included: Joomla 6 requires it to exist so
        # bootComponent() can register ComponentInterface.  The file is kept
        # intentionally minimal (no MVCFactory/MVCComponent dependencies) so it
        # works across Joomla 4–6 without version-specific class availability.
        admin_dir = COM_DIR / "admin"
        if admin_dir.exists():
            for fpath in sorted(admin_dir.rglob("*")):
                if not fpath.is_file():
                    continue
                rel = fpath.relative_to(admin_dir).as_posix()
                if fpath.name == "script.php":
                    continue
                arcname = "admin/" + rel
                if fpath.suffix == ".php":
                    content = fpath.read_text(encoding="utf-8", errors="replace")
                    stripped = strip_pro_blocks(content)
                    if stripped != content:
                        zf.writestr(arcname, stripped)
                        continue
                zf.write(fpath, arcname)

        # Sync admin/css/*.css → media/css/ so Joomla can serve them via /media/com_aiboost/css/.
        # HTMLHelper::_('stylesheet', 'com_aiboost/...') resolves ONLY against /media/com_aiboost/,
        # not against /administrator/components/com_aiboost/. Without this sync, ab-tokens.css and
        # ab-components.css get installed only to the admin folder and are silently dropped by Joomla.
        admin_css = COM_DIR / "admin" / "css"
        media_css = COM_DIR / "media" / "css"
        if admin_css.exists():
            media_css.mkdir(parents=True, exist_ok=True)
            for css in admin_css.glob("*.css"):
                shutil.copy2(css, media_css / css.name)

        # Add media/ folder (CSS, JS — installed to /media/com_aiboost/ by Joomla).
        media_dir = COM_DIR / "media"
        if media_dir.exists():
            for fpath in sorted(media_dir.rglob("*")):
                if fpath.is_file():
                    arcname = "media/" + fpath.relative_to(media_dir).as_posix()
                    zf.write(fpath, arcname)

        # Copy shared lib/ into admin/lib/ inside the ZIP.
        # lib/ is maintained separately in component/lib/ source,
        # but installed as administrator/components/com_aiboost/lib/
        # Task #462: strip @pro blocks from manifest + lib PHP files.
        if LIB_DIR.exists():
            for fpath in sorted(LIB_DIR.rglob("*")):
                if fpath.is_file():
                    arcname = "admin/lib/" + fpath.relative_to(LIB_DIR).as_posix()
                    existing = set(zf.namelist())
                    if arcname in existing:
                        continue
                    if fpath.suffix == ".php":
                        content = fpath.read_text(encoding="utf-8", errors="replace")
                        stripped = strip_pro_blocks(content)
                        if stripped != content:
                            zf.writestr(arcname, stripped)
                            continue
                    zf.write(fpath, arcname)

    print(f"    ✓ {zip_path.name} ({zip_path.stat().st_size // 1024} KB)")
    return zip_path


def build_plugin_zip(plugin_name: str, version: str, tmp_dir: Path, strip_pro: bool = True) -> Path:
    """Build plg_system_{plugin_name}-{version}.zip.

    Task #462: free plugins are stripped of any `// @pro:start ... // @pro:end`
    blocks. Pro upgrade plugins (`*_pro`) skip stripping — they ARE the Pro
    payload and their @pro blocks contain the real Pro logic.
    """
    plugin_dir = PLUGINS_DIR / plugin_name
    if not plugin_dir.exists():
        sys.exit(f"ERROR: Plugin directory not found: {plugin_dir}")

    zip_name = f"plg_system_{plugin_name}-{version}.zip"
    zip_path = tmp_dir / zip_name

    print(f"  → Building {zip_name} ...")

    with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as zf:
        # Add all plugin source files.
        # Plugins no longer bundle lib/ classes — ProGate/ConflictManager removed in P01.
        # Each plugin Extension class reads #__aiboost_settings directly via Factory::getDbo().
        add_dir_to_zip(zf, plugin_dir, strip_pro=strip_pro, manifest_version=version)

    print(f"    ✓ {zip_name} ({zip_path.stat().st_size // 1024} KB)")
    return zip_path


def build_module_zip(module_name: str, version: str, tmp_dir: Path) -> Path:
    """Build {module_name}-{version}.zip."""
    module_dir = MODULES_DIR / module_name
    if not module_dir.exists():
        sys.exit(f"ERROR: Module directory not found: {module_dir}")

    zip_name = f"{module_name}-{version}.zip"
    zip_path = tmp_dir / zip_name

    print(f"  → Building {zip_name} ...")

    with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as zf:
        add_dir_to_zip(zf, module_dir, manifest_version=version)

    print(f"    ✓ {zip_name} ({zip_path.stat().st_size // 1024} KB)")
    return zip_path


def build_vue_admin() -> None:
    """Build Vue.js admin bundle (component/com_aiboost/vue-admin → media/js/admin-vue.js)."""
    import subprocess as _sp
    vue_dir = WORKSPACE / "component" / "com_aiboost" / "vue-admin"
    if not vue_dir.exists():
        print("  ⚠️  vue-admin directory not found — skipping Vue build")
        return
    print("  → Building Vue admin bundle ...")
    pnpm_bin = "pnpm.cmd" if os.name == "nt" else "pnpm"
    result = _sp.run([pnpm_bin, "run", "build"], cwd=str(vue_dir), capture_output=True, text=True)
    if result.returncode != 0:
        print(f"  [WARN] Vue build failed:\n{result.stderr}")
    else:
        print("  ✓ Vue admin bundle built")


def build_package_zip(version: str, dry_run: bool = False) -> Path:
    """Orchestrate: build all sub-ZIPs, then assemble outer package ZIP."""

    print(f"\n{'='*60}")
    print(f"  AI Boost — Package Build v{version}")
    print(f"{'='*60}\n")

    DELIVERABLES.mkdir(parents=True, exist_ok=True)

    # Build Vue.js admin bundle before packaging (outputs to com_aiboost/media/js/)
    build_vue_admin()

    with tempfile.TemporaryDirectory(prefix="aiboost_build_") as tmp_str:
        tmp_dir = Path(tmp_str)

        print("Building sub-packages:")
        com_zip = build_component_zip(version, tmp_dir)

        plg_zips = []
        for plugin_name in PLUGIN_NAMES:
            plg_zips.append(build_plugin_zip(plugin_name, version, tmp_dir))

        # The admin Health module is a Pro-only surface — it ships in
        # pkg_aiboost_pro, NOT in this base (Free) package.

        output_name = f"pkg_aiboost-{version}.zip"
        output_path = DELIVERABLES / output_name

        if dry_run:
            print(f"\n[DRY-RUN] Would write: {output_path}")
            return output_path

        print(f"\nAssembling package ZIP: {output_name}")

        with zipfile.ZipFile(output_path, "w", zipfile.ZIP_DEFLATED) as zf:
            # Package manifest — version injected from Version.php
            pkg_xml = PKG_DIR / "pkg_aiboost.xml"
            if not pkg_xml.exists():
                sys.exit(f"ERROR: pkg_aiboost.xml not found at {pkg_xml}")

            xml_content = pkg_xml.read_text(encoding="utf-8")
            xml_content = re.sub(r"<version>[^<]+</version>", f"<version>{version}</version>", xml_content)
            xml_content = re.sub(r"com_aiboost-[\d.]+\.zip", f"com_aiboost-{version}.zip", xml_content)
            for pn in PLUGIN_NAMES:
                xml_content = re.sub(
                    rf"plg_system_{pn}-[\d.]+\.zip",
                    f"plg_system_{pn}-{version}.zip",
                    xml_content,
                )
            zf.writestr("pkg_aiboost.xml", xml_content)

            # Package installer script — sync VERSION constant from Version.php
            pkg_script = PKG_DIR / "pkg_script.php"
            if pkg_script.exists():
                script_content = pkg_script.read_text(encoding="utf-8")
                script_content = re.sub(
                    r"(public const VERSION\s*=\s*')[^']*(')",
                    f"\\g<1>{version}\\g<2>",
                    script_content,
                )
                zf.writestr("pkg_script.php", script_content)

            # Sub-ZIPs in packages/ subfolder
            zf.write(com_zip, f"packages/{com_zip.name}")
            for plg_zip in plg_zips:
                zf.write(plg_zip, f"packages/{plg_zip.name}")

        size_kb = output_path.stat().st_size // 1024
        print(f"\n{'='*60}")
        print(f"  ✅ BUILD COMPLETE")
        print(f"  Output : {output_path}")
        print(f"  Size   : {size_kb} KB")
        print(f"  Version: {version}")
        print(f"{'='*60}\n")

    return output_path


def build_addons(dry_run: bool = False) -> None:
    """Delegate add-on ZIP builds to build-addon-zip.py (same directory)."""
    import subprocess

    addon_script = Path(__file__).parent / "build-addon-zip.py"
    if not addon_script.exists():
        print("  ⚠️  build-addon-zip.py not found — skipping add-on build")
        return

    cmd = [sys.executable, str(addon_script)]
    if dry_run:
        cmd.append("--dry-run")

    result = subprocess.run(cmd, check=False)
    if result.returncode != 0:
        print(f"  ⚠️  Add-on build exited with code {result.returncode}")


def build_pro_package_zip(version: str, dry_run: bool = False) -> Path:
    """Build pkg_aiboost_pro-{version}.zip with all 5 Pro upgrade plugins."""
    DELIVERABLES.mkdir(parents=True, exist_ok=True)
    output_name = f"pkg_aiboost_pro-{version}.zip"
    output_path = DELIVERABLES / output_name

    print(f"\nAssembling Pro package ZIP: {output_name}")

    if dry_run:
        print(f"[DRY-RUN] Would write: {output_path}")
        return output_path

    with tempfile.TemporaryDirectory(prefix="aiboost_pro_build_") as tmp_str:
        tmp_dir = Path(tmp_str)

        pro_zips = []
        for plugin_name in PRO_PLUGIN_NAMES:
            # Pro plugins ARE the Pro payload — keep their @pro blocks intact.
            pro_zips.append(build_plugin_zip(plugin_name, version, tmp_dir, strip_pro=False))

        # The admin Health module is a Pro-only surface — it ships here, not in
        # the base (Free) package.
        mod_zips = []
        for module_name in MODULE_NAMES:
            mod_zips.append(build_module_zip(module_name, version, tmp_dir))

        with zipfile.ZipFile(output_path, "w", zipfile.ZIP_DEFLATED) as zf:
            pkg_xml = PKG_DIR / "pkg_aiboost_pro.xml"
            if not pkg_xml.exists():
                sys.exit(f"ERROR: pkg_aiboost_pro.xml not found at {pkg_xml}")
            xml_content = pkg_xml.read_text(encoding="utf-8")
            xml_content = re.sub(r"<version>[^<]+</version>", f"<version>{version}</version>", xml_content)
            for pn in PRO_PLUGIN_NAMES:
                xml_content = re.sub(
                    rf"plg_system_{pn}-[\d.]+\.zip",
                    f"plg_system_{pn}-{version}.zip",
                    xml_content,
                )
            for mn in MODULE_NAMES:
                xml_content = re.sub(
                    rf"{mn}-[\d.]+\.zip",
                    f"{mn}-{version}.zip",
                    xml_content,
                )
            zf.writestr("pkg_aiboost_pro.xml", xml_content)

            pkg_script = PKG_DIR / "pkg_script_pro.php"
            if pkg_script.exists():
                script_content = pkg_script.read_text(encoding="utf-8")
                script_content = re.sub(
                    r"(public const VERSION\s*=\s*')[^']*(')",
                    f"\\g<1>{version}\\g<2>",
                    script_content,
                )
                zf.writestr("pkg_script_pro.php", script_content)

            for plg_zip in pro_zips:
                zf.write(plg_zip, f"packages/{plg_zip.name}")
            for mod_zip in mod_zips:
                zf.write(mod_zip, f"packages/{mod_zip.name}")

    size_kb = output_path.stat().st_size // 1024
    print(f"  ✓ {output_name} ({size_kb} KB)")
    return output_path


def build_integration_zip(name: str, version: str) -> Path:
    """Build a single plg_system_<name>-<version>.zip directly into deliverables/plugin/."""
    DELIVERABLES.mkdir(parents=True, exist_ok=True)
    zip_path = DELIVERABLES / f"plg_system_{name}-{version}.zip"
    plugin_dir = PLUGINS_DIR / name
    if not plugin_dir.exists():
        sys.exit(f"ERROR: integration plugin directory not found: {plugin_dir}")
    print(f"  → Building {zip_path.name} ...")
    with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as zf:
        add_dir_to_zip(zf, plugin_dir, manifest_version=version)
    size_kb = zip_path.stat().st_size // 1024
    print(f"    ✓ {zip_path.name} ({size_kb} KB)")
    return zip_path


def main() -> None:
    parser = argparse.ArgumentParser(description="Build AI Boost package ZIP")
    parser.add_argument("--version", help="Override version (default: read from Version.php)")
    parser.add_argument("--dry-run", action="store_true", help="Show what would be built without writing")
    parser.add_argument(
        "--no-codegen-check",
        action="store_true",
        help="Skip the manifest codegen --check guard (use only when debugging the codegen script itself).",
    )
    parser.add_argument(
        "--addons",
        action="store_true",
        help="Also build add-on bridge plugin ZIPs (pkg_aiboost_yootheme, pkg_aiboost_falang)",
    )
    parser.add_argument(
        "--target",
        default="free",
        help="Build target: 'free' (default, current open-source package), "
             "'pro' (Pro upgrade plugins — placeholder until extraction tasks ship), "
             "'integration:<name>' (single integration plugin ZIP), "
             "'all' (free + pro + every registered integration).",
    )
    parser.add_argument(
        "--integration",
        action="append",
        default=[],
        help="Build a single integration plugin ZIP by short name "
             "(e.g. --integration=falang). Repeatable. Equivalent to --target=integration:<name>.",
    )
    args = parser.parse_args()

    version = args.version or read_version()

    # Normalize --target=integration:<name> into --integration=<name>
    if isinstance(args.target, str) and args.target.startswith("integration:"):
        args.integration.append(args.target.split(":", 1)[1])
        args.target = "free"  # don't also run the free build
        # Integration-only short-circuit happens below.
        target_choice = "integration"
    else:
        target_choice = args.target
        if target_choice not in ("free", "pro", "all"):
            sys.exit(f"ERROR: --target must be one of free|pro|integration:<name>|all, got '{target_choice}'")

    # Integration-only build short-circuits the package build entirely.
    if args.integration:
        print(f"\n  AI Boost — Integration build v{version}\n")
        for short in args.integration:
            full = short if short.startswith("aiboost_int_") else f"aiboost_int_{short}"
            if full not in INTEGRATION_PLUGIN_NAMES:
                sys.exit(f"ERROR: unknown integration '{short}'. Known: {INTEGRATION_PLUGIN_NAMES}")
            build_integration_zip(full, version)
        return

    # Task #469 — manifest codegen guard. Runs in --check (read-only)
    # mode before any ZIP is built; any missing Vue partial, Health
    # override stub, feature stub, INI key, or complex-field coverage
    # gap aborts the build. Disable with --no-codegen-check only when
    # debugging the script itself.
    if not args.dry_run and not getattr(args, "no_codegen_check", False):
        import subprocess
        print("\n  Running manifest codegen guard (--check) ...")
        result = subprocess.run(
            [sys.executable, str(Path(__file__).parent / "codegen-from-manifest.py"), "--check"],
            check=False,
        )
        if result.returncode != 0:
            sys.exit(
                "\n  ❌ Manifest codegen check FAILED.\n"
                "     Run `python3 scripts/codegen-from-manifest.py` to generate\n"
                "     the missing artifacts, then re-run the build."
            )
        print("  ✓ Manifest codegen guard passed.")

    build_package_zip(version, dry_run=args.dry_run)

    if target_choice in ("pro", "all"):
        if not PRO_PLUGIN_NAMES:
            print("  ℹ️  No Pro plugins registered yet — skipping --target=pro stage.")
        else:
            build_pro_package_zip(version, dry_run=args.dry_run)

    if target_choice == "all":
        for name in INTEGRATION_PLUGIN_NAMES:
            build_integration_zip(name, version)

    if args.addons:
        build_addons(dry_run=args.dry_run)

    # Always verify the just-built Free package for Pro logic leakage.
    # Task #462 — STRICT mode: any leaked Pro token aborts the build with
    # exit code 1 so CI catches regressions. There is no `--no-verify`
    # escape hatch on purpose; if the verifier needs a false-positive
    # exemption, update ALLOW_FILES in verify-no-pro-leakage.py instead.
    if target_choice in ("free", "all") and not args.dry_run:
        import subprocess
        output_path = DELIVERABLES / f"pkg_aiboost-{version}.zip"
        if output_path.exists():
            print("\n  Running Pro-leakage verifier (STRICT) ...")
            result = subprocess.run(
                [sys.executable, str(Path(__file__).parent / "verify-no-pro-leakage.py"), str(output_path)],
                check=False,
            )
            if result.returncode != 0:
                sys.exit(
                    "\n  ❌ Pro-leakage check FAILED — Free ZIP contains Pro tokens.\n"
                    "     Review output above and either wrap the offending block with\n"
                    "     // @pro:start ... // @pro:end markers, or move the file into a\n"
                    "     Pro-only plugin directory."
                )
            print("  ✓ Pro-leakage check passed.")


if __name__ == "__main__":
    main()
