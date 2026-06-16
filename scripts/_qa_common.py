#!/usr/bin/env python3
"""
AI Boost — shared QA harness library (Plan 3).

Factors the duplicated auth / HTTP / settings-round-trip / state logic out of
_fetch_site_state.py, verify-schema-fields.py, site-admin.py and
verify-clean-uninstall.py into ONE import surface that every Plan 3 verification
script builds on.

Design rules baked in here (verified against source — see docs/internal):
  * Settings are mutated ONLY through `import.upload` (merge over existing).
    `settings.save` is NEVER used: it rebuilds the whole blob from the posted
    form and silently drops every key not posted.
  * Pro / licence / dev-override state CANNOT be flipped over HTTP via import:
    ImportController::IMPORT_DENYLIST (= SYSTEM_PRESERVED_KEYS) strips them.
    The ONLY HTTP lever is `settings.simulatorSave`, and ONLY when the target
    site has Joomla Debug (JDEBUG) ON. `simulator_get()` detects that.
  * `redirects.clear404` TRUNCATEs the 404 log — this module deliberately does
    NOT wrap it. 404 data is read from `redirects.listJson` (log404 / total404).
  * Every front-end fetch is cache-busted (?ab_qa=<ts> + no-cache headers) to
    defeat LiteSpeed.

This module is import-only for the harness, but also runnable as a read-only
self-check:  python _creds_run.py scripts/_qa_common.py --target staging

@copyright (C) 2025 AI Boost (aiboostnow.com). GPL v2 or later.
"""

from __future__ import annotations

import json
import os
import re
import sys
import time
import urllib.parse
from contextlib import contextmanager

import requests

# ── Constants ────────────────────────────────────────────────────────────────

STATE_DIR = ".local/state"

DEFAULT_LOGIN_DELAY = 2      # Joomla session settle after login POST
DEFAULT_POST_DELAY = 2       # let a settings write land before re-fetching
DEFAULT_INTER_OP_DELAY = 1   # polite spacing between requests (~1 req/s)

OPTION = "com_aiboost"

# license_simulation states / SKUs accepted by settings.simulatorSave.
# Mirrors PluginRegistry::SIM_STATES / SIM_SKUS (read live via simulator_get()
# when available; these are the build-time fallbacks).
SIM_STATES = ("active", "expired", "disabled", "not_licensed")
SIM_SKUS = ("schema", "og", "hreflang", "code", "aeo", "bundle", "int_falang", "int_yootheme")


# ── Console ──────────────────────────────────────────────────────────────────

def setup_console_utf8() -> None:
    """Make stdout/stderr UTF-8 so the emoji status glyphs survive cp1252."""
    for stream in (sys.stdout, sys.stderr):
        try:
            stream.reconfigure(encoding="utf-8", line_buffering=True)  # type: ignore[attr-defined]
        except Exception:  # pragma: no cover - older Python / redirected stream
            pass


# ── Environment / target / SSL ───────────────────────────────────────────────

# target -> (URL spec, USER var, PASS var, SSL-bypass var)
# URL spec is "env:VAR" (read the admin URL from that env var) or a literal URL.
# SSL note: _creds_run.py pops AIBOOST_NO_SSL_VERIFY for the real-cert hosts, so
# the ml + testmyweb targets carry their OWN flag that is NOT popped.
_TARGETS = {
    "staging": ("env:STAGING_URL", "STAGING_ADMIN_USER", "STAGING_ADMIN_PASS", "AIBOOST_NO_SSL_VERIFY"),
    "free":    ("env:FREE_URL", "FREE_ADMIN_USER", "FREE_ADMIN_PASS", "AIBOOST_NO_SSL_VERIFY"),
    "ml":      ("env:ML_URL", "ML_ADMIN_USER", "ML_ADMIN_PASS", "ML_NO_SSL_VERIFY"),
    # testmyweb.info matrix (J5/J6 × Free/Pro), shared creds, self-signed TLS.
    "j5free":  ("https://joomla5-free.testmyweb.info/administrator/", "TESTMYWEB_ADMIN_USER", "J5FREE_ADMIN_PASS", "TESTMYWEB_NO_SSL_VERIFY"),  # j5free has its OWN password (4 marks) + third-party addons (Admin Tools/Falang/Tassos)
    "j5pro":   ("https://joomla5-pro.testmyweb.info/administrator/",  "TESTMYWEB_ADMIN_USER", "TESTMYWEB_ADMIN_PASS", "TESTMYWEB_NO_SSL_VERIFY"),
    "j6free":  ("https://joomla6-free.testmyweb.info/administrator/", "TESTMYWEB_ADMIN_USER", "TESTMYWEB_ADMIN_PASS", "TESTMYWEB_NO_SSL_VERIFY"),
    "j6pro":   ("https://joomla6-pro.testmyweb.info/administrator/",  "TESTMYWEB_ADMIN_USER", "TESTMYWEB_ADMIN_PASS", "TESTMYWEB_NO_SSL_VERIFY"),
}

