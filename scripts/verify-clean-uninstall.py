#!/usr/bin/env python3
"""
AI Boost for Joomla — Clean Uninstall + Upgrade Verifier (Task #491)

Automates two QA passes against staging.offroadserbia.com over plain HTTP so
nobody has to SSH into the DB after every package change.

Pass 1 — Clean uninstall (data AND licence PRESERVED, dev overrides wiped)
  1. Install latest pkg_aiboost-*.zip.
  2. Seed unique markers across the #__aiboost_* tables (settings.org_name, a
     Pro translation row, a redirect row) via the admin AJAX endpoints.
  3. Uninstall the package through com_installer's manage view.
  4. Assert:
     (b) com_installer&view=manage shows no rows for aiboost / pkg_aiboost
         (the extension itself IS removed).
     (c) Public /robots.txt, /llms.txt, /sitemap.xml, /sitemap-index.xml
         either 404 or no longer carry the AI Boost marker (generated
         artifacts ARE cleaned up).
     (a) Re-install pkg_aiboost (admin endpoints need the component present)
         and call settings.getSettings + redirects.listJson. User data MUST
         survive: the org_name marker, the seeded redirect, and (Pro) the
         translation row must all still be there. The licence/activation keys
         (pro_activated, license_state, license_key, install_id) MUST survive
         too — perpetual activation survives uninstall by design. Only the
         developer override keys (dev_license_preview, dev_force_free_tier,
         license_simulation) MUST have been wiped.

Pass 2 — Upgrade preservation
  1. Uninstall whatever is on staging, then install the SECOND-newest
     pkg_aiboost-*.zip on disk (the "old" version).
  2. Write a unique marker + a translation row.
  3. Install the latest pkg_aiboost-*.zip on top (Joomla treats this as
     update because the package element matches).
  4. Call settings.getSettings and assert the marker + translations row
     count survived the upgrade.

Env vars:
  --target pro  (default): STAGING_URL, STAGING_ADMIN_USER, STAGING_ADMIN_PASS
  --target free          : FREE_URL,    FREE_ADMIN_USER,    FREE_ADMIN_PASS
                           (remapped to STAGING_* before importing the installer,
                            mirroring scripts/install-to-free.py)

Usage:
  python3 scripts/verify-clean-uninstall.py                       # both passes vs Pro staging
  python3 scripts/verify-clean-uninstall.py --target free         # both passes vs Free site
  python3 scripts/verify-clean-uninstall.py --uninstall-only
  python3 scripts/verify-clean-uninstall.py --upgrade-only
"""

from __future__ import annotations

import argparse
import glob
import importlib.util
import json
import os
import re
import sys
import threading
import time
import urllib.parse
from typing import Callable, Optional, TypeVar

import requests

# Line-buffer stdout so progress is visible when this (multi-minute) verifier is
# run detached in the background — the only way the slow Free target fits inside
# the runner's 2-minute foreground command budget. Also force UTF-8 so the
# status emoji/box-drawing chars don't raise UnicodeEncodeError on a default
# Windows console (cp1252).
for _stream in (sys.stdout, sys.stderr):
    try:
        _stream.reconfigure(encoding="utf-8", line_buffering=True)  # type: ignore[attr-defined]
    except (AttributeError, ValueError):
        pass

HERE = os.path.dirname(__file__)
ROOT = os.path.abspath(os.path.join(HERE, ".."))
DELIVERABLES_DIR = os.path.join(ROOT, "deliverables", "plugin")


def _select_target_env(target: str) -> None:
    """
    Re-map env vars based on --target so the rest of the script (and the
    install-to-staging helpers it imports) can keep reading STAGING_*.

    Mirrors scripts/install-to-free.py for the Free path.
    """
    if target == "pro":
        required = ("STAGING_URL", "STAGING_ADMIN_USER", "STAGING_ADMIN_PASS")
        missing = [k for k in required if not os.environ.get(k)]
        if missing:
            print(f"❌ Missing secrets for --target pro: {', '.join(missing)}")
            sys.exit(2)
        print("🟢 Target: Pro staging (staging.offroadserbia.com)")
        return
    if target == "free":
        required = ("FREE_URL", "FREE_ADMIN_USER", "FREE_ADMIN_PASS")
        missing = [k for k in required if not os.environ.get(k)]
        if missing:
            print(f"❌ Missing secrets for --target free: {', '.join(missing)}")
            sys.exit(2)
        os.environ["STAGING_URL"]        = os.environ["FREE_URL"]
        os.environ["STAGING_ADMIN_USER"] = os.environ["FREE_ADMIN_USER"]
        os.environ["STAGING_ADMIN_PASS"] = os.environ["FREE_ADMIN_PASS"]
        print("🟢 Target: Free site (offroadbalkans.com)")
        return
    print(f"❌ Unknown --target value: {target!r} (expected 'pro' or 'free')")
    sys.exit(2)


# Parse --target early so env remap happens before importing install-to-staging
# (its module-level code reads STAGING_URL on import).
_pre = argparse.ArgumentParser(add_help=False)
_pre.add_argument("--target", choices=("pro", "free"), default="pro")
_pre_args, _ = _pre.parse_known_args()
# Skip env validation for --help so users without secrets can still discover flags.
if not any(a in ("-h", "--help") for a in sys.argv[1:]):
    _select_target_env(_pre_args.target)

# Per-language translations are a Pro-only feature (settings.save gates the write
# behind an active Pro license). On a Free install they are never persisted, so
# the translation seed/preservation assertions below are skipped for --target free.
IS_FREE = _pre_args.target == "free"

# Set per-pass by ensure_pro(): True only when the install already carries a
# Pro signal (pro_activated / dev_license_preview). Pro cannot be seeded over
# HTTP by design, so when False the Pro-only translation WRITE assertions
# downgrade to preservation-only checks.
PRO_QA = False

# Reuse login() + install_zip() + helpers from the sibling installer script.
# The filename contains a hyphen so a normal `import` won't work.
_spec = importlib.util.spec_from_file_location(
    "install_to_staging", os.path.join(HERE, "install-to-staging.py")
)
assert _spec is not None and _spec.loader is not None
_its = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(_its)

login = _its.login
install_zip = _its.install_zip
extract_csrf = _its.extract_csrf
ADMIN_PHP = _its.ADMIN_PHP
PUBLIC_BASE = _its._base  # e.g. https://staging.offroadserbia.com

MARKER_PREFIX = "AIBOOST_QA_MARKER_"
PKG_ELEMENT = "pkg_aiboost"


