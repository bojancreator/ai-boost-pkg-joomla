#!/usr/bin/env python3
"""
AI Boost — Pro-leakage Verifier

Unzips a built FREE package and scans every PHP file for tokens that should
only live inside Pro upgrade plugins (`aiboost_*_pro`). Today this script is
ADVISORY (warn but exit 0) because physical Pro extraction is still drift
work from Task #429. Once extraction lands, flip ADVISORY_MODE to False so
build-package-zip.py fails fast on regressions.

Usage:
    python3 scripts/verify-no-pro-leakage.py deliverables/plugin/pkg_aiboost-0.41.0.zip
"""

import re
import sys
import tempfile
import zipfile
from pathlib import Path

# Force UTF-8 stdout/stderr so the ✓/⚠ in the output don't raise
# UnicodeEncodeError on a default Windows console (cp1252) — a crash on the
# success print would otherwise be misread by the build as a Pro-leak failure.
for _stream in (sys.stdout, sys.stderr):
    try:
        _stream.reconfigure(encoding="utf-8")  # type: ignore[attr-defined]
    except (AttributeError, ValueError):
        pass

ADVISORY_MODE = False  # STRICT mode (Task #462) — build fails on any Pro token in free ZIP

# Tokens that, if present in the free package, indicate Pro logic leaked in.
# We allow these inside Pro plugin source — but Pro source must never be in
# the free package, so finding them is itself a signal.
PRO_TOKENS = [
    r"@pro\b",
    r"ProGate::",
    r"use\s+AiBoost\\Lib\\ProGate\s*;",
    r"isProEnabled\s*\(",
    r"licenseStatus\s*\(\s*'\s*\w+\s*'\s*\)\s*===\s*'active'",  # rough
]

PRO_TOKEN_RE = re.compile("|".join(PRO_TOKENS))

# Files we deliberately allow even in the free package because they implement
# the gating mechanism itself (the gate must live somewhere). Update this list
# as the architecture evolves.
ALLOW_FILES = {
    "admin/lib/src/ProGate.php",
    "admin/lib/src/PluginRegistry.php",
    "admin/lib/src/HealthCheckService.php",
    "admin/lib/src/LicenseValidator.php",
    "admin/lib/src/Manifest/Registry.php",
}


def scan_zip(zip_path: Path) -> tuple[int, list[tuple[str, str, int]]]:
    """Return (files_scanned, findings) where findings is [(member, line_text, lineno)]."""
    findings: list[tuple[str, str, int]] = []
    files_scanned = 0

    with tempfile.TemporaryDirectory(prefix="ab_verify_") as tmp:
        tmp_dir = Path(tmp)
        with zipfile.ZipFile(zip_path, "r") as outer:
            outer.extractall(tmp_dir)

        # Iterate every PHP file inside the outer package AND inside each
        # bundled sub-ZIP (packages/*.zip).
        for php_path in tmp_dir.rglob("*.php"):
            files_scanned += 1
            text = php_path.read_text(encoding="utf-8", errors="replace")
            for i, line in enumerate(text.splitlines(), 1):
                if PRO_TOKEN_RE.search(line):
                    member = str(php_path.relative_to(tmp_dir))
                    findings.append((member, line.strip(), i))

        for sub in (tmp_dir / "packages").glob("*.zip") if (tmp_dir / "packages").exists() else []:
            with tempfile.TemporaryDirectory(prefix="ab_sub_") as sub_tmp:
                sub_dir = Path(sub_tmp)
                with zipfile.ZipFile(sub, "r") as inner:
                    inner.extractall(sub_dir)
                for php_path in sub_dir.rglob("*.php"):
                    files_scanned += 1
                    text = php_path.read_text(encoding="utf-8", errors="replace")
                    for i, line in enumerate(text.splitlines(), 1):
                        if PRO_TOKEN_RE.search(line):
                            rel = php_path.relative_to(sub_dir).as_posix()
                            if rel in ALLOW_FILES:
                                continue
                            member = f"{sub.name}::{rel}"
                            findings.append((member, line.strip(), i))

    return files_scanned, findings


def main() -> int:
    if len(sys.argv) < 2:
        print("Usage: verify-no-pro-leakage.py <pkg_aiboost-*.zip>", file=sys.stderr)
        return 2

    zip_path = Path(sys.argv[1])
    if not zip_path.exists():
        print(f"ERROR: {zip_path} not found", file=sys.stderr)
        return 2

    print(f"Scanning {zip_path.name} for Pro logic leakage ...")
    scanned, findings = scan_zip(zip_path)
    print(f"  PHP files scanned: {scanned}")

    if not findings:
        print("  ✓ No Pro tokens found in the free package.")
        return 0

    print(f"  ⚠ {len(findings)} potential Pro token(s) found:")
    for member, line, lineno in findings[:50]:
        print(f"    {member}:{lineno}  {line[:120]}")
    if len(findings) > 50:
        print(f"    ... and {len(findings) - 50} more.")

    if ADVISORY_MODE:
        print(
            "  ℹ ADVISORY_MODE — exit 0 even with findings. Physical Pro"
            " extraction is drift from #429; flip ADVISORY_MODE = False"
            " in this script once extraction lands."
        )
        return 0
    return 1


if __name__ == "__main__":
    sys.exit(main())