TARGETS = tuple(_TARGETS.keys())


def _resolve_url(spec: str) -> str:
    return os.environ.get(spec[4:], "") if spec.startswith("env:") else spec


def env(target: str) -> tuple[str, str, str]:
    """Return (admin_url, user, passwd) for a target, or exit loudly."""
    if target not in _TARGETS:
        sys.exit(f"unknown target {target!r} (expected one of {', '.join(TARGETS)})")
    url_spec, user_var, pass_var, _ = _TARGETS[target]
    url = _resolve_url(url_spec)
    missing = []
    if not url:
        missing.append(url_spec)
    missing += [v for v in (user_var, pass_var) if not os.environ.get(v)]
    if missing:
        sys.exit(
            f"❌ target {target!r} is missing env var(s): {', '.join(missing)}.\n"
            f"   Run via:  python _creds_run.py scripts/<script>.py --target {target}"
        )
    return url, os.environ[user_var], os.environ[pass_var]


def ssl_verify_for(target: str) -> bool:
    """False (skip TLS verification) when the target's NO_SSL flag is set."""
    _, _, _, ssl_var = _TARGETS.get(target, (None, None, None, "AIBOOST_NO_SSL_VERIFY"))
    return not bool(os.environ.get(ssl_var))


def base_url(admin_url: str) -> str:
    parsed = urllib.parse.urlparse(admin_url)
    return f"{parsed.scheme}://{parsed.netloc}"


# ── Auth ─────────────────────────────────────────────────────────────────────

def extract_csrf(html: str) -> str | None:
    """Pull a Joomla form-token (32-hex, value '1') from any admin payload.

    Tries the Vue SPA window vars first, then the classic hidden-input forms,
    then the Joomla page options JSON — covering every AI Boost admin surface.
    """
    for p in (
        r'aiBoostToken\s*=\s*["\']?([a-f0-9]{32})',
        r'csrfToken["\']?\s*[:=]\s*["\']([a-f0-9]{32})',
        r'tokenName["\']?\s*[:=]\s*["\']([a-f0-9]{32})',
        r'name="([a-f0-9]{32})"[^>]*value="1"',
        r'value="1"[^>]*name="([a-f0-9]{32})"',
        r'"csrf\.token"\s*:\s*"([a-f0-9]{32})"',
    ):
        m = re.search(p, html)
        if m:
            return m.group(1)
    return None


def follow_js_redirect(session: requests.Session, response: requests.Response) -> requests.Response:
    """Joomla sometimes returns a JS `document.location.href=` bounce — follow it."""
    m = re.search(r'document\.location\.href=["\']([^"\']+)["\']', response.text)
    if m:
        url = m.group(1).replace("\\/", "/")
        return session.get(url, allow_redirects=True, timeout=30)
    return response