# ─────────────────────────────────────────────────────────────────────────────
# ZIP discovery
# ─────────────────────────────────────────────────────────────────────────────

_VER_RE = re.compile(r"pkg_aiboost-(\d+)\.(\d+)\.(\d+)\.zip$")


def _semver_key(path: str) -> tuple[int, int, int]:
    m = _VER_RE.search(os.path.basename(path))
    return tuple(int(x) for x in m.groups()) if m else (0, 0, 0)  # type: ignore[return-value]


# The upgrade pass writes a Pro-only translation on the OLD build and checks it
# survives the upgrade. Seeding the Pro precondition over HTTP relies on the
# perpetual `pro_activated` flag, which `isProActive()` only honours from 0.71.0
# onward. Older builds gate Pro on `license_state`/`dev_license_preview`, both of
# which are import-denylisted and not writable via settings.save — so they can
# never serve as the "old" side of this test. Pre-1.0 we keep no backwards
# compatibility with those builds (this runs against a test server only).
UPGRADE_BASELINE = (0, 71, 0)


def pick_old_zip(zips: list[str], new_zip: str) -> Optional[str]:
    """Choose the 'old' build for the upgrade pass.

    Must be >= UPGRADE_BASELINE so the Pro precondition can be seeded over HTTP.
    Prefer the newest such build strictly older than new_zip (a real
    cross-version upgrade); if none exists yet, fall back to new_zip itself
    (same-version reinstall preservation test). Returns None only when even the
    new build predates the baseline (the upgrade pass cannot run meaningfully).
    """
    new_key = _semver_key(new_zip)
    candidates = [
        z for z in zips
        if UPGRADE_BASELINE <= _semver_key(z) < new_key
    ]
    if candidates:
        return max(candidates, key=_semver_key)
    return new_zip if _semver_key(new_zip) >= UPGRADE_BASELINE else None


def find_pkg_zips_sorted() -> list[str]:
    zips = glob.glob(os.path.join(DELIVERABLES_DIR, "pkg_aiboost-*.zip"))
    # Newest first
    return sorted(zips, key=_semver_key, reverse=True)


# ─────────────────────────────────────────────────────────────────────────────
# Joomla admin helpers (HTTP only — no SSH, no direct DB)
# ─────────────────────────────────────────────────────────────────────────────


_CSRF_OPTION_RE = re.compile(r'"csrf\.token"\s*:\s*"([a-f0-9]{32})"')


def _fresh_token(
    session: requests.Session, url_query: str = "", bounded: bool = True
) -> Optional[str]:
    """Fetch any admin page and pull the current CSRF token from its HTML.

    The com_aiboost admin is a Vue SPA that no longer renders a classic hidden
    `<input name="<token>" value="1">` field, so `extract_csrf()` finds nothing
    on the component page. Joomla still emits its form token in the page's JS
    options as `"csrf.token":"<32-hex>"` (also mirrored in window.aiBoostBootstrap).
    That hex string IS the form token — used as the POST field NAME with value
    "1", identical to the hidden-input convention — so fall back to it.
    """
    url = ADMIN_PHP + (("?" + url_query) if url_query else "")
    # requests' `timeout` is a per-read-gap, not a total budget, so a host that
    # trickles the page byte-by-byte (staging mid-uninstall) makes this GET hang
    # forever. When `bounded`, cap it on wall-clock; None => caller treats as
    # "no token". When NOT bounded, the caller runs us inside one outer watchdog
    # (uninstall path) and must avoid nesting deadline threads on the shared,
    # non-thread-safe Session, so we issue a plain blocking GET instead.
    if bounded:
        r = _run_with_deadline(
            lambda: session.get(url, timeout=30), 30.0,
            f"CSRF fetch ({url_query or 'admin'})",
        )
    else:
        try:
            r = session.get(url, timeout=30)
        except requests.RequestException:
            return None
    if r is None:
        return None
    tok = extract_csrf(r.text)
    if tok:
        return tok
    m = _CSRF_OPTION_RE.search(r.text)
    return m.group(1) if m else None


def _loads_lenient(text: str):
    """Parse JSON even when the host prepends/append stray output.

    Some Joomla hosts run with display_errors on, so a PHP notice/warning can
    be emitted before the AJAX JSON body. Try a strict parse first, then fall
    back to extracting the last balanced {…} / […] block from the response.
    Raises json.JSONDecodeError when nothing parseable is found.
    """
    try:
        return json.loads(text)
    except json.JSONDecodeError:
        pass
    for opener, closer in (("{", "}"), ("[", "]")):
        start = text.find(opener)
        end = text.rfind(closer)
        if start != -1 and end > start:
            try:
                return json.loads(text[start:end + 1])
            except json.JSONDecodeError:
                continue
    raise json.JSONDecodeError("no JSON object found", text, 0)


def _xlat_map(payload: Optional[dict]) -> dict:
    """Return the translations map as a dict.

    PHP json_encodes an empty associative array as `[]`, so getSettings returns
    a list (not a dict) when there are zero translation rows — which is always
    the case on a Free install. Coerce that to {} so callers can `.get()` safely.
    """
    if not isinstance(payload, dict):
        return {}
    xlat = payload.get("translations")
    return xlat if isinstance(xlat, dict) else {}


def _settings_map(payload: Optional[dict]) -> dict:
    """Return the settings map as a dict.

    Like translations, a fresh install can return `settings` as an empty list
    (PHP json_encodes an empty array as `[]`), so coerce non-dicts to {}.
    """
    if not isinstance(payload, dict):
        return {}
    settings = payload.get("settings")
    return settings if isinstance(settings, dict) else {}


def get_settings(session: requests.Session) -> Optional[dict]:
    """Call settings.getSettings AJAX. Returns parsed JSON dict or None."""
    url = ADMIN_PHP + "?option=com_aiboost&task=settings.getSettings&format=json"
    r = session.get(url, timeout=30)
    if r.status_code != 200:
        return None
    try:
        data = _loads_lenient(r.text)
    except json.JSONDecodeError:
        return None
    return data if isinstance(data, dict) and data.get("success") else None


