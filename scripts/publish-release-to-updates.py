#!/usr/bin/env python3
"""
publish-release-to-updates.py — the repeatable release-sync for the live update server.

Closes the "feed drifts stale" gap (order 0035): take the 4 canonical release ZIPs,
publish them to updates.aiboostnow.com's releases dir with the EXACT filenames, and
verify the feeds + gated downloads reflect them with a matching sha256 — so the
advertised version, the streamable ZIP, and the checksum stay in lockstep.

WHAT IT DOES
  1. Resolves the version (default: component/Version.php).
  2. (--build) optionally runs build-package-zip.py --target all first.
  3. Locates the 4 required ZIPs in deliverables/plugin and prints sha256.
  4. Uploads each atomically over FTPS (STOR <name>.part -> verify size -> rename),
     never deleting older releases (the server picks the highest version; old files
     stay for rollback).
  5. Verifies every feed advertises the version, the feed <sha256> == the built ZIP,
     and a GET download streams the exact bytes (gated feeds use the QA keys).

It NEVER deletes anything on the server and NEVER touches the licensing API.

REQUIRED ENV (load via the wrapper-root _creds_run.py, which reads CREDENTIALS.local.md):
  AIBOOST_FTP_USER, AIBOOST_FTP_PASS         — FTPS account (rooted at public_html)
  AIBOOST_QA_KEY_CORE / _INT_FALANG / _INT_YOOTHEME  — to verify the gated downloads
Optional:
  AIBOOST_FTP_HOST (default tries the hostname then the IP), AIBOOST_FTP_IP

USAGE (from the wrapper root):
  python _creds_run.py scripts/publish-release-to-updates.py            # publish current Version.php
  python _creds_run.py scripts/publish-release-to-updates.py --build    # build first, then publish
  python _creds_run.py scripts/publish-release-to-updates.py --version 0.88.1 --verify-only
"""
import argparse
import hashlib
import io
import os
import re
import socket
import ssl
import subprocess
import sys
import urllib.request
import urllib.error
from ftplib import FTP_TLS
from pathlib import Path

REPO = Path(__file__).resolve().parent.parent          # aiboost-joomla/
DELIVERABLES = REPO / "deliverables" / "plugin"
VERSION_PHP = REPO / "component" / "Version.php"
REMOTE_DIR = "aiboostnow/updates/storage/releases"
FTP_IP_FALLBACK = "109.199.99.205"                     # hostname ftp.lazar.enet.rs may not resolve everywhere
BASE_FEED = "https://updates.aiboostnow.com"

# feed key -> (zip prefix, feed XML path, download path, QA-key env var or None for Free)
FEEDS = {
    "free":      ("pkg_aiboost",                       "/pkg_aiboost.xml",                  "/downloads/free",      None),
    "pro":       ("pkg_aiboost_pro",                   "/pro/pkg_aiboost_pro.xml",          "/downloads/pro",       "AIBOOST_QA_KEY_CORE"),
    "multilang": ("plg_system_aiboost_int_falang_pro", "/multilang/pkg_aiboost_falang.xml", "/downloads/multilang", "AIBOOST_QA_KEY_INT_FALANG"),
    "yootheme":  ("plg_system_aiboost_int_yootheme_pro","/yootheme/pkg_aiboost_yootheme.xml","/downloads/yootheme",  "AIBOOST_QA_KEY_INT_YOOTHEME"),
}


def read_version() -> str:
    m = re.search(r"VERSION\s*=\s*'([0-9]+\.[0-9]+\.[0-9]+)'", VERSION_PHP.read_text(encoding="utf-8"))
    if not m:
        sys.exit("ERROR: could not read VERSION from component/Version.php")
    return m.group(1)


def sha256_file(p: Path) -> str:
    return hashlib.sha256(p.read_bytes()).hexdigest()


def local_zip(prefix: str, version: str) -> Path:
    return DELIVERABLES / f"{prefix}-{version}.zip"


def ensure_zips(version: str, build: bool) -> dict:
    if build:
        print(f"[*] building release ZIPs for {version} (build-package-zip.py --target all) ...")
        r = subprocess.run([sys.executable, str(REPO / "scripts" / "build-package-zip.py"), "--target", "all"],
                           cwd=str(REPO), env={**os.environ, "PYTHONIOENCODING": "utf-8"})
        if r.returncode != 0:
            sys.exit("ERROR: build failed — aborting publish.")
    zips = {}
    missing = []
    for key, (prefix, *_rest) in FEEDS.items():
        p = local_zip(prefix, version)
        if p.is_file():
            zips[key] = p
        else:
            missing.append(p.name)
    if missing:
        sys.exit("ERROR: missing release ZIPs (run with --build):\n  " + "\n  ".join(missing))
    return zips