def login(admin_url: str, user: str, passwd: str, verify: bool = True) -> tuple[requests.Session, str]:
    """Log into a Joomla admin and return (session, admin_php_url)."""
    base = base_url(admin_url)
    admin_php = f"{base}/administrator/index.php"
    s = requests.Session()
    s.headers.update({"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AiBoostQA/1.0"})
    if not verify:
        import urllib3
        urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)
        s.verify = False

    r = s.get(admin_url, allow_redirects=True, timeout=30)
    r = follow_js_redirect(s, r)
    if not extract_csrf(r.text):
        r = s.get(admin_php, allow_redirects=True, timeout=30)
        r = follow_js_redirect(s, r)
    csrf = extract_csrf(r.text)
    if not csrf:
        sys.exit(f"❌ No CSRF token at login (HTTP {r.status_code}) at {r.url[:90]}")
    time.sleep(DEFAULT_LOGIN_DELAY)
    s.post(
        r.url,
        data={"username": user, "passwd": passwd, "option": "com_login",
              "task": "login", "return": "aW5kZXgucGhw", csrf: "1"},
        allow_redirects=True, timeout=30,
    )
    time.sleep(DEFAULT_LOGIN_DELAY)
    return s, admin_php


def connect(target: str) -> tuple[requests.Session, str, str]:
    """env → SSL policy → login. Returns (session, admin_php, base_url)."""
    admin_url, user, passwd = env(target)
    verify = ssl_verify_for(target)
    s, admin_php = login(admin_url, user, passwd, verify=verify)
    return s, admin_php, base_url(admin_url)


# ── Lenient JSON ─────────────────────────────────────────────────────────────

def loads_lenient(text: str):
    """json.loads that tolerates a stray PHP notice/whitespace around the JSON."""
    text = (text or "").strip()
    try:
        return json.loads(text)
    except Exception:
        pass
    # Grab the outermost {...} or [...] span.
    for opener, closer in (("{", "}"), ("[", "]")):
        start = text.find(opener)
        end = text.rfind(closer)
        if start != -1 and end > start:
            try:
                return json.loads(text[start:end + 1])
            except Exception:
                continue
    return None


# ── CSRF for write endpoints ─────────────────────────────────────────────────

_CSRF_VIEWS = (
    "?option=com_aiboost&view=import",
    "?option=com_aiboost",
    "?option=com_aiboost&view=settings",
    "?option=com_aiboost&view=app",
)


def get_csrf(session: requests.Session, admin_php: str) -> str:
    """Fetch a fresh Joomla form-token from an AI Boost admin view."""
    for view in _CSRF_VIEWS:
        csrf = extract_csrf(session.get(admin_php + view, timeout=60).text)
        if csrf:
            return csrf
    sys.exit("❌ Could not obtain a CSRF token from any AI Boost admin view.")


def _task_url(admin_php: str, task: str) -> str:
    return f"{admin_php}?option={OPTION}&task={task}&format=json"


def get_task(session: requests.Session, admin_php: str, task: str, params: dict | None = None) -> dict:
    """GET an AI Boost JSON task endpoint (read-only; no token needed)."""
    url = _task_url(admin_php, task)
    if params:
        url += "&" + urllib.parse.urlencode(params)
    r = session.get(url, timeout=60)
    data = loads_lenient(r.text)
    return data if isinstance(data, dict) else {"success": False, "_raw": r.text[:400], "_status": r.status_code}


def post_task(session: requests.Session, admin_php: str, task: str,
              data: dict | None = None, files: dict | None = None, csrf: str | None = None) -> dict:
    """POST an AI Boost JSON task endpoint with a CSRF token."""
    if csrf is None:
        csrf = get_csrf(session, admin_php)
    body = {"option": OPTION, "task": task, csrf: "1"}
    if data:
        body.update(data)
    r = session.post(_task_url(admin_php, task), data=body, files=files,
                     allow_redirects=True, timeout=120)
    result = loads_lenient(r.text)
    if isinstance(result, dict):
        return result
    # Fall back to a regex sniff of the success flag (covers HTML-wrapped JSON).
    m = re.search(r'"success"\s*:\s*(true|false)', r.text)
    return {"success": bool(m and m.group(1) == "true"), "_raw": r.text[:400], "_status": r.status_code}


# ── Admin operations ─────────────────────────────────────────────────────────

def export_full(session: requests.Session, admin_php: str) -> dict:
    """GET settings.export → full envelope {meta, params, translations}."""
    r = session.get(_task_url(admin_php, "settings.export"), timeout=60)
    data = loads_lenient(r.text)
    return data if isinstance(data, dict) else {}