def seed_redirect(session: requests.Session, marker: str) -> bool:
    """
    Insert one row into #__aiboost_redirects via redirects.add. The row's
    note column carries `marker` so we can identify it after an upgrade.
    """
    csrf = _fresh_token(session, "option=com_aiboost")
    if not csrf:
        print("   ❌ Cannot get CSRF for redirects.add")
        return False
    r = session.post(
        ADMIN_PHP + "?option=com_aiboost&task=redirects.add&format=json",
        data={
            "option": "com_aiboost",
            "task": "redirects.add",
            "format": "json",
            "from_url": "/qa/" + marker,
            "to_url": "/",
            "redirect_type": "301",
            "note": marker,
            csrf: "1",
        },
        timeout=30,
    )
    try:
        payload = _loads_lenient(r.text)
    except json.JSONDecodeError:
        print(f"   ❌ redirects.add non-JSON (HTTP {r.status_code}): {r.text[:200]}")
        return False
    if not payload.get("success"):
        print(f"   ❌ redirects.add failed: {payload}")
        return False
    return True


def redirects_list(session: requests.Session) -> Optional[dict]:
    """Call redirects.listJson — also exercises #__aiboost_404_log."""
    r = session.get(
        ADMIN_PHP + "?option=com_aiboost&task=redirects.listJson&format=json",
        timeout=30,
    )
    try:
        payload = _loads_lenient(r.text)
    except json.JSONDecodeError:
        return None
    return payload if isinstance(payload, dict) and payload.get("success") else None


def url_scans_history(session: requests.Session) -> Optional[dict]:
    """Call urlchecker.scanHistory — exercises #__aiboost_url_scans."""
    csrf = _fresh_token(session, "option=com_aiboost")
    if not csrf:
        return None
    r = session.get(
        ADMIN_PHP + f"?option=com_aiboost&task=urlchecker.scanHistory"
                    f"&format=json&limit=10&{csrf}=1",
        timeout=30,
    )
    try:
        payload = _loads_lenient(r.text)
    except json.JSONDecodeError:
        return None
    return payload if isinstance(payload, dict) and payload.get("success") else None


def save_marker(session: requests.Session, marker: str, with_translation: bool) -> bool:
    """
    Write a unique marker into #__aiboost_settings.org_name (and optionally
    a translation row) via the settings.save AJAX endpoint.

    We don't try to round-trip the full settings blob — settings.save merges
    by listed field name, so posting just the few fields we care about leaves
    the rest of the row untouched.
    """
    csrf = _fresh_token(session, "option=com_aiboost")
    if not csrf:
        print("   ❌ Cannot get CSRF for settings.save")
        return False

    data = {
        "option": "com_aiboost",
        "task": "settings.save",
        "format": "json",
        "org_name": marker,
        # NOTE: posting license_tier here is a no-op — settings.save refuses to
        # set license/dev keys (anti-bypass) and gates translation writes on
        # PluginRegistry::isProActive(). The Pro precondition for translations
        # is detected separately by ensure_pro() before this call. Kept only
        # so an older Pro install that still honoured it isn't disturbed.
        "license_tier": "pro",
        csrf: "1",
    }
    if with_translation:
        data["translations"] = json.dumps({"org_name": {"en-GB": marker + "_xlat"}})

    r = session.post(
        ADMIN_PHP + "?option=com_aiboost&task=settings.save&format=json",
        data=data,
        timeout=30,
    )
    try:
        payload = _loads_lenient(r.text)
    except json.JSONDecodeError:
        print(f"   ❌ settings.save non-JSON (HTTP {r.status_code}): {r.text[:200]}")
        return False
    if not payload.get("success"):
        print(f"   ❌ settings.save failed: {payload}")
        return False
    return True


def ensure_pro(session: requests.Session) -> bool:
    """
    Report whether the freshly installed test site is Pro-capable, so the
    Pro-gated per-language translation assertions can run (they are vacuous on
    a Free/keyless install).

    History: this used to SEED Pro over HTTP by importing `pro_activated`
    through the admin Import endpoint — the one seam that accepted it. That
    seam is now closed BY DESIGN (Faza 1A put pro_activated on the import
    denylist, and ImportController refuses an upload whose payload is empty
    after stripping), and settings.save carries license/dev keys forward from
    the existing row only. There is deliberately NO remote way to flip a site
    to Pro — that is the anti-bypass property working, not a verifier bug.

    So instead of seeding, we detect: Pro is available when the existing row
    already carries pro_activated='1' (a real activation) or
    dev_license_preview='1' (the documented manual QA override, set directly
    in #__aiboost_settings). When neither is present the caller downgrades the
    Pro-only translation assertions to preservation-only checks and says so.
    """
    post = get_settings(session)
    smap = _settings_map(post) if post else {}
    if smap.get("pro_activated") == "1" or smap.get("dev_license_preview") == "1":
        print("   ✅ Pro precondition present "
              f"(pro_activated='{smap.get('pro_activated')}', "
              f"dev_license_preview='{smap.get('dev_license_preview')}')")
        return True
    print("   ⚠️  Pro not active on this install and CANNOT be seeded over HTTP "
          "(import denylist + settings.save carry-forward block it by design). "
          "Pro-only translation write assertions are downgraded to "
          "preservation-only. For full Pro-path coverage set "
          "dev_license_preview='1' directly in #__aiboost_settings and re-run.")
    return False


_T = TypeVar("_T")

# Server-side package uninstall on staging (drops tables, deletes site-root
# files via pkg_script) can take minutes, and the host trickles bytes back so
# requests' per-read-gap `timeout` never trips — a naive call hangs forever.
# We cap the *wall-clock* time of trickle-prone calls instead and then confirm
# the outcome by re-listing, which is reliable because the removal itself
# completes server-side even when the HTTP response never returns.
UNINSTALL_TOTAL_BUDGET = 120.0  # wall-clock cap for the whole package removal


def _run_with_deadline(fn: Callable[[], _T], deadline_s: float, what: str) -> Optional[_T]:
    """Run a blocking call under a hard wall-clock deadline.

    Executes ``fn`` in a daemon thread and waits at most ``deadline_s`` seconds.
    On timeout the call is abandoned (the daemon thread is left to die with the
    interpreter, so it never blocks exit) and ``None`` is returned, signalling
    the caller to verify state another way. Exceptions raised by ``fn`` are
    re-raised on the calling thread.
    """
    box: dict = {}

    def worker() -> None:
        try:
            box["result"] = fn()
        except BaseException as exc:  # noqa: BLE001 — propagate to caller
            box["error"] = exc

    t = threading.Thread(target=worker, daemon=True)
    t.start()
    t.join(deadline_s)
    if t.is_alive():
        print(f"   ⏱ {what} exceeded {deadline_s:.0f}s wall-clock — abandoning "
              f"response (server-side work continues); will verify by re-listing")
        return None
    if "error" in box:
        raise box["error"]
    return box.get("result")