def connect_ftps() -> FTP_TLS:
    host = os.environ.get("AIBOOST_FTP_HOST", "").strip()
    candidates = []
    if host:
        try:
            socket.gethostbyname(host)
            candidates.append(host)
        except OSError:
            pass
    candidates.append(os.environ.get("AIBOOST_FTP_IP", FTP_IP_FALLBACK))
    user = os.environ.get("AIBOOST_FTP_USER", "")
    pw = os.environ.get("AIBOOST_FTP_PASS", "")
    if not user or not pw:
        sys.exit("ERROR: AIBOOST_FTP_USER / AIBOOST_FTP_PASS not set (run via _creds_run.py).")
    last = None
    for h in candidates:
        try:
            ftps = FTP_TLS()
            ftps.connect(h, 21, timeout=120)
            ftps.login(user, pw)
            ftps.prot_p()
            print(f"[*] FTPS connected via {h} (PWD={ftps.pwd()})")
            return ftps
        except Exception as e:  # noqa: BLE001
            last = e
    sys.exit(f"ERROR: could not connect FTPS to any of {candidates}: {last}")


def upload(zips: dict, version: str) -> None:
    ftps = connect_ftps()
    ftps.cwd(REMOTE_DIR)
    print(f"[*] cwd -> {ftps.pwd()}")
    for key, (prefix, *_rest) in FEEDS.items():
        local = zips[key]
        name = local.name
        part = name + ".part"
        lsize = local.stat().st_size
        print(f"[+] {name}  ({lsize} B, sha256={sha256_file(local)[:16]}…)")
        try:
            ftps.delete(part)
        except Exception:
            pass
        with open(local, "rb") as fh:
            ftps.storbinary(f"STOR {part}", fh, blocksize=65536)
        if ftps.size(part) != lsize:
            sys.exit(f"ERROR: size mismatch for {part}; final NOT renamed.")
        try:
            if ftps.size(name) is not None:
                ftps.delete(name)
        except Exception:
            pass
        ftps.rename(part, name)
        print(f"    published -> {name} (remote {ftps.size(name)} B)")
    ftps.quit()
    print("[*] upload done (older releases left in place for rollback).")


def _http_get(url: str) -> tuple:
    ctx = ssl.create_default_context()
    ctx.check_hostname = False
    ctx.verify_mode = ssl.CERT_NONE
    with urllib.request.urlopen(url, timeout=90, context=ctx) as r:
        return r.status, r.read()


def verify(zips: dict, version: str) -> bool:
    ok = True
    for key, (prefix, feed_path, dl_path, key_env) in FEEDS.items():
        local_sha = sha256_file(zips[key])
        st, body = _http_get(BASE_FEED + feed_path)
        xml = body.decode("utf-8", "replace")
        ver = (re.search(r"<version>([^<]+)</version>", xml) or [None, "?"])[1]
        feed_sha = (re.search(r"<sha256>([0-9a-f]+)</sha256>", xml) or [None, ""])[1]
        dl = f"{BASE_FEED}{dl_path}?v={version}"
        if key_env:
            dl += f"&dlid={os.environ.get(key_env, '')}"
        try:
            st2, zb = _http_get(dl)
            dl_sha = hashlib.sha256(zb).hexdigest()
        except urllib.error.HTTPError as e:
            st2, dl_sha = e.code, ""
        row_ok = (ver == version and feed_sha == local_sha and st2 == 200 and dl_sha == local_sha)
        ok = ok and row_ok
        print(f"  [{ 'PASS' if row_ok else 'FAIL' }] {key:9} feed_ver={ver} feed_sha_ok={feed_sha==local_sha} dl_status={st2} dl_sha_ok={dl_sha==local_sha}")
    print("=> " + ("ALL FEEDS VERIFIED — version + checksum in lockstep." if ok else "VERIFICATION FAILED."))
    return ok


def main() -> int:
    ap = argparse.ArgumentParser(description="Publish + verify the release ZIPs on updates.aiboostnow.com")
    ap.add_argument("--version", default=None, help="version to publish (default: component/Version.php)")
    ap.add_argument("--build", action="store_true", help="run build-package-zip.py --target all first")
    ap.add_argument("--verify-only", action="store_true", help="skip upload; only verify the live feeds")
    args = ap.parse_args()
    version = args.version or read_version()
    print(f"=== release-sync for AI Boost {version} ===")
    zips = ensure_zips(version, args.build)
    for key, p in zips.items():
        print(f"    {key:9} {p.name}  sha256={sha256_file(p)}")
    if not args.verify_only:
        upload(zips, version)
    print("[*] verifying live feeds ...")
    return 0 if verify(zips, version) else 1


if __name__ == "__main__":
    raise SystemExit(main())