def export_settings(session: requests.Session, admin_php: str) -> dict:
    """Just the params blob from settings.export."""
    return export_full(session, admin_php).get("params") or {}


def import_params(session: requests.Session, admin_php: str, params: dict, label: str = "",
                  csrf: str | None = None, quiet: bool = False) -> bool:
    """Merge a partial settings dict into the live blob via import.upload.

    Note: import MERGES (array_merge) and can never DELETE a key; license/dev
    keys in the denylist are silently dropped server-side.
    """
    if csrf is None:
        csrf = get_csrf(session, admin_php)
    payload = json.dumps({"meta": {"plugin": "pkg_aiboost"}, "params": params}).encode("utf-8")
    r = session.post(
        admin_php + f"?option={OPTION}&task=import.upload",
        data={"option": OPTION, "task": "import.upload", csrf: "1"},
        files={"ab_import_file": ("qa.json", payload, "application/json")},
        allow_redirects=True, timeout=120,
    )
    result = loads_lenient(r.text)
    if isinstance(result, dict):
        ok = bool(result.get("success"))
        msg = result.get("message", "")
    else:
        m = re.search(r'"success"\s*:\s*(true|false)', r.text)
        ok = bool(m and m.group(1) == "true")
        msg = "" if ok else r.text[:200]
    if not quiet:
        tag = f"[{label}] " if label else ""
        print(f"   {'✓' if ok else '✗'} import {tag}{'ok' if ok else msg}")
    return ok


def get_languages(session: requests.Session, admin_php: str) -> dict:
    """GET settings.getLanguages → {success, languages:[{lang_code,title,sef,image}], default_lang}."""
    return get_task(session, admin_php, "settings.getLanguages")


def get_capabilities(session: requests.Session, admin_php: str) -> dict:
    """GET settings.capabilities → the LIVE merged manifest {capabilities, fields:[...]}.

    `fields` includes runtime plugin fields (falang_*/yootheme_*/schema_pro) with
    tier/sku/integration/locked — the authoritative key universe for a live site.
    """
    return get_task(session, admin_php, "settings.capabilities")


def get_settings(session: requests.Session, admin_php: str) -> dict:
    """GET settings.getSettings → {settings, translations:{field_key:{lang:value}}}."""
    return get_task(session, admin_php, "settings.getSettings")


def health_rerun(session: requests.Session, admin_php: str, csrf: str | None = None) -> dict:
    """POST health.rerun → {success, score, checks:[{id,status,title,message,severity}]}."""
    return post_task(session, admin_php, "health.rerun", csrf=csrf)


def redirects_list(session: requests.Session, admin_php: str) -> dict:
    """GET redirects.listJson → {redirects:[...], log404:[{request_url,referrer,hits,...}], total404}."""
    return get_task(session, admin_php, "redirects.listJson")


def redirects_add(session: requests.Session, admin_php: str, from_url: str, to_url: str,
                  redirect_type: int = 301, note: str = "", csrf: str | None = None) -> dict:
    """POST redirects.add → {success, id}."""
    return post_task(session, admin_php, "redirects.add", data={
        "from_url": from_url, "to_url": to_url,
        "redirect_type": str(redirect_type), "note": note,
    }, csrf=csrf)


def redirects_delete(session: requests.Session, admin_php: str, rule_id: int, csrf: str | None = None) -> dict:
    """POST redirects.delete → {success}. (Safe — single row by id.)"""
    return post_task(session, admin_php, "redirects.delete", data={"id": str(rule_id)}, csrf=csrf)


def integrations_save_toggle(session: requests.Session, admin_php: str, key: str, enabled: bool,
                             csrf: str | None = None) -> dict:
    """POST integrations.saveToggle → flip integration_<key>_enabled (admin master switch only)."""
    return post_task(session, admin_php, "integrations.saveToggle", data={
        "integration": key, "enabled": "1" if enabled else "0",
    }, csrf=csrf)