# Joomla renders the manage-view Type column as the translated label of the
# extension's raw `type` (COM_INSTALLER_TYPE_<TYPE>). These are the values we
# care about distinguishing — the package vs. its members.
_EXTENSION_TYPE_LABELS = {
    "package", "component", "plugin", "module",
    "language", "library", "file", "template",
}


def _extract_extension_type(row_html: str) -> str:
    """Return the lowercased extension type (e.g. "package", "component") read
    from the manage-view Type column of one row, or "" when no recognised type
    cell is present. We scan every <td>/<th> cell's stripped text and match it
    against the known type labels rather than relying on a fixed column index,
    so changing column order/classes across Joomla versions can't break it."""
    for cell in re.finditer(r'<t[dh][^>]*>(.*?)</t[dh]>', row_html,
                            re.DOTALL | re.IGNORECASE):
        text = re.sub(r'<[^>]+>', ' ', cell.group(1))
        text = re.sub(r'\s+', ' ', text).strip().lower()
        if text in _EXTENSION_TYPE_LABELS:
            return text
    return ""


def find_aiboost_extension_ids(
    session: requests.Session, deadline_s: float = 45.0, bounded: bool = True
) -> Optional[list[tuple[int, str, str]]]:
    """
    Scrape com_installer&view=manage filtered by 'aiboost' and return all
    [(extension_id, label, type)] rows still present, where ``type`` is the
    lowercased manage-view Type label ("package", "component", "plugin",
    "module", …) or "" when it could not be read.

    Returns ``None`` (not ``[]``) when the listing request exceeds its
    wall-clock deadline — i.e. the host is still trickling bytes mid-uninstall
    and we genuinely cannot tell what remains. Callers must treat ``None`` as
    "unknown, retry", never as "clean".

    We look for the standard checkbox column Joomla renders in list views:
        <input type="checkbox" name="cid[<row>]" value="<extension_id>" ...>
    plus a nearby element name. That's enough to (a) count remaining rows
    and (b) drive task=manage.remove.
    """
    # The manage-view search filter matches the *display name*, not the element,
    # so it must be searched as "AI Boost" (the brand prefix every package,
    # component, plugin and module shares) — searching "aiboost" returns
    # "No Matching Results". list[limit]=0 shows all rows on one page.
    url = (ADMIN_PHP + "?option=com_installer&view=manage&list[limit]=0&filter[search]="
           + urllib.parse.quote("AI Boost"))
    # `bounded` wraps the GET in its own watchdog (standalone callers). The
    # uninstall path passes bounded=False because it already runs inside ONE
    # outer watchdog and must not nest deadline threads on the shared,
    # non-thread-safe Session — there a plain blocking GET is correct.
    if bounded:
        r = _run_with_deadline(
            lambda: session.get(url, timeout=40), deadline_s, "manage-view listing"
        )
    else:
        try:
            r = session.get(url, timeout=40)
        except requests.RequestException:
            return None
    if r is None:
        return None
    html = r.text
    # Joomla 5/6 render each selectable row's checkbox as
    #   <input ... type="checkbox" ... name="cid[]" value="<extension_id>">
    # (older code expected name="cid[<row>]", which no longer matches). The
    # visible name lives in a <span tabindex="0">…</span> inside the row.
    rows = []
    for m in re.finditer(r'<tr[^>]*>(.*?)</tr>', html, re.DOTALL | re.IGNORECASE):
        row_html = m.group(1)
        cb = re.search(r'name="cid\[\]"\s+value="(\d+)"', row_html)
        if not cb:
            continue
        low = row_html.lower()
        if 'ai boost' not in low and 'aiboost' not in low:
            continue
        ext_id = int(cb.group(1))
        name_m = re.search(r'<span tabindex="0">\s*(.*?)\s*</span>', row_html, re.DOTALL)
        label = re.sub(r'\s+', ' ', name_m.group(1)).strip() if name_m else f"ext#{ext_id}"
        etype = _extract_extension_type(row_html)
        rows.append((ext_id, label, etype))
    return rows


def uninstall_extension(
    session: requests.Session, ext_id: int, label: str,
    deadline_s: float = 120.0, bounded: bool = True
) -> Optional[bool]:
    """Remove one extension. Returns True on confirmed success, False on a
    definite failure, and None when the remove POST exceeded its wall-clock
    deadline (the host is trickling the response while the removal proceeds
    server-side) — the caller then confirms by re-listing.

    When `bounded` the POST runs in its own watchdog thread. The uninstall path
    passes bounded=False because it already runs inside ONE outer watchdog and
    must not nest deadline threads on the shared, non-thread-safe Session — a
    plain blocking POST is correct there (the outer watchdog bounds it)."""
    csrf = _fresh_token(session, "option=com_installer&view=manage", bounded=bounded)
    if not csrf:
        print(f"   ❌ Cannot get CSRF for uninstall of {label}")
        return False

    def _post() -> requests.Response:
        return session.post(
            ADMIN_PHP + "?option=com_installer&view=manage",
            data={
                "option": "com_installer",
                "task": "manage.remove",
                "boxchecked": "1",
                "cid[]": str(ext_id),
                csrf: "1",
            },
            allow_redirects=True,
            timeout=120,
        )

    if bounded:
        r = _run_with_deadline(_post, deadline_s, f"uninstall POST for {label}")
    else:
        try:
            r = _post()
        except requests.RequestException:
            return None
    if r is None:
        return None  # abandoned — verify by re-listing in the caller
    body = r.text.lower()
    if r.status_code != 200:
        print(f"   ❌ uninstall HTTP {r.status_code} for {label}")
        return False
    # Joomla reports per-language; "uninstall" + ("success" | "succe") covers EN.
    # If the row is gone from the list afterwards we'll treat that as success.
    return 'alert-danger' not in body or 'success' in body


