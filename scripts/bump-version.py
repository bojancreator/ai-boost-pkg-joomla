#!/usr/bin/env python3
"""
Version bump helper for AI Boost for Joomla package.

Usage:
    python3 scripts/bump-version.py patch          # 0.25.0 → 0.25.1
    python3 scripts/bump-version.py minor          # 0.25.0 → 0.26.0
    python3 scripts/bump-version.py major          # 0.25.0 → 1.0.0
    python3 scripts/bump-version.py patch --build  # bump + build ZIP

Source of truth: component/Version.php
"""

import argparse
import re
import subprocess
import sys
from datetime import date
from pathlib import Path

WORKSPACE_ROOT = Path(__file__).parent.parent
VERSION_FILE = WORKSPACE_ROOT / "component" / "Version.php"
BUILD_SCRIPT = WORKSPACE_ROOT / "scripts" / "build-package-zip.py"

VERSION_RE = re.compile(
    r"(public\s+const\s+VERSION\s*=\s*')([\d]+\.[\d]+\.[\d]+(?:-[\w.]+)?)(';)"
)
MAJOR_RE = re.compile(r"(public\s+const\s+MAJOR\s*=\s*)(\d+)(\s*;)")
MINOR_RE = re.compile(r"(public\s+const\s+MINOR\s*=\s*)(\d+)(\s*;)")
PATCH_RE = re.compile(r"(public\s+const\s+PATCH\s*=\s*)(\d+)(\s*;)")
RELEASE_DATE_RE = re.compile(
    r"(public\s+const\s+RELEASE_DATE\s*=\s*')(\d{4}-\d{2}-\d{2})(';)"
)


def read_version() -> tuple[str, str]:
    content = VERSION_FILE.read_text(encoding="utf-8")
    match = VERSION_RE.search(content)
    if not match:
        print(
            "ERROR: Could not find VERSION constant in component/Version.php",
            file=sys.stderr,
        )
        sys.exit(1)
    return content, match.group(2)


def bump(version: str, bump_type: str) -> str:
    numeric = version.split("-")[0]
    parts = numeric.split(".")
    if len(parts) != 3:
        print(
            f"ERROR: Unexpected version format '{version}' — expected MAJOR.MINOR.PATCH[-suffix]",
            file=sys.stderr,
        )
        sys.exit(1)
    major, minor, patch = int(parts[0]), int(parts[1]), int(parts[2])
    if bump_type == "major":
        major += 1
        minor = 0
        patch = 0
    elif bump_type == "minor":
        minor += 1
        patch = 0
    elif bump_type == "patch":
        patch += 1
    else:
        print(
            f"ERROR: Unknown bump type '{bump_type}'. Use patch, minor, or major.",
            file=sys.stderr,
        )
        sys.exit(1)
    return f"{major}.{minor}.{patch}"


def write_version(content: str, new_version: str) -> None:
    major, minor, patch = new_version.split("-")[0].split(".")
    today = date.today().isoformat()

    for regex, name in (
        (VERSION_RE, "VERSION"),
        (MAJOR_RE, "MAJOR"),
        (MINOR_RE, "MINOR"),
        (PATCH_RE, "PATCH"),
        (RELEASE_DATE_RE, "RELEASE_DATE"),
    ):
        if not regex.search(content):
            print(
                f"ERROR: Could not find {name} constant in component/Version.php",
                file=sys.stderr,
            )
            sys.exit(1)

    content = VERSION_RE.sub(rf"\g<1>{new_version}\g<3>", content)
    content = MAJOR_RE.sub(rf"\g<1>{major}\g<3>", content)
    content = MINOR_RE.sub(rf"\g<1>{minor}\g<3>", content)
    content = PATCH_RE.sub(rf"\g<1>{patch}\g<3>", content)
    content = RELEASE_DATE_RE.sub(rf"\g<1>{today}\g<3>", content)

    VERSION_FILE.write_text(content, encoding="utf-8")


def run_build() -> None:
    result = subprocess.run(
        [sys.executable, str(BUILD_SCRIPT)],
        check=False,
    )
    if result.returncode != 0:
        print(
            "ERROR: Build failed — version was already bumped in component/Version.php.",
            file=sys.stderr,
        )
        sys.exit(result.returncode)


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Bump the AI Boost for Joomla package version."
    )
    parser.add_argument(
        "bump_type",
        choices=["patch", "minor", "major"],
        help="Which part of the version to increment.",
    )
    parser.add_argument(
        "--build",
        action="store_true",
        help="Also build the package ZIP after bumping.",
    )
    args = parser.parse_args()

    if not VERSION_FILE.exists():
        print(f"ERROR: Version file not found: {VERSION_FILE}", file=sys.stderr)
        sys.exit(1)

    content, current = read_version()
    new_version = bump(current, args.bump_type)

    write_version(content, new_version)
    print(f"Version bumped: {current} → {new_version}")

    if args.build:
        print()
        run_build()


if __name__ == "__main__":
    main()