def simulator_get(session: requests.Session, admin_php: str) -> dict:
    """POST settings.simulatorGet. JDEBUG detector.

    MUST be POST: guardSimulator() calls Session::checkToken() with no argument,
    which only accepts a POST-body token — a GET would fail the token check even
    on a JDEBUG-on site and be mis-read as JDEBUG-off.

    Returns {jdebug: bool, success, states, skus, simulation, capabilities}.
    When JDEBUG is OFF the controller hard-fails with success=false and a
    'only available when Joomla debug mode is on' message → jdebug=False.
    """
    data = post_task(session, admin_php, "settings.simulatorGet")
    msg = str(data.get("message", "")).lower()
    data["jdebug"] = bool(data.get("success")) and "debug mode" not in msg
    return data


def simulator_save(session: requests.Session, admin_php: str, simulation: dict, csrf: str | None = None) -> dict:
    """POST settings.simulatorSave with simulation[<sku>]=<state>. JDEBUG-only."""
    data = {f"simulation[{sku}]": state for sku, state in simulation.items()}
    return post_task(session, admin_php, "settings.simulatorSave", data=data, csrf=csrf)


# ── Front-end fetch helpers (all cache-busted) ───────────────────────────────

_NOCACHE = {"Cache-Control": "no-cache", "Pragma": "no-cache"}


def _bust(url: str) -> str:
    sep = "&" if "?" in url else "?"
    return f"{url}{sep}ab_qa={int(time.time())}"


def fetch_html(session: requests.Session, url: str, cache_bust: bool = True,
               headers: dict | None = None, follow_js: bool = True) -> str:
    if cache_bust:
        url = _bust(url)
    h = dict(_NOCACHE)
    if headers:
        h.update(headers)
    text = session.get(url, timeout=60, headers=h).text
    # Multilingual Joomla JS-redirects "/" → "/<lang>/" via document.location.href;
    # that stub has no content. Follow it once to reach the real page.
    if follow_js and len(text) < 4000:
        m = re.search(r'document\.location\.href=["\']([^"\']+)["\']', text)
        if m:
            target = m.group(1).replace("\\/", "/")
            if cache_bust:
                target = _bust(target)  # re-bust or LiteSpeed serves a stale /<lang>/ page
            text = session.get(target, timeout=60, headers=h).text
    return text


def fetch_text(session: requests.Session, base: str, path: str) -> tuple[str | None, int, str]:
    """Fetch a text artifact (robots/llms/sitemap). Returns (text|None, status, url)."""
    url = _bust(base.rstrip("/") + path)
    r = session.get(url, timeout=60, headers=_NOCACHE)
    return (r.text if r.status_code == 200 else None), r.status_code, url


def fetch_robots(session: requests.Session, base: str) -> tuple[str | None, int, str]:
    return fetch_text(session, base, "/robots.txt")


def fetch_sitemap(session: requests.Session, base: str, path: str = "/sitemap.xml") -> tuple[str | None, int, str]:
    return fetch_text(session, base, path)


def fetch_llms(session: requests.Session, base: str, path: str = "/llms.txt") -> tuple[str | None, int, str]:
    return fetch_text(session, base, path)


def fetch_jsonld(session: requests.Session, url: str) -> tuple[list, str]:
    """Return (flat list of all JSON-LD objects on a page, fetched_url).

    Shares fetch_html so it follows the multilingual "/" → "/<lang>/" JS redirect.
    """
    if not re.search(r"https?://", url):
        raise ValueError("fetch_jsonld needs an absolute URL")
    html = fetch_html(session, url, cache_bust=True)
    return jsonld_from_html(html), url


def jsonld_from_html(html: str) -> list:
    objs: list = []
    for block in re.findall(
        r'<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>',
        html, re.DOTALL | re.IGNORECASE,
    ):
        data = loads_lenient(block)
        if data is None:
            continue
        items = data.get("@graph") if isinstance(data, dict) and "@graph" in data else data
        objs.extend(items if isinstance(items, list) else [items])
    return objs


def find_type(objs: list, type_name: str) -> dict | None:
    for o in objs:
        if isinstance(o, dict):
            t = o.get("@type")
            if t == type_name or (isinstance(t, list) and type_name in t):
                return o
    return None