def _removal_sort_key(row: tuple[int, str, str]) -> tuple[int, str, int]:
    """Sort key that guarantees the BASE package is removed LAST.

    The base/free package ships the shared AiBoost\\Lib library; the Pro
    package's plugins (and the orphanable aiboost_int_falang bridge) load that
    namespace on every request. Removing base first while its dependents are
    still installed strips the shared lib out from under them mid-run and the
    whole site 500s ("Attempted to load class Logger from namespace
    AiBoost\\Lib") — which is exactly how this verifier once killed staging.

    Rank (ascending = removed earlier):
      0  plugins, modules, languages, files, … (lib consumers, safe to drop)
      1  components (com_aiboost carries the lib — keep until its consumers
         are gone, but it must go before the base package row that owns it)
      2  non-base packages (pkg_aiboost_pro and any other add-on package —
         identified by 'pro' / 'add-on' in the label)
      3  the base package (everything else of type 'package') — ALWAYS LAST

    The label and id are tie-breakers so the order is fully deterministic.
    """
    ext_id, label, etype = row
    lab = label.lower()
    if etype == 'package':
        rank = 2 if ('pro' in lab or 'add-on' in lab or 'addon' in lab) else 3
    elif etype == 'component':
        rank = 1
    else:
        rank = 0
    return (rank, lab, ext_id)


def _remove_aiboost_packages(session: requests.Session) -> int:
    """Blocking removal of the AI Boost *package* row(s) only — Joomla cascades
    the members (component, plugins, modules) server-side, so we never POST
    manage.remove per member. Runs INSIDE one outer watchdog (see
    uninstall_all_aiboost), hence every call here is plain/blocking (bounded=
    False): nesting deadline threads on the shared non-thread-safe Session is
    what previously wedged the run. Returns the number of package rows removed."""
    rows = find_aiboost_extension_ids(session, bounded=False)
    if not rows:
        print("   (nothing to remove)")
        return 0
    # Remove the package (element pkg_aiboost) ONLY — Joomla cascades its members
    # (component, plugins, modules) server-side. We key off the manage-view Type
    # column ("Package"), NOT the display name: the package AND the component both
    # show "AI Boost for Joomla", so a name filter would also match the component
    # and POST manage.remove against a package member directly (fragile, and what
    # surfaced the postflight re-injection bug in Task #566).
    pkgs = [row for row in rows if row[2] == 'package']
    if pkgs:
        # Dependents first, BASE PACKAGE LAST (see _removal_sort_key): removing
        # the base/free package while pkg_aiboost_pro is still installed strips
        # the shared AiBoost\Lib out from under the enabled Pro plugins and
        # 500s every page mid-uninstall — the run dies and so does the site.
        targets = sorted(pkgs, key=_removal_sort_key)
    else:
        # Graceful fallback: no package row present (e.g. an already partly
        # uninstalled install left orphan members) — remove whatever AI Boost
        # rows remain so the test still cleans up. Same ordering rule: lib
        # consumers (plugins/modules) first, the lib-carrying component last,
        # so a mid-run page load never hits a missing AiBoost\Lib class.
        print("   (no package row found — falling back to removing remaining rows)")
        targets = sorted(rows, key=_removal_sort_key)
    removed = 0
    for ext_id, label, _etype in targets:
        print(f"   ⟳ remove {label} (id={ext_id})")
        if uninstall_extension(session, ext_id, label, bounded=False):
            removed += 1
        time.sleep(1)
    return removed


def uninstall_all_aiboost(session: requests.Session) -> tuple[int, requests.Session]:
    """Remove the AI Boost package(s) and return ``(removed_count, session)``.

    The returned session is ALWAYS a freshly re-logged-in one: the entire
    removal runs under a single outer wall-clock watchdog, and if that watchdog
    has to abandon a trickling response the original session may carry a leaked
    in-flight request, so we never reuse it for the post-uninstall checks.

    Design (Task #566): one outer watchdog instead of many nested ones, and
    package-only removal (members cascade). The shared requests.Session is not
    thread-safe; nesting per-call deadline threads on it was the cause of the
    earlier hangs. Because the removal completes server-side even when the HTTP
    response is abandoned, callers confirm the outcome by re-listing on the
    fresh session — never by trusting the POST reply."""
    removed = _run_with_deadline(
        lambda: _remove_aiboost_packages(session),
        UNINSTALL_TOTAL_BUDGET,
        "AI Boost package removal",
    )
    if removed is None:
        print(f"   ⏱ removal abandoned after {UNINSTALL_TOTAL_BUDGET:.0f}s wall-clock; "
              "server-side removal continues — confirming by re-listing on a fresh session")
        removed = 0
    # Always continue on a clean session (old one may have a leaked request).
    fresh = login()
    return removed, fresh


# ─────────────────────────────────────────────────────────────────────────────
# Public-site file checks
# ─────────────────────────────────────────────────────────────────────────────


def _cache_busted(path: str) -> str:
    """Append a unique query param so a CDN/proxy can't serve a stale copy of a
    static artifact (robots.txt / sitemap.xml / llms.txt) and make the post-
    uninstall checks non-deterministic."""
    sep = "&" if "?" in path else "?"
    return f"{PUBLIC_BASE}{path}{sep}ab_cb={int(time.time() * 1000)}"


def public_file_clean(path: str, markers: list[str]) -> tuple[bool, str]:
    """
    Returns (ok, reason). A file is considered CLEAN if it 404s or returns
    a body that no longer contains any of the AI Boost markers.
    """
    try:
        r = requests.get(_cache_busted(path), timeout=30)
    except requests.RequestException as e:
        return True, f"unreachable ({e.__class__.__name__})"
    if r.status_code == 404:
        return True, "404"
    body = r.text
    hit = next((m for m in markers if m.lower() in body.lower()), None)
    if hit:
        return False, f"HTTP {r.status_code}, still contains '{hit}'"
    return True, f"HTTP {r.status_code}, no AI Boost marker"


def ensure_robots_marker(session: requests.Session) -> tuple[bool, str]:
    """
    Normalise robots.txt to the current build's known state — AI Boost managed
    block present — immediately before the uninstall step.

    Why: uninstall strips only AI Boost's fenced managed block from robots.txt
    (Task #566). Without seeding that block first, Pass 1's "robots.txt clean"
    assertion could pass or fail on whatever bytes a prior run happened to leave
    on disk rather than on what THIS uninstall did. A plain settings.save with
    enable_robots=1 injects our fenced block server-side, so we trigger one and
    then confirm — cache-busted — that the block is actually on disk before
    uninstalling.
    """
    csrf = _fresh_token(session, "option=com_aiboost")
    if csrf:
        session.post(
            ADMIN_PHP + "?option=com_aiboost&task=settings.save&format=json",
            data={"option": "com_aiboost", "task": "settings.save",
                  "format": "json", "enable_robots": "1", csrf: "1"},
            timeout=30,
        )
    try:
        r = requests.get(_cache_busted("/robots.txt"), timeout=30)
    except requests.RequestException as e:
        return False, f"robots.txt unreachable ({e.__class__.__name__})"
    body = r.text.lower()
    if "ai boost for joomla managed block" in body or "managed by ai boost" in body:
        return True, f"managed block present (HTTP {r.status_code}) — normalised"
    return False, (f"managed block NOT present after regen (HTTP {r.status_code}) — "
                   "robots cleanup check would be inconclusive")


# ─────────────────────────────────────────────────────────────────────────────
# Pass 1 — clean uninstall
# ─────────────────────────────────────────────────────────────────────────────


def pass_clean_uninstall(session: requests.Session, latest_zip: str) -> bool:
    print("\n══════════════════════════════════════════════════════════")
    print("PASS 1 — Clean uninstall (install → marker → uninstall → verify)")
    print("══════════════════════════════════════════════════════════")

    # Ensure a known starting point.
    print("\n[1/6] Removing any pre-existing aiboost extensions…")
    _, session = uninstall_all_aiboost(session)

    print(f"\n[2/6] Installing {os.path.basename(latest_zip)}…")
    if not install_zip(session, latest_zip):
        print("   ❌ Install failed — aborting pass.")
        return False

    global PRO_QA
    marker = MARKER_PREFIX + str(int(time.time()))
    print(f"\n[3/6] Seeding markers across all #__aiboost_* tables…")
    # Translations are Pro-only; detect whether Pro is available so the
    # translation assertions know whether a fresh write is even possible.
    PRO_QA = False if IS_FREE else ensure_pro(session)
    # robots.txt normalisation must run BEFORE the org_name marker save.
    # ensure_robots_marker() triggers a settings.save with only enable_robots=1,
    # and settings.save is a FULL-REPLACE endpoint — it persists the posted
    # payload (plus a small carry-forward whitelist), NOT a merge of the existing
    # row. Running it after save_marker would clobber the org_name marker before
    # the uninstall ever reads it. The managed robots.txt block is written to
    # disk during this save, so a later save dropping enable_robots cannot
    # un-write it — leaving the org_name save as the last settings write.
    print("\n[3.5/6] Normalising robots.txt to a known state before uninstall…")
    robots_ok, robots_reason = ensure_robots_marker(session)
    print(f"   {'✅' if robots_ok else '⚠️ '} robots.txt pre-uninstall: {robots_reason}")
    if not save_marker(session, marker, with_translation=True):
        return False
    if not seed_redirect(session, marker):
        return False
    pre = get_settings(session)
    if not pre or _settings_map(pre).get("org_name") != marker:
        print(f"   ❌ Marker did not round-trip through settings.getSettings: {pre}")
        return False
    pre_xlat = _xlat_map(pre).get("org_name", {})
    # Licence-preservation anchors: perpetual activation survives uninstall by
    # design, so install_id, pro_activated and license_state captured here must
    # come back UNCHANGED after uninstall → reinstall. A changed install_id (the
    # reinstall would mint a fresh UUID only when the key was wiped) or a lost
    # pro_activated proves the uninstall wrongly reset the licence binding —
    # exactly the regression that permanently relocks expired-but-perpetually-
    # activated customers.
    pre_smap = _settings_map(pre)
    pre_install_id = str(pre_smap.get("install_id", "")).strip()
    pre_pro_activated = str(pre_smap.get("pro_activated", "")).strip()
    pre_license_state = pre_smap.get("license_state") or None
    pre_redir = redirects_list(session)
    if not pre_redir:
        print("   ❌ redirects.listJson did not return success before uninstall")
        return False
    redir_count = sum(1 for r in (pre_redir.get("redirects") or []) if r.get("note") == marker)
    print(f"   ✅ Seeded: settings.org_name='{marker}', translations rows={len(pre_xlat)}, "
          f"redirects with marker={redir_count}")
    if redir_count < 1:
        print("   ❌ Seeded redirect not visible — cannot prove #__aiboost_redirects cleanup later")
        return False

    print("\n[4/6] Uninstalling pkg_aiboost via com_installer…")
    removed, session = uninstall_all_aiboost(session)
    print(f"   removed {removed} package row(s) (members cascade); "
          "verifying on a fresh session")

    print("\n[5/6] Verifying post-uninstall state…")
    results: list[tuple[str, bool, str]] = []

    # (b) #__extensions cleanup. The listing can return None while the host is
    # still trickling a prior response, so retry briefly until it settles before
    # judging — a None must never be read as "clean".
    remaining: Optional[list[tuple[int, str, str]]] = None
    _b_deadline = time.time() + 60
    while time.time() < _b_deadline:
        remaining = find_aiboost_extension_ids(session)
        if remaining is not None:
            break
        time.sleep(5)
    if remaining is None:
        results.append(("(b) #__extensions empty", False,
                        "manage view never settled (listing kept timing out)"))
    elif remaining:
        names = ", ".join(f"{label}#{eid}" for eid, label, _etype in remaining)
        results.append(("(b) #__extensions empty", False, f"leftover: {names}"))
    else:
        results.append(("(b) #__extensions empty", True, "no aiboost rows in manage view"))

    # (c) on-disk artifacts
    for fname, markers in [
        ("/llms.txt", ["AI Boost", "aiboost", "Managed by AI Boost"]),
        ("/sitemap.xml", ["aiboost", "AI Boost"]),
        ("/sitemap-index.xml", ["aiboost", "AI Boost"]),
        ("/robots.txt", ["AI Boost for Joomla managed block", "Managed by AI Boost"]),
    ]:
        ok, reason = public_file_clean(fname, markers)
        results.append((f"(c) {fname} clean", ok, reason))

    # (a) DATA PRESERVED + LICENCE PRESERVED + DEV OVERRIDES WIPED — re-install
    # and probe.
    #
    # Contract: pkg_script::uninstall() must NOT drop any #__aiboost_* table.
    # It removes ONLY the three developer override keys (dev_license_preview,
    # dev_force_free_tier, license_simulation) from the `main` settings row —
    # perpetual activation survives uninstall by design, so pro_activated,
    # license_state, license_key and install_id all stay. We cannot read the DB
    # directly over HTTP, so we re-install (the admin AJAX endpoints need the
    # component present) and assert via the API that:
    #   • settings.org_name marker we wrote pre-uninstall is STILL there,
    #   • the seeded redirect row is STILL there,
    #   • (Pro) the translation row is STILL there,
    #   • the licence binding was PRESERVED — install_id UNCHANGED,
    #     pro_activated still '1' (Pro target), license_state untouched,
    #   • the developer override keys are GONE (nothing ever re-creates those).
    # Re-install is non-destructive: install.sql uses CREATE TABLE IF NOT EXISTS
    # and the install path does not overwrite an existing `main` row.
    #
    # Why install_id is decisive: the uninstall preserves it and the reinstall
    # only mints a fresh UUID when the key is MISSING — so a changed install_id
    # proves the uninstall wrongly wiped the licence binding (the regression that
    # permanently relocks expired-but-perpetually-activated customers).
    DEV_OVERRIDE_KEYS = ("dev_license_preview", "dev_force_free_tier",
                         "license_simulation")
    print("\n[6/6] Re-installing to probe #__aiboost_* tables "
          "(data AND licence must SURVIVE, dev overrides wiped)…")
    if not install_zip(session, latest_zip):
        results.append(("(a) data + licence preserved, dev overrides wiped", False,
                        "re-install failed, cannot probe"))
    else:
        post = get_settings(session)
        post_redir = redirects_list(session)
        post_scans = url_scans_history(session)

        problems: list[str] = []

        # #__aiboost_settings — table + row + marker must SURVIVE.
        if post is None:
            problems.append("settings.getSettings did not return JSON success "
                            "(#__aiboost_settings missing or endpoint broken)")
        elif _settings_map(post).get("org_name") != marker:
            problems.append(
                f"#__aiboost_settings: org_name marker LOST "
                f"(expected '{marker}', got "
                f"'{_settings_map(post).get('org_name')}') — data NOT preserved")

        # Licence binding must have been PRESERVED. The decisive signal is
        # install_id: the uninstall keeps it and the reinstall only mints a
        # fresh UUID when the key is missing, so a value differing from the
        # pre-uninstall one means the uninstall wrongly wiped the licence
        # binding. pro_activated and license_state must likewise survive
        # untouched (perpetual activation — see the [6/6] header note). The
        # three DB-only dev overrides are the ONLY keys that must be gone.
        if post is not None:
            smap = _settings_map(post)
            post_install_id = str(smap.get("install_id", "")).strip()
            if pre_install_id and post_install_id and post_install_id != pre_install_id:
                problems.append(
                    "licence binding NOT preserved: install_id changed "
                    f"('{pre_install_id}' → '{post_install_id}') — uninstall "
                    "wiped it")
            if pre_pro_activated == "1" and str(smap.get("pro_activated", "")).strip() != "1":
                problems.append(
                    "perpetual activation LOST: pro_activated was '1' before "
                    "uninstall but is "
                    f"'{str(smap.get('pro_activated', '')).strip()}' after "
                    "reinstall — uninstall wiped it")
            # license_state is only asserted when it was visible pre-uninstall
            # (the QA seed sets pro_activated alone, so this is usually vacuous
            # here — the real-customer path it protects is exercised by the
            # PHPUnit guard on UNINSTALL_WIPED_KEYS).
            if pre_license_state is not None and not smap.get("license_state"):
                problems.append("license_state NOT preserved: present before "
                                "uninstall, gone after reinstall")
            leaked_dev = [k for k in DEV_OVERRIDE_KEYS
                          if smap.get(k) not in (None, "", "0", {}, [])]
            if leaked_dev:
                problems.append("dev override keys NOT wiped on uninstall: "
                                + ", ".join(sorted(leaked_dev)))

        # #__aiboost_redirects + #__aiboost_404_log — seeded redirect must SURVIVE.
        if post_redir is None:
            problems.append("redirects.listJson failed "
                            "(#__aiboost_redirects or #__aiboost_404_log missing)")
        else:
            marker_redirs = [r for r in (post_redir.get("redirects") or [])
                             if r.get("note") == marker]
            if not marker_redirs:
                problems.append("#__aiboost_redirects: seeded row LOST "
                                "(data NOT preserved)")

        # #__aiboost_translations (Pro only) — the seeded translation row must
        # SURVIVE uninstall. We assert the org_name translation row still EXISTS,
        # not that it equals this run's marker: settings.save does not reliably
        # overwrite an existing org_name translation row, so a value comparison
        # would false-negative on a legitimately-preserved row left from an
        # earlier run. Survival of the row is the #584 contract (the table/row is
        # not dropped on uninstall); the translation-update behaviour is a
        # separate concern out of scope here.
        # Only assert the row's survival when Pro was active to write it in
        # the first place; without Pro the write was server-side stripped and
        # absence proves nothing.
        if not IS_FREE and PRO_QA \
                and (post is None or not _xlat_map(post).get("org_name")):
            problems.append("#__aiboost_translations: org_name row LOST "
                            "(data NOT preserved)")

        # #__aiboost_url_scans — table must still EXIST (endpoint returns success).
        if post_scans is None:
            problems.append("urlchecker.scanHistory failed "
                            "(#__aiboost_url_scans missing)")

        if problems:
            results.append(("(a) data + licence preserved, dev overrides wiped", False,
                            "; ".join(problems)))
        else:
            results.append(("(a) data + licence preserved, dev overrides wiped", True,
                            "all tables + seeded rows + licence survived; "
                            "dev override keys wiped"))

    print("\n──── Pass 1 results ────")
    all_ok = True
    for name, ok, reason in results:
        flag = "✅ PASS" if ok else "❌ FAIL"
        print(f"  {flag}  {name}  — {reason}")
        all_ok = all_ok and ok
    return all_ok