# ── State / snapshot / restore ───────────────────────────────────────────────

def snapshot_path(target: str, suffix: str = "") -> str:
    os.makedirs(STATE_DIR, exist_ok=True)
    name = f"{target}-snapshot{('-' + suffix) if suffix else ''}.json"
    return os.path.join(STATE_DIR, name)


def write_snapshot(target: str, params: dict, suffix: str = "") -> str:
    path = snapshot_path(target, suffix)
    with open(path, "w", encoding="utf-8") as f:
        json.dump(params, f, ensure_ascii=False, indent=2)
    return path


def read_snapshot(path: str) -> dict:
    with open(path, encoding="utf-8") as f:
        return json.load(f)


def restore_keys(session: requests.Session, admin_php: str, snapshot: dict, keys, *,
                 defaults: dict | None = None, label: str = "restore") -> bool:
    """Re-import the snapshot values for `keys`.

    import MERGES and cannot delete, so a key absent from the snapshot is reset
    to its manifest default when one is supplied, else to '' (logged).
    """
    defaults = defaults or {}
    restore = {}
    for k in keys:
        if k in snapshot:
            restore[k] = snapshot[k]
        elif k in defaults:
            restore[k] = defaults[k]
            print(f"   ⓘ {k} absent from snapshot — restoring manifest default {defaults[k]!r}")
        else:
            restore[k] = ""
            print(f"   ⚠️ {k} absent from snapshot and has no known default — restoring ''")
    return import_params(session, admin_php, restore, label=label)


@contextmanager
def settings_mutation(session: requests.Session, admin_php: str, target: str, touched_keys, *,
                      defaults: dict | None = None, label: str = ""):
    """Snapshot → yield params → restore `touched_keys` no matter what.

    Writes the FULL pre-run params to .local/state/<target>-snapshot-<label>.json
    so an interrupted run can be repaired with the harness's --restore-only mode.
    """
    snap = export_settings(session, admin_php)
    path = write_snapshot(target, snap, suffix=label or "mutation")
    print(f"   📦 snapshot ({len(snap)} keys) → {path}")
    try:
        yield snap
    finally:
        touched = list(touched_keys)
        print(f"   ♻️  restoring {len(touched)} touched key(s)…")
        ok = restore_keys(session, admin_php, snap, touched, defaults=defaults,
                          label=(label + ":restore") if label else "restore")
        if not ok:
            # A silently-failed restore would leave the live site mutated — make it loud.
            raise RuntimeError(
                f"CRITICAL: restore failed for {touched} (snapshot {path}); "
                f"re-run: python _creds_run.py scripts/verify-frontend-emission.py "
                f"--target {target} --restore-only")


# ── Self-check (read-only) ───────────────────────────────────────────────────

def _selfcheck(target: str) -> int:
    setup_console_utf8()
    s, admin_php, base = connect(target)
    print(f"🟢 connected: {base}  (ssl_verify={ssl_verify_for(target)})")
    params = export_settings(s, admin_php)
    print(f"📤 settings.export: {len(params)} params; schema_type={params.get('schema_type')!r}")
    langs = get_languages(s, admin_php)
    lang_list = langs.get("languages") or []
    print(f"🌐 languages: {[l.get('sef') for l in lang_list]} (default {langs.get('default_lang')})")
    sim = simulator_get(s, admin_php)
    print(f"🔧 JDEBUG simulator available: {sim.get('jdebug')} "
          f"({'HTTP Pro-flip OK' if sim.get('jdebug') else 'Pro state is DB-only on this target'})")
    caps = get_capabilities(s, admin_php)
    print(f"🧩 live fields: {len(caps.get('fields') or [])}; capabilities keys: {len(caps.get('capabilities') or {})}")
    return 0


if __name__ == "__main__":
    import argparse
    ap = argparse.ArgumentParser(description="Read-only connectivity self-check for the QA harness library.")
    ap.add_argument("--target", default="staging", choices=list(TARGETS))
    sys.exit(_selfcheck(ap.parse_args().target))