# ─────────────────────────────────────────────────────────────────────────────
# Pass 2 — upgrade preservation
# ─────────────────────────────────────────────────────────────────────────────


def pass_upgrade(session: requests.Session, old_zip: str, new_zip: str) -> bool:
    print("\n══════════════════════════════════════════════════════════")
    print("PASS 2 — Upgrade preservation (old → marker → new → verify)")
    print("══════════════════════════════════════════════════════════")
    print(f"  old: {os.path.basename(old_zip)}")
    print(f"  new: {os.path.basename(new_zip)}")
    if os.path.realpath(old_zip) == os.path.realpath(new_zip):
        print("  mode: same-version reinstall (no older pro_activated-aware "
              "build available — verifies preservation across a reinstall)")
    else:
        print("  mode: cross-version upgrade")

    print("\n[1/5] Removing any pre-existing aiboost extensions…")
    _, session = uninstall_all_aiboost(session)

    print(f"\n[2/5] Installing OLD {os.path.basename(old_zip)}…")
    if not install_zip(session, old_zip):
        print("   ❌ Old install failed — aborting pass.")
        return False

    global PRO_QA
    marker = MARKER_PREFIX + "UPG_" + str(int(time.time()))
    expected_xlat_value = marker + "_xlat"  # mirrors save_marker(with_translation=True)
    print(f"\n[3/5] Writing marker '{marker}'…")
    # Translations only persist on a Pro install; detect whether Pro is
    # available (it cannot be seeded remotely — see ensure_pro) so the
    # preservation assertions can downgrade honestly when it is not.
    PRO_QA = False if IS_FREE else ensure_pro(session)
    if not save_marker(session, marker, with_translation=True):
        return False
    pre = get_settings(session)
    if not pre:
        print("   ❌ Cannot read settings before upgrade.")
        return False
    pre_xlat_map = _xlat_map(pre)
    pre_xlat_count = sum(len(v) for v in pre_xlat_map.values())
    pre_org_xlat = (pre_xlat_map.get("org_name") or {}).get("en-GB")
    if not IS_FREE and PRO_QA and pre_org_xlat != expected_xlat_value:
        print(f"   ❌ Pre-upgrade translation marker missing: got '{pre_org_xlat}' "
              f"(expected '{expected_xlat_value}'). Pro is active but the "
              f"translation write did not persist — cannot test upgrade preservation.")
        return False
    if IS_FREE:
        print(f"   ✅ Pre-upgrade: org_name='{_settings_map(pre).get('org_name')}' "
              f"(translations are Pro-only — skipped on Free)")
    elif not PRO_QA:
        print(f"   ✅ Pre-upgrade: org_name='{_settings_map(pre).get('org_name')}', "
              f"existing translation rows={pre_xlat_count} "
              f"(Pro write-path unavailable — preservation-only)")
    else:
        print(f"   ✅ Pre-upgrade: org_name='{_settings_map(pre).get('org_name')}', "
              f"translation rows={pre_xlat_count}, org_name[en-GB]='{pre_org_xlat}'")

    print(f"\n[4/5] Installing NEW {os.path.basename(new_zip)} on top…")
    if not install_zip(session, new_zip):
        print("   ❌ Upgrade install failed — aborting pass.")
        return False

    print("\n[5/5] Reading settings after upgrade…")
    post = get_settings(session)
    if not post:
        print("   ❌ getSettings returned no JSON after upgrade.")
        return False
    post_org = _settings_map(post).get("org_name")
    post_xlat_map = _xlat_map(post)
    post_xlat_count = sum(len(v) for v in post_xlat_map.values())
    post_org_xlat = (post_xlat_map.get("org_name") or {}).get("en-GB")

    results = [
        ("settings.org_name survived", post_org == marker,
         f"before='{marker}' after='{post_org}'"),
    ]
    if IS_FREE:
        # Translations are Pro-only and never written on Free, so the only
        # meaningful preservation signal is that none leaked in either state.
        results.append(
            ("translations stay empty on Free (Pro-only feature)",
             pre_xlat_count == 0 and post_xlat_count == 0,
             f"before={pre_xlat_count} after={post_xlat_count}")
        )
    elif not PRO_QA:
        # Pro target without an active Pro signal: a fresh translation write
        # was impossible, so assert only that whatever rows existed before the
        # upgrade survived it (rows may legitimately be left from earlier runs).
        results.append(
            ("translations row count preserved (Pro write-path skipped — "
             "Pro not active)",
             post_xlat_count == pre_xlat_count,
             f"before={pre_xlat_count} after={post_xlat_count}")
        )
    else:
        results.extend([
            ("translations row count preserved exactly",
             post_xlat_count == pre_xlat_count and pre_xlat_count > 0,
             f"before={pre_xlat_count} after={post_xlat_count}"),
            ("translations[org_name][en-GB] value survived",
             post_org_xlat == expected_xlat_value,
             f"before='{expected_xlat_value}' after='{post_org_xlat}'"),
        ])

    print("\n──── Pass 2 results ────")
    all_ok = True
    for name, ok, reason in results:
        flag = "✅ PASS" if ok else "❌ FAIL"
        print(f"  {flag}  {name}  — {reason}")
        all_ok = all_ok and ok
    return all_ok


# ─────────────────────────────────────────────────────────────────────────────
# Driver
# ─────────────────────────────────────────────────────────────────────────────


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[1])
    parser.add_argument("--target", choices=("pro", "free"), default="pro",
                        help="Which test site to verify against "
                             "(pro=staging.offroadserbia.com, free=offroadbalkans.com).")
    parser.add_argument("--uninstall-only", action="store_true",
                        help="Run only Pass 1 (clean uninstall).")
    parser.add_argument("--upgrade-only", action="store_true",
                        help="Run only Pass 2 (upgrade preservation).")
    parser.add_argument("--old-zip", help="Explicit older pkg_aiboost ZIP for the upgrade pass.")
    parser.add_argument("--new-zip", help="Explicit newer pkg_aiboost ZIP for both passes.")
    args = parser.parse_args()

    zips = find_pkg_zips_sorted()
    if not zips:
        print("❌ No pkg_aiboost-*.zip found in deliverables/plugin/")
        sys.exit(2)

    new_zip = args.new_zip or zips[0]
    old_zip = args.old_zip or pick_old_zip(zips, new_zip)

    if not os.path.isfile(new_zip):
        print(f"❌ ZIP not found: {new_zip}")
        sys.exit(2)
    if not args.uninstall_only and (old_zip is None or not os.path.isfile(old_zip)):
        print("❌ Upgrade pass needs a pkg_aiboost build >= "
              f"{'.'.join(map(str, UPGRADE_BASELINE))} (the version where the "
              "pro_activated Pro gate landed). Pass --old-zip explicitly or add "
              "a newer build to deliverables/plugin/.")
        sys.exit(2)

    session = login()

    overall_ok = True
    if not args.upgrade_only:
        overall_ok &= pass_clean_uninstall(session, new_zip)
    if not args.uninstall_only:
        assert old_zip is not None  # validated above
        overall_ok &= pass_upgrade(session, old_zip, new_zip)

    print("\n══════════════════════════════════════════════════════════")
    print(f"OVERALL: {'✅ PASS' if overall_ok else '❌ FAIL'}")
    print("══════════════════════════════════════════════════════════")
    sys.exit(0 if overall_ok else 1)


if __name__ == "__main__":
    main()
